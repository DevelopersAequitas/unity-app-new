<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AdminScopeService
{
    public function roleKeys(User $user): array
    {
        return $user->roles()->pluck('roles.key')->map(fn ($v) => strtolower((string) $v))->unique()->values()->all();
    }

    public function isFullAccess(User $user): bool
    {
        $keys = $this->roleKeys($user);

        return (bool) array_intersect(['global_admin', 'admin', 'ho'], $keys);
    }

    public function visibleDistrictIds(User $user): array
    {
        if ($this->isFullAccess($user)) {
            return DB::table('districts')->pluck('id')->all();
        }

        $keys = $this->roleKeys($user);
        if (! in_array('ded', $keys, true)) {
            return [];
        }

        if (DB::getSchemaBuilder()->hasTable('leader_role_assignments')) {
            return DB::table('leader_role_assignments')
                ->where('user_id', $user->id)
                ->whereIn('role_key', ['ded'])
                ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', true))
                ->pluck('district_id')->filter()->unique()->values()->all();
        }

        return [];
    }

    public function visibleIndustryIds(User $user): array
    {
        if ($this->isFullAccess($user)) {
            return DB::table('industries')->pluck('id')->all();
        }

        $keys = $this->roleKeys($user);
        if (! in_array('industry_director', $keys, true) && ! in_array('id', $keys, true)) {
            return [];
        }

        if (DB::getSchemaBuilder()->hasTable('leader_role_assignments')) {
            return DB::table('leader_role_assignments')
                ->where('user_id', $user->id)
                ->whereIn('role_key', ['industry_director', 'id'])
                ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', true))
                ->pluck('industry_id')->filter()->unique()->values()->all();
        }

        return [];
    }

    public function visibleCircleIds(User $user): array
    {
        if ($this->isFullAccess($user)) {
            return DB::table('circles')->pluck('id')->all();
        }

        $keys = $this->roleKeys($user);
        if (array_intersect(['circle_leader', 'cf', 'cd', 'lt', 'founder', 'director', 'chair', 'vice_chair', 'secretary', 'powerhouse'], $keys)) {
            return DB::table('circle_members')->where('user_id', $user->id)
                ->where(fn ($q) => $q->whereNull('deleted_at'))
                ->pluck('circle_id')->unique()->values()->all();
        }

        if (DB::getSchemaBuilder()->hasTable('leader_role_assignments')) {
            return DB::table('leader_role_assignments')
                ->where('user_id', $user->id)
                ->whereIn('role_key', ['cf', 'cd', 'lt', 'chair', 'vice_chair', 'secretary', 'powerhouse', 'circle_advisor', 'circle_influencer'])
                ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', true))
                ->pluck('circle_id')->filter()->unique()->values()->all();
        }

        return [];
    }

    public function applyCircleScope(Builder $query, User $user, string $column = 'circle_id'): Builder
    {
        if ($this->isFullAccess($user)) {
            return $query;
        }

        $ids = $this->visibleCircleIds($user);
        return $ids === [] ? $query->whereRaw('1=0') : $query->whereIn($column, $ids);
    }

    public function applyIndustryScope(Builder $query, User $user, string $column = 'industry_id'): Builder
    {
        if ($this->isFullAccess($user)) {
            return $query;
        }

        $ids = $this->visibleIndustryIds($user);
        return $ids === [] ? $query->whereRaw('1=0') : $query->whereIn($column, $ids);
    }

    public function applyDistrictScope(Builder $query, User $user, string $column = 'district_id'): Builder
    {
        if ($this->isFullAccess($user)) {
            return $query;
        }

        $ids = $this->visibleDistrictIds($user);
        return $ids === [] ? $query->whereRaw('1=0') : $query->whereIn($column, $ids);
    }

    public function applyUserScope(Builder $query, User $user, string $usersTable = 'users'): Builder
    {
        if ($this->isFullAccess($user)) {
            return $query;
        }

        $circleIds = $this->visibleCircleIds($user);
        $industryIds = $this->visibleIndustryIds($user);
        $districtIds = $this->visibleDistrictIds($user);

        return $query->where(function ($q) use ($circleIds, $industryIds, $districtIds, $usersTable) {
            if ($circleIds !== []) {
                $q->orWhereExists(function ($sq) use ($circleIds, $usersTable) {
                    $sq->selectRaw('1')->from('circle_members as cms')
                        ->whereColumn('cms.user_id', "{$usersTable}.id")
                        ->whereIn('cms.circle_id', $circleIds)
                        ->whereNull('cms.deleted_at');
                });
            }

            if ($industryIds !== [] && DB::getSchemaBuilder()->hasColumn($usersTable, 'industry_id')) {
                $q->orWhereIn("{$usersTable}.industry_id", $industryIds);
            }

            if ($districtIds !== [] && DB::getSchemaBuilder()->hasColumn($usersTable, 'district_id')) {
                $q->orWhereIn("{$usersTable}.district_id", $districtIds);
            }
        });
    }
}
