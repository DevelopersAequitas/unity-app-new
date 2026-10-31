<?php

declare(strict_types=1);

namespace App\Modules\Leadership\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Leader\Services\LeaderTeamsService;
use App\Models\Circle;
use App\Models\User;
use App\Shared\OrganizationalScope\OrganizationalScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadershipCirclesController extends BaseApiController
{
    public function __construct(
        private readonly LeaderTeamsService $teamsService,
        private readonly OrganizationalScopeService $scopeService,
    ) {}

    /**
     * API 44: Scoped Circles List.
     * GET /api/v1/leadership/circles
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $scope = $this->scopeService->resolveForActor($user, 'circles');

        $query = Circle::query()->whereNull('deleted_at');
        $this->scopeService->applyCircleScope($query, $user, $request->query('circle_id') ? (string) $request->query('circle_id') : null);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }
        if ($request->filled('search')) {
            $s = strtolower(trim((string) $request->query('search')));
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.$s.'%']);
        }

        $circles = $query->orderBy('name')->get()->map(function (Circle $c): array {
            $peerCount = DB::table('circle_members')
                ->where('circle_id', $c->id)
                ->whereNull('deleted_at')
                ->where(fn ($q) => $q->whereNull('status')->orWhereIn(DB::raw('LOWER(status::text)'), ['approved', 'active']))
                ->count();

            return [
                'id' => (string) $c->id,
                'name' => (string) $c->name,
                'slug' => (string) $c->slug,
                'status' => (string) $c->status,
                'description' => $c->description,
                'founder_user_id' => $c->founder_user_id,
                'city_id' => $c->city_id,
                'industry_tags' => $c->industry_tags ?? [],
                'peers_count' => $peerCount,
                'created_at' => $c->created_at?->toIso8601String(),
            ];
        })->all();

        return $this->success([
            'scope_type' => $scope['effective_scope_type'],
            'total' => count($circles),
            'items' => $circles,
        ], 'Scoped circles fetched successfully.');
    }

    /**
     * API 45: Scoped Circle Detail.
     * GET /api/v1/leadership/circles/{circleId}
     */
    public function show(Request $request, string $circleId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->scopeService->canAccessCircle($user, $circleId)) {
            return $this->error('You do not have organizational scope access to view this circle.', 403);
        }

        $details = $this->teamsService->getCircleDetails($circleId);
        if (! $details) {
            return $this->error('Circle not found.', 404);
        }

        return $this->success($details, 'Circle details fetched successfully.');
    }
}

