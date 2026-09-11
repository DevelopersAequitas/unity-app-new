<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Jobs\SendWearTheBadgeWhatsappJob;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use Throwable;

class WearTheBadgeWhatsappService
{
    public const TEMPLATE_KEY = 'wear_the_badge';

    public const UUID_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    /**
     * Generate deterministic UUID for WearTheBadge notification delivery log idempotency.
     */
    public static function getDeterministicLogId(string $userId, string $templateKey = self::TEMPLATE_KEY): string
    {
        return Uuid::uuid5(self::UUID_NAMESPACE, "wear_the_badge:{$userId}")->toString();
    }

    /**
     * Trigger WhatsApp notification for WearTheBadge if eligible and not already processed.
     */
    public function handleWearTheBadge(User $user): bool
    {
        try {
            if (! $this->isEligible($user)) {
                return false;
            }

            $deterministicLogId = self::getDeterministicLogId((string) $user->id, self::TEMPLATE_KEY);
            $shouldDispatch = false;

            if (Schema::hasTable('notification_delivery_logs')) {
                try {
                    $shouldDispatch = DB::transaction(function () use ($user, $deterministicLogId): bool {
                        // Check if deterministic log already exists
                        $existingLog = NotificationDeliveryLog::where('id', $deterministicLogId)->lockForUpdate()->first();
                        if ($existingLog) {
                            if ($existingLog->status === 'sent') {
                                Log::info('[WearTheBadgeWhatsappService] Skipped: WearTheBadge already sent for user.', [
                                    'user_id' => $user->id,
                                    'log_id' => $deterministicLogId,
                                    'status' => $existingLog->status,
                                ]);

                                return false;
                            }

                            if (in_array($existingLog->status, ['queued', 'processing', 'pending'], true)) {
                                if ($existingLog->attempted_at && $existingLog->attempted_at->gt(now()->subMinutes(5))) {
                                    Log::info('[WearTheBadgeWhatsappService] Skipped: WearTheBadge already queued or processing for user.', [
                                        'user_id' => $user->id,
                                        'log_id' => $deterministicLogId,
                                        'status' => $existingLog->status,
                                    ]);

                                    return false;
                                }
                            }

                            // If failed or stale in-flight, update and allow re-dispatch
                            $existingLog->update([
                                'status' => 'queued',
                                'request_payload' => [
                                    'template_key' => self::TEMPLATE_KEY,
                                    'trigger' => 'profile_complete_or_first_payment',
                                ],
                                'error_message' => null,
                                'attempted_at' => now(),
                            ]);

                            return true;
                        }

                        // Check legacy logs if any exist for this user & template
                        $legacyExists = NotificationDeliveryLog::query()
                            ->where('user_id', (string) $user->id)
                            ->where('channel', 'whatsapp')
                            ->where('provider', self::TEMPLATE_KEY)
                            ->whereIn('status', ['sent', 'queued', 'pending', 'processing'])
                            ->exists();

                        if ($legacyExists) {
                            Log::info('[WearTheBadgeWhatsappService] Skipped: Legacy WearTheBadge delivery record exists.', [
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
                                'trigger' => 'profile_complete_or_first_payment',
                            ],
                            'attempted_at' => now(),
                        ]);

                        return true;
                    });
                } catch (QueryException $qe) {
                    Log::info('[WearTheBadgeWhatsappService] Duplicate dispatch race prevented by DB unique primary key.', [
                        'user_id' => $user->id,
                        'log_id' => $deterministicLogId,
                    ]);
                    $shouldDispatch = false;
                } catch (Throwable $dbEx) {
                    Log::error('[WearTheBadgeWhatsappService] Error during WearTheBadge dispatch reservation: '.$dbEx->getMessage(), [
                        'user_id' => $user->id,
                        'exception' => $dbEx,
                    ]);
                    $shouldDispatch = false;
                }
            } else {
                $shouldDispatch = true;
            }

            if ($shouldDispatch) {
                SendWearTheBadgeWhatsappJob::dispatch((string) $user->id);

                Log::info('[WearTheBadgeWhatsappService] Dispatched SendWearTheBadgeWhatsappJob.', [
                    'user_id' => $user->id,
                    'log_id' => $deterministicLogId,
                ]);

                return true;
            }

            return false;
        } catch (Throwable $e) {
            Log::error('[WearTheBadgeWhatsappService] Exception in handleWearTheBadge: '.$e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Check if a user is eligible for WearTheBadge WhatsApp notification.
     */
    public function isEligible(User $user): bool
    {
        if ($this->isProcessed((string) $user->id)) {
            return false;
        }

        // Profile reaching 100%
        $isProfileComplete = $user->calculateProfileCompletionPercentage() === 100;

        // First payment condition: last_payment_at filled or paid membership status
        $isPaid = filled($user->last_payment_at) || ! in_array((string) $user->membership_status, ['visitor', 'free_peer', 'free_trial_peer', ''], true);

        return $isProfileComplete || $isPaid;
    }

    /**
     * Check whether WearTheBadge WhatsApp has already been processed or queued for this user.
     */
    public function isProcessed(string $userId): bool
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return false;
        }

        $deterministicLogId = self::getDeterministicLogId($userId, self::TEMPLATE_KEY);

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
     * Check whether WearTheBadge WhatsApp has already been successfully sent to this user.
     */
    public function isSent(string $userId): bool
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return false;
        }

        $deterministicLogId = self::getDeterministicLogId($userId, self::TEMPLATE_KEY);

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
                ->where('status', 'sent')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
