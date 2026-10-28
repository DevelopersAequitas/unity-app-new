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
                    $isBookActive = (bool) $dbConfig->is_active;
                    $isReferralActive = (bool) $dbConfig->show_floating_badge;
                    $showInviteButton = (bool) $dbConfig->show_send_invite_button;

                    return [
                        // Referral Popup & Invite Flags
                        'showFloatingBadge' => $isReferralActive,
                        'show_floating_badge' => $isReferralActive,
                        'autoOpenBadgeScreen' => $isReferralActive && (bool) $dbConfig->auto_open_badge_screen,
                        'auto_open_badge_screen' => $isReferralActive && (bool) $dbConfig->auto_open_badge_screen,
                        'showSendInviteButton' => $showInviteButton,
                        'show_send_invite_button' => $showInviteButton,
                        'isReferralActive' => $isReferralActive,
                        'is_referral_active' => $isReferralActive,

                        // Book Reward Campaign Flags
                        'isActive' => $isBookActive,
                        'is_active' => $isBookActive,
                        'isCampaignActive' => $isBookActive,
                        'showBookCampaign' => $isBookActive,
                        'show_book_campaign' => $isBookActive,
                        'bookCampaignEnabled' => $isBookActive,
                        'book_campaign_enabled' => $isBookActive,
                        'hasBookCampaign' => $isBookActive,
                        'has_book_campaign' => $isBookActive,

                        // Book Reward Content
                        'rewardTitle' => $isBookActive
                            ? (string) ($dbConfig->reward_title ?: 'Empower Your Leadership Journey: Refer 10 Peers & Receive The Leadership Book')
                            : null,
                        'rewardSubtitle' => $isBookActive
                            ? (string) ($dbConfig->reward_subtitle ?: "As part of the Peers Global LSR (Learning, Sales, Resources) initiative, grow our trusted network by inviting 10 fellow business owners. Reach this milestone and receive John C. Maxwell's bestselling hardcover book delivered to your doorstep.")
                            : null,
                        'rewardImageUrl' => $isBookActive
                            ? ($dbConfig->reward_image_url !== null ? (string) $dbConfig->reward_image_url : null)
                            : null,

                        'floatingBadgeDelaySeconds' => (int) $dbConfig->floating_badge_delay_seconds,
                        'autoOpenDelaySeconds' => (int) $dbConfig->auto_open_delay_seconds,
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
            'show_floating_badge' => (bool) config('referrals.campaign.show_floating_badge', true),
            'autoOpenBadgeScreen' => (bool) config('referrals.campaign.auto_open_badge_screen', true),
            'auto_open_badge_screen' => (bool) config('referrals.campaign.auto_open_badge_screen', true),
            'showSendInviteButton' => true,
            'show_send_invite_button' => true,
            'isReferralActive' => true,
            'is_referral_active' => true,

            'isActive' => true,
            'is_active' => true,
            'isCampaignActive' => true,
            'showBookCampaign' => true,
            'show_book_campaign' => true,
            'bookCampaignEnabled' => true,
            'book_campaign_enabled' => true,
            'hasBookCampaign' => true,
            'has_book_campaign' => true,

            'rewardTitle' => (string) config('referrals.campaign.reward_title', 'Empower Your Leadership Journey: Refer 10 Peers & Receive The Leadership Book'),
            'rewardSubtitle' => (string) config('referrals.campaign.reward_subtitle', "As part of the Peers Global LSR (Learning, Sales, Resources) initiative, grow our trusted network by inviting 10 fellow business owners. Reach this milestone and receive John C. Maxwell's bestselling hardcover book delivered to your doorstep."),
            'rewardImageUrl' => config('referrals.campaign.reward_image_url'),

            'floatingBadgeDelaySeconds' => (int) config('referrals.campaign.floating_badge_delay_seconds', 10),
            'autoOpenDelaySeconds' => (int) config('referrals.campaign.auto_open_delay_seconds', 30),
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
            $config = ReferralCampaignConfig::create([
                'show_floating_badge' => true,
                'auto_open_badge_screen' => true,
                'floating_badge_delay_seconds' => 10,
                'auto_open_delay_seconds' => 30,
                'reward_title' => 'Empower Your Leadership Journey: Refer 10 Peers & Receive The Leadership Book',
                'reward_subtitle' => "As part of the Peers Global LSR (Learning, Sales, Resources) initiative, grow our trusted network by inviting 10 fellow business owners. Reach this milestone and receive John C. Maxwell's bestselling hardcover book delivered to your doorstep.",
                'reward_image_url' => null,
                'show_send_invite_button' => true,
                'is_active' => true,
            ]);
        }

        return $config;
    }

    /**
     * Toggle the Book Reward Campaign.
     */
    public function toggleBookCampaign(?bool $state = null): ReferralCampaignConfig
    {
        $config = $this->getOrCreateCurrentConfig();
        $newState = $state !== null ? $state : ! (bool) $config->is_active;

        $config->update([
            'is_active' => $newState,
        ]);

        return $config->fresh();
    }

    /**
     * Toggle the Referral Invite Campaign / Popup.
     */
    public function toggleReferralCampaign(?bool $state = null): ReferralCampaignConfig
    {
        $config = $this->getOrCreateCurrentConfig();
        $newState = $state !== null ? $state : ! (bool) $config->show_floating_badge;

        $config->update([
            'show_floating_badge' => $newState,
            'show_send_invite_button' => $newState,
            'auto_open_badge_screen' => $newState,
        ]);

        return $config->fresh();
    }

    /**
     * Legacy toggle for backward compatibility.
     */
    public function toggleActive(?bool $state = null): ReferralCampaignConfig
    {
        return $this->toggleBookCampaign($state);
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
