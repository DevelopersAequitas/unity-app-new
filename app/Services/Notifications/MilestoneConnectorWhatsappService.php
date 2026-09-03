<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Jobs\SendMilestoneConnectorWhatsappJob;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MilestoneConnectorWhatsappService
{
    public const TEMPLATE_KEY = 'milestone_connector';

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

            $lockKey = "milestone_connector_dispatch_{$user->id}";
            $lock = Cache::lock($lockKey, 15);

            $lock->block(10, function () use ($user, $imageUrl, $introducedCount): void {
                // Check duplicate/idempotency before dispatching
                if ($this->isMilestoneProcessed((string) $user->id)) {
                    Log::info('[MilestoneConnectorWhatsappService] Skipped: Milestone already processed or queued for this member.', [
                        'user_id' => $user->id,
                    ]);

                    return;
                }

                // Record pre-dispatch queued entry to guarantee race-condition and database-level idempotency
                if (Schema::hasTable('notification_delivery_logs')) {
                    try {
                        NotificationDeliveryLog::create([
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
                    } catch (Throwable $logEx) {
                        Log::warning('[MilestoneConnectorWhatsappService] Could not write pre-dispatch log: '.$logEx->getMessage());
                    }
                }

                // Dispatch job to send independently
                SendMilestoneConnectorWhatsappJob::dispatch((string) $user->id, $imageUrl);

                Log::info('[MilestoneConnectorWhatsappService] Dispatched SendMilestoneConnectorWhatsappJob.', [
                    'user_id' => $user->id,
                ]);
            });
        } catch (Throwable $e) {
            // Main flow must never fail because of WhatsApp handling
            Log::error('[MilestoneConnectorWhatsappService] Exception in handleFirstIntroduction: '.$e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Check whether milestone_connector WhatsApp has already been queued, sent, or processed for this user.
     */
    public function isMilestoneProcessed(string $userId): bool
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return false;
        }

        try {
            return NotificationDeliveryLog::query()
                ->where('user_id', $userId)
                ->where('channel', 'whatsapp')
                ->where('provider', self::TEMPLATE_KEY)
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

