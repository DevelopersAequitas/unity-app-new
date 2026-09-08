<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Jobs\SendMilestoneCatalystWhatsappJob;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use Throwable;

class MilestoneCatalystWhatsappService
{
    public const TEMPLATE_KEY = 'pgu_catalyst_3';

    public const MILESTONE_COUNT = 3;

    public const MILESTONE_KEY = 'CATALYST';

    /**
     * Generate deterministic UUID for milestone notification delivery log idempotency.
     */
    public static function getDeterministicLogId(string $userId, string $templateKey = self::TEMPLATE_KEY, int $milestoneCount = self::MILESTONE_COUNT): string
    {
        return Uuid::uuid5('6ba7b810-9dad-11d1-80b4-00c04fd430c8', "notification_delivery.{$templateKey}.{$userId}.{$milestoneCount}")->toString();
    }

    /**
     * Trigger WhatsApp notification for CATALYST (3 member introductions) milestone.
     */
    public function handleCatalystMilestone(User $user, ?string $imageUrl = null): void
    {
        try {
            $user->refresh();

            $introducedCount = (int) ($user->members_introduced_count ?? 0);

            // Trigger only when user has reached or exceeded the CATALYST milestone threshold of 3
            if ($introducedCount < self::MILESTONE_COUNT) {
                Log::info('[MilestoneCatalystWhatsappService] Skipped: Referral count threshold not reached.', [
                    'user_id' => $user->id,
                    'members_introduced_count' => $introducedCount,
                    'required_threshold' => self::MILESTONE_COUNT,
                ]);

                return;
            }

            $deterministicLogId = self::getDeterministicLogId((string) $user->id, self::TEMPLATE_KEY, self::MILESTONE_COUNT);
            $shouldDispatch = false;

            if (Schema::hasTable('notification_delivery_logs')) {
                try {
                    $shouldDispatch = DB::transaction(function () use ($user, $deterministicLogId, $imageUrl, $introducedCount): bool {
                        // Check if deterministic log already exists
                        $existingLog = NotificationDeliveryLog::where('id', $deterministicLogId)->lockForUpdate()->first();
                        if ($existingLog) {
                            Log::info('[MilestoneCatalystWhatsappService] Skipped: CATALYST milestone already processed or queued for this member.', [
                                'user_id' => $user->id,
                                'log_id' => $deterministicLogId,
                                'status' => $existingLog->status,
                            ]);

                            return false;
                        }

                        // Check existing logs if any exist for this user & template
                        $alreadyExists = NotificationDeliveryLog::query()
                            ->where('user_id', (string) $user->id)
                            ->where('channel', 'whatsapp')
                            ->where('provider', self::TEMPLATE_KEY)
                            ->whereIn('status', ['sent', 'queued', 'pending', 'processing'])
                            ->exists();

                        if ($alreadyExists) {
                            Log::info('[MilestoneCatalystWhatsappService] Skipped: CATALYST milestone delivery record exists.', [
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
                                'milestone' => self::MILESTONE_KEY,
                                'milestone_count' => self::MILESTONE_COUNT,
                                'introduced_count' => $introducedCount,
                                'image_url' => $imageUrl,
                            ],
                            'attempted_at' => now(),
                        ]);

                        return true;
                    });
                } catch (QueryException $qe) {
                    Log::info('[MilestoneCatalystWhatsappService] Duplicate dispatch race prevented by DB unique primary key.', [
                        'user_id' => $user->id,
                        'log_id' => $deterministicLogId,
                    ]);
                    $shouldDispatch = false;
                } catch (Throwable $dbEx) {
                    Log::error('[MilestoneCatalystWhatsappService] Error during CATALYST milestone dispatch reservation: '.$dbEx->getMessage(), [
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
                SendMilestoneCatalystWhatsappJob::dispatch((string) $user->id, $imageUrl);

                Log::info('[MilestoneCatalystWhatsappService] Dispatched SendMilestoneCatalystWhatsappJob.', [
                    'user_id' => $user->id,
                    'log_id' => $deterministicLogId,
                    'milestone' => self::MILESTONE_KEY,
                ]);
            }
        } catch (Throwable $e) {
            // Main flow must never fail because of WhatsApp handling
            Log::error('[MilestoneCatalystWhatsappService] Exception in handleCatalystMilestone: '.$e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Check whether pgu_catalyst_3 WhatsApp has already been sent to this user.
     */
    public function isMilestoneProcessed(string $userId): bool
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return false;
        }

        $deterministicLogId = self::getDeterministicLogId($userId, self::TEMPLATE_KEY, self::MILESTONE_COUNT);

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
                ->whereIn('status', ['sent', 'queued', 'pending', 'processing'])
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Check whether pgu_catalyst_3 WhatsApp has already been sent to this user.
     */
    public function alreadySent(string $userId): bool
    {
        return $this->isMilestoneProcessed($userId);
    }
}
