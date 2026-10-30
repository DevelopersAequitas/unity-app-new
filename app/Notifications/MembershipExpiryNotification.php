<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

class MembershipExpiryNotification extends Notification
{
    public User $user;

    public ?int $reminderDay;

    public function __construct(User $user, ?int $reminderDay = null)
    {
        $this->user = $user;
        $this->reminderDay = $reminderDay;
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

        $title = match ($this->reminderDay) {
            3 => 'Membership Expired – Day 3 Reminder',
            7 => 'Membership Expired – Final Reminder',
            default => 'Membership Expiring Soon',
        };

        $body = match ($this->reminderDay) {
            7 => $formattedDate ? "Your membership expired on {$formattedDate}. Rejoin now before account deactivation." : 'Your membership has expired. Rejoin now before account deactivation.',
            3 => $formattedDate ? "Your membership expired on {$formattedDate}. Tap to renew now." : 'Your membership has expired. Tap to renew now.',
            default => $formattedDate ? "Your membership will expire on {$formattedDate}. Tap to renew now." : 'Your membership will expire soon. Tap to renew now.',
        };

        return array_filter([
            'title' => $title,
            'body' => $body,
            'navigation_screen' => '/profile',
            'type' => 'membership_expiry',
            'activity_type' => 'membership_expiry',
            'user_id' => (string) $this->user->id,
            'membership_ends_at' => $this->user->membership_ends_at ? $this->user->membership_ends_at->toIso8601String() : null,
            'reminder_day' => $this->reminderDay,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
