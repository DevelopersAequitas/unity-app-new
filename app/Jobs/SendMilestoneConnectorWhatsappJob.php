<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\IntroductionCreative;
use App\Models\MilestoneBadge;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
use App\Services\Notifications\MilestoneConnectorWhatsappService;
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

class SendMilestoneConnectorWhatsappJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const TEMPLATE_KEY = 'milestone_connector';

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
        $lockKey = "milestone_connector_job_exec_{$this->userId}";
        $lock = Cache::lock($lockKey, 30);

        $lock->get(function () use ($whatsappService, $referralService, $creativeGenerator): void {
            $user = User::find($this->userId);

            if (! $user) {
                Log::warning('[SendMilestoneConnectorWhatsappJob] Skipped: User not found.', [
                    'user_id' => $this->userId,
                    'template_key' => self::TEMPLATE_KEY,
                ]);

                return;
            }

            $rawPhone = $user->phone ?? $user->secondary_mobile;

            if (blank($rawPhone)) {
                Log::warning('[SendMilestoneConnectorWhatsappJob] Skipped: Missing phone number.', [
                    'user_id' => $this->userId,
                    'template_key' => self::TEMPLATE_KEY,
                ]);

                return;
            }

            // 1. Construct canonical data object
            $introducedCount = (int) ($user->members_introduced_count ?? 1);
            if ($introducedCount <= 0) {
                $introducedCount = 1;
            }

            // Determine specific milestone title and tier key
            $honourMeta = $creativeGenerator->getHonourMeta($introducedCount);
            $selectedMilestone = ucwords(strtolower($honourMeta['title'] ?? 'Connector'));
            $milestoneKey = strtoupper(trim((string) ($honourMeta['title'] ?? 'CONNECTOR')));

            $deterministicLogId = MilestoneConnectorWhatsappService::getDeterministicLogId($this->userId, self::TEMPLATE_KEY, $introducedCount);

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
                    'template_key' => self::TEMPLATE_KEY,
                    'selected_milestone' => $selectedMilestone,
                    'introduced_count' => $introducedCount,
                ]);

                return;
            }

            // Verify template exists in whatsapp_templates
            $template = WhatsappTemplate::query()
                ->where('template_key', self::TEMPLATE_KEY)
                ->first();

            if (! $template) {
                Log::warning('[SendMilestoneConnectorWhatsappJob] Skipped: Template not found in database.', [
                    'template_key' => self::TEMPLATE_KEY,
                ]);

                return;
            }

            if (! $template->is_active) {
                Log::info('[SendMilestoneConnectorWhatsappJob] Skipped: Template is inactive.', [
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

            // 3. Resolve Personalized Connector Creative Image URL (reuse stored creative if available)
            $badgeImageUrl = null;
            if (! empty($this->customImageUrl) && $this->isValidPublicMediaUrl($this->customImageUrl)) {
                $badgeImageUrl = $this->customImageUrl;
            }

            if (blank($badgeImageUrl) && Schema::hasTable('introduction_creatives')) {
                try {
                    $storedCreative = IntroductionCreative::query()
                        ->where('introducer_id', $this->userId)
                        ->where('introduced_count', $introducedCount)
                        ->latest()
                        ->first();

                    if ($storedCreative && ! empty($storedCreative->image_url) && $this->isValidPublicMediaUrl($storedCreative->image_url)) {
                        $badgeImageUrl = $storedCreative->image_url;
                    }
                } catch (Throwable $e) {
                    Log::warning('[SendMilestoneConnectorWhatsappJob] Could not check introduction_creatives: '.$e->getMessage());
                }
            }

            if (blank($badgeImageUrl)) {
                if (! empty($user->connector_creative_url) && $this->isValidPublicMediaUrl($user->connector_creative_url)) {
                    $badgeImageUrl = $user->connector_creative_url;
                } elseif (! empty($user->growth_creative_url) && $this->isValidPublicMediaUrl($user->growth_creative_url)) {
                    $badgeImageUrl = $user->growth_creative_url;
                }
            }

            if (blank($badgeImageUrl)) {
                try {
                    $badgeImageUrl = $creativeGenerator->generateOrGetUrl($user, $introducedCount);
                } catch (Throwable $e) {
                    Log::error('[SendMilestoneConnectorWhatsappJob] Failed generating personalized connector creative: '.$e->getMessage(), [
                        'user_id' => $user->id,
                        'exception' => $e,
                    ]);
                }
            }

            if (blank($badgeImageUrl) || ! $this->isValidPublicMediaUrl($badgeImageUrl)) {
                $badgeImageUrl = $this->resolveBadgeImageUrl($introducedCount);
            }

            // Body Parameters for template milestone_connector_v2:
            // {{1}} = Member / Peer Name
            // {{2}} = Selected Milestone Recognition (e.g. "Connector", "Ambassador")
            // {{3}} = Member Referral Link
            $bodyParam1 = $memberName;
            $bodyParam2 = $selectedMilestone;
            $bodyParam3 = $referralLink;

            // Structured pre-dispatch logging
            Log::info('[SendMilestoneConnectorWhatsappJob] Dispatching milestone WhatsApp notification.', [
                'introduced_count' => $introducedCount,
                'selected_milestone' => $selectedMilestone,
                'template_name' => $template->template_name ?: self::TEMPLATE_KEY,
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
                'connector_name' => $bodyParam1,
                'first_name' => $firstName,
                'inviter_name' => $bodyParam1,

                'milestone' => $bodyParam2,
                'milestone_name' => $bodyParam2,
                'milestone_title' => $bodyParam2,
                'honour_title' => $bodyParam2,
                'award_name' => $bodyParam2,
                'title' => $bodyParam2,
                'selected_milestone' => $bodyParam2,
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

            try {
                $success = $whatsappService->send(self::TEMPLATE_KEY, (string) $rawPhone, $payload);

                if ($success) {
                    $this->logDelivery($this->userId, (string) $rawPhone, 'sent', null, $payload, $introducedCount);

                    Log::info('[SendMilestoneConnectorWhatsappJob] Milestone connector WhatsApp delivered successfully.', [
                        'user_id' => $this->userId,
                        'phone' => (string) $rawPhone,
                        'template_key' => self::TEMPLATE_KEY,
                        'badge_image_url' => $badgeImageUrl,
                        'referral_link' => $referralLink,
                    ]);
                } else {
                    $errorMessage = WhatsappNotificationService::$lastError ?? 'Webhook check failed or template inactive';
                    $this->logDelivery($this->userId, (string) $rawPhone, 'failed', $errorMessage, $payload, $introducedCount);

                    Log::error('[SendMilestoneConnectorWhatsappJob] Milestone connector WhatsApp delivery failed.', [
                        'template_key' => self::TEMPLATE_KEY,
                        'user_id' => $this->userId,
                        'phone' => (string) $rawPhone,
                        'error' => $errorMessage,
                    ]);
                }
            } catch (Throwable $exception) {
                Log::error('[SendMilestoneConnectorWhatsappJob] Milestone connector WhatsApp threw exception: '.$exception->getMessage(), [
                    'template_key' => self::TEMPLATE_KEY,
                    'user_id' => $this->userId,
                    'phone' => (string) $rawPhone,
                    'exception' => $exception,
                ]);

                $this->logDelivery($this->userId, (string) $rawPhone, 'failed', $exception->getMessage(), $payload, $introducedCount);
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
            Log::error('[SendMilestoneConnectorWhatsappJob] Failed to write NotificationDeliveryLog: '.$e->getMessage());
        }
    }

    /**
     * Resolve a publicly accessible, stable HTTPS badge image URL for WhatsApp delivery.
     */
    public function resolveBadgeImageUrl(int $introducedCount = 1): string
    {
        $baseUrl = IntroducedPeerCreativeGenerator::getPublicBaseUrl();

        // 1. Look up milestone_badges table for the exact or highest threshold badge
        $badge = MilestoneBadge::query()
            ->where('type', MilestoneBadge::TYPE_MEMBER_INTRODUCTION)
            ->where('required_count', $introducedCount)
            ->first();

        if (! $badge && $introducedCount > 1) {
            $badge = MilestoneBadge::query()
                ->where('type', MilestoneBadge::TYPE_MEMBER_INTRODUCTION)
                ->where('required_count', '<=', $introducedCount)
                ->orderByDesc('required_count')
                ->first();
        }

        $url = $badge?->badge_image_url;
        if (! empty($url)) {
            $url = (string) $url;
            if (str_contains($baseUrl, 'dev.peersunity.com') && str_contains($url, 'peersunity.com') && ! str_contains($url, 'dev.peersunity.com')) {
                $url = str_replace('https://peersunity.com', $baseUrl, $url);
            }
            if ($this->isValidPublicMediaUrl($url)) {
                return $url;
            }
        }

        // 2. Resolve using honour title if available
        if (class_exists(IntroducedPeerCreativeGenerator::class)) {
            try {
                $generator = app(IntroducedPeerCreativeGenerator::class);
                $meta = $generator->getHonourMeta($introducedCount);
                if (! empty($meta['title'])) {
                    $titleCase = ucwords(strtolower($meta['title']));
                    $encodedTitle = str_replace(' ', '%20', $titleCase);
                    $honourUrl = "{$baseUrl}/images/member_introduce_badges/{$encodedTitle}.png";
                    if ($this->isValidPublicMediaUrl($honourUrl)) {
                        return $honourUrl;
                    }
                }
            } catch (Throwable) {
            }
        }

        return "{$baseUrl}/images/member_introduce_badges/Connector.png";
    }

    /**
     * Check if a media URL is a valid, publicly reachable HTTPS image URL (not localhost, not ngrok, not 404 API path).
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

        // For storage uploads, verify physical existence on public disk if running on the host instance
        if (preg_match('~/storage/(uploads/[^?#\s]+)~i', $trimmed, $matches)) {
            $s3Key = $matches[1];
            $exists = Storage::disk('public')->exists($s3Key)
                || file_exists(storage_path('app/public/'.$s3Key))
                || file_exists(public_path('storage/'.$s3Key));

            if ($exists) {
                return true;
            }

            // If not found locally, verify external HTTPS reachability if pointing to a remote host (skipped in unit tests)
            if (! app()->runningUnitTests()) {
                $host = parse_url($trimmed, PHP_URL_HOST);
                if ($host && ! in_array(strtolower($host), ['localhost', '127.0.0.1'], true)) {
                    try {
                        $response = Http::timeout(5)->get($trimmed);
                        if ($response->status() !== 200) {
                            return false;
                        }
                    } catch (Throwable) {
                        return false;
                    }
                }
            }
        }

        // Must have an image extension or valid storage path
        $path = (string) parse_url($trimmed, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true) && ! str_contains($path, '/storage/')) {
            return false;
        }

        return true;
    }
}
