<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppCampaignCsvImportService
{
    /**
     * Parse and validate an uploaded CSV file.
     *
     * @param  array<string, string>  $headerMapping  Optional column aliases (e.g. ['phone' => 'mobile_number'])
     * @return array{
     *     import_id: string,
     *     total_rows: int,
     *     valid_count: int,
     *     invalid_count: int,
     *     duplicate_count: int,
     *     headers: list<string>,
     *     sample_valid_recipients: list<array<string, mixed>>,
     *     errors: list<array<string, mixed>>,
     *     storage_path: string
     * }
     */
    public function parseAndValidate(UploadedFile $file, array $headerMapping = []): array
    {
        $importId = (string) Str::uuid();
        $filePath = $file->getRealPath();

        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new \InvalidArgumentException('Uploaded CSV file could not be read.');
        }

        $rawBytes = file_get_contents($filePath);
        if ($rawBytes === false || strlen($rawBytes) === 0) {
            throw new \InvalidArgumentException('Uploaded CSV file is empty or could not be read.');
        }

        // Remove UTF-8 BOM if present
        if (str_starts_with($rawBytes, "\xEF\xBB\xBF")) {
            $rawBytes = substr($rawBytes, 3);
        }

        // Detect and convert non-UTF8 encodings (like Windows-1252 / ISO-8859-1 commonly produced by Excel)
        if (! mb_check_encoding($rawBytes, 'UTF-8')) {
            $encoding = mb_detect_encoding($rawBytes, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true) ?: 'Windows-1252';
            $rawBytes = mb_convert_encoding($rawBytes, 'UTF-8', $encoding);
        }

        // Sanitize any remaining malformed UTF-8 byte sequences
        $cleanContent = mb_convert_encoding($rawBytes, 'UTF-8', 'UTF-8');

        // Store a persistent copy in private storage
        $storedName = "whatsapp_bulk_imports/{$importId}.csv";
        Storage::disk('local')->put($storedName, $cleanContent);

        // Open in-memory stream for parsing
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \InvalidArgumentException('Failed to open CSV parsing stream.');
        }
        fwrite($handle, $cleanContent);
        rewind($handle);

        $rawHeaders = fgetcsv($handle);
        if (! $rawHeaders || empty(array_filter($rawHeaders))) {
            fclose($handle);
            throw new \InvalidArgumentException('CSV file is empty or does not contain a valid header row.');
        }

        // Clean headers: lower, trim, remove non-printable characters
        $headers = [];
        foreach ($rawHeaders as $idx => $header) {
            $cleaned = trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', (string) $header));
            $headers[$idx] = strtolower($cleaned);
        }

        // Auto-detect name and phone headers if not explicitly mapped
        $nameIndex = $this->findHeaderIndex($headers, ['name', 'full_name', 'fullname', 'recipient_name', 'contact_name', 'customer_name'], $headerMapping['name'] ?? null);
        $phoneIndex = $this->findHeaderIndex($headers, ['phone', 'mobile', 'mobile_number', 'phone_number', 'contact', 'contact_no', 'whatsapp_number', 'phone_no'], $headerMapping['phone'] ?? null);

        if ($nameIndex === null || $phoneIndex === null) {
            fclose($handle);
            throw new \InvalidArgumentException('CSV must contain columns for recipient name and phone number. Found headers: ['.implode(', ', $headers).'].');
        }

        $totalRows = 0;
        $validRecipients = [];
        $errors = [];
        $seenPhones = [];
        $duplicateCount = 0;

        $rowNumber = 1; // Header is row 1
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Ensure every string cell in the row is clean UTF-8
            $row = array_map(function ($val) {
                if ($val === null) {
                    return '';
                }

                return mb_convert_encoding(trim((string) $val), 'UTF-8', 'UTF-8');
            }, $row);

            // Skip completely empty lines
            if (empty(array_filter($row, fn ($val) => $val !== ''))) {
                continue;
            }

            $totalRows++;

            // Map row to headers
            $rowData = [];
            foreach ($headers as $colIdx => $colName) {
                $val = isset($row[$colIdx]) ? $row[$colIdx] : '';
                $rowData[$colName] = $this->sanitizeForCsvInjection($val);
            }

            $rawName = trim((string) ($row[$nameIndex] ?? ''));
            $rawPhone = trim((string) ($row[$phoneIndex] ?? ''));

            $rowErrors = [];

            if ($rawName === '') {
                $rowErrors[] = 'Recipient name is missing.';
            }

            if ($rawPhone === '') {
                $rowErrors[] = 'Recipient phone number is missing.';
            }

            $normalizedPhone = null;
            if ($rawPhone !== '') {
                $phoneValidation = $this->normalizePhone($rawPhone);
                if (! $phoneValidation['valid']) {
                    $rowErrors[] = $phoneValidation['error'] ?? 'Invalid phone number format.';
                } else {
                    $normalizedPhone = $phoneValidation['phone'];
                }
            }

            // Check duplicate phone in same CSV
            if ($normalizedPhone !== null) {
                if (isset($seenPhones[$normalizedPhone])) {
                    $duplicateCount++;
                    $rowErrors[] = "Duplicate phone number ({$normalizedPhone}) already seen on row {$seenPhones[$normalizedPhone]}.";
                } else {
                    $seenPhones[$normalizedPhone] = $rowNumber;
                }
            }

            if (! empty($rowErrors)) {
                $errors[] = [
                    'row_number' => $rowNumber,
                    'raw_name' => $rawName,
                    'raw_phone' => $rawPhone,
                    'raw_data' => $rowData,
                    'error_message' => implode(' | ', $rowErrors),
                ];
            } else {
                $validRecipients[] = [
                    'csv_row_number' => $rowNumber,
                    'name' => $rawName,
                    'phone' => $normalizedPhone,
                    'original_phone' => $rawPhone,
                    'custom_data' => $rowData,
                ];
            }
        }

        fclose($handle);

        // Store parsed valid and invalid rows into JSON cache/storage for the wizard steps
        $cacheData = [
            'import_id' => $importId,
            'storage_path' => $storedName,
            'headers' => array_values($headers),
            'total_rows' => $totalRows,
            'valid_count' => count($validRecipients),
            'invalid_count' => count($errors),
            'duplicate_count' => $duplicateCount,
            'valid_recipients' => $validRecipients,
            'errors' => $errors,
        ];

        Storage::disk('local')->put("whatsapp_bulk_imports/{$importId}_parsed.json", json_encode($cacheData, JSON_THROW_ON_ERROR));

        return [
            'import_id' => $importId,
            'total_rows' => $totalRows,
            'valid_count' => count($validRecipients),
            'invalid_count' => count($errors),
            'duplicate_count' => $duplicateCount,
            'headers' => array_values($headers),
            'sample_valid_recipients' => array_slice($validRecipients, 0, 5),
            'errors' => array_slice($errors, 0, 20),
            'storage_path' => $storedName,
        ];
    }

    /**
     * Retrieve previously parsed import data from local cache storage.
     *
     * @return array<string, mixed>|null
     */
    public function getImportData(string $importId): ?array
    {
        $path = "whatsapp_bulk_imports/{$importId}_parsed.json";
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        $content = Storage::disk('local')->get($path);
        if (! $content) {
            return null;
        }

        return json_decode($content, true);
    }

    /**
     * Generate downloadable sample CSV content.
     */
    public function generateSampleCsv(): string
    {
        $headers = ['name', 'phone', 'company', 'city', 'email', 'custom_ref'];
        $rows = [
            ['Amit Patel', '919876543210', 'Peers Global', 'Ahmedabad', 'amit@peersglobal.com', 'REF-001'],
            ['Neha Shah', '+919876543211', 'ABC Industries', 'Surat', 'neha@example.com', 'REF-002'],
            ['Rajesh Kumar', '9876543212', 'XYZ Corp', 'Mumbai', 'rajesh@example.com', 'REF-003'],
            ['Priya Sharma', '+919876543213', 'Sharma & Co', 'Delhi', 'priya@example.com', 'REF-004'],
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return (string) $csv;
    }

    /**
     * Generate CSV export for import error rows.
     *
     * @param  list<array<string, mixed>>  $errors
     */
    public function generateErrorReportCsv(array $errors): string
    {
        $headers = ['Row Number', 'Name', 'Phone', 'Error Details'];
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);

        foreach ($errors as $err) {
            $rowNum = (string) ($err['row_number'] ?? '');
            $name = (string) ($err['raw_name'] ?? ($err['raw_data']['name'] ?? ''));
            $phone = (string) ($err['raw_phone'] ?? ($err['raw_data']['phone'] ?? ''));
            $msg = (string) ($err['error_message'] ?? 'Validation failed');

            fputcsv($output, [
                $this->sanitizeForCsvInjection($rowNum),
                $this->sanitizeForCsvInjection($name),
                $this->sanitizeForCsvInjection($phone),
                $this->sanitizeForCsvInjection($msg),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return (string) $csv;
    }

    /**
     * Normalize international phone numbers for WhatsApp Cloud API.
     * Meta requires digits only with international country code, no leading + or zeros.
     *
     * @return array{valid: bool, phone: ?string, error: ?string}
     */
    public function normalizePhone(string $phone): array
    {
        $raw = trim($phone);
        if ($raw === '') {
            return ['valid' => false, 'phone' => null, 'error' => 'Phone number is empty.'];
        }

        // Remove non-digit characters except leading +
        $hasLeadingPlus = str_starts_with($raw, '+');
        $digitsOnly = preg_replace('/[^0-9]/', '', $raw);

        if (empty($digitsOnly)) {
            return ['valid' => false, 'phone' => null, 'error' => 'Phone number contains no numeric digits.'];
        }

        // Remove leading international access prefixes like '00'
        if (str_starts_with($digitsOnly, '00')) {
            $digitsOnly = substr($digitsOnly, 2);
        }

        // 8 digits starting with 2, 3, 4, 5, 6, 7 (e.g. Ahmedabad / local landline without STD code)
        if (strlen($digitsOnly) === 8 && in_array($digitsOnly[0], ['2', '3', '4', '5', '6', '7'], true)) {
            $digitsOnly = '9179'.$digitsOnly;
        }

        // Indian mobile standard handling (10 digits starting with 6, 7, 8, 9)
        if (strlen($digitsOnly) === 10 && preg_match('/^[6-9]/', $digitsOnly)) {
            $digitsOnly = '91'.$digitsOnly;
        } elseif (strlen($digitsOnly) === 10 && preg_match('/^[2-5]/', $digitsOnly)) {
            // 10-digit landline with STD code (e.g. 2717230324)
            $digitsOnly = '91'.$digitsOnly;
        } elseif (strlen($digitsOnly) === 11 && str_starts_with($digitsOnly, '0')) {
            // Leading 0 + 10 digits
            $withoutZero = substr($digitsOnly, 1);
            if (strlen($withoutZero) === 10) {
                $digitsOnly = '91'.$withoutZero;
            }
        }

        // Validate international length (minimum 10 digits with country code, maximum 15 digits as per E.164)
        $len = strlen($digitsOnly);
        if ($len < 10 || $len > 15) {
            return [
                'valid' => false,
                'phone' => null,
                'error' => "Phone number '{$phone}' has invalid length ({$len} digits). Must be between 10 and 15 digits including country code.",
            ];
        }

        return [
            'valid' => true,
            'phone' => $digitsOnly,
            'error' => null,
        ];
    }

    /**
     * Sanitize values to prevent spreadsheet formula injection.
     */
    public function sanitizeForCsvInjection(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$trimmed;
        }

        return $value;
    }

    /**
     * Find header index from candidates or custom alias.
     *
     * @param  array<int, string>  $headers
     * @param  list<string>  $candidates
     */
    private function findHeaderIndex(array $headers, array $candidates, ?string $customAlias = null): ?int
    {
        if ($customAlias !== null && $customAlias !== '') {
            $idx = array_search(strtolower(trim($customAlias)), $headers, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }

        foreach ($candidates as $candidate) {
            $idx = array_search(strtolower($candidate), $headers, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }

        return null;
    }
}
