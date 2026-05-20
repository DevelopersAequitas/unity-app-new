<?php

namespace App\Services\Zoho;

use App\Models\EventRegistration;
use Illuminate\Support\Facades\Http;

class ZohoPaymentService
{
    public function __construct(private readonly ZohoTokenService $tokenService) {}

    public function createPaymentForEventRegistration(EventRegistration $registration): array
    {
        $payload = [
            'amount' => (float) ($registration->payment_amount ?? $registration->amount ?? 0),
            'currency' => $registration->payment_currency ?? $registration->currency ?? config('services.zoho_payments.currency', 'INR'),
            'description' => 'Event registration payment',
            'reference_id' => (string) $registration->id,
            'success_url' => config('services.zoho_payments.success_url'),
            'failure_url' => config('services.zoho_payments.failure_url'),
            'customer' => [
                'name' => $registration->visitor_name ?? $registration->user?->name,
                'email' => $registration->visitor_email ?? $registration->user?->email,
            ],
        ];

        $response = $this->client()->post('/payment_links', $payload)->throw()->json();

        return [
            'payment_url' => data_get($response, 'payment_link.url') ?? data_get($response, 'url'),
            'payment_link_id' => data_get($response, 'payment_link.payment_link_id') ?? data_get($response, 'payment_link_id'),
            'session_id' => data_get($response, 'payment_link.session_id') ?? data_get($response, 'session_id'),
            'raw' => $response,
        ];
    }

    public function verifyPaymentSession(string $sessionId): array
    {
        return $this->client()->get('/payment_sessions/'.$sessionId)->throw()->json();
    }

    private function client()
    {
        return Http::withToken($this->tokenService->accessToken())
            ->acceptJson()
            ->baseUrl((string) config('services.zoho_payments.api_base_url'));
    }
}
