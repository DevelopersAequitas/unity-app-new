<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ask;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ask\UpdateAskFlowStatusRequest;
use App\Models\Ask\Ask;
use App\Models\Ask\AskResponse;
use App\Models\User;
use App\Services\Ask\AskFlowHubService;
use App\Services\Ask\AskResponseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * Tab 2: My Asks & History for a specific Flow
     * GET /api/asks/{flow}/my
     */
    public function myAsks(Request $request, string $flow): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $this->flowHubService->getMyAsks($user, $flow, $request->all());

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
