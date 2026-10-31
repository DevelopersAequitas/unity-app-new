<?php

declare(strict_types=1);

namespace App\Shared\Authorization;

use App\Leader\Services\LeaderPermissionService;
use App\Models\AdminModule;
use App\Models\AdminUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\PermissionService;
use App\Shared\OrganizationalScope\OrganizationalScopeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EffectiveAccessResolverService
{
    public function __construct(
        private readonly OrganizationalScopeService $scopeService,
        private readonly FieldAccessService $fieldAccessService,
        private readonly PermissionService $adminPermissionService,
        private readonly LeaderPermissionService $leaderPermissionService,
    ) {}

    /**
     * Resolve complete effective access profile for a User or AdminUser.
     *
     * @return array<string, mixed>
     */
    public function resolveEffectiveAccess(User|AdminUser|null $actor): array
    {
        $scope = $this->scopeService->resolveForActor($actor);
        $roleKeys = $scope['effective_role_keys'];
        $isGlobal = $scope['is_global'];

        $permissions = $this->resolveGrantedPermissionKeys($roleKeys, $isGlobal);
        $screens = $this->resolveVisibleScreens($roleKeys, $isGlobal);
        $fieldRules = $this->fieldAccessService->getRulesForRoleKeys($roleKeys);
        $reports = $this->resolveAllowedReports($roleKeys, $isGlobal);

        $membershipStatus = ($actor instanceof User) ? (string) ($actor->membership_status ?? 'free_peer') : null;

        return [
            'user_id' => $scope['user_id'],
            'membership_status' => $membershipStatus,
            'primary_role_key' => $scope['primary_role_key'],
            'effective_role_keys' => $roleKeys,
            'effective_scope_type' => $scope['effective_scope_type'],
            'is_global' => $isGlobal,
            'permissions' => $permissions,
            'screens' => $screens,
            'organizational_scope' => [
                'circle_ids' => $scope['circle_ids'],
                'district_ids' => $scope['district_ids'],
                'district_names' => $scope['district_names'],
                'industry_ids' => $scope['industry_ids'],
                'industry_names' => $scope['industry_names'],
                'committee_keys' => $scope['committee_keys'],
                'assignments' => $scope['assignments'],
            ],
            'resource_policies' => $scope['resource_policies'],
            'field_access_rules' => $fieldRules,
            'allowed_reports' => $reports,
        ];
    }

    /**
     * Check whether an actor has a specific permission key.
     */
    public function hasPermission(User|AdminUser|null $actor, string $permissionKey): bool
    {
        if (! $actor) {
            return false;
        }

        $scope = $this->scopeService->resolveForActor($actor);
        if ($scope['is_global']) {
            return true;
        }

        $granted = $this->resolveGrantedPermissionKeys($scope['effective_role_keys'], false);

        return in_array($permissionKey, $granted, true);
    }

    /**
     * @param  array<int, string>  $roleKeys
     * @return array<int, string>
     */
    public function resolveGrantedPermissionKeys(array $roleKeys, bool $isGlobal): array
    {
        if ($isGlobal) {
            if (Schema::hasTable('permissions')) {
                $dbKeys = Permission::query()->pluck('key')->all();
                if (! empty($dbKeys)) {
                    return array_values(array_unique($dbKeys));
                }
            }

            return [
                'view', 'create', 'edit', 'delete', 'export', 'approve_reject', 'assign_role', 'manage_data_scope',
                'peers.view', 'peers.view_details', 'peers.create', 'peers.update', 'peers.export',
                'circles.view', 'circles.view_details', 'circles.create', 'circles.update',
                'activities.view', 'activities.create', 'events.view', 'events.create',
                'coins.view', 'reports.view', 'reports.export',
                'leadership_assignments.view', 'leadership_assignments.assign', 'leadership_assignments.revoke',
                'role_configuration.manage',
            ];
        }

        $granted = [];

        if (Schema::hasTable('role_page_permissions') && Schema::hasTable('permissions') && Schema::hasTable('roles') && ! empty($roleKeys)) {
            $roleIds = Role::query()->whereIn('key', $roleKeys)->pluck('id')->all();
            if (! empty($roleIds)) {
                $dbPerms = DB::table('role_page_permissions')
                    ->join('permissions', 'permissions.id', '=', 'role_page_permissions.permission_id')
                    ->whereIn('role_page_permissions.role_id', $roleIds)
                    ->where('role_page_permissions.is_granted', true)
                    ->pluck('permissions.key')
                    ->all();
                $granted = array_merge($granted, $dbPerms);
            }
        }

        // Also map from LeaderPermissionService capabilities
        foreach ($roleKeys as $rKey) {
            $caps = $this->leaderPermissionService->getEnabledCapabilitiesForRole($rKey);
            foreach ($caps as $cap) {
                $granted[] = (string) $cap;
                if ($cap === 'view_peers') {
                    $granted[] = 'peers.view';
                    $granted[] = 'peers.view_details';
                } elseif ($cap === 'manage_peers') {
                    $granted[] = 'peers.update';
                } elseif ($cap === 'access_teams') {
                    $granted[] = 'circles.view';
                    $granted[] = 'circles.view_details';
                } elseif ($cap === 'request_actions') {
                    $granted[] = 'activities.view';
                    $granted[] = 'activities.create';
                    $granted[] = 'events.view';
                } elseif ($cap === 'view_reports') {
                    $granted[] = 'reports.view';
                } elseif ($cap === 'manage_roles') {
                    $granted[] = 'role_configuration.manage';
                }
            }
        }

        return array_values(array_unique(array_filter($granted)));
    }

    /**
     * Resolve navigation menu and visible screens for the given role keys.
     *
     * @param  array<int, string>  $roleKeys
     * @return array<int, array<string, mixed>>
     */
    public function resolveVisibleScreens(array $roleKeys, bool $isGlobal, ?string $targetApp = null): array
    {
        if (Schema::hasTable('admin_modules') && Schema::hasTable('admin_pages')) {
            $modules = AdminModule::query()->where('is_active', true)->orderBy('sort_order')->with('pages')->get();
            if ($modules->isNotEmpty()) {
                $roleIds = Schema::hasTable('roles') ? Role::query()->whereIn('key', $roleKeys)->pluck('id')->all() : [];
                $grantedPageIds = [];

                if (! $isGlobal && ! empty($roleIds) && Schema::hasTable('role_page_permissions')) {
                    $grantedPageIds = DB::table('role_page_permissions')
                        ->whereIn('role_id', $roleIds)
                        ->where('is_granted', true)
                        ->pluck('page_id')
                        ->unique()
                        ->all();
                }

                $result = [];
                foreach ($modules as $mod) {
                    $pages = [];
                    foreach ($mod->pages as $page) {
                        if (! $page->is_active) {
                            continue;
                        }
                        if ($targetApp && Schema::hasColumn('admin_pages', 'target_app') && $page->target_app !== 'both' && $page->target_app !== $targetApp) {
                            continue;
                        }
                        if (! $isGlobal && ! empty($grantedPageIds) && ! in_array($page->id, $grantedPageIds, true)) {
                            continue;
                        }
                        $pages[] = [
                            'id' => (string) $page->id,
                            'screen_key' => $page->screen_key ?? $page->slug,
                            'name' => (string) $page->name,
                            'route_name' => (string) $page->route_name,
                            'slug' => (string) $page->slug,
                            'page_url' => $page->page_url,
                            'icon' => $page->icon,
                            'sort_order' => (int) $page->sort_order,
                        ];
                    }

                    if (! empty($pages) || $isGlobal) {
                        $result[] = [
                            'module_id' => (string) $mod->id,
                            'module_key' => (string) $mod->slug,
                            'module_name' => (string) $mod->name,
                            'icon' => $mod->icon,
                            'sort_order' => (int) $mod->sort_order,
                            'screens' => $pages,
                        ];
                    }
                }

                return $result;
            }
        }

        // Default navigation structure when admin_modules/admin_pages are not yet populated
        return [
            [
                'module_key' => 'dashboard',
                'module_name' => 'Dashboard',
                'icon' => 'bi-speedometer2',
                'sort_order' => 1,
                'screens' => [
                    ['screen_key' => 'leadership.dashboard', 'name' => 'Leadership Dashboard', 'route_name' => 'leadership.dashboard', 'slug' => 'dashboard'],
                ],
            ],
            [
                'module_key' => 'peers',
                'module_name' => 'Peers',
                'icon' => 'bi-people',
                'sort_order' => 2,
                'screens' => [
                    ['screen_key' => 'leadership.peers', 'name' => 'Peers Directory', 'route_name' => 'leadership.peers.index', 'slug' => 'peers'],
                ],
            ],
            [
                'module_key' => 'circles',
                'module_name' => 'Circles',
                'icon' => 'bi-diagram-3',
                'sort_order' => 3,
                'screens' => [
                    ['screen_key' => 'leadership.circles', 'name' => 'Circles Directory', 'route_name' => 'leadership.circles.index', 'slug' => 'circles'],
                ],
            ],
            [
                'module_key' => 'activities',
                'module_name' => 'Activities',
                'icon' => 'bi-activity',
                'sort_order' => 4,
                'screens' => [
                    ['screen_key' => 'leadership.activities', 'name' => 'Activities Feed', 'route_name' => 'leadership.activities.index', 'slug' => 'activities'],
                ],
            ],
            [
                'module_key' => 'reports',
                'module_name' => 'Reports',
                'icon' => 'bi-bar-chart-line',
                'sort_order' => 5,
                'screens' => [
                    ['screen_key' => 'leadership.reports', 'name' => 'Performance Reports', 'route_name' => 'leadership.reports.index', 'slug' => 'reports'],
                ],
            ],
        ];
    }

    /**
     * Resolve allowed reports for the actor's role keys.
     *
     * @param  array<int, string>  $roleKeys
     * @return array<int, array<string, mixed>>
     */
    public function resolveAllowedReports(array $roleKeys, bool $isGlobal): array
    {
        if (Schema::hasTable('report_definitions')) {
            $defs = DB::table('report_definitions')->where('is_active', true)->orderBy('sort_order')->get();
            if ($defs->isNotEmpty()) {
                if ($isGlobal || ! Schema::hasTable('role_report_permissions')) {
                    return $defs->map(fn ($d) => [
                        'id' => (string) $d->id,
                        'report_key' => (string) $d->report_key,
                        'name' => (string) $d->name,
                        'category' => (string) $d->category,
                        'default_scope_type' => (string) $d->default_scope_type,
                        'can_view' => true,
                        'can_export' => true,
                        'can_schedule' => true,
                    ])->all();
                }

                $roleIds = Schema::hasTable('roles') ? Role::query()->whereIn('key', $roleKeys)->pluck('id')->all() : [];
                $perms = ! empty($roleIds)
                    ? DB::table('role_report_permissions')->whereIn('role_id', $roleIds)->where('can_view', true)->get()->keyBy('report_id')
                    : collect();

                if ($perms->isEmpty()) {
                    // Fallback: allow viewing standard reports within role scope
                    return $defs->map(fn ($d) => [
                        'id' => (string) $d->id,
                        'report_key' => (string) $d->report_key,
                        'name' => (string) $d->name,
                        'category' => (string) $d->category,
                        'default_scope_type' => (string) $d->default_scope_type,
                        'can_view' => true,
                        'can_export' => false,
                        'can_schedule' => false,
                    ])->all();
                }

                $allowed = [];
                foreach ($defs as $d) {
                    if ($perms->has($d->id)) {
                        $p = $perms->get($d->id);
                        $allowed[] = [
                            'id' => (string) $d->id,
                            'report_key' => (string) $d->report_key,
                            'name' => (string) $d->name,
                            'category' => (string) $d->category,
                            'default_scope_type' => (string) ($p->allowed_scope_override ?: $d->default_scope_type),
                            'can_view' => (bool) $p->can_view,
                            'can_export' => (bool) $p->can_export,
                            'can_schedule' => (bool) $p->can_schedule,
                        ];
                    }
                }

                return $allowed;
            }
        }

        return [
            ['report_key' => 'circle_summary_report', 'name' => 'Circle Summary Report', 'category' => 'circles', 'default_scope_type' => 'own_circle', 'can_view' => true, 'can_export' => true],
            ['report_key' => 'peer_growth_report', 'name' => 'Peer Growth & Membership Report', 'category' => 'peers', 'default_scope_type' => 'own_circle', 'can_view' => true, 'can_export' => true],
            ['report_key' => 'activity_performance_report', 'name' => 'Activity Performance Report', 'category' => 'activities', 'default_scope_type' => 'own_circle', 'can_view' => true, 'can_export' => true],
            ['report_key' => 'coin_distribution_report', 'name' => 'Coin Distribution Report', 'category' => 'coins', 'default_scope_type' => 'own_circle', 'can_view' => true, 'can_export' => true],
            ['report_key' => 'event_attendance_report', 'name' => 'Event Attendance Report', 'category' => 'events', 'default_scope_type' => 'own_circle', 'can_view' => true, 'can_export' => true],
            ['report_key' => 'district_performance_report', 'name' => 'District Performance Report', 'category' => 'regional', 'default_scope_type' => 'district', 'can_view' => true, 'can_export' => true],
            ['report_key' => 'industry_performance_report', 'name' => 'Industry Performance Report', 'category' => 'regional', 'default_scope_type' => 'industry', 'can_view' => true, 'can_export' => true],
            ['report_key' => 'leadership_coverage_report', 'name' => 'Leadership Coverage Report', 'category' => 'leadership', 'default_scope_type' => 'multi_circle', 'can_view' => true, 'can_export' => true],
        ];
    }
}
