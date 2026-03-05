<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyZohoWebhookToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $incomingToken = (string) $request->header('x-webhook-token', '');
        $configuredToken = (string) env('ZOHO_WEBHOOK_TOKEN', '');

        if ($incomingToken === '' || $configuredToken === '' || ! hash_equals($configuredToken, $incomingToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
