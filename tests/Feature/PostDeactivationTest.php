<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Post;
use App\Models\User;
use App\Services\Posts\PostDeactivationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostDeactivationTest extends TestCase
{
    protected AdminUser $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
                'search_path' => '',
            ],
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        // Create roles and admin_user_roles
        Schema::dropIfExists('roles');
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::dropIfExists('admin_user_roles');
        Schema::create('admin_user_roles', function (Blueprint $table): void {
            $table->uuid('user_id');
            $table->uuid('role_id');
        });

        // Create admin_users
        Schema::dropIfExists('admin_users');
        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('role')->nullable();
            $table->timestamps();
        });

        // Create users
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->unique();
            $table->integer('coins_balance')->default(0);
            $table->integer('life_impacted_count')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        // Create posts
        Schema::dropIfExists('posts');
        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id')->nullable();
            $table->text('content_text')->nullable();
            $table->json('media')->nullable();
            $table->json('tags')->nullable();
            $table->string('visibility')->default('public');
            $table->string('moderation_status')->default('approved');
            $table->boolean('sponsored')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->boolean('active')->default(true);
            $table->string('status')->default('active');
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('post_type')->nullable();
            $table->text('deactivation_remarks')->nullable();
            $table->uuid('deactivated_by')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Create coins_ledger
        Schema::dropIfExists('coins_ledger');
        Schema::create('coins_ledger', function (Blueprint $table): void {
            $table->uuid('transaction_id')->primary();
            $table->uuid('user_id');
            $table->integer('amount');
            $table->integer('balance_after')->default(0);
            $table->string('activity_id')->nullable();
            $table->string('reference')->nullable();
            $table->text('remark')->nullable();
            $table->string('entry_type')->nullable();
            $table->string('bucket')->nullable();
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->text('metadata')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // Create life_impact_histories
        Schema::dropIfExists('life_impact_histories');
        Schema::create('life_impact_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('triggered_by_user_id')->nullable();
            $table->string('activity_type')->nullable();
            $table->string('activity_id')->nullable();
            $table->integer('impact_value')->default(0);
            $table->integer('impact_after')->default(0);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('counted_in_total')->default(true);
            $table->text('meta')->nullable();
            $table->timestamps();
        });

        // Create app_notifications & notifications
        Schema::dropIfExists('app_notifications');
        Schema::create('app_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type')->nullable();
            $table->string('category')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('channel')->default('push');
            $table->string('priority')->default('medium');
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('screen')->nullable();
            $table->json('data')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::dropIfExists('notifications');
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
        });

        // Seed Admin, Role and User
        $globalRole = \App\Models\Role::create([
            'id' => (string) Str::uuid(),
            'key' => 'global_admin',
            'name' => 'Global Admin',
        ]);

        $this->admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'role' => 'global_admin',
        ]);

        DB::table('admin_user_roles')->insert([
            'user_id' => $this->admin->id,
            'role_id' => $globalRole->id,
        ]);

        $this->user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'coins_balance' => 15000,
            'life_impacted_count' => 10,
        ]);
    }

    /**
     * Test 1: Deactivation requires remarks (validation check).
     */
    public function test_deactivation_requires_remarks(): void
    {
        $post = Post::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'content_text' => 'Testing post deactivation validation',
            'status' => 'active',
            'active' => true,
            'is_deleted' => false,
            'visibility' => 'public',
            'moderation_status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.posts.deactivate', $post), [
                'remarks' => '', // empty remarks
            ]);

        $response->assertSessionHasErrors('remarks');

        // Post should still be active
        $this->assertFalse((bool) Post::where('id', $post->id)->first()->is_deleted);
    }

    /**
     * Test 2: Standard post deactivation without rewards (zero deduction, no artificial deduction).
     */
    public function test_standard_post_deactivation_without_rewards(): void
    {
        $initialCoins = (int) ($this->user->fresh()->coins_balance ?? 0);
        $initialImpact = (int) ($this->user->fresh()->life_impacted_count ?? 0);

        $post = Post::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'content_text' => 'Standard post without activity rewards',
            'status' => 'active',
            'active' => true,
            'is_deleted' => false,
            'visibility' => 'public',
            'moderation_status' => 'approved',
        ]);

        $service = app(PostDeactivationService::class);
        $result = $service->deactivatePost($post, 'Content does not comply with community guidelines.', $this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['coins_reversed']);
        $this->assertEquals(0, $result['impact_reversed']);

        // Balances must not change artificially
        $this->assertEquals($initialCoins, (int) $this->user->fresh()->coins_balance);
        $this->assertEquals($initialImpact, (int) $this->user->fresh()->life_impacted_count);

        // Post status must be deactivated
        $post->refresh();
        $this->assertTrue((bool) $post->is_deleted);
        $this->assertEquals('inactive', $post->status);
        $this->assertEquals('Content does not comply with community guidelines.', $post->deactivation_remarks);
    }

    /**
     * Test 3: Deactivating post linked to an activity reverses exact coins and life impact points.
     */
    public function test_deactivation_reverses_coins_and_life_impact(): void
    {
        $activityId = (string) Str::uuid();

        // Credit coins for this activity
        $awardedCoins = 5000;
        $prevBalance = (int) ($this->user->fresh()->coins_balance ?? 0);
        $newBalance = $prevBalance + $awardedCoins;
        $this->user->coins_balance = $newBalance;
        $this->user->save();

        DB::table('coins_ledger')->insert([
            'transaction_id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'amount' => $awardedCoins,
            'balance_after' => $newBalance,
            'activity_id' => $activityId,
            'reference' => 'Activity: business_deal reward',
            'reference_type' => 'ACTIVITY',
            'reference_id' => $activityId,
            'created_by' => $this->user->id,
            'created_at' => now(),
        ]);

        // Credit life impact for this activity
        $awardedImpact = 5;
        $prevImpact = (int) ($this->user->fresh()->life_impacted_count ?? 0);
        $this->user->life_impacted_count = $prevImpact + $awardedImpact;
        $this->user->save();

        DB::table('life_impact_histories')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'activity_type' => 'business_deal',
            'activity_id' => $activityId,
            'impact_value' => $awardedImpact,
            'title' => 'Closed a business deal',
            'counted_in_total' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create the post associated with this business deal
        $post = Post::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'source_type' => 'business_deal',
            'source_id' => $activityId,
            'content_text' => 'Closed business deal worth 50000',
            'status' => 'active',
            'active' => true,
            'is_deleted' => false,
            'visibility' => 'public',
            'moderation_status' => 'approved',
        ]);

        $service = app(PostDeactivationService::class);
        $result = $service->deactivatePost($post, 'Duplicate post or image uploaded.', $this->admin->id);

        $this->assertTrue($result['success']);
        $this->assertEquals($awardedCoins, $result['coins_reversed']);
        $this->assertEquals($awardedImpact, $result['impact_reversed']);

        // User coins must be deducted by exact awarded amount
        $this->assertEquals($prevBalance, (int) $this->user->fresh()->coins_balance);

        // User life impact must be deducted by exact awarded impact
        $this->assertEquals($prevImpact, (int) $this->user->fresh()->life_impacted_count);

        // Check negative ledger entry in coins_ledger
        $reversalRow = DB::table('coins_ledger')
            ->where('user_id', $this->user->id)
            ->where('amount', -$awardedCoins)
            ->where('reference_id', (string) $post->id)
            ->first();
        $this->assertNotNull($reversalRow);
        $this->assertStringContainsString('Duplicate post or image uploaded.', (string) $reversalRow->remark);
    }

    /**
     * Test 4: Idempotency - repeated deactivation calls do not cause duplicate deductions.
     */
    public function test_deactivation_is_idempotent(): void
    {
        $post = Post::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'content_text' => 'Post for idempotency test',
            'status' => 'active',
            'active' => true,
            'is_deleted' => false,
            'visibility' => 'public',
            'moderation_status' => 'approved',
        ]);

        $service = app(PostDeactivationService::class);

        // 1st deactivation call
        $firstResult = $service->deactivatePost($post, 'Initial deactivation remark.', $this->admin->id);
        $this->assertTrue($firstResult['success']);

        // 2nd deactivation call
        $secondResult = $service->deactivatePost($post->fresh(), 'Second deactivation call.', $this->admin->id);
        $this->assertTrue($secondResult['already_deactivated']);
        $this->assertEquals(0, $secondResult['coins_reversed']);
        $this->assertEquals(0, $secondResult['impact_reversed']);
    }

    /**
     * Test 5: Insufficient coin balance does not produce negative balance.
     */
    public function test_insufficient_coin_balance_handled_safely(): void
    {
        $this->user->coins_balance = 1000; // User only has 1000 coins left
        $this->user->save();

        $activityId = (string) Str::uuid();
        $awardedCoins = 5000; // Originally awarded 5000 coins

        DB::table('coins_ledger')->insert([
            'transaction_id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'amount' => $awardedCoins,
            'balance_after' => 6000,
            'activity_id' => $activityId,
            'reference' => 'Activity: test deal',
            'created_by' => $this->user->id,
            'created_at' => now(),
        ]);

        $post = Post::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'source_type' => 'test_deal',
            'source_id' => $activityId,
            'content_text' => 'Post with spent coins',
            'status' => 'active',
            'active' => true,
            'is_deleted' => false,
            'visibility' => 'public',
            'moderation_status' => 'approved',
        ]);

        $service = app(PostDeactivationService::class);
        $result = $service->deactivatePost($post, 'Invalid activity submission.', $this->admin->id);

        $this->assertTrue($result['success']);
        // Coins balance must not be negative
        $this->assertGreaterThanOrEqual(0, (int) $this->user->fresh()->coins_balance);
        $this->assertEquals(0, (int) $this->user->fresh()->coins_balance);
    }

    /**
     * Test 6: In-App notification is sent to user containing admin remarks.
     */
    public function test_deactivation_sends_notification_with_remarks(): void
    {
        $post = Post::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'content_text' => 'Post to test notification delivery',
            'status' => 'active',
            'active' => true,
            'is_deleted' => false,
            'visibility' => 'public',
            'moderation_status' => 'approved',
        ]);

        $remarks = 'Incorrect or misleading activity information.';

        $service = app(PostDeactivationService::class);
        $result = $service->deactivatePost($post, $remarks, $this->admin->id);

        $this->assertTrue($result['success']);

        // Check AppNotification
        $appNotification = DB::table('app_notifications')
            ->where('user_id', $this->user->id)
            ->where('type', 'post_deactivated')
            ->first();

        $this->assertNotNull($appNotification);
        $this->assertEquals('Your Post Has Been Deactivated', $appNotification->title);
        $this->assertStringContainsString($remarks, $appNotification->body);

        // Check legacy Notification
        $legacyNotification = DB::table('notifications')
            ->where('user_id', $this->user->id)
            ->where('type', 'post_deactivated')
            ->first();

        $this->assertNotNull($legacyNotification);
        $this->assertEquals('Your Post Has Been Deactivated', $legacyNotification->title);
        $this->assertStringContainsString($remarks, (string) $legacyNotification->message);
    }
}
