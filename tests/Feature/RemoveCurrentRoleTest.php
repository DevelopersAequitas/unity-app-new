<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\AdminAuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RemoveCurrentRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->post(route('admin.profile.remove-current-role'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_rejects_if_user_already_has_only_default_user_role(): void
    {
        $userId = (string) Str::uuid();

        // Create standard User record to satisfy foreign key constraint on admin_audit_logs if any triggers occur
        User::create([
            'id' => $userId,
            'email' => 'testuseronly@example.com',
            'first_name' => 'Test User',
            'last_name' => 'Only',
            'password_hash' => 'dummy_hash',
        ]);

        $admin = AdminUser::create([
            'id' => $userId,
            'name' => 'Test User Only',
            'email' => 'testuseronly@example.com',
        ]);

        $userRole = Role::firstOrCreate(
            ['key' => 'user'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'User',
            ]
        );

        $insertData = [
            'user_id' => $admin->id,
            'role_id' => $userRole->id,
        ];
        if (DB::connection()->getDriverName() === 'pgsql') {
            $insertData['id'] = (string) Str::uuid();
            $insertData['created_at'] = now();
        }
        DB::table('admin_user_roles')->insert($insertData);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.profile.remove-current-role'));

        $response->assertRedirect();
        $response->assertSessionHasErrors(['message']);
    }

    public function test_successful_role_removal_to_default_user(): void
    {
        $userId = (string) Str::uuid();

        // 1. Create standard User record
        User::create([
            'id' => $userId,
            'email' => 'testadmin@example.com',
            'first_name' => 'Test',
            'last_name' => 'Admin User',
            'password_hash' => 'dummy_hash',
        ]);

        // 2. Create admin user with same ID
        $admin = AdminUser::create([
            'id' => $userId,
            'name' => 'Test Admin User',
            'email' => 'testadmin@example.com',
        ]);

        // 3. Create roles
        $adminRole = Role::firstOrCreate(
            ['key' => 'global_admin'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Global Admin',
            ]
        );

        $userRole = Role::firstOrCreate(
            ['key' => 'user'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'User',
            ]
        );

        // 4. Assign global admin role
        $insertData = [
            'user_id' => $admin->id,
            'role_id' => $adminRole->id,
        ];
        if (DB::connection()->getDriverName() === 'pgsql') {
            $insertData['id'] = (string) Str::uuid();
            $insertData['created_at'] = now();
        }
        DB::table('admin_user_roles')->insert($insertData);

        // 5. Perform request
        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.profile.remove-current-role'));

        // 6. Assert redirection and logout
        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('status', 'Your role has been removed successfully. Your account has been changed to the default User role.');

        // 7. Assert DB state: only 'user' role is assigned to the user
        $assignedRoleKeys = Role::query()
            ->join('admin_user_roles', 'admin_user_roles.role_id', '=', 'roles.id')
            ->where('admin_user_roles.user_id', $admin->id)
            ->pluck('roles.key')
            ->toArray();

        $this->assertEquals(['user'], $assignedRoleKeys);

        // 8. Assert action logged in audit log
        $auditExists = AdminAuditLog::query()
            ->where('admin_user_id', $admin->id)
            ->where('action', 'admin.profile.remove_current_role')
            ->exists();

        $this->assertTrue($auditExists);
    }
}
