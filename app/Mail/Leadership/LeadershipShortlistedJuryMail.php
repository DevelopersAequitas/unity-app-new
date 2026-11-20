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
use Illuminate\Support\Carbon;

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
        $roleName = $this->nomination->applied_role_name
            ?? $this->nomination->campaign?->role?->name
            ?? 'District Executive Director';

        $region = $this->nomination->scope_name
            ?? $this->nomination->scope?->name
            ?? 'Ahmedabad';

        $roleWithRegion = $region ? "{$roleName} – {$region}" : $roleName;

        return new Envelope(
            subject: "You are shortlisted – complete your Jury Form | {$roleWithRegion}",
        );
    }

    public function content(): Content
    {
        $roleName = $this->nomination->applied_role_name
            ?? $this->nomination->campaign?->role?->name
            ?? 'District Executive Director';

        $region = $this->nomination->scope_name
            ?? $this->nomination->scope?->name
            ?? 'Ahmedabad';

        $roleWithRegion = $region ? "{$roleName} – {$region}" : $roleName;
        $candidateName = $this->nomination->candidate_name ?: $this->nomination->full_name ?: 'Candidate';

        // 7 days deadline formatted in IST
        $deadline = Carbon::now()->addDays(7)->setTimezone('Asia/Kolkata')->format('d M Y, h:i A');

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
                'region' => $region,
                'roleWithRegion' => $roleWithRegion,
                'deadline' => $deadline,
                'secureLink' => $this->form2Link,
                'email' => $this->nomination->email,
                'applicationNumber' => $this->nomination->application_number,
                'logoUrl' => $logoUrl,
                'websiteUrl' => config('services.frontend_url', env('FRONTEND_URL', 'https://peersglobal.com')),
            ],
        );
    }
}
