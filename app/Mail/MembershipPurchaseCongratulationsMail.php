<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MembershipPurchaseCongratulationsMail extends Mailable
{
    use Queueable, SerializesModels;

    private function senderAddress(): string
    {
        return (string) config('mail.from.address');
    }

    private function senderName(): string
    {
        return (string) config('mail.from.name');
    }

    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    private function applyMembershipHeaders(Mailable $mail, string $emailType): Mailable
    {
        return $mail->replyTo($this->senderAddress(), $this->senderName())
            ->withSymfonyMessage(function ($message) use ($emailType): void {
                $headers = $message->getHeaders();
                $headers->addTextHeader('X-PeersGlobal-Email-Type', $emailType);
                $headers->addTextHeader('X-Auto-Response-Suppress', 'All');
                $headers->addTextHeader('Precedence', 'bulk');
            });
    }

    public function build()
    {
        return $this->applyMembershipHeaders(
            $this->from($this->senderAddress(), $this->senderName())
                ->subject('Congratulations! Your Membership Is Now Active')
                ->view('emails.membership.membership_purchase_congratulations')
                ->text('emails.membership.text.purchase-congratulations'),
            'membership_purchase'
        )
            ->with([
                'user' => $this->user,
            ]);
    }
}
