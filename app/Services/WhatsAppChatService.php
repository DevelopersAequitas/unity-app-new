<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MetaWhatsAppException;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class WhatsAppChatService
{
    public function __construct(
        protected MetaWhatsAppService $metaService
    ) {}

    /**
     * Get all conversations sorted by latest activity.
     *
     * @return Collection<int, WhatsappConversation>
     */
    public function getConversations(?string $search = null): Collection
    {
        $query = WhatsappConversation::query()
            ->orderByRaw('COALESCE(last_message_at, updated_at, created_at) DESC');

        if (! empty($search)) {
            $term = trim($search);
            $digits = preg_replace('/\D+/', '', $term);

            $query->where(function ($q) use ($term, $digits) {
                $q->where('contact_name', 'like', "%{$term}%")
                    ->orWhere('last_message_preview', 'like', "%{$term}%");

                if (! empty($digits)) {
                    $q->orWhere('contact_phone', 'like', "%{$digits}%");
                }
            });
        }

        return $query->get();
    }

    /**
     * Get conversation by ID with messages and CRM details.
     */
    public function getConversation(string $conversationId): ?WhatsappConversation
    {
        return WhatsappConversation::with('assignedUser')->find($conversationId);
    }

    /**
     * Get messages for a conversation and automatically trigger read receipts on Meta.
     *
     * @return Collection<int, WhatsappMessage>
     */
    public function getConversationMessages(string $conversationId): Collection
    {
        $conversation = WhatsappConversation::findOrFail($conversationId);

        // Fetch messages
        $messages = WhatsappMessage::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get();

        // Mark inbound unread messages as READ
        $this->markConversationAsRead($conversationId);

        return $messages;
    }

    /**
     * Mark all inbound messages as read for this conversation,
     * resetting unread count and notifying Meta Cloud API to display BLUE TICKS.
     */
    public function markConversationAsRead(string $conversationId): int
    {
        $conversation = WhatsappConversation::find($conversationId);
        if (! $conversation) {
            return 0;
        }

        $unreadInbound = WhatsappMessage::where('conversation_id', $conversationId)
            ->where('direction', 'INBOUND')
            ->where('status', '!=', 'READ')
            ->get();

        $markedCount = 0;

        foreach ($unreadInbound as $msg) {
            $msg->update([
                'status' => 'READ',
                'read_at' => now(),
            ]);
            $markedCount++;

            // Dispatch Meta Cloud API mark-as-read to turn ticks BLUE on customer's phone
            if (! empty($msg->meta_message_id)) {
                try {
                    $this->metaService->markMessageAsRead((string) $msg->meta_message_id);
                } catch (Throwable $e) {
                    Log::warning('[WhatsAppChatService] Meta mark-as-read dispatch failed: '.$e->getMessage(), [
                        'message_id' => $msg->meta_message_id,
                    ]);
                }
            }
        }

        // Reset conversation unread counter
        if ($conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
        }

        return $markedCount;
    }

    /**
     * Send an outbound message (freeform or template).
     * Enforces the 24-hour customer care service window rule.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException|MetaWhatsAppException
     */
    public function sendMessage(string $conversationId, array $data, ?int $adminUserId = null): WhatsappMessage
    {
        $conversation = WhatsappConversation::findOrFail($conversationId);

        $body = isset($data['body']) ? trim((string) $data['body']) : '';
        $templateName = isset($data['template_name']) ? trim((string) $data['template_name']) : '';
        $languageCode = isset($data['language_code']) ? (string) $data['language_code'] : 'en';
        /** @var array<int, array<string, mixed>> $components */
        $components = isset($data['components']) && is_array($data['components']) ? $data['components'] : [];
        $mediaUrl = isset($data['media_url']) ? (string) $data['media_url'] : null;

        $isWindowActive = $conversation->isWindowActive();

        // 24-hour service window policy enforcement:
        // If window is expired and no template is selected, throw validation error
        if (! $isWindowActive && empty($templateName)) {
            throw ValidationException::withMessages([
                'body' => [
                    'The 24-hour Meta customer care window has expired for this contact. You must send an approved Meta Template Message to re-open the window.',
                ],
            ]);
        }

        // If window is active or template is provided
        $metaMessageId = null;
        $status = 'PENDING';
        $messageType = empty($templateName) ? 'TEXT' : 'TEMPLATE';
        $content = empty($templateName) ? $body : "Template: {$templateName}";

        try {
            if (! empty($templateName)) {
                $response = $this->metaService->sendTemplateMessage(
                    $conversation->contact_phone,
                    $templateName,
                    $languageCode,
                    $components
                );
                $metaMessageId = $response['message_id'];
                $status = 'SENT';
            } else {
                $response = $this->metaService->sendTextMessage(
                    $conversation->contact_phone,
                    $body
                );
                $metaMessageId = $response['message_id'];
                $status = 'SENT';
            }
        } catch (Throwable $e) {
            Log::error('[WhatsAppChatService] Failed to send message via Meta API: '.$e->getMessage(), [
                'conversation_id' => $conversationId,
                'phone' => $conversation->contact_phone,
            ]);

            // Create record with FAILED status
            $failedMessage = WhatsappMessage::create([
                'conversation_id' => $conversation->id,
                'recipient_phone' => $conversation->contact_phone,
                'direction' => 'OUTBOUND',
                'type' => $messageType,
                'body' => $content,
                'media_url' => $mediaUrl,
                'status' => 'FAILED',
                'error_code' => 'META_SEND_ERROR',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        // Create outbound message
        $message = WhatsappMessage::create([
            'conversation_id' => $conversation->id,
            'recipient_phone' => $conversation->contact_phone,
            'meta_message_id' => $metaMessageId,
            'direction' => 'OUTBOUND',
            'type' => $messageType,
            'body' => $content,
            'media_url' => $mediaUrl,
            'status' => $status,
            'sent_at' => now(),
        ]);

        // Update conversation summary
        $conversation->update([
            'last_message_preview' => $content,
            'last_message_at' => now(),
        ]);

        return $message;
    }

    /**
     * Get total unread count across all conversations.
     */
    public function getTotalUnreadCount(): int
    {
        return (int) WhatsappConversation::where('status', '!=', 'ARCHIVED')->sum('unread_count');
    }

    /**
     * Process an incoming message received via Meta Webhook.
     *
     * @param  array<string, mixed>  $messageData
     */
    public function handleIncomingMessage(
        array $messageData,
        ?string $contactName = null,
        ?string $wabaPhoneNumberId = null
    ): WhatsappMessage {
        $from = trim((string) ($messageData['from'] ?? ''));
        $wamid = trim((string) ($messageData['id'] ?? ''));
        $type = strtoupper((string) ($messageData['type'] ?? 'TEXT'));
        $timestamp = isset($messageData['timestamp']) ? (int) $messageData['timestamp'] : null;
        $eventTime = $timestamp ? Carbon::createFromTimestamp($timestamp) : now();

        // Extract body text based on message type
        $body = match (strtolower($type)) {
            'text' => (string) ($messageData['text']['body'] ?? ''),
            'image' => (string) ($messageData['image']['caption'] ?? '[Photo]'),
            'document' => (string) ($messageData['document']['caption'] ?? ($messageData['document']['filename'] ?? '[Document]')),
            'audio' => '[Voice Message]',
            'video' => (string) ($messageData['video']['caption'] ?? '[Video]'),
            'interactive' => (string) ($messageData['interactive']['button_reply']['title'] ?? ($messageData['interactive']['list_reply']['title'] ?? '[Interactive Reply]')),
            'button' => (string) ($messageData['button']['text'] ?? '[Button Selection]'),
            'location' => '[Shared Location: '.($messageData['location']['name'] ?? 'Pin').']',
            default => '[WhatsApp Message]',
        };

        // Find or create conversation by phone
        $conversation = WhatsappConversation::where('contact_phone', $from)->first();

        // Check if phone matches any registered user
        if (! $conversation) {
            $user = User::where('phone', $from)
                ->orWhere('phone', 'like', '%'.substr($from, -10))
                ->first();

            $userName = $user ? ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))) : null;
            $initialName = $contactName ?: ($userName ?: 'WhatsApp Contact (+'.$from.')');

            $conversation = WhatsappConversation::create([
                'contact_phone' => $from,
                'contact_name' => $initialName,
                'status' => 'OPEN',
                'unread_count' => 0,
                'window_expires_at' => now()->addHours(24),
            ]);
        } else {
            // Update contact name with real WhatsApp profile name
            if (! empty($contactName) && ($contactName !== 'DOOT Test User' && $contactName !== 'WhatsApp Tester')) {
                $conversation->contact_name = $contactName;
            } elseif (! empty($contactName) && (empty($conversation->contact_name) || str_contains((string) $conversation->contact_name, 'Test') || str_contains((string) $conversation->contact_name, 'WhatsApp Contact'))) {
                $conversation->contact_name = $contactName;
            }
        }

        // Deduplication check for message
        $existingMessage = WhatsappMessage::where('meta_message_id', $wamid)->first();
        if ($existingMessage) {
            return $existingMessage;
        }

        // Create incoming message record
        $message = WhatsappMessage::create([
            'conversation_id' => $conversation->id,
            'recipient_phone' => $from,
            'meta_message_id' => $wamid,
            'direction' => 'INBOUND',
            'type' => $type,
            'body' => $body,
            'status' => 'DELIVERED',
            'delivered_at' => $eventTime,
            'created_at' => $eventTime,
        ]);

        // Increment unread count, set preview, and refresh 24h window
        $conversation->update([
            'contact_name' => $conversation->contact_name,
            'unread_count' => ((int) $conversation->unread_count) + 1,
            'last_message_preview' => $body,
            'last_message_at' => $eventTime,
            'window_expires_at' => now()->addHours(24),
        ]);

        return $message;
    }

    /**
     * Process status update webhook event from Meta (SENT, DELIVERED, READ, FAILED).
     *
     * @param  array<string, mixed>  $statusItem
     */
    public function handleStatusUpdate(array $statusItem): void
    {
        $wamid = trim((string) ($statusItem['id'] ?? ''));
        $rawStatus = strtoupper(trim((string) ($statusItem['status'] ?? '')));
        $timestamp = isset($statusItem['timestamp']) ? (int) $statusItem['timestamp'] : null;
        $eventTime = $timestamp ? Carbon::createFromTimestamp($timestamp) : now();

        if (empty($wamid)) {
            return;
        }

        $message = WhatsappMessage::where('meta_message_id', $wamid)->first();
        if (! $message) {
            return;
        }

        $updates = [];

        switch ($rawStatus) {
            case 'READ':
                $updates['status'] = 'READ';
                $updates['read_at'] = $message->read_at ?? $eventTime;
                $updates['delivered_at'] = $message->delivered_at ?? $eventTime;
                break;
            case 'DELIVERED':
                // Do not downgrade if already marked READ
                if ($message->status !== 'READ') {
                    $updates['status'] = 'DELIVERED';
                    $updates['delivered_at'] = $message->delivered_at ?? $eventTime;
                }
                break;
            case 'SENT':
                if (in_array($message->status, ['PENDING', null], true)) {
                    $updates['status'] = 'SENT';
                    $updates['sent_at'] = $message->sent_at ?? $eventTime;
                }
                break;
            case 'FAILED':
                $updates['status'] = 'FAILED';
                $updates['failed_at'] = $eventTime;
                $errors = (array) ($statusItem['errors'] ?? []);
                $firstError = (array) ($errors[0] ?? []);
                $code = (string) ($firstError['code'] ?? 'META_ERROR');
                $title = (string) ($firstError['title'] ?? ($firstError['message'] ?? ''));
                $details = isset($firstError['error_data']['details']) ? (string) $firstError['error_data']['details'] : '';

                // Helpful fallback descriptions for standard Meta WhatsApp error codes
                if (empty($title)) {
                    $title = match ($code) {
                        '131026' => 'Message Undeliverable (Recipient cannot receive message or inactive)',
                        '131047' => '24-Hour Customer Care Window Closed (Template message required)',
                        '131042' => 'Business Eligibility or Payment Issue',
                        '131051' => 'Unsupported message type',
                        '130429' => 'Meta Rate Limit Exceeded',
                        default => 'Delivery failed',
                    };
                }

                $updates['error_code'] = $code;
                $updates['error_message'] = trim($title.($details !== '' ? " - {$details}" : ''));
                break;
        }

        if (! empty($updates)) {
            $message->update($updates);
        }
    }

    /**
     * Simulate a status update for testing and verification purposes.
     */
    public function simulateStatusUpdate(string $messageId, string $status, ?string $errorCode = null, ?string $errorMessage = null): ?WhatsappMessage
    {
        $message = WhatsappMessage::find($messageId);
        if (! $message) {
            return null;
        }

        $now = now();
        $statusUpper = strtoupper($status);
        $updates = ['status' => $statusUpper];

        if ($statusUpper === 'SENT') {
            $updates['sent_at'] = $message->sent_at ?? $now;
        } elseif ($statusUpper === 'DELIVERED') {
            $updates['sent_at'] = $message->sent_at ?? $now;
            $updates['delivered_at'] = $now;
        } elseif ($statusUpper === 'READ') {
            $updates['sent_at'] = $message->sent_at ?? $now;
            $updates['delivered_at'] = $message->delivered_at ?? $now;
            $updates['read_at'] = $now;
        } elseif ($statusUpper === 'FAILED') {
            $updates['failed_at'] = $now;
            $updates['error_code'] = $errorCode ?: '131047';
            $updates['error_message'] = $errorMessage ?: '24-Hour Customer Care Window Closed (Template message required)';
        }

        $message->update($updates);

        return $message;
    }
}
