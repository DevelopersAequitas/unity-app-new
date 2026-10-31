<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Services;

use App\Leader\Models\LeaderRoleCapability;
use App\Models\Role;
use App\Models\RoleHierarchy;
use App\Models\RolePagePermission;
use App\Modules\DynamicAccess\Models\RoleFieldAccessRule;
use App\Modules\DynamicAccess\Models\RoleReportPermission;
use App\Modules\DynamicAccess\Models\RoleScopePolicy;
use App\Shared\Authorization\AccessConstants;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class DynamicAccessConfigService
{
    /**
     * List all roles with counts and configuration metadata.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function listRoles(array $filters = []): array
    {
        if (! Schema::hasTable('roles')) {
            return AccessConstants::defaultRolesCatalog();
        }

        $cols = Schema::getColumnListing('roles');
        $query = Role::query();

        if (! empty($filters['status']) && in_array('status', $cols, true)) {
            $query->where('status', (string) $filters['status']);
        }
        if (isset($filters['is_leadership_role']) && in_array('is_leadership_role', $cols, true)) {
            $query->where('is_leadership_role', filter_var($filters['is_leadership_role'], FILTER_VALIDATE_BOOLEAN));
        }
        if (isset($filters['is_admin_panel_role']) && in_array('is_admin_panel_role', $cols, true)) {
            $query->where('is_admin_panel_role', filter_var($filters['is_admin_panel_role'], FILTER_VALIDATE_BOOLEAN));
        }
        if (! empty($filters['search'])) {
            $search = strtolower(trim((string) $filters['search']));
            $query->where(function ($q) use ($search): void {
                $q->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('LOWER(key) LIKE ?', ['%'.$search.'%']);
            });
        }

        if (in_array('hierarchy_level', $cols, true)) {
            $query->orderBy('hierarchy_level');
        } elseif (in_array('hierarchy_depth', $cols, true)) {
            $query->orderBy('hierarchy_depth');
        }
        $query->orderBy('name');

        $roles = $query->get();

        // Merge with default catalog if standard roles are not yet seeded in roles table
        $existingKeys = $roles->pluck('key')->map(fn ($k) => AccessConstants::normalizeRoleKey((string) $k))->all();
        $result = $roles->map(fn (Role $r): array => $this->formatRole($r))->all();

        foreach (AccessConstants::defaultRolesCatalog() as $defaultRole) {
            if (! in_array($defaultRole['key'], $existingKeys, true) && empty($filters['search'])) {
                $result[] = array_merge(['id' => $defaultRole['key']], $defaultRole);
            }
        }

        return $result;
    }

    /**
     * Resolve a Role model by UUID or key, creating a standard role on the fly if needed.
     */
    public function resolveRoleOrFail(string $roleIdOrKey): Role
    {
        $role = Str::isUuid($roleIdOrKey)
            ? Role::query()->where('id', $roleIdOrKey)->first()
            : Role::query()->where('key', $roleIdOrKey)->orWhere('key', AccessConstants::normalizeRoleKey($roleIdOrKey))->first();

        if (! $role) {
            $normalized = AccessConstants::normalizeRoleKey($roleIdOrKey);
            $catalogMatch = collect(AccessConstants::defaultRolesCatalog())->firstWhere('key', $normalized);
            if ($catalogMatch && Schema::hasTable('roles')) {
                $cols = Schema::getColumnListing('roles');
                $payload = array_intersect_key(array_merge([
                    'id' => (string) Str::uuid(),
                    'role_type' => 'system',
                    'scope_rule' => $catalogMatch['default_scope_type'] === 'global' ? 'global' : 'circle',
                    'hierarchy_depth' => 2,
                ], $catalogMatch), array_flip($cols));

                $role = Role::query()->create($payload);
            }
        }

        if (! $role) {
            throw new RuntimeException("Role '{$roleIdOrKey}' not found.");
        }

        return $role;
    }

    /**
     * Create a new role in public.roles.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createRole(array $data): array
    {
        $cols = Schema::getColumnListing('roles');
        $key = AccessConstants::normalizeRoleKey((string) ($data['role_key'] ?? $data['key'] ?? Str::slug((string) $data['name'], '_')));

        if (Role::query()->where('key', $key)->exists()) {
            throw new RuntimeException("A role with key '{$key}' already exists.");
        }

        $defaultScope = (string) ($data['default_scope_type'] ?? 'own_circle');
        $scopeRule = match ($defaultScope) {
            'global', 'country' => 'global',
            'district' => 'district',
            'industry' => 'industry',
            'self', 'none' => 'own',
            default => 'circle',
        };

        $raw = [
            'id' => (string) Str::uuid(),
            'key' => $key,
            'name' => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'role_type' => $data['role_type'] ?? 'custom',
            'scope_rule' => $scopeRule,
            'default_scope_type' => $defaultScope,
            'hierarchy_level' => (int) ($data['hierarchy_level'] ?? 70),
            'hierarchy_depth' => (int) ($data['hierarchy_depth'] ?? 4),
            'is_system_role' => false,
            'is_leadership_role' => (bool) ($data['is_leadership_role'] ?? true),
            'is_admin_panel_role' => (bool) ($data['is_admin_panel_role'] ?? true),
            'is_assignable' => (bool) ($data['is_assignable'] ?? true),
            'status' => (string) ($data['status'] ?? 'active'),
        ];

        $role = Role::query()->create(array_intersect_key($raw, array_flip($cols)));
        Cache::forget('roles:key_map');

        return $this->formatRole($role);
    }

    /**
     * Update an existing role.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateRole(string $roleId, array $data): array
    {
        $role = $this->resolveRoleOrFail($roleId);
        $cols = Schema::getColumnListing('roles');

        $updatable = [];
        foreach (['name', 'description', 'default_scope_type', 'hierarchy_level', 'hierarchy_depth', 'is_leadership_role', 'is_admin_panel_role', 'is_assignable', 'status'] as $field) {
            if (array_key_exists($field, $data) && in_array($field, $cols, true)) {
                $updatable[$field] = $data[$field];
            }
        }

        if (! empty($updatable)) {
            $role->fill($updatable)->save();
            Cache::forget('roles:key_map');
        }

        return $this->formatRole($role->fresh() ?? $role);
    }

    /**
     * Clone an existing role along with its permissions, screens, scope policies, field rules, and report access.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function cloneRole(string $sourceRoleId, array $data): array
    {
        $source = $this->resolveRoleOrFail($sourceRoleId);

        $created = $this->createRole([
            'role_key' => $data['role_key'] ?? ($source->key.'_copy_'.Str::lower(Str::random(4))),
            'name' => $data['name'] ?? ($source->name.' (Copy)'),
            'description' => $data['description'] ?? $source->description,
            'default_scope_type' => $source->default_scope_type ?? 'own_circle',
            'hierarchy_level' => $source->hierarchy_level ?? 70,
            'is_leadership_role' => $source->is_leadership_role ?? true,
            'is_admin_panel_role' => $source->is_admin_panel_role ?? true,
        ]);

        $newRoleId = (string) $created['id'];

        if (Schema::hasTable('role_page_permissions')) {
            $perms = RolePagePermission::query()->where('role_id', $source->id)->get();
            foreach ($perms as $p) {
                RolePagePermission::query()->firstOrCreate([
                    'role_id' => $newRoleId,
                    'page_id' => $p->page_id,
                    'permission_id' => $p->permission_id,
                ], [
                    'is_granted' => $p->is_granted,
                    'conditions' => $p->conditions,
                ]);
            }
        }

        if (Schema::hasTable('role_scope_policies')) {
            $policies = RoleScopePolicy::query()->where('role_id', $source->id)->get();
            foreach ($policies as $pol) {
                RoleScopePolicy::query()->updateOrCreate(
                    ['role_id' => $newRoleId, 'resource_key' => $pol->resource_key],
                    $pol->only([
                        'scope_type', 'include_assigned_circles', 'include_child_circles',
                        'include_same_district', 'include_same_industry', 'include_all_peers_in_scope',
                        'allow_cross_circle_read', 'allow_cross_circle_write', 'allow_export', 'custom_filter_config',
                    ])
                );
            }
        }

        if (Schema::hasTable('role_field_access_rules')) {
            $rules = RoleFieldAccessRule::query()->where('role_id', $source->id)->get();
            foreach ($rules as $r) {
                RoleFieldAccessRule::query()->updateOrCreate(
                    ['role_id' => $newRoleId, 'resource_key' => $r->resource_key, 'field_key' => $r->field_key],
                    $r->only(['can_view', 'can_edit', 'mask_strategy', 'metadata'])
                );
            }
        }

        if (Schema::hasTable('role_report_permissions')) {
            $repPerms = RoleReportPermission::query()->where('role_id', $source->id)->get();
            foreach ($repPerms as $rp) {
                RoleReportPermission::query()->updateOrCreate(
                    ['role_id' => $newRoleId, 'report_id' => $rp->report_id],
                    $rp->only(['can_view', 'can_export', 'can_schedule', 'allowed_scope_override', 'visible_columns'])
                );
            }
        }

        return $created;
    }

    /**
     * Delete a custom role safely.
     */
    public function deleteRole(string $roleId): void
    {
        $role = $this->resolveRoleOrFail($roleId);
        $systemKeys = ['global_admin', 'super_admin', 'country_director', 'ded', 'industry_director', 'circle_founder', 'circle_director', 'chair', 'vice_chair', 'secretary', 'member'];

        if (in_array(AccessConstants::normalizeRoleKey((string) $role->key), $systemKeys, true) || ($role->role_type === 'system') || ($role->is_system_role ?? false)) {
            throw new RuntimeException('System roles cannot be deleted. You may only delete custom roles.');
        }

        if (Schema::hasTable('admin_user_roles')) {
            $assignedCount = DB::table('admin_user_roles')->where('role_id', $role->id)->count();
            if ($assignedCount > 0) {
                throw new RuntimeException('Cannot delete a role that is currently assigned to users.');
            }
        }

        if (Schema::hasTable('leader_role_capabilities')) {
            LeaderRoleCapability::query()->where('role_key', $role->key)->delete();
        }

        $role->delete();
        Cache::forget('roles:key_map');
    }

    /**
     * Get role hierarchy links.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRoleHierarchy(): array
    {
        if (! Schema::hasTable('role_hierarchies')) {
            return [];
        }

        return RoleHierarchy::query()
            ->with(['parentRole:id,key,name', 'childRole:id,key,name'])
            ->get()
            ->map(fn (RoleHierarchy $h): array => [
                'id' => (string) $h->id,
                'parent_role_id' => (string) $h->parent_role_id,
                'parent_role_key' => $h->parentRole?->key,
                'parent_role_name' => $h->parentRole?->name,
                'child_role_id' => (string) $h->child_role_id,
                'child_role_key' => $h->childRole?->key,
                'child_role_name' => $h->childRole?->name,
                'can_assign' => (bool) ($h->can_assign ?? true),
                'can_view_subordinate_data' => (bool) ($h->can_view_subordinate_data ?? true),
                'can_approve_subordinate_actions' => (bool) ($h->can_approve_subordinate_actions ?? false),
            ])
            ->all();
    }

    /**
     * Update role hierarchy links.
     *
     * @param  array<int, array<string, mixed>>  $links
     * @return array<int, array<string, mixed>>
     */
    public function updateRoleHierarchy(array $links): array
    {
        if (! Schema::hasTable('role_hierarchies')) {
            return [];
        }

        $cols = Schema::getColumnListing('role_hierarchies');

        DB::transaction(function () use ($links, $cols): void {
            RoleHierarchy::query()->delete();
            foreach ($links as $link) {
                if ((string) $link['parent_role_id'] === (string) $link['child_role_id']) {
                    continue;
                }
                $payload = [
                    'id' => (string) Str::uuid(),
                    'parent_role_id' => (string) $link['parent_role_id'],
                    'child_role_id' => (string) $link['child_role_id'],
                    'can_assign' => (bool) ($link['can_assign'] ?? true),
                    'can_view_subordinate_data' => (bool) ($link['can_view_subordinate_data'] ?? true),
                    'can_approve_subordinate_actions' => (bool) ($link['can_approve_subordinate_actions'] ?? false),
                ];
                RoleHierarchy::query()->create(array_intersect_key($payload, array_flip($cols)));
            }
        });

        return $this->getRoleHierarchy();
    }

    /**
     * Format a Role model into a standardized array.
     *
     * @return array<string, mixed>
     */
    public function formatRole(Role $role): array
    {
        $normalizedKey = AccessConstants::normalizeRoleKey((string) $role->key);
        $assignedUsersCount = Schema::hasTable('admin_user_roles')
            ? DB::table('admin_user_roles')->where('role_id', $role->id)->count()
            : 0;

        return [
            'id' => (string) $role->id,
            'key' => (string) $role->key,
            'normalized_key' => $normalizedKey,
            'name' => (string) $role->name,
            'description' => $role->description,
            'role_type' => $role->role_type ?? 'system',
            'scope_rule' => $role->scope_rule ?? 'circle',
            'default_scope_type' => $role->default_scope_type ?? AccessConstants::defaultScopeForRole($normalizedKey),
            'hierarchy_level' => (int) ($role->hierarchy_level ?? 50),
            'hierarchy_depth' => (int) ($role->hierarchy_depth ?? 2),
            'is_system_role' => (bool) ($role->is_system_role ?? ($role->role_type === 'system')),
            'is_leadership_role' => (bool) ($role->is_leadership_role ?? ($normalizedKey !== 'member')),
            'is_admin_panel_role' => (bool) ($role->is_admin_panel_role ?? ($normalizedKey !== 'member')),
            'is_assignable' => (bool) ($role->is_assignable ?? true),
            'status' => (string) ($role->status ?? 'active'),
            'assigned_users_count' => $assignedUsersCount,
            'created_at' => $role->created_at?->toIso8601String(),
            'updated_at' => $role->updated_at?->toIso8601String(),
        ];
    }
}
