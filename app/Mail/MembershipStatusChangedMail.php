<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class MembershipStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $membershipStatus,
        public ?Carbon $membershipEndsAt,
    ) {
    }

    public function build()
    {
        $mail = $this->from('pravin@peersunity.com', 'Peers Global / Unity Peer')
            ->subject('Your Unity Peer Membership Status Updated')
            ->view('emails.membership.status-changed')
            ->with([
                'userName' => $this->memberName(),
                'membershipStatus' => $this->statusLabel(),
                'membershipExpiryDate' => $this->membershipEndsAt?->format('d M Y') ?? 'Not available',
                'currentYear' => now()->year,
            ]);

        return $mail;
    }

    private function memberName(): string
    {
        return trim((string) ($this->user->display_name ?? ''))
            ?: trim(trim((string) ($this->user->first_name ?? '')) . ' ' . trim((string) ($this->user->last_name ?? '')))
            ?: 'Unity Peer';
    }

    private function statusLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->membershipStatus));
    }
}
