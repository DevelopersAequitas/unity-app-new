<?php

namespace App\Support\Zoho;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ZohoBillingClient
{
    public function __construct(private readonly ZohoBillingTokenService $tokenService)
    {
    }

    public function request(string $method, string $path, array $data = [], array $query = []): array
    {
        $baseUrl = rtrim((string) config('zoho_billing.base_url'), '/');
        $normalizedPath = ltrim($path, '/');
        if (str_starts_with($normalizedPath, 'billing/v1/')) {
            $normalizedPath = substr($normalizedPath, strlen('billing/v1/'));
        }
        $url = $baseUrl . '/' . $normalizedPath;

        $request = Http::timeout((int) config('zoho_billing.timeout_seconds', 20))
            ->retry(
                (int) config('zoho_billing.retry_times', 2),
                (int) config('zoho_billing.retry_sleep_milliseconds', 200)
            )
            ->withToken($this->tokenService->getAccessToken(), 'Zoho-oauthtoken')
            ->withHeaders([
                'X-com-zoho-subscriptions-organizationid' => (string) config('zoho_billing.organization_id'),
                'Accept' => 'application/json',
            ])
            ->withQueryParameters($query);

        $method = strtolower($method);

        Log::info('Zoho Billing request', [
            'method' => strtoupper($method),
            'path' => $path,
            'final_url' => $url,
            'query' => $query,
            'has_body' => $data !== [],
        ]);

        $response = match ($method) {
            'get' => $request->get($url),
            'post' => $request->post($url, $data),
            'put' => $request->put($url, $data),
            'patch' => $request->patch($url, $data),
            'delete' => $request->delete($url, $data),
            default => throw new RuntimeException("Unsupported Zoho HTTP method: {$method}"),
        };

        return $this->handleResponse($response, $method, $path);
    }

    private function handleResponse(Response $response, string $method, string $path): array
    {
        $json = $response->json();

        if ($response->successful() && is_array($json)) {
            return $json;
        }

        $zohoCode = (string) ($json['code'] ?? $json['error'] ?? 'unknown_error');
        $zohoMessage = (string) ($json['message'] ?? $json['error_description'] ?? 'Zoho API request failed.');

        Log::error('Zoho Billing API request failed', [
            'status' => $response->status(),
            'method' => strtoupper($method),
            'path' => $path,
            'code' => $zohoCode,
            'message' => $zohoMessage,
            'response' => $json,
        ]);

        $exceptionMessage = sprintf(
            'Zoho API request failed. status=%d code=%s message=%s body=%s',
            $response->status(),
            $zohoCode,
            $zohoMessage,
            json_encode($json, JSON_UNESCAPED_SLASHES)
        );

        throw new ZohoBillingException($exceptionMessage, $zohoCode, $response->status(), $json);
    }
}

class ZohoBillingException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $zohoCode = 'unknown_error',
        private readonly int $statusCode = 500,
        private readonly array $payload = []
    ) {
        parent::__construct($message);
    }

    public function zohoCode(): string
    {
        return $this->zohoCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function payload(): array
    {
        return $this->payload;
    }
}
