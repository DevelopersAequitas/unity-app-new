<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\AdClick;
use App\Models\AdminUser;
use App\Models\AdView;
use App\Models\User;
use App\Services\Ads\AdAnalyticsService;
use App\Support\SqliteMigrator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('display_name', 150)->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('company_name', 255)->nullable();
                $table->string('membership_status', 50)->default('visitor');
                $table->integer('coins_balance')->default(0);
                $table->string('password_hash')->nullable();
                $table->string('public_profile_slug', 80)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key', 50)->unique();
                $table->string('name', 100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_user_roles')) {
            Schema::create('admin_user_roles', function (Blueprint $table) {
                $table->uuid('user_id');
                $table->uuid('role_id');
            });
        }

        if (! Schema::hasTable('tbl_permission_cache')) {
            Schema::create('tbl_permission_cache', function (Blueprint $table) {
                $table->uuid('user_id')->primary();
                $table->text('circle_ids')->nullable();
                $table->timestamp('computed_at')->nullable();
                $table->integer('version')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ads')) {
            Schema::create('ads', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->text('description')->nullable();
                $table->string('image_path')->nullable();
                $table->string('redirect_url')->nullable();
                $table->string('button_text')->nullable();
                $table->string('placement')->nullable();
                $table->string('page_name')->nullable();
                $table->integer('timeline_position')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->uuid('created_by')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('files')) {
            Schema::create('files', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('uploader_user_id')->nullable();
                $table->string('s3_key');
                $table->string('mime_type');
                $table->integer('size_bytes');
                $table->integer('width')->nullable();
                $table->integer('height')->nullable();
                $table->integer('duration')->nullable();
                $table->boolean('is_orphaned')->default(false);
                $table->timestamps();
            });
        }

        // Ensure ad_views and ad_clicks tables exist in SQLite testing memory
        SqliteMigrator::run(file_get_contents(base_path('database/manual_sql/ads/001_ad_views.sql')));
        SqliteMigrator::run(file_get_contents(base_path('database/manual_sql/ads/002_ad_clicks.sql')));
    }

    public function test_view_tracking_api_logs_ad_view_and_enforces_24_hour_deduplication(): void
    {
        $user = User::factory()->create();
        $ad = Ad::create([
            'id' => (string) Str::uuid(),
            'title' => 'Test Ad',
            'is_active' => true,
        ]);

        // 1. Initial view log
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/ads/{$ad->id}/view");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ad view event logged successfully.',
            ]);

        $this->assertDatabaseHas('ad_views', [
            'ad_id' => $ad->id,
            'user_id' => $user->id,
        ]);
        $this->assertEquals(1, AdView::where('ad_id', $ad->id)->count());

        // 2. Immediate second view log within 24h should be deduplicated
        $response2 = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/ads/{$ad->id}/view");

        $response2->assertStatus(200);
        $this->assertEquals(1, AdView::where('ad_id', $ad->id)->count());
    }

    public function test_view_tracking_api_returns_404_for_invalid_ad_id(): void
    {
        $user = User::factory()->create();
        $fakeUuid = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/ads/{$fakeUuid}/view");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Ad not found.',
            ]);
    }

    public function test_click_tracking_api_logs_ad_click_and_enforces_24_hour_deduplication(): void
    {
        $user = User::factory()->create();
        $ad = Ad::create([
            'id' => (string) Str::uuid(),
            'title' => 'Test Click Ad',
            'is_active' => true,
        ]);

        // 1. Initial click log
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/ads/{$ad->id}/click", [
                'click_type' => 'visit',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Ad click event logged successfully.',
            ]);

        $this->assertDatabaseHas('ad_clicks', [
            'ad_id' => $ad->id,
            'user_id' => $user->id,
            'click_type' => 'visit',
        ]);
        $this->assertEquals(1, AdClick::where('ad_id', $ad->id)->count());

        // 2. Immediate second click log within 24h should be deduplicated
        $response2 = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/ads/{$ad->id}/click");

        $response2->assertStatus(200);
        $this->assertEquals(1, AdClick::where('ad_id', $ad->id)->count());
    }

    public function test_ad_analytics_service_calculates_total_and_unique_metrics(): void
    {
        $ad = Ad::create([
            'id' => (string) Str::uuid(),
            'title' => 'Analytics Ad',
            'is_active' => true,
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 views ad
        AdView::create([
            'ad_id' => $ad->id,
            'user_id' => $user1->id,
            'viewed_at' => now(),
        ]);

        // User 2 views ad
        AdView::create([
            'ad_id' => $ad->id,
            'user_id' => $user2->id,
            'viewed_at' => now(),
        ]);

        // User 1 clicks ad
        AdClick::create([
            'ad_id' => $ad->id,
            'user_id' => $user1->id,
            'click_type' => 'visit',
            'created_at' => now(),
        ]);

        $service = app(AdAnalyticsService::class);
        $stats = $service->getAdAnalytics($ad->id);

        $this->assertEquals(2, $stats['views']);
        $this->assertEquals(2, $stats['unique_views']);
        $this->assertEquals(1, $stats['clicks']);
        $this->assertEquals(1, $stats['unique_clicks']);
        $this->assertEquals(50.0, $stats['ctr']);
    }

    public function test_ad_analytics_service_handles_zero_views_safely(): void
    {
        $ad = Ad::create([
            'id' => (string) Str::uuid(),
            'title' => 'Zero View Ad',
            'is_active' => true,
        ]);

        $service = app(AdAnalyticsService::class);
        $stats = $service->getAdAnalytics($ad->id);

        $this->assertEquals(0, $stats['views']);
        $this->assertEquals(0, $stats['unique_views']);
        $this->assertEquals(0, $stats['clicks']);
        $this->assertEquals(0, $stats['unique_clicks']);
        $this->assertEquals(0.0, $stats['ctr']);
    }

    public function test_admin_ads_dashboard_analytics_and_show_pages_load_successfully(): void
    {
        $admin = AdminUser::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $ad = Ad::create([
            'id' => (string) Str::uuid(),
            'title' => 'Admin Test Ad',
            'is_active' => true,
        ]);

        // 1. Dashboard page
        $resDashboard = $this->actingAs($admin, 'admin')
            ->get('/admin/ads/dashboard');
        $resDashboard->assertStatus(200)
            ->assertSee('Ads Dashboard');

        // 2. Analytics page
        $resAnalytics = $this->actingAs($admin, 'admin')
            ->get('/admin/ads/analytics');
        $resAnalytics->assertStatus(200)
            ->assertSee('Ads Analytics &amp; Reports', false);

        // 3. Show Ad page
        $resShow = $this->actingAs($admin, 'admin')
            ->get("/admin/ads/{$ad->id}");
        $resShow->assertStatus(200)
            ->assertSee('Admin Test Ad');
    }

    public function test_admin_pages_load_successfully_with_empty_database(): void
    {
        $admin = AdminUser::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Admin User Empty',
            'email' => 'admin_empty@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($admin, 'admin')->get('/admin/ads/dashboard')->assertStatus(200);
        $this->actingAs($admin, 'admin')->get('/admin/ads')->assertStatus(200);
        $this->actingAs($admin, 'admin')->get('/admin/ads/analytics')->assertStatus(200);
    }

    public function test_admin_can_create_and_update_ad_successfully(): void
    {
        $admin = AdminUser::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Admin User Ad CRUD',
            'email' => 'admin_crud@example.com',
            'password' => bcrypt('password'),
        ]);

        // 1. Create Ad
        $resStore = $this->actingAs($admin, 'admin')
            ->post('/admin/ads', [
                'title' => 'New Test Ad',
                'subtitle' => 'Subtitle',
                'description' => 'Description text',
                'placement' => 'timeline',
                'page_name' => 'Home',
                'timeline_position' => 3,
                'sort_order' => 1,
                'is_active' => true,
            ]);

        $resStore->assertRedirect('/admin/ads');
        $this->assertDatabaseHas('ads', [
            'title' => 'New Test Ad',
            'placement' => 'timeline',
        ]);

        $ad = Ad::where('title', 'New Test Ad')->first();
        $this->assertNotNull($ad);

        // 2. Update Ad
        $resUpdate = $this->actingAs($admin, 'admin')
            ->put("/admin/ads/{$ad->id}", [
                'title' => 'Updated Test Ad',
                'placement' => 'dashboard',
                'is_active' => false,
            ]);

        $resUpdate->assertRedirect('/admin/ads');
        $this->assertDatabaseHas('ads', [
            'id' => $ad->id,
            'title' => 'Updated Test Ad',
            'placement' => 'dashboard',
            'is_active' => false,
        ]);
    }

    public function test_backward_compatibility_of_get_ads_endpoint(): void
    {
        $user = User::factory()->create();
        Ad::create([
            'id' => (string) Str::uuid(),
            'title' => 'Public Ad 1',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => true,
            ])
            ->assertJsonStructure([
                'success',
                'status',
                'message',
                'data' => [
                    '*' => ['id', 'user_id', 'title', 'description', 'image_url', 'status', 'created_at', 'updated_at'],
                ],
                'meta',
            ]);
    }
}
