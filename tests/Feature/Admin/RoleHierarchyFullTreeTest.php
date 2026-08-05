<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Role;
use App\Models\RoleHierarchy;
use Database\Seeders\RoleHierarchySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoleHierarchyFullTreeTest extends TestCase
{
    use DatabaseTransactions;

    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleHierarchySeeder::class);

        $this->admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Super Admin',
            'email' => 'admin.hierarchy@example.com',
        ]);

        $globalAdminRole = Role::where('key', 'global_admin')->firstOrFail();
        $this->admin->roles()->attach($globalAdminRole->id);
    }

    public function test_full_tree_hierarchy_seeding_and_depths(): void
    {
        $this->assertGreaterThanOrEqual(16, Role::count());
        $this->assertGreaterThanOrEqual(15, RoleHierarchy::count());

        $globalAdmin = Role::where('key', 'global_admin')->firstOrFail();
        $this->assertSame(0, $globalAdmin->hierarchy_depth);

        $ded = Role::where('key', 'ded')->firstOrFail();
        $this->assertSame(1, $ded->hierarchy_depth);

        $industryDirector = Role::where('key', 'industry_director')->firstOrFail();
        $this->assertSame(2, $industryDirector->hierarchy_depth);

        $circleLeader = Role::where('key', 'circle_leader')->firstOrFail();
        $this->assertSame(3, $circleLeader->hierarchy_depth);

        $member = Role::where('key', 'member')->firstOrFail();
        $this->assertGreaterThan(5, $member->hierarchy_depth);
    }

    public function test_hierarchy_tree_views_accessible_for_admin(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.rbac.hierarchy'));
        $response->assertStatus(200);
        $response->assertSee('Hierarchy Map');
        $response->assertSee('Global Admin');
        $response->assertSee('District Executive Director');

        $fullmapResponse = $this->actingAs($this->admin, 'admin')->get(route('admin.rbac.hierarchy.fullmap'));
        $fullmapResponse->assertStatus(200);
        $fullmapResponse->assertSee('Role Hierarchy Map');
        $fullmapResponse->assertSee('Live');
    }
}
