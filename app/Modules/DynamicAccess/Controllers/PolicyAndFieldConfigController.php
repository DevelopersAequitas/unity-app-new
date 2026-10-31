<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\DynamicAccess\Models\RoleFieldAccessRule;
use App\Modules\DynamicAccess\Models\RoleScopePolicy;
use App\Modules\DynamicAccess\Requests\UpdateFieldAccessRequest;
use App\Modules\DynamicAccess\Requests\UpdateScopePoliciesRequest;
use App\Modules\DynamicAccess\Services\DynamicAccessConfigService;
use App\Shared\Authorization\FieldAccessService;
use App\Shared\OrganizationalScope\OrganizationalScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PolicyAndFieldConfigController extends BaseApiController
{
    public function __construct(
        private readonly DynamicAccessConfigService $configService,
        private readonly OrganizationalScopeService $scopeService,
        private readonly FieldAccessService $fieldAccessService,
    ) {}

    /**
     * API 22: Get Role Scope Policies.
     */
    public function showScopePolicies(string $roleId): JsonResponse
    {
        try {
            $role = $this->configService->resolveRoleOrFail($roleId);
            $formatted = $this->configService->formatRole($role);
            $policies = $this->scopeService->resolveResourcePolicies([$formatted['normalized_key']]);

            return $this->success([
                'role_id' => (string) $role->id,
                'role_key' => $role->key,
                'role_name' => $role->name,
                'policies' => array_values($policies),
            ], 'Role scope policies fetched successfully.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * API 23: Update Role Scope Policies.
     */
    public function updateScopePolicies(UpdateScopePoliciesRequest $request, string $roleId): JsonResponse
    {
        try {
            $role = $this->configService->resolveRoleOrFail($roleId);

            if (! Schema::hasTable('role_scope_policies')) {
                return $this->error('Table role_scope_policies is not yet initialized. Please run Script B (02_additive_schema.sql).', 422);
            }

            $policies = $request->validated('policies');

            DB::transaction(function () use ($role, $policies): void {
                foreach ($policies as $pol) {
                    RoleScopePolicy::query()->updateOrCreate(
                        [
                            'role_id' => $role->id,
                            'resource_key' => (string) $pol['resource_key'],
                        ],
                        [
                            'scope_type' => (string) $pol['scope_type'],
                            'include_assigned_circles' => (bool) ($pol['include_assigned_circles'] ?? true),
                            'include_child_circles' => (bool) ($pol['include_child_circles'] ?? false),
                            'include_same_district' => (bool) ($pol['include_same_district'] ?? false),
                            'include_same_industry' => (bool) ($pol['include_same_industry'] ?? false),
                            'include_all_peers_in_scope' => (bool) ($pol['include_all_peers_in_scope'] ?? true),
                            'allow_cross_circle_read' => (bool) ($pol['allow_cross_circle_read'] ?? false),
                            'allow_cross_circle_write' => (bool) ($pol['allow_cross_circle_write'] ?? false),
                            'allow_export' => (bool) ($pol['allow_export'] ?? false),
                            'custom_filter_config' => $pol['custom_filter_config'] ?? [],
                        ]
                    );
                }
            });

            return $this->showScopePolicies((string) $role->id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * API 24: Get Role Field Access Rules.
     */
    public function showFieldAccess(string $roleId): JsonResponse
    {
        try {
            $role = $this->configService->resolveRoleOrFail($roleId);
            $formatted = $this->configService->formatRole($role);
            $rules = $this->fieldAccessService->getRulesForRoleKeys([$formatted['normalized_key']]);

            return $this->success([
                'role_id' => (string) $role->id,
                'role_key' => $role->key,
                'role_name' => $role->name,
                'rules' => $rules,
            ], 'Role field access rules fetched successfully.');
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * API 25: Update Role Field Access Rules.
     */
    public function updateFieldAccess(UpdateFieldAccessRequest $request, string $roleId): JsonResponse
    {
        try {
            $role = $this->configService->resolveRoleOrFail($roleId);

            if (! Schema::hasTable('role_field_access_rules')) {
                return $this->error('Table role_field_access_rules is not yet initialized. Please run Script B (02_additive_schema.sql).', 422);
            }

            $rules = $request->validated('rules');

            DB::transaction(function () use ($role, $rules): void {
                foreach ($rules as $r) {
                    RoleFieldAccessRule::query()->updateOrCreate(
                        [
                            'role_id' => $role->id,
                            'resource_key' => (string) $r['resource_key'],
                            'field_key' => (string) $r['field_key'],
                        ],
                        [
                            'can_view' => (bool) $r['can_view'],
                            'can_edit' => (bool) ($r['can_edit'] ?? false),
                            'mask_strategy' => (string) ($r['mask_strategy'] ?? 'none'),
                            'metadata' => $r['metadata'] ?? [],
                        ]
                    );
                }
            });

            return $this->showFieldAccess((string) $role->id);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }
}

