<?php

declare(strict_types=1);

namespace App\Services\SpeedNetworking;

use RuntimeException;
use ZipArchive;

/**
 * Pure-PHP OOXML (.xlsx) multi-worksheet generator built with ZipArchive.
 */
class SimpleXlsxBuilder
{
    /**
     * @var array<int, array{name: string, headers: array<int, string>, rows: array<int, array<int, int|float|string|null>>}>
     */
    protected array $sheets = [];

    /**
     * Add a worksheet to the workbook.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, int|float|string|null>>  $rows
     */
    public function addSheet(string $name, array $headers, array $rows): void
    {
        $sanitized = preg_replace('/[\\\\\/\?\*\[\]:]+/', ' ', $name) ?? 'Sheet';
        $sanitized = trim($sanitized);
        if ($sanitized === '') {
            $sanitized = 'Sheet'.(count($this->sheets) + 1);
        }
        $sanitized = mb_substr($sanitized, 0, 31);

        // Deduplicate sheet names case-insensitively
        $existingNames = array_map(
            static fn (array $s): string => mb_strtolower($s['name']),
            $this->sheets
        );
        $base = $sanitized;
        $suffix = 2;
        while (in_array(mb_strtolower($sanitized), $existingNames, true)) {
            $tag = " ({$suffix})";
            $sanitized = mb_substr($base, 0, max(1, 31 - mb_strlen($tag))).$tag;
            $suffix++;
        }

        $this->sheets[] = [
            'name' => $sanitized,
            'headers' => array_values($headers),
            'rows' => array_values($rows),
        ];
    }

    public function buildBinary(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive extension is required for Excel exports.');
        }

        if ($this->sheets === []) {
            $this->addSheet('Summary', ['Message'], [['No data available']]);
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'pg_xlsx_');
        if ($tmpPath === false) {
            throw new RuntimeException('Unable to allocate temporary file for Excel export.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpPath);
            throw new RuntimeException('Unable to open temporary ZIP archive for Excel export.');
        }

        $zip->addFromString('[Content_Types].xml', $this->buildContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->buildRootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->buildWorkbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->buildStylesXml());

        foreach ($this->sheets as $index => $sheet) {
            $sheetNumber = $index + 1;
            $zip->addFromString(
                "xl/worksheets/sheet{$sheetNumber}.xml",
                $this->buildWorksheetXml($sheet['headers'], $sheet['rows'])
            );
        }

        $zip->close();

        $binary = file_get_contents($tmpPath);
        @unlink($tmpPath);

        if ($binary === false) {
            throw new RuntimeException('Unable to read generated Excel archive.');
        }

        return $binary;
    }

    protected function buildContentTypesXml(): string
    {
        $overrides = [
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>',
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>',
        ];
        foreach ($this->sheets as $index => $_) {
            $num = $index + 1;
            $overrides[] = "<Override PartName=\"/xl/worksheets/sheet{$num}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .implode('', $overrides)
            .'</Types>';
    }

    protected function buildRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    protected function buildWorkbookXml(): string
    {
        $sheetsXml = [];
        foreach ($this->sheets as $index => $sheet) {
            $id = $index + 1;
            $escapedName = $this->xmlEscape($sheet['name']);
            $sheetsXml[] = "<sheet name=\"{$escapedName}\" sheetId=\"{$id}\" r:id=\"rId{$id}\"/>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.implode('', $sheetsXml).'</sheets>'
            .'</workbook>';
    }

    protected function buildWorkbookRelsXml(): string
    {
        $rels = [];
        foreach ($this->sheets as $index => $_) {
            $id = $index + 1;
            $rels[] = "<Relationship Id=\"rId{$id}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$id}.xml\"/>";
        }
        $styleRid = count($this->sheets) + 1;
        $rels[] = "<Relationship Id=\"rId{$styleRid}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>";

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .implode('', $rels)
            .'</Relationships>';
    }

    protected function buildStylesXml(): string
    {
        // Style 0 = normal, Style 1 = header (bold white on #1D4ED8), Style 2 = integer #,##0, Style 3 = decimal #,##0.0
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2">'
            .'<numFmt numFmtId="164" formatCode="#,##0"/>'
            .'<numFmt numFmtId="165" formatCode="#,##0.0"/>'
            .'</numFmts>'
            .'<fonts count="2">'
            .'<font><sz val="10"/><name val="Calibri"/><color rgb="FF1E293B"/></font>'
            .'<font><b/><sz val="10"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1D4ED8"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border>'
            .'<left style="thin"><color rgb="FFE2E8F0"/></left>'
            .'<right style="thin"><color rgb="FFE2E8F0"/></right>'
            .'<top style="thin"><color rgb="FFE2E8F0"/></top>'
            .'<bottom style="thin"><color rgb="FFE2E8F0"/></bottom>'
            .'<diagonal/>'
            .'</border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, int|float|string|null>>  $rows
     */
    protected function buildWorksheetXml(array $headers, array $rows): string
    {
        $colCount = max(1, count($headers));
        $colWidths = array_fill(0, $colCount, 14);
        foreach ($headers as $cIdx => $h) {
            $colWidths[$cIdx] = min(44, max($colWidths[$cIdx], mb_strlen((string) $h) + 4));
        }

        $rowsXml = [];
        // Row 1: Header row
        $headerCells = [];
        foreach ($headers as $cIdx => $h) {
            $ref = $this->colLetter($cIdx + 1).'1';
            $safe = $this->xmlEscape($this->sanitizeFormula((string) $h));
            $headerCells[] = "<c r=\"{$ref}\" t=\"inlineStr\" s=\"1\"><is><t>{$safe}</t></is></c>";
        }
        $rowsXml[] = '<row r="1" ht="22" customHeight="1">'.implode('', $headerCells).'</row>';

        foreach ($rows as $rIdx => $row) {
            $excelRow = $rIdx + 2;
            $cellsXml = [];
            for ($cIdx = 0; $cIdx < $colCount; $cIdx++) {
                $val = $row[$cIdx] ?? '';
                $ref = $this->colLetter($cIdx + 1).$excelRow;

                if (is_int($val)) {
                    $colWidths[$cIdx] = min(44, max($colWidths[$cIdx], strlen((string) $val) + 3));
                    $cellsXml[] = "<c r=\"{$ref}\" t=\"n\" s=\"2\"><v>{$val}</v></c>";
                } elseif (is_float($val)) {
                    $style = floor($val) === $val ? 2 : 3;
                    $cellsXml[] = "<c r=\"{$ref}\" t=\"n\" s=\"{$style}\"><v>{$val}</v></c>";
                } else {
                    $strVal = $this->sanitizeFormula((string) $val);
                    $colWidths[$cIdx] = min(44, max($colWidths[$cIdx], mb_strlen($strVal) + 3));
                    $escaped = $this->xmlEscape($strVal);
                    $cellsXml[] = "<c r=\"{$ref}\" t=\"inlineStr\" s=\"0\"><is><t>{$escaped}</t></is></c>";
                }
            }
            $rowsXml[] = "<row r=\"{$excelRow}\">".implode('', $cellsXml).'</row>';
        }

        $colsXml = '<cols>';
        foreach ($colWidths as $cIdx => $width) {
            $colNum = $cIdx + 1;
            $colsXml .= "<col min=\"{$colNum}\" max=\"{$colNum}\" width=\"{$width}\" customWidth=\"1\"/>";
        }
        $colsXml .= '</cols>';

        $lastCol = $this->colLetter($colCount);
        $lastRow = max(1, count($rows) + 1);
        $autoFilter = "<autoFilter ref=\"A1:{$lastCol}{$lastRow}\"/>";

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$colsXml
            .'<sheetData>'.implode('', $rowsXml).'</sheetData>'
            .$autoFilter
            .'</worksheet>';
    }

    protected function colLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $index = (int) (($index - $mod) / 26);
        }

        return $letter;
    }

    protected function sanitizeFormula(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) && ! is_numeric($value)) {
            return "'".$value;
        }

        return $value;
    }

    protected function xmlEscape(string $value): string
    {
        // Strip invalid XML control chars except tab, LF, CR
        $cleaned = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $value) ?? '';

        return htmlspecialchars($cleaned, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
