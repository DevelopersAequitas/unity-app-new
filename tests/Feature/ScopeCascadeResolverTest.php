<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\Role;
use App\Models\RoleHierarchy;
use App\Models\User;
use App\Support\ScopeCascadeResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScopeCascadeResolverTest extends TestCase
{
    use DatabaseTransactions;

    private Role $superAdminRole;

    private Role $dedRole;

    private Role $idRole;

    private Role $cdRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist for test purposes
        $this->superAdminRole = Role::firstOrCreate(
            ['key' => 'global_founder'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Global Founder',
                'role_type' => 'system',
                'scope_rule' => 'not_applicable',
                'status' => 'active',
                'is_assignable' => true,
                'role_code' => 'global_founder',
                'hierarchy_depth' => 0,
            ]
        );

        $this->dedRole = Role::firstOrCreate(
            ['key' => 'ded'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'District Executive Director',
                'role_type' => 'admin',
                'scope_rule' => 'mandatory',
                'status' => 'active',
                'is_assignable' => true,
                'role_code' => 'ded',
                'hierarchy_depth' => 1,
            ]
        );

        $this->idRole = Role::firstOrCreate(
            ['key' => 'id'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Industry Director',
                'role_type' => 'admin',
                'scope_rule' => 'optional',
                'status' => 'active',
                'is_assignable' => true,
                'role_code' => 'id',
                'hierarchy_depth' => 2,
            ]
        );

        $this->cdRole = Role::firstOrCreate(
            ['key' => 'cd'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Circle Director',
                'role_type' => 'admin',
                'scope_rule' => 'optional',
                'status' => 'active',
                'is_assignable' => true,
                'role_code' => 'cd',
                'hierarchy_depth' => 3,
            ]
        );

        // Build relationships
        RoleHierarchy::firstOrCreate([
            'parent_role_id' => $this->superAdminRole->id,
            'child_role_id' => $this->dedRole->id,
        ]);

        RoleHierarchy::firstOrCreate([
            'parent_role_id' => $this->dedRole->id,
            'child_role_id' => $this->idRole->id,
        ]);

        RoleHierarchy::firstOrCreate([
            'parent_role_id' => $this->idRole->id,
            'child_role_id' => $this->cdRole->id,
        ]);
    }

    public function test_super_admin_resolves_all_circles(): void
    {
        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Admin '.Str::random(4),
            'email' => 'admin.'.Str::random(8).'@example.com',
        ]);

        $admin->roles()->attach($this->superAdminRole->id);

        // Create test circles
        $founder = User::factory()->create([
            'email' => 'founder.'.Str::random(8).'@example.com',
            'status' => 'active',
        ]);

        $circle1 = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Circle A',
            'slug' => 'test-circle-a-'.Str::lower(Str::random(5)),
            'status' => 'active',
            'circle_founder_user_id' => $founder->id,
        ]);

        ScopeCascadeResolver::invalidateCache($admin->id);
        $window = ScopeCascadeResolver::resolveDataWindow($admin->id);

        $this->assertContains($circle1->id, $window);
    }

    public function test_circle_director_resolves_assigned_circles_only(): void
    {
        $appUser = User::factory()->create([
            'email' => 'member.'.Str::random(8).'@example.com',
            'status' => 'active',
        ]);

        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test CD Admin',
            'email' => $appUser->email,
        ]);

        $admin->roles()->attach($this->cdRole->id);

        $circle1 = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Circle B',
            'slug' => 'test-circle-b-'.Str::lower(Str::random(5)),
            'status' => 'active',
            'circle_founder_user_id' => $appUser->id,
        ]);

        $circle2 = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Circle C',
            'slug' => 'test-circle-c-'.Str::lower(Str::random(5)),
            'status' => 'active',
            'circle_founder_user_id' => $appUser->id,
        ]);

        // Assign appUser to circle1
        DB::table('circle_members')->insert([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle1->id,
            'user_id' => $appUser->id,
            'role' => 'director',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ScopeCascadeResolver::invalidateCache($admin->id);
        $window = ScopeCascadeResolver::resolveDataWindow($admin->id);

        $this->assertContains($circle1->id, $window);
        $this->assertNotContains($circle2->id, $window);
    }
}
