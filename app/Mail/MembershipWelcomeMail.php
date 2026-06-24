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
     * @var array<int, array{path?:string,disk?:string,storage_path?:string,name:string,mime?:string}>
     */
    public array $attachmentsConfig;

    /**
     * @param  array<int, array{path?:string,disk?:string,storage_path?:string,name:string,mime?:string}>  $attachmentsConfig
     */
    public function __construct(User $user, array $attachmentsConfig = [])
    {
        $this->user = $user;
        $this->attachmentsConfig = $attachmentsConfig;
    }

    public function build()
    {
        $mail = $this->from('pravin@peersunity.com', config('mail.from.name'))
            ->subject('Welcome to your Peers Unity Membership')
            ->view('emails.membership.membership_welcome')
            ->with([
                'user' => $this->user,
            ]);

        foreach ($this->attachmentsConfig as $attachment) {
            if (! empty($attachment['disk']) && ! empty($attachment['storage_path'])) {
                $options = [];

                if (! empty($attachment['mime'])) {
                    $options['mime'] = $attachment['mime'];
                }

                $mail->attachFromStorageDisk($attachment['disk'], $attachment['storage_path'], $attachment['name'], $options);

                continue;
            }

            if (! empty($attachment['path'])) {
                $mail->attach($attachment['path'], [
                    'as' => $attachment['name'],
                ]);
            }
        }

        return $mail;
    }
}
