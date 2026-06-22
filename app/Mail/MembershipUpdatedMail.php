<?php

namespace App\Mail;

use App\Models\User;
use App\Support\MembershipDisplay;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MembershipUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?string $oldMembershipStatus,
        public ?string $newMembershipStatus,
        public mixed $oldMembershipExpiry,
        public mixed $newMembershipExpiry,
        public mixed $updatedAt,
    ) {
    }

    public function build()
    {
        $mail = $this->from(config('peers.membership_update_from_email'), config('peers.membership_update_from_name'))
            ->replyTo(config('peers.membership_update_reply_to_email'))
            ->subject('Your Peers Global Membership Has Been Updated')
            ->view('emails.membership.updated')
            ->with([
                'user' => $this->user,
                'peerName' => $this->peerName(),
                'oldMembershipStatus' => MembershipDisplay::statusLabel($this->oldMembershipStatus),
                'newMembershipStatus' => MembershipDisplay::statusLabel($this->newMembershipStatus),
                'oldMembershipExpiry' => MembershipDisplay::dateLabel($this->oldMembershipExpiry),
                'newMembershipExpiry' => MembershipDisplay::dateLabel($this->newMembershipExpiry),
                'updatedAtLabel' => MembershipDisplay::dateLabel($this->updatedAt),
            ]);

        $ccEmail = trim((string) config('peers.membership_update_cc_email', ''));
        if ($ccEmail !== '') {
            if (strcasecmp($ccEmail, (string) $this->user->email) === 0) {
                Log::info('Membership email CC skipped because recipient is same as To email.', [
                    'user_id' => $this->user->id,
                    'to' => $this->user->email,
                    'cc' => $ccEmail,
                ]);
            } else {
                $mail->cc($ccEmail);
            }
        }

        $attachmentPath = trim((string) config('peers.membership_update_attachment_path', ''));
        if ($attachmentPath !== '') {
            $resolvedPath = str_starts_with($attachmentPath, '/') ? $attachmentPath : base_path($attachmentPath);
            if (is_file($resolvedPath)) {
                $mail->attach($resolvedPath, ['as' => basename($resolvedPath)]);
            } else {
                Log::warning('membership.update_email.attachment_not_found', [
                    'path' => $attachmentPath,
                    'resolved_path' => $resolvedPath,
                ]);
            }
        }

        return $mail;
    }

    private function peerName(): string
    {
        $name = trim((string) ($this->user->display_name ?: trim(($this->user->first_name ?? '') . ' ' . ($this->user->last_name ?? ''))));

        return $name !== '' ? $name : 'Peer';
    }
}
