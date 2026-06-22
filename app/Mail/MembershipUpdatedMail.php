<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MembershipUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{old_status:string,new_status:string,old_expiry:string,new_expiry:string,updated_at:string,admin_note:?string,notification_body:string}  $details
     * @param  array<int, array{path:string,name:string}>  $attachmentsConfig
     */
    public function __construct(
        public User $user,
        public array $details,
        public array $attachmentsConfig = [],
    ) {
    }

    public function build()
    {
        $mail = $this->subject('Your Peers Membership Has Been Updated')
            ->view('emails.membership.membership_updated')
            ->with([
                'user' => $this->user,
                'details' => $this->details,
                'peerName' => $this->user->display_name
                    ?: trim((string) (($this->user->first_name ?? '') . ' ' . ($this->user->last_name ?? '')))
                    ?: ($this->user->name ?: 'Peer'),
            ]);

        foreach ($this->attachmentsConfig as $attachment) {
            $mail->attach($attachment['path'], [
                'as' => $attachment['name'],
            ]);
        }

        return $mail;
    }
}
