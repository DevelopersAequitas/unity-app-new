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
    public string $certificateTitle = 'Entrepreneur Certification';
    public string $recipientName;
    public string $achievementLabel;
    public array $certificationDetails;

    public function __construct(EntrepreneurCertificationSubmission $submission, ?CarbonInterface $approvalDate = null)
    {
        $this->submission = $submission;
        $this->approvalDate = ($approvalDate ?? $submission->updated_at ?? now())->format('d M Y');
        $this->recipientName = $this->displayValue($submission->full_name, 'Peer');
        $this->achievementLabel = $this->displayValue($submission->certification_tier, $this->certificateTitle);
        $this->certificationDetails = [
            'Business Name' => $this->displayValue($submission->business_name),
            'Email' => $this->displayValue($submission->email),
            'Contact Number' => $this->displayValue($submission->contact_no),
            'Certification Tier' => $this->displayValue($submission->certification_tier),
            'Total Score' => $this->displayValue($submission->total_score),
            'Percentage' => $this->formatPercentage($submission->percentage),
            'Approval Date' => $this->displayValue($this->approvalDate),
        ];
    }

    public function build(): self
    {
        return $this->subject('Congratulations! Your Entrepreneur Certification Has Been Approved')
            ->view('emails.certifications.entrepreneur-approved');
    }

    private function displayValue(mixed $value, string $fallback = '—'): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return (string) $value;
    }

    private function formatPercentage(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'%';
    }
}
