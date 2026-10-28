<?php

declare(strict_types=1);

namespace App\Services\Referrals;

use App\Models\ReferralCampaignConfig;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReferralCampaignConfigService
{
    /**
     * Retrieve current referral campaign configuration formatted for mobile consumption.
     *
     * @return array<string, mixed>
     */
    public function getCampaignConfig(): array
    {
        try {
            if (Schema::hasTable('referral_campaign_configs')) {
                /** @var ReferralCampaignConfig|null $dbConfig */
                $dbConfig = ReferralCampaignConfig::query()
                    ->latest('updated_at')
                    ->first();

                if ($dbConfig !== null) {
                    $isActive = (bool) $dbConfig->is_active;
                    $showFloatingBadge = $isActive && (bool) $dbConfig->show_floating_badge;
                    $autoOpenBadgeScreen = $isActive && (bool) $dbConfig->auto_open_badge_screen;
                    $showSendInviteButton = $isActive && (bool) $dbConfig->show_send_invite_button;

                    return [
                        'isActive' => $isActive,
                        'is_active' => $isActive,
                        'showFloatingBadge' => $showFloatingBadge,
                        'show_floating_badge' => $showFloatingBadge,
                        'autoOpenBadgeScreen' => $autoOpenBadgeScreen,
                        'auto_open_badge_screen' => $autoOpenBadgeScreen,
                        'floatingBadgeDelaySeconds' => (int) $dbConfig->floating_badge_delay_seconds,
                        'autoOpenDelaySeconds' => (int) $dbConfig->auto_open_delay_seconds,
                        'rewardTitle' => (string) ($dbConfig->reward_title ?? 'Empower Your Leadership Journey: Refer 10 Peers & Receive The Leadership Book'),
                        'rewardSubtitle' => (string) ($dbConfig->reward_subtitle ?? "As part of the Peers Global LSR (Learning, Sales, Resources) initiative, grow our trusted network by inviting 10 fellow business owners. Reach this milestone and receive John C. Maxwell's bestselling hardcover book delivered to your doorstep."),
                        'rewardImageUrl' => $dbConfig->reward_image_url !== null ? (string) $dbConfig->reward_image_url : null,
                        'showSendInviteButton' => $showSendInviteButton,
                        'show_send_invite_button' => $showSendInviteButton,
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
        $showFloatingBadge = (bool) config('referrals.campaign.show_floating_badge', true);
        $autoOpenBadgeScreen = (bool) config('referrals.campaign.auto_open_badge_screen', true);
        $showSendInviteButton = (bool) config('referrals.campaign.show_send_invite_button', true);

        return [
            'isActive' => true,
            'is_active' => true,
            'showFloatingBadge' => $showFloatingBadge,
            'show_floating_badge' => $showFloatingBadge,
            'autoOpenBadgeScreen' => $autoOpenBadgeScreen,
            'auto_open_badge_screen' => $autoOpenBadgeScreen,
            'floatingBadgeDelaySeconds' => (int) config('referrals.campaign.floating_badge_delay_seconds', 10),
            'autoOpenDelaySeconds' => (int) config('referrals.campaign.auto_open_delay_seconds', 30),
            'rewardTitle' => (string) config('referrals.campaign.reward_title', 'Empower Your Leadership Journey: Refer 10 Peers & Receive The Leadership Book'),
            'rewardSubtitle' => (string) config('referrals.campaign.reward_subtitle', "As part of the Peers Global LSR (Learning, Sales, Resources) initiative, grow our trusted network by inviting 10 fellow business owners. Reach this milestone and receive John C. Maxwell's bestselling hardcover book delivered to your doorstep."),
            'rewardImageUrl' => config('referrals.campaign.reward_image_url'),
            'showSendInviteButton' => $showSendInviteButton,
            'show_send_invite_button' => $showSendInviteButton,
        ];
    }

    /**
     * Get the active or newest configuration model, seeding default if missing.
     */
    public function getOrCreateCurrentConfig(): ReferralCampaignConfig
    {
        /** @var ReferralCampaignConfig|null $config */
        $config = ReferralCampaignConfig::query()->latest('updated_at')->first();

        if ($config === null) {
            $defaults = $this->getDefaultConfig();
            $config = ReferralCampaignConfig::create([
                'show_floating_badge' => $defaults['showFloatingBadge'],
                'auto_open_badge_screen' => $defaults['autoOpenBadgeScreen'],
                'floating_badge_delay_seconds' => $defaults['floatingBadgeDelaySeconds'],
                'auto_open_delay_seconds' => $defaults['autoOpenDelaySeconds'],
                'reward_title' => $defaults['rewardTitle'],
                'reward_subtitle' => $defaults['rewardSubtitle'],
                'reward_image_url' => $defaults['rewardImageUrl'],
                'show_send_invite_button' => $defaults['showSendInviteButton'],
                'is_active' => true,
            ]);
        }

        return $config;
    }

    /**
     * Toggle the active state of the campaign.
     */
    public function toggleActive(?bool $state = null): ReferralCampaignConfig
    {
        $config = $this->getOrCreateCurrentConfig();
        $newState = $state !== null ? $state : ! (bool) $config->is_active;

        $config->update([
            'is_active' => $newState,
            'show_floating_badge' => $newState,
        ]);

        return $config->fresh();
    }

    /**
     * Update configuration attributes.
     *
     * @param array<string, mixed> $data
     */
    public function updateConfig(array $data): ReferralCampaignConfig
    {
        $config = $this->getOrCreateCurrentConfig();
        $config->update($data);

        return $config->fresh();
    }
}
