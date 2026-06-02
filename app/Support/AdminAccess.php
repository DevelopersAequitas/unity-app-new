<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\CircleMember;
use App\Models\Industry;
use App\Models\Role;
use App\Models\User;
use App\Services\IndustryDirector\IndustryScopeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AdminAccess
{
    private const CACHE_TTL = 300;

    private const SUPER_ROLE_KEYS = [
        'global_admin',
        'ded',
    ];

    private const CIRCLE_SCOPED_KEYS = [
        'circle_leader',
        'chair',
        'vice_chair',
        'secretary',
        'founder',
        'director',
        'member',
    ];

    private const CIRCLE_ROLE_PRIORITY = [
        'chair' => 1,
        'vice_chair' => 2,
        'secretary' => 3,
        'founder' => 4,
        'director' => 5,
        'committee_leader' => 6,
        'member' => 7,
    ];

    private const CIRCLE_ROLE_LABELS = [
        'chair' => 'Chair',
        'vice_chair' => 'Vice Chair',
        'secretary' => 'Secretary',
        'founder' => 'Founder',
        'director' => 'Director',
        'committee_leader' => 'Committee Leader',
        'member' => 'Member',
    ];

    public static function resolveAppUser(?AdminUser $admin): ?User
    {
        if (! $admin) {
            return null;
        }

        $cacheKey = 'admin-access:user:' . $admin->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($admin) {
            $email = trim(strtolower((string) $admin->email));
            if ($email === '') {
                return null;
            }

            return User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();
        });
    }

    public static function adminRoleKeys(?AdminUser $admin): array
    {
        if (! $admin) {
            return [];
        }

        $cacheKey = 'admin-access:roles:' . $admin->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($admin) {
            return Role::query()
                ->join('admin_user_roles', 'admin_user_roles.role_id', '=', 'roles.id')
                ->where('admin_user_roles.user_id', $admin->id)
                ->pluck('roles.key')
                ->unique()
                ->values()
                ->all();
        });
    }

    public static function isSuper(?AdminUser $admin): bool
    {
        $roleKeys = self::adminRoleKeys($admin);

        return (bool) array_intersect(self::SUPER_ROLE_KEYS, $roleKeys);
    }

    public static function isGlobalAdmin(?AdminUser $admin): bool
    {
        if (! $admin) {
            return false;
        }

        return in_array('global_admin', self::adminRoleKeys($admin), true);
    }

    public static function isIndustryScoped(?AdminUser $admin): bool
    {
        if (! $admin || self::isSuper($admin)) {
            return false;
        }

        return in_array('industry_director', self::adminRoleKeys($admin), true);
    }

    public static function assignedIndustryId(?AdminUser $admin): ?string
    {
        if (! $admin || ! self::isIndustryScoped($admin)) {
            return null;
        }

        return app(IndustryScopeService::class)->assignedIndustryIdForAdmin((string) $admin->id);
    }

    public static function primaryIndustryName(?AdminUser $admin): ?string
    {
        $industryId = self::assignedIndustryId($admin);

        if (! $industryId) {
            return null;
        }

        return Industry::query()->whereKey($industryId)->value('name');
    }

    public static function isCircleScoped(?AdminUser $admin): bool
    {
        if (! $admin || self::isSuper($admin) || self::isIndustryScoped($admin)) {
            return false;
        }

        $roleKeys = self::adminRoleKeys($admin);
        $hasCircleRoleKey = (bool) array_intersect(self::CIRCLE_SCOPED_KEYS, $roleKeys);

        if ($hasCircleRoleKey) {
            return true;
        }

        $user = self::resolveAppUser($admin);

        if (! $user) {
            return false;
        }

        $allowedRoles = array_keys(self::CIRCLE_ROLE_PRIORITY);

        return CircleMember::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereIn(DB::raw('circle_members.role::text'), $allowedRoles)
            ->exists();
    }

    public static function allowedCircleIds(?AdminUser $admin): array
    {
        if (! $admin) {
            return [];
        }

        $cacheKey = 'admin-access:circles:' . $admin->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($admin) {
            if (self::isIndustryScoped($admin)) {
                $industryId = self::assignedIndustryId($admin);

                return $industryId ? app(IndustryScopeService::class)->industryCircleIds($industryId) : [];
            }

            $user = self::resolveAppUser($admin);
            if (! $user) {
                return [];
            }

            return CircleMember::query()
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->pluck('circle_id')
                ->unique()
                ->values()
                ->all();
        });
    }

    public static function allowedUserIds(?AdminUser $admin): array
    {
        if (! $admin) {
            return [];
        }

        $cacheKey = 'admin-access:allowed-users:' . $admin->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($admin) {
            $allowedCircleIds = self::allowedCircleIds($admin);
            if ($allowedCircleIds === []) {
                return [];
            }

            return CircleMember::query()
                ->whereIn('circle_id', $allowedCircleIds)
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->pluck('user_id')
                ->unique()
                ->values()
                ->all();
        });
    }

    public static function primaryCircleRoleKey(?AdminUser $admin): ?string
    {
        if (! $admin) {
            return null;
        }

        $cacheKey = 'admin-access:primary-role:' . $admin->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($admin) {
            $user = self::resolveAppUser($admin);
            if (! $user) {
                return null;
            }

            $allowedCircleIds = self::allowedCircleIds($admin);
            if ($allowedCircleIds === []) {
                return null;
            }

            $roles = array_keys(self::CIRCLE_ROLE_PRIORITY);
            $orderCases = collect(self::CIRCLE_ROLE_PRIORITY)
                ->map(fn ($priority, $role) => "when '{$role}' then {$priority}")
                ->implode(' ');

            return CircleMember::query()
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->whereIn('circle_id', $allowedCircleIds)
                ->whereIn(DB::raw('circle_members.role::text'), $roles)
                ->orderByRaw("case circle_members.role::text {$orderCases} else 999 end")
                ->limit(1)
                ->value(DB::raw('circle_members.role::text'));
        });
    }

    public static function primaryCircleRoleLabel(?AdminUser $admin): string
    {
        $roleKey = self::primaryCircleRoleKey($admin);

        if (! $roleKey) {
            return 'Circle Leader';
        }

        return self::CIRCLE_ROLE_LABELS[$roleKey] ?? 'Circle Leader';
    }

    public static function canEditUsers(?AdminUser $admin): bool
    {
        if (! $admin) {
            return false;
        }

        return in_array('global_admin', self::adminRoleKeys($admin), true);
    }
}
