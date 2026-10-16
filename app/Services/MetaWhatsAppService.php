<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MetaWhatsAppException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaWhatsAppService
{
    private string $baseUrl;

    private string $apiVersion;

    private ?string $accessToken;

    private string $phoneNumberId;

    private string $wabaId;

    private int $timeout;

    private int $retryTimes;

    private int $retrySleepMs;

    public function __construct()
    {
        $this->baseUrl = (string) Config::get('whatsapp.meta.base_url', 'https://graph.facebook.com');
        $this->apiVersion = (string) Config::get('whatsapp.meta.api_version', 'v21.0');
        $this->accessToken = Config::get('whatsapp.meta.access_token') ?: null;
        $this->phoneNumberId = (string) Config::get('whatsapp.meta.phone_number_id', '1148211571702139');
        $this->wabaId = (string) Config::get('whatsapp.meta.business_account_id', '1475250733815899');
        $this->timeout = (int) Config::get('whatsapp.meta.timeout', 20);
        $this->retryTimes = (int) Config::get('whatsapp.meta.retry_times', 2);
        $this->retrySleepMs = (int) Config::get('whatsapp.meta.retry_sleep_ms', 500);
    }

    /**
     * Get configured HTTP client with auth headers and timeout.
     */
    protected function client(): PendingRequest
    {
        if (empty($this->accessToken)) {
            throw new MetaWhatsAppException(
                'Meta WhatsApp access token is not configured. Please set META_WHATSAPP_ACCESS_TOKEN or WHATSAPP_META_ACCESS_TOKEN in your environment.',
                401,
                190,
                'ConfigurationError'
            );
        }

        return Http::withToken($this->accessToken)
            ->baseUrl("{$this->baseUrl}/{$this->apiVersion}")
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retrySleepMs, function ($exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response?->serverError());
            }, throw: false);
    }

    /**
     * Clean and format phone number for Meta Cloud API (digits only, no + or whitespace).
     */
    public function formatPhone(string $phone): string
    {
        $cleaned = preg_replace('/\D+/', '', $phone) ?? '';

        // If 10 digits and starts with standard Indian mobile (6,7,8,9), prefix 91
        if (strlen($cleaned) === 10 && preg_match('/^[6-9]/', $cleaned)) {
            $cleaned = '91'.$cleaned;
        }

        return $cleaned;
    }

    /**
     * Send a freeform text message to a WhatsApp user (Allowed within active 24h window).
     *
     * @return array{
     *     success: bool,
     *     message_id: ?string,
     *     response: array<string, mixed>,
     *     http_status: int
     * }
     */
    public function sendTextMessage(string $toPhone, string $messageText): array
    {
        $to = $this->formatPhone($toPhone);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $messageText,
            ],
        ];

        return $this->dispatchPayload($payload, 'sendTextMessage');
    }

    /**
     * Send a pre-approved template message to a WhatsApp user (Required when 24h window expired).
     *
     * @param  array<int, array<string, mixed>>  $components
     * @return array{
     *     success: bool,
     *     message_id: ?string,
     *     response: array<string, mixed>,
     *     http_status: int
     * }
     */
    public function sendTemplateMessage(
        string $toPhone,
        string $templateName,
        string $languageCode = 'en',
        array $components = []
    ): array {
        $to = $this->formatPhone($toPhone);

        $templateData = [
            'name' => $templateName,
            'language' => [
                'code' => $languageCode,
            ],
        ];

        if (! empty($components)) {
            $templateData['components'] = $components;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => $templateData,
        ];

        return $this->dispatchPayload($payload, 'sendTemplateMessage');
    }

    /**
     * Mark an inbound customer message as READ on Meta's servers.
     * This immediately turns the checkmarks BLUE on the customer's phone!
     *
     * @return array{
     *     success: bool,
     *     response: array<string, mixed>,
     *     http_status: int
     * }
     */
    public function markMessageAsRead(string $messageId): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ];

        try {
            $response = $this->client()->post("/{$this->phoneNumberId}/messages", $payload);

            if (! $response->successful()) {
                Log::warning('[MetaWhatsAppService] Failed to mark message as read on Meta API.', [
                    'message_id' => $messageId,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return [
                    'success' => false,
                    'response' => (array) $response->json(),
                    'http_status' => $response->status(),
                ];
            }

            return [
                'success' => true,
                'response' => (array) $response->json(),
                'http_status' => $response->status(),
            ];
        } catch (Throwable $e) {
            Log::warning('[MetaWhatsAppService] Exception marking message as read: '.$e->getMessage(), [
                'message_id' => $messageId,
            ]);

            return [
                'success' => false,
                'response' => ['error' => $e->getMessage()],
                'http_status' => 500,
            ];
        }
    }

    /**
     * Dispatch general message payload to Meta Graph API.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     success: bool,
     *     message_id: ?string,
     *     response: array<string, mixed>,
     *     http_status: int
     * }
     */
    private function dispatchPayload(array $payload, string $operation): array
    {
        $endpoint = "/{$this->phoneNumberId}/messages";

        try {
            $response = $this->client()->post($endpoint, $payload);

            if (! $response->successful()) {
                $this->handleErrorResponse($response, $operation);
            }

            $responseData = (array) $response->json();
            $metaMessageId = (string) ($responseData['messages'][0]['id'] ?? '');

            return [
                'success' => true,
                'message_id' => $metaMessageId ?: null,
                'response' => $responseData,
                'http_status' => $response->status(),
            ];
        } catch (MetaWhatsAppException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error("[MetaWhatsAppService] Error during {$operation}: ".$e->getMessage(), [
                'endpoint' => $endpoint,
                'payload' => $payload,
            ]);

            throw new MetaWhatsAppException(
                "WhatsApp API request failed during {$operation}: ".$e->getMessage(),
                500,
                500,
                'ConnectionError',
                null,
                null,
                [],
                [],
                $e
            );
        }
    }

    /**
     * Handle HTTP error response from Meta Graph API.
     */
    private function handleErrorResponse(Response $response, string $operation): never
    {
        $status = $response->status();
        $body = $response->json() ?? [];
        $error = is_array($body) ? ($body['error'] ?? []) : [];

        $message = (string) ($error['message'] ?? $response->body() ?: 'Meta Graph API error');
        $code = isset($error['code']) ? (int) $error['code'] : null;
        $type = isset($error['type']) ? (string) $error['type'] : null;
        $subcode = isset($error['error_subcode']) ? (int) $error['error_subcode'] : null;
        $fbtraceId = isset($error['fbtrace_id']) ? (string) $error['fbtrace_id'] : null;

        Log::error("[MetaWhatsAppService] Meta API error during {$operation}.", [
            'http_status' => $status,
            'meta_code' => $code,
            'meta_type' => $type,
            'meta_subcode' => $subcode,
            'meta_message' => $message,
            'fbtrace_id' => $fbtraceId,
        ]);

        throw new MetaWhatsAppException(
            $message,
            $status,
            $code,
            $type,
            $subcode,
            $fbtraceId,
            [],
            is_array($body) ? $body : []
        );
    }
}
