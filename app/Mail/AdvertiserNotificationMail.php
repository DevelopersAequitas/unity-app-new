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
        public readonly array $details = []
    ) {}

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.advertiser_notification');
    }
}
