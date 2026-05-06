<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Circle;
use App\Models\Industry;
use App\Models\Payment;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\AdminScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class IndustryManagementController extends BaseApiController
{
    public function __construct(private readonly AdminScopeService $scope, private readonly AdminAuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Industry::query();
        $this->scope->applyIndustryScope($query, $request->user());

        return $this->success($query->paginate((int) $request->input('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string']]);
        $industry = Industry::query()->create($validated);
        $this->audit->log($request->user(), 'admin.industry.create', 'industries', $industry->id, [], $industry->toArray(), $request);

        return $this->success($industry, 'Industry created.');
    }

    public function show(string $id): JsonResponse
    {
        return $this->success(Industry::query()->findOrFail($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $industry = Industry::query()->findOrFail($id);
        $validated = $request->validate(['name' => ['sometimes', 'string', 'max:255'], 'description' => ['nullable', 'string']]);
        $old = $industry->toArray();
        $industry->fill($validated)->save();
        $this->audit->log($request->user(), 'admin.industry.update', 'industries', $id, $old, $industry->toArray(), $request);

        return $this->success($industry);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $industry = Industry::query()->findOrFail($id);
        $circlesExists = $this->circleIndustryQuery($id, (string) $industry->name)->exists();
        if ($circlesExists && isset($industry->is_active)) {
            $industry->is_active = false;
            $industry->save();
        } else {
            $industry->delete();
        }

        $this->audit->log($request->user(), 'admin.industry.delete', 'industries', $id, [], [], $request);

        return $this->success(['deleted' => true]);
    }

    public function assignId(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'uuid', 'exists:users,id']]);
        $industry = Industry::query()->findOrFail($id);
        $this->circleIndustryQuery($id, (string) $industry->name)->update(['industry_director_user_id' => $validated['user_id']]);
        return $this->success(['assigned' => true]);
    }

    public function circles(string $id): JsonResponse
    {
        $industry = Industry::query()->findOrFail($id);
        return $this->success($this->circleIndustryQuery($id, (string) $industry->name)->paginate(20));
    }

    public function stats(string $id): JsonResponse
    {
        $industry = Industry::query()->findOrFail($id);
        $circles = $this->circleIndustryQuery($id, (string) $industry->name);
        $circleIds = $circles->pluck('id');

        return $this->success([
            'total_circles' => $circles->count(),
            'active_circles' => (clone $circles)->where('status', 'active')->count(),
            'total_members' => \App\Models\CircleMember::query()->whereIn('circle_id', $circleIds)->where('status', 'approved')->whereNull('deleted_at')->count(),
            'total_revenue' => Payment::query()->whereIn('circle_id', $circleIds)->where('status', 'paid')->sum('amount'),
            'total_impacts' => \App\Models\Impact::query()->whereIn('circle_id', $circleIds)->where('status', 'approved')->count(),
        ]);
    }

    private function circleIndustryQuery(string $industryId, string $industryName)
    {
        $query = Circle::query();

        if (Schema::hasColumn('circles', 'industry_id')) {
            return $query->where('industry_id', $industryId);
        }

        if (Schema::hasColumn('circles', 'industry_tags')) {
            return $query->where(function ($q) use ($industryId, $industryName): void {
                $q->whereJsonContains('industry_tags', $industryId);
                if (trim($industryName) !== '') {
                    $q->orWhereJsonContains('industry_tags', $industryName);
                }
            });
        }

        return $query->whereRaw('1 = 0');
    }
}
