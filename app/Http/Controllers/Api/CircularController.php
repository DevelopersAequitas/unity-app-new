<?php

namespace App\Http\Controllers\Api;

use App\Models\Circular;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CircularController extends BaseApiController
{
    public function index(Request $request)
    {
        $user = $request->user();
        $now = now(config('app.timezone'));

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $userCircleIds = $this->resolveUserCircleIds($user);

        $totalCircularCount = Circular::query()->count();
        $totalActiveCircularCount = Circular::query()->active()->count();

        $query = Circular::query()->visibleInApp($now);
        $baseVisibleCount = (clone $query)->count();

        $this->applyAudienceFilter($query, $user, $userCircleIds);
        $afterAudienceCount = (clone $query)->count();

        $query->orderByDesc('is_pinned')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'important' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderByDesc('publish_date');

        Log::info('Circular feed filters applied', [
            'user_id' => $user?->id,
            'user_city_id' => $user?->city_id,
            'user_circle_ids' => $userCircleIds,
            'now' => $now->toIso8601String(),
            'total_circular_count' => $totalCircularCount,
            'total_active_circular_count' => $totalActiveCircularCount,
            'base_visible_count' => $baseVisibleCount,
            'after_audience_count' => $afterAudienceCount,
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'per_page' => $perPage,
        ]);

        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(fn (Circular $circular) => [
            'id' => $circular->id,
            'title' => $circular->title,
            'summary' => $circular->summary,
            'category' => $circular->category,
            'priority' => $circular->priority,
            'featured_image_url' => $circular->featured_image_url,
            'publish_date' => optional($circular->publish_date)?->toIso8601String(),
            'slug' => $circular->slug,
            'cta_label' => $circular->cta_label,
            'cta_url' => $circular->cta_url,
            'view_count' => (int) $circular->view_count,
            'is_pinned' => (bool) $circular->is_pinned,
            'allow_comments' => (bool) $circular->allow_comments,
        ])->values();

        Log::info('Circular feed response built', [
            'user_id' => $user?->id,
            'final_response_item_count' => $items->count(),
            'final_total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ]);

        return $this->success([
            'items' => $items,
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    public function show(Request $request, string $identifier)
    {
        $user = $request->user();
        $now = now(config('app.timezone'));
        $userCircleIds = $this->resolveUserCircleIds($user);

        $query = Circular::query()
            ->visibleInApp($now)
            ->with(['city:id,name', 'circle:id,name']);

        $this->applyAudienceFilter($query, $user, $userCircleIds);

        $circular = (clone $query)
            ->where(function (Builder $identifierQuery) use ($identifier): void {
                $identifierQuery->where('slug', $identifier)->orWhere('id', $identifier);
            })
            ->first();

        Log::info('Circular detail filters applied', [
            'user_id' => $user?->id,
            'user_city_id' => $user?->city_id,
            'user_circle_ids' => $userCircleIds,
            'identifier' => $identifier,
            'now' => $now->toIso8601String(),
            'matched' => (bool) $circular,
        ]);

        if (! $circular) {
            return $this->error('Circular not found.', 404);
        }

        Circular::query()->whereKey($circular->id)->increment('view_count');
        $circular->refresh()->loadMissing(['city:id,name', 'circle:id,name']);

        return $this->success([
            'id' => $circular->id,
            'title' => $circular->title,
            'summary' => $circular->summary,
            'content' => $circular->content,
            'featured_image_url' => $circular->featured_image_url,
            'attachment_url' => $circular->attachment_url,
            'video_url' => $circular->video_url,
            'category' => $circular->category,
            'priority' => $circular->priority,
            'publish_date' => optional($circular->publish_date)?->toIso8601String(),
            'expiry_date' => optional($circular->expiry_date)?->toIso8601String(),
            'cta_label' => $circular->cta_label,
            'cta_url' => $circular->cta_url,
            'allow_comments' => (bool) $circular->allow_comments,
            'send_push_notification' => (bool) $circular->send_push_notification,
            'is_pinned' => (bool) $circular->is_pinned,
            'city' => $circular->city ? ['id' => $circular->city->id, 'name' => $circular->city->name] : null,
            'circle' => $circular->circle ? ['id' => $circular->circle->id, 'name' => $circular->circle->name] : null,
            'view_count' => (int) $circular->view_count,
        ]);
    }

    private function applyAudienceFilter(Builder $query, $user, array $userCircleIds): void
    {
        $userCityId = $user?->city_id;
        $userType = strtolower((string) ($user->membership_type ?? $user->member_type ?? $user->persona ?? ''));

        $query->where(function (Builder $audienceQuery) use ($userType, $userCircleIds): void {
            $audienceQuery
                ->whereNull('audience_type')
                ->orWhere('audience_type', 'all_members')
                ->orWhere(function (Builder $circleAudience) use ($userCircleIds): void {
                    $circleAudience->where('audience_type', 'circle_members')
                        ->where(function (Builder $circleScope) use ($userCircleIds): void {
                            $circleScope->whereNull('circle_id');

                            if ($userCircleIds !== []) {
                                $circleScope->orWhereIn('circle_id', $userCircleIds);
                            }
                        });
                })
                ->orWhere(function (Builder $fempreneurAudience) use ($userType): void {
                    $fempreneurAudience->where('audience_type', 'fempreneur');

                    if ($userType !== '' && $userType !== 'fempreneur') {
                        $fempreneurAudience->whereRaw('1=0');
                    }
                })
                ->orWhere(function (Builder $greenpreneurAudience) use ($userType): void {
                    $greenpreneurAudience->where('audience_type', 'greenpreneur');

                    if ($userType !== '' && $userType !== 'greenpreneur') {
                        $greenpreneurAudience->whereRaw('1=0');
                    }
                });
        });

        if ($userCityId) {
            $query->where(function (Builder $cityScope) use ($userCityId): void {
                $cityScope->whereNull('city_id')->orWhere('city_id', $userCityId);
            });
        }
    }

    private function resolveUserCircleIds($user): array
    {
        if (! $user?->id || ! Schema::hasTable('circle_members') || ! Schema::hasColumn('circle_members', 'user_id') || ! Schema::hasColumn('circle_members', 'circle_id')) {
            return [];
        }

        return DB::table('circle_members')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->whereNotNull('circle_id')
            ->pluck('circle_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
