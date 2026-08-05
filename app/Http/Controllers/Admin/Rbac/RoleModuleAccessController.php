<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\AdminModule;
use App\Models\Role;
use App\Models\RoleModuleAccess;
use App\Services\Admin\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleModuleAccessController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    public function index(Request $request): View
    {
        $roles = Role::query()
            ->where('status', 'active')
            ->orderBy('hierarchy_depth')
            ->orderBy('name')
            ->get();

        $selectedRoleId = $request->get('role_id', $roles->first()?->id);
        $selectedRole = $roles->firstWhere('id', $selectedRoleId);

        $modules = AdminModule::query()->active()->orderBy('sort_order')->get();

        $currentAccess = [];
        if ($selectedRole) {
            $access = RoleModuleAccess::query()
                ->where('role_id', $selectedRole->id)
                ->get();

            foreach ($access as $row) {
                $currentAccess[$row->module_id] = $row->is_visible;
            }
        }

        return view('admin.rbac.module-access', compact(
            'roles',
            'selectedRole',
            'modules',
            'currentAccess',
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|uuid|exists:roles,id',
            'modules' => 'nullable|array',
            'modules.*' => 'boolean',
        ]);

        $roleId = $validated['role_id'];
        $moduleVisibility = $validated['modules'] ?? [];

        $allModules = AdminModule::query()->pluck('id')->all();

        foreach ($allModules as $moduleId) {
            RoleModuleAccess::query()->updateOrCreate(
                ['role_id' => $roleId, 'module_id' => $moduleId],
                ['is_visible' => isset($moduleVisibility[$moduleId]) && $moduleVisibility[$moduleId]],
            );
        }

        $this->permissionService->invalidateCacheForRole($roleId);

        return redirect()->route('admin.rbac.module-access.index', ['role_id' => $roleId])
            ->with('success', 'Module access updated successfully.');
    }
}
