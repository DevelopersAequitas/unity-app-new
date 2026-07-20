<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MembershipUpgradedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?string $previousStatus,
        public ?string $newStatus,
        public ?string $previousExpiryDate,
        public ?string $newExpiryDate,
        public ?string $changeType, // 'increased', 'decreased', 'no_change'
        public ?string $remark = null,
        public ?string $updatedBy = null
    ) {}

    public function build()
    {
        return $this->subject('Your Peers Global Membership Has Been Updated')
            ->from(config('mail.membership_from.address', 'support@peersglobal.com'), config('mail.membership_from.name', 'Peers Global Unity'))
            ->view('emails.membership.upgraded')
            ->with([
                'user' => $this->user,
                'previousStatus' => $this->previousStatus,
                'newStatus' => $this->newStatus,
                'previousExpiryDate' => $this->previousExpiryDate,
                'newExpiryDate' => $this->newExpiryDate,
                'changeType' => $this->changeType,
                'remark' => $this->remark,
                'updatedBy' => $this->updatedBy,
            ]);
    }
}
