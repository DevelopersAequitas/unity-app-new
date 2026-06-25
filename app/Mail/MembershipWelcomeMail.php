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
     * @var array<int, array{disk:string,path:string,name:string,mime?:string|null}>
     */
    public array $attachmentsConfig;

    public ?string $bannerUrl;

    /**
     * @param  array<int, array{disk:string,path:string,name:string,mime?:string|null}>  $attachmentsConfig
     */
    public function __construct(User $user, array $attachmentsConfig = [], ?string $bannerUrl = null)
    {
        $this->user = $user;
        $this->attachmentsConfig = $attachmentsConfig;
        $this->bannerUrl = $bannerUrl;
    }

    public function build()
    {
        $mail = $this->subject('Welcome to your Peers Unity Membership')
            ->view('emails.membership.membership_welcome')
            ->with([
                'user' => $this->user,
                'bannerUrl' => $this->bannerUrl,
            ]);

        foreach ($this->attachmentsConfig as $attachment) {
            $mail->attachFromStorageDisk($attachment['disk'], $attachment['path'], $attachment['name'], [
                'mime' => $attachment['mime'] ?? null,
            ]);
        }

        return $mail;
    }
}
