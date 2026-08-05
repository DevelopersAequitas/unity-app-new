<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\AppVersion;
use App\Models\Role;
use App\Models\User;
use App\Models\UserMobileVersion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAppUpdatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users table for testing
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('password_hash')->nullable();
            $table->string('company_name')->nullable();
            $table->string('membership_status')->nullable();
            $table->integer('coins_balance')->default(0);
            $table->string('public_profile_slug')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        // Create roles table
        Schema::dropIfExists('roles');
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->timestamps();
        });

        // Create admin_users table
        Schema::dropIfExists('admin_users');
        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        // Create admin_user_roles table
        Schema::dropIfExists('admin_user_roles');
        Schema::create('admin_user_roles', function (Blueprint $table): void {
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->primary(['user_id', 'role_id']);
        });

        // Create app_versions table
        Schema::dropIfExists('app_versions');
        Schema::create('app_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('platform');
            $table->string('latest_version');
            $table->string('min_version');
            $table->string('update_type');
            $table->boolean('is_active')->default(true);
            $table->text('release_notes')->nullable();
            $table->timestamps();
        });

        // Create user_mobile_versions table
        Schema::dropIfExists('user_mobile_versions');
        Schema::create('user_mobile_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('platform');
            $table->string('app_version');
            $table->string('device_model')->nullable();
            $table->string('os_version')->nullable();
            $table->timestamps();
        });

        // Create user_push_tokens table
        Schema::dropIfExists('user_push_tokens');
        Schema::create('user_push_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token');
            $table->string('platform');
            $table->boolean('is_active')->default(true);
            $table->string('app_version')->nullable();
            $table->string('device_model')->nullable();
            $table->string('os_version')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        // Create notification_preferences table
        Schema::dropIfExists('notification_preferences');
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('chat_enabled')->default(true);
            $table->boolean('event_enabled')->default(true);
            $table->boolean('circle_enabled')->default(true);
            $table->boolean('business_enabled')->default(true);
            $table->boolean('campaign_enabled')->default(true);
            $table->string('quiet_hours_start')->nullable();
            $table->string('quiet_hours_end')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
        });

        // Create app_notifications table
        Schema::dropIfExists('app_notifications');
        Schema::create('app_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type');
            $table->string('category')->nullable();
            $table->string('title');
            $table->text('message')->nullable();
            $table->text('body');
            $table->json('data')->nullable();
            $table->json('payload')->nullable();
            $table->string('channel')->default('in_app');
            $table->string('priority')->default('medium');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('status')->default('sent');
            $table->uuid('campaign_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('screen')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->timestamps();
        });

        // Create notification_delivery_logs table
        Schema::dropIfExists('notification_delivery_logs');
        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('notification_id')->nullable();
            $table->uuid('campaign_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('channel')->nullable();
            $table->string('provider')->nullable();
            $table->string('status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // Create notification_suppression_logs table
        Schema::dropIfExists('notification_suppression_logs');
        Schema::create('notification_suppression_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type');
            $table->string('dedupe_key');
            $table->uuid('campaign_id')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->integer('send_count')->default(0);
            $table->timestamps();
        });
    }

    private function createAdminUser(): AdminUser
    {
        $role = Role::create([
            'id' => (string) Str::uuid(),
            'key' => 'global_admin',
            'name' => 'Global Admin',
        ]);

        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Administrator',
            'email' => 'admin@example.com',
        ]);

        $admin->roles()->attach($role->id);

        return $admin;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/app-updates');
        $response->assertRedirect('/admin/login');
    }

    public function test_admin_can_view_updates_manager_page(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        $response = $this->get('/admin/app-updates');
        $response->assertStatus(200);
        $response->assertSee('App Updates Manager');
        $response->assertSee('Android Config');
        $response->assertSee('iOS Config');
    }

    public function test_admin_can_save_settings(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        $payload = [
            'latest_version' => '1.9.0',
            'min_version' => '1.8.0',
            'update_type' => 'force',
            'is_active' => '1',
            'release_notes' => 'Major updates',
        ];

        $response = $this->post('/admin/app-updates/save/android', $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('app_versions', [
            'platform' => 'android',
            'latest_version' => '1.9.0',
            'min_version' => '1.8.0',
            'update_type' => 'force',
            'is_active' => true,
            'release_notes' => 'Major updates',
        ]);
    }

    public function test_admin_can_notify_selected_users(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        // Create sample user and mobile version record
        $user = User::factory()->create();
        UserMobileVersion::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'platform' => 'android',
            'app_version' => '1.8.0',
        ]);

        // Create config version which is higher (requires update)
        AppVersion::create([
            'id' => (string) Str::uuid(),
            'platform' => 'android',
            'latest_version' => '1.9.0',
            'min_version' => '1.8.0',
            'update_type' => 'optional',
            'is_active' => true,
        ]);

        $response = $this->postJson('/admin/app-updates/notify-selected', [
            'user_ids' => [$user->id],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }
}
