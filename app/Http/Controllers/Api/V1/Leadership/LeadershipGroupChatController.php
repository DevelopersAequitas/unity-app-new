<?php

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Leadership\MarkLeadershipMessagesReadRequest;
use App\Http\Requests\Leadership\SendLeadershipMessageRequest;
use App\Http\Resources\Leadership\LeadershipMessageResource;
use App\Http\Resources\Leadership\LeadershipMemberResource;
use App\Models\Circle;
use App\Services\Leadership\LeadershipGroupChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadershipGroupChatController extends BaseApiController
{
    public function __construct(private readonly LeadershipGroupChatService $leadershipGroupChatService)
    {
    }

    public function members(Request $request, Circle $circle): JsonResponse
    {
        $payload = $this->leadershipGroupChatService->getMembersPayload($circle, $request->user());

        if ($payload === null) {
            return $this->error('Forbidden.', 403);
        }

        return $this->success([
            'circle' => $payload['circle'],
            'chat' => $payload['chat'],
            'current_user' => $payload['current_user'],
            'members' => LeadershipMemberResource::collection($payload['members']),
        ], 'Leadership members fetched successfully.');
    }

    public function messages(Request $request, Circle $circle): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $messages = $this->leadershipGroupChatService->getMessages($circle, $request->user(), $perPage);

        if (! $messages) {
            return $this->error('Forbidden.', 403);
        }

        return $this->success([
            'items' => LeadershipMessageResource::collection($messages->items()),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ], 'Leadership chat messages fetched successfully.');
    }

    public function markRead(MarkLeadershipMessagesReadRequest $request, Circle $circle): JsonResponse
    {
        $markedCount = $this->leadershipGroupChatService->markMessagesRead(
            $circle,
            $request->user(),
            $request->validated()['message_ids']
        );

        if ($markedCount === null) {
            return $this->error('Forbidden.', 403);
        }

        return $this->success([
            'marked_count' => $markedCount,
        ], 'Messages marked as read successfully.');
    }

    public function sendMessage(SendLeadershipMessageRequest $request, Circle $circle): JsonResponse
    {
        $message = $this->leadershipGroupChatService->sendMessage($circle, $request->user(), $request->validated());

        if (! $message) {
            return $this->error('Forbidden.', 403);
        }

        return $this->success(new LeadershipMessageResource($message), 'Message sent successfully.', 201);
    }
}
