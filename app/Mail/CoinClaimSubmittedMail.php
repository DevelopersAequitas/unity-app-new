<?php

namespace App\Mail;

use App\Models\CoinClaimRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class CoinClaimSubmittedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public CoinClaimRequest $claim)
    {
    }

    public function build(): self
    {
        $activityName = $this->activityName();

        return $this->subject($this->subjectLine($activityName))
            ->markdown('emails.coin_claim_submitted')
            ->with([
                'userName' => $this->userName(),
                'claimId' => $this->claim->id ?: '-',
                'activityName' => $activityName,
                'coinsClaimed' => $this->coinsClaimed(),
                'submittedDate' => $this->submittedDate(),
                'status' => 'Pending Review',
            ]);
    }

    private function subjectLine(string $activityName): string
    {
        if ($activityName === '-') {
            return 'Coin Claim Received – Pending Review';
        }

        return sprintf('Coin Claim Received for %s – Pending Review', $activityName);
    }

    private function userName(): string
    {
        $fullName = trim(implode(' ', array_filter([
            $this->claim->user?->first_name,
            $this->claim->user?->last_name,
        ])));

        return $this->claim->user?->display_name
            ?: ($fullName !== '' ? $fullName : 'Peer');
    }

    private function activityName(): string
    {
        $activityCode = $this->claim->activity_code;

        if (! $activityCode) {
            return '-';
        }

        $configuredLabel = config("coin_claims.activities.{$activityCode}.label")
            ?? config("coins.claim_coin_labels.{$activityCode}");

        if ($configuredLabel) {
            return (string) $configuredLabel;
        }

        return Str::of($activityCode)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    private function coinsClaimed(): string
    {
        if ($this->claim->coins_awarded === null) {
            return '-';
        }

        return (string) $this->claim->coins_awarded;
    }

    private function submittedDate(): string
    {
        if (! $this->claim->created_at) {
            return '-';
        }

        return $this->claim->created_at->format('d M Y, h:i A');
    }
}
