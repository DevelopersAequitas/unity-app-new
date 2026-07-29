<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\CertificationSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CertificationApprovedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public CertificationSubmission $submission) {}

    public function build(): self
    {
        $typeLabel = ucfirst($this->submission->certification_type);

        return $this->subject("Your {$typeLabel} Certification Has Been Approved! 🎉")
            ->view('emails.certification_approved', [
                'submission' => $this->submission,
                'logoUrl' => 'https://peersunity.com/images/peersglobal-logo.png',
            ]);
    }
}
