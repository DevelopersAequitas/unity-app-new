<?php

namespace App\Services\Certificates;

use App\Models\EntrepreneurCertificationSubmission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class EntrepreneurCertificatePdf
{
    private const PAGE_WIDTH = 842;
    private const PAGE_HEIGHT = 595;

    public function generate(EntrepreneurCertificationSubmission $submission): string
    {
        $details = $this->details($submission);
        $content = $this->buildContent($details);

        return $this->buildPdf($content);
    }

    public function filename(EntrepreneurCertificationSubmission $submission): string
    {
        return 'entrepreneur-certificate-'.$submission->id.'.pdf';
    }

    public function details(EntrepreneurCertificationSubmission $submission): array
    {
        return [
            'full_name' => $this->displayValue($submission->full_name),
            'business_name' => $this->displayValue($submission->business_name),
            'email' => $this->displayValue($submission->email),
            'contact_no' => $this->displayValue($submission->contact_no),
            'certification_tier' => $this->displayValue($submission->certification_tier),
            'total_score' => $this->displayValue($submission->total_score),
            'percentage' => $this->formatPercentage($submission->percentage),
            'approval_date' => $this->formatApprovalDate($submission),
            'certificate_id' => $this->displayValue($submission->id),
        ];
    }

    private function buildContent(array $details): string
    {
        $commands = [];
        $commands[] = 'q 0.98 0.97 1 rg 0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.' re f Q';
        $commands[] = 'q 0.30 0.13 0.55 rg 0 515 '.self::PAGE_WIDTH.' 80 re f Q';
        $commands[] = 'q 0.30 0.13 0.55 rg 0 0 '.self::PAGE_WIDTH.' 52 re f Q';
        $commands[] = 'q 0.30 0.13 0.55 RG 3 w 36 74 770 408 re S Q';
        $commands[] = 'q 0.78 0.65 0.95 RG 1.2 w 50 88 742 380 re S Q';

        $commands[] = $this->centerText('PeersGlobal', 548, 24, 'F2', [1, 1, 1]);
        $commands[] = $this->centerText('Community of Collaboration', 526, 11, 'F1', [0.91, 0.86, 1]);
        $commands[] = $this->centerText('Certificate of Achievement', 466, 30, 'F2', [0.22, 0.10, 0.40]);
        $commands[] = $this->centerText('Entrepreneur Certification', 434, 16, 'F2', [0.46, 0.25, 0.70]);
        $commands[] = $this->centerText('This certificate is awarded to', 386, 13, 'F1', [0.35, 0.35, 0.42]);
        $commands[] = $this->centerText($details['full_name'], 350, 28, 'F2', [0.15, 0.10, 0.22]);
        $commands[] = $this->centerText('for successfully achieving the Entrepreneur Certification with the tier', 318, 12, 'F1', [0.36, 0.36, 0.44]);
        $commands[] = $this->centerText($details['certification_tier'], 290, 18, 'F2', [0.30, 0.13, 0.55]);

        $commands[] = 'q 0.96 0.94 1 rg 150 116 542 142 re f Q';
        $commands[] = 'q 0.69 0.55 0.85 RG 1 w 150 116 542 142 re S Q';
        $commands[] = $this->text('Certification Details', 170, 235, 13, 'F2', [0.22, 0.10, 0.40]);

        $rows = [
            ['Business Name', $details['business_name'], 'Email', $details['email']],
            ['Contact Number', $details['contact_no'], 'Certification Tier', $details['certification_tier']],
            ['Total Score', $details['total_score'], 'Percentage', $details['percentage']],
            ['Approval Date', $details['approval_date'], 'Certificate ID', $details['certificate_id']],
        ];

        $y = 206;
        foreach ($rows as $row) {
            $lineY = $y - 8;
            $commands[] = 'q 0.82 0.76 0.91 RG 0.5 w 170 '.$lineY.' m 672 '.$lineY.' l S Q';
            $commands[] = $this->text($row[0], 170, $y, 9, 'F1', [0.42, 0.34, 0.52]);
            $commands[] = $this->text($row[1], 285, $y, 10, 'F2', [0.13, 0.12, 0.16]);
            $commands[] = $this->text($row[2], 455, $y, 9, 'F1', [0.42, 0.34, 0.52]);
            $commands[] = $this->text($row[3], 570, $y, 10, 'F2', [0.13, 0.12, 0.16]);
            $y -= 27;
        }

        $commands[] = $this->centerText('Peers Global Team', 91, 12, 'F2', [0.22, 0.10, 0.40]);
        $commands[] = $this->centerText('Peers are partners in business and friends in life.', 22, 12, 'F2', [1, 1, 1]);

        return implode("\n", $commands)."\n";
    }

    private function buildPdf(string $content): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            '<< /Length '.strlen($content).' >>' . "\nstream\n" . $content . "endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $objectNumber = $index + 1;
            $pdf .= $objectNumber." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i])."\n";
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF\n";

        return $pdf;
    }

    private function centerText(string $text, float $y, float $fontSize, string $font, array $rgb): string
    {
        $x = max(48, (self::PAGE_WIDTH - $this->estimateTextWidth($text, $fontSize)) / 2);

        return $this->text($text, $x, $y, $fontSize, $font, $rgb);
    }

    private function text(string $text, float $x, float $y, float $fontSize, string $font, array $rgb): string
    {
        [$r, $g, $b] = $rgb;

        return sprintf(
            'BT /%s %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET',
            $font,
            $fontSize,
            $r,
            $g,
            $b,
            $x,
            $y,
            $this->escapePdfText($this->truncateText($text, $fontSize))
        );
    }

    private function estimateTextWidth(string $text, float $fontSize): float
    {
        return strlen($this->toPdfEncoding($text)) * $fontSize * 0.52;
    }

    private function truncateText(string $text, float $fontSize): string
    {
        $maxCharacters = $fontSize >= 18 ? 46 : 62;

        return Str::limit($text, $maxCharacters, '...');
    }

    private function escapePdfText(string $text): string
    {
        return strtr($this->toPdfEncoding($text), [
            '\\' => '\\\\',
            '(' => '\\(',
            ')' => '\\)',
        ]);
    }

    private function toPdfEncoding(string $text): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        if ($converted === false) {
            return preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
        }

        return $converted;
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return (string) $value;
    }

    private function formatPercentage(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $percentage = (string) $value;

        if (str_contains($percentage, '%')) {
            return $percentage;
        }

        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'%';
        }

        return $percentage;
    }

    private function formatApprovalDate(EntrepreneurCertificationSubmission $submission): string
    {
        $date = $submission->getAttribute('approved_at') ?: $submission->updated_at;

        if (! $date) {
            return '—';
        }

        return Carbon::parse($date)->format('d M Y');
    }
}
