<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class MembershipExpiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function build()
    {
        return $this->from('pravin@peersunity.com', 'Peers Global')
            ->subject('Membership Expired')
            ->view('emails.membership.expired')
            ->with(['user' => $this->user])
            ->withSymfonyMessage(function (Email $message): void {
                $headers = $message->getHeaders();

                if ($headers->has('Reply-To')) {
                    $headers->remove('Reply-To');
                }

                if (! $headers->has('Date')) {
                    $headers->addDateHeader('Date', now()->toDateTimeImmutable());
                }
            });
    }
}
