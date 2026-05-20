<?php

namespace App\Services\Zoho;

use App\Exceptions\ZohoAuthorizationException;
use App\Models\EventRegistration;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
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
        $response = $this->sendWithAuthRetry('post', '/contacts', [
            'contact_name' => $name,
            'email' => $registration->visitor_email ?? $registration->user?->email,
            'custom_fields' => [['label' => 'registration_id', 'value' => (string) $registration->id]],
        ]);

        $customerId = data_get($response->json(), 'contact.contact_id');
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

        $response = $this->sendWithAuthRetry('post', '/invoices', [
            'customer_id' => $customerId,
            'reference_number' => (string) $registration->id,
            'line_items' => [[
                'name' => 'Event Registration',
                'rate' => (float) ($registration->payment_amount ?? $registration->amount ?? 0),
                'quantity' => 1,
            ]],
        ]);

        $invoiceId = data_get($response->json(), 'invoice.invoice_id');
        $invoiceUrl = data_get($response->json(), 'invoice.invoice_url') ?? data_get($response->json(), 'invoice.url');
        $registration->forceFill(['zoho_invoice_id' => $invoiceId, 'zoho_invoice_url' => $invoiceUrl])->save();
        Log::info('Zoho invoice created', ['registration_id' => $registration->id, 'invoice_id' => $invoiceId]);

        return ['invoice_id' => $invoiceId, 'invoice_url' => $invoiceUrl];
    }

    private function sendWithAuthRetry(string $method, string $uri, array $payload = []): Response
    {
        $response = $this->send($method, $uri, $payload, false);
        if ($response->status() === 401) {
            Log::warning('Zoho books API 401; retrying with fresh token', ['uri' => $uri]);
            $this->tokenService->forgetCachedToken();
            $response = $this->send($method, $uri, $payload, true);
        }

        if ($response->status() === 401) {
            throw new ZohoAuthorizationException('Zoho authorization failed. Please verify Zoho credentials, refresh token, scopes, and organization ID.');
        }

        $response->throw();

        return $response;
    }

    private function send(string $method, string $uri, array $payload, bool $forceRefresh): Response
    {
        $base = rtrim((string) config('services.zoho_books.base_url', 'https://www.zohoapis.in/books/v3'), '/');
        $endpoint = $base.$uri;
        $token = $this->tokenService->accessToken($forceRefresh);

        Log::info('Zoho books request', [
            'endpoint' => $endpoint,
            'organization_id' => config('services.zoho_books.organization_id'),
            'token_exists' => $token !== '',
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken '.$token,
            'Accept' => 'application/json',
        ])->send(strtoupper($method), $endpoint, [
            'query' => ['organization_id' => config('services.zoho_books.organization_id')],
            'json' => $payload,
        ]);

        Log::info('Zoho books response', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response;
    }
}
