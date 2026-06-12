<?php

namespace App\Services\Certifications;

use App\Models\CertificationSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateGeneratorService
{
    public function approveSubmission(CertificationSubmission $submission, ?string $adminNote, ?string $adminId): CertificationSubmission
    {
        return DB::transaction(function () use ($submission, $adminNote, $adminId) {
            /** @var CertificationSubmission $submission */
            $submission = CertificationSubmission::query()
                ->whereKey($submission->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $now = now();

            $submission->forceFill([
                'status' => CertificationSubmission::STATUS_APPROVED,
                'admin_note' => $adminNote,
                'approved_by' => $adminId,
                'approved_at' => $now,
                'rejected_by' => null,
                'rejected_at' => null,
            ]);

            if (! $submission->certificate_number) {
                $submission->certificate_number = $this->nextCertificateNumber($submission);
            }

            if (! $submission->issued_at) {
                $submission->issued_at = $now;
            }

            if ($this->shouldWriteCertificatePdf($submission)) {
                $this->writeCertificatePdf($submission, $now);
            }

            $submission->save();

            return $submission->refresh();
        });
    }

    private function nextCertificateNumber(CertificationSubmission $submission): string
    {
        $typePrefix = $submission->certification_type === CertificationSubmission::TYPE_LEADERSHIP ? 'LEAD' : 'ENT';
        $year = now()->format('Y');
        $prefix = $typePrefix . '-' . $year . '-';

        $numbers = CertificationSubmission::query()
            ->where('certificate_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->pluck('certificate_number')
            ->all();

        $max = 0;
        foreach ($numbers as $number) {
            $suffix = (int) Str::afterLast((string) $number, '-');
            $max = max($max, $suffix);
        }

        do {
            $candidate = $prefix . str_pad((string) (++$max), 6, '0', STR_PAD_LEFT);
        } while (CertificationSubmission::query()->where('certificate_number', $candidate)->exists());

        return $candidate;
    }

    private function shouldWriteCertificatePdf(CertificationSubmission $submission): bool
    {
        return ! $submission->certificate_file_path
            || ! $submission->certificate_download_url
            || ! $submission->certificate_generated_at
            || ! Storage::disk('public')->exists($submission->certificate_file_path);
    }

    private function writeCertificatePdf(CertificationSubmission $submission, $generatedAt): void
    {
        $fileName = $submission->certificate_number . '.pdf';
        $relativePath = 'certificates/' . $fileName;
        $pdfBytes = $this->renderPdf($submission);

        Storage::disk('public')->put($relativePath, $pdfBytes);

        $submission->forceFill([
            'certificate_file_path' => $relativePath,
            'certificate_download_url' => asset('storage/' . $relativePath),
            'certificate_generated_at' => $generatedAt,
        ]);
    }

    private function renderPdf(CertificationSubmission $submission): string
    {
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('certificates.certification', [
                'submission' => $submission,
            ])->setPaper('a4', 'landscape')->output();
        }

        return $this->renderBasicPdf($submission);
    }

    private function renderBasicPdf(CertificationSubmission $submission): string
    {
        $lines = [
            'Peers Global Unity',
            $this->certificateTitle($submission),
            'This certificate is proudly presented to',
            (string) $submission->full_name,
            'Business: ' . (($submission->business_name ?: 'N/A')),
            'Certification Level: ' . (($submission->certification_level ?: 'N/A')),
            'Score: ' . (int) $submission->total_score . ' | Percentage: ' . (int) $submission->percentage . '%',
            'Certificate Number: ' . $submission->certificate_number,
            'Issued Date: ' . optional($submission->issued_at ?: now())->format('d M Y'),
            'Approved Date: ' . optional($submission->approved_at ?: now())->format('d M Y'),
        ];

        $content = "BT\n/F1 28 Tf\n80 530 Td\n(" . $this->escapePdfText($lines[0]) . ") Tj\n";
        $content .= "/F1 22 Tf\n0 -52 Td\n(" . $this->escapePdfText($lines[1]) . ") Tj\n";
        $content .= "/F1 14 Tf\n0 -56 Td\n(" . $this->escapePdfText($lines[2]) . ") Tj\n";
        $content .= "/F1 26 Tf\n0 -42 Td\n(" . $this->escapePdfText($lines[3]) . ") Tj\n";
        $content .= "/F1 12 Tf\n0 -48 Td\n";

        foreach (array_slice($lines, 4) as $line) {
            $content .= '(' . $this->escapePdfText($line) . ") Tj\n0 -24 Td\n";
        }

        $content .= 'ET';

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function certificateTitle(CertificationSubmission $submission): string
    {
        return $submission->certification_type === CertificationSubmission::TYPE_LEADERSHIP
            ? 'Leadership Certification'
            : 'Entrepreneur Certification';
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
