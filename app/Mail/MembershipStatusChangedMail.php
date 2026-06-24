<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MembershipStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    private const ATTACHMENTS = [
        'mail-attachments/dummy-pdf_2.pdf',
        'mail-attachments/dummy-pdf_2_1.pdf',
    ];

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

        foreach (self::ATTACHMENTS as $file) {
            $path = Storage::disk('public')->path($file);

            if (! Storage::disk('public')->exists($file)) {
                Log::warning('Membership email attachment missing', [
                    'email_type' => 'membership_status',
                    'user_id' => (string) $this->user->id,
                    'file' => $file,
                    'full_path' => $path,
                ]);

                continue;
            }

            $mail->attach($path, [
                'as' => basename($file),
                'mime' => 'application/pdf',
            ]);
        }

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
