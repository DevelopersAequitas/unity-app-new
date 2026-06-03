<?php

namespace App\Http\Controllers\Admin\IndustryDirector;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\CoinLedger;
use App\Models\Industry;
use App\Models\IndustryDirectorAssignment;
use App\Models\LifeImpactHistory;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class IndustryDirectorDashboardController extends Controller
{
    public function index(): View
    {
        $admin = Auth::guard('admin')->user();
        $assignment = IndustryDirectorAssignment::query()
            ->where('admin_user_id', $admin->id)
            ->where('is_active', true)
            ->firstOrFail();

        $industry = Industry::query()->find($assignment->industry_id);
        $industries = $this->assignedIndustryTree($assignment->industry_id);
        $industryIds = $industries->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
        $industryNames = $industries->pluck('name')->filter()->map(fn ($name) => (string) $name)->values()->all();

        $industryUserIds = $this->industryUsersQuery($industryIds, $industryNames)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $industryCircleIds = $this->industryCirclesQuery($industryIds, $industryNames)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $metrics = [
            'total_industry_members' => count($industryUserIds),
            'active_members' => $this->industryUsersQuery($industryIds, $industryNames)
                ->when(Schema::hasColumn('users', 'status'), fn (Builder $query) => $query->where('status', 'active'))
                ->count(),
            'new_registrations' => $this->industryUsersQuery($industryIds, $industryNames)
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
            'total_activities' => Activity::query()
                ->where(function (Builder $query) use ($industryUserIds, $industryCircleIds): void {
                    if ($industryUserIds === [] && $industryCircleIds === []) {
                        $query->whereRaw('1 = 0');
                        return;
                    }

                    $query->when($industryUserIds !== [], fn (Builder $q) => $q->orWhereIn('user_id', $industryUserIds));
                    $query->when($industryCircleIds !== [], fn (Builder $q) => $q->orWhereIn('circle_id', $industryCircleIds));
                })
                ->count(),
            'total_posts' => Post::query()
                ->where(function (Builder $query) use ($industryUserIds, $industryCircleIds): void {
                    if ($industryUserIds === [] && $industryCircleIds === []) {
                        $query->whereRaw('1 = 0');
                        return;
                    }

                    $query->when($industryUserIds !== [], fn (Builder $q) => $q->orWhereIn('user_id', $industryUserIds));
                    $query->when($industryCircleIds !== [], fn (Builder $q) => $q->orWhereIn('circle_id', $industryCircleIds));
                })
                ->when(Schema::hasColumn('posts', 'is_deleted'), fn (Builder $query) => $query->where('is_deleted', false))
                ->count(),
            'pending_requests_count' => CircleJoinRequest::query()
                ->whereIn('status', [CircleJoinRequest::STATUS_PENDING_ID_APPROVAL, CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE])
                ->when($industryCircleIds !== [], fn (Builder $query) => $query->whereIn('circle_id', $industryCircleIds), fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->count(),
            'total_circles' => count($industryCircleIds),
            'total_coins_earned' => CoinLedger::query()
                ->when($industryUserIds !== [], fn (Builder $query) => $query->whereIn('user_id', $industryUserIds), fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->where('amount', '>', 0)
                ->sum('amount'),
            'life_impact' => LifeImpactHistory::query()
                ->when($industryUserIds !== [], fn (Builder $query) => $query->whereIn('user_id', $industryUserIds), fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->sum('life_impacted'),
        ];

        return view('admin.industry-director.dashboard', [
            'industry' => $industry,
            'industryCount' => count($industryIds),
            'metrics' => $metrics,
        ]);
    }

    private function assignedIndustryTree(string $industryId): Collection
    {
        $industries = Industry::query()
            ->where('id', $industryId)
            ->orWhere('parent_id', $industryId)
            ->get(['id', 'parent_id', 'name']);

        $frontier = $industries->pluck('id')->map(fn ($id) => (string) $id)->all();
        $seen = $frontier;

        while ($frontier !== []) {
            $children = Industry::query()
                ->whereIn('parent_id', $frontier)
                ->get(['id', 'parent_id', 'name']);

            $newChildren = $children->reject(fn (Industry $industry) => in_array((string) $industry->id, $seen, true));

            if ($newChildren->isEmpty()) {
                break;
            }

            $industries = $industries->merge($newChildren);
            $frontier = $newChildren->pluck('id')->map(fn ($id) => (string) $id)->all();
            $seen = array_merge($seen, $frontier);
        }

        return $industries->unique('id')->values();
    }

    private function industryUsersQuery(array $industryIds, array $industryNames): Builder
    {
        return User::query()
            ->when(Schema::hasColumn('users', 'deleted_at'), fn (Builder $query) => $query->whereNull('deleted_at'))
            ->where(function (Builder $query) use ($industryIds, $industryNames): void {
                if (! Schema::hasColumn('users', 'industry_tags') || ($industryIds === [] && $industryNames === [])) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                foreach (array_merge($industryIds, $industryNames) as $industryValue) {
                    $query->orWhereJsonContains('industry_tags', $industryValue);
                }
            });
    }

    private function industryCirclesQuery(array $industryIds, array $industryNames): Builder
    {
        return Circle::query()
            ->when(Schema::hasColumn('circles', 'deleted_at'), fn (Builder $query) => $query->whereNull('deleted_at'))
            ->where(function (Builder $query) use ($industryIds, $industryNames): void {
                $hasIndustryFilter = false;

                if ($industryIds === [] && $industryNames === []) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                if (Schema::hasColumn('circles', 'industry_id')) {
                    $query->orWhereIn('industry_id', $industryIds);
                    $hasIndustryFilter = true;
                }

                if (Schema::hasColumn('circles', 'industry_tags')) {
                    $hasIndustryFilter = true;
                    foreach (array_merge($industryIds, $industryNames) as $industryValue) {
                        $query->orWhereJsonContains('industry_tags', $industryValue);
                    }
                }

                if (! $hasIndustryFilter) {
                    $query->whereRaw('1 = 0');
                }
            });
    }
}
