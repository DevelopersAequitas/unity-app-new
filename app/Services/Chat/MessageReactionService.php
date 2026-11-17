<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Events\Chat\MessageReactionUpdated;
use App\Models\CircleChatMessage;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use App\Services\CircleChat\CircleChatAccessService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class MessageReactionService
{
    public function __construct(
        private readonly CircleChatAccessService $circleChatAccessService,
    ) {}

    /**
     * Toggle an emoji reaction on a 1-to-1 chat message or circle group chat message.
     *
     * @return array{
     *     action: string,
     *     message_id: string,
     *     chat_id: ?string,
     *     circle_id: ?string,
     *     message_type: string,
     *     reactions: array<int, array<string, mixed>>
     * }
     */
    public function toggleReaction(
        User $user,
        string $messageId,
        string $emoji,
        ?string $expectedChatId = null,
        ?string $expectedCircleId = null
    ): array {
        if (! Schema::hasTable('message_reactions')) {
            throw new HttpException(500, 'Message reactions table is not initialized.');
        }

        $context = $this->resolveAndAuthorizeMessage($user, $messageId, $expectedChatId, $expectedCircleId);
        $userId = (string) $user->id;

        $action = DB::transaction(function () use ($messageId, $userId, $emoji, $context): string {
            $existing = MessageReaction::query()
                ->where('message_id', $messageId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ((string) $existing->emoji === $emoji) {
                    $existing->delete();

                    return 'removed';
                }

                $existing->emoji = $emoji;
                $existing->created_at = now();
                $existing->updated_at = now();
                $existing->save();

                return 'replaced';
            }

            $payload = [
                'message_id' => $messageId,
                'user_id' => $userId,
                'emoji' => $emoji,
            ];

            if (Schema::hasColumn('message_reactions', 'message_type')) {
                $payload['message_type'] = $context['message_type'];
            }

            MessageReaction::query()->create($payload);

            return 'added';
        });

        $reactionModels = MessageReaction::query()
            ->with('user:id,display_name,first_name,last_name,profile_photo_url,profile_photo_file_id')
            ->where('message_id', $messageId)
            ->orderBy('created_at')
            ->get();

        $formattedReactions = self::formatReactionsCollection($reactionModels, $userId);

        // Broadcast realtime socket event with neutral 'is_mine' (clients can also match user_id)
        try {
            broadcast(new MessageReactionUpdated(
                messageId: $messageId,
                action: $action,
                reactions: $formattedReactions,
                chatId: $context['chat_id'],
                circleId: $context['circle_id'],
            ))->toOthers();
        } catch (Throwable $e) {
            Log::warning('Failed broadcasting message:reaction event: '.$e->getMessage(), [
                'message_id' => $messageId,
            ]);
        }

        return [
            'action' => $action,
            'message_id' => $messageId,
            'chat_id' => $context['chat_id'],
            'circle_id' => $context['circle_id'],
            'message_type' => $context['message_type'],
            'reactions' => $formattedReactions,
        ];
    }

    /**
     * Format a collection of MessageReaction models for API output.
     *
     * @param  Collection<int, MessageReaction>  $reactions
     * @return array<int, array{
     *     emoji: string,
     *     user_id: string,
     *     user_name: string,
     *     is_mine: bool,
     *     created_at: ?string
     * }>
     */
    public static function formatReactionsCollection(Collection $reactions, ?string $authUserId = null): array
    {
        return $reactions->map(function (MessageReaction $reaction) use ($authUserId): array {
            $reactingUser = $reaction->relationLoaded('user') ? $reaction->user : null;
            $userName = '';

            if ($reactingUser) {
                $userName = trim((string) ($reactingUser->display_name
                    ?: trim(((string) ($reactingUser->first_name ?? '')).' '.((string) ($reactingUser->last_name ?? '')))));
            }

            return [
                'emoji' => (string) $reaction->emoji,
                'user_id' => (string) $reaction->user_id,
                'user_name' => $userName !== '' ? $userName : 'Peer',
                'is_mine' => $authUserId !== null && $authUserId !== '' && (string) $reaction->user_id === $authUserId,
                'created_at' => $reaction->created_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * @return array{
     *     message_type: string,
     *     chat_id: ?string,
     *     circle_id: ?string
     * }
     */
    private function resolveAndAuthorizeMessage(
        User $user,
        string $messageId,
        ?string $expectedChatId = null,
        ?string $expectedCircleId = null
    ): array {
        $userId = (string) $user->id;

        // 1. Check 1-to-1 Direct Chat messages table
        if ($expectedCircleId === null && Schema::hasTable('messages')) {
            $directMessage = Message::query()
                ->with('chat')
                ->whereNull('deleted_at')
                ->find($messageId);

            if ($directMessage) {
                $chat = $directMessage->chat;

                if (! $chat) {
                    throw new HttpException(404, 'Chat not found.');
                }

                if ($expectedChatId !== null && (string) $chat->id !== $expectedChatId) {
                    throw new HttpException(404, 'Message not found in this chat.');
                }

                $isUser1 = (string) $chat->user1_id === $userId;
                $isUser2 = (string) $chat->user2_id === $userId;

                if (! $isUser1 && ! $isUser2) {
                    throw new HttpException(403, 'You do not have access to this chat message.');
                }

                if (($isUser1 && $directMessage->deleted_for_user1_at !== null)
                    || ($isUser2 && $directMessage->deleted_for_user2_at !== null)) {
                    throw new HttpException(404, 'Message not found.');
                }

                return [
                    'message_type' => 'direct',
                    'chat_id' => (string) $chat->id,
                    'circle_id' => null,
                ];
            }
        }

        // 2. Check Circle Group Chat messages table
        if ($expectedChatId === null && Schema::hasTable('circle_chat_messages')) {
            $circleMessage = CircleChatMessage::query()
                ->where('is_deleted_for_all', false)
                ->find($messageId);

            if ($circleMessage) {
                $circleId = (string) $circleMessage->circle_id;

                if ($expectedCircleId !== null && $circleId !== $expectedCircleId) {
                    throw new HttpException(404, 'Message not found in this circle.');
                }

                $this->circleChatAccessService->ensureUserIsCircleMember($user, $circleId);

                $deletedForUsers = is_array($circleMessage->deleted_for_users) ? $circleMessage->deleted_for_users : [];
                if (in_array($userId, $deletedForUsers, true)) {
                    throw new HttpException(404, 'Message not found.');
                }

                return [
                    'message_type' => 'circle',
                    'chat_id' => null,
                    'circle_id' => $circleId,
                ];
            }
        }

        throw new HttpException(404, 'Message not found.');
    }
}
