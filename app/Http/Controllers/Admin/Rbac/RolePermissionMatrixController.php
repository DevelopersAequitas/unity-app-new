<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\AdminModule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleModuleAccess;
use App\Models\RolePagePermission;
use App\Services\Admin\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolePermissionMatrixController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $roles = Role::query()
            ->where('status', 'active')
            ->orderBy('hierarchy_depth')
            ->orderBy('name')
            ->get();

        $selectedRoleId = $request->get('role_id', $roles->first()?->id);
        $selectedRole = $roles->firstWhere('id', $selectedRoleId);

        $modulesQuery = AdminModule::query()
            ->active()
            ->orderBy('sort_order')
            ->with(['pages' => fn ($q) => $q->active()->orderBy('sort_order')]);

        if ($selectedRole) {
            $hasAccessRules = RoleModuleAccess::query()
                ->where('role_id', $selectedRole->id)
                ->exists();

            if ($hasAccessRules) {
                $visibleModuleIds = RoleModuleAccess::query()
                    ->where('role_id', $selectedRole->id)
                    ->where('is_visible', true)
                    ->pluck('module_id')
                    ->all();

                $modulesQuery->whereIn('id', $visibleModuleIds);
            }
        }

        $modules = $modulesQuery->get();

        $permissions = Permission::query()->orderBy('sort_order')->get();

        // Get current page assignments for selected role
        $currentPermissions = [];
        if ($selectedRole) {
            $assignedPageIds = RolePagePermission::query()
                ->where('role_id', $selectedRole->id)
                ->pluck('page_id')
                ->unique()
                ->all();

            foreach ($assignedPageIds as $pageId) {
                $currentPermissions[$pageId] = true;
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'roles' => $roles,
                'selectedRole' => $selectedRole,
                'modules' => $modules,
                'permissions' => $permissions,
                'currentPermissions' => $currentPermissions,
            ]);
        }

        return view('admin.rbac.permission-matrix', compact(
            'roles',
            'selectedRole',
            'modules',
            'permissions',
            'currentPermissions',
        ));
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|uuid|exists:roles,id',
            'pages' => 'nullable|array',
            'permissions' => 'nullable|array',
        ]);

        $roleId = $validated['role_id'];

        // Delete all existing permissions for this role
        RolePagePermission::query()->where('role_id', $roleId)->delete();

        $viewPermission = Permission::query()->where('key', 'view')->first();

        // 1. Single-checkbox page access form submission
        if ($request->has('pages')) {
            $pages = $request->input('pages', []);
            foreach ($pages as $pageId => $enabled) {
                if ((bool) $enabled) {
                    if ($viewPermission) {
                        RolePagePermission::query()->create([
                            'role_id' => $roleId,
                            'page_id' => $pageId,
                            'permission_id' => $viewPermission->id,
                        ]);
                    }
                }
            }
        }
        // 2. Backward compatible 2D array form submission
        elseif ($request->has('permissions')) {
            $permissionMatrix = $request->input('permissions', []);
            foreach ($permissionMatrix as $pageId => $permIds) {
                if (is_array($permIds)) {
                    foreach ($permIds as $permId => $enabled) {
                        if ((bool) $enabled) {
                            RolePagePermission::query()->create([
                                'role_id' => $roleId,
                                'page_id' => $pageId,
                                'permission_id' => $permId,
                            ]);
                        }
                    }
                }
            }
        }

        // Invalidate caches
        $this->permissionService->invalidateCacheForRole($roleId);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Permission matrix updated successfully.',
                'role_id' => $roleId,
            ]);
        }

        return redirect()->route('admin.rbac.permission-matrix.index', ['role_id' => $roleId])
            ->with('success', 'Permission matrix updated successfully.');
    }
}
