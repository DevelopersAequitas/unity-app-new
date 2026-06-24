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
     * @var array<int, array{file_id:string,disk:string,path:string,name:string,mime?:string|null,resolved_path?:string|null}>
     */
    public array $attachmentsConfig;

    /**
     * @param  array<int, array{file_id:string,disk:string,path:string,name:string,mime?:string|null,resolved_path?:string|null}>  $attachmentsConfig
     */
    public function __construct(User $user, array $attachmentsConfig = [])
    {
        $this->user = $user;
        $this->attachmentsConfig = $attachmentsConfig;
    }

    private function senderAddress(): string
    {
        return (string) config('mail.from.address');
    }

    private function senderName(): string
    {
        return (string) config('mail.from.name');
    }

    private function applyMembershipHeaders(Mailable $mail, string $emailType): Mailable
    {
        return $mail->replyTo($this->senderAddress(), $this->senderName())
            ->withSymfonyMessage(function ($message) use ($emailType): void {
                $headers = $message->getHeaders();
                $headers->addTextHeader('X-PeersGlobal-Email-Type', $emailType);
                $headers->addTextHeader('X-Auto-Response-Suppress', 'All');
                $headers->addTextHeader('Precedence', 'bulk');
            });
    }

    public function build()
    {
        $mail = $this->applyMembershipHeaders(
            $this->from($this->senderAddress(), $this->senderName())
                ->subject('Welcome to your Peers Unity Membership')
                ->view('emails.membership.membership_welcome')
                ->text('emails.membership.text.welcome'),
            'membership_welcome'
        )
            ->with([
                'user' => $this->user,
            ]);

        foreach ($this->attachmentsConfig as $attachment) {
            $options = ['as' => $attachment['name']];

            if (! empty($attachment['mime'])) {
                $options['mime'] = $attachment['mime'];
            }

            $mail->attachFromStorageDisk($attachment['disk'], $attachment['path'], $attachment['name'], $options);
        }

        return $mail;
    }
}
