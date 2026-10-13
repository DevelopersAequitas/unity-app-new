<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappMessage;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsappTemplate;
use App\Models\WhatsappWebhookEvent;
use App\Services\WhatsApp\WhatsAppBulkCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaWhatsAppWebhookController extends Controller
{
    /**
     * Handle Meta Webhook verification handshake (GET).
     */
    public function verify(Request $request): Response|JsonResponse
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $expectedToken = Config::get('whatsapp.meta.webhook_verify_token', 'unity_whatsapp_webhook_verify_token_2026');

        if ($mode === 'subscribe' && $token === $expectedToken && $challenge !== null) {
            Log::info('[MetaWhatsAppWebhookController] Webhook verification handshake successful.');

            return response((string) $challenge, 200, ['Content-Type' => 'text/plain']);
        }

        Log::warning('[MetaWhatsAppWebhookController] Webhook verification failed.', [
            'mode' => $mode,
            'token_match' => ($token === $expectedToken),
        ]);

        return response()->json(['error' => 'Forbidden'], 403);
    }

    /**
     * Handle incoming Meta Webhook notification events (POST).
     */
    public function handle(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = (array) $request->all();
        $rawContent = $request->getContent();
        $payloadHash = hash('sha256', $rawContent ?: json_encode($payload));

        Log::info('[MetaWhatsAppWebhookController] Inbound Meta webhook received.', [
            'payload_hash' => $payloadHash,
            'keys' => array_keys($payload),
        ]);

        // Deduplication check
        $existingEvent = WhatsappWebhookEvent::where('payload_hash', $payloadHash)->first();
        if ($existingEvent) {
            Log::info('[MetaWhatsAppWebhookController] Duplicate webhook event ignored.', [
                'payload_hash' => $payloadHash,
                'existing_id' => $existingEvent->id,
            ]);

            return response()->json(['status' => 'duplicate_ignored'], 200);
        }

        $eventType = 'unknown';
        $wabaId = null;
        $phoneNumberId = null;
        $metaMessageId = null;
        $metaTemplateId = null;
        $processedCount = 0;

        try {
            if (isset($payload['entry']) && is_array($payload['entry'])) {
                foreach ($payload['entry'] as $entry) {
                    $wabaId = isset($entry['id']) ? (string) $entry['id'] : $wabaId;

                    if (! isset($entry['changes']) || ! is_array($entry['changes'])) {
                        continue;
                    }

                    foreach ($entry['changes'] as $change) {
                        $field = (string) ($change['field'] ?? '');
                        $value = (array) ($change['value'] ?? []);

                        // 1. Message Status Updates (field == 'messages')
                        if ($field === 'messages') {
                            $eventType = 'messages';
                            $metadata = (array) ($value['metadata'] ?? []);
                            $phoneNumberId = (string) ($metadata['phone_number_id'] ?? $phoneNumberId);

                            if (isset($value['statuses']) && is_array($value['statuses'])) {
                                foreach ($value['statuses'] as $statusItem) {
                                    $wamid = (string) ($statusItem['id'] ?? '');
                                    $metaMessageId = $wamid ?: $metaMessageId;
                                    $this->processMessageStatusUpdate($statusItem, $payload);
                                    $processedCount++;
                                }
                            }
                        }

                        // 2. Template Status Updates (field == 'message_template_status_update')
                        if ($field === 'message_template_status_update') {
                            $eventType = 'message_template_status_update';
                            $metaTemplateId = isset($value['message_template_id']) ? (string) $value['message_template_id'] : null;
                            $this->processTemplateStatusUpdate($value);
                            $processedCount++;
                        }
                    }
                }
            }

            // Save raw webhook event for audit trail
            WhatsappWebhookEvent::create([
                'event_type' => $eventType,
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
                'meta_message_id' => $metaMessageId,
                'meta_template_id' => $metaTemplateId,
                'payload_hash' => $payloadHash,
                'payload' => $payload,
                'processing_status' => 'processed',
                'processed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'event_type' => $eventType,
                'processed_count' => $processedCount,
            ], 200);
        } catch (Throwable $e) {
            Log::error('[MetaWhatsAppWebhookController] Webhook handling error: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            WhatsappWebhookEvent::create([
                'event_type' => $eventType,
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
                'meta_message_id' => $metaMessageId,
                'meta_template_id' => $metaTemplateId,
                'payload_hash' => $payloadHash,
                'payload' => $payload,
                'processing_status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            return response()->json(['error' => 'Webhook processing failed', 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * Process single message status item (sent, delivered, read, failed).
     *
     * @param  array<string, mixed>  $statusItem
     * @param  array<string, mixed>  $fullPayload
     */
    protected function processMessageStatusUpdate(array $statusItem, array $fullPayload): void
    {
        $wamid = trim((string) ($statusItem['id'] ?? ''));
        $rawStatus = strtolower((string) ($statusItem['status'] ?? ''));
        $timestamp = isset($statusItem['timestamp']) ? (int) $statusItem['timestamp'] : null;
        $eventTime = $timestamp ? now()->setTimestamp($timestamp) : now();

        if ($wamid === '') {
            return;
        }

        $message = WhatsappMessage::where('meta_message_id', $wamid)->first();
        $messageLog = WhatsAppMessageLog::where('provider_message_id', $wamid)->first();

        if (! $message && ! $messageLog) {
            try {
                $errCode = isset($statusItem['errors'][0]['code']) ? (string) $statusItem['errors'][0]['code'] : null;
                $errMsg = isset($statusItem['errors'][0]['title']) ? (string) $statusItem['errors'][0]['title'] : null;
                app(WhatsAppBulkCampaignService::class)->handleWebhookStatus(
                    $wamid,
                    $rawStatus,
                    $errCode,
                    $errMsg
                );
            } catch (Throwable $bulkEx) {
                Log::debug('[MetaWhatsAppWebhookController] Bulk campaign sync error on unlinked message: '.$bulkEx->getMessage());
            }

            Log::warning('[MetaWhatsAppWebhookController] No matching message records found for meta_message_id.', [
                'meta_message_id' => $wamid,
                'status' => $rawStatus,
            ]);

            return;
        }

        $currentResponse = is_array($message?->response_payload) ? $message->response_payload : (is_array($messageLog?->provider_response) ? $messageLog->provider_response : []);
        $updatedResponse = array_merge($currentResponse, [
            'last_webhook_status' => $rawStatus,
            'last_webhook_time' => $eventTime->toIso8601String(),
            'webhook_raw_status' => $statusItem,
        ]);

        if ($rawStatus === 'failed') {
            $errors = (array) ($statusItem['errors'] ?? []);
            $firstError = $errors[0] ?? [];
            $errorCode = (string) ($firstError['code'] ?? '131026');
            $errorMessage = (string) ($firstError['title'] ?? ($firstError['message'] ?? 'Delivery failed'));
            $errorDetails = (string) ($firstError['error_data']['details'] ?? '');
            if ($errorDetails !== '') {
                $errorMessage .= " - {$errorDetails}";
            }

            if ($message) {
                $message->update([
                    'status' => 'FAILED',
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'response_payload' => $updatedResponse,
                    'failed_at' => $eventTime,
                ]);
            }

            WhatsAppMessageLog::where('provider_message_id', $wamid)->update([
                'status' => WhatsAppMessageLog::STATUS_FAILED,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'provider_response' => $updatedResponse,
                'failed_at' => $eventTime,
            ]);

            try {
                app(WhatsAppBulkCampaignService::class)->handleWebhookStatus(
                    $wamid,
                    'failed',
                    $errorCode,
                    $errorMessage
                );
            } catch (Throwable $bulkEx) {
                Log::debug('[MetaWhatsAppWebhookController] Bulk campaign sync skipped: '.$bulkEx->getMessage());
            }

            return;
        }

        if ($rawStatus === 'read') {
            if ($message) {
                $message->update([
                    'status' => 'READ',
                    'read_at' => $message->read_at ?? $eventTime,
                    'delivered_at' => $message->delivered_at ?? $eventTime,
                    'response_payload' => $updatedResponse,
                ]);
            }

            WhatsAppMessageLog::where('provider_message_id', $wamid)->update([
                'status' => WhatsAppMessageLog::STATUS_READ,
                'provider_response' => $updatedResponse,
            ]);

            Log::info('[MetaWhatsAppWebhookController] WhatsApp message marked READ.', [
                'meta_message_id' => $wamid,
            ]);

            try {
                app(WhatsAppBulkCampaignService::class)->handleWebhookStatus(
                    $wamid,
                    'read'
                );
            } catch (Throwable $bulkEx) {
                Log::debug('[MetaWhatsAppWebhookController] Bulk campaign sync skipped: '.$bulkEx->getMessage());
            }

            return;
        }

        if ($rawStatus === 'delivered') {
            if ($message) {
                $newStatus = ($message->status === 'READ') ? 'READ' : 'DELIVERED';
                $message->update([
                    'status' => $newStatus,
                    'delivered_at' => $message->delivered_at ?? $eventTime,
                    'response_payload' => $updatedResponse,
                ]);
            }

            WhatsAppMessageLog::where('provider_message_id', $wamid)
                ->where('status', '!=', WhatsAppMessageLog::STATUS_READ)
                ->update([
                    'status' => WhatsAppMessageLog::STATUS_DELIVERED,
                    'provider_response' => $updatedResponse,
                ]);

            Log::info('[MetaWhatsAppWebhookController] WhatsApp message marked DELIVERED.', [
                'meta_message_id' => $wamid,
            ]);

            try {
                app(WhatsAppBulkCampaignService::class)->handleWebhookStatus(
                    $wamid,
                    'delivered'
                );
            } catch (Throwable $bulkEx) {
                Log::debug('[MetaWhatsAppWebhookController] Bulk campaign sync skipped: '.$bulkEx->getMessage());
            }

            return;
        }

        if ($rawStatus === 'sent') {
            // Only update if currently PENDING
            if ($message && $message->status === 'PENDING') {
                $message->update([
                    'status' => 'SENT',
                    'sent_at' => $message->sent_at ?? $eventTime,
                    'response_payload' => $updatedResponse,
                ]);
            }

            WhatsAppMessageLog::where('provider_message_id', $wamid)
                ->where('status', WhatsAppMessageLog::STATUS_PENDING)
                ->update([
                    'status' => WhatsAppMessageLog::STATUS_SENT,
                    'sent_at' => $eventTime,
                    'provider_response' => $updatedResponse,
                ]);

            try {
                app(WhatsAppBulkCampaignService::class)->handleWebhookStatus(
                    $wamid,
                    'sent'
                );
            } catch (Throwable $bulkEx) {
                Log::debug('[MetaWhatsAppWebhookController] Bulk campaign sync skipped: '.$bulkEx->getMessage());
            }

            return;
        }

        // Generic payload update for other statuses
        $message?->update([
            'response_payload' => $updatedResponse,
        ]);

        WhatsAppMessageLog::where('provider_message_id', $wamid)->update([
            'provider_response' => $updatedResponse,
        ]);
    }

    /**
     * Process template status update webhook from Meta.
     *
     * @param  array<string, mixed>  $value
     */
    protected function processTemplateStatusUpdate(array $value): void
    {
        $metaTemplateId = isset($value['message_template_id']) ? (string) $value['message_template_id'] : null;
        $name = (string) ($value['message_template_name'] ?? '');
        $language = (string) ($value['message_template_language'] ?? '');
        $rawEvent = strtoupper((string) ($value['event'] ?? ''));

        // Map Meta webhook event to template status
        $status = match ($rawEvent) {
            'APPROVED' => 'APPROVED',
            'REJECTED' => 'REJECTED',
            'PAUSED' => 'PAUSED',
            'DISABLED' => 'DISABLED',
            'PENDING_DELETION' => 'DELETED',
            default => $rawEvent ?: 'IN_REVIEW',
        };

        $template = null;
        if ($metaTemplateId) {
            $template = WhatsappTemplate::where('meta_template_id', $metaTemplateId)->first();
        }
        if (! $template && $name !== '') {
            $query = WhatsappTemplate::where('name', $name);
            if ($language !== '') {
                $query->where('language', $language);
            }
            $template = $query->first();
        }

        if ($template) {
            $template->update([
                'status' => $status,
                'last_synced_at' => now(),
            ]);

            Log::info('[MetaWhatsAppWebhookController] Template status updated via webhook.', [
                'template_name' => $name,
                'meta_template_id' => $metaTemplateId,
                'new_status' => $status,
            ]);
        } else {
            Log::warning('[MetaWhatsAppWebhookController] Received template status update for unmatched template.', [
                'template_name' => $name,
                'meta_template_id' => $metaTemplateId,
                'status' => $status,
            ]);
        }
    }
}
