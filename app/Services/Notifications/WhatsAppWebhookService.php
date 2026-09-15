<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\WhatsappMessageDeliveryLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WhatsAppWebhookService
{
    /**
     * Process an incoming WhatsApp webhook payload.
     * Supports Meta Cloud API webhook format and FlexiMSG format.
     *
     * @param  array<string, mixed>  $payload
     * @return array{processed_count: int, updated_logs: list<string>}
     */
    public function processWebhook(array $payload): array
    {
        $updatedLogIds = [];
        $statusEvents = $this->extractStatusEvents($payload);

        foreach ($statusEvents as $event) {
            $logId = $this->handleSingleStatusEvent($event, $payload);
            if ($logId !== null) {
                $updatedLogIds[] = $logId;
            }
        }

        return [
            'processed_count' => count($statusEvents),
            'updated_logs' => $updatedLogIds,
        ];
    }

    /**
     * Extract normalized status events from various webhook payload formats.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{
     *     wamid: string,
     *     status: string,
     *     timestamp: ?string,
     *     recipient_id: ?string,
     *     errors: list<array<string, mixed>>,
     *     raw_event: array<string, mixed>
     * }>
     */
    public function extractStatusEvents(array $payload): array
    {
        $events = [];

        // 1. Meta Cloud API standard structure: entry[].changes[].value.statuses[]
        if (isset($payload['entry']) && is_array($payload['entry'])) {
            foreach ($payload['entry'] as $entry) {
                if (! is_array($entry) || ! isset($entry['changes']) || ! is_array($entry['changes'])) {
                    continue;
                }

                foreach ($entry['changes'] as $change) {
                    if (! is_array($change)) {
                        continue;
                    }

                    $value = $change['value'] ?? null;
                    if (! is_array($value) || ! isset($value['statuses']) || ! is_array($value['statuses'])) {
                        continue;
                    }

                    foreach ($value['statuses'] as $statusItem) {
                        if (! is_array($statusItem) || empty($statusItem['id'])) {
                            continue;
                        }

                        $events[] = [
                            'wamid' => (string) $statusItem['id'],
                            'status' => strtolower((string) ($statusItem['status'] ?? '')),
                            'timestamp' => isset($statusItem['timestamp']) ? (string) $statusItem['timestamp'] : null,
                            'recipient_id' => isset($statusItem['recipient_id']) ? (string) $statusItem['recipient_id'] : null,
                            'errors' => isset($statusItem['errors']) && is_array($statusItem['errors']) ? $statusItem['errors'] : [],
                            'raw_event' => $statusItem,
                        ];
                    }
                }
            }
        }

        // 2. Direct/flat statuses array: statuses[]
        if (empty($events) && isset($payload['statuses']) && is_array($payload['statuses'])) {
            foreach ($payload['statuses'] as $statusItem) {
                if (! is_array($statusItem) || empty($statusItem['id'])) {
                    continue;
                }

                $events[] = [
                    'wamid' => (string) $statusItem['id'],
                    'status' => strtolower((string) ($statusItem['status'] ?? '')),
                    'timestamp' => isset($statusItem['timestamp']) ? (string) $statusItem['timestamp'] : null,
                    'recipient_id' => isset($statusItem['recipient_id']) ? (string) $statusItem['recipient_id'] : null,
                    'errors' => isset($statusItem['errors']) && is_array($statusItem['errors']) ? $statusItem['errors'] : [],
                    'raw_event' => $statusItem,
                ];
            }
        }

        // 3. Single / FlexiMSG flat structure: wamid / provider_message_id + status
        if (empty($events)) {
            $wamid = $payload['wamid']
                ?? $payload['provider_message_id']
                ?? $payload['message_id']
                ?? $payload['id']
                ?? null;

            if ($wamid !== null && (is_string($wamid) || is_numeric($wamid)) && ! empty($payload['status'])) {
                $errors = [];
                if (isset($payload['errors']) && is_array($payload['errors'])) {
                    $errors = $payload['errors'];
                } elseif (isset($payload['error']) && is_array($payload['error'])) {
                    $errors = [$payload['error']];
                }

                $events[] = [
                    'wamid' => (string) $wamid,
                    'status' => strtolower((string) $payload['status']),
                    'timestamp' => isset($payload['timestamp']) ? (string) $payload['timestamp'] : null,
                    'recipient_id' => isset($payload['recipient_id']) ? (string) $payload['recipient_id'] : ($payload['phone'] ?? null),
                    'errors' => $errors,
                    'raw_event' => $payload,
                ];
            }
        }

        return $events;
    }

    /**
     * Handle a single normalized status event and update WhatsappMessageDeliveryLog.
     *
     * @param  array{
     *     wamid: string,
     *     status: string,
     *     timestamp: ?string,
     *     recipient_id: ?string,
     *     errors: list<array<string, mixed>>,
     *     raw_event: array<string, mixed>
     * }  $event
     * @param  array<string, mixed>  $fullWebhookPayload
     */
    public function handleSingleStatusEvent(array $event, array $fullWebhookPayload = []): ?string
    {
        $wamid = trim($event['wamid']);
        $rawStatus = trim($event['status']);

        if ($wamid === '' || ! Schema::hasTable('whatsapp_message_delivery_logs')) {
            return null;
        }

        try {
            // Strict exact lookup by provider_message_id (wamid) to avoid accidental matches
            $deliveryLog = WhatsappMessageDeliveryLog::where('provider_message_id', $wamid)->first();

            if (! $deliveryLog) {
                Log::warning('[WhatsAppWebhookService] No matching WhatsappMessageDeliveryLog found for wamid.', [
                    'wamid' => $wamid,
                    'status' => $rawStatus,
                ]);

                return null;
            }

            $currentResponsePayload = is_array($deliveryLog->response_payload) ? $deliveryLog->response_payload : [];
            $mergedResponsePayload = array_merge($currentResponsePayload, [
                'webhook_status_event' => $event['raw_event'],
                'webhook_full_payload' => $fullWebhookPayload,
                'last_status_received' => $rawStatus,
                'status_received_at' => now()->toIso8601String(),
            ]);

            if ($rawStatus === 'failed') {
                $errorDetails = $this->formatMetaErrorDetails($event['errors'], $event['raw_event']);

                $deliveryLog->update([
                    'status' => 'failed',
                    'error_message' => $errorDetails['formatted_message'],
                    'response_payload' => $mergedResponsePayload,
                    'delivered_at' => null,
                    'read_at' => null,
                ]);

                Log::error('[WhatsAppWebhookService] WhatsApp message delivery failed reported by Meta status webhook.', [
                    'wamid' => $wamid,
                    'delivery_log_id' => $deliveryLog->id,
                    'template_key' => $deliveryLog->template_key,
                    'phone' => $deliveryLog->phone,
                    'meta_error_code' => $errorDetails['code'],
                    'meta_error_title' => $errorDetails['title'],
                    'meta_error_message' => $errorDetails['message'],
                    'meta_error_details' => $errorDetails['details'],
                    'is_ecosystem_engagement_restriction' => $errorDetails['code'] === 131049,
                ]);

                return (string) $deliveryLog->id;
            }

            if ($rawStatus === 'delivered') {
                // Prevent downgrading read -> delivered on duplicate or out-of-order webhook delivery
                $newStatus = $deliveryLog->status === 'read' ? 'read' : 'delivered';
                $eventTimestamp = $event['timestamp'] ? now()->setTimestamp((int) $event['timestamp']) : now();

                $deliveryLog->update([
                    'status' => $newStatus,
                    'delivered_at' => $deliveryLog->delivered_at ?? $eventTimestamp,
                    'response_payload' => $mergedResponsePayload,
                ]);

                Log::info('[WhatsAppWebhookService] WhatsApp message marked delivered.', [
                    'wamid' => $wamid,
                    'delivery_log_id' => $deliveryLog->id,
                    'template_key' => $deliveryLog->template_key,
                    'status' => $newStatus,
                ]);

                return (string) $deliveryLog->id;
            }

            if ($rawStatus === 'read') {
                $eventTimestamp = $event['timestamp'] ? now()->setTimestamp((int) $event['timestamp']) : now();

                $deliveryLog->update([
                    'status' => 'read',
                    'read_at' => $deliveryLog->read_at ?? $eventTimestamp,
                    'delivered_at' => $deliveryLog->delivered_at ?? $eventTimestamp,
                    'response_payload' => $mergedResponsePayload,
                ]);

                Log::info('[WhatsAppWebhookService] WhatsApp message marked read.', [
                    'wamid' => $wamid,
                    'delivery_log_id' => $deliveryLog->id,
                    'template_key' => $deliveryLog->template_key,
                ]);

                return (string) $deliveryLog->id;
            }

            if ($rawStatus === 'sent' || $rawStatus === 'accepted') {
                // Only upgrade pending -> sent; never downgrade delivered/read/failed -> sent
                if ($deliveryLog->status === 'pending') {
                    $deliveryLog->update([
                        'status' => 'sent',
                        'response_payload' => $mergedResponsePayload,
                    ]);
                } else {
                    $deliveryLog->update([
                        'response_payload' => $mergedResponsePayload,
                    ]);
                }

                return (string) $deliveryLog->id;
            }

            // Fallback for unhandled status string: update payload safely without corrupting status
            $deliveryLog->update([
                'response_payload' => $mergedResponsePayload,
            ]);

            return (string) $deliveryLog->id;
        } catch (Throwable $e) {
            Log::error('[WhatsAppWebhookService] Exception processing status event for wamid: '.$e->getMessage(), [
                'wamid' => $wamid,
                'status' => $rawStatus,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Format and extract detailed Meta error breakdown.
     *
     * @param  list<array<string, mixed>>  $errors
     * @param  array<string, mixed>  $rawEvent
     * @return array{
     *     code: ?int,
     *     title: ?string,
     *     message: ?string,
     *     details: ?string,
     *     formatted_message: string
     * }
     */
    public function formatMetaErrorDetails(array $errors, array $rawEvent = []): array
    {
        $primaryError = $errors[0] ?? [];

        $code = isset($primaryError['code']) ? (int) $primaryError['code'] : null;
        $title = isset($primaryError['title']) ? (string) $primaryError['title'] : null;
        $message = isset($primaryError['message']) ? (string) $primaryError['message'] : null;

        $errorData = $primaryError['error_data'] ?? null;
        $details = null;
        if (is_array($errorData) && isset($errorData['details'])) {
            $details = (string) $errorData['details'];
        } elseif (isset($rawEvent['error_message'])) {
            $details = (string) $rawEvent['error_message'];
        }

        // Specific notation for Meta Error 131049
        if ($code === 131049) {
            $formattedMessage = '[Meta Error 131049] Message failed to send: In order to maintain a healthy ecosystem engagement, the message failed to be delivered.';
        } else {
            $parts = [];
            if ($code !== null) {
                $parts[] = "[Meta Error {$code}]";
            }
            if ($title !== null && $title !== '') {
                $parts[] = "{$title}:";
            }
            if ($details !== null && $details !== '') {
                $parts[] = $details;
            } elseif ($message !== null && $message !== '') {
                $parts[] = $message;
            }

            $formattedMessage = ! empty($parts)
                ? implode(' ', $parts)
                : 'Meta delivery failed (no detailed error provided).';
        }

        return [
            'code' => $code,
            'title' => $title,
            'message' => $message,
            'details' => $details,
            'formatted_message' => $formattedMessage,
        ];
    }
}
