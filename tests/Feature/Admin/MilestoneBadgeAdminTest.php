<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\MilestoneBadge;
use App\Models\Role;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MilestoneBadgeAdminTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesExist();
    }

    private function ensureTablesExist(): void
    {
        if (! Schema::hasTable('milestone_badges')) {
            Schema::create('milestone_badges', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type', 50);
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->bigInteger('required_count')->default(0);
                $table->string('badge_image_url', 2000)->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name', 255)->nullable();
                $table->string('email', 255)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name', 255);
                $table->string('key', 100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_user_roles')) {
            Schema::create('admin_user_roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('role_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('role_module_access')) {
            Schema::create('role_module_access', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('role_id');
                $table->string('module_key', 100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_modules')) {
            Schema::create('admin_modules', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name', 255);
                $table->string('module_key', 100);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    private function createAdminUser(): AdminUser
    {
        $role = Role::firstOrCreate(
            ['key' => 'global_admin'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Global Admin',
            ]
        );

        $admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
        ]);

        DB::table('admin_user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $admin;
    }

    public function test_unauthorized_user_cannot_access_badge_management(): void
    {
        $response = $this->get('/admin/milestone-badges');
        $response->assertRedirect('/admin/login');
    }

    public function test_admin_can_view_milestone_badges_page(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        MilestoneBadge::create([
            'type' => 'life_impact',
            'title' => 'Life Transformer Test',
            'description' => 'Test Life Impact badge',
            'required_count' => 25,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get('/admin/milestone-badges');
        $response->assertStatus(200);
        $response->assertSee('Life Transformer Test');
    }

    public function test_admin_can_create_life_impact_badge(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');
        Storage::fake('public');

        $file = UploadedFile::fake()->image('badge.png', 100, 100);

        $payload = [
            'type' => 'life_impact',
            'title' => 'Change Maker',
            'description' => 'Impact 10 lives',
            'required_count' => 10,
            'sort_order' => 1,
            'is_active' => '1',
            'badge_image' => $file,
        ];

        $response = $this->post('/admin/milestone-badges', $payload);
        $response->assertRedirect('/admin/milestone-badges');
        $response->assertSessionHas('success');

        $badge = MilestoneBadge::where('title', 'Change Maker')->first();
        $this->assertNotNull($badge);
        $this->assertEquals('life_impact', $badge->type);
        $this->assertEquals(10, $badge->required_count);
        $this->assertNotNull($badge->badge_image_url);
    }

    public function test_admin_can_create_coin_badge(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        $payload = [
            'type' => 'coins',
            'title' => 'Coin Builder',
            'description' => 'Accumulate 10000 coins',
            'required_count' => 10000,
            'sort_order' => 2,
            'is_active' => '1',
        ];

        $response = $this->post('/admin/milestone-badges', $payload);
        $response->assertRedirect('/admin/milestone-badges');

        $badge = MilestoneBadge::where('title', 'Coin Builder')->first();
        $this->assertNotNull($badge);
        $this->assertEquals('coins', $badge->type);
        $this->assertEquals(10000, $badge->required_count);
    }

    public function test_admin_can_create_member_introduction_badge(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        $payload = [
            'type' => 'member_introduction',
            'title' => 'Community Connector',
            'description' => 'Introduce 5 members',
            'required_count' => 5,
            'sort_order' => 3,
            'is_active' => '1',
        ];

        $response = $this->post('/admin/milestone-badges', $payload);
        $response->assertRedirect('/admin/milestone-badges');

        $badge = MilestoneBadge::where('title', 'Community Connector')->first();
        $this->assertNotNull($badge);
        $this->assertEquals('member_introduction', $badge->type);
        $this->assertEquals(5, $badge->required_count);
    }

    public function test_admin_can_edit_and_toggle_badge_status(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        $badge = MilestoneBadge::create([
            'type' => 'coins',
            'title' => 'Original Title',
            'description' => 'Original description',
            'required_count' => 5000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->put("/admin/milestone-badges/{$badge->id}", [
            'type' => 'coins',
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'required_count' => 6000,
            'sort_order' => 2,
            'is_active' => '1',
        ]);
        $response->assertRedirect('/admin/milestone-badges');

        $badge->refresh();
        $this->assertEquals('Updated Title', $badge->title);
        $this->assertEquals(6000, $badge->required_count);

        // Toggle status
        $response = $this->post("/admin/milestone-badges/{$badge->id}/toggle-status");
        $badge->refresh();
        $this->assertFalse($badge->is_active);
    }

    public function test_admin_can_delete_badge(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'admin');

        $badge = MilestoneBadge::create([
            'type' => 'life_impact',
            'title' => 'Badge To Delete',
            'required_count' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->delete("/admin/milestone-badges/{$badge->id}");
        $response->assertRedirect('/admin/milestone-badges');

        $this->assertDatabaseMissing('milestone_badges', ['id' => $badge->id]);
    }
}
