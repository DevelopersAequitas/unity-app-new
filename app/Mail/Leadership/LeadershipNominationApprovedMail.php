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
        return new Envelope(
            subject: 'Approved! Your Candidate Voting Link is Live | Peers Global',
        );
    }

    public function content(): Content
    {
        $roleName = $this->nomination->applied_role_name
            ?? $this->nomination->campaign?->role?->name
            ?? 'District Executive Director';

        $candidateName = $this->nomination->candidate_name ?: $this->nomination->full_name;

        $logoUrl = 'https://peersunity.com/images/peersglobal-logo.png';
        if (file_exists(public_path('images/peersglobal-logo.png'))) {
            $logoUrl = asset('images/peersglobal-logo.png');
        }

        $creativeUrl = $this->creativeUrl ?: $this->nomination->creative_url;

        return new Content(
            view: 'emails.leadership.nomination_approved',
            with: [
                'nomination' => $this->nomination,
                'candidateName' => $candidateName,
                'roleName' => $roleName,
                'votingLink' => $this->votingLink,
                'candidateSlug' => $this->nomination->candidate_slug,
                'applicationNumber' => $this->nomination->application_number,
                'logoUrl' => $logoUrl,
                'creativeUrl' => $creativeUrl,
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
        if (! $path) {
            $candidatePath = public_path("storage/creatives/leadership/nomination_{$this->nomination->id}.png");
            if (file_exists($candidatePath)) {
                $path = $candidatePath;
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
