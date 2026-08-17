<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MilestoneBadge;
use App\Models\Post;
use App\Models\User;
use App\Models\UserMilestoneBadge;
use App\Services\MilestoneBadgeService;
use Database\Seeders\Track1GrowthHonoursSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Track1GrowthHonoursTest extends TestCase
{
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
    }

    public function test_seeder_populates_all_12_growth_track_honours(): void
    {
        $this->seed(Track1GrowthHonoursSeeder::class);

        $honours = MilestoneBadge::where('type', MilestoneBadge::TYPE_MEMBER_INTRODUCTION)->get();

        $this->assertCount(12, $honours);

        $titles = $honours->pluck('title')->all();
        $this->assertContains('Connector', $titles);
        $this->assertContains('Catalyst', $titles);
        $this->assertContains('Influencer', $titles);
        $this->assertContains('Ambassador', $titles);
        $this->assertContains('Rainmaker', $titles);
        $this->assertContains('Trailblazer', $titles);
        $this->assertContains('Vanguard', $titles);
        $this->assertContains('Luminary', $titles);
        $this->assertContains('Movement Maker', $titles);
        $this->assertContains('Community Titan', $titles);
        $this->assertContains('Network Architect', $titles);
        $this->assertContains('Global Icon', $titles);

        $connector = MilestoneBadge::where('title', 'Connector')->first();
        $this->assertNotNull($connector);
        $this->assertEquals(1, $connector->required_count);

        $globalIcon = MilestoneBadge::where('title', 'Global Icon')->first();
        $this->assertNotNull($globalIcon);
        $this->assertEquals(500, $globalIcon->required_count);
    }

    public function test_milestone_badge_service_awards_honours_and_posts_to_timeline(): void
    {
        $this->seed(Track1GrowthHonoursSeeder::class);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Introducer',
            'email' => 'john.growth.'.Str::random(6).'@example.com',
            'status' => 'active',
            'members_introduced_count' => 3,
        ]);

        app(MilestoneBadgeService::class)->calculateForUser($user);

        $earnedCount = UserMilestoneBadge::where('user_id', $user->id)
            ->where('status', UserMilestoneBadge::STATUS_EARNED)
            ->count();

        $this->assertEquals(2, $earnedCount);

        $timelinePosts = Post::where('source_type', 'milestone_badge')
            ->where('tags', 'like', "%{$user->id}%")
            ->get();

        $this->assertGreaterThanOrEqual(1, $timelinePosts->count());
        $titles = $timelinePosts->pluck('title')->implode(' ');
        $this->assertStringContainsString('Honour Unlocked', $titles);
    }

    public function test_can_seed_track_1_honours(): void
    {
        $this->seed(Track1GrowthHonoursSeeder::class);

        $this->assertDatabaseHas('milestone_badges', [
            'type' => 'member_introduction',
            'title' => 'Connector',
        ]);
    }
}
