<?php

declare(strict_types=1);

namespace App\Mail\Leadership;

use App\Models\Leadership\LeadershipNomination;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeadershipShortlistedJuryMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly LeadershipNomination $nomination,
        public readonly string $form2Link
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're Shortlisted! Complete Your Jury Form — Peers Global",
        );
    }

    public function content(): Content
    {
        $roleName = $this->nomination->applied_role_name
            ?? $this->nomination->campaign?->role?->name
            ?? 'Leadership Role';

        $candidateName = $this->nomination->candidate_name ?: $this->nomination->full_name;

        $logoUrl = 'https://peersunity.com/images/peersglobal-logo.png';
        if (file_exists(public_path('images/peersglobal-logo.png'))) {
            $logoUrl = asset('images/peersglobal-logo.png');
        }

        return new Content(
            view: 'emails.leadership.shortlisted_jury_form',
            with: [
                'nomination' => $this->nomination,
                'candidateName' => $candidateName,
                'roleName' => $roleName,
                'form2Link' => $this->form2Link,
                'email' => $this->nomination->email,
                'nominationId' => $this->nomination->id,
                'logoUrl' => $logoUrl,
                'websiteUrl' => config('services.frontend_url', env('FRONTEND_URL', 'https://peersglobal.com')),
            ],
        );
    }
}
