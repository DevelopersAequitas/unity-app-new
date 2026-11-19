<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdvertiserNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $recipientName,
        public readonly string $heading,
        public readonly string $mainMessage,
        public readonly ?string $actionUrl = null,
        public readonly ?string $actionText = null,
        public readonly array $details = [],
        public readonly ?string $replyToEmail = null,
        public readonly ?string $replyToName = null,
        public readonly ?string $secondaryActionUrl = null,
        public readonly ?string $secondaryActionText = null,
        public readonly ?string $footerNote = null
    ) {}

    public function build(): self
    {
        $mail = $this->subject($this->subjectLine)
            ->view('emails.advertiser_notification');

        if (! empty($this->replyToEmail) && filter_var($this->replyToEmail, FILTER_VALIDATE_EMAIL)) {
            $mail->replyTo($this->replyToEmail, $this->replyToName ?: null);
        }

        return $mail;
    }
}
