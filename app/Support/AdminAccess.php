<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Industry;
use App\Models\IndustryDirectorAssignment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AdminAccess
{
    private const CACHE_TTL = 300;

    private const SUPER_ROLE_KEYS = [
        'global_admin',
        'ded',
    ];

    private const INDUSTRY_SCOPED_KEYS = [
        'industry_director',
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

        return (bool) array_intersect(self::INDUSTRY_SCOPED_KEYS, self::adminRoleKeys($admin));
    }

    public static function allowedIndustryIds(?AdminUser $admin): array
    {
        if (! $admin || ! self::isIndustryScoped($admin)) {
            return [];
        }

        $cacheKey = 'admin-access:industries:' . $admin->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($admin) {
            $ids = collect();

            if (Schema::hasTable('industry_director_assignments')) {
                $ids = $ids->merge(IndustryDirectorAssignment::query()
                    ->where(function ($query) use ($admin): void {
                        $query->where('admin_user_id', $admin->id);

                        $user = self::resolveAppUser($admin);
                        if ($user) {
                            $query->orWhere('user_id', $user->id);
                        }
                    })
                    ->pluck('industry_id'));
            }

            $user = self::resolveAppUser($admin);
            if ($user) {
                if (Schema::hasColumn('circles', 'industry_id')) {
                    $ids = $ids->merge(Circle::query()
                        ->where('industry_director_user_id', $user->id)
                        ->whereNotNull('industry_id')
                        ->pluck('industry_id'));
                }

                if ($ids->isEmpty() && Schema::hasColumn('circles', 'industry_tags')) {
                    $industryTags = Circle::query()
                        ->where('industry_director_user_id', $user->id)
                        ->pluck('industry_tags')
                        ->flatten()
                        ->filter()
                        ->unique()
                        ->values();

                    if ($industryTags->isNotEmpty()) {
                        $uuidTags = $industryTags->filter(fn ($tag) => is_string($tag) && preg_match('/^[0-9a-fA-F-]{36}$/', $tag));
                        $nameTags = $industryTags->diff($uuidTags);

                        $ids = $ids->merge(Industry::query()
                            ->where(function ($query) use ($uuidTags, $nameTags): void {
                                if ($uuidTags->isNotEmpty()) {
                                    $query->whereIn('id', $uuidTags);
                                }

                                if ($nameTags->isNotEmpty()) {
                                    $method = $uuidTags->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                                    $query->{$method}('name', $nameTags);
                                }
                            })
                            ->pluck('id'));
                    }
                }
            }

            return $ids->map(fn ($id) => (string) $id)->unique()->values()->all();
        });
    }

    public static function primaryIndustryName(?AdminUser $admin): ?string
    {
        $industryId = self::allowedIndustryIds($admin)[0] ?? null;

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
                $industryIds = self::allowedIndustryIds($admin);

                if ($industryIds === []) {
                    return [];
                }

                return self::circleIdsForIndustries($industryIds);
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


    private static function circleIdsForIndustries(array $industryIds): array
    {
        $industryIds = array_values(array_filter(array_map('strval', $industryIds)));

        if ($industryIds === []) {
            return [];
        }

        $query = Circle::query();

        if (Schema::hasColumn('circles', 'industry_id')) {
            $query->whereIn('industry_id', $industryIds);
        } elseif (Schema::hasColumn('circles', 'industry_tags')) {
            $industryNames = Industry::query()
                ->whereIn('id', $industryIds)
                ->pluck('name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->values()
                ->all();

            $query->where(function ($tagQuery) use ($industryIds, $industryNames): void {
                foreach ($industryIds as $industryId) {
                    $tagQuery->orWhereJsonContains('industry_tags', $industryId);
                }

                foreach ($industryNames as $industryName) {
                    $tagQuery->orWhereJsonContains('industry_tags', $industryName);
                }
            });
        } else {
            return [];
        }

        return $query->pluck('id')->map(fn ($id) => (string) $id)->unique()->values()->all();
    }

    public static function canEditUsers(?AdminUser $admin): bool
    {
        if (! $admin) {
            return false;
        }

        return in_array('global_admin', self::adminRoleKeys($admin), true);
    }
}
