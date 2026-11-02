<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Circle;
use App\Models\UserCircleRequest;
use App\Models\UserCircleRequestNominee;
use App\Services\Circles\BuildYourOwnCircleApprovalService;
use App\Services\Circles\UserCircleNomineeStatusResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserCircleRequestAdminController extends BaseApiController
{
    public function __construct(
        private readonly BuildYourOwnCircleApprovalService $approvalService,
        private readonly UserCircleNomineeStatusResolver $nomineeResolver,
    ) {}

    /**
     * List all "Build Your Own Circle" requests with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = UserCircleRequest::query()
            ->with(['user', 'category', 'nominees'])
            ->withCount('nominees');

        // Filter by status
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // Search by circle name or user
        if ($request->filled('search') || $request->filled('q')) {
            $term = trim((string) ($request->input('search') ?: $request->input('q')));
            $query->where(function ($sub) use ($term): void {
                $sub->where('circle_name', 'ilike', "%{$term}%")
                    ->orWhere('city', 'ilike', "%{$term}%")
                    ->orWhereHas('user', fn ($uq) => $uq
                        ->where('first_name', 'ilike', "%{$term}%")
                        ->orWhere('last_name', 'ilike', "%{$term}%")
                        ->orWhere('display_name', 'ilike', "%{$term}%")
                        ->orWhere('email', 'ilike', "%{$term}%")
                    );
            });
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        $minimum = (int) config('circle.build_your_own.minimum_nominees', 40);

        $items = collect($paginated->items())->map(fn (UserCircleRequest $r) => $this->formatForAdmin($r, $minimum));

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'current_page'     => $paginated->currentPage(),
                'last_page'        => $paginated->lastPage(),
                'per_page'         => $paginated->perPage(),
                'total'            => $paginated->total(),
            ],
            'minimum_nominees_required' => $minimum,
        ]);
    }

    /**
     * Show detail of a single request including full nominees list (name, phone, email).
     */
    public function show(string $id): JsonResponse
    {
        $minimum = (int) config('circle.build_your_own.minimum_nominees', 40);

        $circleRequest = UserCircleRequest::query()
            ->with(['user', 'category', 'nominees.nomineeUser', 'reviewer'])
            ->withCount('nominees')
            ->findOrFail($id);

        return $this->success($this->formatForAdmin($circleRequest, $minimum, detailed: true));
    }

    /**
     * Approve and auto-create the Circle from the request data.
     *
     * POST /api/v1/admin/user-circle-requests/{id}/approve
     *
     * Optional body fields (all nullable — use request defaults if omitted):
     *   - admin_notes        : string
     *   - status_override    : string  (circle status: 'active'|'pending') default 'active'
     *   - city_id            : uuid    (override auto-resolved city)
     *   - meeting_mode       : string
     *   - meeting_frequency  : string
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $circleRequest = UserCircleRequest::query()
            ->with(['user', 'nominees'])
            ->findOrFail($id);

        // Idempotency: already approved with a circle
        if ($circleRequest->status === UserCircleRequest::STATUS_APPROVED && $circleRequest->created_circle_id) {
            $circle  = Circle::find($circleRequest->created_circle_id);
            $minimum = (int) config('circle.build_your_own.minimum_nominees', 40);

            return $this->success([
                'circle_request' => $this->formatForAdmin($circleRequest->loadCount('nominees'), $minimum, detailed: false),
                'circle'         => $circle ? $this->formatCircle($circle) : null,
                'already_approved' => true,
            ], 'This request was already approved and the circle already exists.');
        }

        $v = $request->validate([
            'admin_notes'       => ['nullable', 'string', 'max:2000'],
            'status_override'   => ['nullable', 'string', 'in:active,pending'],
            'city_id'           => ['nullable', 'uuid', 'exists:cities,id'],
            'category_id'       => ['nullable', 'integer', 'exists:circle_categories,id'],
            'meeting_mode'      => ['nullable', 'string', 'in:online,offline,hybrid'],
            'meeting_frequency' => ['nullable', 'string', 'in:monthly,quarterly'],
        ]);

        $overrides = array_filter([
            'status'            => $v['status_override'] ?? 'active',
            'city_id'           => $v['city_id'] ?? null,
            'category_id'       => $v['category_id'] ?? null,
            'meeting_mode'      => $v['meeting_mode'] ?? null,
            'meeting_frequency' => $v['meeting_frequency'] ?? null,
        ], fn ($val) => $val !== null);

        $adminUserId = auth('admin')->id() ?? auth('sanctum')->id();

        try {
            $result = $this->approvalService->approve(
                circleRequest: $circleRequest,
                adminUserId: $adminUserId ? (string) $adminUserId : null,
                adminNotes: $v['admin_notes'] ?? null,
                overrides: $overrides,
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Circle creation failed: ' . $e->getMessage(),
            ], 500);
        }

        $minimum = (int) config('circle.build_your_own.minimum_nominees', 40);
        $result['circle_request']->loadCount('nominees');

        return $this->success([
            'circle_request' => $this->formatForAdmin($result['circle_request'], $minimum, detailed: false),
            'circle'         => $this->formatCircle($result['circle']),
        ], 'Request approved and circle created successfully.', 201);
    }

    /**
     * Update status (approve, reject, under_review) with optional admin notes.
     * NOTE: Use POST /approve endpoint for full auto-circle-creation on approval.
     *       This endpoint handles non-approval status changes (reject, under_review).
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $v = $request->validate([
            'status'      => ['required', 'string', 'in:pending,under_review,approved,rejected'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $circleRequest = UserCircleRequest::query()->findOrFail($id);

        $circleRequest->status      = $v['status'];
        $circleRequest->admin_notes = $v['admin_notes'] ?? $circleRequest->admin_notes;
        $circleRequest->reviewed_by = auth('admin')->id() ?? auth('sanctum')->id() ?? null;
        $circleRequest->reviewed_at = now();
        $circleRequest->save();

        $minimum = (int) config('circle.build_your_own.minimum_nominees', 40);
        $circleRequest->load(['user', 'nominees']);

        return $this->success($this->formatForAdmin($circleRequest, $minimum), 'Status updated successfully.');
    }

    /**
     * Update dynamic minimum recommended peers requirement.
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $v = $request->validate([
            'minimum_recommended_peers' => ['nullable', 'integer', 'min:1', 'max:500'],
            'minimum_nominees'          => ['nullable', 'integer', 'min:1', 'max:500'],
            'minimum_nominees_required' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $limit = (int) ($v['minimum_recommended_peers'] ?? $v['minimum_nominees'] ?? $v['minimum_nominees_required'] ?? 40);
        \Illuminate\Support\Facades\Cache::forever('circle.build_your_own.minimum_nominees', $limit);

        if (\Illuminate\Support\Facades\Schema::hasTable('app_configs')) {
            DB::table('app_configs')->updateOrInsert(
                ['key' => 'circle_build_minimum_nominees'],
                ['value' => (string) $limit, 'updated_at' => now()]
            );
        }

        return $this->success([
            'minimum_recommended_peers_required' => $limit,
            'minimum_nominees_required'          => $limit,
            'message'                            => "Dynamic requirement successfully updated to {$limit} recommended peers.",
        ], 'Dynamic configuration updated successfully.');
    }

    /**
     * Get all recommended peers of a specific request with basic details (name, phone, email)
     * and live statuses:
     * - has_joined_unity / unity_join_status ('joined' | 'not_joined')
     * - has_joined_circle / circle_join_status ('joined' | 'request_sent' | 'not_joined')
     */
    public function recommendedPeers(string $id): JsonResponse
    {
        $circleRequest = UserCircleRequest::query()->with('category')->findOrFail($id);

        $resolvedPeers = $this->nomineeResolver->resolveNominees($circleRequest);
        $summary = $this->nomineeResolver->getSummary($circleRequest, $resolvedPeers);

        $minimum = (int) config('circle.build_your_own.minimum_nominees', 40);
        $count   = $resolvedPeers->count();

        return $this->success([
            'request_id'                     => $circleRequest->id,
            'circle_name'                    => $circleRequest->circle_name,
            'category_id'                    => $circleRequest->category_id,
            'category'                       => $circleRequest->category ? [
                'id'   => $circleRequest->category->id,
                'name' => $circleRequest->category->name,
                'slug' => $circleRequest->category->slug,
            ] : null,
            'status'                         => $circleRequest->status,
            'created_circle_id'              => $circleRequest->created_circle_id,
            'recommended_peers'              => $resolvedPeers,
            'nominees'                       => $resolvedPeers, // alias
            'recommended_peers_count'        => $count,
            'nominees_count'                 => $count, // alias
            'minimum_required'               => $minimum,
            'has_minimum_recommended_peers' => $count >= $minimum,
            'has_minimum_nominees'          => $count >= $minimum, // alias
            'remaining_recommended_peers'   => max(0, $minimum - $count),
            'remaining'                      => max(0, $minimum - $count),
            'summary'                        => $summary,
        ], 'Recommended peers fetched successfully.');
    }

    /**
     * Backward-compatible alias for recommendedPeers.
     */
    public function nominees(string $id): JsonResponse
    {
        return $this->recommendedPeers($id);
    }

    /**
     * Stats/summary for admin dashboard.
     */
    public function stats(): JsonResponse
    {
        $minimum = \App\Http\Controllers\Api\UserCircleRequestController::getMinimumRequired();

        $totalByStatus = UserCircleRequest::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalWithMinimum = UserCircleRequest::query()
            ->has('nominees', '>=', $minimum)
            ->count();

        return $this->success([
            'total'                     => array_sum($totalByStatus),
            'by_status'                 => $totalByStatus,
            'total_with_minimum_peers'  => $totalWithMinimum,
            'minimum_nominees_required' => $minimum,
        ], 'Stats fetched.');
    }



    private function formatForAdmin(UserCircleRequest $r, int $minimum, bool $detailed = false): array
    {
        $count = $r->nominees_count ?? $r->nominees->count();
        $user  = $r->user;
        $name  = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->display_name : 'Unknown';

        $resolvedNominees = null;
        $summary = null;

        if ($detailed) {
            $resolvedNominees = $this->nomineeResolver->resolveNominees($r);
            $summary = $this->nomineeResolver->getSummary($r, $resolvedNominees);
        }

        $data = [
            'id'                   => $r->id,
            'circle_name'          => $r->circle_name,
            'category_id'          => $r->category_id,
            'category'             => $r->category ? [
                'id'         => $r->category->id,
                'name'       => $r->category->name,
                'slug'       => $r->category->slug,
                'circle_key' => $r->category->circle_key,
            ] : null,
            'description'          => $r->description,
            'city'                 => $r->city,
            'country'              => $r->country,
            'meeting_mode'         => $r->meeting_mode,
            'meeting_frequency'    => $r->meeting_frequency,
            'status'                         => $r->status,
            'admin_notes'                    => $r->admin_notes,
            'recommended_peers_count'        => $count,
            'nominees_count'                 => $count, // alias
            'minimum_required'               => $minimum,
            'has_minimum_recommended_peers' => $count >= $minimum,
            'has_minimum_nominees'          => $count >= $minimum, // alias
            'remaining_recommended_peers'   => max(0, $minimum - $count),
            'remaining_nominees'            => max(0, $minimum - $count), // alias
            'created_circle_id'              => $r->created_circle_id,
            'circle_created_at'              => $r->circle_created_at?->toISOString(),
            'submitted_by'         => [
                'id'    => $user?->id,
                'name'  => $name,
                'email' => $user?->email,
                'phone' => $user?->phone ?? $user?->mobile ?? null,
            ],
            'reviewed_by' => $r->reviewer ? [
                'id'   => $r->reviewer->id,
                'name' => trim(($r->reviewer->first_name ?? '') . ' ' . ($r->reviewer->last_name ?? '')) ?: $r->reviewer->display_name,
            ] : null,
            'reviewed_at' => $r->reviewed_at?->toISOString(),
            'created_at'  => $r->created_at?->toISOString(),
            'updated_at'  => $r->updated_at?->toISOString(),
        ];

        if ($summary !== null) {
            $data['summary'] = $summary;
            $data['recommended_peers_joined_unity_count']  = $summary['joined_unity_count'];
            $data['recommended_peers_joined_circle_count'] = $summary['joined_circle_count'];
            $data['nominees_joined_unity_count']           = $summary['joined_unity_count'];
            $data['nominees_joined_circle_count']          = $summary['joined_circle_count'];
        }

        if ($detailed && $resolvedNominees !== null) {
            $data['recommended_peers'] = $resolvedNominees;
            $data['nominees']          = $resolvedNominees;
        }

        return $data;
    }

    private function formatCircle(\App\Models\Circle $circle): array
    {
        return [
            'id'                => $circle->id,
            'name'              => $circle->name,
            'slug'              => $circle->slug,
            'description'       => $circle->description,
            'city'              => $circle->city_display ?? $circle->city,
            'country'           => $circle->country,
            'status'            => $circle->status,
            'meeting_mode'      => $circle->meeting_mode,
            'meeting_frequency' => $circle->meeting_frequency,
            'members_count'     => $circle->relationLoaded('members') ? $circle->members->count() : null,
            'cover_image_url'   => $circle->cover_image_url,
            'circle_image_url'  => $circle->circle_image_url,
            'created_at'        => $circle->created_at?->toISOString(),
        ];
    }
}
