<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MemberAwardSend;
use App\Models\MemberJourneySend;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsappWebhookEvent;
use App\Services\WhatsApp\WhatsAppBulkCampaignService;
use App\Services\WhatsAppChatService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        protected WhatsAppChatService $chatService
    ) {}

    /**
     * Meta Webhook Verification Handshake (GET).
     */
    public function verify(Request $request): Response|JsonResponse
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $configuredToken = (string) Config::get('whatsapp.meta.webhook_verify_token', 'peers_unity_meta_verify_token_2026');
        $validTokens = array_unique(array_filter([
            $configuredToken,
            'peers_unity_meta_verify_token_2026',
            'unity_whatsapp_webhook_verify_token_2026',
        ]));

        if ($mode === 'subscribe' && in_array($token, $validTokens, true) && $challenge !== null) {
            Log::info('[WhatsAppWebhookController] Webhook verification handshake successful.', [
                'token_used' => $token,
            ]);

            return response((string) $challenge, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        Log::warning('[WhatsAppWebhookController] Webhook verification handshake failed.', [
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
        $payloadHash = hash('sha256', $rawContent ?: (string) json_encode($payload));

        Log::info('[WhatsAppWebhookController] Inbound Meta webhook notification received.', [
            'payload_hash' => $payloadHash,
        ]);

        // Deduplication check
        $existingEvent = WhatsappWebhookEvent::where('payload_hash', $payloadHash)->first();
        if ($existingEvent) {
            return response()->json(['status' => 'duplicate_ignored'], 200);
        }

        $processedMessages = 0;
        $processedStatuses = 0;

        try {
            if (isset($payload['entry']) && is_array($payload['entry'])) {
                foreach ($payload['entry'] as $entry) {
                    if (! isset($entry['changes']) || ! is_array($entry['changes'])) {
                        continue;
                    }

                    foreach ($entry['changes'] as $change) {
                        $field = (string) ($change['field'] ?? '');
                        $value = (array) ($change['value'] ?? []);

                        if ($field !== 'messages') {
                            continue;
                        }

                        $contacts = (array) ($value['contacts'] ?? []);
                        $contactMap = [];
                        foreach ($contacts as $c) {
                            $waId = (string) ($c['wa_id'] ?? '');
                            $profileName = (string) ($c['profile']['name'] ?? '');
                            if ($waId !== '' && $profileName !== '') {
                                $contactMap[$waId] = $profileName;
                            }
                        }

                        // 1. Process Inbound Messages
                        if (isset($value['messages']) && is_array($value['messages'])) {
                            foreach ($value['messages'] as $messageData) {
                                $from = (string) ($messageData['from'] ?? '');
                                $contactName = $contactMap[$from] ?? null;

                                $this->chatService->handleIncomingMessage($messageData, $contactName);
                                $processedMessages++;
                            }
                        }

                        // 2. Process Outbound Message Delivery Statuses Across All WhatsApp Modules
                        if (isset($value['statuses']) && is_array($value['statuses'])) {
                            foreach ($value['statuses'] as $statusItem) {
                                $wamid = trim((string) ($statusItem['id'] ?? ''));
                                $rawStatus = strtolower(trim((string) ($statusItem['status'] ?? '')));
                                $timestamp = isset($statusItem['timestamp']) ? (int) $statusItem['timestamp'] : null;
                                $eventTime = $timestamp ? Carbon::createFromTimestamp($timestamp) : now();

                                $errCode = isset($statusItem['errors'][0]['code']) ? (string) $statusItem['errors'][0]['code'] : null;
                                $errMsg = isset($statusItem['errors'][0]['title'])
                                    ? (string) $statusItem['errors'][0]['title']
                                    : (isset($statusItem['errors'][0]['message']) ? (string) $statusItem['errors'][0]['message'] : null);

                                // A. WhatsApp Live Chat (WhatsappMessage)
                                try {
                                    $this->chatService->handleStatusUpdate($statusItem);
                                } catch (Throwable $chatEx) {
                                    Log::debug('[WhatsAppWebhookController] Chat status update skipped: '.$chatEx->getMessage());
                                }

                                if ($wamid !== '') {
                                    // B. Message Logs (WhatsAppMessageLog)
                                    try {
                                        $logQuery = WhatsAppMessageLog::where('provider_message_id', $wamid);
                                        if ($rawStatus === 'read') {
                                            $logQuery->update(['status' => 'read']);
                                        } elseif ($rawStatus === 'delivered') {
                                            $logQuery->where('status', '!=', 'read')->update(['status' => 'delivered']);
                                        } elseif ($rawStatus === 'failed') {
                                            $logQuery->update([
                                                'status' => 'failed',
                                                'error_code' => $errCode,
                                                'error_message' => $errMsg,
                                                'failed_at' => $eventTime,
                                            ]);
                                        }
                                    } catch (Throwable $logEx) {
                                        Log::debug('[WhatsAppWebhookController] MessageLog sync skipped: '.$logEx->getMessage());
                                    }

                                    // C. Bulk Campaign Tracking & Aggregates (WhatsAppBulkCampaignRecipient)
                                    try {
                                        app(WhatsAppBulkCampaignService::class)->handleWebhookStatus(
                                            $wamid,
                                            $rawStatus,
                                            $errCode,
                                            $errMsg
                                        );
                                    } catch (Throwable $bulkEx) {
                                        Log::debug('[WhatsAppWebhookController] Bulk campaign sync skipped: '.$bulkEx->getMessage());
                                    }

                                    // D. Award Recognitions (MemberAwardSend)
                                    try {
                                        $award = MemberAwardSend::where('provider_message_id', $wamid)->first();
                                        if ($award) {
                                            $awardUpdates = [];
                                            if ($rawStatus === 'read') {
                                                $awardUpdates['status'] = 'read';
                                                $awardUpdates['read_at'] = $eventTime;
                                                if (! $award->delivered_at) {
                                                    $awardUpdates['delivered_at'] = $eventTime;
                                                }
                                            } elseif ($rawStatus === 'delivered' && $award->status !== 'read') {
                                                $awardUpdates['status'] = 'delivered';
                                                if (! $award->delivered_at) {
                                                    $awardUpdates['delivered_at'] = $eventTime;
                                                }
                                            } elseif ($rawStatus === 'failed') {
                                                $awardUpdates['status'] = 'failed';
                                                if ($errMsg) {
                                                    $awardUpdates['error_message'] = $errMsg;
                                                }
                                            }
                                            if (! empty($awardUpdates)) {
                                                $award->update($awardUpdates);
                                            }
                                        }
                                    } catch (Throwable $awardEx) {
                                        Log::debug('[WhatsAppWebhookController] Award send sync skipped: '.$awardEx->getMessage());
                                    }

                                    // E. 30-Day Journey (MemberJourneySend)
                                    try {
                                        $journey = MemberJourneySend::where('provider_message_id', $wamid)->first();
                                        if ($journey) {
                                            $journeyUpdates = [];
                                            if ($rawStatus === 'read' || $rawStatus === 'delivered') {
                                                $journeyUpdates['status'] = $rawStatus;
                                            } elseif ($rawStatus === 'failed') {
                                                $journeyUpdates['status'] = 'failed';
                                                if ($errMsg) {
                                                    $journeyUpdates['error_message'] = $errMsg;
                                                }
                                            }
                                            if (! empty($journeyUpdates)) {
                                                $journey->update($journeyUpdates);
                                            }
                                        }
                                    } catch (Throwable $journeyEx) {
                                        Log::debug('[WhatsAppWebhookController] Journey send sync skipped: '.$journeyEx->getMessage());
                                    }
                                }

                                $processedStatuses++;
                            }
                        }
                    }
                }
            }

            // Record audit trail
            WhatsappWebhookEvent::create([
                'event_type' => 'messages',
                'payload_hash' => $payloadHash,
                'payload' => $payload,
                'processing_status' => 'processed',
                'processed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'processed_messages' => $processedMessages,
                'processed_statuses' => $processedStatuses,
            ], 200);
        } catch (Throwable $e) {
            Log::error('[WhatsAppWebhookController] Error handling webhook: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            WhatsappWebhookEvent::create([
                'event_type' => 'messages',
                'payload_hash' => $payloadHash,
                'payload' => $payload,
                'processing_status' => 'failed',
                'error_message' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 200);
        }
    }
}
