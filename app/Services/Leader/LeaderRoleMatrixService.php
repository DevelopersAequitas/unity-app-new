<?php

declare(strict_types=1);

namespace App\Services\Leader;

use App\Models\AdminModule;
use App\Models\LeaderRoleCapability;
use App\Models\Role;
use App\Models\RoleModuleAccess;
use Illuminate\Support\Str;
use RuntimeException;

class LeaderRoleMatrixService
{
    public function __construct(
        private readonly LeaderPermissionService $permissionService,
    ) {}

    /**
     * Get 12-capability definitions and active roles matrix.
     *
     * @return array{capabilities: array<int, mixed>, roles: array<int, mixed>}
     */
    public function getMatrix(): array
    {
        $capabilities = $this->permissionService->getCapabilitiesMetadata();
        $defaults = $this->permissionService->getDefaultRoleCapabilities();

        $standardRoles = [
            'chairBusinessGrowth' => 'Chair - Business Growth Committee',
            'chairMembership' => 'Chair - Membership Committee',
            'chairEventsPrograms' => 'Chair - Events & Programs Committee',
            'circleFounder' => 'Circle Founder',
            'circleDirector' => 'Circle Director',
            'industryDirector' => 'Industry Director',
            'districtExecDirector' => 'District Exec Director',
            'countryDirector' => 'Country Director',
            'superAdmin' => 'Super Admin',
        ];

        $rolesList = [];

        foreach ($standardRoles as $roleKey => $roleLabel) {
            $enabled = $this->permissionService->getEnabledCapabilitiesForRole($roleKey);

            $rolesList[] = [
                'id' => $roleKey,
                'label' => $roleLabel,
                'is_system_role' => true,
                'enabled_capabilities' => array_values($enabled),
            ];
        }

        // Add custom dynamic roles from database
        $customRoles = Role::query()
            ->whereNotIn('key', [
                'global_admin', 'super_admin', 'chair', 'eed', 'ded', 'industry_director',
                'Circle Director', 'Circle Founder', 'member', 'user',
                'circleChair', 'Circle_Chair', 'chairBusinessGrowth', 'chairMembership', 'chairEventsPrograms',
                'chair_business_growth', 'chair_membership', 'chair_events_programs',
                'business_growth_committee_chair', 'membership_growth_committee_chair', 'events_impacts_committee_chair',
            ])
            ->get();

        foreach ($customRoles as $cr) {
            $key = (string) $cr->key;
            $enabled = $this->permissionService->getEnabledCapabilitiesForRole($key);

            $rolesList[] = [
                'id' => (string) $cr->id,
                'role_key' => $key,
                'label' => (string) $cr->name,
                'is_system_role' => false,
                'enabled_capabilities' => array_values($enabled),
            ];
        }

        return [
            'capabilities' => $capabilities,
            'roles' => $rolesList,
        ];
    }

    /**
     * Update enabled capabilities for a role.
     *
     * @param  array<string>  $enabledCapabilities
     */
    public function updateMatrix(string $roleId, array $enabledCapabilities): void
    {
        // Check if roleId is UUID of custom role or role key
        $role = Str::isUuid($roleId)
            ? Role::query()->where('id', $roleId)->first()
            : Role::query()->where('key', $roleId)->first();
        $roleKey = $role ? (string) $role->key : $roleId;

        // Remove previous overrides
        LeaderRoleCapability::query()->where('role_key', $roleKey)->delete();

        foreach ($enabledCapabilities as $capId) {
            LeaderRoleCapability::query()->create([
                'id' => (string) Str::uuid(),
                'role_key' => $roleKey,
                'capability_id' => $capId,
                'is_enabled' => true,
            ]);
        }

        if ($role) {
            $this->syncToDynamicRbacTables($role, $enabledCapabilities);
        }
    }

    /**
     * Create a new custom role.
     *
     * @param  array<string>  $enabledCapabilities
     * @return array<string, mixed>
     */
    public function createRole(string $label, array $enabledCapabilities): array
    {
        $roleKey = Str::slug($label, '_');
        $id = (string) Str::uuid();

        $role = Role::query()->create([
            'id' => $id,
            'key' => $roleKey,
            'name' => $label,
            'description' => "Custom leader role: {$label}",
            'role_type' => 'custom',
            'is_assignable' => true,
            'status' => 'active',
        ]);

        foreach ($enabledCapabilities as $capId) {
            LeaderRoleCapability::query()->create([
                'id' => (string) Str::uuid(),
                'role_key' => $roleKey,
                'capability_id' => $capId,
                'is_enabled' => true,
            ]);
        }

        $this->syncToDynamicRbacTables($role, $enabledCapabilities);

        return [
            'id' => (string) $role->id,
            'role_key' => $roleKey,
            'label' => (string) $role->name,
            'is_system_role' => false,
            'enabled_capabilities' => $enabledCapabilities,
        ];
    }

    /**
     * Sync capability assignments to Dynamic RBAC web tables (role_module_access and role_page_permissions).
     *
     * @param  array<string>  $enabledCapabilities
     */
    public function syncToDynamicRbacTables(Role $role, array $enabledCapabilities): void
    {
        $roleId = (string) $role->id;

        $moduleMapping = [
            'access_dashboard' => 'dashboard',
            'access_teams' => 'circles',
            'access_finance' => 'finance-analytics',
            'manage_finance' => 'finance-analytics',
            'view_peers' => 'members',
            'manage_peers' => 'members',
            'request_actions' => 'activities',
            'view_reports' => 'referral-report',
            'coin_payouts' => 'coins',
            'manage_roles' => 'role-management',
            'system_configs' => 'settings',
        ];

        $activeModuleSlugs = [];
        foreach ($enabledCapabilities as $cap) {
            if (isset($moduleMapping[$cap])) {
                $activeModuleSlugs[] = $moduleMapping[$cap];
            }
        }
        $activeModuleSlugs = array_unique($activeModuleSlugs);

        try {
            $modules = AdminModule::query()->get();
            if ($modules->isNotEmpty()) {
                foreach ($modules as $mod) {
                    $isVisible = in_array($mod->slug, $activeModuleSlugs, true);
                    RoleModuleAccess::query()->updateOrCreate(
                        ['role_id' => $roleId, 'module_id' => $mod->id],
                        ['is_visible' => $isVisible]
                    );
                }
            }
        } catch (\Throwable $e) {
            // Non-blocking if tables not loaded in current context
        }
    }

    /**
     * Update custom role.
     *
     * @param  array<string>|null  $enabledCapabilities
     */
    public function updateRole(string $id, ?string $label = null, ?array $enabledCapabilities = null): void
    {
        $role = Role::query()->where('id', $id)->first();
        if (! $role) {
            throw new RuntimeException("Role with ID {$id} not found.");
        }

        if ($label !== null) {
            $role->name = $label;
            $role->save();
        }

        if ($enabledCapabilities !== null) {
            $this->updateMatrix((string) $role->key, $enabledCapabilities);
        }
    }

    /**
     * Delete custom role.
     */
    public function deleteRole(string $id): void
    {
        $role = Role::query()->where('id', $id)->first();
        if ($role) {
            LeaderRoleCapability::query()->where('role_key', $role->key)->delete();
            $role->delete();
        }
    }
}
