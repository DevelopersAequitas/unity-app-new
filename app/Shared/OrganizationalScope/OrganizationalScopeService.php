<?php

declare(strict_types=1);

namespace App\Shared\OrganizationalScope;

use App\Leader\Services\LeaderPermissionService;
use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\Role;
use App\Models\User;
use App\Modules\DynamicAccess\Models\RoleScopePolicy;
use App\Modules\DynamicAccess\Models\UserOrganizationalAssignment;
use App\Shared\Authorization\AccessConstants;
use App\Shared\Services\UserRoleResolverService;
use App\Support\AdminAccess;
use App\Support\AdminCircleScope;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrganizationalScopeService
{
    public function __construct(
        private readonly UserRoleResolverService $roleResolver,
        private readonly LeaderPermissionService $leaderPermissionService,
    ) {}

    /**
     * Resolve complete organizational scope for a User or AdminUser.
     *
     * @return array{
     *     user_id: string,
     *     primary_role_key: string,
     *     effective_role_keys: array<int, string>,
     *     effective_scope_type: string,
     *     is_global: bool,
     *     circle_ids: array<int, string>,
     *     district_ids: array<int, string>,
     *     district_names: array<int, string>,
     *     industry_ids: array<int, string>,
     *     industry_names: array<int, string>,
     *     committee_keys: array<int, string>,
     *     assignments: array<int, array<string, mixed>>,
     *     resource_policies: array<string, array<string, mixed>>
     * }
     */
    public function resolveForActor(User|AdminUser|null $actor, ?string $resourceKey = null): array
    {
        if (! $actor) {
            return $this->emptyScope();
        }

        $appUser = $this->resolveAppUser($actor);
        $adminUser = $this->resolveAdminUser($actor);
        $actorId = (string) ($appUser?->id ?? $adminUser?->id ?? '');

        $effectiveRoleKeys = $this->resolveEffectiveRoleKeys($appUser, $adminUser);
        $primaryRoleKey = $effectiveRoleKeys[0] ?? 'member';

        $isGlobal = count(array_intersect($effectiveRoleKeys, ['global_admin', 'super_admin', 'country_director'])) > 0;

        $circleIds = [];
        $districtIds = [];
        $districtNames = [];
        $industryIds = [];
        $industryNames = [];
        $committeeKeys = [];
        $assignments = [];

        // 1. Read explicit database assignments from user_organizational_assignments if table exists
        if ($appUser && Schema::hasTable('user_organizational_assignments')) {
            $uoaRows = UserOrganizationalAssignment::query()
                ->with(['role', 'circle', 'district', 'industry'])
                ->where('user_id', $appUser->id)
                ->where('status', 'active')
                ->where(function ($q): void {
                    $q->whereNull('effective_from')->orWhere('effective_from', '<=', now());
                })
                ->where(function ($q): void {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', now());
                })
                ->orderByDesc('is_primary')
                ->orderByDesc('created_at')
                ->get();

            foreach ($uoaRows as $row) {
                $rKey = AccessConstants::normalizeRoleKey((string) ($row->role?->key ?? 'member'));
                if (! in_array($rKey, $effectiveRoleKeys, true)) {
                    $effectiveRoleKeys[] = $rKey;
                }
                if ($row->circle_id) {
                    $circleIds[] = (string) $row->circle_id;
                }
                if ($row->district_id) {
                    $districtIds[] = (string) $row->district_id;
                }
                if ($row->industry_id) {
                    $industryIds[] = (string) $row->industry_id;
                }
                if ($row->committee_key) {
                    $committeeKeys[] = (string) $row->committee_key;
                }

                $assignments[] = [
                    'id' => (string) $row->id,
                    'source' => 'user_organizational_assignments',
                    'user_id' => (string) $row->user_id,
                    'role_id' => (string) $row->role_id,
                    'role_key' => $rKey,
                    'role_name' => (string) ($row->role?->name ?? Str::headline($rKey)),
                    'scope_type' => (string) $row->scope_type,
                    'circle_id' => $row->circle_id ? (string) $row->circle_id : null,
                    'circle_name' => $row->circle?->name,
                    'district_id' => $row->district_id ? (string) $row->district_id : null,
                    'district_name' => $row->district?->name,
                    'industry_id' => $row->industry_id ? (string) $row->industry_id : null,
                    'industry_name' => $row->industry?->name,
                    'country_code' => $row->country_code ?? 'IN',
                    'committee_key' => $row->committee_key,
                    'title_override' => $row->title_override,
                    'is_primary' => (bool) $row->is_primary,
                    'status' => (string) $row->status,
                    'effective_from' => $row->effective_from?->toIso8601String(),
                    'effective_to' => $row->effective_to?->toIso8601String(),
                ];
            }
        }

        // 2. Read existing circles & circle_members assignments (backward compatibility)
        if ($appUser && Schema::hasTable('circles')) {
            $circleCols = Schema::getColumnListing('circles');
            $circles = Circle::query()->whereNull('deleted_at')->get();

            foreach ($circles as $c) {
                $cId = (string) $c->id;
                $cName = (string) $c->name;
                $cal = is_array($c->calendar) ? $c->calendar : (is_string($c->calendar) ? (json_decode($c->calendar, true) ?: []) : []);

                $matchedRolesForCircle = [];
                if ((string) $c->founder_user_id === $actorId || (in_array('circle_founder_user_id', $circleCols, true) && (string) $c->circle_founder_user_id === $actorId) || (string) data_get($cal, 'leadership.founder_user_id') === $actorId || (string) data_get($cal, 'leadership.circle_founder_user_id') === $actorId) {
                    $matchedRolesForCircle[] = 'circle_founder';
                }
                if ((in_array('director_user_id', $circleCols, true) && (string) $c->director_user_id === $actorId) || (in_array('circle_director_user_id', $circleCols, true) && (string) $c->circle_director_user_id === $actorId) || (string) data_get($cal, 'leadership.director_user_id') === $actorId || (string) data_get($cal, 'leadership.circle_director_user_id') === $actorId) {
                    $matchedRolesForCircle[] = 'circle_director';
                }
                if ((in_array('chair_user_id', $circleCols, true) && (string) $c->chair_user_id === $actorId) || (string) data_get($cal, 'leadership.chair_user_id') === $actorId) {
                    $matchedRolesForCircle[] = 'chair';
                }
                if ((string) data_get($cal, 'leadership.business_growth_committee_chair_user_id') === $actorId) {
                    $matchedRolesForCircle[] = 'chair_business_growth';
                    $committeeKeys[] = 'business_growth';
                }
                if ((string) data_get($cal, 'leadership.membership_growth_committee_chair_user_id') === $actorId) {
                    $matchedRolesForCircle[] = 'chair_membership';
                    $committeeKeys[] = 'membership_growth';
                }
                if ((string) data_get($cal, 'leadership.events_impacts_committee_chair_user_id') === $actorId) {
                    $matchedRolesForCircle[] = 'chair_events_programs';
                    $committeeKeys[] = 'events_impacts';
                }
                if ((in_array('ded_user_id', $circleCols, true) && (string) $c->ded_user_id === $actorId)) {
                    $matchedRolesForCircle[] = 'ded';
                }
                if ((in_array('industry_director_user_id', $circleCols, true) && (string) $c->industry_director_user_id === $actorId)) {
                    $matchedRolesForCircle[] = 'industry_director';
                }

                foreach (array_unique($matchedRolesForCircle) as $mRole) {
                    $circleIds[] = $cId;
                    if (! in_array($mRole, $effectiveRoleKeys, true)) {
                        $effectiveRoleKeys[] = $mRole;
                    }
                    $alreadyRecorded = collect($assignments)->contains(fn ($a) => $a['circle_id'] === $cId && $a['role_key'] === $mRole);
                    if (! $alreadyRecorded) {
                        $assignments[] = [
                            'id' => (string) Str::uuid(),
                            'source' => 'circles',
                            'user_id' => $actorId,
                            'role_id' => null,
                            'role_key' => $mRole,
                            'role_name' => Str::headline($mRole),
                            'scope_type' => AccessConstants::defaultScopeForRole($mRole),
                            'circle_id' => $cId,
                            'circle_name' => $cName,
                            'district_id' => in_array('district_id', $circleCols, true) ? $c->district_id : null,
                            'district_name' => null,
                            'industry_id' => in_array('industry_id', $circleCols, true) ? $c->industry_id : null,
                            'industry_name' => null,
                            'country_code' => 'IN',
                            'committee_key' => null,
                            'title_override' => null,
                            'is_primary' => true,
                            'status' => 'active',
                            'effective_from' => $c->created_at?->toIso8601String(),
                            'effective_to' => null,
                        ];
                    }
                }
            }
        }

        if ($appUser && Schema::hasTable('circle_members')) {
            $cmRows = DB::table('circle_members')
                ->where('user_id', $appUser->id)
                ->whereNull('deleted_at')
                ->where(function ($q): void {
                    $q->whereNull('status')->orWhereIn(DB::raw('LOWER(status::text)'), ['approved', 'active']);
                })
                ->get();

            foreach ($cmRows as $cm) {
                $cId = (string) $cm->circle_id;
                $circleIds[] = $cId;
                $cmRole = AccessConstants::normalizeRoleKey((string) ($cm->role ?? 'member'));
                if ($cmRole !== 'member' && ! in_array($cmRole, $effectiveRoleKeys, true)) {
                    $effectiveRoleKeys[] = $cmRole;
                }
            }
        }

        // 3. Resolve DED district assignments
        if ($adminUser && AdminAccess::isDed($adminUser)) {
            $loc = AdminAccess::assignedDedLocation($adminUser);
            if (! empty($loc['district_id'])) {
                $districtIds[] = (string) $loc['district_id'];
            }
            if (! empty($loc['district_name'])) {
                $districtNames[] = (string) $loc['district_name'];
            }
            $dedCircles = AdminCircleScope::getDedCircleIds($adminUser);
            $circleIds = array_merge($circleIds, $dedCircles);
        }

        if (Schema::hasTable('admin_ded_districts') && $actorId !== '') {
            $dedRows = DB::table('admin_ded_districts')
                ->where('admin_user_id', $actorId)
                ->when(Schema::hasColumn('admin_ded_districts', 'user_id'), fn ($q) => $q->orWhere('user_id', $actorId))
                ->get();
            foreach ($dedRows as $dRow) {
                if (! empty($dRow->district_id)) {
                    $districtIds[] = (string) $dRow->district_id;
                }
                if (! empty($dRow->district_name)) {
                    $districtNames[] = (string) $dRow->district_name;
                }
            }
        }

        // 4. Resolve Industry Director assignments
        if (Schema::hasTable('industry_director_assignments') && $actorId !== '') {
            $idRows = DB::table('industry_director_assignments')
                ->where('is_active', true)
                ->where(function ($q) use ($actorId, $adminUser): void {
                    $q->where('admin_user_id', $actorId);
                    if ($adminUser) {
                        $q->orWhere('admin_user_id', $adminUser->id);
                    }
                    if (Schema::hasColumn('industry_director_assignments', 'user_id')) {
                        $q->orWhere('user_id', $actorId);
                    }
                })
                ->get();

            foreach ($idRows as $iRow) {
                if (! empty($iRow->industry_id)) {
                    $industryIds[] = (string) $iRow->industry_id;
                }
                if (! empty($iRow->industry_name)) {
                    $industryNames[] = (string) $iRow->industry_name;
                }
            }
        }

        // 5. Resolve role_data_scope if table exists
        if (Schema::hasTable('role_data_scope') && $actorId !== '') {
            $rdsRows = DB::table('role_data_scope')
                ->where('admin_user_id', $actorId)
                ->when($adminUser, fn ($q) => $q->orWhere('admin_user_id', $adminUser->id))
                ->get();
            foreach ($rdsRows as $rds) {
                if ($rds->scope_type === 'circle' && $rds->scope_id) {
                    $circleIds[] = (string) $rds->scope_id;
                } elseif ($rds->scope_type === 'district' && $rds->scope_id) {
                    $districtIds[] = (string) $rds->scope_id;
                } elseif ($rds->scope_type === 'industry' && $rds->scope_id) {
                    $industryIds[] = (string) $rds->scope_id;
                } elseif ($rds->scope_type === 'global') {
                    $isGlobal = true;
                }
            }
        }

        // Expand circles from districts and industries when applicable
        if (! empty($districtIds) && Schema::hasTable('circles') && Schema::hasColumn('circles', 'district_id')) {
            $distCircleIds = DB::table('circles')
                ->whereIn('district_id', array_unique($districtIds))
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();
            $circleIds = array_merge($circleIds, $distCircleIds);
        }

        if (! empty($industryIds) && Schema::hasTable('circles')) {
            $indCircleQuery = DB::table('circles')->whereNull('deleted_at');
            $hasIndFilter = false;
            $indCircleQuery->where(function ($q) use ($industryIds, &$hasIndFilter): void {
                if (Schema::hasColumn('circles', 'industry_id')) {
                    $q->orWhereIn('industry_id', $industryIds);
                    $hasIndFilter = true;
                }
                if (Schema::hasColumn('circles', 'industry_tags')) {
                    foreach ($industryIds as $indId) {
                        $q->orWhereRaw('CAST(industry_tags AS TEXT) LIKE ?', ['%'.$indId.'%']);
                        $hasIndFilter = true;
                    }
                }
            });
            if ($hasIndFilter) {
                $indCircles = $indCircleQuery->pluck('id')->map(fn ($id) => (string) $id)->all();
                $circleIds = array_merge($circleIds, $indCircles);
            }
        }

        $circleIds = array_values(array_unique(array_filter($circleIds)));
        $districtIds = array_values(array_unique(array_filter($districtIds)));
        $districtNames = array_values(array_unique(array_filter($districtNames)));
        $industryIds = array_values(array_unique(array_filter($industryIds)));
        $industryNames = array_values(array_unique(array_filter($industryNames)));
        $committeeKeys = array_values(array_unique(array_filter($committeeKeys)));

        $resourcePolicies = $this->resolveResourcePolicies($effectiveRoleKeys);
        $effectiveScopeType = $isGlobal
            ? 'global'
            : ($resourceKey && isset($resourcePolicies[$resourceKey]['scope_type'])
                ? (string) $resourcePolicies[$resourceKey]['scope_type']
                : AccessConstants::defaultScopeForRole($primaryRoleKey));

        return [
            'user_id' => $actorId,
            'primary_role_key' => $primaryRoleKey,
            'effective_role_keys' => $effectiveRoleKeys,
            'effective_scope_type' => $effectiveScopeType,
            'is_global' => $isGlobal,
            'circle_ids' => $circleIds,
            'district_ids' => $districtIds,
            'district_names' => $districtNames,
            'industry_ids' => $industryIds,
            'industry_names' => $industryNames,
            'committee_keys' => $committeeKeys,
            'assignments' => $assignments,
            'resource_policies' => $resourcePolicies,
        ];
    }

    /**
     * Resolve peer user IDs visible to the actor for a given resource.
     * Returns null when the actor has global/country scope (all peers allowed).
     *
     * @return array<int, string>|null
     */
    public function resolveScopedPeerIds(
        User|AdminUser|null $actor,
        string $resourceKey = 'peers',
        ?string $requestedCircleId = null,
        ?string $requestedDistrictId = null,
        ?string $requestedIndustryId = null,
    ): ?array {
        $scope = $this->resolveForActor($actor, $resourceKey);

        if ($scope['is_global'] || in_array($scope['effective_scope_type'], ['global', 'country'], true)) {
            if (! $requestedCircleId && ! $requestedDistrictId && ! $requestedIndustryId) {
                return null;
            }
        }

        if ($scope['effective_scope_type'] === 'none') {
            return [];
        }

        if ($scope['effective_scope_type'] === 'self') {
            return $scope['user_id'] !== '' ? [$scope['user_id']] : [];
        }

        $targetCircleIds = $scope['circle_ids'];
        if ($requestedCircleId && Str::isUuid($requestedCircleId)) {
            if (! $scope['is_global'] && ! in_array($requestedCircleId, $targetCircleIds, true)) {
                return [];
            }
            $targetCircleIds = [$requestedCircleId];
        }

        $peerIds = [];
        if ($scope['user_id'] !== '') {
            $peerIds[] = $scope['user_id'];
        }

        if (! empty($targetCircleIds) && Schema::hasTable('circle_members')) {
            $memberIds = DB::table('circle_members')
                ->whereIn('circle_id', $targetCircleIds)
                ->whereNull('deleted_at')
                ->where(function ($q): void {
                    $q->whereNull('status')->orWhereIn(DB::raw('LOWER(status::text)'), ['approved', 'active']);
                })
                ->pluck('user_id')
                ->map(fn ($id) => (string) $id)
                ->all();
            $peerIds = array_merge($peerIds, $memberIds);

            if (Schema::hasTable('circles')) {
                $founderIds = DB::table('circles')
                    ->whereIn('id', $targetCircleIds)
                    ->whereNull('deleted_at')
                    ->whereNotNull('founder_user_id')
                    ->pluck('founder_user_id')
                    ->map(fn ($id) => (string) $id)
                    ->all();
                $peerIds = array_merge($peerIds, $founderIds);
            }
        }

        return array_values(array_unique(array_filter($peerIds)));
    }

    /**
     * Apply organizational scope filter to a Circles query builder.
     */
    public function applyCircleScope(
        EloquentBuilder|QueryBuilder $query,
        User|AdminUser|null $actor,
        ?string $requestedCircleId = null,
    ): EloquentBuilder|QueryBuilder {
        $scope = $this->resolveForActor($actor, 'circles');

        if ($requestedCircleId && Str::isUuid($requestedCircleId)) {
            if (! $scope['is_global'] && ! in_array($requestedCircleId, $scope['circle_ids'], true)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('circles.id', $requestedCircleId);
        }

        if ($scope['is_global'] || in_array($scope['effective_scope_type'], ['global', 'country'], true)) {
            return $query;
        }

        if (empty($scope['circle_ids'])) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('circles.id', $scope['circle_ids']);
    }

    /**
     * Check whether the actor can access a specific circle UUID.
     */
    public function canAccessCircle(User|AdminUser|null $actor, string $circleId): bool
    {
        if (! Str::isUuid($circleId)) {
            return false;
        }

        $scope = $this->resolveForActor($actor, 'circles');
        if ($scope['is_global'] || in_array($scope['effective_scope_type'], ['global', 'country'], true)) {
            return true;
        }

        return in_array($circleId, $scope['circle_ids'], true);
    }

    /**
     * Check whether the actor can access a specific peer UUID.
     */
    public function canAccessPeer(User|AdminUser|null $actor, string $peerId): bool
    {
        if (! Str::isUuid($peerId)) {
            return false;
        }

        $scope = $this->resolveForActor($actor, 'peers');
        if ($scope['is_global'] || in_array($scope['effective_scope_type'], ['global', 'country'], true)) {
            return true;
        }

        if ($scope['user_id'] === $peerId) {
            return true;
        }

        $scopedPeerIds = $this->resolveScopedPeerIds($actor, 'peers');

        return $scopedPeerIds === null || in_array($peerId, $scopedPeerIds, true);
    }

    /**
     * Resolve effective normalized role keys for an actor.
     *
     * @return array<int, string>
     */
    public function resolveEffectiveRoleKeys(?User $appUser, ?AdminUser $adminUser): array
    {
        $roleKeys = [];

        if ($adminUser) {
            if (! empty($adminUser->role)) {
                $roleKeys[] = AccessConstants::normalizeRoleKey((string) $adminUser->role);
            }
            if (Schema::hasTable('admin_user_roles') && Schema::hasTable('roles')) {
                $dbKeys = DB::table('admin_user_roles')
                    ->join('roles', 'roles.id', '=', 'admin_user_roles.role_id')
                    ->where('admin_user_roles.user_id', $adminUser->id)
                    ->pluck('roles.key')
                    ->all();
                foreach ($dbKeys as $k) {
                    $roleKeys[] = AccessConstants::normalizeRoleKey((string) $k);
                }
            }
        }

        if ($appUser) {
            if (Schema::hasTable('admin_user_roles') && Schema::hasTable('roles')) {
                $dbKeys = DB::table('admin_user_roles')
                    ->join('roles', 'roles.id', '=', 'admin_user_roles.role_id')
                    ->where('admin_user_roles.user_id', $appUser->id)
                    ->when(Schema::hasColumn('admin_user_roles', 'status'), fn ($q) => $q->where('admin_user_roles.status', 'active'))
                    ->pluck('roles.key')
                    ->all();
                foreach ($dbKeys as $k) {
                    $roleKeys[] = AccessConstants::normalizeRoleKey((string) $k);
                }
            }

            if (Schema::hasTable('user_organizational_assignments') && Schema::hasTable('roles')) {
                $uoaKeys = DB::table('user_organizational_assignments')
                    ->join('roles', 'roles.id', '=', 'user_organizational_assignments.role_id')
                    ->where('user_organizational_assignments.user_id', $appUser->id)
                    ->where('user_organizational_assignments.status', 'active')
                    ->whereNull('user_organizational_assignments.deleted_at')
                    ->pluck('roles.key')
                    ->all();
                foreach ($uoaKeys as $k) {
                    $roleKeys[] = AccessConstants::normalizeRoleKey((string) $k);
                }
            }

            $resolved = $this->roleResolver->resolveUserRole($appUser);
            if (! empty($resolved['role'])) {
                $roleKeys[] = AccessConstants::normalizeRoleKey((string) $resolved['role']);
            }
        }

        $roleKeys = array_values(array_unique(array_filter($roleKeys)));

        if (empty($roleKeys)) {
            return ['member'];
        }

        // Sort so non-member roles appear before 'member'
        usort($roleKeys, function (string $a, string $b): int {
            $rankA = AccessConstants::SCOPE_RANK[AccessConstants::defaultScopeForRole($a)] ?? 50;
            $rankB = AccessConstants::SCOPE_RANK[AccessConstants::defaultScopeForRole($b)] ?? 50;

            return $rankA <=> $rankB;
        });

        return $roleKeys;
    }

    /**
     * Resolve effective scope policies per resource across all active roles.
     *
     * @param  array<int, string>  $roleKeys
     * @return array<string, array<string, mixed>>
     */
    public function resolveResourcePolicies(array $roleKeys): array
    {
        $policies = [];

        if (Schema::hasTable('role_scope_policies') && Schema::hasTable('roles') && ! empty($roleKeys)) {
            $roleIds = Role::query()->whereIn('key', $roleKeys)->pluck('id')->all();
            if (! empty($roleIds)) {
                $rows = RoleScopePolicy::query()->whereIn('role_id', $roleIds)->get();
                foreach ($rows as $row) {
                    $resKey = (string) $row->resource_key;
                    $existing = $policies[$resKey] ?? null;
                    $newRank = AccessConstants::SCOPE_RANK[$row->scope_type] ?? 50;
                    $existingRank = $existing ? (AccessConstants::SCOPE_RANK[$existing['scope_type']] ?? 50) : 999;

                    if (! $existing || $newRank < $existingRank) {
                        $policies[$resKey] = [
                            'resource_key' => $resKey,
                            'scope_type' => (string) $row->scope_type,
                            'include_assigned_circles' => (bool) $row->include_assigned_circles,
                            'include_child_circles' => (bool) $row->include_child_circles,
                            'include_same_district' => (bool) $row->include_same_district,
                            'include_same_industry' => (bool) $row->include_same_industry,
                            'include_all_peers_in_scope' => (bool) $row->include_all_peers_in_scope,
                            'allow_cross_circle_read' => (bool) $row->allow_cross_circle_read,
                            'allow_cross_circle_write' => (bool) $row->allow_cross_circle_write,
                            'allow_export' => (bool) $row->allow_export,
                            'custom_filter_config' => $row->custom_filter_config ?? [],
                        ];
                    }
                }
            }
        }

        // Fill defaults for any resource not explicitly stored in DB
        $primaryRole = $roleKeys[0] ?? 'member';
        $defaultScope = AccessConstants::defaultScopeForRole($primaryRole);
        $isBroad = in_array($defaultScope, ['global', 'country', 'district', 'industry', 'multi_circle'], true);

        foreach (array_keys(AccessConstants::RESOURCE_KEYS) as $resKey) {
            if (! isset($policies[$resKey])) {
                $policies[$resKey] = [
                    'resource_key' => $resKey,
                    'scope_type' => $defaultScope,
                    'include_assigned_circles' => true,
                    'include_child_circles' => $isBroad,
                    'include_same_district' => $defaultScope === 'district',
                    'include_same_industry' => $defaultScope === 'industry',
                    'include_all_peers_in_scope' => true,
                    'allow_cross_circle_read' => $isBroad,
                    'allow_cross_circle_write' => in_array($defaultScope, ['global', 'country'], true),
                    'allow_export' => $isBroad,
                    'custom_filter_config' => [],
                ];
            }
        }

        return $policies;
    }

    private function resolveAppUser(User|AdminUser $actor): ?User
    {
        if ($actor instanceof User) {
            return $actor;
        }

        return AdminAccess::resolveAppUser($actor);
    }

    private function resolveAdminUser(User|AdminUser $actor): ?AdminUser
    {
        if ($actor instanceof AdminUser) {
            return $actor;
        }

        if (! Schema::hasTable('admin_users')) {
            return null;
        }

        return AdminUser::query()
            ->where('id', $actor->id)
            ->when(! empty($actor->email), fn ($q) => $q->orWhere('email', $actor->email))
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyScope(): array
    {
        return [
            'user_id' => '',
            'primary_role_key' => 'guest',
            'effective_role_keys' => [],
            'effective_scope_type' => 'none',
            'is_global' => false,
            'circle_ids' => [],
            'district_ids' => [],
            'district_names' => [],
            'industry_ids' => [],
            'industry_names' => [],
            'committee_keys' => [],
            'assignments' => [],
            'resource_policies' => [],
        ];
    }
}

