<?php

namespace App\Services\Zoho;

use App\Models\EventRegistration;
use Illuminate\Support\Facades\Log;

class ZohoBooksService
{
    public function __construct(private readonly ZohoTokenService $tokenService) {}

    public function createOrUpdateCustomer(EventRegistration $registration): ?string
    {
        if ($registration->zoho_customer_id) {
            return $registration->zoho_customer_id;
        }

        $name = $registration->visitor_name ?? $registration->user?->name ?? 'Event Customer';
        $customer = $this->client()->post('/contacts', ['contact_name' => $name, 'email' => $registration->visitor_email ?? $registration->user?->email, 'custom_fields' => [['label' => 'registration_id', 'value' => (string) $registration->id]]])->throw()->json();
        $customerId = data_get($customer, 'contact.contact_id');

        if ($customerId) {
            $registration->forceFill(['zoho_customer_id' => $customerId])->save();
        }

        return $customerId;
    }

    public function createPaidInvoiceForEventRegistration(EventRegistration $registration): ?array
    {
        if ($registration->zoho_invoice_id) {
            return ['invoice_id' => $registration->zoho_invoice_id, 'invoice_url' => $registration->zoho_invoice_url];
        }

        $customerId = $this->createOrUpdateCustomer($registration);
        if (! $customerId) {
            return null;
        }

        $invoice = $this->client()->post('/invoices', [
            'customer_id' => $customerId,
            'reference_number' => (string) $registration->id,
            'line_items' => [[
                'name' => 'Event Registration',
                'rate' => (float) ($registration->payment_amount ?? $registration->amount ?? 0),
                'quantity' => 1,
            ]],
        ])->throw()->json();

        $invoiceId = data_get($invoice, 'invoice.invoice_id');
        $invoiceUrl = data_get($invoice, 'invoice.invoice_url') ?? data_get($invoice, 'invoice.url');
        $registration->forceFill(['zoho_invoice_id' => $invoiceId, 'zoho_invoice_url' => $invoiceUrl])->save();
        Log::info('Zoho invoice created', ['registration_id' => $registration->id, 'invoice_id' => $invoiceId]);

        return ['invoice_id' => $invoiceId, 'invoice_url' => $invoiceUrl];
    }

    private function client()
    {
        return \Illuminate\Support\Facades\Http::withToken($this->tokenService->accessToken())
            ->acceptJson()
            ->baseUrl((string) config('services.zoho_books.base_url'))
            ->withQueryParameters(['organization_id' => config('services.zoho_books.organization_id')]);
    }
}
