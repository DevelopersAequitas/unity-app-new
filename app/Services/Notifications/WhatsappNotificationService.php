<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsappNotificationService
{
    public static ?array $lastResponse = null;

    /**
     * Send a WhatsApp notification using a database-driven template.
     *
     * @param  string  $templateKey  Key identifying the template (e.g. 'otp_verification')
     * @param  string  $phone  Target phone number
     * @param  array<string, mixed>  $payload  Payload parameters to send in webhook body
     */
    public function send(string $templateKey, string $phone, array $payload = []): bool
    {
        try {
            $template = WhatsappTemplate::query()
                ->where('template_key', $templateKey)
                ->first();

            if (! $template) {
                Log::warning('WhatsApp notification skipped: Template key not found in database.', [
                    'template_key' => $templateKey,
                ]);

                return false;
            }

            if (! $template->is_active) {
                Log::info('WhatsApp notification skipped: Template is inactive.', [
                    'template_key' => $templateKey,
                ]);

                return false;
            }

            $webhookUrl = trim((string) $template->webhook_url);
            $webhookSecret = trim((string) $template->webhook_secret);

            if ($webhookUrl === '') {
                Log::error('WhatsApp notification failed: Webhook URL is empty.', [
                    'template_key' => $templateKey,
                ]);

                return false;
            }

            $normalizedPhone = static::normalizePhone($phone);
            if ($normalizedPhone === '') {
                Log::error('WhatsApp notification failed: Invalid phone number format.', [
                    'template_key' => $templateKey,
                    'phone' => $phone,
                ]);

                return false;
            }

            $atPayload = [];
            foreach ($payload as $key => $value) {
                if (is_string($key) && ! str_starts_with($key, '@')) {
                    $atPayload['@'.$key] = $value;
                }
            }

            $body = array_merge([
                'phone' => $normalizedPhone,
                '@phone' => $normalizedPhone,
                'mobile' => $normalizedPhone,
                '@mobile' => $normalizedPhone,
            ], $payload, $atPayload);

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
                        Log::error('WhatsApp notification failed: API response indicated success=false.', [
                            'template_key' => $templateKey,
                            'webhook_url' => $webhookUrl,
                            'request_body' => $body,
                            'response_body' => $response->body(),
                        ]);

                        return false;
                    }

                    // FlexiMSG: check if the async WhatsApp trigger failed
                    if (isset($responseData['whatsapp_triggered']) && $responseData['whatsapp_triggered'] === false) {
                        $errorMsg = $responseData['error_message'] ?? '(no error_message returned by FlexiMSG)';
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

                        return false;
                    }

                    // FlexiMSG: check if processing status is failed
                    if (isset($responseData['processing_status']) && in_array(strtolower((string) $responseData['processing_status']), ['failed', 'error', 'failure'], true)) {
                        Log::error('WhatsApp notification failed: FlexiMSG processing_status indicates failure.', [
                            'template_key' => $templateKey,
                            'webhook_url' => $webhookUrl,
                            'processing_status' => $responseData['processing_status'],
                            'fleximsg_log_id' => $responseData['log_id'] ?? null,
                            'request_body' => $body,
                            'response_body' => $response->body(),
                        ]);

                        return false;
                    }

                    if (isset($responseData['status']) && in_array(strtolower((string) $responseData['status']), ['error', 'failed', 'failure'], true)) {
                        Log::error('WhatsApp notification failed: API response status is error.', [
                            'template_key' => $templateKey,
                            'webhook_url' => $webhookUrl,
                            'request_body' => $body,
                            'response_body' => $response->body(),
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

            Log::error('WhatsApp notification failed HTTP response check.', [
                'template_key' => $templateKey,
                'webhook_url' => $webhookUrl,
                'status_code' => $response->status(),
                'request_body' => $body,
                'response_body' => $response->body(),
            ]);

            return false;
        } catch (Throwable $exception) {
            Log::error('WhatsApp notification threw an exception.', [
                'template_key' => $templateKey,
                'error' => $exception->getMessage(),
                'exception_class' => get_class($exception),
            ]);

            return false;
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
