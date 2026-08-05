<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\RoleHierarchy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleHierarchySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure all standard roles exist with proper details
        $standardRoles = [
            'global_admin' => ['name' => 'Global Admin',               'role_type' => 'system', 'scope_rule' => 'not_applicable'],
            'ded' => ['name' => 'District Executive Director', 'role_type' => 'admin',  'scope_rule' => 'mandatory'],
            'eed' => ['name' => 'Executive Director',          'role_type' => 'admin',  'scope_rule' => 'mandatory'],
            'industry_director' => ['name' => 'Industry Director',           'role_type' => 'admin',  'scope_rule' => 'optional'],
            'circle_leader' => ['name' => 'Circle Leader',               'role_type' => 'admin',  'scope_rule' => 'optional'],
            'founder' => ['name' => 'Circle Founder',              'role_type' => 'admin',  'scope_rule' => 'optional'],
            'director' => ['name' => 'Circle Director',             'role_type' => 'admin',  'scope_rule' => 'optional'],
            'chair' => ['name' => 'Circle Chair',                'role_type' => 'user',   'scope_rule' => 'optional'],
            'vice_chair' => ['name' => 'Vice Chair',                  'role_type' => 'user',   'scope_rule' => 'optional'],
            'secretary' => ['name' => 'Secretary',                   'role_type' => 'user',   'scope_rule' => 'optional'],
            'committee_leader' => ['name' => 'Committee Leader',            'role_type' => 'user',   'scope_rule' => 'optional'],
            'member' => ['name' => 'Circle Member',               'role_type' => 'user',   'scope_rule' => 'not_applicable'],
            'marketing_team' => ['name' => 'Marketing Team',              'role_type' => 'admin',  'scope_rule' => 'not_applicable'],
            'analytics_team' => ['name' => 'Analytics Team',              'role_type' => 'admin',  'scope_rule' => 'not_applicable'],
            'content_team' => ['name' => 'Content Team',                'role_type' => 'admin',  'scope_rule' => 'not_applicable'],
            'read_only' => ['name' => 'Read Only Staff',             'role_type' => 'admin',  'scope_rule' => 'not_applicable'],
        ];

        $roleModels = [];
        foreach ($standardRoles as $key => $meta) {
            $roleModels[$key] = Role::firstOrCreate(
                ['key' => $key],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $meta['name'],
                    'description' => $meta['name'].' Role',
                    'role_type' => $meta['role_type'],
                    'scope_rule' => $meta['scope_rule'],
                    'status' => 'active',
                    'is_assignable' => true,
                    'role_code' => $key,
                    'hierarchy_depth' => 0,
                ]
            );
        }

        // 2. Define parent -> child relationships for full tree Role Hierarchy
        $hierarchyTree = [
            'global_admin' => [
                'ded',
                'eed',
                'marketing_team',
                'analytics_team',
                'content_team',
                'read_only',
            ],
            'ded' => [
                'industry_director',
            ],
            'eed' => [
                'industry_director',
            ],
            'industry_director' => [
                'circle_leader',
            ],
            'circle_leader' => [
                'founder',
                'director',
            ],
            'founder' => [
                'chair',
            ],
            'director' => [
                'chair',
            ],
            'chair' => [
                'vice_chair',
            ],
            'vice_chair' => [
                'secretary',
            ],
            'secretary' => [
                'committee_leader',
            ],
            'committee_leader' => [
                'member',
            ],
        ];

        foreach ($hierarchyTree as $parentKey => $childrenKeys) {
            $parentRole = $roleModels[$parentKey] ?? Role::where('key', $parentKey)->first();
            if (! $parentRole) {
                continue;
            }

            foreach ($childrenKeys as $childKey) {
                $childRole = $roleModels[$childKey] ?? Role::where('key', $childKey)->first();
                if (! $childRole) {
                    continue;
                }

                RoleHierarchy::firstOrCreate([
                    'parent_role_id' => $parentRole->id,
                    'child_role_id' => $childRole->id,
                ]);
            }
        }

        // 3. Recompute depths for root roles
        $rootRoles = Role::where('key', 'global_admin')->get();
        foreach ($rootRoles as $root) {
            $root->recomputeDepth();
        }
    }
}
