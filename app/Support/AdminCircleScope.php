<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
        return self::allowedCircleIds($admin)[0] ?? null;
    }

    public static function allowedCircleIds(?AdminUser $admin): array
    {
        if (! $admin || ! AdminAccess::isCircleScoped($admin)) {
            return [];
        }

        $user = AdminAccess::resolveAppUser($admin);
        if (! $user) {
            return [];
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

        return $query->orderByRaw("case circle_members.role::text {$orderCases} else 999 end")
            ->orderBy('circle_members.created_at')
            ->pluck('circle_members.circle_id')
            ->unique()
            ->values()
            ->all();
    }

    public static function circleOptions(?AdminUser $admin)
    {
        $query = Circle::query()->select(['id', 'name'])->orderBy('name');

        if (AdminAccess::isDed($admin)) {
            $district = AdminAccess::assignedDedDistrict($admin);

            if (! $district) {
                return collect();
            }

            return self::applyDistrictCircleScope($query, $district)->get();
        }

        if (! AdminAccess::isCircleScoped($admin)) {
            return $query->get();
        }

        $circleIds = self::allowedCircleIds($admin);

        if ($circleIds === []) {
            return collect();
        }

        return $query->whereIn('id', $circleIds)->get();
    }

    public static function circleUserIdsSubquery(string|array $circleIds): Builder
    {
        $circleIds = is_array($circleIds) ? $circleIds : [$circleIds];

        return CircleMember::query()
            ->select('user_id')
            ->whereIn('circle_id', $circleIds)
            ->where('status', 'approved')
            ->whereNull('deleted_at');
    }

    public static function applyToActivityQuery($query, ?AdminUser $admin, string $primaryColumn, ?string $peerColumn): void
    {
        if (AdminAccess::isDed($admin)) {
            if ($peerColumn) {
                $query->where(function ($districtQuery) use ($admin, $primaryColumn, $peerColumn): void {
                    self::applyDistrictUserScope($districtQuery, $admin, $primaryColumn);
                    $districtQuery->orWhere(function ($peerQuery) use ($admin, $peerColumn): void {
                        self::applyDistrictUserScope($peerQuery, $admin, $peerColumn);
                    });
                });

                return;
            }

            self::applyDistrictUserScope($query, $admin, $primaryColumn);
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

        if (! AdminAccess::isCircleScoped($admin)) {
            return;
        }

        $circleIds = self::allowedCircleIds($admin);

        if ($circleIds === []) {
            $query->whereRaw('1=0');
            return;
        }

        $circleUserIds = self::circleUserIdsSubquery($circleIds);

        $query->whereIn($primaryColumn, $circleUserIds);
    }

    public static function applyToUsersQuery($query, ?AdminUser $admin): void
    {
        if (AdminAccess::isDed($admin)) {
            self::applyDistrictUserScope($query, $admin, 'users.id');
            self::applyDedDistrictScope($query, $admin);
            return;
        }

        if (! AdminAccess::isCircleScoped($admin)) {
            return;
        }

        $circleIds = self::allowedCircleIds($admin);

        if ($circleIds === []) {
            $query->whereRaw('1=0');
            return;
        }

        $query->whereExists(function ($subQuery) use ($circleIds) {
            $subQuery->selectRaw(1)
                ->from('circle_members as cm')
                ->whereColumn('cm.user_id', 'users.id')
                ->where('cm.status', 'approved')
                ->whereNull('cm.deleted_at')
                ->whereIn('cm.circle_id', $circleIds);
        });
    }


    public static function applyRequestedCircleFilter($query, ?AdminUser $admin, string $userColumn, ?string $circleId): void
    {
        $circleId = trim((string) $circleId);

        if ($circleId === '' || $circleId === 'all') {
            return;
        }

        if (AdminAccess::isDed($admin) && ! self::circleBelongsToDedDistrict($admin, $circleId)) {
            $query->whereRaw('1=0');
            return;
        }

        if (! AdminAccess::isDed($admin) && AdminAccess::isCircleScoped($admin) && ! in_array($circleId, self::allowedCircleIds($admin), true)) {
            $query->whereRaw('1=0');
            return;
        }

        $query->whereExists(function ($subQuery) use ($userColumn, $circleId): void {
            $subQuery->selectRaw('1')
                ->from('circle_members as cm_filter')
                ->whereColumn('cm_filter.user_id', $userColumn)
                ->where('cm_filter.status', 'approved')
                ->whereNull('cm_filter.deleted_at')
                ->where('cm_filter.circle_id', $circleId);
        });
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

        if ($userColumn) {
            $query->whereExists(function ($subQuery) use ($userColumn, $districtName, $stateName) {
                $subQuery->selectRaw(1)
                    ->from('users as ded_scope_users')
                    ->leftJoin('cities as ded_scope_cities', 'ded_scope_cities.id', '=', 'ded_scope_users.city_id')
                    ->whereColumn('ded_scope_users.id', $userColumn);

                self::applyUserLocationPredicate($subQuery, 'ded_scope_users', 'ded_scope_cities', $districtName, $stateName);
            });

            return;
        }

        $query->where(function ($scopeQuery) use ($districtName, $stateName) {
            $scopeQuery->where(function ($directUserQuery) use ($districtName, $stateName) {
                self::applyDirectUserCityPredicate($directUserQuery, 'users', $districtName, $stateName);
            });

            if (Schema::hasTable('cities') && Schema::hasColumn('users', 'city_id')) {
                $scopeQuery->orWhereExists(function ($subQuery) use ($districtName, $stateName) {
                    $subQuery->selectRaw(1)
                        ->from('cities as ded_scope_cities')
                        ->whereColumn('ded_scope_cities.id', 'users.city_id');

                    self::applyCityDistrictPredicate($subQuery, 'ded_scope_cities', $districtName, $stateName);
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
        $query->where(function ($cityQuery) use ($cityAlias, $districtName, $stateName) {
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

        self::applyCircleLocationPredicate($query, $circleAlias, $districtName, $stateName);
    }

    private static function applyCircleLocationPredicate($query, string $circleAlias, string $districtName, ?string $stateName): void
    {
        $query->where(function ($circleLocationQuery) use ($circleAlias, $districtName, $stateName): void {
            if (Schema::hasColumn('circles', 'city')) {
                $circleLocationQuery->whereRaw("LOWER(NULLIF(TRIM({$circleAlias}.city), '')) = ?", [mb_strtolower($districtName)]);
            } else {
                $circleLocationQuery->whereRaw('1=0');
            }

            if (Schema::hasColumn('circles', 'city_id') && Schema::hasTable('cities')) {
                $circleLocationQuery->orWhereExists(function ($citySubQuery) use ($circleAlias, $districtName, $stateName): void {
                    $citySubQuery->selectRaw(1)
                        ->from('cities as ded_scope_circle_cities')
                        ->whereColumn('ded_scope_circle_cities.id', "{$circleAlias}.city_id");

                    self::applyCityDistrictPredicate($citySubQuery, 'ded_scope_circle_cities', $districtName, $stateName);
                });
            }
        });
    }

    public static function applyToEventsQuery($query, ?AdminUser $admin, string $eventTable = 'events'): void
    {
        if (! AdminAccess::isDed($admin)) {
            return;
        }

        if (! Schema::hasColumn($eventTable, 'circle_id') || ! Schema::hasTable('circles')) {
            $query->whereRaw('1=0');
            return;
        }

        $query->whereExists(function ($subQuery) use ($eventTable, $admin) {
            $subQuery->selectRaw(1)
                ->from('circles as ded_scope_circles')
                ->whereColumn('ded_scope_circles.id', "{$eventTable}.circle_id");

            self::applyToCirclesQuery($subQuery, $admin, 'ded_scope_circles');
        });
    }

    public static function eventInScope(?AdminUser $admin, string $eventId): bool
    {
        if (! AdminAccess::isDed($admin)) {
            return true;
        }

        $query = \App\Models\Event::query()->whereKey($eventId);
        self::applyToEventsQuery($query, $admin);

        return $query->exists();
    }

    public static function userInScope(?AdminUser $admin, string $userId): bool
    {
        if (AdminAccess::isDed($admin)) {
            $district = AdminAccess::assignedDedDistrict($admin);

            if (! $district) {
                return false;
            }

            $query = DB::table('users')
                ->leftJoin('cities', 'cities.id', '=', 'users.city_id')
                ->where('users.id', $userId);

            self::applyUserDistrictCriteria($query, 'users', 'cities', $district);
            $query = User::query()->whereKey($userId);
            self::applyDedDistrictScope($query, $admin);

            return $query->exists();
        }

        if (! AdminAccess::isCircleScoped($admin)) {
            return true;
        }

        $circleIds = self::allowedCircleIds($admin);

        if ($circleIds === []) {
            return false;
        }

        return CircleMember::query()
            ->where('user_id', $userId)
            ->whereIn('circle_id', $circleIds)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->exists();
    }

    public static function circleBelongsToDedDistrict(?AdminUser $admin, string $circleId): bool
    {
        $district = AdminAccess::assignedDedDistrict($admin);

        if (! $district) {
            return false;
        }

        return self::applyDistrictCircleScope(Circle::query()->where('circles.id', $circleId), $district)->exists();
    }

    public static function applyDedDistrictUserScope($query, ?AdminUser $admin, string $userColumn): void
    {
        self::applyDistrictUserScope($query, $admin, $userColumn);
    }

    public static function applyDedDistrictCircleScope($query, ?AdminUser $admin): void
    {
        $district = AdminAccess::assignedDedDistrict($admin);

        if (! $district) {
            $query->whereRaw('1=0');
            return;
        }

        self::applyDistrictCircleScope($query, $district);
    }

    private static function applyDistrictCircleScope($query, array $district)
    {
        return $query->whereExists(function ($subQuery) use ($district): void {
            $subQuery->selectRaw('1')
                ->from('cities as district_scope_circle_cities')
                ->whereColumn('district_scope_circle_cities.id', 'circles.city_id');

            self::applyCityDistrictCriteria($subQuery, 'district_scope_circle_cities', $district);
        });
    }

    private static function applyCityDistrictCriteria($query, string $cityAlias, array $district): void
    {
        $districtName = self::normalizeLocationValue($district['name'] ?? null);

        if ($districtName === '') {
            $query->whereRaw('1=0');
            return;
        }

        $query->where(function ($locationQuery) use ($cityAlias, $districtName): void {
            $locationQuery->whereRaw("LOWER(TRIM(COALESCE({$cityAlias}.district, ''))) = ?", [$districtName])
                ->orWhereRaw("LOWER(TRIM(COALESCE({$cityAlias}.name, ''))) = ?", [$districtName]);
        });
    }

    private static function applyUserDistrictCriteria($query, string $userAlias, string $cityAlias, array $district): void
    {
        $districtName = self::normalizeLocationValue($district['name'] ?? null);

        if ($districtName === '') {
            $query->whereRaw('1=0');
            return;
        }

        $query->where(function ($locationQuery) use ($userAlias, $cityAlias, $districtName): void {
            self::appendUserCityStringMatch($locationQuery, $userAlias, $districtName);

            $locationQuery->orWhereRaw("LOWER(TRIM(COALESCE({$cityAlias}.name, ''))) = ?", [$districtName])
                ->orWhereRaw("LOWER(TRIM(COALESCE({$cityAlias}.district, ''))) = ?", [$districtName]);
        });
    }

    private static function appendUserCityStringMatch($query, string $userAlias, string $districtName): void
    {
        if (Schema::hasColumn('users', 'city')) {
            $query->whereRaw("LOWER(TRIM(COALESCE({$userAlias}.city, ''))) = ?", [$districtName]);
            return;
        }

        $query->whereRaw('1=0');
    }

    private static function normalizeLocationValue(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private static function applyDistrictUserScope($query, ?AdminUser $admin, string $userColumn): void
    {
        $district = AdminAccess::assignedDedDistrict($admin);

        if (! $district) {
            $query->whereRaw('1=0');
            return;
        }

        $query->whereExists(function ($subQuery) use ($userColumn, $district): void {
            $subQuery->selectRaw('1')
                ->from('users as district_scope_users')
                ->leftJoin('cities as district_scope_cities', 'district_scope_cities.id', '=', 'district_scope_users.city_id')
                ->whereRaw('district_scope_users.id::text = ' . $userColumn . '::text');

            self::applyUserDistrictCriteria($subQuery, 'district_scope_users', 'district_scope_cities', $district);
        });
    }
}
