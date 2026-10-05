<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\RoleHierarchy;
use Illuminate\Database\Seeder;

class RoleHierarchySeeder extends Seeder
{
    /**
     * Run the database seeds to construct the full role hierarchy tree.
     */
    public function run(): void
    {
        // 1. Ensure all standard roles exist and are active
        $standardRoles = [
            'global_admin' => ['name' => 'Global Admin',      'type' => 'admin', 'scope' => 'not_applicable'],
            'ded' => ['name' => 'DED',               'type' => 'admin', 'scope' => 'mandatory'],
            'industry_director' => ['name' => 'Industry Director', 'type' => 'admin', 'scope' => 'optional'],
            'circle_leader' => ['name' => 'Circle Leader',     'type' => 'admin', 'scope' => 'mandatory'],
            'chair' => ['name' => 'Circle Chair',      'type' => 'admin', 'scope' => 'mandatory'],
            'vice_chair' => ['name' => 'Vice Chair',        'type' => 'admin', 'scope' => 'mandatory'],
            'secretary' => ['name' => 'Secretary',         'type' => 'admin', 'scope' => 'mandatory'],
            'committee_leader' => ['name' => 'Committee Leader',  'type' => 'admin', 'scope' => 'mandatory'],
            'member' => ['name' => 'Circle Member',     'type' => 'user',  'scope' => 'mandatory'],
        ];

        foreach ($standardRoles as $key => $meta) {
            Role::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $meta['name'],
                    'role_type' => $meta['type'],
                    'scope_rule' => $meta['scope'],
                    'status' => 'active',
                    'is_assignable' => true,
                    'role_code' => $key,
                ]
            );
        }

        // Also ensure any roles with null status are activated
        Role::query()->whereNull('status')->orWhere('status', '')->update(['status' => 'active']);

        $roles = Role::all()->keyBy('key');

        $gAdmin = $roles->get('global_admin')?->id;
        $ded = $roles->get('ded')?->id;
        $id = $roles->get('industry_director')?->id;
        $cLeader = $roles->get('circle_leader')?->id;
        $chair = $roles->get('chair')?->id;
        $vChair = $roles->get('vice_chair')?->id;
        $secretary = $roles->get('secretary')?->id;
        $commLeader = $roles->get('committee_leader')?->id;
        $member = $roles->get('member')?->id;

        // 2. Define standard tree links (Parent -> Child)
        $links = [
            ['parent' => $gAdmin,     'child' => $ded],
            ['parent' => $gAdmin,     'child' => $id],
            ['parent' => $ded,        'child' => $cLeader],
            ['parent' => $cLeader,    'child' => $chair],
            ['parent' => $chair,      'child' => $vChair],
            ['parent' => $chair,      'child' => $secretary],
            ['parent' => $vChair,     'child' => $commLeader],
            ['parent' => $commLeader, 'child' => $member],
        ];

        foreach ($links as $link) {
            if ($link['parent'] && $link['child']) {
                RoleHierarchy::firstOrCreate([
                    'parent_role_id' => $link['parent'],
                    'child_role_id' => $link['child'],
                ]);
            }
        }

        // 3. Recompute depths for all roles recursively
        foreach (Role::all() as $r) {
            $r->recomputeDepth();
        }
    }
}
