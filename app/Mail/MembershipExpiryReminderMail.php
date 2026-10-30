<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MembershipExpiryReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;

    public string $formattedExpiryDate;

    public string $support_email;

    public ?int $reminderDay;

    public function __construct(User $user, ?int $reminderDay = null)
    {
        $this->user = $user;
        $this->reminderDay = $reminderDay;
        $this->formattedExpiryDate = $user->membership_ends_at
            ? $user->membership_ends_at->format('d M Y')
            : '';
        $this->support_email = config('mail.membership_from.address', 'support@peersglobal.com');
    }

    public function build()
    {
        $subject = match ($this->reminderDay) {
            3 => 'Peers Global Unity: Membership Expired – Day 3 Reminder',
            7 => 'Peers Global Unity: Membership Expired – Final Reminder (Day 7)',
            default => 'Membership Expired – Action Required',
        };

        return $this->subject($subject)
            ->from(config('mail.membership_from.address', 'support@peersglobal.com'), config('mail.membership_from.name', 'Peers Global Unity'))
            ->view('emails.membership.expiry_reminder')
            ->with([
                'user' => $this->user,
                'membership_ends_at' => $this->formattedExpiryDate,
                'support_email' => $this->support_email,
                'reminder_day' => $this->reminderDay,
            ]);
    }
}
