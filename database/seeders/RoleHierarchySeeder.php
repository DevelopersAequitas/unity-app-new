<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\RoleHierarchy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleHierarchySeeder extends Seeder
{
    /**
     * Run the database seeds to construct the full role hierarchy tree.
     */
    public function run(): void
    {
        // 1. Ensure all standard roles exist
        $standardRoles = [
            'global_admin' => ['name' => 'Global Admin', 'type' => 'admin', 'scope' => 'not_applicable'],
            'ded' => ['name' => 'DED',          'type' => 'admin', 'scope' => 'mandatory'],
            'industry_director' => ['name' => 'ID',      'type' => 'admin', 'scope' => 'optional'],
            'Circle Director' => ['name' => 'CD',      'type' => 'admin', 'scope' => 'optional'],
            'Circle Founder' => ['name' => 'CF',      'type' => 'admin', 'scope' => 'optional'],
            'Circle_Chair' => ['name' => 'Circle Chair', 'type' => 'user', 'scope' => 'optional'],
            'chair' => ['name' => 'Chair',    'type' => 'user', 'scope' => 'optional'],
            'vice_chair' => ['name' => 'Vice Chair', 'type' => 'user', 'scope' => 'optional'],
            'member' => ['name' => 'Member',   'type' => 'user', 'scope' => 'optional'],
            'user' => ['name' => 'User',     'type' => 'user', 'scope' => 'optional'],
        ];

        foreach ($standardRoles as $key => $meta) {
            Role::query()->firstOrCreate(
                ['key' => $key],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $meta['name'],
                    'role_type' => $meta['type'],
                    'scope_rule' => $meta['scope'],
                    'status' => 'active',
                    'is_assignable' => true,
                    'role_code' => $key,
                    'hierarchy_depth' => 0,
                ]
            );
        }

        $roles = Role::all()->keyBy('key');

        $gAdmin = $roles->get('global_admin')?->id;
        $ded = $roles->get('ded')?->id;
        $id = $roles->get('industry_director')?->id;
        $cd = $roles->get('Circle Director')?->id;
        $cf = $roles->get('Circle Founder')?->id;
        $cChair = $roles->get('Circle_Chair')?->id;
        $chair = $roles->get('chair')?->id;
        $vChair = $roles->get('vice_chair')?->id;
        $member = $roles->get('member')?->id;
        $user = $roles->get('user')?->id;

        // 2. Define standard tree links (Parent -> Child)
        $links = [
            ['parent' => $gAdmin, 'child' => $ded],
            ['parent' => $ded,    'child' => $id],
            ['parent' => $id,     'child' => $cd],
            ['parent' => $id,     'child' => $cf],
            ['parent' => $cd,     'child' => $cChair],
            ['parent' => $cf,     'child' => $cChair],
            ['parent' => $cd,     'child' => $chair],
            ['parent' => $cf,     'child' => $chair],
            ['parent' => $cChair, 'child' => $vChair],
            ['parent' => $chair,  'child' => $vChair],
            ['parent' => $vChair, 'child' => $member],
            ['parent' => $member, 'child' => $user],
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
