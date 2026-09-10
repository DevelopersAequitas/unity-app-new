<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CircleJoinAutoApprovalTest extends TestCase
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
            $table->uuid('active_circle_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->uuid('circle_founder_user_id')->nullable();
            $table->text('calendar')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id');
            $table->uuid('user_id');
            $table->string('role')->default('member');
            $table->string('status')->default('pending');
            $table->timestamp('joined_at')->nullable();
            $table->integer('substitute_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_super_admin_join_circle_is_auto_approved(): void
    {
        $superAdminUser = User::create([
            'id' => (string) Str::uuid(),
            'email' => 'superadmin@example.com',
            'first_name' => 'Super',
            'last_name' => 'Admin',
        ]);

        AdminUser::create([
            'id' => $superAdminUser->id,
            'email' => $superAdminUser->email,
            'name' => 'Super Admin',
            'role' => 'super_admin',
        ]);

        $circle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Global Leadership Circle',
            'circle_founder_user_id' => (string) Str::uuid(),
        ]);

        $response = $this->actingAs($superAdminUser, 'sanctum')
            ->postJson("/api/v1/circles/{$circle->id}/join");

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('circle_members', [
            'circle_id' => $circle->id,
            'user_id' => $superAdminUser->id,
            'status' => 'approved',
        ]);
    }

    public function test_country_director_join_circle_is_auto_approved(): void
    {
        $countryDirectorUser = User::create([
            'id' => (string) Str::uuid(),
            'email' => 'countrydirector@example.com',
            'first_name' => 'Country',
            'last_name' => 'Director',
        ]);
        $countryDirectorUser->role = 'country_director';
        $countryDirectorUser->save();

        $circle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'National Circle',
            'circle_founder_user_id' => (string) Str::uuid(),
        ]);

        $response = $this->actingAs($countryDirectorUser, 'sanctum')
            ->postJson("/api/v1/circles/{$circle->id}/join");

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('circle_members', [
            'circle_id' => $circle->id,
            'user_id' => $countryDirectorUser->id,
            'status' => 'approved',
        ]);
    }

    public function test_normal_member_join_circle_is_pending(): void
    {
        $normalUser = User::create([
            'id' => (string) Str::uuid(),
            'email' => 'normaluser@example.com',
            'first_name' => 'Normal',
            'last_name' => 'Peer',
        ]);

        $circle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Local Circle',
            'circle_founder_user_id' => (string) Str::uuid(),
        ]);

        $response = $this->actingAs($normalUser, 'sanctum')
            ->postJson("/api/v1/circles/{$circle->id}/join");

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('circle_members', [
            'circle_id' => $circle->id,
            'user_id' => $normalUser->id,
            'status' => 'pending',
        ]);
    }
}
