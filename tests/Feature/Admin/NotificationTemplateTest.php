<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\NotificationTemplate;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class NotificationTemplateTest extends TestCase
{
    use DatabaseTransactions;

    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->createTestSchemas();

        // Create admin user and assign super-admin role
        $roleId = (string) \Illuminate\Support\Str::uuid();
        DB::table('roles')->insert([
            'id' => $roleId,
            'name' => 'Super Admin',
            'key' => 'super-admin',
            'slug' => 'super-admin',
            'is_system' => true,
        ]);

        $this->admin = AdminUser::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin_notif_test_' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        
        DB::table('admin_user_roles')->insert([
            'user_id' => $this->admin->id,
            'role_id' => $roleId,
        ]);
    }

    private function createTestSchemas(): void
    {
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

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->string('email')->unique();
                $table->string('peer_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function test_admin_can_access_notification_templates_directory(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.notification-templates.index'));

        $response->assertStatus(200);
        $response->assertSee('All Available Notifications Lists');
    }

    public function test_admin_can_access_notification_template_editor(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.notification-templates.edit', 'welcome_notification'));

        $response->assertStatus(200);
        $response->assertSee('Notification Content Editor');
        $response->assertSee('Live Mobile Mockup Preview');
    }

    public function test_admin_can_update_notification_template(): void
    {
        NotificationTemplate::create([
            'template_key' => 'welcome_notification',
            'name' => 'Welcome Notification',
            'title_template' => 'Original Title',
            'body_template' => 'Original Body',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->put(route('admin.notification-templates.update', 'welcome_notification'), [
                'title_template' => 'Updated Welcome Title',
                'body_template' => 'Hello {name}, your account is fully approved.',
            ]);

        $response->assertRedirect(route('admin.notification-templates.edit', 'welcome_notification'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('notification_templates', [
            'template_key' => 'welcome_notification',
            'title_template' => 'Updated Welcome Title',
            'body_template' => 'Hello {name}, your account is fully approved.',
        ]);
    }
}
