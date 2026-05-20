<?php

namespace App\Services\Zoho;

use App\Exceptions\ZohoAuthorizationException;
use App\Models\EventRegistration;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $response = $this->sendWithAuthRetry('post', (string) config('services.zoho_payments.payment_link_endpoint', '/api/v1/payment_links'), $payload);
        $body = $response->json();

        return [
            'payment_url' => data_get($body, 'payment_link.url') ?? data_get($body, 'url'),
            'payment_link_id' => data_get($body, 'payment_link.payment_link_id') ?? data_get($body, 'payment_link_id'),
            'session_id' => data_get($body, 'payment_link.session_id') ?? data_get($body, 'session_id'),
            'raw' => $body,
        ];
    }

    public function verifyPaymentSession(string $sessionId): array
    {
        $sessionPath = rtrim((string) config('services.zoho_payments.payment_session_endpoint', '/api/v1/payment_sessions'), '/').'/'.$sessionId;
        $response = $this->sendWithAuthRetry('get', $sessionPath);

        return $response->json();
    }

    private function sendWithAuthRetry(string $method, string $uri, array $payload = []): Response
    {
        $response = $this->send($method, $uri, $payload, false);
        if (in_array($response->status(), [401, 404], true)) {
            Log::warning('Zoho payment API 401; retrying with fresh token', ['uri' => $uri]);
            $this->tokenService->forgetCachedToken();
            $response = $this->send($method, $uri, $payload, true);
        }

        if (in_array($response->status(), [401, 404], true)) {
            throw new ZohoAuthorizationException('Zoho authorization failed. Please verify Zoho credentials, refresh token, scopes, and organization ID.');
        }

        $response->throw();

        return $response;
    }

    private function send(string $method, string $uri, array $payload = [], bool $forceRefresh = false): Response
    {
        $uri = str_starts_with($uri, 'http') ? $uri : '/'.ltrim($uri, '/');
        $endpoint = str_starts_with($uri, 'http') ? $uri : rtrim((string) config('services.zoho_payments.base_url', 'https://payments.zoho.in'), '/').$uri;
        $token = $this->tokenService->accessToken($forceRefresh);

        Log::info('Zoho payment request', [
            'endpoint' => $endpoint,
            'organization_id' => config('services.zoho_books.organization_id'),
            'token_exists' => $token !== '',
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken '.$token,
            'Accept' => 'application/json',
        ])->send(strtoupper($method), $endpoint, [
            'json' => $payload,
        ]);

        Log::info('Zoho payment response', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response;
    }
}
