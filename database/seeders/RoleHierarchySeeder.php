<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\RoleHierarchy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleHierarchySeeder extends Seeder
{
    /**
     * Complete role hierarchy:
     *
     * Global Admin
     * ├── EED
     * │   └── DED
     * │       └── Industry Director
     * │           └── Circle Leader
     * │               ├── Circle Director
     * │               ├── Circle Founder
     * │               ├── Circle Chair
     * │               │   ├── Vice Chair
     * │               │   │   └── Committee Leader
     * │               │   │       ├── Marketing Team
     * │               │   │       ├── Analytics Team
     * │               │   │       ├── Content Team
     * │               │   │       └── Read Only Staff
     * │               │   └── Secretary
     * │               └── Circle Member
     * ├── Chair - Business Growth Committee  (USER)
     * ├── Chair - Membership Growth Committee (USER)
     * └── Chair - Events & Impacts Committee  (USER)
     */
    public function run(): void
    {
        // 1. Ensure all standard roles exist and are active
        $standardRoles = [
            // ── Top-level system ────────────────────────────────────────────
            'global_admin'                       => ['name' => 'Global Admin',                        'type' => 'admin', 'scope' => 'not_applicable'],

            // ── Governance tier ─────────────────────────────────────────────
            'eed'                                => ['name' => 'EED',                                 'type' => 'admin', 'scope' => 'mandatory'],
            'ded'                                => ['name' => 'DED',                                 'type' => 'admin', 'scope' => 'mandatory'],
            'industry_director'                  => ['name' => 'Industry Director',                   'type' => 'admin', 'scope' => 'optional'],

            // ── Circle leadership ────────────────────────────────────────────
            'circle_leader'                      => ['name' => 'Circle Leader',                       'type' => 'admin', 'scope' => 'mandatory'],
            'circle_director'                    => ['name' => 'Circle Director',                     'type' => 'admin', 'scope' => 'mandatory'],
            'circle_founder'                     => ['name' => 'Circle Founder',                      'type' => 'admin', 'scope' => 'mandatory'],
            'chair'                              => ['name' => 'Circle Chair',                        'type' => 'admin', 'scope' => 'mandatory'],
            'vice_chair'                         => ['name' => 'Vice Chair',                          'type' => 'admin', 'scope' => 'mandatory'],
            'secretary'                          => ['name' => 'Secretary',                           'type' => 'admin', 'scope' => 'mandatory'],

            // ── Committee tier ───────────────────────────────────────────────
            'committee_leader'                   => ['name' => 'Committee Leader',                    'type' => 'admin', 'scope' => 'mandatory'],
            'marketing_team'                     => ['name' => 'Marketing Team',                      'type' => 'admin', 'scope' => 'optional'],
            'analytics_team'                     => ['name' => 'Analytics Team',                      'type' => 'admin', 'scope' => 'optional'],
            'content_team'                       => ['name' => 'Content Team',                        'type' => 'admin', 'scope' => 'optional'],
            'read_only'                          => ['name' => 'Read Only Staff',                     'type' => 'admin', 'scope' => 'optional'],

            // ── Base member ──────────────────────────────────────────────────
            'member'                             => ['name' => 'Circle Member',                       'type' => 'user',  'scope' => 'mandatory'],

            // ── Committee chairs (USER-level, scoped to circle committees) ───
            'chair_business_growth_committee'    => ['name' => 'Chair - Business Growth Committee',   'type' => 'user',  'scope' => 'mandatory'],
            'chair_membership_growth_committee'  => ['name' => 'Chair - Membership Growth Committee', 'type' => 'user',  'scope' => 'mandatory'],
            'chair_events_impacts_committee'     => ['name' => 'Chair - Events & Impacts Committee',  'type' => 'user',  'scope' => 'mandatory'],
        ];

        DB::transaction(function () use ($standardRoles): void {
            foreach ($standardRoles as $key => $meta) {
                $existing = Role::query()->where('key', $key)->first();

                if ($existing) {
                    $existing->update([
                        'name'       => $meta['name'],
                        'role_type'  => $meta['type'],
                        'scope_rule' => $meta['scope'],
                        'status'     => 'active',
                        'is_assignable' => true,
                        'role_code'  => $key,
                    ]);
                } else {
                    Role::query()->create([
                        'id'         => (string) Str::uuid(),
                        'key'        => $key,
                        'name'       => $meta['name'],
                        'role_type'  => $meta['type'],
                        'scope_rule' => $meta['scope'],
                        'status'     => 'active',
                        'is_assignable' => true,
                        'role_code'  => $key,
                        'hierarchy_depth' => 0,
                    ]);
                }
            }

            // Activate any legacy roles with null / empty status
            Role::query()->whereNull('status')->orWhere('status', '')->update(['status' => 'active']);

            $roles = Role::all()->keyBy('key');

            // ── Resolve IDs ───────────────────────────────────────────────────
            $gAdmin       = $roles->get('global_admin')?->id;
            $eed          = $roles->get('eed')?->id;
            $ded          = $roles->get('ded')?->id;
            $indDir       = $roles->get('industry_director')?->id;
            $cLeader      = $roles->get('circle_leader')?->id;
            $cDirector    = $roles->get('circle_director')?->id;
            $cFounder     = $roles->get('circle_founder')?->id;
            $chair        = $roles->get('chair')?->id;
            $vChair       = $roles->get('vice_chair')?->id;
            $secretary    = $roles->get('secretary')?->id;
            $commLeader   = $roles->get('committee_leader')?->id;
            $marketing    = $roles->get('marketing_team')?->id;
            $analytics    = $roles->get('analytics_team')?->id;
            $content      = $roles->get('content_team')?->id;
            $readOnly     = $roles->get('read_only')?->id;
            $member       = $roles->get('member')?->id;
            $bgcChair     = $roles->get('chair_business_growth_committee')?->id;
            $mgcChair     = $roles->get('chair_membership_growth_committee')?->id;
            $eicChair     = $roles->get('chair_events_impacts_committee')?->id;

            // ── 2. Define the full hierarchy (Parent → Child) ─────────────────
            $links = [
                // Global Admin → governance
                ['parent' => $gAdmin,     'child' => $eed],
                ['parent' => $gAdmin,     'child' => $bgcChair],
                ['parent' => $gAdmin,     'child' => $mgcChair],
                ['parent' => $gAdmin,     'child' => $eicChair],

                // EED → DED
                ['parent' => $eed,        'child' => $ded],

                // DED → Industry Director
                ['parent' => $ded,        'child' => $indDir],

                // Industry Director → Circle Leader
                ['parent' => $indDir,     'child' => $cLeader],

                // Circle Leader → circle roles
                ['parent' => $cLeader,    'child' => $cDirector],
                ['parent' => $cLeader,    'child' => $cFounder],
                ['parent' => $cLeader,    'child' => $chair],
                ['parent' => $cLeader,    'child' => $member],

                // Chair → vice-chair & secretary
                ['parent' => $chair,      'child' => $vChair],
                ['parent' => $chair,      'child' => $secretary],

                // Vice Chair → Committee Leader
                ['parent' => $vChair,     'child' => $commLeader],

                // Committee Leader → team roles
                ['parent' => $commLeader, 'child' => $marketing],
                ['parent' => $commLeader, 'child' => $analytics],
                ['parent' => $commLeader, 'child' => $content],
                ['parent' => $commLeader, 'child' => $readOnly],
            ];

            // Clear all existing hierarchy links and rebuild clean
            DB::table('role_hierarchies')->truncate();

            foreach ($links as $link) {
                if ($link['parent'] && $link['child']) {
                    RoleHierarchy::create([
                        'parent_role_id' => $link['parent'],
                        'child_role_id'  => $link['child'],
                    ]);
                }
            }

            // 3. Recompute depths for all roles recursively
            foreach (Role::all() as $r) {
                $r->recomputeDepth();
            }
        });
    }
}
