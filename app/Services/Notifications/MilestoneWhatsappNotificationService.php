<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Jobs\SendMilestoneWhatsappJob;
use App\Models\User;
use App\Models\WhatsappMessageDeliveryLog;
use App\Models\WhatsappTemplate;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use Throwable;

class MilestoneWhatsappNotificationService
{
    public const UUID_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    /**
     * Exact milestone count to template key mapping.
     *
     * @var array<int, string>
     */
    public const MILESTONE_TEMPLATES = [
        1 => 'milestone_connector',
        3 => 'pgu_catalyst_3',
        5 => 'milestone_influencer_5',
        10 => 'milestone_ambassador_10',
        20 => 'milestone_rainmaker_20',
        35 => 'milestone_trailblazer_35',
        50 => 'milestone_vanguard_50',
        75 => 'milestone_luminary_75',
        100 => 'milestone_movement_maker_100',
        150 => 'milestone_community_titan_150',
        250 => 'milestone_network_architect_250',
        500 => 'milestone_global_icon_500',
    ];

    /**
     * Generate deterministic UUID for milestone WhatsApp delivery log idempotency.
     */
    public static function getDeterministicLogId(string $userId, string $templateKey): string
    {
        return Uuid::uuid5(self::UUID_NAMESPACE, "whatsapp_milestone.{$templateKey}.{$userId}")->toString();
    }

    /**
     * Check whether the given count is an exact configured milestone count.
     */
    public function isExactMilestone(int $introducedCount): bool
    {
        return array_key_exists($introducedCount, self::MILESTONE_TEMPLATES);
    }

    /**
     * Get the template key for the given exact milestone count.
     */
    public function getTemplateKeyForMilestone(int $introducedCount): ?string
    {
        return self::MILESTONE_TEMPLATES[$introducedCount] ?? null;
    }

    /**
     * Handle milestone WhatsApp notification evaluation and trigger.
     *
     * @param  User  $user  The user who achieved the milestone (the introducer).
     * @param  int  $introducedCount  The user's current total introduced count.
     * @param  string|null  $imageUrl  The public creative image URL generated for this introduction.
     * @return bool True if the notification was dispatched or processed, false otherwise.
     */
    public function handleMilestoneNotification(User $user, int $introducedCount, ?string $imageUrl = null): bool
    {
        try {
            // 1. Only trigger for exact milestone counts
            if (! $this->isExactMilestone($introducedCount)) {
                Log::info('[MilestoneWhatsappNotificationService] Skipped: Introduced count is not an exact milestone.', [
                    'user_id' => $user->id,
                    'introduced_count' => $introducedCount,
                ]);

                return false;
            }

            $templateKey = (string) $this->getTemplateKeyForMilestone($introducedCount);
            $deterministicLogId = self::getDeterministicLogId((string) $user->id, $templateKey);
            $normalizedPhone = WhatsappNotificationService::normalizePhone((string) ($user->phone ?? $user->secondary_mobile ?? ''));

            // 2. Fetch template record to resolve template_name
            $template = null;
            if (Schema::hasTable('whatsapp_templates')) {
                $template = WhatsappTemplate::query()->where('template_key', $templateKey)->first();
                if (! $template) {
                    if ($templateKey === 'milestone_connector') {
                        $template = WhatsappTemplate::query()->where('template_key', 'milestone_badge_whatsapp')->first();
                    } elseif ($templateKey === 'milestone_badge_whatsapp') {
                        $template = WhatsappTemplate::query()->where('template_key', 'milestone_connector')->first();
                    }
                }
            }
            $templateName = $template?->template_name ?: $templateKey;

            // 3. Resolve creative if missing
            if (empty($imageUrl)) {
                try {
                    $imageUrl = app(IntroducedPeerCreativeGenerator::class)->generateOrGetUrl($user, $introducedCount);
                } catch (Throwable $e) {
                    Log::warning('[MilestoneWhatsappNotificationService] Could not auto-generate creative URL: '.$e->getMessage(), [
                        'user_id' => $user->id,
                        'introduced_count' => $introducedCount,
                    ]);
                }
            }

            // Critical ordering rule: creative must be complete with available image_url
            if (empty($imageUrl) || ! $this->isValidMediaUrl($imageUrl)) {
                $errorMsg = empty($imageUrl)
                    ? "Creative generation/storage failed or image_url unavailable for milestone {$introducedCount}."
                    : "Creative image_url is invalid or inaccessible for milestone {$introducedCount}: {$imageUrl}";

                Log::error("[MilestoneWhatsappNotificationService] {$errorMsg}", [
                    'user_id' => $user->id,
                    'introduced_count' => $introducedCount,
                    'template_key' => $templateKey,
                    'image_url' => $imageUrl,
                ]);

                // Record failure in delivery logs
                $this->logFailedAttempt($deterministicLogId, (string) $user->id, $templateKey, $templateName, $normalizedPhone, $imageUrl, $errorMsg, $introducedCount);

                return false;
            }

            // 4. Duplicate / Idempotency protection check
            if (! $this->acquireDispatchReservation($deterministicLogId, (string) $user->id, $templateKey, $templateName, $normalizedPhone, $imageUrl, $introducedCount)) {
                Log::info('[MilestoneWhatsappNotificationService] Skipped: Milestone already sent or in-flight for member.', [
                    'user_id' => $user->id,
                    'introduced_count' => $introducedCount,
                    'template_key' => $templateKey,
                    'log_id' => $deterministicLogId,
                ]);

                return false;
            }

            // 5. Dispatch job to execute the webhook call
            SendMilestoneWhatsappJob::dispatch((string) $user->id, $introducedCount, $imageUrl, $deterministicLogId);

            Log::info('[MilestoneWhatsappNotificationService] Dispatched SendMilestoneWhatsappJob successfully.', [
                'user_id' => $user->id,
                'introduced_count' => $introducedCount,
                'template_key' => $templateKey,
                'image_url' => $imageUrl,
                'log_id' => $deterministicLogId,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('[MilestoneWhatsappNotificationService] Exception in handleMilestoneNotification: '.$e->getMessage(), [
                'user_id' => $user->id,
                'introduced_count' => $introducedCount,
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Check whether this milestone notification was already successfully sent to the user.
     */
    public function alreadySent(string $userId, string $templateKey): bool
    {
        $deterministicLogId = self::getDeterministicLogId($userId, $templateKey);

        if (Schema::hasTable('whatsapp_message_delivery_logs')) {
            try {
                $alreadySentInWaLogs = WhatsappMessageDeliveryLog::query()
                    ->where(function ($q) use ($userId, $templateKey, $deterministicLogId): void {
                        $q->where('id', $deterministicLogId)
                            ->orWhere(function ($sub) use ($userId, $templateKey): void {
                                $sub->where('user_id', $userId)
                                    ->where('template_key', $templateKey);
                            });
                    })
                    ->where('status', 'sent')
                    ->exists();

                if ($alreadySentInWaLogs) {
                    return true;
                }
            } catch (Throwable) {
                // Ignore and check legacy
            }
        }

        if (Schema::hasTable('notification_delivery_logs')) {
            try {
                $legacyTemplateKeys = [$templateKey];
                if ($templateKey === 'milestone_connector') {
                    $legacyTemplateKeys[] = 'milestone_badge_whatsapp';
                }

                return DB::table('notification_delivery_logs')
                    ->where('user_id', $userId)
                    ->where('channel', 'whatsapp')
                    ->whereIn('provider', $legacyTemplateKeys)
                    ->where('status', 'sent')
                    ->exists();
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Check whether this milestone notification is currently in-flight (processing/queued/pending).
     */
    public function isInFlight(string $userId, string $templateKey): bool
    {
        if (! Schema::hasTable('whatsapp_message_delivery_logs')) {
            return false;
        }

        $deterministicLogId = self::getDeterministicLogId($userId, $templateKey);

        try {
            return WhatsappMessageDeliveryLog::query()
                ->where(function ($q) use ($userId, $templateKey, $deterministicLogId): void {
                    $q->where('id', $deterministicLogId)
                        ->orWhere(function ($sub) use ($userId, $templateKey): void {
                            $sub->where('user_id', $userId)
                                ->where('template_key', $templateKey);
                        });
                })
                ->whereIn('status', ['processing', 'queued', 'pending'])
                ->where('attempted_at', '>', now()->subMinutes(5))
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Atomically acquire execution reservation in DB to prevent concurrent races and duplicate dispatches.
     */
    private function acquireDispatchReservation(
        string $deterministicLogId,
        string $userId,
        string $templateKey,
        string $templateName,
        string $phone,
        string $imageUrl,
        int $introducedCount
    ): bool {
        // First check legacy notification_delivery_logs for already sent
        if (Schema::hasTable('notification_delivery_logs')) {
            try {
                $legacyKeys = [$templateKey];
                if ($templateKey === 'milestone_connector') {
                    $legacyKeys[] = 'milestone_badge_whatsapp';
                }
                $legacySent = DB::table('notification_delivery_logs')
                    ->where('user_id', $userId)
                    ->where('channel', 'whatsapp')
                    ->whereIn('provider', $legacyKeys)
                    ->where('status', 'sent')
                    ->exists();

                if ($legacySent) {
                    return false;
                }
            } catch (Throwable) {
                // Continue
            }
        }

        if (! Schema::hasTable('whatsapp_message_delivery_logs')) {
            return true;
        }

        try {
            return DB::transaction(function () use ($deterministicLogId, $userId, $templateKey, $templateName, $phone, $imageUrl, $introducedCount): bool {
                $existingLog = WhatsappMessageDeliveryLog::where('id', $deterministicLogId)->lockForUpdate()->first();

                if ($existingLog) {
                    if ($existingLog->status === 'sent') {
                        return false;
                    }

                    if (in_array($existingLog->status, ['processing', 'queued', 'pending'], true)) {
                        if ($existingLog->attempted_at && $existingLog->attempted_at->gt(now()->subMinutes(5))) {
                            return false;
                        }
                    }

                    // Re-try failed or stale attempt
                    $existingLog->update([
                        'status' => 'processing',
                        'creative_url' => $imageUrl,
                        'phone' => $phone,
                        'request_payload' => [
                            'template_key' => $templateKey,
                            'introduced_count' => $introducedCount,
                            'creative_url' => $imageUrl,
                            'phone' => $phone,
                        ],
                        'error_message' => null,
                        'attempted_at' => now(),
                    ]);

                    return true;
                }

                // Check by user_id and template_key fallback
                $sentExists = WhatsappMessageDeliveryLog::query()
                    ->where('user_id', $userId)
                    ->where('template_key', $templateKey)
                    ->where('status', 'sent')
                    ->exists();

                if ($sentExists) {
                    return false;
                }

                $inFlightExists = WhatsappMessageDeliveryLog::query()
                    ->where('user_id', $userId)
                    ->where('template_key', $templateKey)
                    ->whereIn('status', ['processing', 'queued', 'pending'])
                    ->where('attempted_at', '>', now()->subMinutes(5))
                    ->exists();

                if ($inFlightExists) {
                    return false;
                }

                // Atomically create the pre-dispatch processing record
                WhatsappMessageDeliveryLog::create([
                    'id' => $deterministicLogId,
                    'user_id' => $userId,
                    'template_key' => $templateKey,
                    'template_name' => $templateName,
                    'phone' => $phone,
                    'creative_url' => $imageUrl,
                    'provider' => 'fleximsg',
                    'status' => 'processing',
                    'request_payload' => [
                        'template_key' => $templateKey,
                        'introduced_count' => $introducedCount,
                        'creative_url' => $imageUrl,
                        'phone' => $phone,
                    ],
                    'attempted_at' => now(),
                ]);

                return true;
            });
        } catch (QueryException $qe) {
            Log::info('[MilestoneWhatsappNotificationService] Duplicate dispatch race prevented by DB unique primary key.', [
                'user_id' => $userId,
                'log_id' => $deterministicLogId,
                'template_key' => $templateKey,
            ]);

            return false;
        } catch (Throwable $e) {
            Log::error('[MilestoneWhatsappNotificationService] Error acquiring dispatch reservation: '.$e->getMessage(), [
                'user_id' => $userId,
                'template_key' => $templateKey,
            ]);

            return false;
        }
    }

    /**
     * Record a failed attempt in whatsapp_message_delivery_logs.
     */
    private function logFailedAttempt(
        string $deterministicLogId,
        string $userId,
        string $templateKey,
        string $templateName,
        string $phone,
        ?string $imageUrl,
        string $errorMessage,
        int $introducedCount
    ): void {
        if (! Schema::hasTable('whatsapp_message_delivery_logs')) {
            return;
        }

        try {
            $existing = WhatsappMessageDeliveryLog::find($deterministicLogId);
            if ($existing) {
                if ($existing->status !== 'sent') {
                    $existing->update([
                        'status' => 'failed',
                        'error_message' => $errorMessage,
                        'creative_url' => $imageUrl,
                        'phone' => $phone,
                        'request_payload' => [
                            'template_key' => $templateKey,
                            'introduced_count' => $introducedCount,
                            'creative_url' => $imageUrl,
                            'phone' => $phone,
                        ],
                        'attempted_at' => now(),
                    ]);
                }
            } else {
                WhatsappMessageDeliveryLog::create([
                    'id' => $deterministicLogId,
                    'user_id' => $userId,
                    'template_key' => $templateKey,
                    'template_name' => $templateName,
                    'phone' => $phone,
                    'creative_url' => $imageUrl,
                    'provider' => 'fleximsg',
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                    'request_payload' => [
                        'template_key' => $templateKey,
                        'introduced_count' => $introducedCount,
                        'creative_url' => $imageUrl,
                        'phone' => $phone,
                    ],
                    'attempted_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            Log::error('[MilestoneWhatsappNotificationService] Failed recording failure log: '.$e->getMessage());
        }
    }

    /**
     * Check if a media URL is valid and reachable.
     */
    private function isValidMediaUrl(?string $url): bool
    {
        if (blank($url)) {
            return false;
        }

        $trimmed = trim((string) $url);

        // Must start with http:// or https:// (or relative path in test environments)
        if (! str_starts_with(strtolower($trimmed), 'http://') && ! str_starts_with(strtolower($trimmed), 'https://')) {
            return false;
        }

        // Must not be localhost, loopback, or private IP in production
        if (preg_match('#https?://(localhost|127\.0\.0\.1|10\.0\.2\.2|0\.0\.0\.0|::1)([:/]|$)#i', $trimmed) && ! app()->environment('testing')) {
            return false;
        }

        // Must not be unrendered raw badge template
        if (str_contains($trimmed, '/images/member_introduce_badges/')) {
            return false;
        }

        return true;
    }
}
