<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\ImpactGuideline;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImpactGuidelinesAdminTest extends TestCase
{
    protected AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('password_hash')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::dropIfExists('circle_members');
        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('circle_id')->nullable();
            $table->string('status')->default('approved');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::dropIfExists('admin_users');
        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::dropIfExists('roles');
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::dropIfExists('admin_user_roles');
        Schema::create('admin_user_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->timestamps();
        });

        Schema::dropIfExists('impact_guidelines');
        Schema::create('impact_guidelines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title')->default('Your Life Impact Score');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('action');
            $table->string('category')->default('Business & Growth');
            $table->unsignedInteger('impact_value')->default(1);
            $table->string('impact_unit')->default('Lives');
            $table->integer('display_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'display_order']);
        });

        User::query()->create([
            'id' => (string) Str::uuid(),
            'email' => 'superadmin@peersunity.com',
            'display_name' => 'Super Admin',
            'status' => 'active',
        ]);

        $this->admin = AdminUser::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'email' => 'superadmin@peersunity.com',
        ]);

        $superRole = Role::query()->create([
            'id' => (string) Str::uuid(),
            'key' => 'super_admin',
            'name' => 'Super Admin',
        ]);

        DB::table('admin_user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->admin->id,
            'role_id' => $superRole->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_can_view_impact_guidelines_index(): void
    {
        ImpactGuideline::query()->create([
            'action' => 'Helped a new member get customer',
            'category' => 'Business & Growth',
            'impact_value' => 2,
            'impact_unit' => 'Lives',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.impact-guidelines.index'));

        $response->assertStatus(200);
        $response->assertSee('Helped a new member get customer');
        $response->assertSee('+2 Lives');
    }

    public function test_admin_can_create_impact_guideline(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.impact-guidelines.store'), [
                'action' => 'Mentored an entrepreneur',
                'category' => 'Leadership',
                'impact_value' => 3,
                'impact_unit' => 'Lives',
                'display_order' => 6,
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('admin.impact-guidelines.index'));
        $this->assertDatabaseHas('impact_guidelines', [
            'action' => 'Mentored an entrepreneur',
            'category' => 'Leadership',
            'impact_value' => 3,
            'impact_unit' => 'Lives',
            'display_order' => 6,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_impact_guideline(): void
    {
        $guideline = ImpactGuideline::query()->create([
            'action' => 'Old Action',
            'category' => 'Business & Growth',
            'impact_value' => 1,
            'impact_unit' => 'Life',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->put(route('admin.impact-guidelines.update', $guideline->id), [
                'action' => 'Updated Action',
                'category' => 'Trust & Visibility',
                'impact_value' => 5,
                'impact_unit' => 'Lives',
                'display_order' => 2,
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('admin.impact-guidelines.index'));
        $this->assertDatabaseHas('impact_guidelines', [
            'id' => $guideline->id,
            'action' => 'Updated Action',
            'category' => 'Trust & Visibility',
            'impact_value' => 5,
            'impact_unit' => 'Lives',
        ]);
    }

    public function test_admin_can_toggle_guideline_status(): void
    {
        $guideline = ImpactGuideline::query()->create([
            'action' => 'Toggle Action',
            'category' => 'Business & Growth',
            'impact_value' => 1,
            'impact_unit' => 'Life',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.impact-guidelines.toggle-status', $guideline->id));

        $response->assertRedirect();
        $this->assertFalse($guideline->fresh()->is_active);

        // Toggle back
        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.impact-guidelines.toggle-status', $guideline->id));

        $this->assertTrue($guideline->fresh()->is_active);
    }

    public function test_admin_can_delete_guideline(): void
    {
        $guideline = ImpactGuideline::query()->create([
            'action' => 'To Delete',
            'category' => 'General',
            'impact_value' => 1,
            'impact_unit' => 'Life',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.impact-guidelines.destroy', $guideline->id));

        $response->assertRedirect(route('admin.impact-guidelines.index'));
        $this->assertSoftDeleted('impact_guidelines', ['id' => $guideline->id]);
    }

    public function test_admin_can_update_header_info(): void
    {
        ImpactGuideline::query()->create([
            'action' => 'Action 1',
            'category' => 'General',
            'impact_value' => 1,
            'impact_unit' => 'Life',
            'display_order' => 1,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.impact-guidelines.update-header'), [
                'title' => 'Updated Impact Score',
                'description' => 'Brand new impact score description.',
                'icon_url' => 'https://example.com/impact.png',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('impact_guidelines', [
            'title' => 'Updated Impact Score',
            'description' => 'Brand new impact score description.',
            'icon' => 'https://example.com/impact.png',
        ]);
    }
}
