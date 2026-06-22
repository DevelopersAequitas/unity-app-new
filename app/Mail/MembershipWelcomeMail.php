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
            ->replyTo(config('peers.membership_welcome_reply_to_email'))
            ->subject('Welcome to Peers Global Unity')
            ->view('emails.membership.membership_welcome')
            ->with([
                'user' => $this->user,
            ]);

        $ccEmail = trim((string) config('peers.membership_welcome_cc_email', ''));
        if ($ccEmail !== '') {
            if (strcasecmp($ccEmail, (string) $this->user->email) === 0) {
                \Illuminate\Support\Facades\Log::info('Membership email CC skipped because recipient is same as To email.', [
                    'user_id' => $this->user->id,
                    'to' => $this->user->email,
                    'cc' => $ccEmail,
                ]);
            } else {
                $mail->cc($ccEmail);
            }
        }

        foreach ($this->attachmentsConfig as $attachment) {
            $mail->attach($attachment['path'], [
                'as' => $attachment['name'],
            ]);
        }

        return $mail;
    }
}
