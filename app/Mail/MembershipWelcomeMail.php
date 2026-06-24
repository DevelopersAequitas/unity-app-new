<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MembershipWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{path:string,name:string}>  $attachmentsConfig
     * @param  array{peer_name:string,email:string,membership_status:string,membership_start_date:string,membership_expiry_date:string,payment_date:string,plan_code:string}  $details
     */
    public function __construct(
        public User $user,
        public array $attachmentsConfig = [],
        public array $details = [],
    ) {
    }

    private function logoUrl(): ?string
    {
        foreach ([
            'assets/images/peers-global-logo.png',
            'assets/images/logo.png',
            'images/peers-global-logo.png',
            'images/logo.png',
            'admin/images/logo.png',
            'admin/logo.png',
            'storage/peers-global-logo.png',
            'storage/logo.png',
        ] as $path) {
            if (file_exists(public_path($path))) {
                return asset($path);
            }
        }

        $configuredLogoUrl = trim((string) config('membership_welcome.logo_url', ''));

        return $configuredLogoUrl !== '' ? $configuredLogoUrl : null;
    }

    public function build()
    {
        $mail = $this->subject('Welcome to Peers Global Unity')
        $mail = $this->subject('Welcome to your Peers Unity Membership')
            ->view('emails.membership.membership_welcome')
            ->with([
                'user' => $this->user,
                'details' => $this->details,
                'peerName' => $this->details['peer_name'] ?? ($this->user->display_name
                    ?: trim((string) (($this->user->first_name ?? '') . ' ' . ($this->user->last_name ?? '')))
                    ?: ($this->user->name ?: 'Peer')),
                'logoUrl' => $this->logoUrl(),
            ]);

        foreach ($this->attachmentsConfig as $attachment) {
            $mail->attach($attachment['path'], [
                'as' => $attachment['name'],
            ]);
        }

        return $mail;
    }
}
