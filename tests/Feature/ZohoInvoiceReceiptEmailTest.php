<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\PaymentInvoiceReceiptMail;
use App\Models\ZohoInvoiceDocument;
use App\Services\Zoho\ZohoInvoicePdfStorageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ZohoInvoiceReceiptEmailTest extends TestCase
{
    public function test_send_invoice_email_sends_common_receipt_with_pdf_attachment(): void
    {
        Mail::fake();
        Storage::fake('public');

        $pdfPath = 'invoices/2026/11/INV-TEST-001.pdf';
        Storage::disk('public')->put($pdfPath, "%PDF-1.4\n% Dummy invoice PDF content\n%%EOF");

        $document = new ZohoInvoiceDocument([
            'id' => (string) Str::uuid(),
            'payment_type' => 'circle_join',
            'zoho_invoice_id' => 'zoho_inv_test_'.Str::random(8),
            'zoho_invoice_number' => 'INV-TEST-001',
            'amount' => 1.00,
            'currency' => 'INR',
            'pdf_disk' => 'public',
            'pdf_path' => $pdfPath,
        ]);

        Cache::flush();

        $service = app(ZohoInvoicePdfStorageService::class);
        $sent = $service->sendInvoiceEmail($document, null, [
            'recipient_email' => 'jkb982493@gmail.com',
            'recipient_name' => 'Jay Kanjariya',
        ]);

        $this->assertTrue($sent);

        Mail::assertSent(PaymentInvoiceReceiptMail::class, function (PaymentInvoiceReceiptMail $mail): bool {
            $attachments = $mail->attachments();

            return $mail->hasTo('jkb982493@gmail.com')
                && $mail->recipientName === 'Jay Kanjariya'
                && $mail->formattedAmount === '₹1.00'
                && $mail->invoiceNumber === 'INV-TEST-001'
                && $mail->pdfFileName === 'INV-TEST-001.pdf'
                && count($attachments) === 1;
        });

        // Calling a second time for the same invoice + recipient is idempotent (no duplicate email)
        $sentAgain = $service->sendInvoiceEmail($document, null, [
            'recipient_email' => 'jkb982493@gmail.com',
            'recipient_name' => 'Jay Kanjariya',
        ]);

        $this->assertTrue($sentAgain);
        Mail::assertSentCount(1);
    }
}
