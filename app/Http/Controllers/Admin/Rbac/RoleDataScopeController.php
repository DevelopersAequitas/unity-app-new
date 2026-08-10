<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\District;
use App\Models\Industry;
use App\Models\Role;
use App\Models\RoleDataScope;
use App\Services\Admin\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RoleDataScopeController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $scopes = RoleDataScope::query()
            ->with(['role'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $roles = Role::query()
            ->where('status', 'active')
            ->whereNotIn('key', ['global_admin', 'global_founder'])
            ->orderBy('name')
            ->get();

        $circles = Circle::query()->orderBy('name')->limit(200)->get(['id', 'name']);
        $industries = Industry::query()->orderBy('name')->get(['id', 'name']);
        $districts = Schema::hasTable('districts')
            ? District::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'scopes' => $scopes,
                'roles' => $roles,
                'circles' => $circles,
                'industries' => $industries,
                'districts' => $districts,
            ]);
        }

        return view('admin.rbac.data-scope.index', compact(
            'scopes',
            'roles',
            'circles',
            'industries',
            'districts',
        ));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|uuid|exists:roles,id',
            'scope_type' => 'required|string|in:global,circle,district,industry',
            'scope_id' => 'nullable|uuid',
        ]);

        $scope = RoleDataScope::query()->create($validated);

        // Invalidate cache
        if (! empty($validated['role_id'])) {
            $this->permissionService->invalidateCacheForRole($validated['role_id']);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data scope created successfully.',
                'scope' => $scope->load(['role']),
            ], 201);
        }

        return redirect()->route('admin.rbac.data-scope.index')
            ->with('success', 'Data scope created successfully.');
    }

    public function destroy(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $scope = RoleDataScope::query()->findOrFail($id);

        if ($scope->role_id) {
            $this->permissionService->invalidateCacheForRole($scope->role_id);
        }

        $scope->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data scope removed successfully.',
            ]);
        }

        return redirect()->route('admin.rbac.data-scope.index')
            ->with('success', 'Data scope removed successfully.');
    }
}
