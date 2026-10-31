<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Services;

use App\Models\Circle;
use App\Models\RoleDataScope;
use App\Models\User;
use App\Modules\DynamicAccess\Models\UserOrganizationalAssignment;
use App\Shared\Authorization\AccessConstants;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class OrganizationalAssignmentSyncService
{
    public function __construct(
        private readonly DynamicAccessConfigService $configService,
    ) {}

    /**
     * Create or update an organizational assignment and synchronize with existing tables
     * (admin_user_roles, circles leadership columns/calendar, circle_members, role_data_scope).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function assign(array $data, ?string $actorId = null): array
    {
        $user = User::query()->findOrFail((string) $data['user_id']);
        $roleIdentifier = (string) ($data['role_id'] ?? $data['role_key'] ?? '');
        $role = $this->configService->resolveRoleOrFail($roleIdentifier);
        $normalizedRoleKey = AccessConstants::normalizeRoleKey((string) $role->key);

        $scopeType = (string) ($data['scope_type'] ?? $role->default_scope_type ?? AccessConstants::defaultScopeForRole($normalizedRoleKey));
        $circleId = ! empty($data['circle_id']) ? (string) $data['circle_id'] : null;
        $districtId = ! empty($data['district_id']) ? (string) $data['district_id'] : null;
        $industryId = ! empty($data['industry_id']) ? (string) $data['industry_id'] : null;
        $committeeKey = ! empty($data['committee_key']) ? (string) $data['committee_key'] : null;

        // Validate scope requirements
        if (in_array($scopeType, ['own_circle', 'multi_circle', 'own_committee'], true) && ! $circleId) {
            throw new RuntimeException("A valid circle_id is required for scope_type '{$scopeType}'.");
        }

        $assignmentRecord = DB::transaction(function () use (
            $user,
            $role,
            $normalizedRoleKey,
            $scopeType,
            $circleId,
            $districtId,
            $industryId,
            $committeeKey,
            $data,
            $actorId
        ): array {
            // 1. Sync to admin_user_roles (ensuring admin_users record exists if FK is present)
            if (Schema::hasTable('admin_users')) {
                $adminUserExists = DB::table('admin_users')->where('id', $user->id)->exists();
                if (! $adminUserExists && $user->email) {
                    $emailExists = DB::table('admin_users')->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])->exists();
                    if (! $emailExists) {
                        $adminUserCols = Schema::getColumnListing('admin_users');
                        $adminRow = [
                            'id' => (string) $user->id,
                            'name' => $user->display_name ?: trim($user->first_name.' '.$user->last_name) ?: 'Leader',
                            'email' => (string) $user->email,
                            'role' => $normalizedRoleKey,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        if (in_array('password', $adminUserCols, true)) {
                            $adminRow['password'] = $user->password_hash ?: bcrypt(Str::random(16));
                        }
                        if (in_array('is_active', $adminUserCols, true)) {
                            $adminRow['is_active'] = true;
                        }
                        DB::table('admin_users')->insert($adminRow);
                    }
                }
            }

            if (Schema::hasTable('admin_user_roles')) {
                $canInsertAdminUserRole = ! Schema::hasTable('admin_users')
                    || DB::table('admin_users')->where('id', $user->id)->exists();

                if ($canInsertAdminUserRole) {
                    $aurCols = Schema::getColumnListing('admin_user_roles');
                    $aurValues = [
                        'permission_type' => 'edit',
                        'updated_at' => now(),
                    ];
                    if (in_array('id', $aurCols, true)) {
                        $aurValues['id'] = (string) Str::uuid();
                    }
                    if (in_array('is_primary', $aurCols, true)) {
                        $aurValues['is_primary'] = (bool) ($data['is_primary'] ?? true);
                    }
                    if (in_array('status', $aurCols, true)) {
                        $aurValues['status'] = (string) ($data['status'] ?? 'active');
                    }
                    if (in_array('effective_from', $aurCols, true)) {
                        $aurValues['effective_from'] = $data['effective_from'] ?? now();
                    }
                    if (in_array('effective_to', $aurCols, true)) {
                        $aurValues['effective_to'] = $data['effective_to'] ?? null;
                    }
                    if (in_array('assigned_by', $aurCols, true)) {
                        $aurValues['assigned_by'] = $actorId;
                    }
                    if (in_array('notes', $aurCols, true)) {
                        $aurValues['notes'] = $data['notes'] ?? null;
                    }

                    $exists = DB::table('admin_user_roles')
                        ->where('user_id', $user->id)
                        ->where('role_id', $role->id)
                        ->exists();

                    if ($exists) {
                        unset($aurValues['id']);
                        DB::table('admin_user_roles')
                            ->where('user_id', $user->id)
                            ->where('role_id', $role->id)
                            ->update($aurValues);
                    } else {
                        $aurValues['user_id'] = $user->id;
                        $aurValues['role_id'] = $role->id;
                        $aurValues['created_at'] = now();
                        DB::table('admin_user_roles')->insert($aurValues);
                    }
                }
            }

            // 2. Sync to user_organizational_assignments if table exists
            $uoaId = (string) Str::uuid();
            if (Schema::hasTable('user_organizational_assignments')) {
                $uoa = UserOrganizationalAssignment::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'role_id' => $role->id,
                        'circle_id' => $circleId,
                        'district_id' => $districtId,
                        'industry_id' => $industryId,
                        'committee_key' => $committeeKey,
                    ],
                    [
                        'scope_type' => $scopeType,
                        'country_code' => $data['country_code'] ?? 'IN',
                        'title_override' => $data['title_override'] ?? null,
                        'is_primary' => (bool) ($data['is_primary'] ?? true),
                        'status' => (string) ($data['status'] ?? 'active'),
                        'effective_from' => $data['effective_from'] ?? now(),
                        'effective_to' => $data['effective_to'] ?? null,
                        'assigned_by' => $actorId,
                        'notes' => $data['notes'] ?? null,
                        'metadata' => $data['metadata'] ?? [],
                    ]
                );
                $uoaId = (string) $uoa->id;
            }

            // 3. Sync to circles & circle_members when circle_id is provided
            if ($circleId && Schema::hasTable('circles')) {
                $this->syncCircleLeadership($circleId, (string) $user->id, $normalizedRoleKey, $committeeKey);
            }

            // 4. Sync to role_data_scope if table exists
            if (Schema::hasTable('role_data_scope')) {
                $rdsScopeType = match ($scopeType) {
                    'global', 'country' => 'global',
                    'district' => 'district',
                    'industry' => 'industry',
                    default => 'circle',
                };
                $rdsScopeId = $circleId ?? $districtId ?? $industryId;
                RoleDataScope::query()->firstOrCreate([
                    'role_id' => $role->id,
                    'admin_user_id' => $user->id,
                    'scope_type' => $rdsScopeType,
                    'scope_id' => $rdsScopeId,
                ]);
            }

            $this->invalidateUserCache((string) $user->id);

            return [
                'id' => $uoaId,
                'user_id' => (string) $user->id,
                'user_name' => $user->display_name ?: trim($user->first_name.' '.$user->last_name),
                'user_email' => $user->email,
                'role_id' => (string) $role->id,
                'role_key' => $normalizedRoleKey,
                'role_name' => (string) $role->name,
                'scope_type' => $scopeType,
                'circle_id' => $circleId,
                'district_id' => $districtId,
                'industry_id' => $industryId,
                'committee_key' => $committeeKey,
                'status' => (string) ($data['status'] ?? 'active'),
            ];
        });

        return $assignmentRecord;
    }

    /**
     * Synchronize circle leadership columns, calendar JSONB, and circle_members safely.
     */
    public function syncCircleLeadership(string $circleId, string $userId, string $normalizedRoleKey, ?string $committeeKey = null): void
    {
        $circle = Circle::query()->where('id', $circleId)->first();
        if (! $circle) {
            return;
        }

        $circleCols = Schema::getColumnListing('circles');
        $calendar = is_array($circle->calendar)
            ? $circle->calendar
            : (is_string($circle->calendar) ? (json_decode($circle->calendar, true) ?: []) : []);

        $updates = [];

        if ($normalizedRoleKey === 'circle_founder') {
            if (in_array('founder_user_id', $circleCols, true)) {
                $updates['founder_user_id'] = $userId;
            }
            if (in_array('circle_founder_user_id', $circleCols, true)) {
                $updates['circle_founder_user_id'] = $userId;
            }
            data_set($calendar, 'leadership.founder_user_id', $userId);
            data_set($calendar, 'leadership.circle_founder_user_id', $userId);
        } elseif ($normalizedRoleKey === 'circle_director') {
            if (in_array('director_user_id', $circleCols, true)) {
                $updates['director_user_id'] = $userId;
            }
            if (in_array('circle_director_user_id', $circleCols, true)) {
                $updates['circle_director_user_id'] = $userId;
            }
            data_set($calendar, 'leadership.director_user_id', $userId);
            data_set($calendar, 'leadership.circle_director_user_id', $userId);
        } elseif ($normalizedRoleKey === 'chair') {
            if (in_array('chair_user_id', $circleCols, true)) {
                $updates['chair_user_id'] = $userId;
            }
            data_set($calendar, 'leadership.chair_user_id', $userId);
        } elseif ($normalizedRoleKey === 'chair_business_growth' || $committeeKey === 'business_growth') {
            data_set($calendar, 'leadership.business_growth_committee_chair_user_id', $userId);
        } elseif ($normalizedRoleKey === 'chair_membership' || $committeeKey === 'membership_growth') {
            data_set($calendar, 'leadership.membership_growth_committee_chair_user_id', $userId);
        } elseif ($normalizedRoleKey === 'chair_events_programs' || $committeeKey === 'events_impacts') {
            data_set($calendar, 'leadership.events_impacts_committee_chair_user_id', $userId);
        } elseif ($normalizedRoleKey === 'vice_chair') {
            if (in_array('vice_chair_user_id', $circleCols, true)) {
                $updates['vice_chair_user_id'] = $userId;
            }
            data_set($calendar, 'leadership.vice_chair_user_id', $userId);
        } elseif ($normalizedRoleKey === 'secretary') {
            if (in_array('secretary_user_id', $circleCols, true)) {
                $updates['secretary_user_id'] = $userId;
            }
            data_set($calendar, 'leadership.secretary_user_id', $userId);
        } elseif ($normalizedRoleKey === 'ded') {
            if (in_array('ded_user_id', $circleCols, true)) {
                $updates['ded_user_id'] = $userId;
            }
        } elseif ($normalizedRoleKey === 'industry_director') {
            if (in_array('industry_director_user_id', $circleCols, true)) {
                $updates['industry_director_user_id'] = $userId;
            }
        }

        if (in_array('calendar', $circleCols, true)) {
            $updates['calendar'] = json_encode($calendar);
        }

        if (! empty($updates)) {
            DB::table('circles')->where('id', $circleId)->update(array_merge($updates, ['updated_at' => now()]));
        }

        // Safely map to circle_member_role_enum values supported by live PostgreSQL
        if (Schema::hasTable('circle_members')) {
            $enumSafeRole = match ($normalizedRoleKey) {
                'circle_founder' => 'founder',
                'circle_director' => 'director',
                'chair' => 'chair',
                'vice_chair' => 'vice_chair',
                'secretary' => 'secretary',
                'chair_business_growth', 'chair_membership', 'chair_events_programs', 'committee_leader' => 'committee_leader',
                default => 'member',
            };

            $existingMember = DB::table('circle_members')
                ->where('circle_id', $circleId)
                ->where('user_id', $userId)
                ->first();

            if ($existingMember) {
                DB::table('circle_members')
                    ->where('id', $existingMember->id)
                    ->update([
                        'role' => $enumSafeRole,
                        'status' => 'approved',
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('circle_members')->insert([
                    'id' => (string) Str::uuid(),
                    'circle_id' => $circleId,
                    'user_id' => $userId,
                    'role' => $enumSafeRole,
                    'status' => 'approved',
                    'substitute_count' => 0,
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function invalidateUserCache(string $userId): void
    {
        Cache::forget('admin-access:allowed-users:'.$userId);
        Cache::forget('admin-access:allowed-circles:'.$userId);
        Cache::forget('admin-access:primary-role:'.$userId);
        Cache::forget('admin-access:assigned-circles:'.$userId);
        Cache::forget('admin-access:user:'.$userId);
        Cache::forget('admin-access:roles:'.$userId);
    }
}
