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

    /**
     * @param  array<int, array{path: string, name: string, mime: string}>  $attachmentsList
     */
    public function __construct(
        public SupportTicket $ticket,
        public string $emailSubject,
        public string $responseMessage,
        public array $attachmentsList = []
    ) {}

    public function build(): self
    {
        $mail = $this->subject($this->emailSubject)
            ->view('emails.support-ticket-response');

        foreach ($this->attachmentsList as $attachment) {
            if (isset($attachment['path']) && file_exists($attachment['path'])) {
                $options = [];
                if (! empty($attachment['name'])) {
                    $options['as'] = $attachment['name'];
                }
                if (! empty($attachment['mime'])) {
                    $options['mime'] = $attachment['mime'];
                }
                $mail->attach($attachment['path'], $options);
            }
        }

        return $mail;
    }
}
