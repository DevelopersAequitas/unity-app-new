<?php

declare(strict_types=1);

namespace App\Leader\Services;

class LeaderReportPdfGenerator
{
    /**
     * Generate standard PDF binary string for Leader Performance Report.
     *
     * @param  array<string, mixed>  $report
     */
    public function generate(array $report): string
    {
        $pageWidth = 595.28;
        $pageHeight = 841.89;
        $marginLeft = 36.0;
        $contentWidth = $pageWidth - (2 * $marginLeft); // 523.28

        $stream = [];
        $stream[] = 'q'; // save state

        // Top Accent Bar
        $stream[] = $this->rect(0, $pageHeight - 6, $pageWidth, 6, [0.388, 0.400, 0.945], null);

        // Header Section
        $curY = $pageHeight - 45;
        $stream[] = $this->text('PEERS UNITY', $marginLeft, $curY, 20, true, [0.118, 0.106, 0.294]);
        $curY -= 14;
        $stream[] = $this->text('LEADERSHIP PERFORMANCE REPORT', $marginLeft, $curY, 9, true, [0.388, 0.400, 0.945]);

        // Badges on Top Right
        $reportType = strtoupper((string) ($report['report_type'] ?? 'MONTHLY')).' REPORT';
        $status = strtoupper((string) ($report['status'] ?? 'APPROVED'));
        $badgeX = $pageWidth - $marginLeft - 180;
        $stream[] = $this->rect($badgeX, $pageHeight - 48, 95, 20, [0.933, 0.949, 1.0], [0.780, 0.824, 0.996]);
        $stream[] = $this->text($reportType, $badgeX + 8, $pageHeight - 42, 8, true, [0.263, 0.220, 0.792]);

        $stream[] = $this->rect($badgeX + 102, $pageHeight - 48, 78, 20, [0.925, 0.992, 0.961], [0.655, 0.953, 0.816]);
        $stream[] = $this->text($status, $badgeX + 110, $pageHeight - 42, 8, true, [0.016, 0.471, 0.341]);

        // Separator Line
        $curY -= 12;
        $stream[] = $this->line($marginLeft, $curY, $marginLeft + $contentWidth, $curY, [0.89, 0.91, 0.94], 1.5);

        // Metadata Grid Card
        $curY -= 55;
        $metaCardY = $curY;
        $metaCardHeight = 46;
        $stream[] = $this->rect($marginLeft, $metaCardY, $contentWidth, $metaCardHeight, [0.973, 0.980, 0.988], [0.886, 0.910, 0.941]);

        $colW = $contentWidth / 4;
        // Col 1: Circle
        $stream[] = $this->text('CIRCLE', $marginLeft + 10, $metaCardY + 28, 8, true, [0.392, 0.455, 0.545]);
        $stream[] = $this->text($this->limitText((string) ($report['circle_name'] ?? 'Mumbai Tech Sunrise'), 24), $marginLeft + 10, $metaCardY + 14, 10, true, [0.059, 0.090, 0.165]);

        // Col 2: District & Industry
        $distInd = ($report['district_name'] ?? 'District Mumbai').' / '.($report['industry_name'] ?? 'Tech');
        $stream[] = $this->text('DISTRICT & INDUSTRY', $marginLeft + $colW + 10, $metaCardY + 28, 8, true, [0.392, 0.455, 0.545]);
        $stream[] = $this->text($this->limitText($distInd, 24), $marginLeft + $colW + 10, $metaCardY + 14, 10, true, [0.059, 0.090, 0.165]);

        // Col 3: Submitter
        $stream[] = $this->text('SUBMITTED BY', $marginLeft + ($colW * 2) + 10, $metaCardY + 28, 8, true, [0.392, 0.455, 0.545]);
        $stream[] = $this->text($this->limitText((string) ($report['submitted_by'] ?? 'Arjun Patel'), 24), $marginLeft + ($colW * 2) + 10, $metaCardY + 14, 10, true, [0.059, 0.090, 0.165]);

        // Col 4: Period
        $stream[] = $this->text('PERIOD', $marginLeft + ($colW * 3) + 10, $metaCardY + 28, 8, true, [0.392, 0.455, 0.545]);
        $stream[] = $this->text((string) ($report['period'] ?? now()->format('F Y')), $marginLeft + ($colW * 3) + 10, $metaCardY + 14, 10, true, [0.059, 0.090, 0.165]);

        // KPI Metric Cards (3 columns)
        $curY -= 68;
        $kpiCardY = $curY;
        $kpiCardH = 56;
        $kpiW = ($contentWidth - 20) / 3;

        // KPI 1: Attendance
        $stream[] = $this->rect($marginLeft, $kpiCardY, $kpiW, $kpiCardH, [0.933, 0.949, 1.0], [0.780, 0.824, 0.996]);
        $stream[] = $this->text('ATTENDANCE RATE', $marginLeft + 12, $kpiCardY + 36, 8, true, [0.263, 0.220, 0.792]);
        $attVal = number_format((float) ($report['attendance_percentage'] ?? 94.5), 1).'%';
        $stream[] = $this->text($attVal, $marginLeft + 12, $kpiCardY + 14, 18, true, [0.118, 0.106, 0.294]);

        // KPI 2: Deals Closed Value
        $stream[] = $this->rect($marginLeft + $kpiW + 10, $kpiCardY, $kpiW, $kpiCardH, [0.925, 0.992, 0.961], [0.655, 0.953, 0.816]);
        $stream[] = $this->text('DEALS CLOSED VALUE', $marginLeft + $kpiW + 22, $kpiCardY + 36, 8, true, [0.016, 0.471, 0.341]);
        $dealsVal = $this->formatCurrency((string) ($report['deals_closed_value'] ?? 'INR 18.5L'));
        $stream[] = $this->text($dealsVal, $marginLeft + $kpiW + 22, $kpiCardY + 14, 18, true, [0.024, 0.329, 0.239]);

        // KPI 3: Total Revenue
        $stream[] = $this->rect($marginLeft + ($kpiW * 2) + 20, $kpiCardY, $kpiW, $kpiCardH, [0.945, 0.961, 0.976], [0.796, 0.835, 0.882]);
        $stream[] = $this->text('TOTAL REVENUE', $marginLeft + ($kpiW * 2) + 32, $kpiCardY + 36, 8, true, [0.278, 0.333, 0.412]);
        $revVal = $this->formatCurrency((string) ($report['total_revenue'] ?? 'INR 24.0L'));
        $stream[] = $this->text($revVal, $marginLeft + ($kpiW * 2) + 32, $kpiCardY + 14, 18, true, [0.059, 0.090, 0.165]);

        // Executive Summary
        $curY -= 20;
        $stream[] = $this->text('EXECUTIVE SUMMARY', $marginLeft, $curY, 10, true, [0.059, 0.090, 0.165]);
        $curY -= 36;
        $summaryText = (string) ($report['summary_text'] ?? 'Strong monthly participation with new peer referrals closed across industry categories.');
        $stream[] = $this->rect($marginLeft, $curY, $contentWidth, 32, [1.0, 1.0, 1.0], [0.886, 0.910, 0.941]);
        $stream[] = $this->text($this->limitText($summaryText, 95), $marginLeft + 10, $curY + 12, 9, false, [0.200, 0.255, 0.333]);

        // Highlights & Action Items (2 columns)
        $curY -= 20;
        $stream[] = $this->text('HIGHLIGHTS & ACHIEVEMENTS', $marginLeft, $curY, 10, true, [0.059, 0.090, 0.165]);
        $stream[] = $this->text('ACTION ITEMS & NEXT STEPS', $marginLeft + ($contentWidth / 2) + 5, $curY, 10, true, [0.059, 0.090, 0.165]);

        $curY -= 42;
        $cardW = ($contentWidth - 10) / 2;
        $hlText = (string) ($report['highlights'] ?? 'Launched new partnerships and active peer engagement.');
        $stream[] = $this->rect($marginLeft, $curY, $cardW, 36, [1.0, 1.0, 1.0], [0.886, 0.910, 0.941]);
        $stream[] = $this->text($this->limitText($hlText, 48), $marginLeft + 8, $curY + 14, 9, false, [0.200, 0.255, 0.333]);

        $actText = (string) ($report['action_items'] ?? 'Follow up with pending peer renewals and schedule review assemblies.');
        $stream[] = $this->rect($marginLeft + $cardW + 10, $curY, $cardW, 36, [1.0, 1.0, 1.0], [0.886, 0.910, 0.941]);
        $stream[] = $this->text($this->limitText($actText, 48), $marginLeft + $cardW + 18, $curY + 14, 9, false, [0.200, 0.255, 0.333]);

        // Peer Roster Section
        $curY -= 22;
        $stream[] = $this->text('CIRCLE PEERS ROSTER & ENGAGEMENT', $marginLeft, $curY, 10, true, [0.059, 0.090, 0.165]);

        // Roster Table
        $roster = is_array($report['peers_roster'] ?? null) ? array_slice($report['peers_roster'], 0, 7) : [];
        $curY -= 18;
        $tableHeaderY = $curY;
        $stream[] = $this->rect($marginLeft, $tableHeaderY, $contentWidth, 16, [0.973, 0.980, 0.988], [0.886, 0.910, 0.941]);

        $stream[] = $this->text('PEER NAME', $marginLeft + 8, $tableHeaderY + 5, 8, true, [0.278, 0.333, 0.412]);
        $stream[] = $this->text('COMPANY & DESIGNATION', $marginLeft + 130, $tableHeaderY + 5, 8, true, [0.278, 0.333, 0.412]);
        $stream[] = $this->text('STATUS', $marginLeft + 310, $tableHeaderY + 5, 8, true, [0.278, 0.333, 0.412]);
        $stream[] = $this->text('ATTEND', $marginLeft + 370, $tableHeaderY + 5, 8, true, [0.278, 0.333, 0.412]);
        $stream[] = $this->text('DEALS', $marginLeft + 420, $tableHeaderY + 5, 8, true, [0.278, 0.333, 0.412]);
        $stream[] = $this->text('P2P', $marginLeft + 475, $tableHeaderY + 5, 8, true, [0.278, 0.333, 0.412]);

        $rowY = $tableHeaderY;
        foreach ($roster as $idx => $peer) {
            $rowY -= 18;
            if ($rowY < 60) {
                break;
            }
            $bg = ($idx % 2 === 0) ? [1.0, 1.0, 1.0] : [0.988, 0.992, 0.996];
            $stream[] = $this->rect($marginLeft, $rowY, $contentWidth, 18, $bg, [0.945, 0.961, 0.976]);

            $pName = $this->limitText((string) ($peer['name'] ?? 'Peer'), 18);
            $pComp = $this->limitText(($peer['designation'] ?? 'Member').' @ '.($peer['company'] ?? 'Unity'), 28);
            $pStat = (string) ($peer['status'] ?? 'Active');
            $pAtt = (string) ($peer['attendance'] ?? '94%');
            $pDeals = $this->formatCurrency((string) ($peer['deals_closed'] ?? 'INR 10L'));
            $pP2p = (string) ($peer['p2p_count'] ?? '12');

            $stream[] = $this->text($pName, $marginLeft + 8, $rowY + 5, 8, true, [0.059, 0.090, 0.165]);
            $stream[] = $this->text($pComp, $marginLeft + 130, $rowY + 5, 8, false, [0.333, 0.416, 0.522]);
            $stream[] = $this->text($pStat, $marginLeft + 310, $rowY + 5, 8, false, [0.016, 0.471, 0.341]);
            $stream[] = $this->text($pAtt, $marginLeft + 370, $rowY + 5, 8, false, [0.059, 0.090, 0.165]);
            $stream[] = $this->text($pDeals, $marginLeft + 420, $rowY + 5, 8, false, [0.059, 0.090, 0.165]);
            $stream[] = $this->text($pP2p, $marginLeft + 475, $rowY + 5, 8, false, [0.059, 0.090, 0.165]);
        }

        // Footer Section
        $stream[] = $this->line($marginLeft, 42, $marginLeft + $contentWidth, 42, [0.886, 0.910, 0.941], 1.0);
        $footerText = 'Peers Unity Leadership System  |  Report ID: '.($report['id'] ?? '').'  |  Confidential';
        $stream[] = $this->text($footerText, $marginLeft + 80, 30, 8, false, [0.580, 0.639, 0.722]);

        $stream[] = 'Q'; // restore state
        $contentStream = implode("\n", $stream);

        return $this->buildPdfDocument($contentStream, $pageWidth, $pageHeight);
    }

    private function rect(float $x, float $y, float $w, float $h, ?array $fillRgb, ?array $strokeRgb): string
    {
        $out = [];
        $out[] = sprintf('%.2f %.2f %.2f %.2f re', $x, $y, $w, $h);
        if ($fillRgb && $strokeRgb) {
            $out[] = sprintf('%.3f %.3f %.3f rg', $fillRgb[0], $fillRgb[1], $fillRgb[2]);
            $out[] = sprintf('%.3f %.3f %.3f RG', $strokeRgb[0], $strokeRgb[1], $strokeRgb[2]);
            $out[] = 'B';
        } elseif ($fillRgb) {
            $out[] = sprintf('%.3f %.3f %.3f rg', $fillRgb[0], $fillRgb[1], $fillRgb[2]);
            $out[] = 'f';
        } elseif ($strokeRgb) {
            $out[] = sprintf('%.3f %.3f %.3f RG', $strokeRgb[0], $strokeRgb[1], $strokeRgb[2]);
            $out[] = 'S';
        }

        return implode("\n", $out);
    }

    private function line(float $x1, float $y1, float $x2, float $y2, array $rgb, float $width = 1.0): string
    {
        return sprintf(
            "%.2f w\n%.3f %.3f %.3f RG\n%.2f %.2f m\n%.2f %.2f l\nS",
            $width,
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x1,
            $y1,
            $x2,
            $y2
        );
    }

    private function text(string $text, float $x, float $y, float $size, bool $bold, array $rgb): string
    {
        $font = $bold ? '/F2' : '/F1';
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->toAscii($text));

        return sprintf(
            "BT\n%s %.2f Tf\n%.3f %.3f %.3f rg\n%.2f %.2f Td\n(%s) Tj\nET",
            $font,
            $size,
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x,
            $y,
            $escaped
        );
    }

    private function toAscii(string $str): string
    {
        $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str);

        return $converted !== false ? $converted : preg_replace('/[^\x20-\x7E]/', '', $str);
    }

    private function formatCurrency(string $val): string
    {
        return str_replace(['₹', 'Ã¢â€šÂ¹'], 'Rs. ', $val);
    }

    private function limitText(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit - 3).'...';
    }

    private function buildPdfDocument(string $contentStream, float $width, float $height): string
    {
        $objects = [];
        $streamLen = strlen($contentStream);

        // 1 0 obj: Catalog
        $objects[1] = "<<\n  /Type /Catalog\n  /Pages 2 0 R\n>>";
        // 2 0 obj: Pages
        $objects[2] = "<<\n  /Type /Pages\n  /Kids [3 0 R]\n  /Count 1\n>>";
        // 3 0 obj: Page
        $objects[3] = sprintf(
            "<<\n  /Type /Page\n  /Parent 2 0 R\n  /MediaBox [0 0 %.2f %.2f]\n  /Resources 4 0 R\n  /Contents 7 0 R\n>>",
            $width,
            $height
        );
        // 4 0 obj: Resources
        $objects[4] = "<<\n  /Font <<\n    /F1 5 0 R\n    /F2 6 0 R\n  >>\n>>";
        // 5 0 obj: Font Regular (Helvetica)
        $objects[5] = "<<\n  /Type /Font\n  /Subtype /Type1\n  /BaseFont /Helvetica\n  /Encoding /WinAnsiEncoding\n>>";
        // 6 0 obj: Font Bold (Helvetica-Bold)
        $objects[6] = "<<\n  /Type /Font\n  /Subtype /Type1\n  /BaseFont /Helvetica-Bold\n  /Encoding /WinAnsiEncoding\n>>";
        // 7 0 obj: Content Stream
        $objects[7] = sprintf("<<\n  /Length %d\n>>\nstream\n%s\nendstream", $streamLen, $contentStream);

        $pdf = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $offsets = [];

        foreach ($objects as $id => $obj) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$obj}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $totalObjs = count($objects) + 1;
        $pdf .= "xref\n0 {$totalObjs}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<<\n  /Size {$totalObjs}\n  /Root 1 0 R\n>>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }
}
