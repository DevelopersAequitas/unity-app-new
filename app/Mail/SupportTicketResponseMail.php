<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SupportTicketResponseMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public string $emailSubject,
        public string $responseMessage
    ) {}

    public function build(): self
    {
        return $this->subject($this->emailSubject)
            ->view('emails.support-ticket-response');
    }
}
