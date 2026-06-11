<?php

namespace App\Mail;

use App\Models\EntrepreneurCertificationSubmission;
use Carbon\Carbon;
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
        $this->approvalDate = $this->formatDate($approvalDate ?? $submission->getAttribute('approved_at') ?? $submission->updated_at ?? now());
        $this->recipientName = $this->displayValue($submission->full_name, 'Peer');
        $this->achievementLabel = $this->displayValue($submission->certification_tier, $this->certificateTitle);
        $this->certificationDetails = [
            'Business Name' => $this->displayValue($submission->business_name),
            'Email' => $this->displayValue($submission->email),
            'Contact Number' => $this->displayValue($submission->contact_no),
            'Total Score' => $this->displayValue($submission->total_score),
            'Percentage' => $this->formatPercentage($submission->percentage),
        ];
    }

    public function build(): self
    {
        return $this->subject('Congratulations! Your Entrepreneur Certification Has Been Approved')
            ->view('emails.certifications.entrepreneur-approved');
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
