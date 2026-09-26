<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ask;

use App\Http\Controllers\Controller;
use App\Models\Ask\Ask;
use App\Models\Ask\AskResponse;
use App\Models\Ask\AskResponseStatusHistory;
use App\Models\User;
use App\Services\Ask\AskFlowHubService;
use App\Services\Ask\AskResponseService;
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

        $statusId = $validated['status_id'] ?? null;
        $statusLabel = $validated['status_label'] ?? $validated['status'] ?? null;
        $remarks = $validated['remarks'] ?? $validated['note'] ?? null;

        $mappedStatus = match ((string) $statusId) {
            '1' => AskResponse::STATUS_PENDING,
            '2', '3' => 'connected',
            '4' => AskResponse::STATUS_COMPLETED,
            '5' => AskResponse::STATUS_COMPLETED,
            '6', '7' => AskResponse::STATUS_DECLINED,
            default => ($statusLabel ? strtolower(str_replace(' ', '_', (string) $statusLabel)) : AskResponse::STATUS_ACCEPTED),
        };

        $response->update([
            'status' => $mappedStatus,
        ]);

        AskResponseStatusHistory::query()->create([
            'response_id' => $response->id,
            'changed_by_user_id' => $user->id,
            'old_status' => $response->getOriginal('status') ?? AskResponse::STATUS_PENDING,
            'new_status' => $mappedStatus,
            'note' => $remarks ?: ($statusLabel ? 'Status updated to '.$statusLabel : null),
        ]);

        // If status is "Got The Business" (4) or "Got things done" (5), mark Ask as fulfilled
        if (in_array((string) $statusId, ['4', '5'], true) && $response->ask) {
            $response->ask->update([
                'status' => Ask::STATUS_FULFILLED,
                'fulfilled_at' => now(),
                'outcome_status' => (string) $statusId === '4' ? 'got_the_business' : 'testimonial_given',
                'outcome_notes' => $remarks,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Response status updated successfully.',
            'data' => [
                'id' => (string) $response->id,
                'status' => $response->status,
                'status_id' => $statusId,
                'status_label' => $statusLabel,
                'remarks' => $remarks,
            ],
        ]);
    }
}
