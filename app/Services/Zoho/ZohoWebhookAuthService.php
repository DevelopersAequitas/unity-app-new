<?php

namespace App\Services\Zoho;

use Illuminate\Http\Request;

class ZohoWebhookAuthService
{
    public function isAuthorized(Request $request): bool
    {
        $expected = $this->expectedSecret();
        if ($expected === '') {
            return false;
        }

        $incoming = $this->extractIncomingSecret($request);

        return $incoming !== '' && hash_equals($expected, $incoming);
    }

    public function expectedSecret(): string
    {
        return trim((string) (
            env('ZOHO_WEBHOOK_SECRET')
            ?: config('services.zoho.webhook_secret')
            ?: config('services.zoho.webhook_token')
            ?: config('zoho_billing.webhook_secret')
            ?: env('ZOHO_WEBHOOK_TOKEN')
            ?: ''
        ));
    }

    public function extractIncomingSecret(Request $request): string
    {
        $authorization = (string) $request->header('Authorization', '');
        $bearerFromHeader = '';
        if (str_starts_with(strtolower($authorization), 'bearer ')) {
            $bearerFromHeader = trim(substr($authorization, 7));
        }

        return trim((string) (
            $request->header('X-Webhook-Secret')
            ?? $request->header('X-Zoho-Webhook-Secret')
            ?? $request->header('X-Webhook-Token')
            ?? $request->header('X-Zoho-Webhook-Signature')
            ?? $bearerFromHeader
            ?? $request->bearerToken()
            ?? $request->query('token')
            ?? $request->input('token')
            ?? ''
        ));
    }
}
