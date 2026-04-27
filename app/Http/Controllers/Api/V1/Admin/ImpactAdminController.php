<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\Impacts\ReviewImpactRequest;
use App\Http\Requests\Api\V1\Admin\StoreManualLifeImpactRequest;
use App\Http\Resources\ImpactResource;
use App\Models\Impact;
use App\Models\Role;
use App\Services\Impacts\ImpactService;
use App\Services\LifeImpact\LifeImpactBackfillService;
use Illuminate\Http\JsonResponse;

class ImpactAdminController extends BaseApiController
{
    public function __construct(
        private readonly ImpactService $impactService,
        private readonly LifeImpactBackfillService $lifeImpactBackfillService,
    ) {
    }


    public function storeManual(StoreManualLifeImpactRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->canReview($user)) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to add manual life impact records.',
            ], 403);
        }

        $history = $this->lifeImpactBackfillService->createManualImpact($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Manual life impact record added successfully.',
            'data' => [
                'id' => (string) $history->id,
                'user_id' => (string) $history->user_id,
                'activity_type' => (string) $history->activity_type,
                'impact_value' => (int) ($history->impact_value ?? 0),
                'title' => (string) $history->title,
                'description' => $history->description,
                'action_key' => (string) ($history->action_key ?? ''),
                'status' => (string) ($history->status ?? 'approved'),
                'created_at' => optional($history->created_at)?->toISOString(),
            ],
        ]);
    }

    public function approve(ReviewImpactRequest $request, Impact $impact): JsonResponse
    {
        $user = $request->user();

        if (! $this->canReview($user)) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to review impacts.',
            ], 403);
        }

        try {
            $approved = $this->impactService->approveImpact($impact, $user, $request->input('review_remarks'));

            return response()->json([
                'status' => true,
                'message' => 'Impact approved successfully.',
                'data' => (new ImpactResource($approved->load(['user', 'impactedPeer'])))->resolve(),
            ]);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function reject(ReviewImpactRequest $request, Impact $impact): JsonResponse
    {
        $user = $request->user();

        if (! $this->canReview($user)) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to review impacts.',
            ], 403);
        }

        try {
            $rejected = $this->impactService->rejectImpact($impact, $user, $request->input('review_remarks'));

            return response()->json([
                'status' => true,
                'message' => 'Impact rejected successfully.',
                'data' => (new ImpactResource($rejected->load(['user', 'impactedPeer'])))->resolve(),
            ]);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function canReview($user): bool
    {
        if (! $user) {
            return false;
        }

        $roleIds = Role::query()->whereIn('key', ['global_admin', 'industry_director', 'ded'])->pluck('id');

        return $user->roles()->whereIn('roles.id', $roleIds)->exists();
    }
}
