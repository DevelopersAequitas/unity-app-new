<?php

namespace App\Notifications\BrandPartners;

use App\Models\BrandPartner;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOfferAddedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly BrandPartner $partner
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'system';
    }

    public function toDatabase(object $notifiable): array
    {
        return array_filter([
            'title' => 'Exclusive Brand Offer!',
            'body' => $this->partner->offer_title ? $this->partner->offer_title.' Grab before expiry.' : 'Get exclusive discounts with our new Brand Partner offer.',
            'navigation_screen' => '/brand-partner-details',
            'type' => 'brand_partner_offer',
            'partner_id' => (string) $this->partner->id,
            'brand_partner_id' => (string) $this->partner->id,
            'partner_name' => $this->partner->name,
            'offer_title' => $this->partner->offer_title,
            'coupon_code' => $this->partner->coupon_code,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
