<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Exceptions\MetaWhatsAppException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppCloudApiService
{
    private string $baseUrl;

    private string $apiVersion;

    private ?string $accessToken;

    private string $phoneNumberId;

    private int $timeout;

    private int $retryTimes;

    private int $retrySleepMs;

    public function __construct()
    {
        $this->baseUrl = (string) Config::get('whatsapp.meta.base_url', 'https://graph.facebook.com');
        $this->apiVersion = (string) Config::get('whatsapp.meta.api_version', 'v22.0');
        $this->accessToken = Config::get('whatsapp.meta.access_token') ?: null;
        $this->phoneNumberId = (string) Config::get('whatsapp.meta.phone_number_id', '1148211571702139');
        $this->timeout = (int) Config::get('whatsapp.meta.timeout', 20);
        $this->retryTimes = (int) Config::get('whatsapp.meta.retry_times', 2);
        $this->retrySleepMs = (int) Config::get('whatsapp.meta.retry_sleep_ms', 500);
    }

    /**
     * Get configured HTTP client with auth headers, timeouts, and safe retry policy.
     */
    protected function client(): PendingRequest
    {
        if (empty($this->accessToken)) {
            throw new MetaWhatsAppException(
                'Meta WhatsApp access token is not configured. Please set WHATSAPP_META_ACCESS_TOKEN in your environment.',
                401,
                190,
                'ConfigurationError'
            );
        }

        return Http::withToken($this->accessToken)
            ->baseUrl("{$this->baseUrl}/{$this->apiVersion}")
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retrySleepMs, function ($exception, $request): bool {
                // Retry only network drops or 5xx server errors, never 4xx client errors
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response?->serverError());
            }, throw: false);
    }

    /**
     * Send a template message payload directly to Meta WhatsApp Cloud API.
     *
     * @param  array<string, mixed>  $payload  Standard Meta WhatsApp template payload
     * @return array{
     *     success: bool,
     *     status: string,
     *     provider_message_id: ?string,
     *     response: array<string, mixed>,
     *     http_status: int
     * }
     *
     * @throws MetaWhatsAppException
     */
    public function send(array $payload): array
    {
        $endpoint = "/{$this->phoneNumberId}/messages";

        try {
            $response = $this->client()->post($endpoint, $payload);

            if (! $response->successful()) {
                $this->handleErrorResponse($response, 'sendMessage');
            }

            $responseData = (array) $response->json();
            $metaMessageId = (string) ($responseData['messages'][0]['id'] ?? '');

            return [
                'success' => true,
                'status' => 'sent',
                'provider_message_id' => $metaMessageId ?: null,
                'response' => $responseData,
                'http_status' => $response->status(),
            ];
        } catch (MetaWhatsAppException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('[WhatsAppCloudApiService] Network or client exception calling Meta Graph API: '.$e->getMessage(), [
                'endpoint' => $endpoint,
            ]);

            throw new MetaWhatsAppException(
                'Meta WhatsApp API request failed: '.$e->getMessage(),
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
     *
     * @throws MetaWhatsAppException
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

        Log::error("[WhatsAppCloudApiService] Meta API error during {$operation}.", [
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
            $error,
            ['operation' => $operation]
        );
    }
}
