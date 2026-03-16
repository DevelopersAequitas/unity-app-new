<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\CircularDetailResource;
use App\Http\Resources\CircularListResource;
use App\Models\Circular;
use App\Models\CircleMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CircularController extends BaseApiController
{
    public function index(Request $request)
    {
        /** @var User|null $user */
        $user = $request->user();
        $now = now();
        $model = new Circular();
        $connectionName = $model->getConnectionName() ?: config('database.default');

        // TEMP DEBUG FOR CIRCULAR API
        Log::debug('circulars_api_reached', [
            'path' => $request->path(),
            'full_url' => $request->fullUrl(),
            'app_timezone' => config('app.timezone'),
            'php_now' => $now->toIso8601String(),
            'db_connection_name' => $connectionName,
            'db_name' => config('database.connections.' . $connectionName . '.database'),
        ]);

        // TEMP DEBUG FOR CIRCULAR API
        Log::debug('circulars_api_user', [
            'request_user_is_null' => $user === null,
            'request_user_id' => $user?->id,
            'request_user_class' => $user ? get_class($user) : null,
            'auth_id' => Auth::id(),
            'auth_user_id' => Auth::user()?->id,
            'auth_user_class' => Auth::user() ? get_class(Auth::user()) : null,
            'default_guard' => config('auth.defaults.guard'),
        ]);

        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        // TEMP DEBUG FOR CIRCULAR API: exact requested count sequence
        $countTotal = Circular::count();
        $countActive = Circular::query()->where('status', 'active')->count();
        $countActiveNotDeleted = Circular::query()->where('status', 'active')->whereNull('deleted_at')->count();
        $countActivePublished = Circular::query()->where('status', 'active')->whereNull('deleted_at')->where('publish_date', '<=', $now)->count();
        $countActivePublishedExpiryValid = Circular::query()->where('status', 'active')->whereNull('deleted_at')->where('publish_date', '<=', $now)->where(function ($q) use ($now) {
            $q->whereNull('expiry_date')->orWhere('expiry_date', '>', $now);
        })->count();
        $countAllMembersVisible = Circular::query()->where('status', 'active')->whereNull('deleted_at')->where('publish_date', '<=', $now)->where(function ($q) use ($now) {
            $q->whereNull('expiry_date')->orWhere('expiry_date', '>', $now);
        })->where('audience_type', 'all_members')->count();

        // TEMP DEBUG FOR CIRCULAR API: resilient equivalents for diagnosing data quality issues
        $countActiveNormalized = Circular::query()->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = ?", ['active'])->count();
        $countAllMembersNormalized = Circular::query()->whereRaw("LOWER(TRIM(COALESCE(audience_type, ''))) = ?", ['all_members'])->count();

        $baseVisibleQuery = Circular::query()->visibleNow();
        $baseVisibleCount = (clone $baseVisibleQuery)->count();

        $userCircleIds = $this->userCircleIds($user);

        $query = Circular::query()->visibleNow();
        $this->applyAudienceFilter($query, $user, $userCircleIds);

        $afterAudienceCount = (clone $query)->count();
        $query->orderedForFeed();

        // TEMP DEBUG FOR CIRCULAR API
        Log::debug('circulars_api_counts', [
            'total_circular_count' => $countTotal,
            'total_active_circular_count' => $countActive,
            'total_active_not_deleted_count' => $countActiveNotDeleted,
            'total_active_not_deleted_published_count' => $countActivePublished,
            'total_active_not_deleted_published_expiry_valid_count' => $countActivePublishedExpiryValid,
            'total_active_not_deleted_published_expiry_valid_all_members_count' => $countAllMembersVisible,
            'total_active_normalized_count' => $countActiveNormalized,
            'total_all_members_normalized_count' => $countAllMembersNormalized,
            'total_visible_by_scope_count' => $baseVisibleCount,
            'total_after_audience_filter_count' => $afterAudienceCount,
            'user_circle_ids' => $userCircleIds,
            'user_city_id' => $user->city_id,
        ]);

        // TEMP DEBUG FOR CIRCULAR API
        Log::debug('circulars_api_sql', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        $perPage = (int) min(max((int) $request->query('per_page', 20), 1), 100);
        $paginator = $query->paginate($perPage);

        $resourceCollection = CircularListResource::collection($paginator);
        $resolvedItems = $resourceCollection->resolve($request);
        $resolvedItemsCount = is_countable($resolvedItems) ? count($resolvedItems) : 0;

        // TEMP DEBUG FOR CIRCULAR API
        Log::debug('circulars_api_result', [
            'paginator_total' => $paginator->total(),
            'paginator_collection_count' => $paginator->count(),
            'final_response_item_count' => $resolvedItemsCount,
        ]);

        return $this->success([
            'items' => $resourceCollection,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id)
    {
        /** @var User|null $user */
        $user = $request->user();

        // TEMP DEBUG FOR CIRCULAR API
        Log::debug('circulars_api_reached', [
            'path' => $request->path(),
            'full_url' => $request->fullUrl(),
            'requested_id' => $id,
            'detail_endpoint' => true,
            'request_user_id' => $user?->id,
        ]);

        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        $userCircleIds = $this->userCircleIds($user);

        $query = Circular::query()->visibleNow()->where('id', $id);
        $this->applyAudienceFilter($query, $user, $userCircleIds);

        // TEMP DEBUG FOR CIRCULAR API
        Log::debug('circulars_api_sql', [
            'detail_endpoint' => true,
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        $circular = $query->first();

        // TEMP DEBUG FOR CIRCULAR API
        Log::debug('circulars_api_result', [
            'detail_endpoint' => true,
            'requested_id' => $id,
            'found' => (bool) $circular,
        ]);

        if (! $circular) {
            return $this->error('Circular not found.', 404);
        }

        return $this->success(new CircularDetailResource($circular));
    }

    private function applyAudienceFilter(Builder $query, User $user, array $userCircleIds): void
    {
        $isFempreneur = $this->userHasSegment($user, 'fempreneur');
        $isGreenpreneur = $this->userHasSegment($user, 'greenpreneur');

        $query->where(function (Builder $audience) use ($user, $userCircleIds, $isFempreneur, $isGreenpreneur): void {
            // all_members must not be blocked by circle/city filters.
            $audience->whereRaw("LOWER(TRIM(COALESCE(audience_type, ''))) = ?", ['all_members']);

            // circle_members is only for members of matching circles.
            $audience->orWhere(function (Builder $circleMembers) use ($userCircleIds): void {
                $circleMembers->whereRaw("LOWER(TRIM(COALESCE(audience_type, ''))) = ?", ['circle_members'])
                    ->whereNotNull('circle_id')
                    ->when(
                        $userCircleIds !== [],
                        fn (Builder $q) => $q->whereIn('circle_id', $userCircleIds),
                        fn (Builder $q) => $q->whereRaw('1 = 0')
                    );
            });

            if ($isFempreneur) {
                $audience->orWhere(function (Builder $fempreneur) use ($user): void {
                    $fempreneur->whereRaw("LOWER(TRIM(COALESCE(audience_type, ''))) = ?", ['fempreneur'])
                        ->where(function (Builder $city) use ($user): void {
                            $city->whereNull('city_id')
                                ->orWhere('city_id', $user->city_id);
                        });
                });
            }

            if ($isGreenpreneur) {
                $audience->orWhere(function (Builder $greenpreneur) use ($user): void {
                    $greenpreneur->whereRaw("LOWER(TRIM(COALESCE(audience_type, ''))) = ?", ['greenpreneur'])
                        ->where(function (Builder $city) use ($user): void {
                            $city->whereNull('city_id')
                                ->orWhere('city_id', $user->city_id);
                        });
                });
            }
        });
    }

    private function userCircleIds(User $user): array
    {
        $membershipStatuses = ['active', 'approved'];

        // TEMP DEBUG FOR CIRCULAR API
        Log::debug('circulars_api_user_circle_membership_query', [
            'user_id' => $user->id,
            'statuses' => $membershipStatuses,
        ]);

        return CircleMember::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->whereIn('status', $membershipStatuses)
            ->pluck('circle_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function userHasSegment(User $user, string $segment): bool
    {
        // TODO: Replace this fallback check with project-specific segment mapping once finalized.
        $segment = strtolower($segment);

        foreach (["is_{$segment}", $segment] as $flagKey) {
            $value = data_get($user, $flagKey);
            if (is_bool($value) && $value === true) {
                return true;
            }
            if (is_string($value) && in_array(strtolower($value), ['1', 'yes', 'true', $segment], true)) {
                return true;
            }
        }

        foreach (['business_type', 'designation', 'short_bio', 'long_bio_html', 'company_name'] as $textColumn) {
            $value = strtolower((string) data_get($user, $textColumn));
            if ($value !== '' && str_contains($value, $segment)) {
                return true;
            }
        }

        return false;
    }
}
