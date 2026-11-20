<?php

declare(strict_types=1);

namespace App\Services\Zoho;

use App\Models\ZohoInvoiceDocument;
use App\Support\Zoho\ZohoBillingClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ZohoInvoicePdfStorageService
{
    public function __construct(
        private readonly ZohoBillingClient $client,
    ) {}

    /**
     * Download an invoice PDF from Zoho Billing, store it locally on the configured disk,
     * and persist its mapping in the zoho_invoice_documents table.
     *
     * @param  array<string, mixed>  $meta
     */
    public function fetchAndStore(string $zohoInvoiceId, array $meta = []): ZohoInvoiceDocument
    {
        $zohoInvoiceId = trim($zohoInvoiceId);
        if ($zohoInvoiceId === '') {
            throw new RuntimeException('Zoho invoice ID is required to fetch PDF.');
        }

        // Fetch invoice metadata from Zoho if not fully provided
        $invoiceNumber = trim((string) ($meta['zoho_invoice_number'] ?? ''));
        $zohoInvoiceUrl = $meta['zoho_invoice_url'] ?? null;
        $zohoCustomerId = $meta['zoho_customer_id'] ?? null;
        $gstNumber = $meta['gst_number'] ?? null;
        $gstTreatment = $meta['gst_treatment'] ?? null;
        $amount = $meta['amount'] ?? null;
        $currency = $meta['currency'] ?? null;

        if ($invoiceNumber === '' || $zohoInvoiceUrl === null) {
            $invoiceResp = $this->client->request('GET', '/invoices/'.$zohoInvoiceId);
            $inv = is_array($invoiceResp['invoice'] ?? null) ? $invoiceResp['invoice'] : $invoiceResp;

            $invoiceNumber = $invoiceNumber !== '' ? $invoiceNumber : (string) ($inv['invoice_number'] ?? $zohoInvoiceId);
            $zohoInvoiceUrl = $zohoInvoiceUrl ?: trim((string) ($inv['invoice_url'] ?? $inv['url'] ?? ''));
            $zohoCustomerId = $zohoCustomerId ?: (string) ($inv['customer_id'] ?? '');
            $gstNumber = $gstNumber !== null ? $gstNumber : (trim((string) ($inv['gst_no'] ?? '')) ?: null);
            $gstTreatment = $gstTreatment ?: (string) ($inv['gst_treatment'] ?? '');
            $amount = $amount !== null ? (float) $amount : (float) ($inv['total'] ?? 0);
            $currency = $currency ?: (string) ($inv['currency_code'] ?? 'INR');
        }

        // Download actual PDF bytes from Zoho Billing
        $pdfResp = $this->client->requestPdf('/invoices/'.$zohoInvoiceId, ['accept' => 'pdf']);
        $pdfBytes = (string) ($pdfResp['content'] ?? '');

        if ($pdfBytes === '' || ! str_starts_with($pdfBytes, '%PDF-')) {
            throw new RuntimeException('Zoho Billing did not return a valid PDF document for invoice '.$zohoInvoiceId.'.');
        }

        $safeFileName = Str::of($invoiceNumber !== '' ? $invoiceNumber : $zohoInvoiceId)
            ->replaceMatches('/[^A-Za-z0-9\\-_]/', '-')
            ->trim('-')
            ->toString().'.pdf';

        $disk = (string) ($meta['pdf_disk'] ?? 'public');
        $relativePath = 'invoices/'.now()->format('Y/m').'/'.$safeFileName;

        Storage::disk($disk)->put($relativePath, $pdfBytes);

        $pdfUrl = Storage::disk($disk)->url($relativePath);
        $sizeBytes = strlen($pdfBytes);
        $sha256 = hash('sha256', $pdfBytes);

        $document = ZohoInvoiceDocument::query()->updateOrCreate(
            ['zoho_invoice_id' => $zohoInvoiceId],
            [
                'user_id' => $meta['user_id'] ?? null,
                'payment_type' => $meta['payment_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
                'zoho_customer_id' => $zohoCustomerId ?: null,
                'zoho_invoice_number' => $invoiceNumber ?: null,
                'zoho_invoice_url' => $zohoInvoiceUrl ?: null,
                'gst_number' => $gstNumber ?: null,
                'gst_treatment' => $gstTreatment ?: null,
                'amount' => $amount !== null ? round((float) $amount, 2) : 0,
                'currency' => strtoupper((string) ($currency ?: 'INR')),
                'pdf_disk' => $disk,
                'pdf_path' => $relativePath,
                'pdf_url' => $pdfUrl,
                'pdf_size_bytes' => $sizeBytes,
                'pdf_sha256' => $sha256,
            ]
        );

        Log::info('zoho_invoice_pdf_stored', [
            'document_id' => (string) $document->id,
            'zoho_invoice_id' => $zohoInvoiceId,
            'zoho_invoice_number' => $invoiceNumber,
            'pdf_path' => $relativePath,
            'pdf_size_bytes' => $sizeBytes,
        ]);

        return $document;
    }
}
