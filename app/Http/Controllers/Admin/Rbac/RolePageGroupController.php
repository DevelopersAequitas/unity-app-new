<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\PageGroup;
use App\Models\Role;
use App\Models\RolePageGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolePageGroupController extends Controller
{
    /**
     * Display page group assignments per role.
     */
    public function index(Request $request): View
    {
        $roles = Role::orderBy('name')->get();
        $groups = PageGroup::where('is_active', true)
            ->withCount('pages')
            ->orderBy('sort_order')
            ->get();

        $selectedRoleId = $request->query('role_id', $roles->first()?->id);
        $selectedRole = $roles->firstWhere('id', $selectedRoleId);

        $assignedGroupIds = $selectedRole
            ? RolePageGroup::where('role_id', $selectedRole->id)->pluck('group_id')->all()
            : [];

        return view('admin.rbac.page-group-access.index', compact(
            'roles',
            'groups',
            'selectedRole',
            'assignedGroupIds',
        ));
    }

    /**
     * Save page group assignments for a role.
     * Expects: { role_id, group_ids: [uuid, ...] }
     */
    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
            'group_ids' => ['present', 'array'],
            'group_ids.*' => ['uuid', 'exists:page_groups,id'],
        ]);

        $roleId = $validated['role_id'];
        $newGroupIds = collect($validated['group_ids']);

        // Delete existing and re-insert
        RolePageGroup::where('role_id', $roleId)->delete();

        foreach ($newGroupIds as $groupId) {
            RolePageGroup::create([
                'role_id' => $roleId,
                'group_id' => $groupId,
            ]);
        }

        return response()->json(['message' => 'Page groups assigned successfully.']);
    }
}
