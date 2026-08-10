<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\EmailTemplate;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use DatabaseTransactions;

    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->createTestSchemas();

        // Create admin user and assign super-admin/admin role
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
            'email' => 'admin_test_' . uniqid() . '@example.test',
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

        if (! Schema::hasTable('email_templates')) {
            Schema::create('email_templates', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('template_key')->unique();
                $table->string('name');
                $table->string('file_path');
                $table->string('subject')->nullable();
                $table->text('dynamic_params')->nullable();
                $table->text('custom_html')->nullable();
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

    public function test_admin_can_access_email_templates_directory(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.email-templates.index'));

        $response->assertStatus(200);
        $response->assertSee('All Available Email Lists');
    }

    public function test_admin_can_access_email_template_editor(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.email-templates.edit', 'welcome_peer'));

        $response->assertStatus(200);
        $response->assertSee('Simple Content Editor');
        $response->assertSee('HTML Code Editor');
    }

    public function test_admin_can_preview_email_template(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.email-templates.preview', 'welcome_peer'));

        $response->assertStatus(200);
        $response->assertSee('Welcome to Peers Global');
    }

    public function test_admin_can_update_email_template_in_simple_mode(): void
    {
        $filePath = resource_path('views/emails/welcome_peer.blade.php');
        $originalContent = File::get($filePath);

        try {
            $response = $this->actingAs($this->admin, 'admin')
                ->put(route('admin.email-templates.update', 'welcome_peer'), [
                    'mode' => 'simple',
                    'subject' => 'New Welcome Peer Subject',
                    'simple_content' => '<p>This is a modified simple welcome message</p>',
                ]);

            $response->assertRedirect(route('admin.email-templates.edit', 'welcome_peer'));
            $response->assertSessionHas('success');

            $this->assertDatabaseHas('email_templates', [
                'template_key' => 'welcome_peer',
                'subject' => 'New Welcome Peer Subject',
            ]);

            $updatedContent = File::get($filePath);
            $this->assertStringContainsString('This is a modified simple welcome message', $updatedContent);
        } finally {
            // Revert changes to prevent file pollution
            File::put($filePath, $originalContent);
        }
    }
}
