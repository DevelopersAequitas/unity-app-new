<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\DynamicAccess\Requests\StoreRoleRequest;
use App\Modules\DynamicAccess\Requests\UpdateRoleRequest;
use App\Modules\DynamicAccess\Services\DynamicAccessConfigService;
use App\Shared\Authorization\EffectiveAccessResolverService;
use App\Shared\Authorization\FieldAccessService;
use App\Shared\OrganizationalScope\OrganizationalScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RoleConfigController extends BaseApiController
{
    public function __construct(
        private readonly DynamicAccessConfigService $configService,
        private readonly OrganizationalScopeService $scopeService,
        private readonly FieldAccessService $fieldAccessService,
        private readonly EffectiveAccessResolverService $accessResolver,
    ) {}

    /**
     * API 2: List Roles.
     */
    public function index(Request $request): JsonResponse
    {
        $roles = $this->configService->listRoles($request->only([
            'status',
            'is_leadership_role',
            'is_admin_panel_role',
            'search',
        ]));

        return $this->success([
            'total' => count($roles),
            'items' => $roles,
        ], 'Roles fetched successfully.');
    }

    /**
     * API 3: Create Role.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            $role = $this->configService->createRole($request->validated());

            return $this->success($role, 'Role created successfully.', 201);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * API 4: Get Role Details.
     */
    public function show(string $roleId): JsonResponse
    {
        try {
            $role = $this->configService->resolveRoleOrFail($roleId);
            $formatted = $this->configService->formatRole($role);
            $rKey = $formatted['normalized_key'];
            $isGlobal = in_array($rKey, ['global_admin', 'super_admin', 'country_director'], true);

            $formatted['permissions'] = $this->accessResolver->resolveGrantedPermissionKeys([$rKey], $isGlobal);
            $formatted['scope_policies'] = array_values($this->scopeService->resolveResourcePolicies([$rKey]));
            $formatted['field_access_rules'] = $this->fieldAccessService->getRulesForRoleKeys([$rKey]);
            $formatted['allowed_reports'] = $this->accessResolver->resolveAllowedReports([$rKey], $isGlobal);

            return $this->success($formatted, 'Role details fetched successfully.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * API 5: Update Role.
     */
    public function update(UpdateRoleRequest $request, string $roleId): JsonResponse
    {
        try {
            $role = $this->configService->updateRole($roleId, $request->validated());

            return $this->success($role, 'Role updated successfully.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * API 6: Activate / Deactivate Role.
     */
    public function updateStatus(Request $request, string $roleId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        try {
            $role = $this->configService->resolveRoleOrFail($roleId);
            if ($validated['status'] === 'inactive' && in_array($role->key, ['global_admin', 'super_admin'], true)) {
                return $this->error('Cannot deactivate primary Super Admin / Global Admin role.', 422);
            }

            $updated = $this->configService->updateRole($roleId, ['status' => $validated['status']]);

            return $this->success($updated, 'Role status updated successfully.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * API 7: Clone Role.
     */
    public function cloneRole(Request $request, string $roleId): JsonResponse
    {
        $validated = $request->validate([
            'role_key' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $cloned = $this->configService->cloneRole($roleId, $validated);

            return $this->success($cloned, 'Role cloned successfully.', 201);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * API 8: Delete Custom Role.
     */
    public function destroy(string $roleId): JsonResponse
    {
        try {
            $this->configService->deleteRole($roleId);

            return $this->success(['deleted' => true], 'Custom role deleted successfully.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * API 9: List Role Hierarchy.
     */
    public function hierarchyIndex(): JsonResponse
    {
        return $this->success([
            'roles' => $this->configService->listRoles(),
            'links' => $this->configService->getRoleHierarchy(),
        ], 'Role hierarchy fetched successfully.');
    }

    /**
     * API 10: Update Role Hierarchy.
     */
    public function hierarchyUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'links' => ['required', 'array'],
            'links.*.parent_role_id' => ['required', 'uuid', 'exists:roles,id'],
            'links.*.child_role_id' => ['required', 'uuid', 'exists:roles,id', 'different:links.*.parent_role_id'],
            'links.*.can_assign' => ['nullable', 'boolean'],
            'links.*.can_view_subordinate_data' => ['nullable', 'boolean'],
            'links.*.can_approve_subordinate_actions' => ['nullable', 'boolean'],
        ]);

        $updated = $this->configService->updateRoleHierarchy($validated['links']);

        return $this->success([
            'links' => $updated,
        ], 'Role hierarchy updated successfully.');
    }
}

