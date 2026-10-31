<?php

declare(strict_types=1);

namespace App\Shared\Authorization;

final class AccessConstants
{
    /**
     * Supported Organizational Scope Types (Phase 4.1).
     *
     * @var array<string, string>
     */
    public const SCOPE_TYPES = [
        'global' => 'Global / All Platform Data',
        'country' => 'Country-Wide Data',
        'district' => 'Assigned District(s)',
        'industry' => 'Assigned Industry Vertical(s)',
        'multi_circle' => 'Assigned Multiple Circles',
        'own_circle' => 'Single Assigned Circle',
        'own_committee' => 'Assigned Circle Committee',
        'self' => 'Own User Data Only',
        'none' => 'No Access',
    ];

    /**
     * Scope precedence rank (lower number = broader scope).
     *
     * @var array<string, int>
     */
    public const SCOPE_RANK = [
        'global' => 1,
        'country' => 2,
        'district' => 3,
        'industry' => 4,
        'multi_circle' => 5,
        'own_circle' => 6,
        'own_committee' => 7,
        'self' => 8,
        'none' => 99,
    ];

    /**
     * Supported Resource Keys (Phase 4.2).
     *
     * @var array<string, string>
     */
    public const RESOURCE_KEYS = [
        'peers' => 'Peers / Members',
        'circles' => 'Circles',
        'activities' => 'Unified Activities',
        'referrals' => 'Business Referrals',
        'p2p_meetings' => '1-on-1 P2P Meetings',
        'business_deals' => 'Closed Business Deals',
        'life_impacts' => 'Life Impacts',
        'coins' => 'Unity Coins & Ledger',
        'events' => 'Circle & Regional Events',
        'timeline_posts' => 'Timeline Posts & Creatives',
        'reports' => 'Reports & Analytics',
        'leadership_assignments' => 'Organizational & Leadership Assignments',
        'role_configuration' => 'Dynamic Role & Access Configuration',
    ];

    /**
     * Supported Action Keys (Phase 4.3).
     *
     * @var array<string, string>
     */
    public const ACTION_KEYS = [
        'view' => 'View Resource',
        'list' => 'List Records',
        'view_details' => 'View Detailed Record',
        'create' => 'Create Record',
        'update' => 'Update Record',
        'delete' => 'Delete Record',
        'approve' => 'Approve Submission',
        'reject' => 'Reject Submission',
        'assign' => 'Assign Role or Scope',
        'revoke' => 'Revoke Role or Scope',
        'export' => 'Export Data',
        'manage_configuration' => 'Manage Access Configuration',
    ];

    /**
     * Supported Field Masking Strategies (Phase 4.5).
     *
     * @var array<string, string>
     */
    public const MASK_STRATEGIES = [
        'none' => 'Show Full Value',
        'hidden' => 'Omit / Hide Field Completely',
        'partial_phone' => 'Mask Phone (e.g. +91******3210)',
        'partial_email' => 'Mask Email (e.g. ar***@domain.com)',
        'redacted' => 'Replace with [REDACTED]',
    ];

    /**
     * Default system roles metadata when database tables are not yet seeded.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaultRolesCatalog(): array
    {
        return [
            [
                'key' => 'global_admin',
                'name' => 'Global Admin',
                'description' => 'Full platform access across all modules and scopes',
                'hierarchy_level' => 1,
                'default_scope_type' => 'global',
                'is_system_role' => true,
                'is_leadership_role' => true,
                'is_admin_panel_role' => true,
                'is_assignable' => true,
                'status' => 'active',
            ],
            [
                'key' => 'super_admin',
                'name' => 'Super Admin',
                'description' => 'Full platform access across all modules and scopes',
                'hierarchy_level' => 1,
                'default_scope_type' => 'global',
                'is_system_role' => true,
                'is_leadership_role' => true,
                'is_admin_panel_role' => true,
                'is_assignable' => true,
                'status' => 'active',
            ],
            [
                'key' => 'country_director',
                'name' => 'Country Director (EED)',
                'description' => 'Country-wide executive leadership access',
                'hierarchy_level' => 10,
                'default_scope_type' => 'country',
                'is_system_role' => true,
                'is_leadership_role' => true,
                'is_admin_panel_role' => true,
                'is_assignable' => true,
                'status' => 'active',
            ],
            [
                'key' => 'ded',
                'name' => 'District Executive Director (DED)',
                'description' => 'District-wide leadership access across all circles and peers in assigned district(s)',
                'hierarchy_level' => 20,
                'default_scope_type' => 'district',
                'is_system_role' => true,
                'is_leadership_role' => true,
                'is_admin_panel_role' => true,
                'is_assignable' => true,
                'status' => 'active',
            ],
            [
                'key' => 'industry_director',
                'name' => 'Industry Director (ID)',
                'description' => 'Industry-wide leadership access across assigned industry vertical(s)',
                'hierarchy_level' => 30,
                'default_scope_type' => 'industry',
                'is_system_role' => true,
                'is_leadership_role' => true,
                'is_admin_panel_role' => true,
                'is_assignable' => true,
                'status' => 'active',
            ],
            [
                'key' => 'circle_founder',
                'name' => 'Circle Founder',
                'description' => 'Founder of one or more circles',
                'hierarchy_level' => 40,
                'default_scope_type' => 'multi_circle',
                'is_system_role' => true,
                'is_leadership_role' => true,
                'is_admin_panel_role' => true,
                'is_assignable' => true,
                'status' => 'active',
            ],
            [
                'key' => 'circle_director',
                'name' => 'Circle Director',
                'description' => 'Director of one or more circles',
                'hierarchy_level' => 45,
                'default_scope_type' => 'multi_circle',
                'is_system_role' => true,
                'is_leadership_role' => true,
                'is_admin_panel_role' => true,
                'is_assignable' => true,
                'status' => 'active',
            ],
            [
                'key' => 'chair',
                'name' => 'Circle Chair',
                'description' => 'Primary Chair of an assigned circle',
                'hierarchy_level' => 50,
                'default_scope_type' => 'own_circle',
                'is_system_role' => true,
                'is_leadership_role' => true,
                'is_admin_panel_role' => true,
                'is_assignable' => true,
                'status' => 'active',
            ],
            [
                'key' => 'chair_business_growth',
                'name' => 'Chair - Business Growth Committee',
                'description' => 'Circle Business Growth Committee Chair',
                'hierarchy_level' => 55,
                'default_scope_type' => 'own_circle',
                'is_system_role' => true,
                'is_leadership_role' => true,
                'is_admin_panel_role' => true,
                'is_assignable' => true,
                'status' => 'active',
            ],
            [
                'key' => 'chair_membership',
                'name' => 'Chair - Membership Growth Committee',
                'description' => 'Circle Membership Growth Committee Chair',
                'hierarchy_level' => 55,
                'default_scope_type' => 'own_circle',
                'is_system_role' => true,
                'is_leadership_role' => true,
                'is_admin_panel_role' => true,
                'is_assignable' => true,
                'status' => 'active',
            ],
            [
                'key' => 'chair_events_programs',
                'name' => 'Chair - Events & Impacts Committee',
                'description' => 'Circle Events & Impacts Committee Chair',
                'hierarchy_level' => 55,
                'default_scope_type' => 'own_circle',
                'is_system_role' => true,
                'is_leadership_role' => true,
                'is_admin_panel_role' => true,
                'is_assignable' => true,
                'status' => 'active',
            ],
            [
                'key' => 'vice_chair',
                'name' => 'Vice Chair',
                'description' => 'Circle Vice Chair',
                'hierarchy_level' => 60,
                'default_scope_type' => 'own_circle',
                'is_system_role' => true,
                'is_leadership_role' => true,
                'is_admin_panel_role' => true,
                'is_assignable' => true,
                'status' => 'active',
            ],
            [
                'key' => 'secretary',
                'name' => 'Secretary',
                'description' => 'Circle Secretary',
                'hierarchy_level' => 65,
                'default_scope_type' => 'own_circle',
                'is_system_role' => true,
                'is_leadership_role' => true,
                'is_admin_panel_role' => true,
                'is_assignable' => true,
                'status' => 'active',
            ],
            [
                'key' => 'member',
                'name' => 'Peer Member',
                'description' => 'Standard Peer Member (self scope)',
                'hierarchy_level' => 100,
                'default_scope_type' => 'self',
                'is_system_role' => true,
                'is_leadership_role' => false,
                'is_admin_panel_role' => false,
                'is_assignable' => true,
                'status' => 'active',
            ],
        ];
    }

    /**
     * Normalize role aliases across Admin Panel, Leader App, and Database keys.
     */
    public static function normalizeRoleKey(string $roleKey): string
    {
        $clean = strtolower(trim($roleKey));

        return match ($clean) {
            'superadmin', 'super_admin', 'global_admin', 'globaladmin' => 'global_admin',
            'countrydirector', 'country_director', 'eed' => 'country_director',
            'districtexecdirector', 'district_exec_director', 'district_executive_director', 'ded' => 'ded',
            'industrydirector', 'industry_director', 'id' => 'industry_director',
            'circlefounder', 'circle_founder', 'founder' => 'circle_founder',
            'circledirector', 'circle_director', 'director' => 'circle_director',
            'circlechair', 'circle_chair', 'chair' => 'chair',
            'chairbusinessgrowth', 'chair_business_growth', 'chair_business_growth_committee', 'business_growth_committee_chair' => 'chair_business_growth',
            'chairmembership', 'chair_membership', 'chair_membership_growth_committee', 'membership_growth_committee_chair' => 'chair_membership',
            'chaireventsprograms', 'chair_events_programs', 'chair_events_impacts_committee', 'events_impacts_committee_chair' => 'chair_events_programs',
            'vicechair', 'vice_chair', 'circle_vice_chair' => 'vice_chair',
            'circlesecretary', 'secretary' => 'secretary',
            default => str_replace(['-', ' '], '_', $clean),
        };
    }

    /**
     * Determine default scope type for a given normalized role key.
     */
    public static function defaultScopeForRole(string $roleKey): string
    {
        $normalized = self::normalizeRoleKey($roleKey);

        return match ($normalized) {
            'global_admin', 'super_admin' => 'global',
            'country_director' => 'country',
            'ded' => 'district',
            'industry_director' => 'industry',
            'circle_founder', 'circle_director' => 'multi_circle',
            'chair', 'chair_business_growth', 'chair_membership', 'chair_events_programs', 'vice_chair', 'secretary', 'committee_leader' => 'own_circle',
            'member' => 'self',
            default => 'own_circle',
        };
    }
}

