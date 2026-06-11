<?php

namespace App\Mail;

use App\Models\EntrepreneurCertificationSubmission;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EntrepreneurCertificationApprovedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public EntrepreneurCertificationSubmission $submission;
    public string $approvalDate;

    public function __construct(EntrepreneurCertificationSubmission $submission, ?CarbonInterface $approvalDate = null)
    {
        $this->submission = $submission;
        $this->approvalDate = ($approvalDate ?? now())->format('d M Y');
    }

    public function build(): self
    {
        return $this->subject('Congratulations! Your Entrepreneur Certification Has Been Approved')
            ->view('emails.certifications.entrepreneur-approved');
    }
}
