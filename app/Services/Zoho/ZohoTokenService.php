<?php

namespace App\Services\Zoho;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZohoTokenService
{
    public function accessToken(): string
    {
        return Cache::remember('zoho:oauth:access_token', now()->addMinutes(50), function (): string {
            $response = Http::asForm()->post(rtrim((string) config('services.zoho_payments.accounts_base_url'), '/').'/oauth/v2/token', [
                'refresh_token' => config('services.zoho_payments.refresh_token'),
                'client_id' => config('services.zoho_payments.client_id'),
                'client_secret' => config('services.zoho_payments.client_secret'),
                'grant_type' => 'refresh_token',
            ])->throw()->json();

            $token = (string) ($response['access_token'] ?? '');
            if ($token === '') {
                throw new RuntimeException('Unable to fetch Zoho access token.');
            }

            return $token;
        });
    }
}
