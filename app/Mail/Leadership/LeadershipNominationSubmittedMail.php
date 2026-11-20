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
        $roleName = $this->nomination->applied_role_name
            ?? $this->nomination->campaign?->role?->name
            ?? 'Leadership Role';
        $appNo = $this->nomination->application_number;

        return new Envelope(
            subject: "Nomination received – {$appNo} | {$roleName}",
        );
    }

    public function content(): Content
    {
        $roleName = $this->nomination->applied_role_name
            ?? $this->nomination->campaign?->role?->name
            ?? 'Leadership Role';

        $region = $this->nomination->scope_name;
        if (empty($region)) {
            $region = $this->nomination->scope?->name ?? ($this->nomination->city ?? 'All Circles');
        }

        $logoUrl = 'https://peersunity.com/images/peersglobal-logo.png';
        if (file_exists(public_path('images/peersglobal-logo.png'))) {
            $logoUrl = asset('images/peersglobal-logo.png');
        }

        $submittedDateTime = $this->nomination->submitted_at
            ? $this->nomination->submitted_at->timezone('Asia/Kolkata')->format('d M Y, h:i A')
            : now()->timezone('Asia/Kolkata')->format('d M Y, h:i A');

        $statusLink = config('services.frontend_url', env('FRONTEND_URL', 'https://peersglobal.com')).'/leadership';

        return new Content(
            view: 'emails.leadership.nomination_submitted',
            with: [
                'nomination' => $this->nomination,
                'fullName' => $this->nomination->candidate_name ?: $this->nomination->full_name,
                'roleName' => $roleName,
                'region' => $region,
                'applicationNumber' => $this->nomination->application_number,
                'submittedDateTime' => $submittedDateTime,
                'email' => $this->nomination->email,
                'statusLink' => $statusLink,
                'logoUrl' => $logoUrl,
                'websiteUrl' => config('services.frontend_url', env('FRONTEND_URL', 'https://peersglobal.com')),
            ],
        );
    }
}
