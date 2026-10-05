<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\AdminModule;
use App\Models\AdminPage;
use App\Models\AdminUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePagePermission;
use App\Models\WorkflowApprovalRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RbacController extends BaseApiController
{
    /**
     * Platform modules catalog with available actions and pages.
     */
    public function modules(Request $request): JsonResponse
    {
        $dbModules = AdminModule::query()
            ->with(['pages' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $defaultActionsMap = [
            'dashboard' => ['view'],
            'members' => ['view', 'create', 'edit', 'delete', 'export', 'upgrade', 'status'],
            'peers' => ['view', 'create', 'edit', 'delete', 'export', 'upgrade', 'status'],
            'circles' => ['view', 'create', 'edit', 'delete', 'approve'],
            'events' => ['view', 'create', 'edit', 'delete', 'checkin', 'export'],
            'campaigns' => ['view', 'create', 'send', 'delete'],
            'coins' => ['view', 'approve', 'reject', 'adjust', 'export'],
            'life-impact' => ['view', 'approve', 'reject'],
            'activities' => ['view', 'create', 'edit', 'delete', 'export', 'approve'],
            'pending-requests' => ['view', 'approve', 'reject'],
            'reports' => ['view', 'export'],
            'rbac' => ['view', 'create', 'edit', 'delete', 'assign'],
            'settings' => ['view', 'edit'],
        ];

        if ($dbModules->isNotEmpty()) {
            $formatted = $dbModules->map(function (AdminModule $module) use ($defaultActionsMap) {
                $slug = (string) ($module->slug ?? Str::slug($module->name));
                $actions = $defaultActionsMap[$slug] ?? ['view', 'create', 'edit', 'delete'];

                return [
                    'id' => (string) $module->id,
                    'key' => $slug,
                    'name' => (string) $module->name,
                    'slug' => $slug,
                    'icon' => (string) ($module->icon ?? 'layers'),
                    'route' => '/admin/'.$slug,
                    'order' => (int) ($module->sort_order ?? 0),
                    'isActive' => (bool) ($module->is_active ?? true),
                    'pageCount' => $module->pages ? $module->pages->count() : 0,
                    'pages' => $module->pages ? $module->pages->map(function ($page) {
                        return [
                            'id' => (string) $page->id,
                            'moduleId' => (string) $page->module_id,
                            'moduleName' => $page->module ? (string) $page->module->name : null,
                            'name' => (string) $page->name,
                            'slug' => (string) ($page->slug ?? Str::slug($page->name)),
                            'route' => (string) ($page->page_url ?? $page->route_name ?? ''),
                            'order' => (int) ($page->sort_order ?? 0),
                            'isActive' => (bool) ($page->is_active ?? true),
                        ];
                    })->values()->all() : [],
                    'actions' => $actions,
                ];
            })->values()->all();
        } else {
            // Standard fallback modules if table is empty
            $fallbackDefinitions = [
                ['id' => 'mod_dashboard', 'key' => 'dashboard', 'slug' => 'dashboard', 'name' => 'Dashboard', 'icon' => 'gauge', 'route' => '/admin/dashboard', 'order' => 1, 'isActive' => true, 'pageCount' => 4, 'actions' => ['view']],
                ['id' => 'mod_peers', 'key' => 'peers', 'slug' => 'peers', 'name' => 'Peers & Users', 'icon' => 'users', 'route' => '/admin/peers', 'order' => 2, 'isActive' => true, 'pageCount' => 5, 'actions' => ['view', 'create', 'edit', 'delete', 'export', 'upgrade', 'status']],
                ['id' => 'mod_circles', 'key' => 'circles', 'slug' => 'circles', 'name' => 'Circles (Chapters)', 'icon' => 'circle-dot', 'route' => '/admin/circles', 'order' => 3, 'isActive' => true, 'pageCount' => 3, 'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
                ['id' => 'mod_events', 'key' => 'events', 'slug' => 'events', 'name' => 'Events & Conclaves', 'icon' => 'calendar', 'route' => '/admin/events', 'order' => 4, 'isActive' => true, 'pageCount' => 4, 'actions' => ['view', 'create', 'edit', 'delete', 'checkin']],
                ['id' => 'mod_campaigns', 'key' => 'campaigns', 'slug' => 'campaigns', 'name' => 'Campaigns & Circulars', 'icon' => 'send', 'route' => '/admin/campaigns', 'order' => 5, 'isActive' => true, 'pageCount' => 3, 'actions' => ['view', 'create', 'send', 'delete']],
                ['id' => 'mod_coins', 'key' => 'coins', 'slug' => 'coins', 'name' => 'Coins Ledger', 'icon' => 'coins', 'route' => '/admin/coins', 'order' => 6, 'isActive' => true, 'pageCount' => 3, 'actions' => ['view', 'approve', 'reject', 'adjust']],
                ['id' => 'mod_impacts', 'key' => 'impacts', 'slug' => 'impacts', 'name' => 'Life Impacts', 'icon' => 'heart-handshake', 'route' => '/admin/impacts', 'order' => 7, 'isActive' => true, 'pageCount' => 2, 'actions' => ['view', 'approve', 'reject']],
                ['id' => 'mod_rbac', 'key' => 'rbac', 'slug' => 'rbac', 'name' => 'Role Based Access Control', 'icon' => 'shield-check', 'route' => '/admin/rbac', 'order' => 8, 'isActive' => true, 'pageCount' => 4, 'actions' => ['view', 'create', 'edit', 'delete', 'assign']],
                ['id' => 'mod_settings', 'key' => 'settings', 'slug' => 'settings', 'name' => 'App Settings & Config', 'icon' => 'settings', 'route' => '/admin/settings', 'order' => 9, 'isActive' => true, 'pageCount' => 4, 'actions' => ['view', 'edit']],
            ];
            $formatted = $fallbackDefinitions;
        }

        return response()->json([
            'success' => true,
            'message' => 'RBAC modules retrieved successfully',
            'data' => $formatted,
            'modules' => $formatted,
        ]);
    }

    /**
     * Available permissions list categorized by module and role capability matrix.
     */
    public function permissions(Request $request): JsonResponse
    {
        $permissions = Permission::query()->orderBy('sort_order')->get();
        $roles = Role::query()->where(function ($q) {
            $q->where('status', 'active')->orWhereNull('status');
        })->orderBy('hierarchy_depth')->get();

        // 1. Module-categorized permission items
        $modules = AdminModule::query()->orderBy('sort_order')->get();
        $categorized = [];

        foreach ($permissions as $perm) {
            $categorized[] = [
                'id' => (string) $perm->id,
                'key' => (string) $perm->key,
                'name' => (string) $perm->name,
                'description' => (string) ($perm->description ?? ''),
                'sortOrder' => (int) ($perm->sort_order ?? 0),
            ];
        }

        // 2. Build role matrix rows for frontend matrix tab
        $matrixRows = [];
        $standardActions = ['view', 'create', 'edit', 'delete', 'approve'];

        // Get all role page permissions
        $rolePagePerms = RolePagePermission::query()
            ->join('permissions', 'role_page_permissions.permission_id', '=', 'permissions.id')
            ->select('role_page_permissions.role_id', 'permissions.key as perm_key')
            ->distinct()
            ->get()
            ->groupBy('role_id');

        foreach ($roles as $role) {
            $rolePermList = [];
            if ($rolePagePerms->has($role->id)) {
                $rolePermList = $rolePagePerms->get($role->id)->pluck('perm_key')->unique()->all();
            }

            // Fallback for global admin or roles with null assignments
            if (in_array($role->key, ['global_admin', 'global_founder'], true)) {
                $rolePermList = array_values(array_unique(array_merge($rolePermList, $standardActions, ['export', 'reject'])));
            } elseif (empty($rolePermList)) {
                if (in_array($role->key, ['chair', 'vice_chair', 'ded', 'industry_director'], true)) {
                    $rolePermList = ['view', 'create', 'edit', 'approve'];
                } elseif (in_array($role->key, ['secretary', 'founder', 'director'], true)) {
                    $rolePermList = ['view', 'create', 'edit'];
                } else {
                    $rolePermList = ['view'];
                }
            }

            $matrixRows[] = [
                'roleId' => (string) $role->id,
                'roleName' => (string) $role->name,
                'roleKey' => (string) ($role->key ?? ''),
                'permissions' => array_values(array_unique($rolePermList)),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'RBAC permissions retrieved successfully',
            'data' => $matrixRows, // matrix rows array so frontend matrix tab is directly populated
            'matrix' => $matrixRows,
            'permissions' => $categorized,
        ]);
    }

    /**
     * Active roles with attached permissions and user counts.
     */
    public function roles(Request $request): JsonResponse
    {
        $roles = Role::query()->where(function ($q) {
            $q->where('status', 'active')->orWhereNull('status');
        })->orderBy('hierarchy_depth')->get();

        // Get user counts
        $roleCounts = AdminUserRole::query()
            ->select('role_id', DB::raw('count(*) as total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id')
            ->all();

        // Get permissions per role
        $rolePerms = RolePagePermission::query()
            ->join('permissions', 'role_page_permissions.permission_id', '=', 'permissions.id')
            ->select('role_page_permissions.role_id', 'permissions.key as perm_key')
            ->distinct()
            ->get()
            ->groupBy('role_id');

        $formatted = $roles->map(function (Role $role) use ($roleCounts, $rolePerms) {
            $perms = $rolePerms->has($role->id)
                ? $rolePerms->get($role->id)->pluck('perm_key')->unique()->all()
                : [];

            if (in_array($role->key, ['global_admin', 'global_founder'], true)) {
                $perms = array_values(array_unique(array_merge($perms, ['view', 'create', 'edit', 'delete', 'approve', 'reject', 'export'])));
            } elseif (empty($perms)) {
                $perms = ['view'];
            }

            return [
                'id' => (string) $role->id,
                'name' => (string) $role->name,
                'slug' => (string) ($role->key ?? Str::slug($role->name)),
                'description' => (string) ($role->description ?? ''),
                'parentId' => null,
                'parentRoleName' => null,
                'isSystem' => in_array($role->key, ['global_admin', 'global_founder', 'member'], true),
                'permissions' => array_values(array_unique($perms)),
                'dataScope' => (string) ($role->scope_rule ?? 'circle'),
                'userCount' => (int) ($roleCounts[$role->id] ?? 0),
                'createdAt' => $role->created_at ? $role->created_at->toIso8601String() : now()->toIso8601String(),
                'updatedAt' => $role->updated_at ? $role->updated_at->toIso8601String() : now()->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'RBAC roles retrieved successfully',
            'data' => $formatted,
            'roles' => $formatted,
        ]);
    }

    /**
     * Role hierarchy tree representation for visual graph and clone actions.
     */
    public function hierarchy(Request $request): JsonResponse
    {
        $roles = Role::query()->where(function ($q) {
            $q->where('status', 'active')->orWhereNull('status');
        })->orderBy('hierarchy_depth')->get();

        $relations = DB::table('role_hierarchies')->get();
        $parentToChildren = [];
        $childToParents = [];

        foreach ($relations as $rel) {
            $parentToChildren[$rel->parent_role_id][] = $rel->child_role_id;
            $childToParents[$rel->child_role_id][] = $rel->parent_role_id;
        }

        $roleCounts = AdminUserRole::query()
            ->select('role_id', DB::raw('count(*) as total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id')
            ->all();

        $nodes = $roles->map(function (Role $role) use ($childToParents, $roleCounts) {
            $parentId = $childToParents[$role->id][0] ?? null;

            return [
                'id' => (string) $role->id,
                'name' => (string) $role->name,
                'slug' => (string) ($role->key ?? Str::slug($role->name)),
                'description' => (string) ($role->description ?? ''),
                'parentId' => $parentId ? (string) $parentId : null,
                'level' => (int) ($role->hierarchy_depth ?? 1),
                'userCount' => (int) ($roleCounts[$role->id] ?? 0),
                'permissionsCount' => (int) RolePagePermission::query()->where('role_id', $role->id)->count(),
                'children' => [],
                'createdAt' => $role->created_at ? $role->created_at->toIso8601String() : now()->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Role hierarchy retrieved successfully',
            'data' => $nodes,
            'hierarchy' => $nodes,
        ]);
    }

    /**
     * Admin pages list.
     */
    public function pages(Request $request): JsonResponse
    {
        $pages = AdminPage::query()->with('module')->orderBy('sort_order')->get();

        $formatted = $pages->map(function (AdminPage $page) {
            return [
                'id' => (string) $page->id,
                'moduleId' => (string) $page->module_id,
                'moduleName' => $page->module ? (string) $page->module->name : null,
                'name' => (string) $page->name,
                'slug' => (string) ($page->slug ?? Str::slug($page->name)),
                'route' => (string) ($page->page_url ?? $page->route_name ?? ''),
                'order' => (int) ($page->sort_order ?? 0),
                'isActive' => (bool) ($page->is_active ?? true),
                'requiredPermission' => 'view',
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Admin pages retrieved successfully',
            'data' => $formatted,
            'pages' => $formatted,
        ]);
    }

    /**
     * Workflow approval rules.
     */
    public function workflows(Request $request): JsonResponse
    {
        $rules = WorkflowApprovalRule::query()->with('requiredRole')->orderBy('step_order')->get();

        $formatted = $rules->map(function ($rule) {
            return [
                'id' => (string) $rule->id,
                'workflowType' => (string) $rule->workflow_type,
                'stepOrder' => (int) $rule->step_order,
                'requiredRoleId' => (string) $rule->required_role_id,
                'roleName' => $rule->requiredRole ? (string) $rule->requiredRole->name : null,
                'autoApproveHours' => (int) ($rule->auto_approve_hours ?? 0),
                'isActive' => (bool) ($rule->is_active ?? true),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Workflow rules retrieved successfully',
            'data' => $formatted,
            'workflows' => $formatted,
        ]);
    }
}
