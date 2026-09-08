<?php

declare(strict_types=1);

namespace App\Services\Leader;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\LeaderRoleCapability;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminCircleScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeaderPermissionService
{
    /**
     * Standard 12 capabilities metadata.
     *
     * @return array<int, array<string, string>>
     */
    public function getCapabilitiesMetadata(): array
    {
        return [
            [
                'id' => 'access_dashboard',
                'name' => 'Access Dashboard',
                'category' => 'Navigation & Access',
                'description' => 'Allows access to the primary metrics and impacter list dashboard.',
            ],
            [
                'id' => 'access_teams',
                'name' => 'Access Circles & Teams',
                'category' => 'Navigation & Access',
                'description' => 'Allows viewing circles, directors, and chairs directories.',
            ],
            [
                'id' => 'access_finance',
                'name' => 'Access Financial Analytics',
                'category' => 'Navigation & Access',
                'description' => 'Allows viewing fee collections, dues, and transaction histories.',
            ],
            [
                'id' => 'regional_data',
                'name' => 'View Regional Scope Data',
                'category' => 'Navigation & Access',
                'description' => 'Access and filter data beyond own local circle (District/Country level).',
            ],
            [
                'id' => 'view_peers',
                'name' => 'View Peer Profiles',
                'category' => 'Core Operations',
                'description' => 'Allows viewing and browsing peer profile details and attendance stats.',
            ],
            [
                'id' => 'manage_peers',
                'name' => 'Add/Edit Peer Information',
                'category' => 'Core Operations',
                'description' => 'Allows coordinators to add new peers or edit biographical fields.',
            ],
            [
                'id' => 'request_actions',
                'name' => 'Request Introductions & Actions',
                'category' => 'Core Operations',
                'description' => 'Allows sending wishes, introductions, and review peer approvals.',
            ],
            [
                'id' => 'view_reports',
                'name' => 'View & Submit Weekly Reports',
                'category' => 'Compliance & Growth',
                'description' => 'Allows viewing, submitting, and tracking weekly circle reports.',
            ],
            [
                'id' => 'manage_finance',
                'name' => 'Manage Financial Settings',
                'category' => 'Finance Control',
                'description' => 'Allows setting fee structures, tracking dues, and refunding.',
            ],
            [
                'id' => 'coin_payouts',
                'name' => 'Process Coin Payouts',
                'category' => 'Finance Control',
                'description' => 'Allows executing coin reward payouts to leaders and founders.',
            ],
            [
                'id' => 'manage_roles',
                'name' => 'Manage Roles & Matrix',
                'category' => 'Administration',
                'description' => 'Grants access to the Super Admin Role Matrix and privileges.',
            ],
            [
                'id' => 'system_configs',
                'name' => 'Configure System Parameters',
                'category' => 'Administration',
                'description' => 'Manage system-wide categories, tags, and launch configurations.',
            ],
        ];
    }

    /**
     * Default enabled capabilities for each standard system role.
     *
     * @return array<string, array<string>>
     */
    public function getDefaultRoleCapabilities(): array
    {
        return [
            'chairBusinessGrowth' => [
                'access_dashboard',
                'view_peers',
                'request_actions',
                'view_reports',
            ],
            'chairMembership' => [
                'access_dashboard',
                'view_peers',
                'request_actions',
                'view_reports',
            ],
            'chairEventsPrograms' => [
                'access_dashboard',
                'view_peers',
                'request_actions',
                'view_reports',
            ],
            'circleChair' => [
                'access_dashboard',
                'view_peers',
                'request_actions',
                'view_reports',
            ],
            'viceChair' => [
                'access_dashboard',
                'view_peers',
                'request_actions',
                'view_reports',
            ],
            'secretary' => [
                'access_dashboard',
                'view_peers',
                'request_actions',
                'view_reports',
            ],
            'circleFounder' => [
                'access_dashboard',
                'access_teams',
                'access_finance',
                'view_peers',
                'manage_peers',
                'request_actions',
                'view_reports',
            ],
            'circleDirector' => [
                'access_dashboard',
                'access_teams',
                'access_finance',
                'view_peers',
                'manage_peers',
                'request_actions',
                'view_reports',
            ],
            'industryDirector' => [
                'access_dashboard',
                'access_teams',
                'access_finance',
                'regional_data',
                'view_peers',
                'request_actions',
                'view_reports',
            ],
            'districtExecDirector' => [
                'access_dashboard',
                'access_teams',
                'access_finance',
                'regional_data',
                'view_peers',
                'manage_peers',
                'request_actions',
                'view_reports',
                'manage_finance',
            ],
            'countryDirector' => [
                'access_dashboard',
                'access_teams',
                'access_finance',
                'regional_data',
                'view_peers',
                'manage_peers',
                'request_actions',
                'view_reports',
                'manage_finance',
                'coin_payouts',
            ],
            'superAdmin' => [
                'access_dashboard',
                'access_teams',
                'access_finance',
                'regional_data',
                'view_peers',
                'manage_peers',
                'request_actions',
                'view_reports',
                'manage_finance',
                'coin_payouts',
                'manage_roles',
                'system_configs',
            ],
        ];
    }

    /**
     * Map database role key/name to canonical standard role key.
     */
    public function normalizeRoleKey(string $rawRole): string
    {
        $cleaned = trim($rawRole);
        $lower = strtolower($cleaned);
        $lowerNormalized = str_replace(['_', '-'], ' ', $lower);

        return match (true) {
            in_array($lower, ['superadmin', 'super_admin', 'global_admin', 'admin'], true) || str_contains($lowerNormalized, 'super admin') || str_contains($lowerNormalized, 'global admin') => 'superAdmin',
            in_array($lower, ['countrydirector', 'country_director', 'eed'], true) || str_contains($lowerNormalized, 'country director') => 'countryDirector',
            in_array($lower, ['districtexecdirector', 'district_exec_director', 'ded'], true) || str_contains($lowerNormalized, 'district exec director') || str_contains($lowerNormalized, 'district director') => 'districtExecDirector',
            in_array($lower, ['industrydirector', 'industry_director', 'id', 'ied'], true) || str_contains($lowerNormalized, 'industry director') => 'industryDirector',
            in_array($lower, ['circledirector', 'circle_director', 'cd', 'director'], true) || str_contains($lowerNormalized, 'circle director') => 'circleDirector',
            in_array($lower, ['circlefounder', 'circle_founder', 'cf', 'founder'], true) || str_contains($lowerNormalized, 'circle founder') => 'circleFounder',
            in_array($lower, ['business_growth_committee', 'business growth committee', 'chairbusinessgrowth', 'chair_business_growth', 'businessgrowthcommitteechair', 'business_growth_committee_chair', 'business_growth_chair', 'chair - business growth committee', 'business_growth'], true) || str_contains($lowerNormalized, 'business growth') => 'chairBusinessGrowth',
            in_array($lower, ['membership_growth_committee', 'membership growth committee', 'chairmembership', 'chair_membership', 'membershipgrowthcommitteechair', 'membership_growth_committee_chair', 'membership_committee_chair', 'membershipgrowthchair', 'membership_chair', 'chair - membership committee', 'chair - membership growth committee', 'membership_growth'], true) || str_contains($lowerNormalized, 'membership growth') || str_contains($lowerNormalized, 'membership committee') => 'chairMembership',
            in_array($lower, ['events_impacts_committee', 'events impacts committee', 'chaireventsprograms', 'chair_events_programs', 'eventsimpactscommitteechair', 'events_impacts_committee_chair', 'events_programs_committee_chair', 'eventsprogramscommitteechair', 'events_impacts_chair', 'events_chair', 'chair - events & programs committee', 'chair - events & impacts committee', 'events_programs_committee', 'events_impacts'], true) || str_contains($lowerNormalized, 'events impacts') || str_contains($lowerNormalized, 'events programs') || str_contains($lowerNormalized, 'events & programs') || str_contains($lowerNormalized, 'events & impacts') => 'chairEventsPrograms',
            in_array($lower, ['circlechair', 'circle_chair', 'chair'], true) => 'chairBusinessGrowth',
            in_array($lower, ['vice_chair', 'vicechair', 'vice chair'], true) || str_contains($lowerNormalized, 'vice chair') => 'viceChair',
            in_array($lower, ['secretary', 'circle_secretary'], true) || str_contains($lowerNormalized, 'secretary') => 'secretary',
            default => $cleaned,
        };
    }

    /**
     * Determine if a given role key is an authorized leadership role.
     */
    public function isLeaderRole(string $rawRole): bool
    {
        $normalized = $this->normalizeRoleKey($rawRole);

        return in_array($normalized, [
            'superAdmin',
            'countryDirector',
            'districtExecDirector',
            'industryDirector',
            'circleDirector',
            'circleFounder',
            'chairBusinessGrowth',
            'chairMembership',
            'chairEventsPrograms',
            'circleChair',
            'viceChair',
            'secretary',
        ], true);
    }

    /**
     * Check if a given User is an authorized Leader.
     */
    public function isLeader(User $user): bool
    {
        $roleInfo = $this->resolveUserRole($user);

        return (bool) ($roleInfo['is_leader'] ?? false);
    }

    /**
     * Canonical display label for a role.
     */
    public function getRoleLabel(string $canonicalRole): string
    {
        return match ($canonicalRole) {
            'superAdmin' => 'Super Admin',
            'countryDirector' => 'Country Director',
            'districtExecDirector' => 'District Exec Director',
            'industryDirector' => 'Industry Director',
            'circleDirector' => 'Circle Director',
            'circleFounder' => 'Circle Founder',
            'chairBusinessGrowth' => 'Chair - Business Growth Committee',
            'chairMembership' => 'Chair - Membership Committee',
            'chairEventsPrograms' => 'Chair - Events & Programs Committee',
            'circleChair' => 'Chair - Business Growth Committee',
            'viceChair' => 'Vice Chair',
            'secretary' => 'Secretary',
            'member' => 'Peer Member',
            default => ucwords(str_replace(['_', '-'], ' ', $canonicalRole)),
        };
    }

    /**
     * Detect the user's primary leader role and custom label.
     *
     * @return array{role: string, custom_role_label: ?string, regional_scope: string, is_leader: bool}
     */
    public function resolveUserRole(User $user): array
    {
        $userId = (string) $user->id;
        $userEmail = strtolower(trim((string) ($user->email ?? '')));

        // 1. Check AdminUser roles & direct user role assignments from Dynamic RBAC
        $adminUser = AdminUser::query()
            ->where('id', $userId)
            ->when($userEmail !== '', fn ($q) => $q->orWhereRaw('LOWER(email) = ?', [$userEmail]))
            ->first();

        $adminUserIds = array_values(array_filter(array_unique([$userId, $adminUser?->id])));

        if (! empty($adminUserIds) && Schema::hasTable('admin_user_roles') && Schema::hasTable('roles')) {
            $roles = DB::table('admin_user_roles')
                ->join('roles', 'admin_user_roles.role_id', '=', 'roles.id')
                ->whereIn('admin_user_roles.user_id', $adminUserIds)
                ->pluck('roles.key')
                ->all();

            foreach ($roles as $r) {
                if ($this->isLeaderRole((string) $r)) {
                    $normalized = $this->normalizeRoleKey((string) $r);

                    return [
                        'role' => $normalized,
                        'custom_role_label' => $this->getRoleLabel($normalized),
                        'regional_scope' => $this->resolveRegionalScope($normalized),
                        'is_leader' => true,
                    ];
                }
            }
        }

        // 2. Check Circle direct assignment columns & calendar JSON
        if (Schema::hasTable('circles')) {
            if (Circle::query()->where('circle_founder_user_id', $userId)->orWhere('founder_user_id', $userId)->exists()) {
                return [
                    'role' => 'circleFounder',
                    'custom_role_label' => 'Circle Founder',
                    'regional_scope' => 'Own Circle',
                    'is_leader' => true,
                ];
            }

            if (Circle::query()->where('circle_director_user_id', $userId)->orWhere('director_user_id', $userId)->exists()) {
                return [
                    'role' => 'circleDirector',
                    'custom_role_label' => 'Circle Director',
                    'regional_scope' => 'Own Circle',
                    'is_leader' => true,
                ];
            }

            if (Circle::query()->where('industry_director_user_id', $userId)->exists()) {
                return [
                    'role' => 'industryDirector',
                    'custom_role_label' => 'Industry Director',
                    'regional_scope' => 'Industry Scope',
                    'is_leader' => true,
                ];
            }

            if (Circle::query()->where('ded_user_id', $userId)->exists()) {
                return [
                    'role' => 'districtExecDirector',
                    'custom_role_label' => 'District Exec Director',
                    'regional_scope' => 'District Scope',
                    'is_leader' => true,
                ];
            }

            if (Circle::query()->where('eed_user_id', $userId)->exists()) {
                return [
                    'role' => 'countryDirector',
                    'custom_role_label' => 'Country Director',
                    'regional_scope' => 'Country Scope',
                    'is_leader' => true,
                ];
            }

            // Check committee chairs in circles (via column, json calendar, or chair_user_id)
            $circles = Circle::query()->whereNull('deleted_at')->get();
            foreach ($circles as $circle) {
                $bgId = data_get($circle->calendar, 'leadership.business_growth_committee_chair_user_id')
                    ?? data_get($circle->calendar, 'leadership.business_growth_committee_chair.id')
                    ?? data_get($circle->calendar, 'business_growth_committee_chair.id')
                    ?? ($circle->business_growth_committee_chair_user_id ?? null);
                if ($bgId && (string) $bgId === $userId) {
                    return [
                        'role' => 'chairBusinessGrowth',
                        'custom_role_label' => 'Chair - Business Growth Committee',
                        'regional_scope' => 'Own Circle',
                        'is_leader' => true,
                    ];
                }

                $mgId = data_get($circle->calendar, 'leadership.membership_growth_committee_chair_user_id')
                    ?? data_get($circle->calendar, 'leadership.membership_growth_committee_chair.id')
                    ?? data_get($circle->calendar, 'membership_growth_committee_chair.id')
                    ?? ($circle->membership_growth_committee_chair_user_id ?? null);
                if ($mgId && (string) $mgId === $userId) {
                    return [
                        'role' => 'chairMembership',
                        'custom_role_label' => 'Chair - Membership Committee',
                        'regional_scope' => 'Own Circle',
                        'is_leader' => true,
                    ];
                }

                $eiId = data_get($circle->calendar, 'leadership.events_impacts_committee_chair_user_id')
                    ?? data_get($circle->calendar, 'leadership.events_impacts_committee_chair.id')
                    ?? data_get($circle->calendar, 'events_impacts_committee_chair.id')
                    ?? ($circle->events_impacts_committee_chair_user_id ?? null);
                if ($eiId && (string) $eiId === $userId) {
                    return [
                        'role' => 'chairEventsPrograms',
                        'custom_role_label' => 'Chair - Events & Programs Committee',
                        'regional_scope' => 'Own Circle',
                        'is_leader' => true,
                    ];
                }

                $vcId = data_get($circle->calendar, 'leadership.vice_chair_user_id')
                    ?? ($circle->vice_chair_user_id ?? null);
                if ($vcId && (string) $vcId === $userId) {
                    return [
                        'role' => 'viceChair',
                        'custom_role_label' => 'Vice Chair',
                        'regional_scope' => 'Own Circle',
                        'is_leader' => true,
                    ];
                }

                $secId = data_get($circle->calendar, 'leadership.secretary_user_id')
                    ?? ($circle->secretary_user_id ?? null);
                if ($secId && (string) $secId === $userId) {
                    return [
                        'role' => 'secretary',
                        'custom_role_label' => 'Secretary',
                        'regional_scope' => 'Own Circle',
                        'is_leader' => true,
                    ];
                }

                if ((string) $circle->chair_user_id === $userId || (string) data_get($circle->calendar, 'leadership.chair_user_id') === $userId) {
                    return [
                        'role' => 'chairBusinessGrowth',
                        'custom_role_label' => 'Chair - Business Growth Committee',
                        'regional_scope' => 'Own Circle',
                        'is_leader' => true,
                    ];
                }
            }
        }

        // 3. Check circle_members role & roleRef
        if (Schema::hasTable('circle_members')) {
            $circleMembers = DB::table('circle_members')
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->get();

            foreach ($circleMembers as $cm) {
                $rawRole = (string) ($cm->role ?? '');
                $refRoleKey = '';
                if (! empty($cm->role_id) && Schema::hasTable('roles')) {
                    $refRoleKey = (string) (DB::table('roles')->where('id', $cm->role_id)->value('key') ?? '');
                }

                $targetRole = $this->isLeaderRole($rawRole) ? $rawRole : $refRoleKey;
                if ($this->isLeaderRole($targetRole)) {
                    $normalized = $this->normalizeRoleKey($targetRole);

                    return [
                        'role' => $normalized,
                        'custom_role_label' => $this->getRoleLabel($normalized),
                        'regional_scope' => $this->resolveRegionalScope($normalized),
                        'is_leader' => true,
                    ];
                }
            }
        }

        // 4. Check District / Industry Director dedicated tables
        if (Schema::hasTable('admin_ded_districts') && DB::table('admin_ded_districts')->where(function ($q) use ($userId, $adminUserIds) {
            $q->where('user_id', $userId)->orWhereIn('admin_user_id', $adminUserIds);
        })->exists()) {
            return [
                'role' => 'districtExecDirector',
                'custom_role_label' => 'District Exec Director',
                'regional_scope' => 'District Scope',
                'is_leader' => true,
            ];
        }

        if (Schema::hasTable('industry_director_assignments') && DB::table('industry_director_assignments')->where('is_active', true)->whereIn('admin_user_id', $adminUserIds)->exists()) {
            return [
                'role' => 'industryDirector',
                'custom_role_label' => 'Industry Director',
                'regional_scope' => 'Industry Scope',
                'is_leader' => true,
            ];
        }

        // Non-leader: user has no assigned leadership role
        return [
            'role' => 'member',
            'custom_role_label' => 'Peer Member',
            'regional_scope' => 'Own Circle',
            'is_leader' => false,
        ];
    }

    /**
     * Resolve regional scope string.
     */
    public function resolveRegionalScope(string $role): string
    {
        return match ($role) {
            'superAdmin' => 'Global Scope',
            'countryDirector' => 'Country Scope',
            'districtExecDirector' => 'District Scope',
            'industryDirector' => 'Industry Scope',
            default => 'Own Circle',
        };
    }

    /**
     * Get enabled capability IDs for a given role key.
     *
     * @return array<string>
     */
    public function getEnabledCapabilitiesForRole(string $roleKey): array
    {
        $defaults = $this->getDefaultRoleCapabilities();

        // 1. Check if overrides exist in leader_role_capabilities table
        if (Schema::hasTable('leader_role_capabilities')) {
            try {
                $overrides = LeaderRoleCapability::query()
                    ->where('role_key', $roleKey)
                    ->get();

                if ($overrides->isNotEmpty()) {
                    return $overrides->where('is_enabled', true)->pluck('capability_id')->values()->all();
                }
            } catch (\Throwable) {
                // Ignore and fall through
            }
        }

        // 2. Check if dynamic RBAC data exists in role_module_access / role_page_permissions
        if (Schema::hasTable('roles')) {
            try {
                $role = Str::isUuid($roleKey)
                    ? Role::query()->where('id', $roleKey)->first()
                    : Role::query()->where('key', $roleKey)->first();

                if ($role) {
                    $dynamicCaps = $this->resolveCapabilitiesFromDynamicRbac($role);
                    if (! empty($dynamicCaps)) {
                        return $dynamicCaps;
                    }
                }
            } catch (\Throwable) {
                // Ignore and fall through
            }
        }

        // 3. Fallback to standard role defaults
        return $defaults[$roleKey] ?? [
            'access_dashboard',
            'view_peers',
            'request_actions',
            'view_reports',
        ];
    }

    /**
     * Resolve capabilities from web Dynamic RBAC tables (role_module_access & role_page_permissions).
     *
     * @return array<string>
     */
    public function resolveCapabilitiesFromDynamicRbac(Role $role): array
    {
        $roleId = (string) $role->id;

        $hasModuleAccess = DB::table('role_module_access')->where('role_id', $roleId)->exists();
        $hasPagePerms = DB::table('role_page_permissions')->where('role_id', $roleId)->exists();

        if (! $hasModuleAccess && ! $hasPagePerms) {
            return [];
        }

        $visibleModuleSlugs = DB::table('role_module_access')
            ->join('admin_modules', 'role_module_access.module_id', '=', 'admin_modules.id')
            ->where('role_module_access.role_id', $roleId)
            ->where('role_module_access.is_visible', true)
            ->pluck('admin_modules.slug')
            ->all();

        $pagePermSlugs = DB::table('role_page_permissions')
            ->join('admin_pages', 'role_page_permissions.page_id', '=', 'admin_pages.id')
            ->join('permissions', 'role_page_permissions.permission_id', '=', 'permissions.id')
            ->where('role_page_permissions.role_id', $roleId)
            ->select('admin_pages.slug as page_slug', 'permissions.key as perm_key')
            ->get();

        $caps = [];

        if (in_array('dashboard', $visibleModuleSlugs, true) || $pagePermSlugs->where('page_slug', 'like', '%dashboard%')->isNotEmpty()) {
            $caps[] = 'access_dashboard';
        }

        if (in_array('circles', $visibleModuleSlugs, true) || in_array('teams', $visibleModuleSlugs, true)) {
            $caps[] = 'access_teams';
        }

        if (in_array('finance-analytics', $visibleModuleSlugs, true) || in_array('finance', $visibleModuleSlugs, true)) {
            $caps[] = 'access_finance';
            $caps[] = 'manage_finance';
        }

        if (in_array('members', $visibleModuleSlugs, true) || in_array('peers', $visibleModuleSlugs, true)) {
            $caps[] = 'view_peers';
            if ($pagePermSlugs->whereIn('perm_key', ['create', 'edit', 'delete'])->isNotEmpty()) {
                $caps[] = 'manage_peers';
            }
        }

        if (in_array('activities', $visibleModuleSlugs, true)) {
            $caps[] = 'request_actions';
        }

        if (in_array('referral-report', $visibleModuleSlugs, true) || in_array('reports', $visibleModuleSlugs, true)) {
            $caps[] = 'view_reports';
        }

        if (in_array('coins', $visibleModuleSlugs, true)) {
            $caps[] = 'coin_payouts';
        }

        if (in_array('role-management', $visibleModuleSlugs, true)) {
            $caps[] = 'manage_roles';
        }

        if (in_array('settings', $visibleModuleSlugs, true)) {
            $caps[] = 'system_configs';
        }

        // Check data scope
        $hasRegionalScope = DB::table('role_data_scope')
            ->where('role_id', $roleId)
            ->where('scope_type', '!=', 'circle')
            ->exists();

        if ($hasRegionalScope || in_array($role->key, ['global_admin', 'eed', 'ded', 'industry_director', 'countryDirector', 'districtExecDirector', 'industryDirector', 'superAdmin'], true)) {
            $caps[] = 'regional_data';
        }

        return array_values(array_unique($caps));
    }

    /**
     * Build the 21-Flag Dynamic Permission Matrix.
     *
     * @return array<string, bool>
     */
    public function resolvePermissionMatrix(string $role): array
    {
        $enabledCapabilities = $this->getEnabledCapabilitiesForRole($role);

        $hasCap = fn (string $cap): bool => in_array($cap, $enabledCapabilities, true);

        return [
            'enabled_capabilities' => array_values($enabledCapabilities),
            'can_access_dashboard' => $hasCap('access_dashboard'),
            'can_view_overall_revenue' => $hasCap('access_finance'),
            'can_review_pending_peers' => $hasCap('access_teams') || in_array($role, ['chairBusinessGrowth', 'chairMembership', 'chairEventsPrograms', 'circleChair', 'circleFounder', 'circleDirector', 'districtExecDirector'], true),
            'can_access_peers_tab' => $hasCap('view_peers'),
            'can_add_edit_peer' => $hasCap('manage_peers') || in_array($role, ['circleFounder', 'circleDirector', 'districtExecDirector', 'countryDirector', 'superAdmin'], true),
            'can_send_wishes' => $hasCap('request_actions') || true,
            'can_view_peer_profile' => $hasCap('view_peers'),
            'can_view_peer_contact_info' => $hasCap('view_peers'),
            'can_access_teams_tab' => $hasCap('access_teams'),
            'can_manage_circles' => $hasCap('access_teams'),
            'can_assign_circle_chair' => $hasCap('access_teams') && in_array($role, ['circleFounder', 'circleDirector', 'districtExecDirector', 'countryDirector', 'superAdmin'], true),
            'can_access_finance_tab' => $hasCap('access_finance'),
            'can_modify_finance_settings' => $hasCap('manage_finance') || in_array($role, ['districtExecDirector', 'countryDirector', 'superAdmin'], true),
            'can_issue_coins' => $hasCap('coin_payouts') || in_array($role, ['countryDirector', 'superAdmin'], true),
            'can_access_reports_tab' => $hasCap('view_reports'),
            'can_submit_reports' => $hasCap('view_reports') && in_array($role, ['chairBusinessGrowth', 'chairMembership', 'chairEventsPrograms', 'circleChair', 'circleFounder', 'circleDirector'], true),
            'can_export_peer_data' => $hasCap('view_peers') && in_array($role, ['circleFounder', 'circleDirector', 'industryDirector', 'districtExecDirector', 'countryDirector', 'superAdmin'], true),
            'can_export_financial_data' => $hasCap('access_finance') && in_array($role, ['districtExecDirector', 'countryDirector', 'superAdmin'], true),
            'can_export_global_data' => $hasCap('regional_data') && in_array($role, ['superAdmin'], true),
            'can_access_role_management' => $hasCap('manage_roles') || in_array($role, ['superAdmin'], true),
            'can_view_regional_scope' => $hasCap('regional_data') || in_array($role, ['industryDirector', 'districtExecDirector', 'countryDirector', 'superAdmin'], true),
        ];
    }

    /**
     * Get user's managed / active circles.
     *
     * @return array<int, array{id: string, name: string, location: string, category: string}>
     */
    public function resolveManagedCircles(User $user, string $role): array
    {
        $userId = (string) $user->id;

        $query = Circle::query()->whereNull('deleted_at');

        if ($role === 'superAdmin' || $role === 'countryDirector') {
            $joinedCircles = $query->where(function ($q) use ($userId): void {
                $q->where('chair_user_id', $userId)
                    ->orWhere('vice_chair_user_id', $userId)
                    ->orWhere('circle_founder_user_id', $userId)
                    ->orWhere('founder_user_id', $userId)
                    ->orWhere('circle_director_user_id', $userId)
                    ->orWhere('director_user_id', $userId)
                    ->orWhereHas('members', fn ($mq) => $mq->where('user_id', $userId)->whereNull('deleted_at')->where('status', '!=', 'rejected'));
            })->get();

            $circles = $joinedCircles;
        } elseif ($role === 'districtExecDirector') {
            $admin = AdminUser::query()->where('id', $userId)->orWhere('email', $user->email)->first();
            $circleIds = $admin ? AdminCircleScope::getDedCircleIds($admin) : [];

            if (! empty($circleIds)) {
                $circles = $query->whereIn('id', $circleIds)->get();
            } else {
                $assignedDistrictId = DB::table('admin_ded_districts')
                    ->where('admin_user_id', $userId)
                    ->orWhere('user_id', $userId)
                    ->value('district_id');

                $circles = $query->where(function ($q) use ($userId, $assignedDistrictId): void {
                    $q->where('ded_user_id', $userId);
                    if ($assignedDistrictId) {
                        $q->orWhere('district_id', $assignedDistrictId);
                    }
                })->get();
            }
        } elseif ($role === 'industryDirector') {
            $circles = $query->where('industry_director_user_id', $userId)
                ->orWhere('chair_user_id', $userId)
                ->get();
        } else {
            $circles = $query->where('chair_user_id', $userId)
                ->orWhere('circle_founder_user_id', $userId)
                ->orWhere('founder_user_id', $userId)
                ->orWhere('circle_director_user_id', $userId)
                ->orWhere('director_user_id', $userId)
                ->orWhereHas('members', fn ($q) => $q->where('user_id', $userId)->whereNull('deleted_at')->where('status', '!=', 'rejected'))
                ->get();
        }

        return $circles->map(function (Circle $c): array {
            $loc = $c->city?->name ?? $c->location ?? 'Mumbai';
            $category = $c->circleCategory?->name ?? 'Technology';

            return [
                'id' => (string) $c->id,
                'name' => (string) $c->name,
                'location' => (string) $loc,
                'category' => (string) $category,
            ];
        })->values()->all();
    }
}
