<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MembershipWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    /**
     * @var array<int, array{path:string,name:string}>
     */
    public array $attachmentsConfig;

    /**
     * @param  array<int, array{path:string,name:string}>  $attachmentsConfig
     */
    public function __construct(User $user, array $attachmentsConfig = [])
    {
        $this->user = $user;
        $this->attachmentsConfig = $attachmentsConfig;
    }

    public function build()
    {
        $mail = $this->from(
                config('peers.membership_welcome_from_email'),
                config('peers.membership_welcome_from_name')
            )
            ->subject('Welcome to Peers Global Unity')
            ->view('emails.membership.membership_welcome')
            ->with([
                'user' => $this->user,
            ]);

        $ccEmail = trim((string) config('peers.membership_welcome_cc_email', ''));
        if ($ccEmail !== '') {
            $mail->cc($ccEmail);
        }

        foreach ($this->attachmentsConfig as $attachment) {
            $mail->attach($attachment['path'], [
                'as' => $attachment['name'],
            ]);
        }

        return $mail;
    }
}
