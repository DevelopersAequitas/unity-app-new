<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CircleJoinCongratulationsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $displayName,
        public string $circleName,
        public string $categoryName,
        public string $joinRequestId,
        public string $formattedAmount,
        public ?string $paymentUrl
    ) {}

    public function build(): self
    {
        return $this->subject('Congratulations! Your Circle Joining Request Has Been Approved')
            ->view('emails.circle_join_congratulations');
    }
}
