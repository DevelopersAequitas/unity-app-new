<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\AdminPage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleModuleAccess;
use App\Models\RolePagePermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolePermissionMatrixController extends Controller
{
    /**
     * Display the full role × page × permission matrix.
     * Renders as a checkbox grid: rows = pages (grouped by module), columns = permissions.
     */
    public function index(Request $request): View
    {
        $roles = Role::orderBy('name')->get();
        $permissions = Permission::orderBy('sort_order')->get();

        $selectedRoleId = $request->query('role_id', $roles->first()?->id);
        $selectedRole = $roles->firstWhere('id', $selectedRoleId);

        $visibleModuleIds = null;
        if ($selectedRole) {
            $hasModuleAccess = RoleModuleAccess::where('role_id', $selectedRole->id)->exists();
            if ($hasModuleAccess) {
                $visibleModuleIds = RoleModuleAccess::where('role_id', $selectedRole->id)
                    ->where('is_visible', true)
                    ->pluck('module_id')
                    ->all();
            }
        }

        $pagesQuery = AdminPage::where('is_active', true)->with('module');

        if ($visibleModuleIds !== null) {
            $pagesQuery->where(function ($q) use ($visibleModuleIds): void {
                $q->whereIn('module_id', $visibleModuleIds)
                    ->orWhereNull('module_id');
            });
        }

        $pages = $pagesQuery->orderBy('sort_order')
            ->get()
            ->groupBy(fn (AdminPage $p): string => $p->module?->name ?? 'General');

        // Existing grants for selected role: [page_id][permission_id] = true
        $grants = [];
        if ($selectedRole) {
            RolePagePermission::where('role_id', $selectedRole->id)
                ->get()
                ->each(function (RolePagePermission $rpp) use (&$grants): void {
                    $grants[$rpp->page_id][$rpp->permission_id] = true;
                });
        }

        return view('admin.rbac.matrix.index', compact(
            'roles',
            'permissions',
            'pages',
            'grants',
            'selectedRole',
        ));
    }

    /**
     * Save the matrix for a given role via JSON AJAX call.
     * Expects body: { role_id, grants: [{page_id, permission_id}] }
     */
    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
            'grants' => ['present', 'array'],
            'grants.*.page_id' => ['required', 'uuid', 'exists:admin_pages,id'],
            'grants.*.permission_id' => ['required', 'uuid', 'exists:permissions,id'],
        ]);

        // Delete all existing grants for this role and re-insert
        RolePagePermission::where('role_id', $validated['role_id'])->delete();

        foreach ($validated['grants'] as $grant) {
            RolePagePermission::create([
                'role_id' => $validated['role_id'],
                'page_id' => $grant['page_id'],
                'permission_id' => $grant['permission_id'],
            ]);
        }

        // Flush RBAC cache so changes take effect immediately
        Cache::flush();

        return response()->json(['message' => 'Permissions saved successfully.']);
    }
}
