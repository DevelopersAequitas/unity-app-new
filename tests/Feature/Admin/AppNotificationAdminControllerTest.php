<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppNotificationAdminControllerTest extends TestCase
{
    use DatabaseTransactions;

    private AdminUser $admin;

    private User $peer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestSchemas();

        // Create admin user with super-admin role
        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'id' => $roleId,
            'name' => 'Super Admin',
            'key' => 'global_admin',
            'slug' => 'global-admin',
            'is_system' => true,
        ]);

        $this->admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Global Admin',
            'email' => 'admin_app_notif_'.uniqid().'@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        DB::table('admin_user_roles')->insert([
            'user_id' => $this->admin->id,
            'role_id' => $roleId,
        ]);

        // Create test peer user
        $this->peer = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'johndoe_'.uniqid().'@example.test',
            'phone' => '9876543210',
            'status' => 'active',
        ]);
    }

    private function createTestSchemas(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('display_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('mobile')->nullable();
                $table->string('company_name')->nullable();
                $table->string('status')->default('active');
                $table->string('android_fcm_token')->nullable();
                $table->string('ios_fcm_token')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('key')->nullable();
                $table->string('slug')->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->uuid('role_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_user_roles')) {
            Schema::create('admin_user_roles', function (Blueprint $table): void {
                $table->uuid('user_id');
                $table->uuid('role_id');
                $table->primary(['user_id', 'role_id']);
            });
        }

        if (! Schema::hasTable('notification_templates')) {
            Schema::create('notification_templates', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('template_key')->unique();
                $table->string('name');
                $table->string('title_template');
                $table->text('body_template');
                $table->text('default_payload')->nullable();
                $table->text('dynamic_params')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->uuid('campaign_id')->nullable();
                $table->string('type')->nullable();
                $table->string('category')->nullable();
                $table->string('title')->nullable();
                $table->text('body')->nullable();
                $table->string('channel')->nullable();
                $table->string('priority')->nullable();
                $table->string('reference_type')->nullable();
                $table->uuid('reference_id')->nullable();
                $table->string('screen')->nullable();
                $table->text('data')->nullable();
                $table->string('dedupe_key')->nullable();
                $table->string('status')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('clicked_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notification_delivery_logs')) {
            Schema::create('notification_delivery_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('notification_id')->nullable();
                $table->uuid('user_id')->nullable();
                $table->uuid('campaign_id')->nullable();
                $table->string('channel')->nullable();
                $table->string('provider')->nullable();
                $table->string('provider_message_id')->nullable();
                $table->string('status')->nullable();
                $table->text('request_payload')->nullable();
                $table->text('response_payload')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('attempted_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_push_tokens')) {
            Schema::create('user_push_tokens', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->string('token')->nullable();
                $table->string('platform')->nullable();
                $table->string('device_id')->nullable();
                $table->string('app_version')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('last_update_notification_sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->string('failure_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('circles')) {
            Schema::create('circles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('circle_members')) {
            Schema::create('circle_members', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('circle_id');
                $table->uuid('user_id');
                $table->string('role')->default('member');
                $table->string('status')->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function test_admin_can_access_app_notifications_index(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.app-notifications.index'));

        $response->assertStatus(200);
        $response->assertSee('App Notifications & Mobile Navigation');
        $response->assertSee('Send All App Notifications to Selected Peer');
    }

    public function test_admin_can_search_peers_via_ajax(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.app-notifications.peers-search', ['q' => 'John']));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'results' => [
                '*' => ['id', 'name', 'email', 'phone', 'circle', 'push_ready', 'tokens_count'],
            ],
            'pagination' => ['more'],
        ]);
        $response->assertJsonFragment(['name' => 'John Doe']);
    }

    public function test_admin_can_fetch_peer_details(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.app-notifications.peer-details', ['id' => $this->peer->id]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'peer' => [
                'id' => (string) $this->peer->id,
                'name' => 'John Doe',
            ],
        ]);
    }

    public function test_admin_can_preview_notification_for_peer(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.app-notifications.preview', [
                'key' => 'welcome_notification',
                'peer_id' => $this->peer->id,
            ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'key' => 'welcome_notification',
            'navigation_screen' => '/dashboard',
        ]);
    }

    public function test_admin_can_dynamically_store_new_notification_type(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.app-notifications.store'), [
                'name' => 'New Flash Deal',
                'template_key' => 'flash_deal_alert',
                'category' => 'Offers & Deals',
                'navigation_screen' => '/my-offers',
                'title_template' => 'Flash Deal for {name}!',
                'body_template' => 'Get 50% discount on annual passes today.',
                'default_payload' => json_encode(['deal_id' => '123']),
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'notification' => [
                'key' => 'flash_deal_alert',
                'navigation_screen' => '/my-offers',
            ],
        ]);

        $this->assertDatabaseHas('notification_templates', [
            'template_key' => 'flash_deal_alert',
            'name' => 'New Flash Deal',
        ]);
    }

    public function test_admin_can_send_notification_to_peer(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.app-notifications.send'), [
                'user_ids' => [$this->peer->id],
                'notification_key' => 'welcome_notification',
                'title' => 'Welcome {name}!',
                'body' => 'Welcome to Peers Global platform.',
                'navigation_screen' => '/dashboard',
                'channel' => 'push',
                'priority' => 'high',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'total_targeted' => 1,
            'success_count' => 1,
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->peer->id,
            'type' => 'welcome_notification',
        ]);
    }

    public function test_admin_can_send_all_notifications_to_peer(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.app-notifications.send-all-to-peer'), [
                'user_id' => $this->peer->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'message',
            'total_sent',
            'dispatched_notifications',
        ]);
    }
}
