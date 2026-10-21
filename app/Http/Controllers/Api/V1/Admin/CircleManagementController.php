<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\AdminPeerIndexRequest;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\CircleMember;
use App\Models\Impact;
use App\Models\Payment;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\AdminPeerService;
use App\Services\Admin\AdminScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CircleManagementController extends BaseApiController
{
    public function __construct(
        private readonly AdminScopeService $scope,
        private readonly AdminAuditService $audit,
        private readonly AdminPeerService $peerService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = Circle::query()->with('cityRef')->withCount('members');
        if ($request->filled('state_name')) {
            $state = $request->input('state_name');
            $q->where(function ($query) use ($state): void {
                $hasCircleState = Schema::hasColumn('circles', 'state');
                $hasCircleStateName = Schema::hasColumn('circles', 'state_name');
                $hasCityState = Schema::hasColumn('cities', 'state');
                $hasCityStateName = Schema::hasColumn('cities', 'state_name');
                if ($hasCircleState) {
                    $query->where('state', $state);
                }
                if ($hasCircleStateName) {
                    ($hasCircleState ? $query->orWhere('state_name', $state) : $query->where('state_name', $state));
                }
                if ($hasCityState || $hasCityStateName) {
                    $method = ($hasCircleState || $hasCircleStateName) ? 'orWhereHas' : 'whereHas';
                    $query->{$method}('cityRef', function ($cityQuery) use ($state, $hasCityState, $hasCityStateName): void {
                        if ($hasCityState) {
                            $cityQuery->where('state', $state);
                        }
                        if ($hasCityStateName) {
                            ($hasCityState ? $cityQuery->orWhere('state_name', $state) : $cityQuery->where('state_name', $state));
                        }
                    });
                }
            });
        }
        if ($request->filled('search') || $request->filled('q')) {
            $term = trim((string) ($request->input('search') ?: $request->input('q')));
            $q->where(function ($sub) use ($term): void {
                $sub->where('name', 'ilike', "%{$term}%")
                    ->orWhere('description', 'ilike', "%{$term}%")
                    ->orWhere('purpose', 'ilike', "%{$term}%")
                    ->orWhereHas('cityRef', function ($cityQuery) use ($term): void {
                        $cityQuery->where('name', 'ilike', "%{$term}%");
                        if (Schema::hasColumn('cities', 'state')) {
                            $cityQuery->orWhere('state', 'ilike', "%{$term}%");
                        }
                        if (Schema::hasColumn('cities', 'state_name')) {
                            $cityQuery->orWhere('state_name', 'ilike', "%{$term}%");
                        }
                    });
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $q->where('status', strtolower((string) $request->input('status')));
        }

        $this->scope->applyCircleScope($q, $request->user());
        if ($request->filled('state_name')) {
            return $this->success($q->orderBy('name')->get()->map(fn (Circle $circle) => [
                'id' => $circle->id,
                'name' => $circle->name,
                'state_name' => $circle->state_name ?? $circle->state ?? $circle->cityRef?->state_name ?? $circle->cityRef?->state ?? null,
            ])->values(), 'Circles fetched successfully.');
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 50)));
        return $this->success($q->orderBy('name')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'purpose' => ['nullable', 'string'],
            'announcement' => ['nullable', 'string'],
            'city_id' => ['nullable', 'uuid', 'exists:cities,id'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'circle_stage' => ['nullable', 'string'],
            'industry_tags' => ['nullable'],
            'circle_founder_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'circle_director_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'industry_director_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'ded_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'eed_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'cover_file_id' => ['nullable', 'uuid'],
            'circle_image_file_id' => ['nullable', 'uuid'],
            'payment_gateway' => ['nullable', 'string'],
            'payment_plan_id' => ['nullable', 'string'],
            'circle_package' => ['nullable', 'string'],
            'circle_price_amount' => ['nullable', 'numeric'],
            'circle_gst_percent' => ['nullable', 'numeric'],
            'is_package_active' => ['nullable', 'boolean'],
            'meeting_mode' => ['nullable', 'string'],
            'meeting_frequency' => ['nullable', 'string'],
            'launch_date' => ['nullable', 'date'],
            'calendar' => ['nullable', 'array'],
            'categories' => ['nullable', 'array'],
        ]);

        $baseSlug = Str::slug((string) ($validated['name'] ?? 'circle'));
        if ($baseSlug === '') $baseSlug = 'circle';
        $slug = $baseSlug;
        $counter = 1;
        while (Circle::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $circle = new Circle();
        $circle->slug = $slug;

        $fillableColumns = [
            'name', 'description', 'purpose', 'announcement', 'city_id', 'city', 'country',
            'type', 'status', 'circle_stage', 'circle_founder_user_id', 'circle_director_user_id',
            'industry_director_user_id', 'ded_user_id', 'eed_user_id', 'cover_file_id',
            'circle_image_file_id', 'payment_gateway', 'payment_plan_id', 'circle_package',
            'circle_price_amount', 'circle_gst_percent', 'is_package_active', 'meeting_mode',
            'meeting_frequency', 'launch_date',
        ];

        foreach ($fillableColumns as $col) {
            if (array_key_exists($col, $validated) && Schema::hasColumn('circles', $col)) {
                $circle->{$col} = $validated[$col];
            }
        }

        if (isset($validated['industry_tags'])) {
            $tags = is_array($validated['industry_tags'])
                ? $validated['industry_tags']
                : array_filter(array_map('trim', explode(',', (string) $validated['industry_tags'])));
            $circle->industry_tags = array_values($tags);
        }

        if (isset($validated['calendar']) && is_array($validated['calendar'])) {
            $circle->calendar = $validated['calendar'];
        }

        $circle->save();

        if (isset($validated['categories']) && is_array($validated['categories']) && method_exists($circle, 'categories')) {
            $categoryIds = collect($validated['categories'])->filter()->map(fn($id) => (int) $id)->values()->all();
            $circle->categories()->sync($categoryIds);
        }

        $circle->load(['cityRef', 'circleFounder', 'circleDirector', 'industryDirector', 'ded', 'eed', 'categories']);

        return $this->success($circle, 'Circle created successfully.', 201);
    }

    public function show(string $id): JsonResponse
    {
        $relations = ['members', 'cityRef', 'circleFounder', 'circleDirector', 'industryDirector', 'ded', 'eed'];
        if (Schema::hasTable('circle_category_mappings') || Schema::hasTable('circle_categories')) {
            $relations[] = 'categories';
        }

        return $this->success(Circle::query()->with($relations)->findOrFail($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $circle = Circle::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'purpose' => ['nullable', 'string'],
            'announcement' => ['nullable', 'string'],
            'city_id' => ['nullable', 'uuid', 'exists:cities,id'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'circle_stage' => ['nullable', 'string'],
            'industry_tags' => ['nullable'],
            'circle_founder_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'circle_director_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'industry_director_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'ded_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'eed_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'cover_file_id' => ['nullable', 'uuid'],
            'circle_image_file_id' => ['nullable', 'uuid'],
            'payment_gateway' => ['nullable', 'string'],
            'payment_plan_id' => ['nullable', 'string'],
            'circle_package' => ['nullable', 'string'],
            'circle_price_amount' => ['nullable', 'numeric'],
            'circle_gst_percent' => ['nullable', 'numeric'],
            'is_package_active' => ['nullable', 'boolean'],
            'meeting_mode' => ['nullable', 'string'],
            'meeting_frequency' => ['nullable', 'string'],
            'launch_date' => ['nullable', 'date'],
            'calendar' => ['nullable', 'array'],
            'categories' => ['nullable', 'array'],
        ]);

        $fillableColumns = [
            'name', 'description', 'purpose', 'announcement', 'city_id', 'city', 'country',
            'type', 'status', 'circle_stage', 'circle_founder_user_id', 'circle_director_user_id',
            'industry_director_user_id', 'ded_user_id', 'eed_user_id', 'cover_file_id',
            'circle_image_file_id', 'payment_gateway', 'payment_plan_id', 'circle_package',
            'circle_price_amount', 'circle_gst_percent', 'is_package_active', 'meeting_mode',
            'meeting_frequency', 'launch_date',
        ];

        foreach ($fillableColumns as $col) {
            if (array_key_exists($col, $validated) && Schema::hasColumn('circles', $col)) {
                $circle->{$col} = $validated[$col];
            }
        }

        if (array_key_exists('industry_tags', $validated)) {
            $tags = is_array($validated['industry_tags'])
                ? $validated['industry_tags']
                : array_filter(array_map('trim', explode(',', (string) $validated['industry_tags'])));
            $circle->industry_tags = array_values($tags);
        }

        if (isset($validated['calendar']) && is_array($validated['calendar'])) {
            $currentCalendar = is_array($circle->calendar) ? $circle->calendar : [];
            $circle->calendar = array_replace_recursive($currentCalendar, $validated['calendar']);
        }

        if (isset($validated['name']) && $validated['name'] !== $circle->getOriginal('name')) {
            $circle->slug = Circle::generateUniqueSlug($validated['name'], $circle->id);
        }

        $circle->save();

        if (isset($validated['categories']) && is_array($validated['categories']) && method_exists($circle, 'categories')) {
            $categoryIds = collect($validated['categories'])->filter()->map(fn($id) => (int) $id)->values()->all();
            $circle->categories()->sync($categoryIds);
        }

        $circle->load(['cityRef', 'circleFounder', 'circleDirector', 'industryDirector', 'ded', 'eed', 'categories']);

        return $this->success($circle, 'Circle updated successfully.');
    }

    public function patchStatus(Request $request, string $id): JsonResponse
    {
        $request->validate(['status' => ['required', 'string']]);

        return $this->update($request, $id);
    }

    public function assignFounder(Request $request, string $id): JsonResponse
    {
        $request->merge(['circle_founder_user_id' => $request->validate(['user_id' => 'required|uuid|exists:users,id'])['user_id']]);

        return $this->update($request, $id);
    }

    public function assignDirector(Request $request, string $id): JsonResponse
    {
        $request->merge(['circle_director_user_id' => $request->validate(['user_id' => 'required|uuid|exists:users,id'])['user_id']]);

        return $this->update($request, $id);
    }

    public function assignLeadershipTeam(Request $request, string $id): JsonResponse
    {
        $v = $request->validate([
            'chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'vice_chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'secretary_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'powerhouse_user_id' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        foreach (['chair_user_id' => 'chair', 'vice_chair_user_id' => 'vice_chair', 'secretary_user_id' => 'secretary', 'powerhouse_user_id' => 'committee_leader'] as $key => $role) {
            if (! empty($v[$key])) {
                CircleMember::query()->updateOrCreate(['circle_id' => $id, 'user_id' => $v[$key]], ['role' => $role, 'status' => 'approved', 'joined_at' => now(), 'deleted_at' => null]);
            }
        }

        return $this->success(['assigned' => true]);
    }

    public function joinRequests(string $id): JsonResponse
    {
        return $this->success(CircleJoinRequest::query()->where('circle_id', $id)->paginate(20));
    }

    public function peers(AdminPeerIndexRequest $request, string $id): JsonResponse
    {
        Circle::query()->findOrFail($id);
        $filters = $request->validated();
        $perPage = (int) $request->input('per_page', 20);

        $paginator = $this->peerService->listCirclePeers($id, $filters, $request->user(), $perPage);

        return $this->success($this->peerService->transformPaginated($paginator));
    }

    public function members(Request $request, string $id): JsonResponse
    {
        if ($request->wantsJson() || $request->has('canonical')) {
            Circle::query()->findOrFail($id);
            $paginator = $this->peerService->listCirclePeers($id, $request->all(), $request->user(), (int) $request->input('per_page', 20));

            return $this->success($this->peerService->transformPaginated($paginator));
        }

        return $this->success(CircleMember::query()->with('user:id,display_name,membership_status,life_impacted_count,coins_balance')->where('circle_id', $id)->whereNull('deleted_at')->paginate(20));
    }

    public function addMember(Request $request, string $id): JsonResponse
    {
        $v = $request->validate(['user_id' => ['required', 'uuid', 'exists:users,id'], 'role' => ['nullable', 'string']]);
        $member = CircleMember::withTrashed()->where('circle_id', $id)->where('user_id', $v['user_id'])->first();
        if ($member) {
            if ($member->trashed()) {
                $member->restore();
            }
            $member->status = 'approved';
            $member->left_at = null;
            if (! empty($v['role'])) {
                $member->role = $v['role'];
            }
            $member->save();
        } else {
            $member = CircleMember::query()->create([
                'id' => (string) Str::uuid(),
                'circle_id' => $id,
                'user_id' => $v['user_id'],
                'status' => 'approved',
                'joined_at' => now(),
                'role' => $v['role'] ?? 'member',
            ]);
        }

        return $this->success($member);
    }

    public function removeMember(string $id, string $userId): JsonResponse
    {
        CircleMember::query()->where('circle_id', $id)->where('user_id', $userId)->update(['deleted_at' => now(), 'status' => 'inactive']);

        return $this->success(['removed' => true]);
    }

    public function health(string $id): JsonResponse
    {
        $memberCount = CircleMember::query()->where('circle_id', $id)->where('status', 'approved')->whereNull('deleted_at')->count();
        $impactTotal = Impact::query()->where('circle_id', $id)->where('status', 'approved')->sum(DB::raw('COALESCE(impact_score,impact_value,1)'));
        $revenue = Payment::query()->where('circle_id', $id)->where('status', 'paid')->sum('amount');

        return $this->success([
            'current_members' => $memberCount,
            'target_members' => null,
            'avg_attendance' => null,
            'visitor_count' => (int) Circle::query()->where('id', $id)->value('visitor_count'),
            'conversions' => null,
            'renewals' => null,
            'total_impact' => (int) $impactTotal,
            'revenue' => (float) $revenue,
            'latest_health_log' => null,
        ]);
    }

    public function performance(string $id): JsonResponse
    {
        return $this->success([
            'founder' => Circle::query()->where('id', $id)->value('circle_founder_user_id'),
            'director' => Circle::query()->where('id', $id)->value('circle_director_user_id'),
            'leadership' => CircleMember::query()->where('circle_id', $id)->whereIn(DB::raw('role::text'), ['chair', 'vice_chair', 'secretary', 'committee_leader'])->whereNull('deleted_at')->get(),
        ]);
    }

    public function patchPackage(Request $request, string $id): JsonResponse
    {
        $circle = Circle::query()->findOrFail($id);
        $circle->fill($request->only(['zoho_addon_code', 'zoho_addon_id', 'zoho_addon_name', 'circle_price_amount', 'circle_price_currency', 'circle_duration_months']))->save();

        return $this->success($circle);
    }
}
