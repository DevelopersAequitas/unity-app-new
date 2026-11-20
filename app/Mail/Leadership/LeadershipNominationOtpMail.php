<?php

declare(strict_types=1);

namespace App\Mail\Leadership;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeadershipNominationOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $otp,
        public readonly string $verificationSubject = 'Nomination Verification',
        public readonly ?string $name = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->otp} is your Peers Global verification code",
        );
    }

    public function content(): Content
    {
        $logoUrl = 'https://peersunity.com/images/peersglobal-logo.png';
        if (file_exists(public_path('images/peersglobal-logo.png'))) {
            $logoUrl = asset('images/peersglobal-logo.png');
        }

        $recipientName = $this->name;
        if (empty($recipientName)) {
            $recipientName = 'Leader';
        }

        return new Content(
            view: 'emails.leadership.nomination_otp',
            with: [
                'otp' => $this->otp,
                'name' => $recipientName,
                'verificationSubject' => $this->verificationSubject,
                'expiresInMinutes' => 10,
                'logoUrl' => $logoUrl,
                'websiteUrl' => config('services.frontend_url', env('FRONTEND_URL', 'https://peersglobal.com')),
            ],
        );
    }
}
