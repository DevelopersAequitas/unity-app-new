<?php

namespace App\Mail;

use App\Models\LeadershipCertificationSubmission;
use Carbon\Carbon;
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
    public string $certificateTitle = 'Leadership Certification';
    public string $recipientName;
    public string $achievementLabel;
    public array $certificationDetails;

    public function __construct(LeadershipCertificationSubmission $submission, ?CarbonInterface $approvalDate = null)
    {
        $this->submission = $submission;
        $this->approvalDate = $this->formatDate($approvalDate ?? $submission->getAttribute('approved_at') ?? $submission->updated_at ?? now());
        $this->recipientName = $this->displayValue($submission->full_name, 'Peer');
        $this->achievementLabel = $this->displayValue($submission->certification_level, $this->certificateTitle);
        $this->certificationDetails = [
            'Business Name' => $this->displayValue($submission->business_name),
            'Email' => $this->displayValue($submission->email),
            'Contact Number' => $this->displayValue($submission->contact_no),
            'Certification Level' => $this->displayValue($submission->certification_level),
            'Total Score' => $this->displayValue($submission->total_score),
            'Percentage' => $this->formatPercentage($submission->percentage),
            'Approval Date' => $this->displayValue($this->approvalDate),
        ];
    }

    public function build(): self
    {
        return $this->subject('Congratulations! Your Leadership Certification Has Been Approved')
            ->view('emails.certifications.leadership-approved');
    }

    private function displayValue(mixed $value, string $fallback = '—'): string
    {
        if ($value === null) {
            return $fallback;
        }

        $displayValue = trim((string) $value);

        return $displayValue === '' ? $fallback : $displayValue;
    }

    private function formatPercentage(mixed $value): string
    {
        $displayValue = $this->displayValue($value);

        if ($displayValue === '—') {
            return $displayValue;
        }

        if (str_contains($displayValue, '%')) {
            return $displayValue;
        }

        return rtrim(rtrim(number_format((float) $displayValue, 2), '0'), '.').'%';
    }

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('d M Y');
        }

        try {
            return Carbon::parse((string) $value)->format('d M Y');
        } catch (\Throwable) {
            return $this->displayValue($value);
        }
    }
}
