<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Jobs\SendImpactMilestoneWhatsappJob;
use App\Models\User;
use App\Models\WhatsappMessageDeliveryLog;
use App\Models\WhatsappTemplate;
use App\Services\Creative\LifeImpactCreativeGenerator;
use App\Services\Creative\LifeImpactCreativeService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use Throwable;

class ImpactMilestoneWhatsappNotificationService
{
    public const UUID_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    /**
     * Exact 12 Life Impact milestone thresholds to template key mapping.
     *
     * @var array<int, string>
     */
    public const IMPACT_MILESTONE_TEMPLATES = [
        25 => 'impact_creator_25',
        50 => 'change_maker_50',
        100 => 'life_changer_100',
        250 => 'impact_builder_250',
        500 => 'ecosystem_builder_500',
        1000 => 'impact_architect_1000',
        2500 => 'legacy_maker_2500',
        5000 => 'torchbearer_5000',
        10000 => 'world_changer_10000',
        25000 => 'humanitarian_25000',
        50000 => 'history_maker_50000',
        100000 => 'peers_global_legend_100000',
    ];

    /**
     * Generate deterministic UUID for impact milestone WhatsApp delivery log idempotency.
     */
    public static function getDeterministicLogId(string $userId, string $templateKey): string
    {
        return Uuid::uuid5(self::UUID_NAMESPACE, "whatsapp_impact_milestone.{$templateKey}.{$userId}")->toString();
    }

    /**
     * Check whether the given count is an exact configured Impact milestone threshold.
     */
    public function isConfiguredThreshold(int $count): bool
    {
        return array_key_exists($count, self::IMPACT_MILESTONE_TEMPLATES);
    }

    /**
     * Get the template key for the given Impact milestone threshold.
     */
    public function getTemplateKeyForThreshold(int $threshold): ?string
    {
        return self::IMPACT_MILESTONE_TEMPLATES[$threshold] ?? null;
    }

    /**
     * Get all Impact milestone thresholds crossed between $oldCount and $newCount in ascending order.
     *
     * @return array<int, int>
     */
    public function getCrossedThresholds(int $newCount, int $oldCount = 0): array
    {
        $thresholds = array_keys(self::IMPACT_MILESTONE_TEMPLATES);
        sort($thresholds, SORT_NUMERIC);

        $crossed = [];
        foreach ($thresholds as $threshold) {
            if ($newCount >= $threshold && ($oldCount <= 0 || $oldCount < $threshold)) {
                $crossed[] = (int) $threshold;
            }
        }

        return $crossed;
    }

    /**
     * Process all newly crossed Impact milestones for a user in sequential ascending order.
     *
     * @return array<int, int> List of processed milestone thresholds
     */
    public function processMilestonesForUser(User $user, int $newCount, int $oldCount = 0): array
    {
        $crossedThresholds = $this->getCrossedThresholds($newCount, $oldCount);
        $processed = [];

        foreach ($crossedThresholds as $threshold) {
            try {
                $templateKey = $this->getTemplateKeyForThreshold($threshold);
                if (! $templateKey) {
                    continue;
                }

                // Skip if already sent or in-flight
                if ($this->alreadySent((string) $user->id, $templateKey) || $this->isInFlight((string) $user->id, $templateKey)) {
                    Log::info('[ImpactMilestoneWhatsappNotificationService] Skipped crossed milestone: already processed or in flight.', [
                        'user_id' => $user->id,
                        'threshold' => $threshold,
                        'template_key' => $templateKey,
                    ]);

                    continue;
                }

                $success = $this->handleMilestoneNotification($user, $threshold);
                if ($success) {
                    $processed[] = $threshold;
                }
            } catch (Throwable $milestoneEx) {
                Log::error('[ImpactMilestoneWhatsappNotificationService] Failed processing milestone threshold '.$threshold.' for user '.$user->id.': '.$milestoneEx->getMessage(), [
                    'user_id' => $user->id,
                    'threshold' => $threshold,
                    'exception' => $milestoneEx,
                ]);

                try {
                    $templateKey = $this->getTemplateKeyForThreshold($threshold) ?: 'impact_milestone';
                    $logId = self::getDeterministicLogId((string) $user->id, $templateKey);
                    $this->logFailedAttempt($logId, (string) $user->id, $templateKey, $templateKey, (string) ($user->phone ?? ''), null, $milestoneEx->getMessage(), $threshold);
                } catch (Throwable) {
                    // Ignore failure logging error to ensure loop continues to next threshold
                }
            }
        }

        return $processed;
    }

    /**
     * Handle single Impact milestone WhatsApp notification evaluation and trigger.
     *
     * @param  User  $user  The user who achieved the Impact milestone.
     * @param  int  $threshold  The milestone threshold achieved.
     * @param  string|null  $imageUrl  Optional pre-generated creative image URL.
     * @return bool True if the notification was dispatched or processed, false otherwise.
     */
    public function handleMilestoneNotification(User $user, int $threshold, ?string $imageUrl = null): bool
    {
        try {
            // 1. Only trigger for configured threshold counts
            if (! $this->isConfiguredThreshold($threshold)) {
                Log::info('[ImpactMilestoneWhatsappNotificationService] Skipped: Count is not an Impact milestone threshold.', [
                    'user_id' => $user->id,
                    'threshold' => $threshold,
                ]);

                return false;
            }

            $templateKey = (string) $this->getTemplateKeyForThreshold($threshold);
            $deterministicLogId = self::getDeterministicLogId((string) $user->id, $templateKey);
            $normalizedPhone = WhatsappNotificationService::normalizePhone((string) ($user->phone ?? $user->secondary_mobile ?? ''));

            // 2. Fetch template record to resolve template_name and check active status
            $template = null;
            if (Schema::hasTable('whatsapp_templates')) {
                $template = WhatsappTemplate::query()->where('template_key', $templateKey)->first();
            }

            $templateName = $template?->template_name ?: $templateKey;

            if ($template && ! $template->is_active) {
                $errorMsg = "Template is inactive: {$templateKey}";
                Log::info("[ImpactMilestoneWhatsappNotificationService] Skipped: {$errorMsg}", [
                    'user_id' => $user->id,
                    'threshold' => $threshold,
                    'template_key' => $templateKey,
                ]);

                $this->logFailedAttempt($deterministicLogId, (string) $user->id, $templateKey, $templateName, $normalizedPhone, $imageUrl, $errorMsg, $threshold);

                return false;
            }

            // 3. Resolve creative if missing
            if (empty($imageUrl)) {
                try {
                    $creativeService = app(LifeImpactCreativeService::class);
                    $creativeRecord = $creativeService->handleLifeImpactCreative($user, $threshold, $threshold);
                    $imageUrl = $creativeRecord?->image_url;

                    if (empty($imageUrl)) {
                        $imageUrl = app(LifeImpactCreativeGenerator::class)->generateOrGetUrl($user, $threshold, $threshold);
                    }
                } catch (Throwable $e) {
                    Log::warning('[ImpactMilestoneWhatsappNotificationService] Could not auto-generate creative URL: '.$e->getMessage(), [
                        'user_id' => $user->id,
                        'threshold' => $threshold,
                    ]);
                }
            }

            // Critical ordering rule: creative must be complete with available image_url
            if (empty($imageUrl) || ! $this->isValidMediaUrl($imageUrl)) {
                $errorMsg = empty($imageUrl)
                    ? "Life Impact creative generation/storage failed or image_url unavailable for threshold {$threshold}."
                    : "Life Impact creative image_url is invalid or inaccessible for threshold {$threshold}: {$imageUrl}";

                Log::error("[ImpactMilestoneWhatsappNotificationService] {$errorMsg}", [
                    'user_id' => $user->id,
                    'threshold' => $threshold,
                    'template_key' => $templateKey,
                    'image_url' => $imageUrl,
                ]);

                // Record failure in delivery logs
                $this->logFailedAttempt($deterministicLogId, (string) $user->id, $templateKey, $templateName, $normalizedPhone, $imageUrl, $errorMsg, $threshold);

                return false;
            }

            // 4. Duplicate / Idempotency protection check
            if (! $this->acquireDispatchReservation($deterministicLogId, (string) $user->id, $templateKey, $templateName, $normalizedPhone, $imageUrl, $threshold)) {
                Log::info('[ImpactMilestoneWhatsappNotificationService] Skipped: Milestone already sent or in-flight for member.', [
                    'user_id' => $user->id,
                    'threshold' => $threshold,
                    'template_key' => $templateKey,
                    'log_id' => $deterministicLogId,
                ]);

                return false;
            }

            // 5. Dispatch job to execute the webhook call
            SendImpactMilestoneWhatsappJob::dispatch((string) $user->id, $threshold, $imageUrl, $deterministicLogId);

            Log::info('[ImpactMilestoneWhatsappNotificationService] Dispatched SendImpactMilestoneWhatsappJob successfully.', [
                'user_id' => $user->id,
                'threshold' => $threshold,
                'template_key' => $templateKey,
                'image_url' => $imageUrl,
                'log_id' => $deterministicLogId,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('[ImpactMilestoneWhatsappNotificationService] Exception in handleMilestoneNotification: '.$e->getMessage(), [
                'user_id' => $user->id,
                'threshold' => $threshold,
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Check whether this Impact milestone notification was already successfully sent to the user.
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
                return DB::table('notification_delivery_logs')
                    ->where('user_id', $userId)
                    ->where('channel', 'whatsapp')
                    ->where('provider', $templateKey)
                    ->where('status', 'sent')
                    ->exists();
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Check whether this Impact milestone notification is currently in-flight (processing/queued/pending).
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
        int $threshold
    ): bool {
        // First check legacy notification_delivery_logs for already sent
        if (Schema::hasTable('notification_delivery_logs')) {
            try {
                $legacySent = DB::table('notification_delivery_logs')
                    ->where('user_id', $userId)
                    ->where('channel', 'whatsapp')
                    ->where('provider', $templateKey)
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
            return DB::transaction(function () use ($deterministicLogId, $userId, $templateKey, $templateName, $phone, $imageUrl, $threshold): bool {
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
                            'threshold' => $threshold,
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
                        'threshold' => $threshold,
                        'creative_url' => $imageUrl,
                        'phone' => $phone,
                    ],
                    'attempted_at' => now(),
                ]);

                return true;
            });
        } catch (QueryException $qe) {
            Log::info('[ImpactMilestoneWhatsappNotificationService] Duplicate dispatch race prevented by DB unique primary key.', [
                'user_id' => $userId,
                'log_id' => $deterministicLogId,
                'template_key' => $templateKey,
            ]);

            return false;
        } catch (Throwable $e) {
            Log::error('[ImpactMilestoneWhatsappNotificationService] Error acquiring dispatch reservation: '.$e->getMessage(), [
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
        int $threshold
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
                            'threshold' => $threshold,
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
                        'threshold' => $threshold,
                        'creative_url' => $imageUrl,
                        'phone' => $phone,
                    ],
                    'attempted_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            Log::error('[ImpactMilestoneWhatsappNotificationService] Failed recording failure log: '.$e->getMessage());
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
        if (str_contains($trimmed, '/images/life_impact_badges/')) {
            return false;
        }

        return true;
    }
}
