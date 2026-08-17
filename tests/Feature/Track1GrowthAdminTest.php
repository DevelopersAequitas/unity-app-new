<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Models\UserMilestoneBadge;
use App\Services\MilestoneBadgeService;
use Database\Seeders\Track1GrowthHonoursSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Track1GrowthAdminTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesExist();
    }

    private function ensureTablesExist(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('display_name', 150)->nullable();
                $table->string('email')->nullable();
                $table->string('status', 50)->default('active');
                $table->bigInteger('coins_balance')->default(0);
                $table->integer('life_impacted_count')->default(0);
                $table->integer('members_introduced_count')->default(0);
                $table->string('contribution_award_name', 255)->nullable();
                $table->string('contribution_award_recognition', 255)->nullable();
                $table->timestamps();
                $table->softDeletes();
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

        if (! Schema::hasTable('user_milestone_badges')) {
            Schema::create('user_milestone_badges', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('badge_id');
                $table->string('milestone_type', 50);
                $table->bigInteger('achieved_count')->default(0);
                $table->string('status', 50)->default('earned');
                $table->timestamp('earned_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('posts')) {
            Schema::create('posts', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('circle_id')->nullable();
                $table->text('content_text')->nullable();
                $table->json('media')->nullable();
                $table->json('tags')->nullable();
                $table->string('visibility', 50)->default('public');
                $table->string('moderation_status', 50)->default('approved');
                $table->boolean('sponsored')->default(false);
                $table->boolean('is_deleted')->default(false);
                $table->string('source_type', 50)->nullable();
                $table->string('source_id', 100)->nullable();
                $table->string('source_event', 50)->nullable();
                $table->string('post_type', 50)->nullable();
                $table->string('title', 255)->nullable();
                $table->text('description')->nullable();
                $table->string('image', 2000)->nullable();
                $table->string('status', 50)->default('active');
                $table->timestamps();
                $table->softDeletes();
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
            'email' => 'admin.'.Str::random(6).'@example.com',
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

    public function test_admin_can_view_track_1_growth_page(): void
    {
        $admin = $this->createAdminUser();

        $this->seed(Track1GrowthHonoursSeeder::class);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.track1-growth.index'));

        $response->assertStatus(200);
        $response->assertSee('Track 1 — Growth Honours');
        $response->assertSee('Connector');
        $response->assertSee('Global Icon');
    }

    public function test_admin_can_create_track_1_growth_honour(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.track1-growth.store'), [
            'title' => 'Titan Connector',
            'description' => 'You introduced 1000 members.',
            'required_count' => 1000,
            'sort_order' => 13,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.track1-growth.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('milestone_badges', [
            'type' => 'member_introduction',
            'title' => 'Titan Connector',
            'required_count' => 1000,
        ]);
    }

    public function test_admin_can_seed_track_1_growth_honours(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.track1-growth.seed'));

        $response->assertRedirect(route('admin.track1-growth.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('milestone_badges', [
            'type' => 'member_introduction',
            'title' => 'Connector',
        ]);
    }

    public function test_milestone_badge_service_posts_to_timeline_on_new_growth_honour(): void
    {
        $this->seed(Track1GrowthHonoursSeeder::class);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alice',
            'last_name' => 'Growth',
            'email' => 'alice.'.Str::random(6).'@example.com',
            'status' => 'active',
            'members_introduced_count' => 5,
        ]);

        app(MilestoneBadgeService::class)->calculateForUser($user);

        $earnedBadges = UserMilestoneBadge::where('user_id', $user->id)
            ->where('status', UserMilestoneBadge::STATUS_EARNED)
            ->get();

        $this->assertCount(3, $earnedBadges);

        $posts = Post::where('source_type', 'milestone_badge')
            ->where('tags', 'like', "%{$user->id}%")
            ->get();

        $this->assertGreaterThanOrEqual(1, $posts->count());
    }
}
