<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\AdminModule;
use App\Models\AdminPage;
use App\Models\AdminUser;
use App\Models\RoleDataScope;
use App\Models\WorkflowApprovalRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RbacService
{
    private const CACHE_TTL = 300; // 5 minutes

    // -------------------------------------------------------------------------
    // Sidebar / Module Visibility
    // -------------------------------------------------------------------------

    /**
     * Returns all modules visible to the given admin user.
     * Global Admin sees everything.
     *
     * @return Collection<int, AdminModule>
     */
    public function getVisibleModules(AdminUser $user): Collection
    {
        if ($user->isGlobalAdmin()) {
            return AdminModule::where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        }

        $roleIds = $this->cachedRoleIds($user);

        return AdminModule::where('is_active', true)
            ->whereHas('roleModuleAccess', function ($q) use ($roleIds): void {
                $q->whereIn('role_id', $roleIds)->where('is_visible', true);
            })
            ->orderBy('sort_order')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Page Access
    // -------------------------------------------------------------------------

    /**
     * Returns all pages accessible to the given admin user (union of direct
     * role_page_permissions AND pages inside assigned page groups).
     *
     * @return Collection<int, AdminPage>
     */
    public function getAccessiblePages(AdminUser $user): Collection
    {
        if ($user->isGlobalAdmin()) {
            return AdminPage::where('is_active', true)->get();
        }

        $roleIds = $this->cachedRoleIds($user);

        // Pages assigned directly via role_page_permissions
        $directPageIds = \DB::table('role_page_permissions')
            ->whereIn('role_id', $roleIds)
            ->pluck('page_id')
            ->unique();

        // Pages via page groups assigned to roles
        $groupIds = \DB::table('role_page_groups')
            ->whereIn('role_id', $roleIds)
            ->pluck('group_id');

        $groupPageIds = \DB::table('page_group_items')
            ->whereIn('group_id', $groupIds)
            ->pluck('page_id')
            ->unique();

        $allPageIds = $directPageIds->merge($groupPageIds)->unique()->values();

        return AdminPage::whereIn('id', $allPageIds)
            ->where('is_active', true)
            ->get();
    }

    // -------------------------------------------------------------------------
    // Permission Check
    // -------------------------------------------------------------------------

    /**
     * Check whether the admin user has a specific permission on a specific page.
     *
     * @param  string  $routeName  The route_name of the admin_pages record.
     * @param  string  $permissionKey  The key of the permissions record (e.g. 'view', 'edit').
     */
    public function can(AdminUser $user, string $routeName, string $permissionKey): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        $roleIds = $this->cachedRoleIds($user);

        if (empty($roleIds)) {
            return false;
        }

        // Check direct role_page_permissions
        $hasDirectPermission = \DB::table('role_page_permissions as rpp')
            ->join('admin_pages as ap', 'ap.id', '=', 'rpp.page_id')
            ->join('permissions as p', 'p.id', '=', 'rpp.permission_id')
            ->whereIn('rpp.role_id', $roleIds)
            ->where('ap.route_name', $routeName)
            ->where('p.key', $permissionKey)
            ->exists();

        if ($hasDirectPermission) {
            return true;
        }

        // Check via page groups: role → page_group → page_group_items → page
        return \DB::table('role_page_permissions as rpp')
            ->join('page_group_items as pgi', 'pgi.page_id', '=', 'rpp.page_id')
            ->join('role_page_groups as rpg', 'rpg.group_id', '=', 'pgi.group_id')
            ->join('admin_pages as ap', 'ap.id', '=', 'rpp.page_id')
            ->join('permissions as p', 'p.id', '=', 'rpp.permission_id')
            ->whereIn('rpg.role_id', $roleIds)
            ->where('ap.route_name', $routeName)
            ->where('p.key', $permissionKey)
            ->exists();
    }

    // -------------------------------------------------------------------------
    // Data Scope
    // -------------------------------------------------------------------------

    /**
     * Returns allowed scope values for the given scope type.
     *
     * Returns null if the user has global access to that scope type.
     * Returns an empty array if the user has no access at all.
     * Returns an array of UUIDs for restricted access.
     *
     * @return array<string>|null null = unrestricted, [] = no access, [...] = specific IDs
     */
    public function getDataScope(AdminUser $user, string $scopeType): ?array
    {
        if ($user->isGlobalAdmin()) {
            return null; // unrestricted
        }

        $roleIds = $this->cachedRoleIds($user);

        $scopes = RoleDataScope::whereIn('role_id', $roleIds)
            ->where('scope_type', $scopeType)
            ->get();

        if ($scopes->isEmpty()) {
            // Also check for global scope
            $hasGlobal = RoleDataScope::whereIn('role_id', $roleIds)
                ->where('scope_type', 'global')
                ->exists();

            return $hasGlobal ? null : [];
        }

        // If any scope_value is null, it means "all of that type"
        if ($scopes->contains('scope_value', null)) {
            return null;
        }

        return $scopes->pluck('scope_value')->filter()->unique()->values()->all();
    }

    // -------------------------------------------------------------------------
    // Workflow Approvals
    // -------------------------------------------------------------------------

    /**
     * Returns the active approver role for a given module slug and workflow action.
     * Returns null if no rule is configured.
     */
    public function getApproverRole(string $moduleSlug, string $workflowAction): ?string
    {
        $rule = WorkflowApprovalRule::whereHas('module', function ($q) use ($moduleSlug): void {
            $q->where('slug', $moduleSlug);
        })
            ->where('workflow_action', $workflowAction)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with('approverRole:id,key,name')
            ->first();

        return $rule?->approverRole?->key;
    }

    // -------------------------------------------------------------------------
    // Cache Helpers
    // -------------------------------------------------------------------------

    /**
     * Clears all RBAC caches for a specific user (call after role assignment changes).
     */
    public function flushUserCache(AdminUser $user): void
    {
        Cache::forget("rbac.role_ids.{$user->id}");
        // Permission-level cache uses route+permission combos; flush by tag if tags are supported.
        // For drivers without tags, rely on TTL expiry.
    }

    /**
     * @return array<string>
     */
    private function cachedRoleIds(AdminUser $user): array
    {
        return Cache::remember(
            "rbac.role_ids.{$user->id}",
            self::CACHE_TTL,
            fn (): array => $user->roles()->pluck('roles.id')->all()
        );
    }
}
