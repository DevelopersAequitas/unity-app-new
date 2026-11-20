<?php

declare(strict_types=1);

namespace App\Mail\Leadership;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NominationLedgerOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $otp,
        public readonly string $verificationSubject = 'Live Voter Ledger Access Verification',
        public readonly ?string $name = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->otp} is your Peers Global live voter ledger access code",
        );
    }

    public function content(): Content
    {
        $logoUrl = 'https://peersunity.com/images/peersglobal-logo.png';
        if (file_exists(public_path('images/peersglobal-logo.png'))) {
            $logoUrl = asset('images/peersglobal-logo.png');
        }

        $recipientName = $this->name ?: 'Candidate';

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
