<?php

namespace App\Notifications;

use App\Models\CircleMember;
use Illuminate\Notifications\Notification;

class CircleMembershipExpiryNotification extends Notification
{
    public CircleMember $circleMember;

    public function __construct(CircleMember $circleMember)
    {
        $this->circleMember = $circleMember;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return array_filter([
            'title' => 'Membership Expiring Soon',
            'body' => 'Your membership will expire soon. Tap to renew now.',
            'navigation_screen' => '/profile',
            'type' => 'membership_expiry',
            'activity_type' => 'membership_expiry',
            'user_id' => (string) ($notifiable->id ?? $this->circleMember->user_id ?? ''),
            'expires_at' => $this->circleMember->expires_at ? $this->circleMember->expires_at->toIso8601String() : null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
