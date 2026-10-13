<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\MemberAwardSend;
use App\Models\User;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
use App\Services\Creative\LifeImpactCreativeGenerator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
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
    public function handleGrowthAward(User $user, int $count, ?string $imageUrl = null): ?MemberAwardSend
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
            $imageUrl
        );
    }

    /**
     * Handle Track 2: Life Impact Award notification.
     */
    public function handleImpactAward(User $user, int $count, ?string $imageUrl = null): ?MemberAwardSend
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
            $imageUrl
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

        $baseUrl = config('app.url', 'https://peersunity.com');
        $referralLink = rtrim($baseUrl, '/') . '/share?type=' . ($track === 'GROWTH' ? 'referrals' : 'life_impact');

        // Resolve physical creative file from disk
        $dir = $track === 'LIFE_IMPACT' ? 'life_impact_creatives' : 'member_introduce_creatives';
        $titleFilename = ucwords(strtolower($title)) . '.png';
        $localImageFile = public_path("images/{$dir}/{$titleFilename}");
        if (! file_exists($localImageFile)) {
            $localImageFile = public_path("images/{$dir}/{$title}.png");
        }
        if (! file_exists($localImageFile)) {
            $fallbackFile = $track === 'LIFE_IMPACT' ? 'Impact Creator.png' : 'Connector.png';
            $localImageFile = public_path("images/{$dir}/{$fallbackFile}");
        }

        $mediaId = null;
        try {
            $mediaId = $this->metaService->uploadMediaFromPath($localImageFile);
        } catch (Throwable $uploadEx) {
            Log::warning('[AwardRecognitionWhatsappService] uploadMediaFromPath fallback: ' . $uploadEx->getMessage());
        }

        $imageParam = $mediaId 
            ? ['id' => $mediaId] 
            : ['link' => (!empty($imageUrl) && str_starts_with($imageUrl, 'http')) ? $imageUrl : 'https://peersunity.com/images/member_introduce_creatives/Connector.png'];

        // Dynamically detect placeholder count in template definition
        $placeholderCount = 3;
        $localTemplate = \App\Models\WhatsappTemplate::where('name', $templateName)->first();
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

        $displayImageUrl = "/images/{$dir}/" . basename($localImageFile);

        $isTest = (bool) Config::get('whatsapp.testing.test_mode', false);

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

        try {
            $result = $this->metaService->sendTemplateMessage(
                $phone,
                $templateName,
                $language,
                $components,
                (string) $user->id
            );

            $sendRecord->update([
                'status' => 'sent',
                'sent_at' => now(),
                'provider_message_id' => $result['whatsapp_message_id'] ?? ($result['meta_message_id'] ?? null),
                'provider_response_json' => $result,
            ]);

            Log::info("[AwardRecognitionWhatsappService] Successfully dispatched {$templateName} to {$phone}", [
                'user_id' => $user->id,
                'track' => $track,
                'title' => $title,
                'send_id' => $sendRecord->id,
            ]);
        } catch (Throwable $e) {
            $sendRecord->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("[AwardRecognitionWhatsappService] Failed sending {$templateName} to {$phone}: " . $e->getMessage(), [
                'user_id' => $user->id,
                'track' => $track,
                'title' => $title,
                'send_id' => $sendRecord->id,
            ]);
        }

        return $sendRecord;
    }
}
