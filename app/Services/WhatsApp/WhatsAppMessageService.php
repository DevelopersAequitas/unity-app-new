<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Exceptions\MetaWhatsAppException;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageDeliveryLog;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WhatsAppMessageService
{
    public static ?array $lastResponse = null;

    public static ?string $lastError = null;

    public function __construct(
        protected WhatsAppCloudApiService $cloudApiService,
        protected WhatsAppTemplatePayloadBuilder $payloadBuilder
    ) {}

    /**
     * Send an outbound WhatsApp message using direct Meta Cloud API.
     *
     * @param  string  $templateKey  Key identifying the template (e.g. 'otp_verification', 'welcome')
     * @param  string  $phone  Target phone number
     * @param  array<string, mixed>  $payload  Dynamic parameters
     * @param  string|null  $userId  Optional user ID
     * @param  string|null  $eventKey  Optional event key / notification reference
     * @param  string|null  $idempotencyKey  Optional custom idempotency key
     * @return array{
     *     success: bool,
     *     status: string,
     *     provider_message_id: ?string,
     *     log_id: string,
     *     is_test: bool,
     *     is_dry_run: bool,
     *     error: ?string
     * }
     */
    public function send(
        string $templateKey,
        string|array|null $phone = null,
        array $payload = [],
        ?string $userId = null,
        ?string $eventKey = null,
        ?string $idempotencyKey = null
    ): array {
        self::$lastError = null;
        self::$lastResponse = null;

        if (is_array($phone)) {
            $payload = $phone;
            $phone = (string) ($payload['phone'] ?? $payload['mobile'] ?? '');
        } elseif ($phone === null) {
            $phone = (string) ($payload['phone'] ?? $payload['mobile'] ?? '');
        }

        $env = (string) Config::get('app.env', 'production');
        $isLocalOrDev = in_array(strtolower($env), ['local', 'development', 'dev', 'testing'], true);

        // 1. Resolve Safety Configurations
        $isTestMode = (bool) Config::get('whatsapp.testing.test_mode', $isLocalOrDev);
        $configuredTestPhone = trim((string) Config::get('whatsapp.testing.test_phone', Config::get('whatsapp.testing.recipient', '')));
        $isDryRun = (bool) Config::get('whatsapp.testing.dry_run', false);

        // 2. Normalize Requested Recipient Phone
        $normalizedRequestedPhone = $this->normalizePhone($phone);
        $resolvedUserId = $userId ?? ($payload['user_id'] ?? $payload['userId'] ?? null);

        // Fallback user lookup by phone if user ID not explicitly supplied
        if (empty($resolvedUserId) && $normalizedRequestedPhone !== '') {
            $resolvedUserId = $this->lookupUserIdByPhone($normalizedRequestedPhone);
        }

        // 3. Resolve Target Recipient (Test Mode Redirection vs Production)
        $actualRecipientPhone = $normalizedRequestedPhone;
        $isTestMessage = false;

        if ($isTestMode) {
            $isTestMessage = true;

            // ABSOLUTE SAFETY RULE:
            // If test mode is enabled but no test phone is explicitly configured -> FAIL SAFE.
            if ($configuredTestPhone === '') {
                $errorMsg = 'WhatsApp test mode is enabled, but WHATSAPP_TEST_PHONE is not configured. Outbound send blocked for safety.';
                Log::error('[WhatsAppMessageService] Blocked outbound message: Missing WHATSAPP_TEST_PHONE in test mode.', [
                    'template_key' => $templateKey,
                    'requested_recipient' => $phone,
                ]);

                $log = $this->recordSkippedLog(
                    $templateKey,
                    $phone,
                    $normalizedRequestedPhone,
                    null,
                    $payload,
                    $resolvedUserId,
                    $eventKey,
                    $idempotencyKey,
                    $errorMsg,
                    true
                );

                self::$lastError = $errorMsg;

                return [
                    'success' => false,
                    'status' => WhatsAppMessageLog::STATUS_SKIPPED,
                    'provider_message_id' => null,
                    'log_id' => (string) $log->id,
                    'is_test' => true,
                    'is_dry_run' => false,
                    'error' => $errorMsg,
                ];
            }

            $actualRecipientPhone = $this->normalizePhone($configuredTestPhone);

            Log::info('[WhatsAppMessageService] Test mode active: Recipient redirected to configured test phone.', [
                'template_key' => $templateKey,
                'requested_recipient' => $normalizedRequestedPhone,
                'actual_test_recipient' => $actualRecipientPhone,
            ]);
        }

        if ($actualRecipientPhone === '') {
            $errorMsg = "Invalid recipient phone number format: {$phone}";
            Log::error('[WhatsAppMessageService] '.$errorMsg, [
                'template_key' => $templateKey,
                'phone' => $phone,
            ]);

            $log = $this->recordSkippedLog(
                $templateKey,
                $phone,
                $normalizedRequestedPhone,
                $actualRecipientPhone,
                $payload,
                $resolvedUserId,
                $eventKey,
                $idempotencyKey,
                $errorMsg,
                $isTestMessage,
                WhatsAppMessageLog::STATUS_FAILED,
                'INVALID_RECIPIENT'
            );

            self::$lastError = $errorMsg;

            return [
                'success' => false,
                'status' => WhatsAppMessageLog::STATUS_FAILED,
                'error_code' => 'INVALID_RECIPIENT',
                'error_message' => $errorMsg,
                'provider_message_id' => null,
                'log_id' => (string) $log->id,
                'is_test' => $isTestMessage,
                'is_dry_run' => false,
                'error' => $errorMsg,
            ];
        }

        // 4. Resolve Template Definition & Check Active Status
        $templateDef = null;
        try {
            $templateDef = $this->payloadBuilder->resolveTemplateDefinition($templateKey);
        } catch (Throwable $e) {
            $errorMsg = "Template mapping not found for key: {$templateKey} ({$e->getMessage()})";
            Log::error('[WhatsAppMessageService] '.$errorMsg);

            $log = $this->recordSkippedLog(
                $templateKey,
                $phone,
                $normalizedRequestedPhone,
                $actualRecipientPhone,
                $payload,
                $resolvedUserId,
                $eventKey,
                $idempotencyKey,
                $errorMsg,
                $isTestMessage,
                WhatsAppMessageLog::STATUS_FAILED,
                'TEMPLATE_NOT_FOUND'
            );

            self::$lastError = $errorMsg;

            return [
                'success' => false,
                'status' => WhatsAppMessageLog::STATUS_FAILED,
                'error_code' => 'TEMPLATE_NOT_FOUND',
                'error_message' => $errorMsg,
                'provider_message_id' => null,
                'log_id' => (string) $log->id,
                'is_test' => $isTestMessage,
                'is_dry_run' => false,
                'error' => $errorMsg,
            ];
        }

        $dbTemplate = $templateDef['db_template'] ?? null;
        if ($dbTemplate && ! $dbTemplate->is_active) {
            $errorMsg = "WhatsApp template '{$templateKey}' is marked as inactive.";
            Log::info('[WhatsAppMessageService] Message skipped: '.$errorMsg);

            $log = $this->recordSkippedLog(
                $templateKey,
                $phone,
                $normalizedRequestedPhone,
                $actualRecipientPhone,
                $payload,
                $resolvedUserId,
                $eventKey,
                $idempotencyKey,
                $errorMsg,
                $isTestMessage,
                WhatsAppMessageLog::STATUS_SKIPPED,
                'TEMPLATE_INACTIVE'
            );

            self::$lastError = $errorMsg;

            return [
                'success' => false,
                'status' => WhatsAppMessageLog::STATUS_SKIPPED,
                'error_code' => 'TEMPLATE_INACTIVE',
                'error_message' => $errorMsg,
                'provider_message_id' => null,
                'log_id' => (string) $log->id,
                'is_test' => $isTestMessage,
                'is_dry_run' => false,
                'error' => $errorMsg,
            ];
        }

        // 5. Build Cloud API Template Payload
        $built = $this->payloadBuilder->build($templateKey, $actualRecipientPhone, $payload);
        $metaPayload = [
            'messaging_product' => $built['messaging_product'],
            'recipient_type' => $built['recipient_type'],
            'to' => $built['to'],
            'type' => $built['type'],
            'template' => $built['template'],
        ];

        // 6. Idempotency Check
        $computedIdempotencyKey = $idempotencyKey ?: $this->generateIdempotencyKey($templateKey, $resolvedUserId, $normalizedRequestedPhone, $payload, $eventKey);
        if ($computedIdempotencyKey !== null && $this->isDuplicate($computedIdempotencyKey)) {
            Log::info('[WhatsAppMessageService] Duplicate outbound message prevented by idempotency check.', [
                'template_key' => $templateKey,
                'idempotency_key' => $computedIdempotencyKey,
            ]);

            return [
                'success' => true,
                'status' => 'skipped_duplicate',
                'duplicate' => true,
                'provider_message_id' => null,
                'log_id' => '',
                'is_test' => $isTestMessage,
                'is_dry_run' => false,
                'error' => null,
            ];
        }

        // 7. Prepare Sanitized Logging Data (OTP masked as ******)
        $sanitizedVariables = $this->payloadBuilder->sanitizeVariables($built['extracted_variables']);
        $sanitizedRequestPayload = $this->payloadBuilder->sanitizePayload($metaPayload, $templateKey);

        // 8. Create Initial Message Log Record
        $initialStatus = $isDryRun ? WhatsAppMessageLog::STATUS_DRY_RUN : WhatsAppMessageLog::STATUS_SENDING;

        $log = WhatsAppMessageLog::create([
            'whatsapp_template_id' => $dbTemplate?->id,
            'template_key' => $templateKey,
            'template_name' => $templateDef['meta_name'],
            'event_key' => $eventKey,
            'user_id' => $resolvedUserId,
            'recipient_phone' => $actualRecipientPhone,
            'requested_recipient_phone' => $normalizedRequestedPhone,
            'actual_recipient_phone' => $actualRecipientPhone,
            'payload' => $this->payloadBuilder->sanitizeVariables($payload),
            'template_variables' => $sanitizedVariables,
            'request_payload' => $sanitizedRequestPayload,
            'status' => $initialStatus,
            'source' => $payload['source'] ?? 'application',
            'environment' => $env,
            'is_test' => $isTestMessage,
            'idempotency_key' => $computedIdempotencyKey,
        ]);

        // 9. Dry Run Handling
        if ($isDryRun) {
            Log::info('[WhatsAppMessageService] Dry-run enabled: Built Meta payload logged without calling API.', [
                'log_id' => $log->id,
                'template_key' => $templateKey,
                'meta_template' => $templateDef['meta_name'],
                'actual_recipient' => $actualRecipientPhone,
            ]);

            $log->update([
                'status' => WhatsAppMessageLog::STATUS_DRY_RUN,
                'provider_response' => ['dry_run' => true, 'payload' => $sanitizedRequestPayload],
            ]);

            $this->syncLegacyDeliveryLog($log, 'sent', null);

            return [
                'success' => true,
                'status' => WhatsAppMessageLog::STATUS_DRY_RUN,
                'provider_message_id' => null,
                'log_id' => (string) $log->id,
                'is_test' => $isTestMessage,
                'is_dry_run' => true,
                'error' => null,
            ];
        }

        // 10. Execute Direct Meta WhatsApp Cloud API Outbound Send
        try {
            $apiResult = $this->cloudApiService->send($metaPayload);

            $providerMessageId = $apiResult['provider_message_id'];
            $responseData = $apiResult['response'];

            self::$lastResponse = $responseData;

            $log->update([
                'status' => WhatsAppMessageLog::STATUS_SENT,
                'provider_message_id' => $providerMessageId,
                'provider_response' => $responseData,
                'sent_at' => now(),
            ]);

            // Sync to whatsapp_messages table for admin live stream
            $this->syncWhatsappMessagesTable($log, $dbTemplate, $templateDef['meta_name'], $actualRecipientPhone, $resolvedUserId, $sanitizedRequestPayload, $responseData, $providerMessageId, 'SENT');

            // Sync to whatsapp_message_delivery_logs for backward compatibility
            $this->syncLegacyDeliveryLog($log, 'sent', $providerMessageId);

            Log::info('[WhatsAppMessageService] WhatsApp message dispatched successfully via Meta Cloud API.', [
                'log_id' => $log->id,
                'template_key' => $templateKey,
                'meta_template' => $templateDef['meta_name'],
                'recipient' => $actualRecipientPhone,
                'provider_message_id' => $providerMessageId,
                'is_test' => $isTestMessage,
            ]);

            return [
                'success' => true,
                'status' => WhatsAppMessageLog::STATUS_SENT,
                'provider_message_id' => $providerMessageId,
                'log_id' => (string) $log->id,
                'is_test' => $isTestMessage,
                'is_dry_run' => false,
                'error' => null,
            ];
        } catch (MetaWhatsAppException $e) {
            $errorCode = (string) ($e->getMetaErrorCode() ?: $e->getHttpStatus());
            $errorMsg = $e->getUserFriendlyMessage();
            $rawError = $e->getRawError();

            self::$lastError = $errorMsg;

            $log->update([
                'status' => WhatsAppMessageLog::STATUS_FAILED,
                'error_code' => $errorCode,
                'error_message' => $errorMsg,
                'provider_response' => is_array($rawError) ? $rawError : ['error' => $errorMsg],
                'failed_at' => now(),
            ]);

            $this->syncWhatsappMessagesTable($log, $dbTemplate, $templateDef['meta_name'], $actualRecipientPhone, $resolvedUserId, $sanitizedRequestPayload, $rawError, null, 'FAILED', $errorCode, $errorMsg);
            $this->syncLegacyDeliveryLog($log, 'failed', null, $errorMsg);

            Log::error('[WhatsAppMessageService] Meta WhatsApp API error sending message.', [
                'log_id' => $log->id,
                'template_key' => $templateKey,
                'error_code' => $errorCode,
                'error_message' => $errorMsg,
            ]);

            return [
                'success' => false,
                'status' => WhatsAppMessageLog::STATUS_FAILED,
                'error_code' => (string) $errorCode,
                'error_message' => $errorMsg,
                'provider_message_id' => null,
                'log_id' => (string) $log->id,
                'is_test' => $isTestMessage,
                'is_dry_run' => false,
                'error' => $errorMsg,
            ];
        } catch (Throwable $e) {
            $errorMsg = 'Exception: '.$e->getMessage();
            self::$lastError = $errorMsg;

            $log->update([
                'status' => WhatsAppMessageLog::STATUS_FAILED,
                'error_code' => '500',
                'error_message' => $errorMsg,
                'failed_at' => now(),
            ]);

            $this->syncLegacyDeliveryLog($log, 'failed', null, $errorMsg);

            Log::error('[WhatsAppMessageService] Unexpected exception sending WhatsApp message: '.$e->getMessage());

            return [
                'success' => false,
                'status' => WhatsAppMessageLog::STATUS_FAILED,
                'error_code' => '500',
                'error_message' => $errorMsg,
                'provider_message_id' => null,
                'log_id' => (string) $log->id,
                'is_test' => $isTestMessage,
                'is_dry_run' => false,
                'error' => $errorMsg,
            ];
        }
    }

    /**
     * Record early skipped or failed log entry.
     */
    private function recordSkippedLog(
        string $templateKey,
        string $rawPhone,
        string $normalizedPhone,
        ?string $actualRecipientPhone,
        array $payload,
        ?string $userId,
        ?string $eventKey,
        ?string $idempotencyKey,
        string $errorMessage,
        bool $isTest,
        string $status = WhatsAppMessageLog::STATUS_FAILED,
        ?string $errorCode = null
    ): WhatsAppMessageLog {
        $maskedPayload = $this->payloadBuilder->sanitizeVariables($payload);

        return WhatsAppMessageLog::create([
            'template_key' => $templateKey,
            'template_name' => $templateKey,
            'event_key' => $eventKey,
            'user_id' => $userId,
            'recipient_phone' => $actualRecipientPhone ?: ($normalizedPhone ?: $rawPhone),
            'requested_recipient_phone' => $normalizedPhone ?: $rawPhone,
            'actual_recipient_phone' => $actualRecipientPhone,
            'payload' => $maskedPayload,
            'status' => $status,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'is_test' => $isTest,
            'idempotency_key' => $idempotencyKey,
            'failed_at' => $status === WhatsAppMessageLog::STATUS_SKIPPED ? null : now(),
        ]);
    }

    /**
     * Calculate event-specific idempotency key.
     */
    public function generateIdempotencyKey(
        string $templateKey,
        ?string $userId,
        string $phone,
        array $payload,
        ?string $eventKey
    ): ?string {
        // OTP messages are valid on every request; do not prevent legitimate repeated OTP requests
        if ($templateKey === 'otp_verification' || str_contains($templateKey, 'otp')) {
            return null;
        }

        // Daily habits: include day_number and date
        if (isset($payload['day_number'])) {
            $day = (string) $payload['day_number'];
            $uid = $userId ?: $phone;

            return "habit_{$uid}_day{$day}_".now()->format('Y-m-d');
        }

        // Event registrations: unique per registration_id
        if (isset($payload['registration_id'])) {
            return "event_reg_{$payload['registration_id']}";
        }

        // One-time onboarding templates (welcome, wear_the_badge, founder engagement)
        if (in_array($templateKey, ['welcome', 'wear_the_badge', 'engagement_founder'], true)) {
            $uid = $userId ?: $phone;

            return "onboarding_{$templateKey}_{$uid}";
        }

        // Profile completion reminder: daily throttle
        if ($templateKey === 'profile_completion_reminder') {
            $uid = $userId ?: $phone;

            return "profile_rem_{$uid}_".now()->format('Y-m-d');
        }

        // Circle recommendation: once per user per circle
        if ($templateKey === 'circle_calling_day3') {
            $circle = $payload['circle_name'] ?? 'general';
            $uid = $userId ?: $phone;

            return "circle_rem_{$uid}_{$circle}";
        }

        return null;
    }

    /**
     * Check if a message with this idempotency key was already successfully sent.
     */
    private function isDuplicate(string $idempotencyKey): bool
    {
        try {
            return WhatsAppMessageLog::query()
                ->where('idempotency_key', $idempotencyKey)
                ->whereIn('status', [WhatsAppMessageLog::STATUS_SENT, 'sent'])
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Normalize international phone numbers to standard format (e.g. 919876543210).
     */
    public static function normalizePhone(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '';
        }

        $trimmed = trim($phone);
        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return '';
        }

        if ($hasPlus) {
            return $digits;
        }

        if (str_starts_with($trimmed, '00') && strlen($digits) > 2) {
            return substr($digits, 2);
        }

        // 10-digit Indian mobile number
        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        // 11-digit starting with 0
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '91'.substr($digits, 1);
        }

        // 12-digit Indian number starting with 91
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return $digits;
        }

        // 11-digit US number starting with 1
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return $digits;
        }

        return $digits;
    }

    /**
     * Locate a user ID by registered phone number.
     */
    private function lookupUserIdByPhone(string $normalizedPhone): ?string
    {
        try {
            if (! Schema::hasTable('users')) {
                return null;
            }

            $userQuery = User::query()->where('phone', $normalizedPhone);
            if (Schema::hasColumn('users', 'secondary_mobile')) {
                $userQuery->orWhere('secondary_mobile', $normalizedPhone);
            }

            return $userQuery->value('id');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Synchronize with whatsapp_messages table for existing admin live stream.
     */
    private function syncWhatsappMessagesTable(
        WhatsAppMessageLog $log,
        ?WhatsappTemplate $template,
        string $templateName,
        string $recipientPhone,
        ?string $userId,
        array $requestPayload,
        mixed $responsePayload,
        ?string $metaMessageId,
        string $status,
        ?string $errorCode = null,
        ?string $errorMessage = null
    ): void {
        try {
            if (! Schema::hasTable('whatsapp_messages')) {
                return;
            }

            WhatsappMessage::create([
                'user_id' => $userId,
                'recipient_phone' => $recipientPhone,
                'whatsapp_template_id' => $template?->id,
                'meta_template_id' => $template?->meta_template_id,
                'template_name' => $templateName,
                'template_language' => $template?->language ?? 'en_IN',
                'message_type' => 'template',
                'status' => $status,
                'meta_message_id' => $metaMessageId,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'request_payload' => $requestPayload,
                'response_payload' => is_array($responsePayload) ? $responsePayload : ['raw' => (string) $responsePayload],
                'sent_at' => $status === 'SENT' ? now() : null,
                'failed_at' => $status === 'FAILED' ? now() : null,
            ]);
        } catch (Throwable $e) {
            Log::debug('[WhatsAppMessageService] Could not sync whatsapp_messages record: '.$e->getMessage());
        }
    }

    /**
     * Synchronize with whatsapp_message_delivery_logs for backward compatibility.
     */
    private function syncLegacyDeliveryLog(
        WhatsAppMessageLog $log,
        string $status,
        ?string $providerMessageId,
        ?string $errorMessage = null
    ): void {
        try {
            if (! Schema::hasTable('whatsapp_message_delivery_logs')) {
                return;
            }

            WhatsappMessageDeliveryLog::create([
                'user_id' => $log->user_id,
                'template_key' => $log->template_key,
                'template_name' => $log->template_name,
                'phone' => $log->recipient_phone,
                'provider' => 'cloud_api',
                'provider_message_id' => $providerMessageId,
                'status' => $status,
                'error_message' => $errorMessage,
                'request_payload' => $log->request_payload,
                'response_payload' => $log->provider_response,
                'attempted_at' => now(),
                'delivered_at' => $status === 'sent' ? now() : null,
            ]);
        } catch (Throwable $e) {
            Log::debug('[WhatsAppMessageService] Could not sync whatsapp_message_delivery_logs: '.$e->getMessage());
        }
    }
}
