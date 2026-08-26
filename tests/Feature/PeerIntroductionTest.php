<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Notifications\AppNotification;
use App\Models\Notifications\NotificationPreference;
use App\Models\Post;
use App\Models\User;
use App\Services\MilestoneBadgeService;
use App\Services\Users\PeerIntroductionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PeerIntroductionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpInMemoryDatabase();
    }

    protected function setUpInMemoryDatabase(): void
    {

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('company_name')->nullable();
            $table->unsignedBigInteger('business_category_id')->nullable();
            $table->unsignedBigInteger('main_business_category_id')->nullable();
            $table->string('business_sub_category')->nullable();
            $table->uuid('profile_photo_file_id')->nullable();
            $table->string('status')->default('active');
            $table->uuid('introduced_by')->nullable();
            $table->integer('members_introduced_count')->default(0);
            $table->string('contribution_award_name')->nullable();
            $table->string('contribution_award_recognition')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('uploader_user_id')->nullable();
            $table->string('s3_key')->nullable();
            $table->string('mime_type')->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('duration')->nullable();
            $table->timestamps();
        });

        Schema::create('milestone_badges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type')->default('member_introduction');
            $table->string('title');
            $table->string('track')->default('Growth');
            $table->integer('required_count')->default(1);
            $table->text('description')->nullable();
            $table->string('badge_image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('user_milestone_badges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('badge_id');
            $table->string('milestone_type')->nullable();
            $table->integer('achieved_count')->default(0);
            $table->string('status')->default('earned');
            $table->timestamp('earned_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id')->nullable();
            $table->text('content_text')->nullable();
            $table->json('media')->nullable();
            $table->json('tags')->nullable();
            $table->string('visibility')->default('public');
            $table->string('moderation_status')->default('pending');
            $table->boolean('sponsored')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('source_event')->nullable();
            $table->string('post_type')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('app_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('campaign_id')->nullable();
            $table->string('type');
            $table->string('category')->nullable();
            $table->string('title');
            $table->text('body');
            $table->string('channel')->default('push');
            $table->string('priority')->default('medium');
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('screen')->nullable();
            $table->text('data')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_suppression_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type');
            $table->string('dedupe_key')->nullable();
            $table->uuid('campaign_id')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->integer('send_count')->default(0);
            $table->timestamps();
        });

        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('notification_id');
            $table->uuid('campaign_id')->nullable();
            $table->uuid('user_id');
            $table->string('channel');
            $table->string('provider');
            $table->string('status');
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('chat_enabled')->default(true);
            $table->boolean('event_enabled')->default(true);
            $table->boolean('circle_enabled')->default(true);
            $table->boolean('business_enabled')->default(true);
            $table->boolean('campaign_enabled')->default(true);
            $table->string('quiet_hours_start')->nullable();
            $table->string('quiet_hours_end')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('user_push_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token');
            $table->string('platform');
            $table->string('device_id')->nullable();
            $table->string('app_version')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_introduces_peer_triggers_creative_post_and_notification(): void
    {
        $introducer = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Urvashi',
            'last_name' => 'Chavda',
            'display_name' => 'Urvashi Chavda',
            'company_name' => 'Chavda Enterprises',
            'business_sub_category' => 'Interior Design',
            'email' => 'urvashi@example.com',
            'status' => 'active',
        ]);

        $introduced = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Hardik',
            'last_name' => 'Parmar',
            'display_name' => 'Hardik Parmar',
            'company_name' => 'Parmar Tech Solutions',
            'business_sub_category' => 'Software Development',
            'email' => 'hardik@example.com',
            'status' => 'active',
            'introduced_by' => $introducer->id,
        ]);

        $introducer->members_introduced_count = 1;
        $introducer->save();

        // Create notification preferences to ensure notifications are not muted/suppressed
        NotificationPreference::create([
            'id' => (string) Str::uuid(),
            'user_id' => $introducer->id,
            'push_enabled' => true,
            'email_enabled' => true,
            'chat_enabled' => true,
            'event_enabled' => true,
            'circle_enabled' => true,
            'business_enabled' => true,
            'campaign_enabled' => true,
        ]);

        DB::table('milestone_badges')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'member_introduction',
            'title' => 'CONNECTOR',
            'track' => 'Growth',
            'required_count' => 1,
            'description' => 'You are carrying the Peers Global spirit wherever you go.',
            'is_active' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Run introduction service flow & sync milestone badge
        $service = app(PeerIntroductionService::class);
        $service->handlePeerIntroduction($introducer, $introduced);

        app(MilestoneBadgeService::class)->calculateForUser($introducer);

        // Verify image file registration
        $this->assertDatabaseHas('files', [
            'mime_type' => 'image/webp',
        ]);

        // Verify timeline post was created
        $this->assertDatabaseHas('posts', [
            'post_type' => 'introduction',
            'source_id' => $introduced->id,
            'source_type' => 'introduction',
        ]);

        $post = Post::where('source_id', $introduced->id)->firstOrFail();
        $this->assertStringContainsString('Congratulations to Urvashi Chavda for introducing Hardik Parmar', $post->content_text);

        // Verify automatic Growth Honour timeline post created for the threshold (1 introduced member)
        $this->assertDatabaseHas('posts', [
            'post_type' => 'growth_honour',
            'source_type' => 'milestone_badge',
        ]);

        $ghPost = Post::where('post_type', 'growth_honour')->where('source_type', 'milestone_badge')->firstOrFail();
        $this->assertStringContainsString('CONNECTOR', $ghPost->content_text);

        // Verify push notification registered for the introducer
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $introducer->id,
            'type' => 'member_introduced',
            'title' => 'Member Introduced Successfully! 🎉',
        ]);

        $notification = AppNotification::where('user_id', $introducer->id)->firstOrFail();
        $this->assertStringContainsString('Hi, you have introduced Hardik Parmar', $notification->body);
    }

    public function test_introduction_image_generator_resolves_company_and_category(): void
    {
        $generator = app(\App\Services\Creative\IntroductionImageGenerator::class);

        $user1 = new User([
            'first_name' => 'Azhar',
            'last_name' => 'Pathan',
            'company_name' => 'Aequitas Tech',
            'business_sub_category' => 'IT Services',
        ]);

        $this->assertEquals('Aequitas Tech', $generator->resolveCompanyName($user1));
        $this->assertEquals('IT Services', $generator->resolveCategoryName($user1));

        $user2 = new User([
            'first_name' => 'Lalit',
            'last_name' => 'Munot',
        ]);

        $this->assertEquals('', $generator->resolveCompanyName($user2));
        $this->assertEquals('', $generator->resolveCategoryName($user2));
    }
}
