<?php

namespace App\Services\Zoho;

use App\Exceptions\ZohoAuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZohoTokenService
{
    public function accessToken(bool $forceRefresh = false): string
    {
        if ($forceRefresh) {
            $this->forgetCachedToken();
        }

        return Cache::remember('zoho:oauth:access_token', now()->addMinutes(50), function (): string {
            $endpoint = rtrim((string) config('services.zoho_payments.accounts_base_url', 'https://accounts.zoho.in'), '/').'/oauth/v2/token';

            $response = Http::asForm()->post($endpoint, [
                'refresh_token' => config('services.zoho_payments.refresh_token'),
                'client_id' => config('services.zoho_payments.client_id'),
                'client_secret' => config('services.zoho_payments.client_secret'),
                'grant_type' => 'refresh_token',
            ]);

            Log::info('Zoho token response received', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'ok' => $response->ok(),
            ]);

            if (! $response->successful()) {
                Log::error('Zoho token generation failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new ZohoAuthorizationException('Zoho authorization failed. Please verify Zoho credentials, refresh token, scopes, and organization ID.');
            }

            $token = (string) data_get($response->json(), 'access_token', '');
            if ($token === '') {
                Log::error('Zoho token missing in response', ['endpoint' => $endpoint]);
                throw new ZohoAuthorizationException('Zoho authorization failed. Please verify Zoho credentials, refresh token, scopes, and organization ID.');
            }

            Log::info('Zoho token generation success', ['endpoint' => $endpoint]);

            return $token;
        });
    }

    public function forgetCachedToken(): void
    {
        Cache::forget('zoho:oauth:access_token');
    }
}
