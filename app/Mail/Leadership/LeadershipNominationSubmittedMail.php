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

class LeadershipNominationSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly LeadershipNomination $nomination
    ) {}

    public function envelope(): Envelope
    {
        $roleName = $this->nomination->applied_role_name ?? 'Leadership Role';
        $appNo = $this->nomination->application_number;

        return new Envelope(
            subject: "Nomination Application Received [{$appNo}] - {$roleName} | Peers Global Unity",
        );
    }

    public function content(): Content
    {
        $roleName = $this->nomination->applied_role_name
            ?? $this->nomination->campaign?->role?->name
            ?? 'Leadership Role';

        $jurisdiction = $this->nomination->scope_name;
        if (empty($jurisdiction)) {
            $jurisdiction = $this->nomination->scope?->name ?? 'General / All Circles';
        }

        $logoUrl = 'https://peersunity.com/images/peersglobal-logo.png';
        if (file_exists(public_path('images/peersglobal-logo.png'))) {
            $logoUrl = asset('images/peersglobal-logo.png');
        }

        return new Content(
            view: 'emails.leadership.nomination_submitted',
            with: [
                'nomination' => $this->nomination,
                'candidateName' => $this->nomination->candidate_name ?: $this->nomination->full_name,
                'roleName' => $roleName,
                'campaignName' => $this->nomination->campaign_name ?? $this->nomination->campaign?->name ?? 'Leadership Selection',
                'jurisdiction' => $jurisdiction,
                'applicationNumber' => $this->nomination->application_number,
                'submittedAt' => $this->nomination->submitted_at?->format('d/m/Y') ?? now()->format('d/m/Y'),
                'logoUrl' => $logoUrl,
                'websiteUrl' => config('services.frontend_url', env('FRONTEND_URL', 'https://peersglobal.com')),
            ],
        );
    }
}
