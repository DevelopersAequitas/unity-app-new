<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\AiChatbotMessageRequest;
use App\Models\User;
use App\Services\Ai\AiChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AiChatbotController extends Controller
{
    public function __construct(
        protected AiChatbotService $chatbotService
    ) {}

    /**
     * GET /api/ai/chatbot/config
     */
    public function config(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->chatbotService->getConfig($user),
        ]);
    }

    /**
     * POST /api/ai/chatbot/message
     */
    public function message(AiChatbotMessageRequest $request): JsonResponse
    {
        try {
            /** @var User|null $user */
            $user = $request->user('sanctum') ?? $request->user();

            $result = $this->chatbotService->sendMessage(
                user: $user,
                userText: $request->resolvedMessage(),
                sessionId: $request->input('session_id')
            );

            return response()->json([
                'success' => true,
                'session_id' => $result['session_id'],
                'message' => $result['message'],
                'data' => $result['message'],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/ai/chatbot/history
     */
    public function history(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $request->user();
        $sessionId = $request->query('session_id');

        return response()->json([
            'success' => true,
            'data' => $this->chatbotService->getHistory(
                user: $user,
                sessionId: is_string($sessionId) ? $sessionId : null
            ),
        ]);
    }

    /**
     * GET /api/ai/chatbot/sessions
     */
    public function sessions(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->chatbotService->getSessions($user),
        ]);
    }

    /**
     * POST /api/ai/chatbot/clear
     */
    public function clear(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $request->user();
        $sessionId = $request->input('session_id');

        $this->chatbotService->clearHistory(
            user: $user,
            sessionId: is_string($sessionId) ? $sessionId : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Chat history cleared',
        ]);
    }
}

