<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MilestoneBadge;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
use App\Services\Notifications\WhatsappNotificationService;
use App\Services\Referrals\ReferralService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
        public string $userId
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

        // Check idempotency again inside job execution
        if ($this->alreadySent($this->userId)) {
            Log::info('[SendMilestoneConnectorWhatsappJob] Skipped: Already sent to user.', [
                'user_id' => $this->userId,
                'template_key' => self::TEMPLATE_KEY,
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

        // 1. Construct canonical data object
        $introducedCount = (int) ($user->members_introduced_count ?? 1);
        if ($introducedCount <= 0) {
            $introducedCount = 1;
        }

        $connectorName = trim((string) ($user->display_name ?: (($user->first_name ?? '').' '.($user->last_name ?? ''))));
        if ($connectorName === '') {
            $connectorName = 'Valued Member';
        }
        $peerName = $connectorName;
        $firstName = trim((string) ($user->first_name ?: $connectorName));

        // 2. Canonical Referral Link -> Body {{3}}
        $referralData = $referralService->generateOrGetReferral($user);
        $baseUrl = IntroducedPeerCreativeGenerator::getPublicBaseUrl();
        $referralLink = "{$baseUrl}/share?type=referrals";

        // 3. Generate Personalized Connector Creative Image URL
        $badgeImageUrl = null;
        try {
            $badgeImageUrl = $creativeGenerator->generateOrGetUrl($user, $introducedCount);
        } catch (Throwable $e) {
            Log::error('[SendMilestoneConnectorWhatsappJob] Failed generating personalized connector creative: '.$e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);
        }

        if (blank($badgeImageUrl) || ! $this->isValidPublicMediaUrl($badgeImageUrl)) {
            $badgeImageUrl = $this->resolveBadgeImageUrl($introducedCount);
        }

        $payload = [
            'name' => $peerName,
            'peer_name' => $peerName,
            'member_name' => $peerName,
            'first_name' => $firstName,
            'connector_name' => $connectorName,
            'inviter_name' => $connectorName,
            'introduced_count' => $introducedCount,
            'referral_link' => $referralLink,
            'link' => $referralLink,
            'url' => $referralLink,
            'badge_image_url' => $badgeImageUrl,
            'header_media_url' => $badgeImageUrl,
            'header_image_url' => $badgeImageUrl,
            'header_url' => $badgeImageUrl,
            'image' => $badgeImageUrl,
            'image_url' => $badgeImageUrl,
            'media_url' => $badgeImageUrl,
            '1' => $peerName,
            '2' => $connectorName,
            '3' => $referralLink,
            '@1' => $peerName,
            '@2' => $connectorName,
            '@3' => $referralLink,
            'var_1' => $peerName,
            'var_2' => $connectorName,
            'var_3' => $referralLink,
            'var1' => $peerName,
            'var2' => $connectorName,
            'var3' => $referralLink,
            'body_1' => $peerName,
            'body_2' => $connectorName,
            'body_3' => $referralLink,
            'body_param_1' => $peerName,
            'body_param_2' => $connectorName,
            'body_param_3' => $referralLink,
            '@body_param_1' => $peerName,
            '@body_param_2' => $connectorName,
            '@body_param_3' => $referralLink,
            'Peer Name' => $peerName,
            '@Peer Name' => $peerName,
            'Peer_Name' => $peerName,
            '@Peer_Name' => $peerName,
            '@peer_name' => $peerName,
            'Connector Name' => $connectorName,
            '@Connector Name' => $connectorName,
            'Connector_Name' => $connectorName,
            '@Connector_Name' => $connectorName,
            '@connector_name' => $connectorName,
            'Referral Link' => $referralLink,
            '@Referral Link' => $referralLink,
            'Referral_Link' => $referralLink,
            '@Referral_Link' => $referralLink,
            '@referral_link' => $referralLink,
            'variables' => [
                '1' => $peerName,
                '2' => $connectorName,
                '3' => $referralLink,
                'Peer Name' => $peerName,
                'Connector Name' => $connectorName,
                'Referral Link' => $referralLink,
                'peer_name' => $peerName,
                'connector_name' => $connectorName,
                'referral_link' => $referralLink,
                'body_param_1' => $peerName,
                'body_param_2' => $connectorName,
                'body_param_3' => $referralLink,
            ],
            'body_parameters' => [
                $peerName,
                $connectorName,
                $referralLink,
            ],
            'body_params' => [
                '1' => $peerName,
                '2' => $connectorName,
                '3' => $referralLink,
            ],
            'params' => [
                $peerName,
                $connectorName,
                $referralLink,
            ],
            'custom_params' => [
                'Peer Name' => $peerName,
                'Connector Name' => $connectorName,
                'Referral Link' => $referralLink,
            ],
        ];

        try {
            $success = $whatsappService->send(self::TEMPLATE_KEY, (string) $rawPhone, $payload);

            if ($success) {
                $this->logDelivery($this->userId, (string) $rawPhone, 'sent', null, $payload);

                Log::info('[SendMilestoneConnectorWhatsappJob] Milestone connector WhatsApp delivered successfully.', [
                    'user_id' => $this->userId,
                    'phone' => (string) $rawPhone,
                    'template_key' => self::TEMPLATE_KEY,
                    'badge_image_url' => $badgeImageUrl,
                    'referral_link' => $referralLink,
                ]);
            } else {
                $errorMessage = WhatsappNotificationService::$lastError ?? 'Webhook check failed or template inactive';
                $this->logDelivery($this->userId, (string) $rawPhone, 'failed', $errorMessage, $payload);

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

            $this->logDelivery($this->userId, (string) $rawPhone, 'failed', $exception->getMessage(), $payload);
        }
    }

    private function alreadySent(string $userId): bool
    {
        if (! Schema::hasTable('notification_delivery_logs')) {
            return false;
        }

        try {
            return NotificationDeliveryLog::query()
                ->where('user_id', $userId)
                ->where('channel', 'whatsapp')
                ->where('provider', self::TEMPLATE_KEY)
                ->where('status', 'sent')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function logDelivery(string $userId, string $phone, string $status, ?string $errorMessage, array $payload = []): void
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

        try {
            NotificationDeliveryLog::create([
                'user_id' => $userId,
                'channel' => 'whatsapp',
                'provider' => self::TEMPLATE_KEY,
                'status' => $status,
                'request_payload' => $loggedPayload,
                'error_message' => $errorMessage,
                'attempted_at' => now(),
                'delivered_at' => $status === 'sent' ? now() : null,
            ]);
        } catch (Throwable $e) {
            Log::error('[SendMilestoneConnectorWhatsappJob] Failed to write NotificationDeliveryLog: '.$e->getMessage());
        }
    }

    /**
     * Resolve a publicly accessible, stable HTTPS badge image URL for WhatsApp delivery.
     */
    public function resolveBadgeImageUrl(int $introducedCount = 1): string
    {
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

        // 2. Validate URL: must be HTTPS and must not be a broken 404 URL or local/ngrok URL
        if ($this->isValidPublicMediaUrl($url)) {
            return (string) $url;
        }

        // 3. Resolve using honour title if available
        if (class_exists(IntroducedPeerCreativeGenerator::class)) {
            try {
                $generator = app(IntroducedPeerCreativeGenerator::class);
                $meta = $generator->getHonourMeta($introducedCount);
                if (! empty($meta['title'])) {
                    $titleCase = ucwords(strtolower($meta['title']));
                    $encodedTitle = str_replace(' ', '%20', $titleCase);
                    $baseUrl = IntroducedPeerCreativeGenerator::getPublicBaseUrl();
                    $honourUrl = "{$baseUrl}/images/member_introduce_badges/{$encodedTitle}.png";
                    if ($this->isValidPublicMediaUrl($honourUrl)) {
                        return $honourUrl;
                    }
                }
            } catch (Throwable) {
            }
        }

        $baseUrl = IntroducedPeerCreativeGenerator::getPublicBaseUrl();

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

            if (! $exists) {
                return false;
            }
        }

        return true;
    }
}
