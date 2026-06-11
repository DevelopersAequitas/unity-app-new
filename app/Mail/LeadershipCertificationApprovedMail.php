<?php

namespace App\Mail;

use App\Models\LeadershipCertificationSubmission;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadershipCertificationApprovedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public LeadershipCertificationSubmission $submission;
    public string $approvalDate;

    public function __construct(LeadershipCertificationSubmission $submission, ?CarbonInterface $approvalDate = null)
    {
        $this->submission = $submission;
        $this->approvalDate = ($approvalDate ?? now())->format('d M Y');
    }

    public function build(): self
    {
        return $this->subject('Congratulations! Your Leadership Certification Has Been Approved')
            ->view('emails.certifications.leadership-approved');
    }
}
