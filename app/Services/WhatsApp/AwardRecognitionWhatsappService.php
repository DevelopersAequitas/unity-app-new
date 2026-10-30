<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\MemberAwardSend;
use App\Models\User;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
use App\Services\Creative\LifeImpactCreativeGenerator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AwardRecognitionWhatsappService
{
    /**
     * Master mapping for Track 1: Growth (Membership Introductions)
     *
     * @var array<int, array{title: string, template: string, language: string}>
     */
    public const GROWTH_TRACK = [
        1 => ['title' => 'CONNECTOR', 'template' => 'milestone_connector_v2', 'language' => 'en_IN'],
        3 => ['title' => 'CATALYST', 'template' => 'pgu_catalyst_3', 'language' => 'en_IN'],
        5 => ['title' => 'INFLUENCER', 'template' => 'milestone_influencer_5', 'language' => 'en_IN'],
        10 => ['title' => 'AMBASSADOR', 'template' => 'milestone_ambassador_10', 'language' => 'en_IN'],
        20 => ['title' => 'RAINMAKER', 'template' => 'milestone_rainmaker_20', 'language' => 'en_IN'],
        35 => ['title' => 'TRAILBLAZER', 'template' => 'milestone_trailblazer_35', 'language' => 'en_IN'],
        50 => ['title' => 'VANGUARD', 'template' => 'milestone_vanguard_50', 'language' => 'en_IN'],
        75 => ['title' => 'LUMINARY', 'template' => 'milestone_luminary_75', 'language' => 'en_IN'],
        100 => ['title' => 'MOVEMENT MAKER', 'template' => 'milestone_movement_maker_100', 'language' => 'en_IN'],
        150 => ['title' => 'COMMUNITY TITAN', 'template' => 'milestone_community_titan_150', 'language' => 'en_IN'],
        250 => ['title' => 'NETWORK ARCHITECT', 'template' => 'milestone_network_architect_250', 'language' => 'en_IN'],
        500 => ['title' => 'GLOBAL ICON', 'template' => 'milestone_global_icon_500', 'language' => 'en_IN'],
    ];

    /**
     * Master mapping for Track 2: Life Impact
     *
     * @var array<int, array{title: string, template: string, language: string}>
     */
    public const IMPACT_TRACK = [
        25 => ['title' => 'IMPACT CREATOR', 'template' => 'impact_creator_25', 'language' => 'en_IN'],
        50 => ['title' => 'CHANGE MAKER', 'template' => 'change_maker_50', 'language' => 'en_IN'],
        100 => ['title' => 'LIFE CHANGER', 'template' => 'life_changer_100_', 'language' => 'en_IN'],
        250 => ['title' => 'IMPACT BUILDER', 'template' => 'impact_builder_250', 'language' => 'en_IN'],
        500 => ['title' => 'ECOSYSTEM BUILDER', 'template' => 'ecosystem_builder_500', 'language' => 'en_IN'],
        1000 => ['title' => 'IMPACT ARCHITECT', 'template' => 'impact_architect_1000', 'language' => 'en_IN'],
        2500 => ['title' => 'LEGACY MAKER', 'template' => 'legacy_maker_2500', 'language' => 'en_IN'],
        5000 => ['title' => 'TORCHBEARER', 'template' => 'torchbearer_5000', 'language' => 'en_IN'],
        10000 => ['title' => 'WORLD CHANGER', 'template' => 'world_changer_10000', 'language' => 'en_IN'],
        25000 => ['title' => 'HUMANITARIAN', 'template' => 'humanitarian_25000', 'language' => 'en_IN'],
        50000 => ['title' => 'HISTORY MAKER', 'template' => 'history_maker_50000', 'language' => 'en_IN'],
        100000 => ['title' => 'PEERS GLOBAL LEGEND', 'template' => 'peers_global_legend_100000_', 'language' => 'en_IN'],
    ];

    public function __construct(
        protected MetaWhatsAppService $metaService,
        protected IntroducedPeerCreativeGenerator $growthCreativeGenerator,
        protected LifeImpactCreativeGenerator $impactCreativeGenerator
    ) {}

    /**
     * Handle Track 1: Growth Award notification.
     */
    public function handleGrowthAward(User $user, int $count, ?string $imageUrl = null, ?string $customPhone = null): ?MemberAwardSend
    {
        if (! isset(self::GROWTH_TRACK[$count])) {
            return null;
        }

        $award = self::GROWTH_TRACK[$count];

        if (empty($imageUrl)) {
            try {
                $imageUrl = $this->growthCreativeGenerator->generateOrGetUrl($user, $count);
            } catch (Throwable $e) {
                Log::warning('[AwardRecognitionWhatsappService] Could not generate growth creative image: ' . $e->getMessage());
            }
        }

        return $this->sendAward(
            $user,
            'GROWTH',
            $award['title'],
            $count,
            $award['template'],
            $award['language'],
            $imageUrl,
            $customPhone
        );
    }

    /**
     * Handle Track 2: Life Impact Award notification.
     */
    public function handleImpactAward(User $user, int $count, ?string $imageUrl = null, ?string $customPhone = null): ?MemberAwardSend
    {
        if (! isset(self::IMPACT_TRACK[$count])) {
            return null;
        }

        $award = self::IMPACT_TRACK[$count];

        if (empty($imageUrl)) {
            try {
                $imageUrl = $this->impactCreativeGenerator->generateOrGetUrl($user, $count, $count);
            } catch (Throwable $e) {
                Log::warning('[AwardRecognitionWhatsappService] Could not generate impact creative image: ' . $e->getMessage());
            }
        }

        return $this->sendAward(
            $user,
            'LIFE_IMPACT',
            $award['title'],
            $count,
            $award['template'],
            $award['language'],
            $imageUrl,
            $customPhone
        );
    }

    /**
     * Send award template message and log to member_award_sends.
     */
    public function sendAward(
        User $user,
        string $track,
        string $title,
        int $milestoneCount,
        string $templateName,
        string $language,
        ?string $imageUrl = null,
        ?string $customPhone = null
    ): MemberAwardSend {
        $rawPhone = $customPhone ?: ($user->phone ?: $user->secondary_mobile);
        $phone = MetaWhatsAppService::normalizePhone((string) $rawPhone);

        $memberName = trim((string) ($user->display_name ?: (($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))));
        if ($memberName === '') {
            $memberName = 'Valued Peer';
        }

        $baseUrl = LifeImpactCreativeGenerator::getPublicBaseUrl();

        if ($track === 'LIFE_IMPACT') {
            $referralLink = "{$baseUrl}/share?type=badges&tab=lives_impacted";
        } else {
            // Track 1: Growth / Member Introduced Track -> Peer's Referral Link (e.g. /register?ref=HARSH94657)
            $refCode = null;
            try {
                $referralService = app(\App\Services\Referrals\ReferralService::class);
                $referralData = $referralService->generateOrGetReferral($user);
                $refCode = $referralData['referral_code'] ?? null;
            } catch (Throwable $refEx) {
                Log::warning('[AwardRecognitionWhatsappService] Could not resolve user referral link: ' . $refEx->getMessage(), [
                    'user_id' => $user->id,
                ]);
            }

            if (empty($refCode)) {
                $refCode = $user->referral_code ?? $user->invite_code ?? null;
            }

            if (empty($refCode)) {
                $cleanName = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) ($user->first_name ?: $user->name ?: 'PEERS')));
                $prefix = Str::padRight(Str::substr($cleanName, 0, 5), 5, 'X');
                $refCode = $prefix . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            }

            $referralLink = "{$baseUrl}/register?ref={$refCode}";
        }

        // Check if imageUrl is missing or points to an unrendered raw template
        $isRawOrEmpty = empty($imageUrl)
            || str_contains($imageUrl, 'life_impact_creatives/')
            || str_contains($imageUrl, 'member_introduce_creatives/')
            || str_contains($imageUrl, 'life_impact_badges/')
            || str_contains($imageUrl, 'member_introduce_badges/');

        if ($isRawOrEmpty) {
            try {
                if ($track === 'LIFE_IMPACT') {
                    $imageUrl = $this->impactCreativeGenerator->generateOrGetUrl($user, $milestoneCount, $milestoneCount);
                } else {
                    $imageUrl = $this->growthCreativeGenerator->generateOrGetUrl($user, $milestoneCount);
                }
            } catch (Throwable $e) {
                Log::warning("[AwardRecognitionWhatsappService] Failed auto-generating personalized creative in sendAward: {$e->getMessage()}", [
                    'user_id' => $user->id,
                    'track' => $track,
                    'milestone' => $milestoneCount,
                ]);
            }
        }

        // Attempt to resolve the physical local file on disk for the personalized creative
        $localCreativeFile = $this->resolveLocalPhysicalFile($imageUrl);

        $mediaId = null;
        $tempDownloadedFile = null;

        if ($localCreativeFile && file_exists($localCreativeFile)) {
            try {
                $mediaId = $this->metaService->uploadMediaFromPath($localCreativeFile);
            } catch (Throwable $uploadEx) {
                Log::warning('[AwardRecognitionWhatsappService] uploadMediaFromPath for personalized creative failed: ' . $uploadEx->getMessage());
            }
        }

        // If local file wasn't found but imageUrl is an external HTTP/HTTPS URL, download temporarily to upload
        if (! $mediaId && ! empty($imageUrl) && (str_starts_with($imageUrl, 'http://') || str_starts_with($imageUrl, 'https://'))) {
            try {
                $response = Http::timeout(10)->get($imageUrl);
                if ($response->successful() && ! empty($response->body())) {
                    $tempDownloadedFile = @tempnam(sys_get_temp_dir(), 'wa_creative_');
                    if ($tempDownloadedFile) {
                        file_put_contents($tempDownloadedFile, $response->body());
                        $mediaId = $this->metaService->uploadMediaFromPath($tempDownloadedFile);
                    }
                }
            } catch (Throwable $dlEx) {
                Log::warning('[AwardRecognitionWhatsappService] Remote download for Meta upload fallback failed: ' . $dlEx->getMessage());
            } finally {
                if ($tempDownloadedFile && file_exists($tempDownloadedFile)) {
                    @unlink($tempDownloadedFile);
                }
            }
        }

        $dir = $track === 'LIFE_IMPACT' ? 'life_impact_creatives' : 'member_introduce_creatives';
        $titleFilename = ucwords(strtolower($title)) . '.png';

        $isPublicUrl = ! empty($imageUrl)
            && str_starts_with($imageUrl, 'https://')
            && ! preg_match('~https?://(localhost|127\.0\.0\.1)([:/]|$)~i', $imageUrl);

        if ($isPublicUrl) {
            $imageParam = ['link' => $imageUrl];
        } elseif ($mediaId) {
            $imageParam = ['id' => $mediaId];
        } else {
            // Absolute fallback to base template only if personalized creative could not be generated, uploaded, or linked
            $fallbackFile = public_path("images/{$dir}/{$titleFilename}");
            if (! file_exists($fallbackFile)) {
                $fallbackFile = public_path("images/{$dir}/{$title}.png");
            }
            if (! file_exists($fallbackFile)) {
                $defaultName = $track === 'LIFE_IMPACT' ? 'Impact Creator.png' : 'Connector.png';
                $fallbackFile = public_path("images/{$dir}/{$defaultName}");
            }

            try {
                if (file_exists($fallbackFile)) {
                    $fallbackMediaId = $this->metaService->uploadMediaFromPath($fallbackFile);
                    $imageParam = ['id' => $fallbackMediaId];
                } else {
                    $imageParam = ['link' => 'https://peersunity.com/images/member_introduce_creatives/Connector.png'];
                }
            } catch (Throwable $fbEx) {
                Log::warning('[AwardRecognitionWhatsappService] Fallback raw template upload failed: ' . $fbEx->getMessage());
                $imageParam = ['link' => 'https://peersunity.com/images/member_introduce_creatives/Connector.png'];
            }
        }

        // Dynamically detect placeholder count in template definition
        // Contract: impact_builder_250 is 2-parameter (member_name, referral_link).
        // Other Life Impact templates (e.g. ecosystem_builder_500, impact_architect_1000, change_maker_50) use 3 parameters.
        $placeholderCount = ($templateName === 'impact_builder_250') ? 2 : 3;
        try {
            if (Schema::hasTable('whatsapp_templates')) {
                $query = \App\Models\WhatsappTemplate::query();
                if (Schema::hasColumn('whatsapp_templates', 'name')) {
                    $query->where('name', $templateName);
                } elseif (Schema::hasColumn('whatsapp_templates', 'template_key')) {
                    $query->where('template_key', $templateName);
                }
                $localTemplate = $query->first();
                if ($localTemplate && is_array($localTemplate->components)) {
                    foreach ($localTemplate->components as $comp) {
                        if (($comp['type'] ?? '') === 'BODY') {
                            $bodyText = (string) ($comp['text'] ?? '');
                            preg_match_all('/\{\{(\d+)\}\}/', $bodyText, $matches);
                            if (! empty($matches[1])) {
                                $placeholderCount = count(array_unique($matches[1]));
                            }
                            break;
                        }
                    }
                }
            }
        } catch (Throwable) {
        }

        $bodyParams = [];
        for ($i = 1; $i <= $placeholderCount; $i++) {
            if ($i === 1) {
                $bodyParams[] = ['type' => 'text', 'text' => $memberName];
            } elseif ($i === 2) {
                $textVal = ($placeholderCount === 2) ? $referralLink : $memberName;
                $bodyParams[] = ['type' => 'text', 'text' => $textVal];
            } elseif ($i === 3) {
                $bodyParams[] = ['type' => 'text', 'text' => $referralLink];
            }
        }

        $components = [
            [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'image',
                        'image' => $imageParam,
                    ],
                ],
            ],
            [
                'type' => 'body',
                'parameters' => $bodyParams,
            ],
        ];

        $displayImageUrl = ! empty($imageUrl)
            ? $imageUrl
            : ("/images/{$dir}/" . basename($titleFilename));

        $isTest = (bool) Config::get('whatsapp.testing.test_mode', false);

        if ($phone === '') {
            Log::warning("[AwardRecognitionWhatsappService] Skipped {$templateName}: User {$user->id} has no valid phone number.");

            if (Schema::hasTable('member_award_sends')) {
                try {
                    return MemberAwardSend::create([
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'award_track' => $track,
                        'award_title' => $title,
                        'milestone_count' => $milestoneCount,
                        'template_name' => $templateName,
                        'language' => $language,
                        'phone_number' => (string) $rawPhone,
                        'creative_image_url' => $displayImageUrl,
                        'referral_link' => $referralLink,
                        'status' => 'failed',
                        'error_message' => 'User has no valid phone number.',
                        'scheduled_at' => now(),
                        'payload_json' => $components,
                        'is_test' => $isTest,
                    ]);
                } catch (Throwable) {
                }
            }

            return null;
        }

        $sendRecord = null;
        if (Schema::hasTable('member_award_sends')) {
            try {
                $sendRecord = MemberAwardSend::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'award_track' => $track,
                    'award_title' => $title,
                    'milestone_count' => $milestoneCount,
                    'template_name' => $templateName,
                    'language' => $language,
                    'phone_number' => $phone,
                    'creative_image_url' => $displayImageUrl,
                    'referral_link' => $referralLink,
                    'status' => 'pending',
                    'scheduled_at' => now(),
                    'payload_json' => $components,
                    'is_test' => $isTest,
                ]);
            } catch (Throwable $dbEx) {
                Log::warning('[AwardRecognitionWhatsappService] Failed creating initial member_award_sends record: ' . $dbEx->getMessage());
            }
        }

        try {
            $result = $this->metaService->sendTemplateMessage(
                $phone,
                $templateName,
                $language,
                $components,
                (string) $user->id
            );

            if ($sendRecord) {
                $sendRecord->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'provider_message_id' => ! empty($result['meta_message_id']) ? $result['meta_message_id'] : ($result['whatsapp_message_id'] ?? null),
                    'provider_response_json' => $result,
                ]);
            }

            Log::info("[AwardRecognitionWhatsappService] Successfully dispatched {$templateName} to {$phone}", [
                'user_id' => $user->id,
                'track' => $track,
                'title' => $title,
                'send_id' => $sendRecord?->id,
            ]);
        } catch (Throwable $e) {
            if ($sendRecord) {
                $sendRecord->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            Log::error("[AwardRecognitionWhatsappService] Failed sending {$templateName} to {$phone}: " . $e->getMessage(), [
                'user_id' => $user->id,
                'track' => $track,
                'title' => $title,
                'send_id' => $sendRecord?->id,
            ]);
        }

        return $sendRecord;
    }

    /**
     * Resolve the physical on-disk file path for a creative image URL or storage key.
     */
    protected function resolveLocalPhysicalFile(?string $imageUrl): ?string
    {
        if (empty($imageUrl)) {
            return null;
        }

        if (file_exists($imageUrl) && is_file($imageUrl)) {
            return $imageUrl;
        }

        $cleaned = $imageUrl;
        if (str_contains($cleaned, '/storage/')) {
            $cleaned = preg_replace('~^https?://[^/]+/storage/~i', '', $cleaned);
            $cleaned = preg_replace('~^/storage/~i', '', $cleaned);
        } elseif (preg_match('~^https?://[^/]+/(.*)$~i', $cleaned, $matches)) {
            $cleaned = $matches[1];
        }
        $cleaned = ltrim($cleaned, '/');

        $candidates = [
            storage_path('app/public/' . $cleaned),
            public_path('storage/' . $cleaned),
            public_path($cleaned),
            storage_path('app/' . $cleaned),
        ];

        try {
            if (Storage::disk('public')->exists($cleaned)) {
                $candidates[] = Storage::disk('public')->path($cleaned);
            }
        } catch (Throwable) {
        }

        foreach ($candidates as $candidate) {
            if (! empty($candidate) && file_exists($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
