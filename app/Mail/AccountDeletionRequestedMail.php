<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountDeletionRequestedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public User $user) {}

    public function build(): self
    {
        return $this->subject('Account Deletion Request Received')
            ->view('emails.account_deletion_requested');
    }
}
