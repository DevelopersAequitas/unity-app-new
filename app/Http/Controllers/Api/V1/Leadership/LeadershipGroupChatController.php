<?php

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Http\Controllers\Api\BaseApiController;
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
}
