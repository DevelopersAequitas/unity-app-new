<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendWearTheBadgeWhatsappJob;
use App\Jobs\SendWelcomeWhatsappJob;
use App\Models\Post;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\Creative\WearTheBadgeImageGenerator;
use App\Services\Creative\WelcomeCreativeService;
use App\Services\Membership\MembershipUpgradeService;
use App\Services\Notifications\WearTheBadgeWhatsappService;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WelcomeCreativeAndWhatsappOnceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Storage::fake('public');

        $this->createTestTables();
    }

    private function createTestTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('secondary_mobile')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('company_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('city')->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('business_sub_category')->nullable();
            $table->string('membership_status')->default('free_trial_peer');
            $table->string('approval_status')->default('pending');
            $table->string('status')->default('active');
            $table->timestamp('membership_starts_at')->nullable();
            $table->timestamp('membership_ends_at')->nullable();
            $table->timestamp('membership_expiry')->nullable();
            $table->timestamp('membership_approved_at')->nullable();
            $table->uuid('membership_approved_by')->nullable();
            $table->timestamp('last_payment_at')->nullable();
            $table->string('welcome_creative_url', 500)->nullable();
            $table->string('profile_card_image_url', 500)->nullable();
            $table->uuid('profile_photo_file_id')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->integer('coins_balance')->default(0);
            $table->integer('life_impacted_count')->default(0);
            $table->integer('members_introduced_count')->default(0);
            $table->string('coin_medal_rank')->nullable();
            $table->string('coin_milestone_title')->nullable();
            $table->string('coin_milestone_meaning')->nullable();
            $table->string('contribution_award_name')->nullable();
            $table->string('contribution_award_recognition')->nullable();
            $table->boolean('is_online')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uploader_user_id')->nullable();
            $table->string('s3_key', 500)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('size_bytes')->default(0);
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('circle_id')->nullable();
            $table->text('content_text')->nullable();
            $table->json('media')->nullable();
            $table->json('tags')->nullable();
            $table->string('visibility', 50)->default('public');
            $table->string('moderation_status', 50)->default('approved');
            $table->boolean('sponsored')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->boolean('active')->default(true);
            $table->string('source_type', 100)->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('source_event', 100)->nullable();
            $table->string('post_type', 100)->nullable();
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('image', 500)->nullable();
            $table->string('status', 50)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('post_mentions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('post_id')->index();
            $table->uuid('peer_id')->index();
            $table->timestamps();
        });

        Schema::create('activity_creatives', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('post_id')->nullable()->index();
            $table->string('activity_type', 100)->index();
            $table->string('activity_id', 100)->nullable()->index();
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->uuid('creative_file_id')->nullable();
            $table->string('creative_url', 500)->nullable();
            $table->string('status', 50)->default('active');
            $table->json('meta')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('template_key')->unique();
            $table->string('template_name')->nullable();
            $table->string('webhook_url', 500)->nullable();
            $table->string('webhook_secret')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('notification_id')->nullable();
            $table->uuid('user_id')->nullable()->index();
            $table->string('channel', 50)->default('whatsapp');
            $table->string('provider', 100)->nullable();
            $table->string('status', 50)->default('pending');
            $table->json('request_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_message_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->uuid('notification_id')->nullable();
            $table->string('template_key', 100)->nullable()->index();
            $table->string('template_name', 255)->nullable();
            $table->string('phone', 50)->nullable()->index();
            $table->string('creative_url', 500)->nullable();
            $table->string('provider', 50)->default('fleximsg');
            $table->string('status', 50)->default('pending');
            $table->integer('http_status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_new_peer_registration_creates_welcome_creative_post_on_timeline(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jay',
            'last_name' => 'Patel',
            'display_name' => 'Jay Patel',
            'email' => 'jay.patel@example.com',
            'phone' => '919876543210',
            'company_name' => 'Acme Ventures Pvt Ltd',
            'designation' => 'Founder & CEO',
            'city' => 'Ahmedabad',
            'membership_status' => User::STATUS_FREE_TRIAL,
            'status' => 'active',
        ]);

        // Simulate User::created having already set welcome_creative_url via WearTheBadgeImageGenerator
        app(WearTheBadgeImageGenerator::class)->generateOrGetUrl($user);
        $user->refresh();
        $this->assertNotEmpty($user->welcome_creative_url);

        // Now run WelcomeCreativeService (or SendWelcomeWhatsappJob)
        $creative = app(WelcomeCreativeService::class)->generateAndPostWelcomeCreative($user);

        $this->assertNotNull($creative, 'WelcomeCreativeService must return an ActivityCreative record');
        $this->assertNotEmpty($creative->creative_url);
        $this->assertNotEmpty($creative->post_id);

        // Verify Timeline Post was created in posts table
        $post = Post::where('source_type', 'welcome')
            ->where('source_id', (string) $user->id)
            ->where('is_deleted', false)
            ->first();

        $this->assertNotNull($post, 'Welcome Creative Post must exist in posts table');
        $this->assertSame('welcome', $post->post_type);
        $this->assertSame('public', $post->visibility);
        $this->assertSame('active', $post->status);
        $this->assertSame('approved', $post->moderation_status);
        $this->assertStringContainsString('Jay Patel', (string) $post->title);
        $this->assertStringContainsString('Jay Patel', (string) $post->content_text);
        $this->assertNotEmpty($post->media);

        // Verify system author (Peers Global Genie) is active so feed query includes the post
        $systemUser = User::getSystemUser();
        $this->assertSame((string) $systemUser->id, (string) $post->user_id);
        $this->assertSame('active', $systemUser->status);
        $this->assertNull($systemUser->deleted_at);

        // Verify PostMention exists for the new peer
        $this->assertDatabaseHas('post_mentions', [
            'post_id' => (string) $post->id,
            'peer_id' => (string) $user->id,
        ]);

        // Verify idempotency: calling generateAndPostWelcomeCreative a second time does not duplicate the post
        app(WelcomeCreativeService::class)->generateAndPostWelcomeCreative($user);
        $postCount = Post::where('source_type', 'welcome')
            ->where('source_id', (string) $user->id)
            ->count();
        $this->assertSame(1, $postCount);
    }

    public function test_welcome_whatsapp_sent_only_once_on_registration_and_never_on_membership_approval_or_upgrade(): void
    {
        Http::fake([
            'https://fleximsg.com/*' => Http::response(['status' => 'success', 'message' => 'Queued'], 200),
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'welcome',
            'template_name' => 'Welcome to Peers Global',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/welcome',
            'webhook_secret' => 'secret_123',
            'is_active' => true,
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'wear_the_badge',
            'template_name' => 'Wear The Badge',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/wear-the-badge',
            'webhook_secret' => 'secret_123',
            'is_active' => false, // Even if inactive, must never fallback to welcome!
        ]);

        // 1. Register new peer
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jay',
            'last_name' => 'Patel',
            'display_name' => 'Jay Patel',
            'email' => 'jay.patel@example.com',
            'phone' => '919876543210',
            'company_name' => 'Acme Ventures Pvt Ltd',
            'designation' => 'Founder & CEO',
            'city' => 'Ahmedabad',
            'membership_status' => User::STATUS_FREE_TRIAL,
            'approval_status' => 'pending',
            'status' => 'active',
        ]);

        // Execute registration Welcome WhatsApp job
        $job = new SendWelcomeWhatsappJob((string) $user->id);
        $job->handle(app(WhatsappNotificationService::class), app(WelcomeCreativeService::class));

        // Assert Welcome WhatsApp was sent exactly ONCE on registration
        Http::assertSentCount(1);

        // Also assert Timeline Welcome Post was created by SendWelcomeWhatsappJob
        $this->assertDatabaseHas('posts', [
            'source_type' => 'welcome',
            'source_id' => (string) $user->id,
            'post_type' => 'welcome',
            'status' => 'active',
        ]);

        // 2. If SendWelcomeWhatsappJob is triggered again, it must NOT send again
        $job2 = new SendWelcomeWhatsappJob((string) $user->id);
        $job2->handle(app(WhatsappNotificationService::class), app(WelcomeCreativeService::class));
        Http::assertSentCount(1);

        // 3. Admin approves membership (free_trial_peer -> only_unity_peer)
        Queue::fake();

        $user->forceFill([
            'membership_status' => 'only_unity_peer',
            'approval_status' => 'approved',
            'membership_starts_at' => now(),
            'membership_ends_at' => now()->addYear(),
            'membership_approved_at' => now(),
        ])->save();

        Queue::assertNotPushed(SendWelcomeWhatsappJob::class);
        Queue::assertNotPushed(SendWearTheBadgeWhatsappJob::class);
        Http::assertSentCount(1);

        // 4. Admin upgrades membership tier (only_unity_peer -> circle_peer -> global_peer) or marks paid
        $user->forceFill([
            'membership_status' => 'circle_peer',
            'last_payment_at' => now(),
        ])->save();

        app(MembershipUpgradeService::class)->markAsOnlyUnityPeerAfterPayment($user, [
            'membership_starts_at' => now(),
            'membership_ends_at' => now()->addYear(),
            'paid_at' => now(),
        ]);

        Queue::assertNotPushed(SendWelcomeWhatsappJob::class);
        Queue::assertNotPushed(SendWearTheBadgeWhatsappJob::class);
        $this->assertFalse(app(WearTheBadgeWhatsappService::class)->isEligible($user->fresh()));
        Http::assertSentCount(1);

        // 5. Even if WhatsappNotificationService::send('welcome', ...) is called directly again for this user, it is blocked
        $duplicateSendResult = app(WhatsappNotificationService::class)->send('welcome', (string) $user->phone, [
            'first_name' => 'Jay',
        ], (string) $user->id);

        $this->assertFalse($duplicateSendResult);
        Http::assertSentCount(1);
    }
}
