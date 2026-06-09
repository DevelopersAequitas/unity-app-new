<?php

namespace App\Mail;

use App\Models\Impact;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ImpactApprovedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    private const DEFAULT_LOGO_URL = 'https://unity.peersglobal.com/wp-content/uploads/2025/08/peersglobal_white-removebg-preview.png';

    public function __construct(public Impact $impact, public User $submitter)
    {
    }

    public function build(): self
    {
        $subject = 'Impact Approved Successfully';

        return $this->subject($subject)
            ->view('emails.impact_approved')
            ->with([
                'subject' => $subject,
                'userName' => $this->userName(),
                'actionTitle' => $this->actionTitle(),
                'impactDate' => $this->impactDate(),
                'story' => $this->fallbackString($this->impact->story_to_share),
                'lifeImpacted' => $this->impact->life_impacted === null ? '-' : (string) $this->impact->life_impacted,
                'totalLifeImpacted' => $this->submitter->life_impacted_count === null ? '-' : (string) $this->submitter->life_impacted_count,
                'logoUrl' => $this->logoUrl(),
            ]);
    }

    private function userName(): string
    {
        $fullName = trim(implode(' ', array_filter([
            $this->submitter->first_name,
            $this->submitter->last_name,
        ])));

        return $this->submitter->display_name
            ?: ($fullName !== '' ? $fullName : 'Peer');
    }

    private function actionTitle(): string
    {
        $action = $this->fallbackString($this->impact->action);

        if ($action === '-') {
            return $action;
        }

        return Str::of($action)
            ->replace(['_', '-'], ' ')
            ->squish()
            ->title()
            ->toString();
    }

    private function impactDate(): string
    {
        if (! $this->impact->impact_date) {
            return '-';
        }

        return $this->impact->impact_date->format('d M Y');
    }

    private function logoUrl(): ?string
    {
        return config('mail.peers_global_logo_url') ?: self::DEFAULT_LOGO_URL;
    }

    private function fallbackString(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '-' : $value;
    }
}
