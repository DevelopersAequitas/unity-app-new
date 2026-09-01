<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class UpcomingMembershipExpiryNotification extends Notification
{
    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $formattedDate = $this->user->membership_ends_at
            ? $this->user->membership_ends_at->format('d M Y')
            : '';

        return array_filter([
            'title' => 'Membership Expiring Soon',
            'body' => $formattedDate ? 'Your membership will expire in 3 days. Tap to renew now.' : 'Your membership will expire soon. Tap to renew now.',
            'navigation_screen' => '/profile',
            'type' => 'membership_expiry',
            'activity_type' => 'membership_expiry',
            'user_id' => (string) $this->user->id,
            'membership_ends_at' => $this->user->membership_ends_at ? $this->user->membership_ends_at->toIso8601String() : null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
