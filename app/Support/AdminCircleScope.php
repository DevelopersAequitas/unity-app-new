<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\CircleMember;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminCircleScope
{
    private const ROLE_PRIORITY = [
        'circle_leader' => 0,
        'chair' => 1,
        'vice_chair' => 2,
        'secretary' => 3,
        'founder' => 4,
        'director' => 5,
        'committee_leader' => 6,
        'member' => 7,
    ];

    public static function resolveCircleId(?AdminUser $admin): ?string
    {
        if (! $admin) {
            return null;
        }

        $activeScopeId = session('activeScopeId');
        if ($activeScopeId && $activeScopeId !== 'All') {
            $allowed = AdminAccess::allowedCircleIds($admin);
            if (in_array($activeScopeId, $allowed, true)) {
                return $activeScopeId;
            }
        }

        if (! AdminAccess::isCircleScoped($admin)) {
            return null;
        }

        $user = AdminAccess::resolveAppUser($admin);
        if (! $user) {
            return null;
        }

        $roles = array_keys(self::ROLE_PRIORITY);
        $orderCases = collect(self::ROLE_PRIORITY)
            ->map(fn ($priority, $role) => "when '{$role}' then {$priority}")
            ->implode(' ');

        $query = CircleMember::query()
            ->select('circle_members.circle_id')
            ->where('circle_members.user_id', $user->id)
            ->where('circle_members.status', 'approved')
            ->whereNull('circle_members.deleted_at')
            ->whereIn(DB::raw('circle_members.role::text'), $roles);

        if (Schema::hasColumn('circles', 'status')) {
            $query->leftJoin('circles', 'circles.id', '=', 'circle_members.circle_id')
                ->orderByRaw("case when circles.status = 'active' then 0 else 1 end");
        }

        $query->orderByRaw("case circle_members.role::text {$orderCases} else 999 end")
            ->orderBy('circle_members.created_at');

        return $query->value('circle_members.circle_id');
    }

    public static function getSessionCircleIds(?AdminUser $admin): array
    {
        if (! $admin) {
            return [];
        }

        $allCircles = AdminAccess::allowedCircleIds($admin);
        $activeScopeId = session('activeScopeId');

        if ($activeScopeId && $activeScopeId !== 'All') {
            if (in_array($activeScopeId, $allCircles, true)) {
                return [$activeScopeId];
            }

            return [];
        }

        return $allCircles;
    }

    public static function circleUserIdsSubquery(string $circleId): Builder
    {
        return self::circleUserIdsSubqueryForCircleIds([$circleId]);
    }

    public static function circleUserIdsSubqueryForCircleIds(array $circleIds): Builder
    {
        return CircleMember::query()
            ->select('user_id')
            ->whereIn('circle_id', $circleIds)
            ->where('status', 'approved')
            ->whereNull('deleted_at');
    }

    public static function applyToActivityQuery($query, ?AdminUser $admin, string $primaryColumn, ?string $peerColumn): void
    {
        if (! $admin) {
            return;
        }

        if (AdminAccess::isSuper($admin)) {
            return;
        }

        $activeScopeId = session('activeScopeId');
        if (AdminAccess::isDed($admin) && (! $activeScopeId || $activeScopeId === 'All')) {
            $query->where(function ($districtQuery) use ($admin, $primaryColumn, $peerColumn) {
                self::applyDedDistrictScope($districtQuery, $admin, $primaryColumn);

                if ($peerColumn) {
                    $districtQuery->orWhere(function ($peerDistrictQuery) use ($admin, $peerColumn) {
                        self::applyDedDistrictScope($peerDistrictQuery, $admin, $peerColumn);
                    });
                }
            });

            return;
        }

        $allowedCircleIds = self::getSessionCircleIds($admin);

        if (empty($allowedCircleIds)) {
            $query->whereRaw('1=0');

            return;
        }

        $query->where(function ($circleScopeQuery) use ($primaryColumn, $peerColumn, $allowedCircleIds) {
            $circleScopeQuery->whereIn($primaryColumn, self::circleUserIdsSubqueryForCircleIds($allowedCircleIds));

            if ($peerColumn) {
                $circleScopeQuery->orWhereIn($peerColumn, self::circleUserIdsSubqueryForCircleIds($allowedCircleIds));
            }
        });
    }

    public static function applyToUsersQuery($query, ?AdminUser $admin): void
    {
        if (! $admin) {
            return;
        }

        if (AdminAccess::isSuper($admin)) {
            return;
        }

        $activeScopeId = session('activeScopeId');
        if (AdminAccess::isDed($admin) && (! $activeScopeId || $activeScopeId === 'All')) {
            self::applyDedDistrictScope($query, $admin);

            return;
        }

        $allowedCircleIds = self::getSessionCircleIds($admin);

        if (empty($allowedCircleIds)) {
            $query->whereRaw('1=0');

            return;
        }

        $query->whereExists(function ($subQuery) use ($allowedCircleIds) {
            $subQuery->selectRaw(1)
                ->from('circle_members as cm')
                ->whereColumn('cm.user_id', 'users.id')
                ->where('cm.status', 'approved')
                ->whereNull('cm.deleted_at')
                ->whereIn('cm.circle_id', $allowedCircleIds);
        });
    }

    private static ?array $cachedCircleIds = null;

    public static function resetCache(): void
    {
        self::$cachedCircleIds = null;
    }

    public static function getDedCircleIds(?AdminUser $admin): array
    {
        if (! $admin || ! AdminAccess::isDed($admin)) {
            return [];
        }

        if (self::$cachedCircleIds !== null) {
            return self::$cachedCircleIds;
        }

        $location = AdminAccess::assignedDedLocation($admin);
        $districtName = trim((string) ($location['district_name'] ?? ''));
        $districtId = $location['district_id'] ?? null;

        if (! $districtName && ! $districtId) {
            return [];
        }

        $cacheKey = 'ded-circle-ids:'.$admin->id;

        self::$cachedCircleIds = Cache::remember($cacheKey, 300, function () use ($districtName, $districtId) {
            $query = DB::table('circles as c');
            if (Schema::hasColumn('circles', 'deleted_at')) {
                $query->whereNull('c.deleted_at');
            }

            $query->where(function ($q) use ($districtName, $districtId) {
                if (Schema::hasColumn('circles', 'district_id') && $districtId) {
                    $q->orWhere('c.district_id', $districtId);
                }

                if (Schema::hasColumn('circles', 'city') && $districtName !== '') {
                    $q->orWhereRaw("LOWER(NULLIF(TRIM(c.city), '')) = ?", [mb_strtolower($districtName)]);
                    $q->orWhereRaw("LOWER(NULLIF(TRIM(c.city), '')) LIKE ?", ['%'.mb_strtolower($districtName).'%']);
                }

                if (Schema::hasColumn('circles', 'city_id') && Schema::hasTable('cities')) {
                    $q->orWhereExists(function ($citySubQuery) use ($districtName, $districtId): void {
                        $citySubQuery->selectRaw(1)
                            ->from('cities as ded_scope_circle_cities')
                            ->whereColumn('ded_scope_circle_cities.id', 'c.city_id')
                            ->where(function ($cSub) use ($districtName, $districtId) {
                                if ($districtId && Schema::hasColumn('cities', 'district_id')) {
                                    $cSub->orWhere('ded_scope_circle_cities.district_id', $districtId);
                                }
                                if ($districtName !== '') {
                                    if (Schema::hasColumn('cities', 'name')) {
                                        $cSub->orWhereRaw("LOWER(NULLIF(TRIM(ded_scope_circle_cities.name), '')) = ?", [mb_strtolower($districtName)]);
                                        $cSub->orWhereRaw("LOWER(NULLIF(TRIM(ded_scope_circle_cities.name), '')) LIKE ?", ['%'.mb_strtolower($districtName).'%']);
                                    }
                                    if (Schema::hasColumn('cities', 'district')) {
                                        $cSub->orWhereRaw("LOWER(NULLIF(TRIM(ded_scope_circle_cities.district), '')) = ?", [mb_strtolower($districtName)]);
                                        $cSub->orWhereRaw("LOWER(NULLIF(TRIM(ded_scope_circle_cities.district), '')) LIKE ?", ['%'.mb_strtolower($districtName).'%']);
                                    }
                                    if (Schema::hasColumn('cities', 'district_name')) {
                                        $cSub->orWhereRaw("LOWER(NULLIF(TRIM(ded_scope_circle_cities.district_name), '')) = ?", [mb_strtolower($districtName)]);
                                    }
                                }
                            });
                    });
                }
            });

            return $query->pluck('c.id')->unique()->values()->all();
        });

        return self::$cachedCircleIds;
    }

    public static function applyDedDistrictScope($query, ?AdminUser $admin, ?string $userColumn = null): void
    {
        if (! AdminAccess::isDed($admin)) {
            return;
        }

        $location = AdminAccess::assignedDedLocation($admin);
        $districtName = $location['district_name'] ?? null;
        $stateName = $location['state_name'] ?? null;

        if (! $districtName) {
            $query->whereRaw('1=0');

            return;
        }

        $allowedCircleIds = self::getDedCircleIds($admin);

        $query->where(function ($scopeQuery) use ($districtName, $stateName, $userColumn, $allowedCircleIds): void {
            $userIdExpression = $userColumn ?: 'users.id';

            // 1. Direct user location check
            $scopeQuery->where(function ($directQuery) use ($districtName, $stateName, $userColumn): void {
                if ($userColumn) {
                    $directQuery->whereExists(function ($subQuery) use ($userColumn, $districtName, $stateName) {
                        $subQuery->selectRaw(1)
                            ->from('users as ded_scope_users')
                            ->leftJoin('cities as ded_scope_cities', 'ded_scope_cities.id', '=', 'ded_scope_users.city_id')
                            ->whereColumn('ded_scope_users.id', $userColumn);

                        self::applyUserLocationPredicate($subQuery, 'ded_scope_users', 'ded_scope_cities', $districtName, $stateName);
                    });
                } else {
                    $directQuery->where(function ($directUserQuery) use ($districtName, $stateName) {
                        self::applyDirectUserCityPredicate($directUserQuery, 'users', $districtName, $stateName);
                    });

                    if (Schema::hasTable('cities') && Schema::hasColumn('users', 'city_id')) {
                        $directQuery->orWhereExists(function ($subQuery) use ($districtName, $stateName) {
                            $subQuery->selectRaw(1)
                                ->from('cities as ded_scope_cities')
                                ->whereColumn('ded_scope_cities.id', 'users.city_id');

                            self::applyCityDistrictPredicate($subQuery, 'ded_scope_cities', $districtName, $stateName);
                        });
                    }
                }
            });

            // 2. User is a member of a circle in the district
            if (! empty($allowedCircleIds)) {
                $scopeQuery->orWhereExists(function ($subQuery) use ($userIdExpression, $allowedCircleIds): void {
                    $subQuery->selectRaw(1)
                        ->from('circle_members as scm')
                        ->whereColumn('scm.user_id', $userIdExpression)
                        ->whereIn('scm.circle_id', $allowedCircleIds)
                        ->whereNull('scm.deleted_at');
                });
            }

            // 2.5 User is a founder, director, or industry director of an allowed circle
            if (! empty($allowedCircleIds)) {
                $scopeQuery->orWhereExists(function ($subQuery) use ($userIdExpression, $allowedCircleIds): void {
                    $subQuery->selectRaw(1)
                        ->from('circles as sc')
                        ->whereIn('sc.id', $allowedCircleIds)
                        ->where(function ($q) use ($userIdExpression) {
                            $q->whereColumn('sc.circle_founder_user_id', $userIdExpression)
                                ->orWhereColumn('sc.circle_director_user_id', $userIdExpression)
                                ->orWhereColumn('sc.industry_director_user_id', $userIdExpression);
                        });
                });
            }
        });
    }

    private static function applyUserLocationPredicate($query, string $userAlias, string $cityAlias, string $districtName, ?string $stateName): void
    {
        $query->where(function ($locationQuery) use ($userAlias, $cityAlias, $districtName, $stateName) {
            self::applyDirectUserCityPredicate($locationQuery, $userAlias, $districtName, $stateName);

            if (Schema::hasTable('cities')) {
                $locationQuery->orWhere(function ($cityQuery) use ($cityAlias, $districtName, $stateName) {
                    self::applyCityDistrictPredicate($cityQuery, $cityAlias, $districtName, $stateName);
                });
            }
        });
    }

    private static function applyDirectUserCityPredicate($query, string $userAlias, string $districtName, ?string $stateName): void
    {
        if (! Schema::hasColumn('users', 'city')) {
            $query->whereRaw('1=0');

            return;
        }

        $query->whereRaw("LOWER(NULLIF(TRIM({$userAlias}.city), '')) = ?", [mb_strtolower($districtName)]);
    }

    private static function applyCityDistrictPredicate($query, string $cityAlias, string $districtName, ?string $stateName): void
    {
        $query->where(function ($cityQuery) use ($cityAlias, $districtName) {
            $hasLocationColumn = false;

            if (Schema::hasColumn('cities', 'name')) {
                $cityQuery->whereRaw("LOWER(NULLIF(TRIM({$cityAlias}.name), '')) = ?", [mb_strtolower($districtName)]);
                $hasLocationColumn = true;
            }

            if (Schema::hasColumn('cities', 'district')) {
                $method = $hasLocationColumn ? 'orWhereRaw' : 'whereRaw';
                $cityQuery->{$method}("LOWER(NULLIF(TRIM({$cityAlias}.district), '')) = ?", [mb_strtolower($districtName)]);
                $hasLocationColumn = true;
            }

            if (! $hasLocationColumn) {
                $cityQuery->whereRaw('1=0');
            }
        });

        if ($stateName && Schema::hasColumn('cities', 'state')) {
            $query->where(function ($stateQuery) use ($cityAlias, $stateName) {
                $stateQuery->whereNull("{$cityAlias}.state")
                    ->orWhereRaw("NULLIF(TRIM({$cityAlias}.state), '') IS NULL")
                    ->orWhereRaw("LOWER(NULLIF(TRIM({$cityAlias}.state), '')) = ?", [mb_strtolower($stateName)]);
            });
        }
    }

    public static function applyToCirclesQuery($query, ?AdminUser $admin, string $circleAlias = 'circles'): void
    {
        if (! $admin) {
            return;
        }

        if (AdminAccess::isSuper($admin)) {
            return;
        }

        $allowedCircleIds = self::getSessionCircleIds($admin);

        if (empty($allowedCircleIds)) {
            $query->whereRaw('1=0');
        } else {
            $query->whereIn("{$circleAlias}.id", $allowedCircleIds);
        }
    }

    public static function applyToEventsQuery($query, ?AdminUser $admin, string $eventTable = 'events'): void
    {
        if (! $admin || ! Schema::hasColumn($eventTable, 'circle_id') || ! Schema::hasTable('circles')) {
            return;
        }

        if (AdminAccess::isSuper($admin)) {
            return;
        }

        $allowedCircleIds = self::getSessionCircleIds($admin);

        if (empty($allowedCircleIds)) {
            $query->whereRaw('1=0');
        } else {
            $query->whereIn("{$eventTable}.circle_id", $allowedCircleIds);
        }
    }

    public static function eventInScope(?AdminUser $admin, string $eventId): bool
    {
        if (! AdminAccess::isDed($admin) && ! AdminAccess::isCircleScoped($admin)) {
            return true;
        }

        $query = Event::query()->whereKey($eventId);
        self::applyToEventsQuery($query, $admin);

        return $query->exists();
    }

    public static function userInScope(?AdminUser $admin, string $userId): bool
    {
        if (AdminAccess::isDed($admin)) {
            $query = User::query()->whereKey($userId);
            self::applyDedDistrictScope($query, $admin);

            return $query->exists();
        }

        if (! AdminAccess::isCircleScoped($admin)) {
            return true;
        }

        $allowedCircleIds = AdminAccess::allowedCircleIds($admin);

        if ($allowedCircleIds === []) {
            return false;
        }

        return CircleMember::query()
            ->where('user_id', $userId)
            ->whereIn('circle_id', $allowedCircleIds)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->exists();
    }
}
