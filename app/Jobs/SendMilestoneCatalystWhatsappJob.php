<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\Creative\CreativePublicUrlResolver;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
use App\Services\Notifications\MilestoneCatalystWhatsappService;
use App\Services\Notifications\WhatsappNotificationService;
use App\Services\Referrals\ReferralService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendMilestoneCatalystWhatsappJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const TEMPLATE_KEY = 'pgu_catalyst_3';

    public const MILESTONE_COUNT = 3;

    public const MILESTONE_NAME = 'Catalyst';

    public const MILESTONE_KEY = 'CATALYST';

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $userId,
        public ?string $customImageUrl = null
    ) {
        $this->afterCommit = true;
    }

    /**
     * Execute the job to send pgu_catalyst_3 WhatsApp notification.
     */
    public function handle(
        WhatsappNotificationService $whatsappService,
        ReferralService $referralService,
        IntroducedPeerCreativeGenerator $creativeGenerator
    ): void {
        $lockKey = "milestone_catalyst_job_exec_{$this->userId}";
        $lock = Cache::lock($lockKey, 30);

        $lock->get(function () use ($whatsappService, $referralService): void {
            $user = User::find($this->userId);

            if (! $user) {
                Log::warning('[SendMilestoneCatalystWhatsappJob] Skipped: User not found.', [
                    'user_id' => $this->userId,
                    'template_key' => self::TEMPLATE_KEY,
                ]);

                return;
            }

            $primaryPhone = trim((string) ($user->phone ?? ''));
            $secondaryPhone = trim((string) ($user->secondary_mobile ?? ''));

            $rawPhone = null;
            $phoneSource = null;

            if ($primaryPhone !== '' && static::isValidPhoneNumber($primaryPhone)) {
                $rawPhone = $primaryPhone;
                $phoneSource = 'users.phone';
            } elseif ($secondaryPhone !== '' && static::isValidPhoneNumber($secondaryPhone)) {
                $rawPhone = $secondaryPhone;
                $phoneSource = 'users.secondary_mobile';
            }

            if ($rawPhone === null || $phoneSource === null) {
                Log::warning('[SendMilestoneCatalystWhatsappJob] Skipped: No valid phone number found.', [
                    'user_id' => $this->userId,
                    'phone' => $user->phone,
                    'secondary_mobile' => $user->secondary_mobile,
                    'template_key' => self::TEMPLATE_KEY,
                ]);

                return;
            }

            Log::info('[SendMilestoneCatalystWhatsappJob] Resolved recipient phone.', [
                'user_id' => $this->userId,
                'phone_source' => $phoneSource,
                'phone' => (string) $rawPhone,
            ]);

            // 1. Construct canonical data object
            $introducedCount = (int) ($user->members_introduced_count ?? self::MILESTONE_COUNT);
            if ($introducedCount < self::MILESTONE_COUNT) {
                $introducedCount = self::MILESTONE_COUNT;
            }

            $selectedMilestone = self::MILESTONE_NAME;
            $milestoneKey = self::MILESTONE_KEY;

            $deterministicLogId = MilestoneCatalystWhatsappService::getDeterministicLogId($this->userId, self::TEMPLATE_KEY, self::MILESTONE_COUNT);

            // Atomically acquire execution claim in DB
            $canProceed = true;
            if (Schema::hasTable('notification_delivery_logs')) {
                try {
                    $canProceed = DB::transaction(function () use ($deterministicLogId, $milestoneKey): bool {
                        $log = NotificationDeliveryLog::where('id', $deterministicLogId)->lockForUpdate()->first();
                        if ($log) {
                            if ($log->status === 'sent' || $log->status === 'processing') {
                                return false;
                            }
                            $log->status = 'processing';
                            $log->save();

                            return true;
                        }

                        if ($this->alreadySent($this->userId, $milestoneKey)) {
                            return false;
                        }

                        return true;
                    });
                } catch (Throwable $lockEx) {
                    Log::warning('[SendMilestoneCatalystWhatsappJob] Could not acquire execution lock: '.$lockEx->getMessage());
                    if ($this->alreadySent($this->userId, $milestoneKey)) {
                        $canProceed = false;
                    }
                }
            } else {
                if ($this->alreadySent($this->userId, $milestoneKey)) {
                    $canProceed = false;
                }
            }

            if (! $canProceed) {
                Log::info('[SendMilestoneCatalystWhatsappJob] Skipped: CATALYST milestone already sent or currently processing.', [
                    'user_id' => $this->userId,
                    'template_key' => self::TEMPLATE_KEY,
                    'selected_milestone' => $selectedMilestone,
                    'introduced_count' => $introducedCount,
                ]);

                return;
            }

            // Verify template exists in whatsapp_templates table
            $template = WhatsappTemplate::query()
                ->where('template_key', self::TEMPLATE_KEY)
                ->first();

            if (! $template) {
                Log::warning('[SendMilestoneCatalystWhatsappJob] Skipped: Template not found in database.', [
                    'template_key' => self::TEMPLATE_KEY,
                ]);

                return;
            }

            if (! $template->is_active) {
                Log::info('[SendMilestoneCatalystWhatsappJob] Skipped: Template is inactive.', [
                    'template_key' => self::TEMPLATE_KEY,
                ]);

                return;
            }

            $memberName = trim((string) ($user->display_name ?: (($user->first_name ?? '').' '.($user->last_name ?? ''))));
            if ($memberName === '') {
                $memberName = 'Valued Member';
            }
            $firstName = trim((string) ($user->first_name ?: $memberName));

            // 2. Canonical Referral Link -> Body {{3}}
            $referralData = $referralService->generateOrGetReferral($user);
            $baseUrl = IntroducedPeerCreativeGenerator::getPublicBaseUrl();
            $referralLink = "{$baseUrl}/share?type=referrals";

            // 3. Resolve Personalized CATALYST Creative Image URL using centralized CreativePublicUrlResolver
            $badgeImageUrl = null;
            try {
                $resolver = app(CreativePublicUrlResolver::class);
                $badgeImageUrl = $resolver->resolveForUser($user, $introducedCount, $this->customImageUrl);
            } catch (Throwable $e) {
                Log::error('[SendMilestoneCatalystWhatsappJob] CreativePublicUrlResolver failed: '.$e->getMessage(), [
                    'user_id' => $user->id,
                    'exception' => $e,
                ]);
                $this->logDelivery($this->userId, (string) $rawPhone, 'failed', 'Personalized creative resolution failed: '.$e->getMessage(), [], $introducedCount);
                throw new \RuntimeException('Failed to resolve personalized creative for Catalyst milestone: '.$e->getMessage(), 0, $e);
            }

            if (blank($badgeImageUrl) || ! $this->isValidPublicMediaUrl($badgeImageUrl)) {
                $errorMsg = 'Personalized creative URL is invalid or inaccessible for Catalyst milestone: '.(string) $badgeImageUrl;
                Log::error('[SendMilestoneCatalystWhatsappJob] '.$errorMsg, ['user_id' => $user->id]);
                $this->logDelivery($this->userId, (string) $rawPhone, 'failed', $errorMsg, [], $introducedCount);
                throw new \RuntimeException($errorMsg);
            }

            // Body Parameters for template pgu_catalyst_3:
            // {{1}} = Member / Peer Name (e.g. Vinit Chavda)
            // {{2}} = Introducer / Member Name (e.g. Vinit Chavda) for "Join the Peers Global mission through [Name]:"
            // {{3}} = Member Referral Link
            $bodyParam1 = $memberName;
            $bodyParam2 = $memberName;
            $bodyParam3 = $referralLink;

            // Structured pre-dispatch logging
            Log::info('[SendMilestoneCatalystWhatsappJob] Dispatching milestone WhatsApp notification.', [
                'introduced_count' => $introducedCount,
                'selected_milestone' => $selectedMilestone,
                'milestone_key' => $milestoneKey,
                'template_name' => $template->template_name ?: self::TEMPLATE_KEY,
                'template_key' => self::TEMPLATE_KEY,
                'body_param_1' => $bodyParam1,
                'body_param_2' => $bodyParam2,
                'body_param_3' => $bodyParam3,
                'creative_url' => $badgeImageUrl,
                'phone' => (string) $rawPhone,
                'user_id' => $this->userId,
            ]);

            $payload = [
                'name' => $bodyParam1,
                'member_name' => $bodyParam1,
                'peer_name' => $bodyParam1,
                'catalyst_name' => $bodyParam1,
                'connector_name' => $bodyParam1,
                'first_name' => $firstName,
                'inviter_name' => $bodyParam2,

                'milestone' => $selectedMilestone,
                'milestone_name' => $selectedMilestone,
                'milestone_title' => $selectedMilestone,
                'honour_title' => $selectedMilestone,
                'award_name' => $selectedMilestone,
                'title' => $selectedMilestone,
                'selected_milestone' => $selectedMilestone,
                'milestone_key' => $milestoneKey,

                'introduced_count' => $introducedCount,
                'referral_link' => $bodyParam3,
                'link' => $bodyParam3,
                'url' => $bodyParam3,

                'badge_image_url' => $badgeImageUrl,
                'header_media_url' => $badgeImageUrl,
                'header_image_url' => $badgeImageUrl,
                'header_url' => $badgeImageUrl,
                'image' => $badgeImageUrl,
                'image_url' => $badgeImageUrl,
                'media_url' => $badgeImageUrl,

                '1' => $bodyParam1,
                '2' => $bodyParam2,
                '3' => $bodyParam3,
                '@1' => $bodyParam1,
                '@2' => $bodyParam2,
                '@3' => $bodyParam3,
                '(1)' => $bodyParam1,
                '(2)' => $bodyParam2,
                '(3)' => $bodyParam3,
                '@(1)' => $bodyParam1,
                '@(2)' => $bodyParam2,
                '@(3)' => $bodyParam3,
                '{{1}}' => $bodyParam1,
                '{{2}}' => $bodyParam2,
                '{{3}}' => $bodyParam3,
                '{1}' => $bodyParam1,
                '{2}' => $bodyParam2,
                '{3}' => $bodyParam3,

                'var_1' => $bodyParam1,
                'var_2' => $bodyParam2,
                'var_3' => $bodyParam3,
                'var1' => $bodyParam1,
                'var2' => $bodyParam2,
                'var3' => $bodyParam3,
                'body_1' => $bodyParam1,
                'body_2' => $bodyParam2,
                'body_3' => $bodyParam3,

                'body_param_1' => $bodyParam1,
                'body_param_2' => $bodyParam2,
                'body_param_3' => $bodyParam3,
                '@body_param_1' => $bodyParam1,
                '@body_param_2' => $bodyParam2,
                '@body_param_3' => $bodyParam3,
                '(body_param_1)' => $bodyParam1,
                '(body_param_2)' => $bodyParam2,
                '(body_param_3)' => $bodyParam3,
                '@(body_param_1)' => $bodyParam1,
                '@(body_param_2)' => $bodyParam2,
                '@(body_param_3)' => $bodyParam3,
                '{{body_param_1}}' => $bodyParam1,
                '{{body_param_2}}' => $bodyParam2,
                '{{body_param_3}}' => $bodyParam3,
                '{body_param_1}' => $bodyParam1,
                '{body_param_2}' => $bodyParam2,
                '{body_param_3}' => $bodyParam3,

                'body_param1' => $bodyParam1,
                'body_param2' => $bodyParam2,
                'body_param3' => $bodyParam3,
                'param1' => $bodyParam1,
                'param2' => $bodyParam2,
                'param3' => $bodyParam3,
                'param_1' => $bodyParam1,
                'param_2' => $bodyParam2,
                'param_3' => $bodyParam3,

                'Peer Name' => $bodyParam1,
                '@Peer Name' => $bodyParam1,
                'Peer_Name' => $bodyParam1,
                '@Peer_Name' => $bodyParam1,
                '@peer_name' => $bodyParam1,
                'Catalyst Name' => $bodyParam2,
                '@Catalyst Name' => $bodyParam2,
                'Catalyst_Name' => $bodyParam2,
                '@Catalyst_Name' => $bodyParam2,
                '@catalyst_name' => $bodyParam2,
                'Connector Name' => $bodyParam2,
                '@Connector Name' => $bodyParam2,
                'Connector_Name' => $bodyParam2,
                '@Connector_Name' => $bodyParam2,
                '@connector_name' => $bodyParam2,
                'Referral Link' => $bodyParam3,
                '@Referral Link' => $bodyParam3,
                'Referral_Link' => $bodyParam3,
                '@Referral_Link' => $bodyParam3,
                '@referral_link' => $bodyParam3,

                'variables' => [
                    '1' => $bodyParam1,
                    '2' => $bodyParam2,
                    '3' => $bodyParam3,
                    'Peer Name' => $bodyParam1,
                    'Catalyst Name' => $bodyParam2,
                    'Connector Name' => $bodyParam2,
                    'Referral Link' => $bodyParam3,
                    'peer_name' => $bodyParam1,
                    'catalyst_name' => $bodyParam2,
                    'connector_name' => $bodyParam2,
                    'referral_link' => $bodyParam3,
                    'body_param_1' => $bodyParam1,
                    'body_param_2' => $bodyParam2,
                    'body_param_3' => $bodyParam3,
                    'selected_milestone' => $bodyParam2,
                ],
                'body_parameters' => [
                    $bodyParam1,
                    $bodyParam2,
                    $bodyParam3,
                ],
                'body_params' => [
                    '1' => $bodyParam1,
                    '2' => $bodyParam2,
                    '3' => $bodyParam3,
                ],
                'params' => [
                    $bodyParam1,
                    $bodyParam2,
                    $bodyParam3,
                ],
                'custom_params' => [
                    'Peer Name' => $bodyParam1,
                    'Catalyst Name' => $bodyParam2,
                    'Connector Name' => $bodyParam2,
                    'Referral Link' => $bodyParam3,
                ],
            ];

            try {
                $success = $whatsappService->send(self::TEMPLATE_KEY, (string) $rawPhone, $payload);

                if ($success) {
                    $this->logDelivery($this->userId, (string) $rawPhone, 'sent', null, $payload, self::MILESTONE_COUNT);

                    Log::info('[SendMilestoneCatalystWhatsappJob] Milestone catalyst WhatsApp delivered successfully.', [
                        'user_id' => $this->userId,
                        'phone' => (string) $rawPhone,
                        'template_key' => self::TEMPLATE_KEY,
                        'milestone' => self::MILESTONE_KEY,
                        'badge_image_url' => $badgeImageUrl,
                        'referral_link' => $referralLink,
                    ]);
                } else {
                    $errorMessage = WhatsappNotificationService::$lastError ?? 'Webhook check failed or template inactive';
                    $this->logDelivery($this->userId, (string) $rawPhone, 'failed', $errorMessage, $payload, self::MILESTONE_COUNT);

                    Log::error('[SendMilestoneCatalystWhatsappJob] Milestone catalyst WhatsApp delivery failed.', [
                        'template_key' => self::TEMPLATE_KEY,
                        'milestone' => self::MILESTONE_KEY,
                        'user_id' => $this->userId,
                        'phone' => (string) $rawPhone,
                        'error' => $errorMessage,
                    ]);
                }
            } catch (Throwable $exception) {
                Log::error('[SendMilestoneCatalystWhatsappJob] Milestone catalyst WhatsApp threw exception: '.$exception->getMessage(), [
                    'template_key' => self::TEMPLATE_KEY,
                    'milestone' => self::MILESTONE_KEY,
                    'user_id' => $this->userId,
                    'phone' => (string) $rawPhone,
                    'exception' => $exception,
                ]);

                $this->logDelivery($this->userId, (string) $rawPhone, 'failed', $exception->getMessage(), $payload, self::MILESTONE_COUNT);
            }
        });
    }

    private function alreadySent(string $userId, ?string $milestoneKey = null): bool
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return false;
        }

        try {
            $query = NotificationDeliveryLog::query()
                ->where('user_id', $userId)
                ->where('channel', 'whatsapp')
                ->where('provider', self::TEMPLATE_KEY)
                ->where('status', 'sent');

            if (! empty($milestoneKey)) {
                $query->where(function ($q) use ($milestoneKey): void {
                    $q->where('request_payload->milestone_key', $milestoneKey)
                        ->orWhere('request_payload->selected_milestone', $milestoneKey)
                        ->orWhere('request_payload->selected_milestone', ucwords(strtolower($milestoneKey)))
                        ->orWhere('request_payload->body_param_2', $milestoneKey)
                        ->orWhere('request_payload->body_param_2', ucwords(strtolower($milestoneKey)));
                });
            }

            return $query->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function logDelivery(string $userId, string $phone, string $status, ?string $errorMessage, array $payload = [], int $introducedCount = self::MILESTONE_COUNT): void
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return;
        }

        $loggedPayload = [
            'template_key' => self::TEMPLATE_KEY,
            'milestone' => self::MILESTONE_KEY,
            'phone' => $phone,
        ];
        foreach ($payload as $k => $v) {
            $loggedPayload[(string) $k] = $v;
        }

        $lastResponse = WhatsappNotificationService::$lastResponse;
        $providerMessageId = null;
        if (is_array($lastResponse)) {
            $providerMessageId = $lastResponse['wamid']
                ?? $lastResponse['provider_message_id']
                ?? (isset($lastResponse['log_id']) ? (string) $lastResponse['log_id'] : null);
        }

        $deterministicLogId = MilestoneCatalystWhatsappService::getDeterministicLogId($userId, self::TEMPLATE_KEY, $introducedCount);

        try {
            $existingLog = NotificationDeliveryLog::query()
                ->where('id', $deterministicLogId)
                ->orWhere(function ($q) use ($userId): void {
                    $q->where('user_id', $userId)
                        ->where('channel', 'whatsapp')
                        ->where('provider', self::TEMPLATE_KEY)
                        ->whereIn('status', ['queued', 'pending', 'processing']);
                })
                ->latest()
                ->first();

            if ($existingLog) {
                $existingLog->update([
                    'provider_message_id' => $providerMessageId,
                    'status' => $status,
                    'request_payload' => $loggedPayload,
                    'response_payload' => $lastResponse ?? [],
                    'error_message' => $errorMessage,
                    'delivered_at' => $status === 'sent' ? now() : null,
                ]);
            } else {
                NotificationDeliveryLog::create([
                    'id' => $deterministicLogId,
                    'user_id' => $userId,
                    'channel' => 'whatsapp',
                    'provider' => self::TEMPLATE_KEY,
                    'provider_message_id' => $providerMessageId,
                    'status' => $status,
                    'request_payload' => $loggedPayload,
                    'response_payload' => $lastResponse ?? [],
                    'error_message' => $errorMessage,
                    'attempted_at' => now(),
                    'delivered_at' => $status === 'sent' ? now() : null,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('[SendMilestoneCatalystWhatsappJob] Failed to write NotificationDeliveryLog: '.$e->getMessage());
        }
    }

    /**
     * Resolve a publicly accessible, stable HTTPS personalized badge image URL for CATALYST WhatsApp delivery.
     * Note: Never falls back to CONNECTOR or generic milestone images.
     */
    public function resolveBadgeImageUrl(?User $user = null, int $introducedCount = self::MILESTONE_COUNT): ?string
    {
        if ($user) {
            try {
                $generator = app(IntroducedPeerCreativeGenerator::class);

                return $generator->generateOrGetUrl($user, $introducedCount);
            } catch (Throwable $e) {
                Log::error('[SendMilestoneCatalystWhatsappJob] Failed generating personalized badge image in fallback: '.$e->getMessage(), [
                    'user_id' => $user->id,
                    'exception' => $e,
                ]);
            }
        }

        return null;
    }

    /**
     * Check if a media URL is a valid, publicly reachable HTTPS image URL (not localhost, not ngrok, not 404 API path, not blank template).
     */
    public function isValidPublicMediaUrl(?string $url): bool
    {
        if (blank($url)) {
            return false;
        }

        $trimmed = trim((string) $url);

        if (! str_starts_with(strtolower($trimmed), 'https://')) {
            return false;
        }

        // Must not be localhost, loopback, or private IP
        if (preg_match('#https?://(localhost|127\.0\.0\.1|10\.0\.2\.2|0\.0\.0\.0|::1)([:/]|$)#i', $trimmed)) {
            return false;
        }

        // Must not be ngrok temporary tunnel (causes Meta delivery failure)
        if (preg_match('#ngrok(-free)?\.(app|dev|io)#i', $trimmed)) {
            return false;
        }

        // Must not be internal API file endpoint (WhatsApp media fetcher requires direct static public asset)
        if (str_contains($trimmed, '/api/v1/files/')) {
            return false;
        }

        // Static unrendered milestone badge templates are not valid personalized member creatives
        if (str_contains($trimmed, '/images/member_introduce_badges/')) {
            return false;
        }

        // For storage uploads, verify physical existence on public disk
        if (preg_match('~/storage/(uploads/[^?#\s]+)~i', $trimmed, $matches)) {
            $s3Key = $matches[1];

            $localExists = Storage::disk('public')->exists($s3Key)
                || Storage::disk(config('filesystems.default', 'public'))->exists($s3Key)
                || file_exists(storage_path('app/public/'.$s3Key))
                || file_exists(public_path('storage/'.$s3Key));

            // If verified on local public storage disk, it is valid
            if ($localExists) {
                return true;
            }

            $host = parse_url($trimmed, PHP_URL_HOST);
            $isLocalHost = in_array(strtolower((string) $host), ['localhost', '127.0.0.1', '::1'], true);

            if ($isLocalHost) {
                return false;
            }

            if (app()->runningUnitTests()) {
                return $localExists;
            }

            // If pointing to a remote host (e.g. S3 / external CDN), verify external HTTPS reachability
            if (! empty($host)) {
                try {
                    $response = Http::timeout(4)->withoutVerifying()->get($trimmed);
                    if ($response->status() !== 200) {
                        return false;
                    }
                    $contentType = (string) $response->header('Content-Type');

                    return str_starts_with($contentType, 'image/');
                } catch (Throwable) {
                    return false;
                }
            }

            return false;
        }

        // Must have an image extension or valid storage path
        $path = (string) parse_url($trimmed, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true) && ! str_contains($path, '/storage/')) {
            return false;
        }

        return true;
    }

    /**
     * Validate that the given value is a plausible phone number (10 to 15 digits).
     */
    public static function isValidPhoneNumber(?string $phone): bool
    {
        if ($phone === null) {
            return false;
        }

        $trimmed = trim($phone);
        if ($trimmed === '') {
            return false;
        }

        // Check if string contains unexpected non-phone characters (letters, special symbols other than +, -, (, ), space)
        if (preg_match('/[^\d\+\-\s\(\)]/', $trimmed)) {
            return false;
        }

        // Extract digits only
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        // Plausible phone numbers must be between 10 and 15 digits
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return false;
        }

        return true;
    }
}
