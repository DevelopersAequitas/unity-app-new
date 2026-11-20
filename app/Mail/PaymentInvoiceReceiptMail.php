<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentInvoiceReceiptMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $formattedAmount,
        public readonly string $invoiceNumber,
        public readonly string $pdfAbsolutePath,
        public readonly string $pdfFileName,
        public readonly ?string $invoiceDate = null,
    ) {}

    public function envelope(): Envelope
    {
        $suffix = $this->invoiceNumber !== '' ? ' ('.$this->invoiceNumber.')' : '';

        return new Envelope(
            subject: 'Payment Receipt & Invoice'.$suffix.' – Peers Global',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment_invoice_receipt',
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if ($this->pdfAbsolutePath === '' || ! file_exists($this->pdfAbsolutePath)) {
            return [];
        }

        return [
            Attachment::fromPath($this->pdfAbsolutePath)
                ->as($this->pdfFileName)
                ->withMime('application/pdf'),
        ];
    }
}
