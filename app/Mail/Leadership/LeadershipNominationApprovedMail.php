<?php

declare(strict_types=1);

namespace App\Mail\Leadership;

use App\Models\Leadership\LeadershipNomination;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class LeadershipNominationApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly LeadershipNomination $nomination,
        public readonly string $votingLink,
        public readonly ?string $creativeUrl = null,
        public readonly ?string $creativePath = null
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
            subject: "Your nomination is approved – voting is open | {$roleWithRegion}",
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

        // Voting window formatted in IST
        $campaign = $this->nomination->campaign;
        $startCarbon = $campaign?->voting_starts_at ? Carbon::parse($campaign->voting_starts_at) : Carbon::now();
        $endCarbon = $campaign?->voting_ends_at ? Carbon::parse($campaign->voting_ends_at) : Carbon::now()->addDays(14);

        $votingStart = $startCarbon->setTimezone('Asia/Kolkata')->format('d M Y, h:i A');
        $votingEnd = $endCarbon->setTimezone('Asia/Kolkata')->format('d M Y, h:i A');

        $logoUrl = 'https://peersunity.com/images/peersglobal-logo.png';
        if (file_exists(public_path('images/peersglobal-logo.png'))) {
            $logoUrl = asset('images/peersglobal-logo.png');
        }

        $path = $this->creativePath;
        if (! $path || ! file_exists($path)) {
            $candidatePath = public_path("storage/creatives/leadership/nomination_{$this->nomination->id}.png");
            if (file_exists($candidatePath)) {
                $path = $candidatePath;
            } else {
                $storageAppPath = storage_path("app/public/creatives/leadership/nomination_{$this->nomination->id}.png");
                if (file_exists($storageAppPath)) {
                    $path = $storageAppPath;
                }
            }
        }

        $creativeUrl = $this->creativeUrl ?: $this->nomination->creative_url;
        if ($creativeUrl) {
            $version = ($path && file_exists($path)) ? filemtime($path) : time();
            $creativeUrlWithVersion = $creativeUrl.(str_contains($creativeUrl, '?') ? '&' : '?').'v='.$version;
        } else {
            $creativeUrlWithVersion = null;
        }

        return new Content(
            view: 'emails.leadership.nomination_approved',
            with: [
                'nomination' => $this->nomination,
                'candidateName' => $candidateName,
                'roleName' => $roleName,
                'region' => $region,
                'roleWithRegion' => $roleWithRegion,
                'votingStart' => $votingStart,
                'votingEnd' => $votingEnd,
                'votingLink' => $this->votingLink,
                'nomineeLedgerUrl' => url("/leadership/nomination/{$this->nomination->id}/live-votes"),
                'candidateSlug' => $this->nomination->candidate_slug,
                'applicationNumber' => $this->nomination->application_number,
                'logoUrl' => $logoUrl,
                'creativeUrl' => $creativeUrlWithVersion ?: $creativeUrl,
                'creativePath' => $path,
                'websiteUrl' => config('services.frontend_url', env('FRONTEND_URL', 'https://peersglobal.com')),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        $path = $this->creativePath;
        if (! $path || ! file_exists($path)) {
            $candidatePath = public_path("storage/creatives/leadership/nomination_{$this->nomination->id}.png");
            if (file_exists($candidatePath)) {
                $path = $candidatePath;
            } else {
                $storageAppPath = storage_path("app/public/creatives/leadership/nomination_{$this->nomination->id}.png");
                if (file_exists($storageAppPath)) {
                    $path = $storageAppPath;
                }
            }
        }

        if ($path && file_exists($path)) {
            $attachments[] = Attachment::fromPath($path)
                ->as('Official-Leadership-Creative.png')
                ->withMime('image/png');
        }

        return $attachments;
    }
}
