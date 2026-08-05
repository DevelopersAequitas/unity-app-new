<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\RoleDataScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleDataScopeController extends Controller
{
    private const SCOPE_TYPES = ['circle', 'district', 'industry', 'country', 'global'];

    /**
     * Display data scope assignments for all roles.
     */
    public function index(Request $request): View
    {
        $roles = Role::orderBy('name')->get();

        $selectedRoleId = $request->query('role_id', $roles->first()?->id);
        $selectedRole = $roles->firstWhere('id', $selectedRoleId);

        $scopes = $selectedRole
            ? RoleDataScope::where('role_id', $selectedRole->id)->get()
            : collect();

        return view('admin.rbac.data-scope.index', compact(
            'roles',
            'selectedRole',
            'scopes',
        ))->with('scopeTypes', self::SCOPE_TYPES);
    }

    /**
     * Store a new data scope entry for a role.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
            'scope_type' => ['required', 'string', 'in:'.implode(',', self::SCOPE_TYPES)],
            'scope_value' => ['nullable', 'string', 'max:100'],
        ]);

        RoleDataScope::firstOrCreate(
            [
                'role_id' => $validated['role_id'],
                'scope_type' => $validated['scope_type'],
                'scope_value' => $validated['scope_value'] ?? null,
            ]
        );

        return redirect()->route('admin.rbac.data-scope.index', ['role_id' => $validated['role_id']])
            ->with('success', 'Data scope added.');
    }

    /**
     * Remove a specific data scope entry.
     */
    public function destroy(RoleDataScope $roleDataScope): JsonResponse
    {
        $roleDataScope->delete();

        return response()->json(['message' => 'Data scope removed.']);
    }
}
