<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AdminModule;
use App\Models\AdminPage;
use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\Industry;
use App\Models\Permission;
use App\Models\RoleDataScope;
use App\Models\RoleModuleAccess;
use App\Models\RolePagePermission;
use App\Support\AdminAccess;
use App\Support\ScopeCascadeResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    private const CACHE_TTL = 300; // 5 minutes

    // ── Route/Page Access ───────────────────────────────────────

    /**
     * Check if an admin user can access a specific route.
     */
    public function canAccessRoute(AdminUser $admin, string $routeName): bool
    {
        $roleIds = $this->adminRoleIds($admin);

        if ($roleIds === []) {
            return false;
        }

        // Global/super roles get access to everything
        if ($this->hasGlobalScope($admin, $roleIds)) {
            return true;
        }

        // Check if dynamic RBAC data exists for these roles
        if (! $this->hasDynamicRbacData($roleIds)) {
            return true; // Fallback: no dynamic data means no restrictions yet
        }

        // Check CRUD sub-routes mapping to main section permissions
        if (str_contains($routeName, '.edit') || str_contains($routeName, '.update')) {
            $parentIndex = preg_replace('/\.(edit|update)$/', '.index', $routeName);

            return $this->can($admin, $parentIndex, 'edit') || $this->can($admin, $routeName, 'edit');
        }

        if (str_contains($routeName, '.create') || str_contains($routeName, '.store')) {
            $parentIndex = preg_replace('/\.(create|store)$/', '.index', $routeName);

            return $this->can($admin, $parentIndex, 'create') || $this->can($admin, $routeName, 'create');
        }

        if (str_contains($routeName, '.import')) {
            $parentIndex = preg_replace('/\.import.*$/', '.index', $routeName);

            return $this->can($admin, $parentIndex, 'import') || $this->can($admin, $routeName, 'import');
        }

        if (str_contains($routeName, '.export')) {
            $parentIndex = preg_replace('/\.export.*$/', '.index', $routeName);

            return $this->can($admin, $parentIndex, 'export') || $this->can($admin, $routeName, 'export');
        }

        if (str_contains($routeName, '.show')) {
            $parentIndex = preg_replace('/\.show$/', '.index', $routeName);

            return $this->can($admin, $parentIndex, 'view') || $this->can($admin, $routeName, 'view');
        }

        if (str_contains($routeName, '.destroy') || str_contains($routeName, '.delete')) {
            $parentIndex = preg_replace('/\.(destroy|delete)$/', '.index', $routeName);

            return $this->can($admin, $parentIndex, 'delete') || $this->can($admin, $routeName, 'delete');
        }

        // Find the page by route_name
        $page = $this->pageByRoute($routeName);

        if (! $page) {
            return true; // Unknown routes are allowed (not yet registered in admin_pages)
        }

        // Check via role_page_permissions (direct)
        $hasDirectAccess = RolePagePermission::query()
            ->whereIn('role_id', $roleIds)
            ->where('page_id', $page->id)
            ->exists();

        if ($hasDirectAccess) {
            return true;
        }

        // Check via role_page_groups (group-based)
        $accessiblePageIds = $this->accessiblePageIdsViaGroups($roleIds);

        if (in_array($page->id, $accessiblePageIds, true)) {
            return true;
        }

        // If parent module is marked visible and has no page-level restriction defined, allow route access
        $isModuleVisible = RoleModuleAccess::query()
            ->whereIn('role_id', $roleIds)
            ->where('module_id', $page->module_id)
            ->where('is_visible', true)
            ->exists();

        if ($isModuleVisible) {
            $hasModulePagePermissions = RolePagePermission::query()
                ->join('admin_pages', 'admin_pages.id', '=', 'role_page_permissions.page_id')
                ->whereIn('role_page_permissions.role_id', $roleIds)
                ->where('admin_pages.module_id', $page->module_id)
                ->exists();

            if (! $hasModulePagePermissions) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an admin can perform a specific action on a page.
     */
    public function can(AdminUser $admin, string $routeName, string $permissionKey): bool
    {
        $roleIds = $this->adminRoleIds($admin);

        if ($roleIds === []) {
            return false;
        }

        if ($this->hasGlobalScope($admin, $roleIds)) {
            return true;
        }

        if (! $this->hasDynamicRbacData($roleIds)) {
            return $this->legacyPermissionCheck($admin, $permissionKey);
        }

        $page = $this->pageByRoute($routeName);

        if (! $page) {
            $parentIndex = preg_replace('/\.(edit|update|create|store|show|destroy)$/', '.index', $routeName);
            if ($parentIndex !== $routeName) {
                $page = $this->pageByRoute($parentIndex);
            }
        }

        if (! $page) {
            return true;
        }

        $permissionId = Permission::idByKey($permissionKey);

        if (! $permissionId) {
            return false;
        }

        $hasDirect = RolePagePermission::query()
            ->whereIn('role_id', $roleIds)
            ->where('page_id', $page->id)
            ->where('permission_id', $permissionId)
            ->exists();

        if ($hasDirect) {
            return true;
        }

        // Fallback check on parent index page
        $indexRoute = preg_replace('/\.(edit|update|create|store|show|destroy)$/', '.index', $routeName);
        if ($indexRoute !== $routeName) {
            $indexPage = $this->pageByRoute($indexRoute);
            if ($indexPage) {
                return RolePagePermission::query()
                    ->whereIn('role_id', $roleIds)
                    ->where('page_id', $indexPage->id)
                    ->where('permission_id', $permissionId)
                    ->exists();
            }
        }

        return false;
    }

    // ── Sidebar / Modules ───────────────────────────────────────

    /**
     * Get all visible modules for an admin user (for sidebar rendering).
     */
    public function visibleModules(AdminUser $admin): Collection
    {
        $cacheKey = 'perm:modules:'.$admin->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($admin): Collection {
            $roleIds = $this->adminRoleIds($admin);

            if ($roleIds === []) {
                return collect();
            }

            $hasModuleAccess = RoleModuleAccess::query()
                ->whereIn('role_id', $roleIds)
                ->exists();

            if ($this->hasGlobalScope($admin, $roleIds)) {
                if (! $hasModuleAccess) {
                    return AdminModule::query()
                        ->active()
                        ->orderBy('sort_order')
                        ->with(['pages' => fn ($q) => $q->active()->orderBy('sort_order')])
                        ->get();
                }

                $visibleModuleIds = RoleModuleAccess::query()
                    ->whereIn('role_id', $roleIds)
                    ->where('is_visible', true)
                    ->pluck('module_id')
                    ->unique()
                    ->all();

                return AdminModule::query()
                    ->active()
                    ->whereIn('id', $visibleModuleIds)
                    ->orderBy('sort_order')
                    ->with(['pages' => fn ($q) => $q->active()->orderBy('sort_order')])
                    ->get();
            }

            if (! $this->hasDynamicRbacData($roleIds)) {
                return collect(); // Fallback: let legacy sidebar handle it
            }

            $visibleModuleIds = RoleModuleAccess::query()
                ->whereIn('role_id', $roleIds)
                ->where('is_visible', true)
                ->pluck('module_id')
                ->unique()
                ->all();

            if ($visibleModuleIds === []) {
                return collect();
            }

            // Get accessible page IDs (direct + group-based)
            $directPageIds = RolePagePermission::query()
                ->whereIn('role_id', $roleIds)
                ->pluck('page_id')
                ->unique()
                ->all();

            $groupPageIds = $this->accessiblePageIdsViaGroups($roleIds);
            $allAccessiblePageIds = array_unique(array_merge($directPageIds, $groupPageIds));

            $modulesWithPagePermissions = DB::table('role_page_permissions')
                ->join('admin_pages', 'admin_pages.id', '=', 'role_page_permissions.page_id')
                ->whereIn('role_page_permissions.role_id', $roleIds)
                ->pluck('admin_pages.module_id')
                ->unique()
                ->all();

            return AdminModule::query()
                ->active()
                ->whereIn('id', $visibleModuleIds)
                ->orderBy('sort_order')
                ->with(['pages' => function ($q) use ($allAccessiblePageIds, $modulesWithPagePermissions): void {
                    $q->active()
                        ->where(function ($sub) use ($allAccessiblePageIds, $modulesWithPagePermissions): void {
                            if (! empty($allAccessiblePageIds)) {
                                $sub->whereIn('id', $allAccessiblePageIds);
                            }
                            if (! empty($modulesWithPagePermissions)) {
                                $sub->orWhereNotIn('module_id', $modulesWithPagePermissions);
                            } else {
                                $sub->orWhereRaw('1=1');
                            }
                        })
                        ->orderBy('sort_order');
                }])
                ->get();
        });
    }

    /**
     * Build full module and page tree for an admin user with boolean permission flags (is_allowed, view, edit, etc.).
     */
    public function userPermissionTree(AdminUser $admin): array
    {
        $cacheKey = 'perm:tree:'.$admin->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($admin): array {
            $roleIds = $this->adminRoleIds($admin);
            $isGlobal = $this->hasGlobalScope($admin, $roleIds);

            // Visible module IDs
            $visibleModuleIds = [];
            if ($roleIds !== []) {
                $visibleModuleIds = RoleModuleAccess::query()
                    ->whereIn('role_id', $roleIds)
                    ->where('is_visible', true)
                    ->pluck('module_id')
                    ->unique()
                    ->all();
            }

            // Permissions map per page: [page_id => [perm_key => bool]]
            $permissionsMap = [];
            if ($roleIds !== []) {
                $rows = DB::table('role_page_permissions')
                    ->join('permissions', 'permissions.id', '=', 'role_page_permissions.permission_id')
                    ->whereIn('role_page_permissions.role_id', $roleIds)
                    ->select('role_page_permissions.page_id', 'permissions.key as perm_key')
                    ->get();

                foreach ($rows as $row) {
                    $permissionsMap[$row->page_id][$row->perm_key] = true;
                }
            }

            $accessibleGroupPageIds = $roleIds !== [] ? $this->accessiblePageIdsViaGroups($roleIds) : [];
            $allActionKeys = ['view', 'create', 'edit', 'delete', 'approve', 'reject', 'export', 'import', 'print', 'restore'];

            $allModules = AdminModule::query()
                ->active()
                ->orderBy('sort_order')
                ->with(['pages' => fn ($q) => $q->active()->orderBy('sort_order')])
                ->get();

            $result = [];

            foreach ($allModules as $module) {
                $moduleAllowed = $isGlobal || in_array($module->id, $visibleModuleIds, true);

                $pagesList = [];
                foreach ($module->pages as $page) {
                    $hasDirectPerms = isset($permissionsMap[$page->id]);
                    $inGroup = in_array($page->id, $accessibleGroupPageIds, true);

                    $pageAllowed = $isGlobal || $inGroup || ($hasDirectPerms && ($permissionsMap[$page->id]['view'] ?? false));

                    $actions = [];
                    foreach ($allActionKeys as $actionKey) {
                        if ($isGlobal) {
                            $actions[$actionKey] = true;
                        } elseif ($inGroup && $actionKey === 'view') {
                            $actions[$actionKey] = true;
                        } else {
                            $actions[$actionKey] = (bool) ($permissionsMap[$page->id][$actionKey] ?? false);
                        }
                    }

                    $pagesList[] = [
                        'id' => $page->id,
                        'module_id' => $page->module_id,
                        'name' => $page->name,
                        'route_name' => $page->route_name,
                        'slug' => $page->slug,
                        'icon' => $page->icon,
                        'sort_order' => $page->sort_order,
                        'is_active' => (bool) $page->is_active,
                        'is_allowed' => $pageAllowed,
                        'permissions' => $actions,
                    ];
                }

                $result[] = [
                    'id' => $module->id,
                    'name' => $module->name,
                    'slug' => $module->slug,
                    'icon' => $module->icon,
                    'sort_order' => $module->sort_order,
                    'is_active' => (bool) $module->is_active,
                    'is_allowed' => $moduleAllowed,
                    'pages' => $pagesList,
                ];
            }

            return $result;
        });
    }

    /**
     * Resolve the data scope for an admin user.
     */
    public function dataScope(AdminUser $admin): DataScopeResult
    {
        $cacheKey = 'perm:scope:'.$admin->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($admin): DataScopeResult {
            $roleIds = $this->adminRoleIds($admin);

            if ($roleIds === []) {
                return DataScopeResult::circle([]);
            }

            if ($this->hasGlobalScope($admin, $roleIds)) {
                return DataScopeResult::global();
            }

            // Check role_data_scope for user-specific scope first, then role-based
            $scopes = RoleDataScope::query()
                ->where(function ($q) use ($admin, $roleIds): void {
                    $q->where('admin_user_id', $admin->id)
                        ->orWhereIn('role_id', $roleIds);
                })
                ->orderByRaw('CASE WHEN admin_user_id IS NOT NULL THEN 0 ELSE 1 END')
                ->get();

            if ($scopes->isEmpty()) {
                // Fallback to legacy ScopeCascadeResolver
                $circleIds = ScopeCascadeResolver::resolveDataWindow($admin->id);

                return DataScopeResult::circle($circleIds);
            }

            // Use the first matching scope (user-specific takes priority)
            $primaryScope = $scopes->first();

            return match ($primaryScope->scope_type) {
                'global' => DataScopeResult::global(),
                'circle' => DataScopeResult::circle(
                    $scopes->where('scope_type', 'circle')->pluck('scope_id')->filter()->values()->all()
                ),
                'district' => $this->resolveDistrictScope($scopes),
                'industry' => $this->resolveIndustryScope($scopes),
                default => DataScopeResult::circle([]),
            };
        });
    }

    /**
     * Get allowed circle IDs for the admin (backward-compatible replacement for ScopeCascadeResolver).
     */
    public function allowedCircleIds(AdminUser $admin): array
    {
        return $this->dataScope($admin)->circleIds;
    }

    /**
     * Check if admin has global data scope.
     */
    public function isGlobal(AdminUser $admin): bool
    {
        return $this->dataScope($admin)->isGlobal;
    }

    // ── Cache Management ────────────────────────────────────────

    /**
     * Invalidate all permission caches for an admin user.
     */
    public function invalidateCache(string $adminUserId): void
    {
        Cache::forget('perm:roles:'.$adminUserId);
        Cache::forget('perm:modules:'.$adminUserId);
        Cache::forget('perm:tree:'.$adminUserId);
        Cache::forget('perm:scope:'.$adminUserId);
        Cache::forget('perm:global:'.$adminUserId);
    }

    /**
     * Invalidate all permission caches for a role (all users with this role).
     */
    public function invalidateCacheForRole(string $roleId): void
    {
        $userIds = DB::table('admin_user_roles')
            ->where('role_id', $roleId)
            ->pluck('user_id')
            ->all();

        foreach ($userIds as $userId) {
            $this->invalidateCache($userId);
        }
    }

    // ── Private Helpers ─────────────────────────────────────────

    private function adminRoleIds(AdminUser $admin): array
    {
        $cacheKey = 'perm:roles:'.$admin->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($admin): array {
            return DB::table('admin_user_roles')
                ->where('user_id', $admin->id)
                ->pluck('role_id')
                ->all();
        });
    }

    private function hasGlobalScope(AdminUser $admin, array $roleIds): bool
    {
        $cacheKey = 'perm:global:'.$admin->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($roleIds): bool {
            // Check if any role is a known super role
            $superKeys = DB::table('roles')
                ->whereIn('id', $roleIds)
                ->whereIn('key', ['global_admin', 'global_founder'])
                ->exists();

            if ($superKeys) {
                return true;
            }

            // Check role_data_scope for global scope type
            return RoleDataScope::query()
                ->whereIn('role_id', $roleIds)
                ->where('scope_type', 'global')
                ->exists();
        });
    }

    private function hasDynamicRbacData(array $roleIds): bool
    {
        return RoleModuleAccess::query()
            ->whereIn('role_id', $roleIds)
            ->exists();
    }

    private function pageByRoute(string $routeName): ?AdminPage
    {
        return Cache::remember('perm:page:'.$routeName, self::CACHE_TTL, function () use ($routeName): ?AdminPage {
            return AdminPage::query()
                ->where('route_name', $routeName)
                ->where('is_active', true)
                ->first();
        });
    }

    private function accessiblePageIdsViaGroups(array $roleIds): array
    {
        return DB::table('role_page_groups')
            ->join('page_group_items', 'page_group_items.page_group_id', '=', 'role_page_groups.page_group_id')
            ->join('page_groups', 'page_groups.id', '=', 'role_page_groups.page_group_id')
            ->whereIn('role_page_groups.role_id', $roleIds)
            ->where('page_groups.is_active', true)
            ->pluck('page_group_items.page_id')
            ->unique()
            ->all();
    }

    private function resolveDistrictScope(Collection $scopes): DataScopeResult
    {
        $districtScopes = $scopes->where('scope_type', 'district');
        $districtId = $districtScopes->first()?->scope_id;

        if (! $districtId) {
            return DataScopeResult::circle([]);
        }

        // Resolve circles in this district
        $circleIds = Circle::query()
            ->where('district_id', $districtId)
            ->pluck('id')
            ->all();

        // Try to find state from district
        $stateId = DB::table('districts')
            ->where('id', $districtId)
            ->value('state_id');

        return DataScopeResult::district($districtId, $circleIds, $stateId);
    }

    private function resolveIndustryScope(Collection $scopes): DataScopeResult
    {
        $industryIds = $scopes->where('scope_type', 'industry')
            ->pluck('scope_id')
            ->filter()
            ->values()
            ->all();

        if ($industryIds === []) {
            return DataScopeResult::circle([]);
        }

        $circleIds = [];
        foreach ($industryIds as $industryId) {
            $industry = Industry::find($industryId);
            if ($industry) {
                $ids = $industry->circles()->pluck('circles.id')->all();
                $circleIds = array_merge($circleIds, $ids);
            }
        }

        return DataScopeResult::industry($industryIds, array_unique($circleIds));
    }

    /**
     * Fallback to legacy permission check when dynamic RBAC data is not yet populated.
     */
    private function legacyPermissionCheck(AdminUser $admin, string $permissionKey): bool
    {
        return match ($permissionKey) {
            'view' => true,
            'edit' => AdminAccess::isEditAllowed($admin),
            'create' => AdminAccess::isEditAllowed($admin),
            'delete' => AdminAccess::isGlobalAdmin($admin),
            'approve' => AdminAccess::isCircleCommittee($admin) || AdminAccess::isGlobalAdmin($admin),
            'export' => true,
            default => false,
        };
    }
}
