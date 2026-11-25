<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ask;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ask\UpdateAskFlowStatusRequest;
use App\Leader\Services\LeaderDashboardService;
use App\Models\Ask\Ask;
use App\Models\Ask\AskResponse;
use App\Models\User;
use App\Services\Ask\AskFlowHubService;
use App\Services\Ask\AskResponseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AskFlowHubController extends Controller
{
    public function __construct(
        protected AskFlowHubService $flowHubService,
        protected AskResponseService $askResponseService
    ) {}

    /**
     * Tab 1: Global Feed for a specific Flow (collaboration, referral, help)
     * GET /api/asks/{flow}/global
     */
    public function globalFeed(Request $request, string $flow): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $this->flowHubService->getGlobalFeed($user, $flow, $request->all());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * All User Asks across flows (collaboration, referral, help) in short form
     * GET /api/v1/leader/asks/my
     */
    public function myAllAsks(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $targetUserId = $request->query('user_id');
        if ($request->is('*leader/*', '*leaders/*') && $targetUserId && is_string($targetUserId) && Str::isUuid($targetUserId)) {
            $scope = app(LeaderDashboardService::class)->resolveLeaderScope(null, null, $currentUser);
            $scopedUserIds = $scope['scoped_member_user_ids'];
            if ($scopedUserIds !== null && (string) $currentUser->id !== $targetUserId && ! in_array($targetUserId, $scopedUserIds, true)) {
                return response()->json(['success' => false, 'message' => 'Forbidden: user outside your organizational scope.'], 403);
            }
        }
        $targetUser = ($targetUserId && is_string($targetUserId) && Str::isUuid($targetUserId))
            ? (User::find($targetUserId) ?? $currentUser)
            : $currentUser;

        $params = array_merge($request->query(), [
            'user_id' => (string) $targetUser->id,
        ]);

        $data = $this->flowHubService->getMyAllAsks($targetUser, $params);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Tab 2: My Asks & History for a specific Flow (supports ?user_id=...)
     * GET /api/v1/leader/asks/{flow}/my or GET /api/asks/{flow}/my
     */
    public function myAsks(Request $request, string $flow): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $targetUserId = $request->query('user_id');
        if ($request->is('*leader/*', '*leaders/*') && $targetUserId && is_string($targetUserId) && Str::isUuid($targetUserId)) {
            $scope = app(LeaderDashboardService::class)->resolveLeaderScope(null, null, $currentUser);
            $scopedUserIds = $scope['scoped_member_user_ids'];
            if ($scopedUserIds !== null && (string) $currentUser->id !== $targetUserId && ! in_array($targetUserId, $scopedUserIds, true)) {
                return response()->json(['success' => false, 'message' => 'Forbidden: user outside your organizational scope.'], 403);
            }
        }
        $targetUser = ($targetUserId && is_string($targetUserId) && Str::isUuid($targetUserId))
            ? (User::find($targetUserId) ?? $currentUser)
            : $currentUser;

        $params = array_merge($request->query(), [
            'user_id' => (string) $targetUser->id,
        ]);

        $data = $this->flowHubService->getMyAsks($targetUser, $flow, $params);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Specific User's Asks & History for a Flow
     * GET /api/v1/leader/asks/{flow}/user/{userId}
     */
    public function userAsks(Request $request, string $flow, string $userId): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        if ($request->is('*leader/*', '*leaders/*')) {
            $scope = app(LeaderDashboardService::class)->resolveLeaderScope(null, null, $currentUser);
            $scopedUserIds = $scope['scoped_member_user_ids'];
            if ($scopedUserIds !== null && (string) $currentUser->id !== $userId && ! in_array($userId, $scopedUserIds, true)) {
                return response()->json(['success' => false, 'message' => 'Forbidden: user outside your organizational scope.'], 403);
            }
        }
        $targetUser = User::find($userId) ?? $currentUser;

        $params = array_merge($request->query(), [
            'user_id' => $userId,
        ]);

        $data = $this->flowHubService->getMyAsks($targetUser, $flow, $params);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get Single Ask Full Details
     * GET /api/leader/asks/{flow}/{id} or GET /api/asks/{flow}/{id}
     */
    public function showAsk(Request $request, string $flow, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $this->flowHubService->getAskDetails($user, $flow, $id);

        if ($request->is('*leader/*', '*leaders/*')) {
            $scope = app(LeaderDashboardService::class)->resolveLeaderScope(null, null, $user);
            $scopedUserIds = $scope['scoped_member_user_ids'];
            $askOwnerId = (string) ($data['user_id'] ?? ($data['user']['id'] ?? ''));
            if ($scopedUserIds !== null && $askOwnerId !== '' && (string) $user->id !== $askOwnerId && ! in_array($askOwnerId, $scopedUserIds, true)) {
                return response()->json(['success' => false, 'message' => 'Forbidden: ask outside your organizational scope.'], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Tab 3: Leaderboard for a specific Flow
     * GET /api/asks/{flow}/leaderboard
     */
    public function leaderboard(Request $request, string $flow): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $this->flowHubService->getLeaderboard($user, $flow, $request->all());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Categories Endpoint for Asks / Flows
     * GET /api/asks/categories
     */
    public function categories(Request $request): JsonResponse
    {
        $flowCode = $request->query('flow');
        $categories = $this->flowHubService->getCategories($flowCode ? (string) $flowCode : null);

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Update Response Status / Workflow
     * PATCH /api/asks/responses/{id}/status
     */
    public function updateResponseStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status_id' => ['nullable'],
            'status_label' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        /** @var AskResponse $response */
        $response = AskResponse::query()->with('ask')->findOrFail($id);

        $data = $this->flowHubService->updateResponseItem($user, $response, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Response status updated successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Update status for an item in a specific Flow (Referral, Ask, or Response)
     * PATCH /api/asks/{flow}/{id}/status
     */
    public function updateFlowItemStatus(UpdateAskFlowStatusRequest $request, string $flow, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $data = $this->flowHubService->updateFlowItemStatus($user, $flow, $id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully.',
                'data' => $data,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Item not found for ID: {$id}",
            ], 404);
        }
    }
}
