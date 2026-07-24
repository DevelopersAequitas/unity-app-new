<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Industry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScopeCascadeResolver
{
    public static function resolveDataWindow(string $adminUserId): array
    {
        $admin = AdminUser::find($adminUserId);
        if (! $admin) {
            return [];
        }

        // Cache lookup
        $cache = DB::table('tbl_permission_cache')->where('user_id', $adminUserId)->first();
        if ($cache && ! empty($cache->circle_ids)) {
            $decoded = is_string($cache->circle_ids) ? json_decode($cache->circle_ids, true) : $cache->circle_ids;
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $circleIds = self::computeDataWindow($admin);

        // Update cache
        $version = $cache ? ((int) $cache->version + 1) : 1;

        DB::table('tbl_permission_cache')->updateOrInsert(
            ['user_id' => $adminUserId],
            [
                'circle_ids' => json_encode($circleIds),
                'computed_at' => now(),
                'version' => (string) $version,
                'updated_at' => now(),
            ]
        );

        return $circleIds;
    }

    public static function invalidateCache(string $adminUserId): void
    {
        if (Schema::hasTable('tbl_permission_cache')) {
            DB::table('tbl_permission_cache')->where('user_id', $adminUserId)->delete();
        }
        Cache::forget('admin-access:circles:'.$adminUserId);
        Cache::forget('admin-access:allowed-users:'.$adminUserId);
        Cache::forget('admin-access:roles:'.$adminUserId);
        Cache::forget('admin-access:user:'.$adminUserId);
    }

    public static function invalidateAllCaches(): void
    {
        DB::table('tbl_permission_cache')->truncate();
        Cache::flush();
    }

    private static function computeDataWindow(AdminUser $admin): array
    {
        $rawRoleKeys = AdminAccess::adminRoleKeys($admin);
        $roleKeys = array_map(function ($k) {
            return str_replace(' ', '_', strtolower((string) $k));
        }, $rawRoleKeys);

        // Global Founder or Global Admin gets all active circles
        if (in_array('global_founder', $roleKeys, true) || in_array('global_admin', $roleKeys, true)) {
            return Circle::query()->pluck('id')->all();
        }

        $circles = collect();

        // 1. DED scope rollup
        $isDed = false;
        foreach ($roleKeys as $k) {
            if ($k === 'ded' || str_contains($k, 'ded') || str_contains($k, 'district')) {
                $isDed = true;
                break;
            }
        }

        if ($isDed) {
            $dedCircleIds = AdminCircleScope::getDedCircleIds($admin);
            $circles = $circles->merge($dedCircleIds);
        }

        // 2. ID / IED scope rollup
        $isIdOrIed = false;
        foreach ($roleKeys as $k) {
            if ($k === 'id' || $k === 'ied' || str_contains($k, 'industry')) {
                $isIdOrIed = true;
                break;
            }
        }

        if ($isIdOrIed) {
            $industryAssignments = DB::table('industry_director_assignments')
                ->where('admin_user_id', $admin->id)
                ->where('is_active', true)
                ->pluck('industry_id')
                ->all();

            foreach ($industryAssignments as $industryId) {
                $industry = Industry::find($industryId);
                if ($industry) {
                    $circles = $circles->merge($industry->circles()->pluck('circles.id')->all());
                }
            }
        }

        // 3. Circle-type scopes (CD/CF/Chairs)
        $appUser = AdminAccess::resolveAppUser($admin);
        if ($appUser) {
            $circleMemberships = CircleMember::query()
                ->where('user_id', $appUser->id)
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->pluck('circle_id')
                ->all();

            $circles = $circles->merge($circleMemberships);
        }

        return $circles->unique()->values()->all();
    }
}
