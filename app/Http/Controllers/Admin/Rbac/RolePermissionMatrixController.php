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

        // Get current assignments for selected role
        $currentPermissions = [];
        if ($selectedRole) {
            $assignments = RolePagePermission::query()
                ->where('role_id', $selectedRole->id)
                ->get();

            foreach ($assignments as $assignment) {
                $currentPermissions[$assignment->page_id][$assignment->permission_id] = true;
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
            'permissions' => 'nullable|array',
            'permissions.*.*' => 'boolean',
        ]);

        $roleId = $validated['role_id'];
        $permissionMatrix = $validated['permissions'] ?? [];

        // Delete all existing permissions for this role
        RolePagePermission::query()->where('role_id', $roleId)->delete();

        // Insert new permissions from the checkbox matrix
        foreach ($permissionMatrix as $pageId => $permIds) {
            foreach ($permIds as $permId => $enabled) {
                if ($enabled) {
                    RolePagePermission::query()->create([
                        'role_id' => $roleId,
                        'page_id' => $pageId,
                        'permission_id' => $permId,
                    ]);
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
