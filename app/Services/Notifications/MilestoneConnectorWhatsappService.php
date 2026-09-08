<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Jobs\SendMilestoneConnectorWhatsappJob;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use Throwable;

class MilestoneConnectorWhatsappService
{
    public const TEMPLATE_KEY = 'milestone_connector';

    /**
     * Generate deterministic UUID for milestone notification delivery log idempotency.
     */
    public static function getDeterministicLogId(string $userId, string $templateKey = self::TEMPLATE_KEY, int $milestoneCount = 1): string
    {
        return Uuid::uuid5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', "notification_delivery.{$templateKey}.{$userId}.{$milestoneCount}")->toString();
    }

    /**
     * Trigger WhatsApp notification for first member introduction milestone.
     */
    public function handleFirstIntroduction(User $user, ?string $imageUrl = null): void
    {
        try {
            $user->refresh();

            $introducedCount = (int) ($user->members_introduced_count ?? 0);

            // Trigger only when count indicates this is the member's first introduction
            if ($introducedCount !== 1) {
                Log::info('[MilestoneConnectorWhatsappService] Skipped: Not the first introduction.', [
                    'user_id' => $user->id,
                    'members_introduced_count' => $introducedCount,
                ]);

                return;
            }

            $deterministicLogId = self::getDeterministicLogId((string) $user->id, self::TEMPLATE_KEY, 1);
            $shouldDispatch = false;

            if (Schema::hasTable('notification_delivery_logs')) {
                try {
                    $shouldDispatch = DB::transaction(function () use ($user, $deterministicLogId, $imageUrl, $introducedCount): bool {
                        // Check if deterministic log already exists
                        $existingLog = NotificationDeliveryLog::where('id', $deterministicLogId)->lockForUpdate()->first();
                        if ($existingLog) {
                            Log::info('[MilestoneConnectorWhatsappService] Skipped: Milestone already processed or queued for this member.', [
                                'user_id' => $user->id,
                                'log_id' => $deterministicLogId,
                                'status' => $existingLog->status,
                            ]);

                            return false;
                        }

                        // Check legacy logs if any exist for this user & template
                        $legacyExists = NotificationDeliveryLog::query()
                            ->where('user_id', (string) $user->id)
                            ->where('channel', 'whatsapp')
                            ->where('provider', self::TEMPLATE_KEY)
                            ->whereIn('status', ['sent', 'queued', 'pending', 'processing', 'failed'])
                            ->exists();

                        if ($legacyExists) {
                            Log::info('[MilestoneConnectorWhatsappService] Skipped: Legacy milestone delivery record exists.', [
                                'user_id' => $user->id,
                            ]);

                            return false;
                        }

                        // Atomically insert the pre-dispatch queued entry with the deterministic primary key
                        NotificationDeliveryLog::create([
                            'id' => $deterministicLogId,
                            'user_id' => (string) $user->id,
                            'channel' => 'whatsapp',
                            'provider' => self::TEMPLATE_KEY,
                            'status' => 'queued',
                            'request_payload' => [
                                'template_key' => self::TEMPLATE_KEY,
                                'introduced_count' => $introducedCount,
                                'image_url' => $imageUrl,
                            ],
                            'attempted_at' => now(),
                        ]);

                        return true;
                    });
                } catch (QueryException $qe) {
                    Log::info('[MilestoneConnectorWhatsappService] Duplicate dispatch race prevented by DB unique primary key.', [
                        'user_id' => $user->id,
                        'log_id' => $deterministicLogId,
                    ]);
                    $shouldDispatch = false;
                } catch (Throwable $dbEx) {
                    Log::error('[MilestoneConnectorWhatsappService] Error during milestone dispatch reservation: '.$dbEx->getMessage(), [
                        'user_id' => $user->id,
                        'exception' => $dbEx,
                    ]);
                    $shouldDispatch = false;
                }
            } else {
                $shouldDispatch = true;
            }

            if ($shouldDispatch) {
                // Dispatch job to send independently
                SendMilestoneConnectorWhatsappJob::dispatch((string) $user->id, $imageUrl);

                Log::info('[MilestoneConnectorWhatsappService] Dispatched SendMilestoneConnectorWhatsappJob.', [
                    'user_id' => $user->id,
                    'log_id' => $deterministicLogId,
                ]);
            }
        } catch (Throwable $e) {
            // Main flow must never fail because of WhatsApp handling
            Log::error('[MilestoneConnectorWhatsappService] Exception in handleFirstIntroduction: '.$e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Check whether milestone_connector WhatsApp has already been sent to this user.
     */
    public function isMilestoneProcessed(string $userId): bool
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return false;
        }

        $deterministicLogId = self::getDeterministicLogId($userId, self::TEMPLATE_KEY, 1);

        try {
            return NotificationDeliveryLog::query()
                ->where(function ($q) use ($userId, $deterministicLogId): void {
                    $q->where('id', $deterministicLogId)
                        ->orWhere(function ($sub) use ($userId): void {
                            $sub->where('user_id', $userId)
                                ->where('channel', 'whatsapp')
                                ->where('provider', self::TEMPLATE_KEY);
                        });
                })
                ->whereIn('status', ['sent', 'queued', 'pending', 'processing', 'failed'])
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Check whether milestone_connector WhatsApp has already been sent to this user.
     */
    public function alreadySent(string $userId): bool
    {
        return $this->isMilestoneProcessed($userId);
    }
}
