<?php

namespace App\Support\Zoho;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ZohoBillingTokenService
{
    public function getAccessToken(): string
    {
        $cachedToken = Cache::get('zoho_billing_access_token');

        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        $tokenData = $this->refreshAccessToken();
        $ttl = max(((int) ($tokenData['expires_in'] ?? 3600)) - 120, 60);

        Cache::put('zoho_billing_access_token', $tokenData['access_token'], now()->addSeconds($ttl));

        return $tokenData['access_token'];
    }

    public function refreshAccessToken(): array
    {
        $url = config('zoho_billing.oauth_token_url');
        $refreshToken = config('zoho_billing.refresh_token');
        $clientId = config('zoho_billing.client_id');
        $clientSecret = config('zoho_billing.client_secret');

        if (empty($refreshToken) || empty($clientId) || empty($clientSecret)) {
            Log::warning('Zoho Billing configuration missing required OAuth credentials', [
                'has_client_id' => ! empty($clientId),
                'has_client_secret' => ! empty($clientSecret),
                'has_refresh_token' => ! empty($refreshToken),
            ]);

            throw new RuntimeException('Zoho OAuth credentials missing in configuration.');
        }

        try {
            $response = Http::asForm()
                ->timeout(config('zoho_billing.http_timeout', 20))
                ->retry(config('zoho_billing.http_retry_times', 2), config('zoho_billing.http_retry_sleep_ms', 200))
                ->post($url, [
                    'refresh_token' => $refreshToken,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => config('zoho_billing.redirect_uri'),
                    'grant_type' => 'refresh_token',
                ]);
        } catch (RequestException $exception) {
            Log::error('Zoho token refresh HTTP request failed', [
                'status' => optional($exception->response)->status(),
            ]);

            throw new RuntimeException('Unable to connect to Zoho authorization server.');
        }

        if ($response->failed()) {
            Log::error('Zoho token refresh HTTP response failed', [
                'status' => $response->status(),
                'error_code' => data_get($response->json(), 'error'),
            ]);

            throw new RuntimeException('Zoho authorization request failed.');
        }

        $json = $response->json();

        if (! is_array($json) || empty($json['access_token'])) {
            $errorCode = is_array($json) ? ($json['error'] ?? 'missing_access_token') : 'invalid_json_payload';
            Log::error('Zoho token refresh returned error or missing access token', [
                'error_code' => $errorCode,
            ]);

            throw new RuntimeException('Zoho token refresh failed.');
        }

        return $json;
    }

    public function tokenMeta(): array
    {
        $cachedToken = Cache::get('zoho_billing_access_token');

        return [
            'has_cached_token' => (bool) $cachedToken,
            'cached_token_length' => $cachedToken ? strlen((string) $cachedToken) : 0,
            'org_id' => config('zoho_billing.org_id'),
            'base_url' => config('zoho_billing.base_url'),
            'token_endpoint' => config('zoho_billing.oauth_token_url'),
        ];
    }
}
