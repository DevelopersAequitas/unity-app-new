<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\Creative\CreativePublicUrlResolver;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
use App\Services\Notifications\MilestoneConnectorWhatsappService;
use App\Services\Notifications\WhatsappNotificationService;
use App\Services\Referrals\ReferralService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendMilestoneConnectorWhatsappJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const TEMPLATE_KEY = 'milestone_connector';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

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
     * Execute the job to send milestone_connector WhatsApp notification.
     */
    public function handle(
        WhatsappNotificationService $whatsappService,
        ReferralService $referralService,
        IntroducedPeerCreativeGenerator $creativeGenerator
    ): void {
        $jobId = $this->job?->getJobId() ?? 'sync';
        $queueName = $this->queue ?: config('queue.connections.'.config('queue.default').'.queue', 'default');

        Log::info('[SendMilestoneConnectorWhatsappJob] Job started.', [
            'user_id' => $this->userId,
            'job_id' => $jobId,
            'queue' => $queueName,
            'template_key' => self::TEMPLATE_KEY,
        ]);

        $user = User::find($this->userId);

        if (! $user) {
            Log::warning('[SendMilestoneConnectorWhatsappJob] Skipped: User not found.', [
                'user_id' => $this->userId,
                'job_id' => $jobId,
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

        $introducedCount = (int) ($user->members_introduced_count ?? 1);
        if ($introducedCount <= 0) {
            $introducedCount = 1;
        }

        $deterministicLogId = MilestoneConnectorWhatsappService::getDeterministicLogId($this->userId, self::TEMPLATE_KEY, $introducedCount);

        if ($rawPhone === null || $phoneSource === null) {
            Log::warning('[SendMilestoneConnectorWhatsappJob] Skipped: No valid phone number found.', [
                'user_id' => $this->userId,
                'phone' => $user->phone,
                'secondary_mobile' => $user->secondary_mobile,
                'job_id' => $jobId,
                'template_key' => self::TEMPLATE_KEY,
            ]);

            $this->logDelivery($this->userId, '', 'failed', 'No valid recipient phone number found.', [], $introducedCount);

            return;
        }

        $normalizedPhone = WhatsappNotificationService::normalizePhone((string) $rawPhone);

        Log::info('[SendMilestoneConnectorWhatsappJob] Resolved recipient phone.', [
            'user_id' => $this->userId,
            'phone' => (string) $rawPhone,
            'phone_source' => $phoneSource,
            'normalized_phone' => $normalizedPhone,
            'job_id' => $jobId,
            'queue' => $queueName,
            'template_key' => self::TEMPLATE_KEY,
        ]);

        // 1. Construct canonical data object
        $honourMeta = $creativeGenerator->getHonourMeta($introducedCount);
        $selectedMilestone = ucwords(strtolower($honourMeta['title'] ?? 'Connector'));
        $milestoneKey = strtoupper(trim((string) ($honourMeta['title'] ?? 'CONNECTOR')));

        // Atomically acquire execution claim in DB
        $canProceed = true;
        if (Schema::hasTable('notification_delivery_logs')) {
            try {
                $canProceed = DB::transaction(function () use ($deterministicLogId, $milestoneKey, $introducedCount): bool {
                    $log = NotificationDeliveryLog::where('id', $deterministicLogId)->lockForUpdate()->first();
                    if ($log) {
                        if ($log->status === 'sent') {
                            return false;
                        }
                        // If already processing in the last 2 minutes, prevent concurrent execution race
                        if ($log->status === 'processing' && $log->updated_at && $log->updated_at->gt(now()->subMinutes(2))) {
                            return false;
                        }
                        $log->status = 'processing';
                        $log->save();

                        return true;
                    }

                    if ($this->alreadySent($this->userId, $milestoneKey)) {
                        return false;
                    }

                    NotificationDeliveryLog::create([
                        'id' => $deterministicLogId,
                        'user_id' => $this->userId,
                        'channel' => 'whatsapp',
                        'provider' => self::TEMPLATE_KEY,
                        'status' => 'processing',
                        'request_payload' => [
                            'template_key' => self::TEMPLATE_KEY,
                            'introduced_count' => $introducedCount,
                        ],
                        'attempted_at' => now(),
                    ]);

                    return true;
                });
            } catch (Throwable $lockEx) {
                Log::warning('[SendMilestoneConnectorWhatsappJob] Could not acquire execution lock: '.$lockEx->getMessage());
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
            Log::info('[SendMilestoneConnectorWhatsappJob] Skipped: Milestone already sent or currently processing.', [
                'user_id' => $this->userId,
                'job_id' => $jobId,
                'template_key' => self::TEMPLATE_KEY,
                'selected_milestone' => $selectedMilestone,
                'introduced_count' => $introducedCount,
            ]);

            return;
        }

        // Verify template exists in whatsapp_templates
        $template = WhatsappTemplate::query()
            ->whereIn('template_key', [self::TEMPLATE_KEY, 'milestone_badge_whatsapp'])
            ->first();

        if (! $template) {
            Log::warning('[SendMilestoneConnectorWhatsappJob] Skipped: Template not found in database.', [
                'template_key' => self::TEMPLATE_KEY,
                'job_id' => $jobId,
            ]);

            $this->logDelivery($this->userId, (string) $rawPhone, 'failed', 'Template key not found in database: '.self::TEMPLATE_KEY, [], $introducedCount);

            return;
        }

        if (! $template->is_active) {
            Log::info('[SendMilestoneConnectorWhatsappJob] Skipped: Template is inactive.', [
                'template_key' => self::TEMPLATE_KEY,
                'job_id' => $jobId,
            ]);

            $this->logDelivery($this->userId, (string) $rawPhone, 'failed', 'Template is inactive: '.self::TEMPLATE_KEY, [], $introducedCount);

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

        // 3. Resolve Personalized Connector Creative Image URL using centralized CreativePublicUrlResolver
        $badgeImageUrl = null;
        try {
            $resolver = app(CreativePublicUrlResolver::class);
            $badgeImageUrl = $resolver->resolveForUser($user, $introducedCount, $this->customImageUrl);
        } catch (Throwable $e) {
            Log::error('[SendMilestoneConnectorWhatsappJob] CreativePublicUrlResolver failed: '.$e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);
            $this->logDelivery($this->userId, (string) $rawPhone, 'failed', 'Personalized creative resolution failed: '.$e->getMessage(), [], $introducedCount);
            throw new \RuntimeException('Failed to resolve personalized creative for Connector milestone: '.$e->getMessage(), 0, $e);
        }

        if (blank($badgeImageUrl) || ! $this->isValidPublicMediaUrl($badgeImageUrl)) {
            $errorMsg = 'Personalized creative URL is invalid or inaccessible for Connector milestone: '.(string) $badgeImageUrl;
            Log::error('[SendMilestoneConnectorWhatsappJob] '.$errorMsg, ['user_id' => $user->id]);
            $this->logDelivery($this->userId, (string) $rawPhone, 'failed', $errorMsg, [], $introducedCount);
            throw new \RuntimeException($errorMsg);
        }

        Log::info('[SendMilestoneConnectorWhatsappJob] Creative resolved.', [
            'user_id' => $this->userId,
            'creative_url' => $badgeImageUrl,
            'milestone' => $selectedMilestone,
            'introduced_count' => $introducedCount,
        ]);

        // Body Parameters for template milestone_connector_v2:
        // {{1}} = Member / Peer Name (e.g. Vinit Chavda)
        // {{2}} = Introducer / Member Name (e.g. Vinit Chavda) for "Join Peers Global through {{2}}'s invitation:"
        // {{3}} = Member Referral Link
        $bodyParam1 = $memberName;
        $bodyParam2 = $memberName;
        $bodyParam3 = $referralLink;

        $payload = [
            'name' => $bodyParam1,
            'member_name' => $bodyParam1,
            'peer_name' => $bodyParam1,
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
            'Peer Name' => $bodyParam1,
            '@Peer Name' => $bodyParam1,
            'Peer_Name' => $bodyParam1,
            '@Peer_Name' => $bodyParam1,
            '@peer_name' => $bodyParam1,
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
                'Connector Name' => $bodyParam2,
                'Referral Link' => $bodyParam3,
                'peer_name' => $bodyParam1,
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
                'Connector Name' => $bodyParam2,
                'Referral Link' => $bodyParam3,
            ],
        ];

        Log::info('[SendMilestoneConnectorWhatsappJob] Payload constructed.', [
            'user_id' => $this->userId,
            'template_name' => $template->template_name ?: self::TEMPLATE_KEY,
            'body_param_1' => $bodyParam1,
            'body_param_2' => $bodyParam2,
            'body_param_3' => $bodyParam3,
            'creative_url' => $badgeImageUrl,
            'phone' => (string) $rawPhone,
            'normalized_phone' => $normalizedPhone,
            'job_id' => $jobId,
        ]);

        Log::info('[SendMilestoneConnectorWhatsappJob] Sending webhook request.', [
            'template_key' => self::TEMPLATE_KEY,
            'user_id' => $this->userId,
            'phone' => (string) $rawPhone,
            'normalized_phone' => $normalizedPhone,
            'job_id' => $jobId,
        ]);

        try {
            $success = $whatsappService->send(self::TEMPLATE_KEY, (string) $rawPhone, $payload);

            Log::info('[SendMilestoneConnectorWhatsappJob] Webhook response received.', [
                'user_id' => $this->userId,
                'template_key' => self::TEMPLATE_KEY,
                'success' => $success,
                'job_id' => $jobId,
            ]);

            if ($success) {
                $this->logDelivery($this->userId, (string) $rawPhone, 'sent', null, $payload, $introducedCount);

                Log::info('[SendMilestoneConnectorWhatsappJob] Milestone connector WhatsApp delivered successfully.', [
                    'user_id' => $this->userId,
                    'phone' => (string) $rawPhone,
                    'template_key' => self::TEMPLATE_KEY,
                    'badge_image_url' => $badgeImageUrl,
                    'referral_link' => $referralLink,
                    'job_id' => $jobId,
                ]);
            } else {
                $errorMessage = WhatsappNotificationService::$lastError ?? 'Webhook check failed or template inactive';
                $this->logDelivery($this->userId, (string) $rawPhone, 'failed', $errorMessage, $payload, $introducedCount);

                Log::error('[SendMilestoneConnectorWhatsappJob] Milestone connector WhatsApp delivery failed.', [
                    'template_key' => self::TEMPLATE_KEY,
                    'user_id' => $this->userId,
                    'phone' => (string) $rawPhone,
                    'error' => $errorMessage,
                    'job_id' => $jobId,
                ]);
            }
        } catch (Throwable $exception) {
            Log::error('[SendMilestoneConnectorWhatsappJob] Milestone connector WhatsApp threw exception: '.$exception->getMessage(), [
                'template_key' => self::TEMPLATE_KEY,
                'user_id' => $this->userId,
                'phone' => (string) $rawPhone,
                'job_id' => $jobId,
                'exception' => $exception,
            ]);

            $this->logDelivery($this->userId, (string) $rawPhone, 'failed', $exception->getMessage(), $payload, $introducedCount);
        }
    }

    /**
     * Handle permanent job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('[SendMilestoneConnectorWhatsappJob] Job failed permanently.', [
            'user_id' => $this->userId,
            'job_id' => $this->job?->getJobId(),
            'error' => $exception?->getMessage(),
            'template_key' => self::TEMPLATE_KEY,
        ]);

        $this->logDelivery(
            $this->userId,
            '',
            'failed',
            $exception?->getMessage() ?? 'Job failed permanently in queue',
            [],
            1
        );
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

    private function logDelivery(string $userId, string $phone, string $status, ?string $errorMessage, array $payload = [], int $introducedCount = 1): void
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return;
        }

        $loggedPayload = [
            'template_key' => self::TEMPLATE_KEY,
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

        $deterministicLogId = MilestoneConnectorWhatsappService::getDeterministicLogId($userId, self::TEMPLATE_KEY, $introducedCount);

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

            $updateData = [
                'status' => $status,
                'request_payload' => $loggedPayload,
                'error_message' => $errorMessage,
                'delivered_at' => $status === 'sent' ? now() : null,
            ];

            if (Schema::hasColumn('notification_delivery_logs', 'provider_message_id')) {
                $updateData['provider_message_id'] = $providerMessageId;
            }

            if (Schema::hasColumn('notification_delivery_logs', 'response_payload')) {
                $updateData['response_payload'] = $lastResponse ?? [];
            }

            if ($existingLog) {
                $existingLog->update($updateData);
            } else {
                $createData = [
                    'id' => $deterministicLogId,
                    'user_id' => $userId,
                    'channel' => 'whatsapp',
                    'provider' => self::TEMPLATE_KEY,
                    'status' => $status,
                    'request_payload' => $loggedPayload,
                    'error_message' => $errorMessage,
                    'attempted_at' => now(),
                    'delivered_at' => $status === 'sent' ? now() : null,
                ];

                if (Schema::hasColumn('notification_delivery_logs', 'provider_message_id')) {
                    $createData['provider_message_id'] = $providerMessageId;
                }

                if (Schema::hasColumn('notification_delivery_logs', 'response_payload')) {
                    $createData['response_payload'] = $lastResponse ?? [];
                }

                NotificationDeliveryLog::create($createData);
            }
        } catch (Throwable $e) {
            Log::error('[SendMilestoneConnectorWhatsappJob] Failed to write NotificationDeliveryLog: '.$e->getMessage());
        }
    }

    /**
     * Resolve a publicly accessible, stable HTTPS personalized badge image URL for WhatsApp delivery.
     */
    public function resolveBadgeImageUrl(?User $user = null, int $introducedCount = 1): ?string
    {
        if ($user) {
            try {
                $generator = app(IntroducedPeerCreativeGenerator::class);

                return $generator->generateOrGetUrl($user, $introducedCount);
            } catch (Throwable $e) {
                Log::error('[SendMilestoneConnectorWhatsappJob] Failed generating personalized badge image in fallback: '.$e->getMessage(), [
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
