<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\User;
use App\Models\WhatsappMessageDeliveryLog;
use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsappNotificationService
{
    public static ?array $lastResponse = null;

    public static ?string $lastError = null;

    /**
     * Send a WhatsApp notification using a database-driven template.
     *
     * @param  string  $templateKey  Key identifying the template (e.g. 'otp_verification')
     * @param  string  $phone  Target phone number
     * @param  array<string, mixed>  $payload  Payload parameters to send in webhook body
     * @param  string|null  $userId  Optional user ID to associate with the delivery log
     * @param  string|null  $notificationId  Optional notification ID to associate with the delivery log
     */
    public function send(
        string $templateKey,
        string $phone,
        array $payload = [],
        ?string $userId = null,
        ?string $notificationId = null
    ): bool {
        self::$lastError = null;
        self::$lastResponse = null;
        $deliveryLog = null;
        $attemptedAt = now();

        try {
            $template = WhatsappTemplate::query()
                ->where('template_key', $templateKey)
                ->first();

            if (! $template) {
                if ($templateKey === 'milestone_badge_whatsapp') {
                    $template = WhatsappTemplate::query()->where('template_key', 'milestone_connector')->first();
                } elseif ($templateKey === 'milestone_connector') {
                    $template = WhatsappTemplate::query()->where('template_key', 'milestone_badge_whatsapp')->first();
                }
            }

            // Extract creative URL if present in payload
            $creativeUrl = $payload['creative_url']
                ?? $payload['badge_image_url']
                ?? $payload['header_media_url']
                ?? $payload['header_image_url']
                ?? $payload['image_url']
                ?? $payload['image']
                ?? $payload['media_url']
                ?? $payload['welcome_creative_url']
                ?? $payload['custom_image_url']
                ?? null;

            if (! is_string($creativeUrl) || trim($creativeUrl) === '') {
                $creativeUrl = null;
            } else {
                $creativeUrl = trim($creativeUrl);
            }

            $normalizedPhone = static::normalizePhone($phone);

            // Resolve user ID if available
            $resolvedUserId = $userId ?? ($payload['user_id'] ?? $payload['userId'] ?? null);
            if (! is_string($resolvedUserId) || trim($resolvedUserId) === '') {
                $resolvedUserId = null;
            }
            if ($resolvedUserId === null && $normalizedPhone !== '') {
                $resolvedUserId = User::query()
                    ->where('phone', $normalizedPhone)
                    ->orWhere('secondary_mobile', $normalizedPhone)
                    ->orWhere('phone', $phone)
                    ->orWhere('secondary_mobile', $phone)
                    ->value('id');
            }

            // Resolve notification ID if available
            $resolvedNotificationId = $notificationId ?? ($payload['notification_id'] ?? $payload['notificationId'] ?? null);
            if (! is_string($resolvedNotificationId) || trim($resolvedNotificationId) === '') {
                $resolvedNotificationId = null;
            }

            $customLogId = $payload['delivery_log_id'] ?? null;
            if (is_string($customLogId) && trim($customLogId) !== '') {
                try {
                    $deliveryLog = WhatsappMessageDeliveryLog::find(trim($customLogId));
                } catch (Throwable) {
                    $deliveryLog = null;
                }
            }

            if (! $template) {
                self::$lastError = "Template key not found in database: {$templateKey}";
                Log::warning('WhatsApp notification skipped: Template key not found in database.', [
                    'template_key' => $templateKey,
                ]);

                $this->recordEarlyFailure($deliveryLog, $customLogId, $resolvedUserId, $resolvedNotificationId, $templateKey, $templateKey, $normalizedPhone ?: $phone, $creativeUrl, self::$lastError, $payload, $attemptedAt);

                return false;
            }

            if (! $template->is_active) {
                self::$lastError = "Template is inactive: {$templateKey}";
                Log::info('WhatsApp notification skipped: Template is inactive.', [
                    'template_key' => $templateKey,
                ]);

                $this->recordEarlyFailure($deliveryLog, $customLogId, $resolvedUserId, $resolvedNotificationId, $templateKey, $template->template_name ?: $templateKey, $normalizedPhone ?: $phone, $creativeUrl, self::$lastError, $payload, $attemptedAt);

                return false;
            }

            $webhookUrl = trim((string) $template->webhook_url);
            $webhookSecret = trim((string) $template->webhook_secret);

            if ($webhookUrl === '') {
                self::$lastError = "Webhook URL is empty for template key: {$templateKey}";
                Log::error('WhatsApp notification failed: Webhook URL is empty.', [
                    'template_key' => $templateKey,
                ]);

                $this->recordEarlyFailure($deliveryLog, $customLogId, $resolvedUserId, $resolvedNotificationId, $templateKey, $template->template_name ?: $templateKey, $normalizedPhone ?: $phone, $creativeUrl, self::$lastError, $payload, $attemptedAt);

                return false;
            }

            if ($normalizedPhone === '') {
                self::$lastError = "Invalid phone number format: {$phone}";
                Log::error('WhatsApp notification failed: Invalid phone number format.', [
                    'template_key' => $templateKey,
                    'phone' => $phone,
                ]);

                $this->recordEarlyFailure($deliveryLog, $customLogId, $resolvedUserId, $resolvedNotificationId, $templateKey, $template->template_name ?: $templateKey, $phone, $creativeUrl, self::$lastError, $payload, $attemptedAt);

                return false;
            }

            $atPayload = [];
            foreach ($payload as $key => $value) {
                $strKey = (string) $key;
                if (! str_starts_with($strKey, '@')) {
                    $atPayload['@'.$strKey] = $value;
                }
            }

            $body = [
                'phone' => $normalizedPhone,
                '@phone' => $normalizedPhone,
                'mobile' => $normalizedPhone,
                '@mobile' => $normalizedPhone,
            ];

            foreach ($payload as $k => $v) {
                $body[(string) $k] = $v;
            }

            foreach ($atPayload as $k => $v) {
                $body[(string) $k] = $v;
            }

            // Immediately before sending, log pending delivery attempt
            try {
                if ($deliveryLog) {
                    $deliveryLog->update([
                        'user_id' => $resolvedUserId,
                        'notification_id' => $resolvedNotificationId,
                        'template_key' => $templateKey,
                        'template_name' => $template->template_name ?: $templateKey,
                        'phone' => $normalizedPhone,
                        'creative_url' => $creativeUrl,
                        'provider' => 'fleximsg',
                        'status' => 'pending',
                        'request_payload' => $body,
                        'attempted_at' => $attemptedAt,
                    ]);
                } else {
                    $createParams = [
                        'user_id' => $resolvedUserId,
                        'notification_id' => $resolvedNotificationId,
                        'template_key' => $templateKey,
                        'template_name' => $template->template_name ?: $templateKey,
                        'phone' => $normalizedPhone,
                        'creative_url' => $creativeUrl,
                        'provider' => 'fleximsg',
                        'status' => 'pending',
                        'request_payload' => $body,
                        'attempted_at' => $attemptedAt,
                    ];
                    if (is_string($customLogId) && trim($customLogId) !== '') {
                        $createParams['id'] = trim($customLogId);
                    }
                    $deliveryLog = WhatsappMessageDeliveryLog::create($createParams);
                }
            } catch (Throwable $logEx) {
                Log::error('Failed to create pending WhatsappMessageDeliveryLog: '.$logEx->getMessage(), [
                    'template_key' => $templateKey,
                    'phone' => $normalizedPhone,
                ]);
            }

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Secret' => $webhookSecret,
            ])
                ->timeout(15)
                ->post($webhookUrl, $body);

            if ($response->successful()) {
                $responseData = $response->json();
                self::$lastResponse = $responseData;

                if (is_array($responseData)) {
                    if (isset($responseData['success']) && $responseData['success'] === false) {
                        self::$lastError = 'FlexiMsg API success=false: '.($responseData['error_message'] ?? 'Unknown Error');
                        Log::error('WhatsApp notification failed: API response indicated success=false.', [
                            'template_key' => $templateKey,
                            'webhook_url' => $webhookUrl,
                            'request_body' => $body,
                            'response_body' => $response->body(),
                        ]);

                        $deliveryLog?->update([
                            'status' => 'failed',
                            'error_message' => self::$lastError,
                            'response_payload' => $responseData,
                            'attempted_at' => $attemptedAt,
                        ]);

                        return false;
                    }

                    // FlexiMSG: check if the async WhatsApp trigger failed
                    if (isset($responseData['whatsapp_triggered']) && $responseData['whatsapp_triggered'] === false) {
                        $errorMsg = $responseData['error_message'] ?? '(no error_message returned by FlexiMSG)';
                        self::$lastError = "FlexiMsg whatsapp_triggered=false. Error: {$errorMsg}";
                        Log::error('WhatsApp notification failed: FlexiMSG whatsapp_triggered=false. Header image variable is likely not mapped in the FlexiMSG template configuration.', [
                            'template_key' => $templateKey,
                            'webhook_url' => $webhookUrl,
                            'fleximsg_log_id' => $responseData['log_id'] ?? null,
                            'error_message' => $errorMsg,
                            'extracted_fields' => $responseData['extracted_fields'] ?? [],
                            'fix_required' => 'Go to FlexiMSG dashboard -> Webhooks -> wear_the_badge -> edit template -> map HEADER IMAGE variable to @{header_media_url}',
                            'request_body' => $body,
                            'response_body' => $response->body(),
                        ]);

                        $deliveryLog?->update([
                            'status' => 'failed',
                            'error_message' => self::$lastError,
                            'response_payload' => $responseData,
                            'attempted_at' => $attemptedAt,
                        ]);

                        return false;
                    }

                    // FlexiMSG: check if processing status is failed
                    if (isset($responseData['processing_status']) && in_array(strtolower((string) $responseData['processing_status']), ['failed', 'error', 'failure'], true)) {
                        self::$lastError = 'FlexiMsg processing_status failure: '.($responseData['processing_status']);
                        Log::error('WhatsApp notification failed: FlexiMSG processing_status indicates failure.', [
                            'template_key' => $templateKey,
                            'webhook_url' => $webhookUrl,
                            'processing_status' => $responseData['processing_status'],
                            'fleximsg_log_id' => $responseData['log_id'] ?? null,
                            'request_body' => $body,
                            'response_body' => $response->body(),
                        ]);

                        $deliveryLog?->update([
                            'status' => 'failed',
                            'error_message' => self::$lastError,
                            'response_payload' => $responseData,
                            'attempted_at' => $attemptedAt,
                        ]);

                        return false;
                    }

                    if (isset($responseData['status']) && in_array(strtolower((string) $responseData['status']), ['error', 'failed', 'failure'], true)) {
                        self::$lastError = 'FlexiMsg status error: '.($responseData['status']);
                        Log::error('WhatsApp notification failed: API response status is error.', [
                            'template_key' => $templateKey,
                            'webhook_url' => $webhookUrl,
                            'request_body' => $body,
                            'response_body' => $response->body(),
                        ]);

                        $deliveryLog?->update([
                            'status' => 'failed',
                            'error_message' => self::$lastError,
                            'response_payload' => $responseData,
                            'attempted_at' => $attemptedAt,
                        ]);

                        return false;
                    }

                    // Log warning if error_message is returned even on success
                    if (! empty($responseData['error_message'])) {
                        Log::warning('WhatsApp notification: FlexiMSG returned error_message despite HTTP 200.', [
                            'template_key' => $templateKey,
                            'error_message' => $responseData['error_message'],
                            'response_body' => $response->body(),
                        ]);
                    }
                }

                $providerMessageId = null;
                if (is_array($responseData)) {
                    $rawId = $responseData['log_id'] ?? $responseData['message_id'] ?? $responseData['provider_message_id'] ?? $responseData['id'] ?? null;
                    if ($rawId !== null && (is_string($rawId) || is_numeric($rawId))) {
                        $providerMessageId = (string) $rawId;
                    }
                }

                $deliveryLog?->update([
                    'status' => 'sent',
                    'provider_message_id' => $providerMessageId,
                    'response_payload' => is_array($responseData) ? $responseData : ['raw' => $response->body()],
                    'delivered_at' => now(),
                    'attempted_at' => $attemptedAt,
                ]);

                Log::info('WhatsApp notification sent successfully.', [
                    'template_key' => $templateKey,
                    'webhook_url' => $webhookUrl,
                    'status_code' => $response->status(),
                    'fleximsg_log_id' => $responseData['log_id'] ?? null,
                    'whatsapp_triggered' => $responseData['whatsapp_triggered'] ?? 'unknown',
                    'processing_status' => $responseData['processing_status'] ?? 'unknown',
                    'request_body' => $body,
                    'response_body' => $response->body(),
                ]);

                return true;
            }

            // Capture HTTP non-2xx failure details safely (mask authorization/webhook secrets)
            $maskedHeaders = collect($response->headers())
                ->except(['X-Webhook-Secret', 'Authorization', 'x-webhook-secret', 'authorization'])
                ->toArray();

            self::$lastError = 'FlexiMsg HTTP Non-2xx response: '.json_encode([
                'status_code' => $response->status(),
                'response_body' => $response->body(),
                'request_url' => $webhookUrl,
                'request_payload_keys' => array_keys($body),
                'request_headers' => $maskedHeaders,
            ], JSON_UNESCAPED_SLASHES);

            Log::error('WhatsApp notification failed HTTP response check.', [
                'template_key' => $templateKey,
                'webhook_url' => $webhookUrl,
                'status_code' => $response->status(),
                'request_body' => $body,
                'response_body' => $response->body(),
            ]);

            $deliveryLog?->update([
                'status' => 'failed',
                'error_message' => self::$lastError,
                'response_payload' => $response->json() ?? ['raw' => $response->body(), 'status' => $response->status()],
                'attempted_at' => $attemptedAt,
            ]);

            return false;
        } catch (Throwable $exception) {
            self::$lastError = 'Exception: '.$exception->getMessage();
            Log::error('WhatsApp notification threw an exception.', [
                'template_key' => $templateKey,
                'error' => $exception->getMessage(),
                'exception_class' => get_class($exception),
            ]);

            $deliveryLog?->update([
                'status' => 'failed',
                'error_message' => 'Exception: '.$exception->getMessage(),
                'attempted_at' => $attemptedAt,
            ]);

            return false;
        }
    }

    /**
     * Record early failure in whatsapp_message_delivery_logs before webhook call.
     */
    private function recordEarlyFailure(
        ?WhatsappMessageDeliveryLog $deliveryLog,
        ?string $customLogId,
        ?string $userId,
        ?string $notificationId,
        string $templateKey,
        string $templateName,
        string $phone,
        ?string $creativeUrl,
        string $errorMessage,
        array $payload,
        \DateTimeInterface $attemptedAt
    ): void {
        try {
            if ($deliveryLog) {
                $deliveryLog->update([
                    'user_id' => $userId,
                    'notification_id' => $notificationId,
                    'template_key' => $templateKey,
                    'template_name' => $templateName,
                    'phone' => $phone,
                    'creative_url' => $creativeUrl,
                    'provider' => 'fleximsg',
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                    'request_payload' => $payload,
                    'attempted_at' => $attemptedAt,
                ]);
            } else {
                $createData = [
                    'user_id' => $userId,
                    'notification_id' => $notificationId,
                    'template_key' => $templateKey,
                    'template_name' => $templateName,
                    'phone' => $phone,
                    'creative_url' => $creativeUrl,
                    'provider' => 'fleximsg',
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                    'request_payload' => $payload,
                    'attempted_at' => $attemptedAt,
                ];

                if (is_string($customLogId) && trim($customLogId) !== '') {
                    $createData['id'] = trim($customLogId);
                }

                WhatsappMessageDeliveryLog::create($createData);
            }
        } catch (Throwable $e) {
            Log::error('Failed to record early failure in WhatsappMessageDeliveryLog: '.$e->getMessage());
        }
    }

    /**
     * Normalize phone number to standard 12-digit Indian format (e.g. 919876543210).
     */
    public static function normalizePhone(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return $digits;
        }

        if (strlen($digits) > 10) {
            return '91'.substr($digits, -10);
        }

        return $digits;
    }
}
