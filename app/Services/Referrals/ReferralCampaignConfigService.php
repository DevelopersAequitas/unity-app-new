<?php

declare(strict_types=1);

namespace App\Services\Referrals;

use App\Models\ReferralCampaignConfig;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReferralCampaignConfigService
{
    /**
     * Retrieve current referral campaign configuration.
     *
     * @return array<string, mixed>
     */
    public function getCampaignConfig(): array
    {
        try {
            if (Schema::hasTable('referral_campaign_configs')) {
                /** @var ReferralCampaignConfig|null $dbConfig */
                $dbConfig = ReferralCampaignConfig::query()
                    ->where('is_active', true)
                    ->latest('updated_at')
                    ->first();

                if ($dbConfig !== null) {
                    return [
                        'showFloatingBadge' => (bool) $dbConfig->show_floating_badge,
                        'autoOpenBadgeScreen' => (bool) $dbConfig->auto_open_badge_screen,
                        'floatingBadgeDelaySeconds' => (int) $dbConfig->floating_badge_delay_seconds,
                        'autoOpenDelaySeconds' => (int) $dbConfig->auto_open_delay_seconds,
                        'rewardTitle' => (string) $dbConfig->reward_title,
                        'rewardSubtitle' => (string) $dbConfig->reward_subtitle,
                        'rewardImageUrl' => $dbConfig->reward_image_url !== null ? (string) $dbConfig->reward_image_url : null,
                        'showSendInviteButton' => (bool) $dbConfig->show_send_invite_button,
                    ];
                }
            }
        } catch (Throwable) {
            // Fallback to configuration default if database is unavailable or table does not exist
        }

        return $this->getDefaultConfig();
    }

    /**
     * Get fallback default campaign configuration.
     *
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array
    {
        return [
            'showFloatingBadge' => (bool) config('referrals.campaign.show_floating_badge', true),
            'autoOpenBadgeScreen' => (bool) config('referrals.campaign.auto_open_badge_screen', true),
            'floatingBadgeDelaySeconds' => (int) config('referrals.campaign.floating_badge_delay_seconds', 10),
            'autoOpenDelaySeconds' => (int) config('referrals.campaign.auto_open_delay_seconds', 30),
            'rewardTitle' => (string) config('referrals.campaign.reward_title', 'Refer 10 Friends & Win The Book'),
            'rewardSubtitle' => (string) config('referrals.campaign.reward_subtitle', "Get 'The 5 Levels of Leadership' physical book for your next level of leadership!"),
            'rewardImageUrl' => config('referrals.campaign.reward_image_url'),
            'showSendInviteButton' => (bool) config('referrals.campaign.show_send_invite_button', true),
        ];
    }
}
