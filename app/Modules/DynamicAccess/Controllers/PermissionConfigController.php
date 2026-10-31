<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Leader\Models\LeaderRoleCapability;
use App\Models\AdminPage;
use App\Models\Permission;
use App\Models\RolePagePermission;
use App\Modules\DynamicAccess\Services\DynamicAccessConfigService;
use App\Services\Admin\PermissionService;
use App\Shared\Authorization\EffectiveAccessResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class PermissionConfigController extends BaseApiController
{
    public function __construct(
        private readonly DynamicAccessConfigService $configService,
        private readonly EffectiveAccessResolverService $accessResolver,
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * API 11: List Permissions.
     */
    public function index(Request $request): JsonResponse
    {
        if (Schema::hasTable('permissions')) {
            $cols = Schema::getColumnListing('permissions');
            $query = Permission::query()->orderBy('sort_order')->orderBy('key');

            if ($request->filled('module_key') && in_array('module_key', $cols, true)) {
                $query->where('module_key', (string) $request->query('module_key'));
            }
            if ($request->filled('resource_key') && in_array('resource_key', $cols, true)) {
                $query->where('resource_key', (string) $request->query('resource_key'));
            }

            $perms = $query->get();
            if ($perms->isNotEmpty()) {
                return $this->success([
                    'total' => $perms->count(),
                    'items' => $perms,
                ], 'Permissions fetched successfully.');
            }
        }

        // Default catalog when permissions table is empty or not yet created
        $defaultKeys = $this->accessResolver->resolveGrantedPermissionKeys(['global_admin'], true);
        $items = array_map(function (string $k, int $idx): array {
            $parts = explode('.', $k, 2);

            return [
                'id' => $k,
                'key' => $k,
                'name' => Str::headline(str_replace('.', ' ', $k)),
                'module_key' => $parts[0] ?? 'core',
                'resource_key' => $parts[0] ?? 'core',
                'action_key' => $parts[1] ?? $k,
                'sort_order' => $idx + 1,
                'is_system' => true,
                'is_active' => true,
            ];
        }, $defaultKeys, array_keys($defaultKeys));

        return $this->success([
            'total' => count($items),
            'items' => $items,
        ], 'Permissions fetched successfully.');
    }

    /**
     * API 12: Create Permission.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'module_key' => ['nullable', 'string', 'max:100'],
            'resource_key' => ['nullable', 'string', 'max:100'],
            'action_key' => ['nullable', 'string', 'max:100'],
            'target_app' => ['nullable', 'string', 'in:admin_panel,leadership_app,main_app,both'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (! Schema::hasTable('permissions')) {
            return $this->error('The permissions table is not yet initialized. Please run Script B (02_additive_schema.sql).', 422);
        }

        $cols = Schema::getColumnListing('permissions');
        $payload = array_intersect_key(array_merge([
            'id' => (string) Str::uuid(),
            'module_key' => $validated['module_key'] ?? 'general',
            'resource_key' => $validated['resource_key'] ?? 'general',
            'action_key' => $validated['action_key'] ?? 'view',
            'target_app' => $validated['target_app'] ?? 'both',
            'sort_order' => $validated['sort_order'] ?? 50,
            'is_system' => false,
            'is_active' => true,
        ], $validated), array_flip($cols));

        $permission = Permission::query()->updateOrCreate(['key' => $validated['key']], $payload);

        return $this->success($permission, 'Permission saved successfully.', 201);
    }

    /**
     * API 13: Update Permission.
     */
    public function update(Request $request, string $permissionId): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'module_key' => ['nullable', 'string', 'max:100'],
            'resource_key' => ['nullable', 'string', 'max:100'],
            'action_key' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! Schema::hasTable('permissions')) {
            return $this->error('The permissions table is not yet initialized.', 422);
        }

        $perm = Str::isUuid($permissionId)
            ? Permission::query()->findOrFail($permissionId)
            : Permission::query()->where('key', $permissionId)->firstOrFail();

        $cols = Schema::getColumnListing('permissions');
        $perm->fill(array_intersect_key($validated, array_flip($cols)))->save();

        return $this->success($perm, 'Permission updated successfully.');
    }

    /**
     * API 14: Get Role Permission Matrix.
     */
    public function rolePermissions(string $roleId): JsonResponse
    {
        try {
            $role = $this->configService->resolveRoleOrFail($roleId);
            $formatted = $this->configService->formatRole($role);
            $rKey = $formatted['normalized_key'];
            $isGlobal = in_array($rKey, ['global_admin', 'super_admin', 'country_director'], true);

            $grantedKeys = $this->accessResolver->resolveGrantedPermissionKeys([$rKey], $isGlobal);

            return $this->success([
                'role_id' => (string) $role->id,
                'role_key' => $role->key,
                'role_name' => $role->name,
                'granted_permission_keys' => $grantedKeys,
            ], 'Role permissions fetched successfully.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * API 15: Sync Role Permissions.
     */
    public function syncRolePermissions(Request $request, string $roleId): JsonResponse
    {
        $validated = $request->validate([
            'permission_keys' => ['nullable', 'array'],
            'permission_keys.*' => ['string'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string'],
            'matrix' => ['nullable', 'array'],
        ]);

        try {
            $role = $this->configService->resolveRoleOrFail($roleId);
            $keys = array_values(array_unique(array_merge(
                $validated['permission_keys'] ?? [],
                $validated['capabilities'] ?? []
            )));

            // 1. Sync to leader_role_capabilities if table exists
            if (Schema::hasTable('leader_role_capabilities') && ! empty($keys)) {
                LeaderRoleCapability::query()->where('role_key', $role->key)->delete();
                foreach ($keys as $k) {
                    LeaderRoleCapability::query()->create([
                        'id' => (string) Str::uuid(),
                        'role_key' => $role->key,
                        'capability_id' => $k,
                        'is_enabled' => true,
                    ]);
                }
            }

            // 2. Sync to role_page_permissions if matrix or permission_keys provided and tables exist
            if (Schema::hasTable('role_page_permissions') && Schema::hasTable('permissions') && Schema::hasTable('admin_pages')) {
                if (! empty($validated['matrix'])) {
                    $this->permissionService->syncRolePermissions((string) $role->id, $validated['matrix']);
                } elseif (! empty($keys)) {
                    $defaultPage = AdminPage::query()->first();
                    if ($defaultPage) {
                        foreach ($keys as $k) {
                            $perm = Permission::query()->where('key', $k)->first();
                            if ($perm) {
                                RolePagePermission::query()->updateOrCreate(
                                    [
                                        'role_id' => $role->id,
                                        'page_id' => $defaultPage->id,
                                        'permission_id' => $perm->id,
                                    ],
                                    ['is_granted' => true]
                                );
                            }
                        }
                    }
                }
            }

            return $this->rolePermissions((string) $role->id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }
}

