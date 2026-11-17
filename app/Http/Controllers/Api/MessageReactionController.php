<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Chat\ToggleMessageReactionRequest;
use App\Services\Chat\MessageReactionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MessageReactionController extends BaseApiController
{
    public function __construct(
        private readonly MessageReactionService $messageReactionService,
    ) {}

    public function toggle(ToggleMessageReactionRequest $request, string $messageId): JsonResponse
    {
        return $this->handleToggle($request, $messageId);
    }

    public function toggleForChat(ToggleMessageReactionRequest $request, string $chatId, string $messageId): JsonResponse
    {
        return $this->handleToggle($request, $messageId, expectedChatId: $chatId);
    }

    public function toggleForCircle(ToggleMessageReactionRequest $request, string $circleId, string $messageId): JsonResponse
    {
        return $this->handleToggle($request, $messageId, expectedCircleId: $circleId);
    }

    private function handleToggle(
        ToggleMessageReactionRequest $request,
        string $messageId,
        ?string $expectedChatId = null,
        ?string $expectedCircleId = null
    ): JsonResponse {
        try {
            $result = $this->messageReactionService->toggleReaction(
                user: $request->user(),
                messageId: $messageId,
                emoji: (string) $request->validated('emoji'),
                expectedChatId: $expectedChatId,
                expectedCircleId: $expectedCircleId,
            );

            return response()->json([
                'success' => true,
                'action' => $result['action'],
                'message' => match ($result['action']) {
                    'added' => 'Reaction added.',
                    'replaced' => 'Reaction updated.',
                    'removed' => 'Reaction removed.',
                    default => 'Reaction updated.',
                },
                'data' => [
                    'message_id' => $result['message_id'],
                    'chat_id' => $result['chat_id'],
                    'circle_id' => $result['circle_id'],
                    'action' => $result['action'],
                    'reactions' => $result['reactions'],
                ],
            ], 200);
        } catch (HttpException $exception) {
            return $this->error($exception->getMessage(), $exception->getStatusCode());
        }
    }
}
