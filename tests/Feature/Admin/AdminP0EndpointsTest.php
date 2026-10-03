<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminP0EndpointsTest extends TestCase
{
    use DatabaseTransactions;

    private User $adminUser;

    private Role $globalAdminRole;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $instance = new self('temp');
        $instance->createApplication();
        $instance->ensureTestSchemas();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();

        $this->globalAdminRole = Role::query()->firstOrCreate(
            ['key' => 'global_admin'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Global Admin',
                'description' => 'Super Administrator with global access',
            ]
        );

        $this->adminUser = User::withoutEvents(function () {
            return User::query()->create([
                'id' => (string) Str::uuid(),
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'display_name' => 'Super Admin',
                'email' => 'superadmin.'.Str::random(8).'@peersunity.test',
                'phone' => '+9199999'.rand(10000, 99999),
                'status' => 'active',
                'is_active' => true,
                'membership_status' => 'Only Unity Peer',
            ]);
        });

        $this->adminUser->roles()->syncWithoutDetaching([$this->globalAdminRole->id]);
    }

    private function ensureTestSchemas(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('key')->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_user_roles')) {
            Schema::create('admin_user_roles', function (Blueprint $table): void {
                $table->uuid('user_id');
                $table->uuid('role_id');
            });
        }

        if (! Schema::hasTable('user_roles')) {
            Schema::create('user_roles', function (Blueprint $table): void {
                $table->uuid('user_id');
                $table->uuid('role_id');
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('peer_id')->nullable();
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('display_name', 150)->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('company_name', 255)->nullable();
                $table->string('designation', 150)->nullable();
                $table->string('country', 100)->nullable();
                $table->string('state', 100)->nullable();
                $table->string('city', 100)->nullable();
                $table->uuid('city_id')->nullable();
                $table->string('business_country', 100)->nullable();
                $table->string('business_state', 100)->nullable();
                $table->string('business_city', 100)->nullable();
                $table->string('business_category_id')->nullable();
                $table->string('main_business_category_id')->nullable();
                $table->string('business_sub_category', 150)->nullable();
                $table->text('industry_tags')->nullable();
                $table->string('profile_photo_url')->nullable();
                $table->string('profile_photo_file_id')->nullable();
                $table->string('status', 50)->default('active');
                $table->boolean('is_active')->default(true);
                $table->string('membership_status', 50)->default('free_peer');
                $table->timestamp('membership_starts_at')->nullable();
                $table->timestamp('membership_ends_at')->nullable();
                $table->timestamp('membership_expiry')->nullable();
                $table->string('zoho_plan_code', 50)->nullable();
                $table->boolean('is_sponsored_member')->default(false);
                $table->integer('coins_balance')->default(0);
                $table->integer('life_impacted_count')->default(0);
                $table->integer('members_introduced_count')->default(0);
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'welcome_membership_email_sent_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('welcome_membership_email_sent_at')->nullable();
                $table->string('welcome_membership_email_status', 50)->nullable();
                $table->text('welcome_membership_email_error')->nullable();
                $table->string('welcome_membership_email_plan_code', 50)->nullable();
            });
        }

        if (! Schema::hasTable('circles')) {
            Schema::create('circles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->uuid('industry_id')->nullable();
                $table->uuid('circle_founder_user_id')->nullable();
                $table->uuid('circle_director_user_id')->nullable();
                $table->uuid('industry_director_user_id')->nullable();
                $table->uuid('ded_user_id')->nullable();
                $table->string('status', 50)->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('circle_members')) {
            Schema::create('circle_members', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('circle_id');
                $table->uuid('user_id');
                $table->string('role')->nullable();
                $table->string('status')->default('approved');
                $table->timestamp('joined_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('circle_categories')) {
            Schema::create('circle_categories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('industries')) {
            Schema::create('industries', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->uuid('circle_id')->nullable();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('status', 50)->default('success');
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('impacts')) {
            Schema::create('impacts', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->uuid('circle_id')->nullable();
                $table->integer('impact_score')->default(1);
                $table->string('status', 50)->default('approved');
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('circle_join_requests')) {
            Schema::create('circle_join_requests', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('circle_id')->nullable();
                $table->uuid('user_id')->nullable();
                $table->string('status', 50)->default('pending_cd_approval');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_memberships')) {
            Schema::create('user_memberships', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('membership_plan_id')->nullable();
                $table->uuid('payment_id')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->string('status', 50)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_audit_logs')) {
            Schema::create('admin_audit_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('admin_user_id')->nullable();
                $table->string('action');
                $table->string('target_table')->nullable();
                $table->string('target_id')->nullable();
                $table->jsonb('details')->nullable();
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        } elseif (! Schema::hasColumn('admin_audit_logs', 'target_table')) {
            Schema::table('admin_audit_logs', function (Blueprint $table): void {
                $table->string('target_table')->nullable();
                $table->string('target_id')->nullable();
                $table->jsonb('details')->nullable();
            });
        }

        if (! Schema::hasTable('membership_plans')) {
            Schema::create('membership_plans', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->integer('duration_days')->default(365);
                $table->integer('duration_months')->default(12);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_free')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('files')) {
            Schema::create('files', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->string('path')->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->timestamps();
            });
        }
    }

    private function createTestUser(array $attributes = []): User
    {
        return User::withoutEvents(function () use ($attributes) {
            $defaults = [
                'id' => (string) Str::uuid(),
                'first_name' => 'Test',
                'last_name' => 'User',
                'display_name' => 'Test User',
                'email' => 'user.'.Str::random(8).'@example.com',
                'phone' => '+91987'.rand(1000000, 9999999),
                'status' => 'active',
                'is_active' => true,
                'membership_status' => 'free_peer',
            ];

            return User::query()->create(array_merge($defaults, $attributes));
        });
    }

    public function test_get_peers_returns_canonical_paginated_directory(): void
    {
        $peer = $this->createTestUser([
            'first_name' => 'Aarav',
            'last_name' => 'Patel',
            'display_name' => 'Aarav Patel',
            'email' => 'aarav.'.Str::random(6).'@example.com',
            'company_name' => 'Patel Enterprises',
            'designation' => 'Managing Director',
            'country' => 'India',
            'state' => 'Gujarat',
            'city' => 'Ahmedabad',
            'business_sub_category' => 'Cloud Infrastructure',
            'membership_status' => 'Only Unity Peer',
            'membership_starts_at' => now()->subMonths(2),
            'membership_ends_at' => now()->addMonths(10),
            'coins_balance' => 350,
            'life_impacted_count' => 12,
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/peers?search=Patel&city=Ahmedabad');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $data = $response->json('data.data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $item = collect($data)->firstWhere('id', $peer->id);
        $this->assertNotNull($item);
        $this->assertEquals('Aarav Patel', $item['name']);
        $this->assertEquals('Patel Enterprises', $item['company_name']);
        $this->assertEquals('Ahmedabad', $item['city']);
        $this->assertTrue($item['is_pro']);
        $this->assertFalse($item['is_free']);
        $this->assertEquals('Global Peer', $item['membership_status_label']);
        $this->assertEquals(350, $item['coins_balance']);
        $this->assertEquals(12, $item['life_impacted_count']);
    }

    public function test_get_peers_supports_filtering_by_peer_type_and_status(): void
    {
        $freePeer = $this->createTestUser([
            'first_name' => 'Free',
            'last_name' => 'User',
            'display_name' => 'Free User',
            'email' => 'free.'.Str::random(6).'@example.com',
            'membership_status' => 'free_peer',
            'status' => 'active',
            'is_active' => true,
        ]);

        $proPeer = $this->createTestUser([
            'first_name' => 'Pro',
            'last_name' => 'User',
            'display_name' => 'Pro User',
            'email' => 'pro.'.Str::random(6).'@example.com',
            'membership_status' => 'Only Unity Peer',
            'status' => 'active',
            'is_active' => true,
        ]);

        // Filter pro only
        $resPro = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/peers?peer_type=pro');
        $resPro->assertStatus(200);
        $proIds = collect($resPro->json('data.data'))->pluck('id')->all();
        $this->assertContains($proPeer->id, $proIds);
        $this->assertNotContains($freePeer->id, $proIds);

        // Filter free only
        $resFree = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/peers?peer_type=free');
        $resFree->assertStatus(200);
        $freeIds = collect($resFree->json('data.data'))->pluck('id')->all();
        $this->assertContains($freePeer->id, $freeIds);
        $this->assertNotContains($proPeer->id, $freeIds);
    }

    public function test_get_dashboard_metrics_returns_authoritative_aggregations(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/metrics');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $metrics = $response->json('data');
        $this->assertArrayHasKey('total_peers', $metrics);
        $this->assertArrayHasKey('total_active_members', $metrics);
        $this->assertArrayHasKey('total_pro_peers', $metrics);
        $this->assertArrayHasKey('total_circles', $metrics);
        $this->assertArrayHasKey('total_lives_impacted', $metrics);
        $this->assertArrayHasKey('total_coins_issued', $metrics);
        $this->assertArrayHasKey('total_revenue', $metrics);
        $this->assertArrayHasKey('monthly_revenue_growth_percent', $metrics);
        $this->assertArrayHasKey('pending_badges', $metrics);
        $this->assertIsInt($metrics['total_peers']);
        $this->assertGreaterThanOrEqual(1, $metrics['total_peers']);
    }

    public function test_get_circle_peers_returns_canonical_roster(): void
    {
        $circle = new Circle;
        $circle->id = (string) Str::uuid();
        $circle->name = 'Alpha Titans Circle';
        $circle->slug = 'alpha-titans-'.Str::random(5);
        $circle->status = 'active';
        $circle->saveQuietly();

        $memberPeer = $this->createTestUser([
            'first_name' => 'Rohan',
            'last_name' => 'Mehta',
            'display_name' => 'Rohan Mehta',
            'email' => 'rohan.'.Str::random(6).'@example.com',
            'membership_status' => 'Circle Peer',
            'status' => 'active',
            'is_active' => true,
        ]);

        $cm = new CircleMember;
        $cm->id = (string) Str::uuid();
        $cm->circle_id = $circle->id;
        $cm->user_id = $memberPeer->id;
        $cm->role = 'member';
        $cm->status = 'approved';
        $cm->joined_at = now();
        $cm->saveQuietly();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/admin/circles/{$circle->id}/peers");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $data = $response->json('data.data');
        $this->assertIsArray($data);
        $item = collect($data)->firstWhere('id', $memberPeer->id);
        $this->assertNotNull($item);
        $this->assertEquals('Rohan Mehta', $item['name']);
        $this->assertEquals('Alpha Titans Circle', $item['circle_name']);
        $this->assertTrue($item['is_pro']);
    }

    public function test_post_peer_upgrade_upgrades_membership_and_records_audit(): void
    {
        $freePeer = $this->createTestUser([
            'first_name' => 'Vijay',
            'last_name' => 'Sharma',
            'display_name' => 'Vijay Sharma',
            'email' => 'vijay.'.Str::random(6).'@example.com',
            'membership_status' => 'free_peer',
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/peers/{$freePeer->id}/upgrade", [
                'plan' => 'pro',
                'duration_months' => 12,
                'notes' => 'Annual Super Admin complimentary upgrade',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $updatedData = $response->json('data');
        $this->assertEquals('Only Unity Peer', $updatedData['membership_status']);
        $this->assertTrue($updatedData['is_pro']);
        $this->assertFalse($updatedData['is_free']);
        $this->assertNotNull($updatedData['membership_starts_at']);
        $this->assertNotNull($updatedData['membership_ends_at']);

        // Check database state
        $fresh = $freePeer->fresh();
        $this->assertEquals('Only Unity Peer', $fresh->membership_status);
        $this->assertNotNull($fresh->membership_ends_at);
        $this->assertTrue(Carbon::parse($fresh->membership_ends_at)->isFuture());

        // Check user_memberships record
        if (Schema::hasTable('user_memberships')) {
            $this->assertDatabaseHas('user_memberships', [
                'user_id' => $freePeer->id,
                'status' => 'active',
            ]);
        }

        // Check audit log
        if (Schema::hasTable('admin_audit_logs')) {
            $this->assertDatabaseHas('admin_audit_logs', [
                'action' => 'admin.peer.upgrade',
                'target_table' => 'users',
                'target_id' => $freePeer->id,
            ]);
        }
    }
}
