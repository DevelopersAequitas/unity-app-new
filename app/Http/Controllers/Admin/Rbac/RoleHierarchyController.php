<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\RoleHierarchy;
use App\Services\Admin\AdminAuditService;
use App\Support\AdminAccess;
use App\Support\ScopeCascadeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleHierarchyController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function index(): View
    {
        $roles = Role::query()->where('status', 'active')->orderBy('hierarchy_depth')->get();

        // Build parent-child relationships map
        $relations = DB::table('role_hierarchies')->get();
        $parentToChildren = [];
        $childToParents = [];

        foreach ($relations as $rel) {
            $parentToChildren[$rel->parent_role_id][] = $rel->child_role_id;
            $childToParents[$rel->child_role_id][] = $rel->parent_role_id;
        }

        // Find root nodes (roles that have no parents)
        $roots = [];
        foreach ($roles as $role) {
            if (empty($childToParents[$role->id])) {
                $roots[] = $role;
            }
        }

        // Fetch peers and scope entities for the assignment interface
        $peers = DB::table('admin_users')->orderBy('name')->get();
        $districts = DB::table('districts')->orderBy('name')->get();
        $industries = DB::table('industries')->orderBy('name')->get();
        $circles = DB::table('circles')->orderBy('name')->get();

        return view('admin.rbac.tree', [
            'roles' => $roles,
            'roots' => $roots,
            'parentToChildren' => $parentToChildren,
            'childToParents' => $childToParents,
            'peers' => $peers,
            'districts' => $districts,
            'industries' => $industries,
            'circles' => $circles,
        ]);
    }

    public function fullMap(): View
    {
        $roles = Role::query()->where('status', 'active')->orderBy('hierarchy_depth')->get();

        $relations = DB::table('role_hierarchies')->get();
        $parentToChildren = [];
        $childToParents = [];

        foreach ($relations as $rel) {
            $parentToChildren[$rel->parent_role_id][] = $rel->child_role_id;
            $childToParents[$rel->child_role_id][] = $rel->parent_role_id;
        }

        $roots = [];
        foreach ($roles as $role) {
            if (empty($childToParents[$role->id])) {
                $roots[] = $role;
            }
        }

        return view('admin.rbac.tree_fullmap', [
            'roles' => $roles,
            'roots' => $roots,
            'parentToChildren' => $parentToChildren,
            'childToParents' => $childToParents,
        ]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $this->checkEditPermission();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255', 'unique:roles,key'],
            'description' => ['nullable', 'string'],
            'role_type' => ['required', 'in:system,admin,user'],
            'scope_rule' => ['required', 'in:mandatory,optional,not_applicable'],
            'parent_role_ids' => ['nullable', 'array'],
            'parent_role_ids.*' => ['exists:roles,id'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $roleId = (string) Str::uuid();
            $role = Role::create([
                'id' => $roleId,
                'key' => $validated['key'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'role_type' => $validated['role_type'],
                'scope_rule' => $validated['scope_rule'],
                'status' => 'active',
                'is_assignable' => true,
                'role_code' => $validated['key'],
                'hierarchy_depth' => 0,
            ]);

            if (! empty($validated['parent_role_ids'])) {
                foreach ($validated['parent_role_ids'] as $parentId) {
                    RoleHierarchy::create([
                        'parent_role_id' => $parentId,
                        'child_role_id' => $roleId,
                    ]);
                }
            }

            // Recompute depth of this role and descendants
            $role->recomputeDepth();

            $admin = auth('admin')->user();
            if ($admin) {
                $this->audit->log(
                    $admin,
                    'admin.rbac.role.create',
                    'roles',
                    $roleId,
                    [],
                    $role->toArray(),
                    $request
                );
            }
        });

        return redirect()->route('admin.rbac.hierarchy')->with('success', 'Role created successfully.');
    }

    public function updateParent(Request $request): JsonResponse
    {
        $this->checkEditPermission();

        $validated = $request->validate([
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
            'parent_role_ids' => ['nullable', 'array'],
            'parent_role_ids.*' => ['exists:roles,id'],
        ]);

        $roleId = $validated['role_id'];
        $parentIds = $validated['parent_role_ids'] ?? [];

        // Check for cycle dependencies
        $role = Role::findOrFail($roleId);
        $descendants = $role->allDescendantIds();

        foreach ($parentIds as $parentId) {
            if ($parentId === $roleId || in_array($parentId, $descendants, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Relocation failed: parent cannot be itself or a descendant node.',
                ], 422);
            }
        }

        DB::transaction(function () use ($role, $roleId, $parentIds, $request) {
            // Delete old relationships
            DB::table('role_hierarchies')->where('child_role_id', $roleId)->delete();

            // Insert new relationships
            foreach ($parentIds as $parentId) {
                RoleHierarchy::create([
                    'parent_role_id' => $parentId,
                    'child_role_id' => $roleId,
                ]);
            }

            // Recompute depths recursively
            $role->recomputeDepth();

            $admin = auth('admin')->user();
            if ($admin) {
                $this->audit->log(
                    $admin,
                    'admin.rbac.role.relocate',
                    'roles',
                    $roleId,
                    [],
                    ['parent_role_ids' => $parentIds],
                    $request
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Role hierarchy updated successfully.',
        ]);
    }

    public function cloneProfile(Request $request): RedirectResponse
    {
        $this->checkEditPermission();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'clone_from' => ['required', 'uuid', 'exists:roles,id'],
            'parent_role_ids' => ['required', 'array'],
            'parent_role_ids.*' => ['exists:roles,id'],
            'description' => ['nullable', 'string'],
        ]);

        $cloneFromRole = Role::findOrFail($validated['clone_from']);
        $newKey = Str::slug($validated['name'], '_');

        // Check unique key
        if (Role::query()->where('key', $newKey)->exists()) {
            $newKey = $newKey.'_'.time();
        }

        $newRoleId = (string) Str::uuid();

        DB::transaction(function () use ($validated, $cloneFromRole, $newKey, $newRoleId, $request) {
            // Create role
            $role = Role::create([
                'id' => $newRoleId,
                'key' => $newKey,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'role_type' => $cloneFromRole->role_type ?: 'admin',
                'scope_rule' => $cloneFromRole->scope_rule ?: 'optional',
                'status' => 'active',
                'is_assignable' => true,
                'role_code' => $newKey,
                'hierarchy_depth' => 0,
            ]);

            // Add relationships
            foreach ($validated['parent_role_ids'] as $parentId) {
                RoleHierarchy::create([
                    'parent_role_id' => $parentId,
                    'child_role_id' => $newRoleId,
                ]);
            }

            // Copy permissions from cloned role
            $permissions = DB::table('rbac_role_permission_groups')
                ->where('role_id', $cloneFromRole->id)
                ->get();

            foreach ($permissions as $perm) {
                DB::table('rbac_role_permission_groups')->insert([
                    'id' => (string) Str::uuid(),
                    'role_id' => $newRoleId,
                    'permission_group_id' => $perm->permission_group_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Recompute hierarchy depth
            $role->recomputeDepth();

            $admin = auth('admin')->user();
            if ($admin) {
                $this->audit->log(
                    $admin,
                    'admin.rbac.role.clone',
                    'roles',
                    $newRoleId,
                    ['clone_source_id' => $cloneFromRole->id],
                    $role->toArray(),
                    $request
                );
            }
        });

        return redirect()->route('admin.rbac.hierarchy')->with('success', 'Profile cloned successfully.');
    }

    public function updateRole(Request $request, string $id): RedirectResponse
    {
        $this->checkEditPermission();

        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255', 'unique:roles,key,'.$id],
            'description' => ['nullable', 'string'],
            'role_type' => ['required', 'in:system,admin,user'],
            'scope_rule' => ['required', 'in:mandatory,optional,not_applicable'],
            'parent_role_ids' => ['nullable', 'array'],
            'parent_role_ids.*' => ['exists:roles,id'],
        ]);

        DB::transaction(function () use ($role, $validated, $request) {
            $old = $role->toArray();

            $role->update([
                'name' => $validated['name'],
                'key' => $validated['key'],
                'role_code' => $validated['key'],
                'description' => $validated['description'] ?? null,
                'role_type' => $validated['role_type'],
                'scope_rule' => $validated['scope_rule'],
            ]);

            // Update parent relationships if provided
            if (array_key_exists('parent_role_ids', $validated)) {
                $parentIds = $validated['parent_role_ids'] ?? [];

                // Anti-cycle check
                $descendants = $role->allDescendantIds();
                foreach ($parentIds as $parentId) {
                    if ($parentId === $role->id || in_array($parentId, $descendants, true)) {
                        return; // skip bad parent silently — validation layer should catch this
                    }
                }

                DB::table('role_hierarchies')->where('child_role_id', $role->id)->delete();
                foreach ($parentIds as $parentId) {
                    RoleHierarchy::create([
                        'parent_role_id' => $parentId,
                        'child_role_id' => $role->id,
                    ]);
                }

                $role->recomputeDepth();
            }

            $admin = auth('admin')->user();
            if ($admin) {
                $this->audit->log(
                    $admin,
                    'admin.rbac.role.update',
                    'roles',
                    $role->id,
                    $old,
                    $role->fresh()->toArray(),
                    $request
                );
            }
        });

        return redirect()->route('admin.rbac.hierarchy')->with('success', 'Role "'.$role->name.'" updated successfully.');
    }

    public function deleteRole(Request $request, string $id): RedirectResponse
    {
        $this->checkEditPermission();

        $role = Role::findOrFail($id);

        DB::transaction(function () use ($role, $request) {
            $old = $role->toArray();

            // Remove all hierarchy references
            DB::table('role_hierarchies')
                ->where('child_role_id', $role->id)
                ->orWhere('parent_role_id', $role->id)
                ->delete();

            $role->delete();

            $admin = auth('admin')->user();
            if ($admin) {
                $this->audit->log(
                    $admin,
                    'admin.rbac.role.delete',
                    'roles',
                    $role->id,
                    $old,
                    [],
                    $request
                );
            }
        });

        return redirect()->route('admin.rbac.hierarchy')->with('success', 'Role deleted successfully.');
    }

    public function assignRole(Request $request): RedirectResponse
    {
        $this->checkEditPermission();

        $validated = $request->validate([
            'admin_user_id' => ['required', 'uuid', 'exists:admin_users,id'],
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
            'scope_id' => ['nullable', 'string'],
            'allowed_sections' => ['nullable', 'array'],
            'allowed_sections.*' => ['string'],
            'permission_type' => ['nullable', 'string', 'in:edit,view'],
        ]);

        $role = Role::findOrFail($validated['role_id']);
        $this->performAssignment($validated['admin_user_id'], $role, $validated['scope_id'] ?? null, $request);

        return redirect()->route('admin.rbac.hierarchy')->with('success', 'Role assigned to peer successfully.');
    }

    public function getAssignments(string $id): JsonResponse
    {
        $role = Role::findOrFail($id);
        $roleKey = str_replace(' ', '_', strtolower($role->key));

        $assignments = DB::table('admin_user_roles')
            ->where('role_id', $role->id)
            ->join('admin_users', 'admin_user_roles.user_id', '=', 'admin_users.id')
            ->select(
                'admin_user_roles.id as assignment_id',
                'admin_users.id as user_id',
                'admin_users.name',
                'admin_users.email',
                'admin_user_roles.allowed_sections',
                'admin_user_roles.permission_type'
            )
            ->get()
            ->map(function ($assign) use ($roleKey) {
                $scopeId = null;
                $scopeName = 'Global';

                $isDed = $roleKey === 'ded' || str_contains($roleKey, 'ded') || str_contains($roleKey, 'district');
                $isId = $roleKey === 'id' || $roleKey === 'ied' || str_contains($roleKey, 'industry');
                $isCircle = in_array($roleKey, ['cd', 'cf', 'chair', 'vice_chair', 'secretary', 'circle_leader'], true) ||
                    str_contains($roleKey, 'circle') ||
                    str_contains($roleKey, 'leader') ||
                    str_contains($roleKey, 'founder') ||
                    str_contains($roleKey, 'chair') ||
                    str_contains($roleKey, 'secretary');

                if ($isDed) {
                    $scope = DB::table('admin_ded_districts')
                        ->where('admin_user_id', $assign->user_id)
                        ->first();
                    if ($scope) {
                        $scopeId = $scope->district_id;
                        $scopeName = 'District: '.$scope->district_name;
                    }
                } elseif ($isId) {
                    $scope = DB::table('industry_director_assignments')
                        ->where('admin_user_id', $assign->user_id)
                        ->where('is_active', true)
                        ->first();
                    if ($scope) {
                        $scopeId = $scope->industry_id;
                        $scopeName = 'Industry: '.$scope->industry_name;
                    }
                } elseif ($isCircle) {
                    $appUser = DB::table('users')->whereRaw('LOWER(email) = ?', [strtolower($assign->email)])->first();
                    if ($appUser) {
                        $colName = 'circle_director_user_id';
                        $dbRole = 'director';

                        if (str_contains($roleKey, 'founder') || str_contains($roleKey, 'cf')) {
                            $colName = 'circle_founder_user_id';
                            $dbRole = 'founder';
                        } elseif (str_contains($roleKey, 'vice_chair') || str_contains($roleKey, 'vice')) {
                            $colName = 'vice_chair_user_id';
                            $dbRole = 'vice_chair';
                        } elseif (str_contains($roleKey, 'chair')) {
                            $colName = 'chair_user_id';
                            $dbRole = 'chair';
                        } elseif (str_contains($roleKey, 'secretary')) {
                            $colName = 'secretary_user_id';
                            $dbRole = 'secretary';
                        }

                        $circle = DB::table('circles')
                            ->where($colName, $appUser->id)
                            ->first();

                        if (! $circle) {
                            $circle = DB::table('circles')
                                ->join('circle_members', 'circles.id', '=', 'circle_members.circle_id')
                                ->where('circle_members.user_id', $appUser->id)
                                ->where('circle_members.role', $dbRole)
                                ->whereNull('circle_members.deleted_at')
                                ->select('circles.*')
                                ->first();
                        }

                        if ($circle) {
                            $scopeId = $circle->id;
                            $scopeName = 'Circle: '.$circle->name;
                        }
                    }
                }

                return [
                    'user_id' => $assign->user_id,
                    'name' => $assign->name,
                    'email' => $assign->email,
                    'scope_id' => $scopeId,
                    'scope_name' => $scopeName,
                    'allowed_sections' => json_decode((string) $assign->allowed_sections, true) ?: [],
                    'permission_type' => $assign->permission_type ?: 'edit',
                ];
            });

        $assignedUserIds = $assignments->pluck('user_id')->all();
        $query = DB::table('admin_users');
        if ($role->scope_rule === 'none' && ! empty($assignedUserIds)) {
            $query->whereNotIn('id', $assignedUserIds);
        }
        $availablePeers = $query->orderBy('name')->get(['id', 'name', 'email']);

        return response()->json([
            'success' => true,
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'key' => $role->key,
                'scope_rule' => $role->scope_rule,
            ],
            'assignments' => $assignments,
            'available_peers' => $availablePeers,
        ]);
    }

    public function assignPeer(Request $request, string $id): JsonResponse
    {
        $this->checkEditPermission();

        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'admin_user_id' => ['required', 'uuid', 'exists:admin_users,id'],
            'scope_id' => ['nullable', 'string'],
            'allowed_sections' => ['nullable', 'array'],
            'allowed_sections.*' => ['string'],
            'permission_type' => ['nullable', 'string', 'in:edit,view'],
        ]);

        $this->performAssignment($validated['admin_user_id'], $role, $validated['scope_id'] ?? null, $request);

        return response()->json([
            'success' => true,
            'message' => 'Role assigned successfully.',
        ]);
    }

    public function removeAssignment(Request $request, string $id, string $userId): JsonResponse
    {
        $this->checkEditPermission();

        $role = Role::findOrFail($id);
        $roleKey = strtolower($role->key);
        $adminUser = DB::table('admin_users')->where('id', $userId)->first();

        if (! $adminUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        DB::transaction(function () use ($role, $roleKey, $userId, $adminUser, $request) {
            DB::table('admin_user_roles')
                ->where('user_id', $userId)
                ->where('role_id', $role->id)
                ->delete();

            $isDed = $roleKey === 'ded' || str_contains($roleKey, 'ded') || str_contains($roleKey, 'district');
            $isId = $roleKey === 'id' || $roleKey === 'ied' || str_contains($roleKey, 'industry');
            $isCircle = in_array($roleKey, ['cd', 'cf', 'chair', 'vice_chair', 'secretary', 'circle_leader'], true) ||
                str_contains($roleKey, 'circle') ||
                str_contains($roleKey, 'leader') ||
                str_contains($roleKey, 'founder') ||
                str_contains($roleKey, 'chair') ||
                str_contains($roleKey, 'secretary');

            if ($isDed) {
                DB::table('admin_ded_districts')->where('admin_user_id', $userId)->delete();
            } elseif ($isId) {
                DB::table('industry_director_assignments')->where('admin_user_id', $userId)->delete();
            } elseif ($isCircle) {
                $appUser = DB::table('users')->whereRaw('LOWER(email) = ?', [strtolower($adminUser->email)])->first();
                if ($appUser) {
                    $colName = 'circle_director_user_id';
                    $dbRole = 'director';

                    if (str_contains($roleKey, 'founder') || str_contains($roleKey, 'cf')) {
                        $colName = 'circle_founder_user_id';
                        $dbRole = 'founder';
                    } elseif (str_contains($roleKey, 'vice_chair') || str_contains($roleKey, 'vice')) {
                        $colName = 'vice_chair_user_id';
                        $dbRole = 'vice_chair';
                    } elseif (str_contains($roleKey, 'chair')) {
                        $colName = 'chair_user_id';
                        $dbRole = 'chair';
                    } elseif (str_contains($roleKey, 'secretary')) {
                        $colName = 'secretary_user_id';
                        $dbRole = 'secretary';
                    }

                    if ($colName) {
                        DB::table('circles')->where($colName, $appUser->id)->update([
                            $colName => null,
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('circle_members')
                        ->where('user_id', $appUser->id)
                        ->where('role', $dbRole)
                        ->delete();

                    DB::table('tbl_permission_cache')->where('user_id', $appUser->id)->delete();
                }
            }

            $admin = auth('admin')->user();
            if ($admin) {
                $this->audit->log(
                    $admin,
                    'admin.rbac.role.remove_assignment',
                    'admin_user_roles',
                    $userId,
                    ['role_key' => $role->key],
                    [],
                    $request
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Role assignment removed successfully.',
        ]);
    }

    private function performAssignment(string $adminUserId, Role $role, ?string $scopeId, Request $request): void
    {
        $adminUser = DB::table('admin_users')->where('id', $adminUserId)->first();
        if (! $adminUser) {
            return;
        }

        DB::transaction(function () use ($adminUserId, $role, $scopeId, $adminUser, $request) {
            $existingUserRole = DB::table('admin_user_roles')
                ->where('user_id', $adminUserId)
                ->where('role_id', $role->id)
                ->first();

            $allowedSections = $request->has('allowed_sections') ? json_encode($request->input('allowed_sections') ?? []) : null;
            $permissionType = $request->input('permission_type', 'edit');

            if (! $existingUserRole) {
                DB::table('admin_user_roles')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $adminUserId,
                    'role_id' => $role->id,
                    'allowed_sections' => $allowedSections,
                    'permission_type' => $permissionType,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('admin_user_roles')
                    ->where('id', $existingUserRole->id)
                    ->update([
                        'allowed_sections' => $allowedSections,
                        'permission_type' => $permissionType,
                        'updated_at' => now(),
                    ]);
            }

            $roleKey = str_replace(' ', '_', strtolower($role->key));

            $isDed = $roleKey === 'ded' || str_contains($roleKey, 'ded') || str_contains($roleKey, 'district');
            $isId = $roleKey === 'id' || $roleKey === 'ied' || str_contains($roleKey, 'industry');
            $isCircle = in_array($roleKey, ['cd', 'cf', 'chair', 'vice_chair', 'secretary', 'circle_leader'], true) ||
                str_contains($roleKey, 'circle') ||
                str_contains($roleKey, 'leader') ||
                str_contains($roleKey, 'founder') ||
                str_contains($roleKey, 'chair') ||
                str_contains($roleKey, 'secretary');

            if ($isDed) {
                if ($scopeId) {
                    $district = DB::table('districts')->where('id', $scopeId)->first();
                    $state = $district ? DB::table('states')->where('id', $district->state_id)->first() : null;

                    DB::table('admin_ded_districts')->where('admin_user_id', $adminUserId)->delete();

                    DB::table('admin_ded_districts')->insert([
                        'id' => (string) Str::uuid(),
                        'admin_user_id' => $adminUserId,
                        'user_id' => $adminUserId,
                        'district_id' => $scopeId,
                        'district_name' => $district->name ?? '',
                        'state_id' => $district->state_id ?? null,
                        'state_name' => $state->name ?? '',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } elseif ($isId) {
                if ($scopeId) {
                    $industry = DB::table('industries')->where('id', $scopeId)->first();

                    DB::table('industry_director_assignments')
                        ->where('admin_user_id', $adminUserId)
                        ->delete();

                    DB::table('industry_director_assignments')->insert([
                        'id' => (string) Str::uuid(),
                        'admin_user_id' => $adminUserId,
                        'industry_id' => $scopeId,
                        'industry_name' => $industry->name ?? '',
                        'assigned_by' => auth('admin')->id(),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } elseif ($isCircle) {
                if ($scopeId) {
                    $circle = DB::table('circles')->where('id', $scopeId)->first();
                    $appUser = DB::table('users')->whereRaw('LOWER(email) = ?', [strtolower($adminUser->email)])->first();

                    if (! $appUser) {
                        $appUserId = (string) Str::uuid();
                        $parts = explode(' ', trim($adminUser->name ?? 'Admin'));
                        $firstName = $parts[0] ?? 'Admin';
                        $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'User';

                        DB::table('users')->insert([
                            'id' => $appUserId,
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'display_name' => $adminUser->name ?? 'Admin User',
                            'email' => strtolower($adminUser->email),
                            'password_hash' => $adminUser->password ?? bcrypt(Str::random(16)),
                            'status' => 'active',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $appUser = DB::table('users')->where('id', $appUserId)->first();
                    }

                    if ($appUser) {
                        $colName = 'circle_director_user_id';
                        $dbRole = 'director';

                        if (str_contains($roleKey, 'founder') || str_contains($roleKey, 'cf')) {
                            $colName = 'circle_founder_user_id';
                            $dbRole = 'founder';
                        } elseif (str_contains($roleKey, 'vice_chair') || str_contains($roleKey, 'vice')) {
                            $colName = 'vice_chair_user_id';
                            $dbRole = 'vice_chair';
                        } elseif (str_contains($roleKey, 'chair')) {
                            $colName = 'chair_user_id';
                            $dbRole = 'chair';
                        } elseif (str_contains($roleKey, 'secretary')) {
                            $colName = 'secretary_user_id';
                            $dbRole = 'secretary';
                        }

                        if ($colName) {
                            DB::table('circles')->where($colName, $appUser->id)->update([
                                $colName => null,
                                'updated_at' => now(),
                            ]);

                            DB::table('circles')->where('id', $scopeId)->update([
                                $colName => $appUser->id,
                                'updated_at' => now(),
                            ]);
                        }

                        DB::table('circle_members')
                            ->where('user_id', $appUser->id)
                            ->where('role', $dbRole)
                            ->delete();

                        $existingMember = DB::table('circle_members')
                            ->where('circle_id', $scopeId)
                            ->where('user_id', $appUser->id)
                            ->first();

                        if ($existingMember) {
                            DB::table('circle_members')
                                ->where('id', $existingMember->id)
                                ->update([
                                    'role' => $dbRole,
                                    'status' => 'approved',
                                    'updated_at' => now(),
                                    'deleted_at' => null,
                                ]);
                        } else {
                            DB::table('circle_members')->insert([
                                'id' => (string) Str::uuid(),
                                'circle_id' => $scopeId,
                                'user_id' => $appUser->id,
                                'role' => $dbRole,
                                'status' => 'approved',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        DB::table('tbl_permission_cache')->where('user_id', $appUser->id)->delete();
                    }
                }
            }

            $admin = auth('admin')->user();
            if ($admin) {
                $this->audit->log(
                    $admin,
                    'admin.rbac.role.assign',
                    'admin_user_roles',
                    $adminUserId,
                    [],
                    [
                        'role_key' => $role->key,
                        'scope_id' => $scopeId,
                    ],
                    $request
                );
            }
        });
    }

    private function checkEditPermission(): void
    {
        $admin = auth('admin')->user();
        if ($admin && ! \App\Support\AdminAccess::isEditAllowed($admin)) {
            abort(403, 'You do not have edit permissions.');
        }
    }

    public function removeCurrentRole(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        if (!$admin) {
            abort(401);
        }

        $roleKeys = AdminAccess::adminRoleKeys($admin);

        // If the user already only has the 'user' role, reject the request
        if (collect($roleKeys)->reject('user')->isEmpty()) {
            return redirect()->back()->withErrors(['message' => 'You already have the default User role.']);
        }

        // Get or create the 'user' role
        $userRole = Role::where('key', 'user')->first();
        if (!$userRole) {
            $userRoleId = (string) Str::uuid();
            DB::table('roles')->insert([
                'id' => $userRoleId,
                'key' => 'user',
                'name' => 'User',
                'description' => 'Default User Role',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $userRole = Role::find($userRoleId);
        }

        // Get all role keys for audit logging
        $oldRoles = DB::table('roles')
            ->join('admin_user_roles', 'admin_user_roles.role_id', '=', 'roles.id')
            ->where('admin_user_roles.user_id', $admin->id)
            ->pluck('roles.key')
            ->toArray();

        DB::transaction(function () use ($admin, $userRole, $oldRoles, $request) {
            // Delete existing role assignments
            DB::table('admin_user_roles')
                ->where('user_id', $admin->id)
                ->delete();

            // Insert new default 'user' role assignment
            $insertData = [
                'user_id' => $admin->id,
                'role_id' => $userRole->id,
            ];
            if (DB::connection()->getDriverName() === 'pgsql') {
                $insertData['id'] = (string) Str::uuid();
                $insertData['created_at'] = now();
            }
            DB::table('admin_user_roles')->insert($insertData);

            // Deactivate industry director assignments
            if (Schema::hasTable('industry_director_assignments')) {
                DB::table('industry_director_assignments')
                    ->where('admin_user_id', $admin->id)
                    ->update([
                        'is_active' => false,
                        'updated_at' => now(),
                    ]);
            }

            // Delete DED district scope rollups
            if (Schema::hasTable('admin_ded_districts')) {
                DB::table('admin_ded_districts')
                    ->where('admin_user_id', $admin->id)
                    ->delete();
            }

            // Log action in audit log
            $this->audit->log(
                $admin,
                'admin.profile.remove_current_role',
                'admin_user_roles',
                $admin->id,
                ['roles' => $oldRoles],
                ['roles' => ['user']],
                $request
            );
        });

        // Invalidate permissions and role caches
        ScopeCascadeResolver::invalidateCache($admin->id);
        Cache::forget('admin-access:ded-location:'.$admin->id);
        Cache::forget('admin-access:primary-role:'.$admin->id);
        Cache::forget('admin-access:primary-role-label:'.$admin->id);

        // Sign out the user and invalidate the admin session
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->forget(['admin_user_id', 'admin_login_email']);
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'Your role has been removed successfully. Your account has been changed to the default User role.');
    }
}
