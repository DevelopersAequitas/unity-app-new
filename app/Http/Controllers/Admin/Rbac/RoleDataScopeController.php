<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\District;
use App\Models\Industry;
use App\Models\Role;
use App\Models\RoleDataScope;
use App\Services\Admin\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RoleDataScopeController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    public function index(): View
    {
        $scopes = RoleDataScope::query()
            ->with(['role', 'adminUser'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $roles = Role::query()->where('status', 'active')->orderBy('name')->get();
        $adminUsers = AdminUser::query()->orderBy('name')->get();

        $circles = Circle::query()->orderBy('name')->limit(200)->get(['id', 'name']);
        $industries = Industry::query()->orderBy('name')->get(['id', 'name']);
        $districts = Schema::hasTable('districts')
            ? District::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('admin.rbac.data-scope.index', compact(
            'scopes',
            'roles',
            'adminUsers',
            'circles',
            'industries',
            'districts',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => 'nullable|uuid|exists:roles,id',
            'admin_user_id' => 'nullable|uuid|exists:admin_users,id',
            'scope_type' => 'required|string|in:global,circle,district,industry',
            'scope_id' => 'nullable|uuid',
        ]);

        if (empty($validated['role_id']) && empty($validated['admin_user_id'])) {
            return back()->withErrors(['role_id' => 'Either Role or Admin User is required.']);
        }

        RoleDataScope::query()->create($validated);

        // Invalidate cache
        if (! empty($validated['admin_user_id'])) {
            $this->permissionService->invalidateCache($validated['admin_user_id']);
        } elseif (! empty($validated['role_id'])) {
            $this->permissionService->invalidateCacheForRole($validated['role_id']);
        }

        return redirect()->route('admin.rbac.data-scope.index')
            ->with('success', 'Data scope created successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $scope = RoleDataScope::query()->findOrFail($id);

        if ($scope->admin_user_id) {
            $this->permissionService->invalidateCache($scope->admin_user_id);
        } elseif ($scope->role_id) {
            $this->permissionService->invalidateCacheForRole($scope->role_id);
        }

        $scope->delete();

        return redirect()->route('admin.rbac.data-scope.index')
            ->with('success', 'Data scope removed successfully.');
    }
}
