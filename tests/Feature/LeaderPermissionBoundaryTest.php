<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Leader\Services\LeaderPermissionService;
use App\Models\AdminUser;
use App\Models\User;
use App\Shared\Services\UserRoleResolverService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaderPermissionBoundaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    private function setUpDatabase(): void
    {
        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('circles');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->nullable()->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });
    }

    public function test_shared_user_role_resolver_detects_super_admin(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'email' => 'admin@unity.com',
            'first_name' => 'Super',
            'last_name' => 'Admin',
        ]);

        AdminUser::create([
            'id' => $user->id,
            'email' => $user->email,
            'name' => 'Super Admin',
            'role' => 'super_admin',
        ]);

        $resolver = app(UserRoleResolverService::class);
        $roleInfo = $resolver->resolveUserRole($user);

        $this->assertEquals('superAdmin', $roleInfo['role']);
        $this->assertTrue($roleInfo['is_leader']);
        $this->assertEquals('Global Scope', $roleInfo['regional_scope']);
        $this->assertTrue($resolver->isLeader($user));
    }

    public function test_leader_permission_service_delegates_to_shared_resolver(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'email' => 'ded@unity.com',
            'first_name' => 'District',
            'last_name' => 'Director',
        ]);
        $user->role = 'district_exec_director';
        $user->save();

        $leaderService = app(LeaderPermissionService::class);
        $roleInfo = $leaderService->resolveUserRole($user);

        $this->assertEquals('districtExecDirector', $roleInfo['role']);
        $this->assertTrue($roleInfo['is_leader']);
        $this->assertTrue($leaderService->isLeader($user));
        $this->assertTrue($leaderService->isLeaderRole('district_exec_director'));
        $this->assertEquals('districtExecDirector', $leaderService->normalizeRoleKey('ded'));
    }

    public function test_leader_permission_service_resolves_matrix_and_capabilities(): void
    {
        $leaderService = app(LeaderPermissionService::class);

        $metadata = $leaderService->getCapabilitiesMetadata();
        $this->assertCount(12, $metadata);

        $matrix = $leaderService->resolvePermissionMatrix('superAdmin');
        $this->assertTrue($matrix['can_access_dashboard']);
        $this->assertTrue($matrix['can_access_role_management']);
        $this->assertTrue($matrix['can_view_regional_scope']);

        $memberMatrix = $leaderService->resolvePermissionMatrix('member');
        $this->assertFalse($memberMatrix['can_access_role_management']);
    }
}
