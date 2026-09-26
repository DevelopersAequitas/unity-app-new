<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ask;

use App\Http\Controllers\Controller;
use App\Models\Ask\Ask;
use App\Models\User;
use App\Services\Ask\AskFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AskFeedController extends Controller
{
    public function __construct(
        protected AskFeedService $askFeedService
    ) {}

    /**
     * 1. Peers Feed (Other Peers' Posts & Stories)
     * GET /api/v1/asks/feed
     */
    public function feed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'string', 'in:for_you,circle,city,all'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $result = $this->askFeedService->getFeed($user, $validated);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * 2. My Asks (Authenticated User's Posts)
     * GET /api/v1/asks
     */
    public function myAsks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'flow' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $result = $this->askFeedService->getMyAsks($user, $validated);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * 3. Congratulate Ask / Story
     * POST /api/v1/asks/{id}/congratulate
     */
    public function congratulate(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $result = $this->askFeedService->congratulate(
            $user,
            $id,
            $validated['comment'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Congratulation posted successfully',
            'data' => $result,
        ]);
    }

    /**
     * 4. Save / Bookmark Ask
     * POST /api/v1/asks/{id}/save
     */
    public function save(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->askFeedService->toggleSave($user, $id);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * 5. Mark Ask Fulfilled & Thank Giver
     * POST /api/v1/asks/{id}/close-and-thank
     */
    public function closeAndThank(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'giver_id' => ['nullable', 'uuid', 'exists:users,id'],
            'gratitude_note' => ['nullable', 'string', 'max:2000'],
            'is_fulfilled' => ['nullable', 'boolean'],
            'publish_story_to_feed' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        /** @var Ask $ask */
        $ask = Ask::query()->findOrFail($id);

        if ((string) $ask->user_id !== (string) $user->id) {
            abort(403, 'Unauthorized action on this Ask.');
        }

        $result = $this->askFeedService->closeAndThank($user, $ask, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Ask marked as fulfilled and gratitude shared',
            'data' => $result,
        ]);
    }
}
