<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MembershipStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public ?string $previousStatus, public ?string $newStatus, public ?string $updatedBy = null, public bool $manual = false) {}

    public function build()
    {
        return $this->subject($this->manual ? 'Your Peers Global Membership Information' : 'Your Peers Global Membership Status Updated')
            ->from(config('mail.membership_from.address', 'pravin@peersunity.com'), config('mail.membership_from.name', 'Peers Global Unity'))
            ->view('emails.membership.status_changed')
            ->with(['user' => $this->user, 'previousStatus' => $this->previousStatus, 'newStatus' => $this->newStatus, 'updatedBy' => $this->updatedBy, 'manual' => $this->manual]);
    }
}
