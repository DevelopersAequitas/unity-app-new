<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Models\WhatsappMessageDeliveryLog;
use App\Models\WhatsappTemplate;
use App\Services\Notifications\MilestoneWhatsappNotificationService;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SendMilestoneWhatsappJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
        public int $introducedCount,
        public string $imageUrl,
        public ?string $deterministicLogId = null
    ) {
        $this->afterCommit = true;
    }

    /**
     * Execute the job to send milestone WhatsApp notification.
     */
    public function handle(WhatsappNotificationService $whatsappService): void
    {
        $jobId = $this->job?->getJobId() ?? 'sync';
        $templateKey = MilestoneWhatsappNotificationService::MILESTONE_TEMPLATES[$this->introducedCount] ?? null;

        if (! $templateKey) {
            Log::warning('[SendMilestoneWhatsappJob] Skipped: Invalid milestone count.', [
                'user_id' => $this->userId,
                'introduced_count' => $this->introducedCount,
                'job_id' => $jobId,
            ]);

            return;
        }

        $logId = $this->deterministicLogId
            ?: MilestoneWhatsappNotificationService::getDeterministicLogId($this->userId, $templateKey);

        $user = User::find($this->userId);
        if (! $user) {
            Log::warning('[SendMilestoneWhatsappJob] Skipped: User record not found.', [
                'user_id' => $this->userId,
                'job_id' => $jobId,
                'template_key' => $templateKey,
            ]);

            $this->updateDeliveryLog($logId, $this->userId, $templateKey, $templateKey, '', $this->imageUrl, 'failed', 'User record not found in database.', [], []);

            return;
        }

        // 1. Resolve active template
        $template = WhatsappTemplate::query()->where('template_key', $templateKey)->first();
        if (! $template) {
            if ($templateKey === 'milestone_connector') {
                $template = WhatsappTemplate::query()->where('template_key', 'milestone_badge_whatsapp')->first();
            } elseif ($templateKey === 'milestone_badge_whatsapp') {
                $template = WhatsappTemplate::query()->where('template_key', 'milestone_connector')->first();
            }
        }

        if (! $template) {
            $errorMsg = "Template key not found in database: {$templateKey}";
            Log::warning("[SendMilestoneWhatsappJob] Skipped: {$errorMsg}", [
                'template_key' => $templateKey,
                'job_id' => $jobId,
            ]);

            $this->updateDeliveryLog($logId, $this->userId, $templateKey, $templateKey, (string) ($user->phone ?? ''), $this->imageUrl, 'failed', $errorMsg, [], []);

            return;
        }

        $templateName = $template->template_name ?: $templateKey;

        if (! $template->is_active) {
            $errorMsg = "Template is inactive: {$templateKey}";
            Log::info("[SendMilestoneWhatsappJob] Skipped: {$errorMsg}", [
                'template_key' => $templateKey,
                'job_id' => $jobId,
            ]);

            $this->updateDeliveryLog($logId, $this->userId, $templateKey, $templateName, (string) ($user->phone ?? ''), $this->imageUrl, 'failed', $errorMsg, [], []);

            return;
        }

        // 2. Resolve recipient phone number
        $primaryPhone = trim((string) ($user->phone ?? ''));
        $secondaryPhone = trim((string) ($user->secondary_mobile ?? ''));

        $rawPhone = null;
        if ($primaryPhone !== '' && static::isValidPhoneNumber($primaryPhone)) {
            $rawPhone = $primaryPhone;
        } elseif ($secondaryPhone !== '' && static::isValidPhoneNumber($secondaryPhone)) {
            $rawPhone = $secondaryPhone;
        }

        if ($rawPhone === null) {
            $errorMsg = 'No valid recipient phone number found.';
            Log::warning("[SendMilestoneWhatsappJob] Skipped: {$errorMsg}", [
                'user_id' => $this->userId,
                'phone' => $user->phone,
                'secondary_mobile' => $user->secondary_mobile,
                'template_key' => $templateKey,
                'job_id' => $jobId,
            ]);

            $this->updateDeliveryLog($logId, $this->userId, $templateKey, $templateName, '', $this->imageUrl, 'failed', $errorMsg, [], []);

            return;
        }

        $normalizedPhone = WhatsappNotificationService::normalizePhone((string) $rawPhone);

        // 3. Resolve milestone user name (Variables 1 and 2 MUST resolve to the SAME milestone user's name)
        $memberName = trim((string) ($user->display_name ?: (($user->first_name ?? '').' '.($user->last_name ?? ''))));
        if ($memberName === '') {
            $memberName = trim((string) ($user->name ?? 'Valued Member'));
        }
        $firstName = trim((string) ($user->first_name ?: (explode(' ', $memberName)[0] ?? $memberName)));

        $bodyParam1 = $memberName;
        $bodyParam2 = $memberName;

        // 4. Resolve environment-aware referral link (Variable 3)
        $isProduction = app()->environment('production') || config('app.env') === 'production';
        $baseUrl = $isProduction ? 'https://peersunity.com' : 'https://dev.peersunity.com';
        $referralLink = "{$baseUrl}/share?type=referrals";
        $bodyParam3 = $referralLink;

        // 5. Header Media URL -> exact introduction_creatives.image_url
        $headerMediaUrl = trim((string) $this->imageUrl);

        if ($headerMediaUrl === '' || str_contains($headerMediaUrl, '/images/member_introduce_badges/')) {
            $errorMsg = $headerMediaUrl === ''
                ? "Milestone creative image_url is missing for count {$this->introducedCount}."
                : "Milestone creative image_url is an unrendered raw badge template for count {$this->introducedCount}.";
            Log::error("[SendMilestoneWhatsappJob] Skipped: {$errorMsg}", [
                'user_id' => $this->userId,
                'introduced_count' => $this->introducedCount,
                'template_key' => $templateKey,
            ]);

            $this->updateDeliveryLog($logId, $this->userId, $templateKey, $templateName, $normalizedPhone, $headerMediaUrl, 'failed', $errorMsg, [], []);

            return;
        }

        // 6. Build variables payload with full mapping aliases
        $payload = [
            'name' => $bodyParam1,
            'member_name' => $bodyParam1,
            'peer_name' => $bodyParam1,
            'connector_name' => $bodyParam1,
            'catalyst_name' => $bodyParam1,
            'first_name' => $firstName,
            'referrer_name' => $bodyParam2,
            'inviter_name' => $bodyParam2,
            'referral_link' => $bodyParam3,
            'link' => $bodyParam3,
            'url' => $bodyParam3,
            'header_media_url' => $headerMediaUrl,
            'badge_image_url' => $headerMediaUrl,
            'header_image_url' => $headerMediaUrl,
            'image_url' => $headerMediaUrl,
            'creative_url' => $headerMediaUrl,
            'media_url' => $headerMediaUrl,
            'phone' => $normalizedPhone,

            // Indexed variables
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
            'body_param_1' => $bodyParam1,
            'body_param_2' => $bodyParam2,
            'body_param_3' => $bodyParam3,
            'body_parameters' => [
                $bodyParam1,
                $bodyParam2,
                $bodyParam3,
            ],
            'variables' => [
                '1' => $bodyParam1,
                '2' => $bodyParam2,
                '3' => $bodyParam3,
                'name' => $bodyParam1,
                'referrer_name' => $bodyParam2,
                'referral_link' => $bodyParam3,
                'header_media_url' => $headerMediaUrl,
            ],

            'introduced_count' => $this->introducedCount,
            'delivery_log_id' => $logId,
        ];

        Log::info('[SendMilestoneWhatsappJob] Dispatching webhook request for milestone.', [
            'user_id' => $this->userId,
            'introduced_count' => $this->introducedCount,
            'template_key' => $templateKey,
            'template_name' => $templateName,
            'body_param_1' => $bodyParam1,
            'body_param_2' => $bodyParam2,
            'body_param_3' => $bodyParam3,
            'referrer_name' => $bodyParam2,
            'header_media_url' => $headerMediaUrl,
            'phone' => $normalizedPhone,
            'log_id' => $logId,
        ]);

        try {
            $success = $whatsappService->send($templateKey, (string) $rawPhone, $payload, $this->userId);

            $lastResponse = WhatsappNotificationService::$lastResponse;
            $providerMessageId = null;
            if (is_array($lastResponse)) {
                $rawId = $lastResponse['wamid']
                    ?? $lastResponse['provider_message_id']
                    ?? $lastResponse['message_id']
                    ?? $lastResponse['log_id']
                    ?? null;
                if ($rawId !== null && (is_string($rawId) || is_numeric($rawId))) {
                    $providerMessageId = (string) $rawId;
                }
            }

            if ($success) {
                $this->updateDeliveryLog(
                    $logId,
                    $this->userId,
                    $templateKey,
                    $templateName,
                    $normalizedPhone,
                    $headerMediaUrl,
                    'sent',
                    null,
                    $payload,
                    $lastResponse ?? [],
                    $providerMessageId,
                    now()
                );

                Log::info('[SendMilestoneWhatsappJob] Milestone WhatsApp delivered successfully.', [
                    'user_id' => $this->userId,
                    'introduced_count' => $this->introducedCount,
                    'template_key' => $templateKey,
                    'template_name' => $templateName,
                    'phone' => $normalizedPhone,
                    'header_media_url' => $headerMediaUrl,
                    'provider_message_id' => $providerMessageId,
                    'log_id' => $logId,
                    'status' => 'sent',
                ]);
            } else {
                $errorMessage = WhatsappNotificationService::$lastError ?? 'Webhook request failed or returned error.';
                $this->updateDeliveryLog(
                    $logId,
                    $this->userId,
                    $templateKey,
                    $templateName,
                    $normalizedPhone,
                    $headerMediaUrl,
                    'failed',
                    $errorMessage,
                    $payload,
                    $lastResponse ?? []
                );

                Log::error('[SendMilestoneWhatsappJob] Milestone WhatsApp delivery failed.', [
                    'user_id' => $this->userId,
                    'introduced_count' => $this->introducedCount,
                    'template_key' => $templateKey,
                    'template_name' => $templateName,
                    'phone' => $normalizedPhone,
                    'header_media_url' => $headerMediaUrl,
                    'error' => $errorMessage,
                    'log_id' => $logId,
                    'status' => 'failed',
                ]);
            }
        } catch (Throwable $e) {
            Log::error('[SendMilestoneWhatsappJob] Milestone WhatsApp threw exception: '.$e->getMessage(), [
                'user_id' => $this->userId,
                'introduced_count' => $this->introducedCount,
                'template_key' => $templateKey,
                'exception' => $e,
                'log_id' => $logId,
            ]);

            $this->updateDeliveryLog(
                $logId,
                $this->userId,
                $templateKey,
                $templateName,
                $normalizedPhone,
                $headerMediaUrl,
                'failed',
                $e->getMessage(),
                $payload,
                []
            );
        }
    }

    /**
     * Handle permanent job failure in queue.
     */
    public function failed(?Throwable $exception): void
    {
        $templateKey = MilestoneWhatsappNotificationService::MILESTONE_TEMPLATES[$this->introducedCount] ?? 'milestone';
        $logId = $this->deterministicLogId
            ?: MilestoneWhatsappNotificationService::getDeterministicLogId($this->userId, $templateKey);

        Log::error('[SendMilestoneWhatsappJob] Job failed permanently in queue.', [
            'user_id' => $this->userId,
            'introduced_count' => $this->introducedCount,
            'template_key' => $templateKey,
            'error' => $exception?->getMessage(),
            'log_id' => $logId,
        ]);

        $this->updateDeliveryLog(
            $logId,
            $this->userId,
            $templateKey,
            $templateKey,
            '',
            $this->imageUrl,
            'failed',
            $exception?->getMessage() ?? 'Job failed permanently in queue',
            [],
            []
        );
    }

    /**
     * Update or create delivery log in whatsapp_message_delivery_logs.
     */
    private function updateDeliveryLog(
        string $logId,
        string $userId,
        string $templateKey,
        string $templateName,
        string $phone,
        ?string $creativeUrl,
        string $status,
        ?string $errorMessage,
        array $requestPayload = [],
        array $responsePayload = [],
        ?string $providerMessageId = null,
        ?\DateTimeInterface $deliveredAt = null
    ): void {
        if (! Schema::hasTable('whatsapp_message_delivery_logs')) {
            return;
        }

        try {
            $existing = WhatsappMessageDeliveryLog::find($logId);

            $data = [
                'user_id' => $userId,
                'template_key' => $templateKey,
                'template_name' => $templateName,
                'phone' => $phone,
                'creative_url' => $creativeUrl,
                'provider' => 'fleximsg',
                'status' => $status,
                'error_message' => $errorMessage,
                'request_payload' => ! empty($requestPayload) ? $requestPayload : ($existing?->request_payload ?? []),
                'response_payload' => ! empty($responsePayload) ? $responsePayload : ($existing?->response_payload ?? []),
                'attempted_at' => now(),
            ];

            if ($providerMessageId !== null) {
                $data['provider_message_id'] = $providerMessageId;
            }

            if ($deliveredAt !== null) {
                $data['delivered_at'] = $deliveredAt;
            } elseif ($status === 'failed') {
                $data['delivered_at'] = null;
            }

            if ($existing) {
                $existing->update($data);
            } else {
                $data['id'] = $logId;
                WhatsappMessageDeliveryLog::create($data);
            }
        } catch (Throwable $e) {
            Log::error('[SendMilestoneWhatsappJob] Failed updating whatsapp_message_delivery_logs: '.$e->getMessage());
        }
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

        if (preg_match('/[^\d\+\-\s\(\)]/', $trimmed)) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return false;
        }

        return true;
    }
}
