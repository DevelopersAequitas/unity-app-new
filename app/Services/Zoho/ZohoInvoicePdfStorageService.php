<?php

declare(strict_types=1);

namespace App\Services\Zoho;

use App\Mail\PaymentInvoiceReceiptMail;
use App\Models\User;
use App\Models\ZohoInvoiceDocument;
use App\Services\WhatsApp\MetaWhatsAppService;
use App\Support\Zoho\ZohoBillingClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ZohoInvoicePdfStorageService
{
    public function __construct(
        private readonly ZohoBillingClient $client,
        private readonly MetaWhatsAppService $metaWhatsAppService,
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

    /**
     * Download and store the Zoho invoice PDF in zoho_invoice_documents,
     * then send it via WhatsApp using the approved payment_invoice_receipt template.
     *
     * Never throws so payment finalization is never disrupted.
     *
     * @param  array<string, mixed>  $meta
     */
    public function fetchStoreAndSendWhatsApp(string $zohoInvoiceId, ?User $user = null, array $meta = []): ?ZohoInvoiceDocument
    {
        $zohoInvoiceId = trim($zohoInvoiceId);
        if ($zohoInvoiceId === '') {
            return null;
        }

        if (! Schema::hasTable('zoho_invoice_documents')) {
            Log::warning('zoho_invoice_documents_table_missing', [
                'zoho_invoice_id' => $zohoInvoiceId,
            ]);

            return null;
        }

        try {
            if ($user && empty($meta['user_id'])) {
                $meta['user_id'] = (string) $user->id;
            }

            $document = $this->fetchAndStore($zohoInvoiceId, $meta);

            $document = $this->sendInvoiceWhatsApp($document, $user, $meta);

            $this->sendInvoiceEmail($document, $user, $meta);

            return $document;
        } catch (Throwable $e) {
            Log::warning('zoho_invoice_pdf_store_or_whatsapp_failed', [
                'zoho_invoice_id' => $zohoInvoiceId,
                'user_id' => $user?->id ?? ($meta['user_id'] ?? null),
                'payment_type' => $meta['payment_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send an already-stored ZohoInvoiceDocument PDF via Meta WhatsApp Cloud API
     * using the payment_invoice_receipt (en_IN) template.
     *
     * @param  array<string, mixed>  $meta
     */
    public function sendInvoiceWhatsApp(ZohoInvoiceDocument $document, ?User $user = null, array $meta = []): ZohoInvoiceDocument
    {
        if (! (bool) Config::get('whatsapp.invoice_receipt.enabled', true)) {
            return $document;
        }

        if ($document->whatsapp_status === 'sent' && ! empty($document->whatsapp_message_id)) {
            return $document;
        }

        if (! $user && ! empty($document->user_id)) {
            $user = User::query()->find($document->user_id);
        }

        $recipientPhone = trim((string) ($meta['recipient_phone'] ?? $user?->phone ?? ''));
        if ($recipientPhone === '') {
            $document->forceFill([
                'whatsapp_status' => 'skipped',
                'whatsapp_error' => 'Recipient phone number is missing.',
            ])->save();

            Log::warning('zoho_invoice_whatsapp_skipped_no_phone', [
                'document_id' => (string) $document->id,
                'zoho_invoice_id' => $document->zoho_invoice_id,
                'user_id' => $document->user_id,
            ]);

            return $document;
        }

        $recipientName = trim((string) ($meta['recipient_name'] ?? ''));
        if ($recipientName === '' && $user) {
            $recipientName = trim((string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))));
            if ($recipientName === '') {
                $recipientName = trim((string) ($user->company_name ?? ''));
            }
        }
        if ($recipientName === '') {
            $recipientName = 'Member';
        }

        $formattedAmount = $this->formatAmount((float) $document->amount, (string) ($document->currency ?: 'INR'));
        $templateName = (string) Config::get('whatsapp.invoice_receipt.template_name', 'payment_invoice_receipt');
        $languageCode = (string) Config::get('whatsapp.invoice_receipt.language', 'en_IN');

        $disk = (string) ($document->pdf_disk ?: 'public');
        $pdfPath = (string) ($document->pdf_path ?? '');
        $absolutePath = $pdfPath !== '' ? Storage::disk($disk)->path($pdfPath) : '';

        if ($absolutePath === '' || ! file_exists($absolutePath)) {
            $document->forceFill([
                'whatsapp_status' => 'failed',
                'whatsapp_error' => 'Stored PDF file not found on disk.',
            ])->save();

            return $document;
        }

        $fileName = basename($pdfPath);
        if ($fileName === '' || ! str_ends_with(strtolower($fileName), '.pdf')) {
            $base = trim((string) ($document->zoho_invoice_number ?: $document->zoho_invoice_id));
            $fileName = ($base !== '' ? $base : 'Invoice').'.pdf';
        }

        try {
            $mediaId = $this->metaWhatsAppService->uploadMediaFromPath($absolutePath);

            $components = [
                [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => 'document',
                            'document' => [
                                'id' => $mediaId,
                                'filename' => $fileName,
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $recipientName,
                        ],
                        [
                            'type' => 'text',
                            'text' => $formattedAmount,
                        ],
                    ],
                ],
            ];

            $result = $this->metaWhatsAppService->sendTemplateMessage(
                recipientPhone: $recipientPhone,
                templateName: $templateName,
                languageCode: $languageCode,
                components: $components,
                userId: $user ? (string) $user->id : ($document->user_id ? (string) $document->user_id : null),
            );

            $document->forceFill([
                'whatsapp_status' => 'sent',
                'whatsapp_media_id' => $mediaId,
                'whatsapp_message_id' => $result['meta_message_id'] ?? $result['whatsapp_message_id'] ?? null,
                'whatsapp_sent_at' => now(),
                'whatsapp_error' => null,
            ])->save();

            Log::info('zoho_invoice_whatsapp_sent', [
                'document_id' => (string) $document->id,
                'zoho_invoice_id' => $document->zoho_invoice_id,
                'zoho_invoice_number' => $document->zoho_invoice_number,
                'payment_type' => $document->payment_type,
                'recipient_phone' => $recipientPhone,
                'template_name' => $templateName,
                'whatsapp_media_id' => $mediaId,
                'whatsapp_message_id' => $document->whatsapp_message_id,
            ]);
        } catch (Throwable $e) {
            $document->forceFill([
                'whatsapp_status' => 'failed',
                'whatsapp_error' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();

            Log::error('zoho_invoice_whatsapp_send_failed', [
                'document_id' => (string) $document->id,
                'zoho_invoice_id' => $document->zoho_invoice_id,
                'payment_type' => $document->payment_type,
                'recipient_phone' => $recipientPhone,
                'error' => $e->getMessage(),
            ]);
        }

        return $document->fresh() ?? $document;
    }

    /**
     * Send an already-stored ZohoInvoiceDocument PDF via email with the PDF attached.
     * Works across all payment flows (membership, circle, event, brand partner).
     * Never throws so payment and WhatsApp flows are never disrupted.
     *
     * @param  array<string, mixed>  $meta
     */
    public function sendInvoiceEmail(ZohoInvoiceDocument $document, ?User $user = null, array $meta = []): bool
    {
        if (! $user && ! empty($document->user_id)) {
            $user = User::query()->find($document->user_id);
        }

        $recipientEmail = trim((string) ($meta['recipient_email'] ?? $user?->email ?? ''));
        if ($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            Log::warning('zoho_invoice_email_skipped_invalid_email', [
                'document_id' => (string) $document->id,
                'zoho_invoice_id' => $document->zoho_invoice_id,
                'user_id' => $document->user_id,
            ]);

            return false;
        }

        $forceEmail = (bool) ($meta['force_email'] ?? false);
        $cacheKey = 'zoho_invoice_email_sent:'.$document->zoho_invoice_id.':'.strtolower($recipientEmail);
        if (! $forceEmail && ! Cache::add($cacheKey, true, now()->addDays(30))) {
            return true;
        }

        $disk = (string) ($document->pdf_disk ?: 'public');
        $pdfPath = (string) ($document->pdf_path ?? '');
        $absolutePath = $pdfPath !== '' ? Storage::disk($disk)->path($pdfPath) : '';

        if ($absolutePath === '' || ! file_exists($absolutePath)) {
            if (! $forceEmail) {
                Cache::forget($cacheKey);
            }
            Log::warning('zoho_invoice_email_skipped_missing_pdf', [
                'document_id' => (string) $document->id,
                'zoho_invoice_id' => $document->zoho_invoice_id,
                'pdf_path' => $pdfPath,
            ]);

            return false;
        }

        $recipientName = trim((string) ($meta['recipient_name'] ?? ''));
        if ($recipientName === '' && $user) {
            $recipientName = trim((string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))));
            if ($recipientName === '') {
                $recipientName = trim((string) ($user->company_name ?? ''));
            }
        }
        if ($recipientName === '') {
            $recipientName = 'Member';
        }

        $invoiceNumber = trim((string) ($document->zoho_invoice_number ?: $document->zoho_invoice_id));
        $fileName = basename($pdfPath);
        if ($fileName === '' || ! str_ends_with(strtolower($fileName), '.pdf')) {
            $fileName = ($invoiceNumber !== '' ? $invoiceNumber : 'Invoice').'.pdf';
        }

        $formattedAmount = $this->formatAmount((float) $document->amount, (string) ($document->currency ?: 'INR'));
        $invoiceDate = ($document->created_at ?? now())->timezone('Asia/Kolkata')->format('d M Y');

        try {
            Mail::to($recipientEmail)->send(new PaymentInvoiceReceiptMail(
                recipientName: $recipientName,
                formattedAmount: $formattedAmount,
                invoiceNumber: $invoiceNumber,
                pdfAbsolutePath: $absolutePath,
                pdfFileName: $fileName,
                invoiceDate: $invoiceDate,
            ));

            Log::info('zoho_invoice_email_sent', [
                'document_id' => (string) $document->id,
                'zoho_invoice_id' => $document->zoho_invoice_id,
                'zoho_invoice_number' => $invoiceNumber,
                'payment_type' => $document->payment_type,
                'recipient_email' => $recipientEmail,
                'pdf_file_name' => $fileName,
            ]);

            return true;
        } catch (Throwable $e) {
            if (! $forceEmail) {
                Cache::forget($cacheKey);
            }

            Log::error('zoho_invoice_email_send_failed', [
                'document_id' => (string) $document->id,
                'zoho_invoice_id' => $document->zoho_invoice_id,
                'payment_type' => $document->payment_type,
                'recipient_email' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function formatAmount(float $amount, string $currency): string
    {
        $code = strtoupper(trim($currency));
        $symbol = match ($code) {
            'INR', '' => '₹',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $code.' ',
        };

        return $symbol.number_format($amount, 2);
    }
}
