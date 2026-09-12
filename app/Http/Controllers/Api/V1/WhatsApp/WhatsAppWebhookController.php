<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\WhatsApp;

use App\Http\Controllers\Controller;
use App\Services\Notifications\WhatsAppWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsAppWebhookService $webhookService
    ) {}

    /**
     * Handle Meta Webhook verification (GET challenge handshake).
     */
    public function verify(Request $request): Response|JsonResponse
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $configuredToken = config('services.whatsapp.webhook_verify_token')
            ?? config('services.fleximsg.webhook_verify_token')
            ?? config('services.meta.webhook_verify_token')
            ?? 'unity_app_whatsapp_verify_token';

        if ($mode === 'subscribe' && $token === $configuredToken && $challenge !== null) {
            Log::info('[WhatsAppWebhookController] Webhook verification handshake successful.');

            return response((string) $challenge, 200, ['Content-Type' => 'text/plain']);
        }

        Log::warning('[WhatsAppWebhookController] Webhook verification handshake failed.', [
            'mode' => $mode,
            'token_provided' => ! empty($token),
        ]);

        return response()->json(['error' => 'Forbidden'], 403);
    }

    /**
     * Handle incoming WhatsApp status / event webhook (POST).
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('[WhatsAppWebhookController] Inbound WhatsApp webhook payload received.', [
            'headers' => $request->headers->all(),
            'body_keys' => array_keys($payload),
        ]);

        $result = $this->webhookService->processWebhook($payload);

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp webhook processed successfully.',
            'processed_count' => $result['processed_count'],
            'updated_logs' => $result['updated_logs'],
        ], 200);
    }
}
