<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WhatsApp\SendChatMessageRequest;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Services\WhatsAppChatService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class WhatsAppChatController extends Controller
{
    public function __construct(
        protected WhatsAppChatService $chatService
    ) {}

    /**
     * Display the main 3-column WhatsApp Live Chat & Inbox screen.
     */
    public function index(Request $request): View
    {
        $totalUnread = $this->chatService->getTotalUnreadCount();
        $templates = WhatsappTemplate::where('status', 'APPROVED')->orderBy('name')->get();

        return view('admin.whatsapp.chat', [
            'totalUnread' => $totalUnread,
            'templates' => $templates,
        ]);
    }

    /**
     * Get list of conversations for the Left Column chat list.
     */
    public function getConversations(Request $request): JsonResponse
    {
        try {
            $search = $request->query('q') ? (string) $request->query('q') : null;
            $conversations = $this->chatService->getConversations($search);

            $convIds = $conversations->pluck('id')->all();
            $latestMessages = WhatsappMessage::whereIn('conversation_id', $convIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('conversation_id')
                ->map(fn ($msgs) => $msgs->first());

            $data = $conversations->map(function (WhatsappConversation $conv) use ($latestMessages) {
                $isWindowActive = $conv->isWindowActive();
                $latestMsg = $latestMessages->get($conv->id);

                $lastMessageAt = $conv->last_message_at
                    ? ($conv->last_message_at instanceof Carbon ? $conv->last_message_at : Carbon::parse($conv->last_message_at))
                    : null;

                $windowExpiresAt = $conv->window_expires_at
                    ? ($conv->window_expires_at instanceof Carbon ? $conv->window_expires_at : Carbon::parse($conv->window_expires_at))
                    : null;

                return [
                    'id' => (string) $conv->id,
                    'contact_phone' => (string) $conv->contact_phone,
                    'contact_name' => $conv->contact_name ?: ('Contact +'.$conv->contact_phone),
                    'avatar_letter' => strtoupper(substr((string) ($conv->contact_name ?: $conv->contact_phone), 0, 1)),
                    'unread_count' => (int) $conv->unread_count,
                    'last_message_preview' => $conv->last_message_preview ?: 'No messages yet',
                    'last_message_direction' => $latestMsg?->direction,
                    'last_message_status' => $latestMsg ? strtoupper((string) $latestMsg->status) : null,
                    'last_message_at' => $lastMessageAt ? $lastMessageAt->toIso8601String() : null,
                    'last_message_time_formatted' => $this->formatMessageTime($lastMessageAt),
                    'is_window_active' => $isWindowActive,
                    'window_expires_at' => $windowExpiresAt ? $windowExpiresAt->toIso8601String() : null,
                    'window_remaining' => $conv->window_remaining_formatted,
                    'status' => (string) $conv->status,
                ];
            });

            return response()->json([
                'success' => true,
                'conversations' => $data,
                'total_unread' => $this->chatService->getTotalUnreadCount(),
            ]);
        } catch (Throwable $e) {
            Log::error('[WhatsAppChatController] getConversations error: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'conversations' => [],
                'total_unread' => 0,
            ], 500);
        }
    }

    /**
     * Get messages for a specific conversation and trigger blue-tick read receipts.
     */
    public function getMessages(string $id): JsonResponse
    {
        try {
            $conversation = $this->chatService->getConversation($id);
            if (! $conversation) {
                return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
            }

            $messages = $this->chatService->getConversationMessages($id);

            // Fetch matched CRM user if available
            $user = User::where('phone', $conversation->contact_phone)
                ->orWhere('phone', 'like', '%'.substr($conversation->contact_phone, -10))
                ->first();

            $formattedMessages = $messages->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'meta_message_id' => $msg->meta_message_id,
                    'direction' => $msg->direction,
                    'type' => $msg->type,
                    'body' => $msg->body,
                    'media_url' => $msg->media_url,
                    'status' => strtoupper((string) $msg->status),
                    'error_code' => $msg->error_code,
                    'error_message' => $msg->error_message,
                    'sent_at' => $msg->sent_at ? $msg->sent_at->toIso8601String() : null,
                    'delivered_at' => $msg->delivered_at ? $msg->delivered_at->toIso8601String() : null,
                    'read_at' => $msg->read_at ? $msg->read_at->toIso8601String() : null,
                    'failed_at' => $msg->failed_at ? $msg->failed_at->toIso8601String() : null,
                    'created_at' => $msg->created_at ? $msg->created_at->toIso8601String() : null,
                    'time_formatted' => $msg->created_at ? $msg->created_at->format('h:i A') : '',
                    'date_formatted' => $msg->created_at ? $msg->created_at->format('Y-m-d') : '',
                ];
            });

            return response()->json([
                'success' => true,
                'conversation' => [
                    'id' => $conversation->id,
                    'contact_phone' => $conversation->contact_phone,
                    'contact_name' => $conversation->contact_name ?: ('Contact +'.$conversation->contact_phone),
                    'is_window_active' => $conversation->isWindowActive(),
                    'window_expires_at' => $conversation->window_expires_at ? $conversation->window_expires_at->toIso8601String() : null,
                    'window_remaining' => $conversation->window_remaining_formatted,
                    'unread_count' => 0,
                    'crm_user' => $user ? [
                        'id' => $user->id,
                        'name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                        'email' => $user->email,
                        'company' => $user->company_name ?? 'Individual Member',
                        'city' => $user->city ?? 'N/A',
                        'membership' => $user->membership_status ?? 'Active Member',
                        'joined_at' => $user->created_at ? $user->created_at->format('M d, Y') : 'N/A',
                    ] : null,
                ],
                'messages' => $formattedMessages,
            ]);
        } catch (Throwable $e) {
            Log::error('[WhatsAppChatController] Error fetching messages: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Send an outbound message (freeform or template).
     */
    public function sendMessage(SendChatMessageRequest $request, string $id): JsonResponse
    {
        try {
            $adminUser = Auth::guard('admin')->user();
            $adminUserId = $adminUser ? (int) $adminUser->id : null;

            $message = $this->chatService->sendMessage(
                $id,
                $request->validated(),
                $adminUserId
            );

            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $message->id,
                    'direction' => $message->direction,
                    'type' => $message->type,
                    'body' => $message->body,
                    'status' => $message->status,
                    'time_formatted' => now()->format('h:i A'),
                    'date_formatted' => now()->format('Y-m-d'),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('[WhatsAppChatController] Send message error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark conversation as read.
     */
    public function markAsRead(string $id): JsonResponse
    {
        $marked = $this->chatService->markConversationAsRead($id);

        return response()->json([
            'success' => true,
            'marked_count' => $marked,
            'total_unread' => $this->chatService->getTotalUnreadCount(),
        ]);
    }

    /**
     * Get real-time unread count for topbar & sidebar pollers.
     */
    public function getTotalUnreadCount(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'total_unread' => $this->chatService->getTotalUnreadCount(),
        ]);
    }

    /**
     * Test simulator endpoint to test incoming customer messages during local development.
     */
    public function simulateIncoming(Request $request): JsonResponse
    {
        $phone = $request->input('phone', '916353025164');
        $body = $request->input('body', 'Hello, this is a simulated message from WhatsApp!');
        $name = $request->input('name', 'WhatsApp Tester');

        $messageData = [
            'from' => preg_replace('/\D+/', '', (string) $phone),
            'id' => 'sim_'.time().'_'.rand(1000, 9999),
            'type' => 'text',
            'timestamp' => time(),
            'text' => [
                'body' => (string) $body,
            ],
        ];

        $incoming = $this->chatService->handleIncomingMessage($messageData, (string) $name);

        return response()->json([
            'success' => true,
            'message' => 'Simulated incoming message processed',
            'data' => $incoming,
            'conversation_id' => $incoming->conversation_id,
        ]);
    }

    /**
     * Simulate a delivery status update for testing & verification.
     */
    public function simulateStatus(Request $request): JsonResponse
    {
        $messageId = (string) $request->input('message_id', '');
        $status = strtoupper((string) $request->input('status', 'DELIVERED'));
        $errorCode = $request->input('error_code');
        $errorMessage = $request->input('error_message');

        if (empty($messageId)) {
            $latest = WhatsappMessage::where('direction', 'OUTBOUND')->latest()->first();
            if (! $latest) {
                return response()->json([
                    'success' => false,
                    'message' => 'No outbound messages found to simulate status.',
                ], 404);
            }
            $messageId = (string) $latest->id;
        }

        $updated = $this->chatService->simulateStatusUpdate(
            $messageId,
            $status,
            $errorCode ? (string) $errorCode : null,
            $errorMessage ? (string) $errorMessage : null
        );

        if (! $updated) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => "Message status successfully updated to {$status}",
            'data' => [
                'id' => $updated->id,
                'status' => $updated->status,
                'error_code' => $updated->error_code,
                'error_message' => $updated->error_message,
                'sent_at' => $updated->sent_at,
                'delivered_at' => $updated->delivered_at,
                'read_at' => $updated->read_at,
                'failed_at' => $updated->failed_at,
                'conversation_id' => $updated->conversation_id,
            ],
        ]);
    }

    /**
     * Format timestamp for WhatsApp chat list snippet.
     */
    private function formatMessageTime(Carbon|string|null $dateTime): string
    {
        if (! $dateTime) {
            return '';
        }

        if (is_string($dateTime)) {
            $dateTime = Carbon::parse($dateTime);
        }

        if ($dateTime->isToday()) {
            return $dateTime->format('h:i A');
        }

        if ($dateTime->isYesterday()) {
            return 'Yesterday';
        }

        if ($dateTime->greaterThan(now()->subDays(6))) {
            return $dateTime->format('l'); // Monday, Tuesday, etc.
        }

        return $dateTime->format('d/m/Y');
    }
}
