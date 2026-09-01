<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Role;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoleHierarchyPermissionsTest extends TestCase
{
    use DatabaseTransactions;

    private AdminUser $admin;

    private Role $testRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        // Ensure global_admin role exists
        Role::firstOrCreate(
            ['key' => 'global_admin'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Global Admin',
                'description' => 'Global Admin Role',
                'role_type' => 'system',
                'scope_rule' => 'none',
                'is_assignable' => true,
            ]
        );

        $this->admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Global Admin',
            'email' => 'admin.test@example.com',
        ]);

        $globalAdminRole = Role::where('key', 'global_admin')->firstOrFail();
        $this->admin->roles()->attach($globalAdminRole->id);

        $this->testRole = Role::create([
            'id' => (string) Str::uuid(),
            'key' => 'ded_test_role',
            'name' => 'DED Test Role',
            'description' => 'DED role for testing',
            'role_type' => 'admin',
            'scope_rule' => 'mandatory',
            'is_assignable' => true,
        ]);
    }

    public function test_assign_role_with_custom_permissions(): void
    {
        $peer = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Peer',
            'email' => 'peer.test@example.com',
        ]);

        $allowedSections = ['Dashboard', 'Peers', 'Coins'];
        $permissionType = 'view';

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.rbac.roles.assign'), [
                'admin_user_id' => $peer->id,
                'role_id' => $this->testRole->id,
                'scope_id' => null,
                'allowed_sections' => $allowedSections,
                'permission_type' => $permissionType,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('admin_user_roles', [
            'user_id' => $peer->id,
            'role_id' => $this->testRole->id,
            'permission_type' => $permissionType,
        ]);

        $assignment = DB::table('admin_user_roles')
            ->where('user_id', $peer->id)
            ->where('role_id', $this->testRole->id)
            ->first();

        $this->assertNotNull($assignment);
        $this->assertNotNull($assignment->allowed_sections);

        $decoded = json_decode((string) $assignment->allowed_sections, true);
        $this->assertEquals($allowedSections, $decoded);

        // Assert isSectionAllowed helpers return correct values
        $this->assertTrue(AdminAccess::isSectionAllowed($peer, 'Dashboard'));
        $this->assertTrue(AdminAccess::isSectionAllowed($peer, 'Peers'));
        $this->assertFalse(AdminAccess::isSectionAllowed($peer, 'Circles')); // Not allowed

        // Assert isEditAllowed returns false for read-only user
        $this->assertFalse(AdminAccess::isEditAllowed($peer));
    }

    public function test_view_only_user_cannot_perform_mutations(): void
    {
        $readOnlyUser = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Read Only User',
            'email' => 'readonly.test@example.com',
        ]);

        // Assign a role as view-only
        DB::table('admin_user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $readOnlyUser->id,
            'role_id' => $this->testRole->id,
            'allowed_sections' => json_encode(['Dashboard']),
            'permission_type' => 'view',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempting to store a role as a read-only admin user should fail with 403
        $response = $this->actingAs($readOnlyUser, 'admin')
            ->post(route('admin.rbac.roles.store'), [
                'name' => 'Should Not Work',
                'key' => 'should_not_work',
                'role_type' => 'user',
                'scope_rule' => 'optional',
            ]);

        $response->assertStatus(403);
    }
}
