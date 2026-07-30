<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Notifications\NotificationPreference;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Firebase\FcmService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpInMemoryDatabase();

        // Boot UUID generators for PostLike and PostComment in test environment
        PostLike::creating(static function (PostLike $like): void {
            if (empty($like->id)) {
                $like->id = Str::uuid()->toString();
            }
        });

        PostComment::creating(static function (PostComment $comment): void {
            if (empty($comment->id)) {
                $comment->id = Str::uuid()->toString();
            }
        });

        // Mock Firebase FcmService to return success immediately
        $fcmMock = $this->mock(FcmService::class);
        $fcmMock->shouldReceive('sendToDevice')
            ->andReturn([
                'success' => true,
                'firebase_response' => ['name' => 'mock-message-id'],
                'error' => null,
            ]);
    }

    protected function setUpInMemoryDatabase(): void
    {
        Schema::create('users', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('status')->default('active');
            $table->uuid('introduced_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('posts', static function (Blueprint $table): void {
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

        Schema::create('post_likes', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('post_id');
            $table->uuid('user_id');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('post_comments', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('post_id');
            $table->uuid('user_id');
            $table->text('content');
            $table->uuid('parent_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('app_notifications', static function (Blueprint $table): void {
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

        Schema::create('notification_suppression_logs', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type');
            $table->string('dedupe_key')->nullable();
            $table->uuid('campaign_id')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->integer('send_count')->default(0);
            $table->timestamps();
        });

        Schema::create('notification_delivery_logs', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('notification_id');
            $table->uuid('campaign_id')->nullable();
            $table->uuid('user_id');
            $table->string('channel');
            $table->string('provider');
            $table->string('status');
            $table->string('provider_message_id')->nullable();
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', static function (Blueprint $table): void {
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

        Schema::create('user_push_tokens', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token');
            $table->string('platform');
            $table->string('device_id')->nullable();
            $table->string('app_version')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    private function createPreferencesForUser(User $user): void
    {
        NotificationPreference::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'push_enabled' => true,
            'email_enabled' => true,
            'chat_enabled' => true,
            'event_enabled' => true,
            'circle_enabled' => true,
            'business_enabled' => true,
            'campaign_enabled' => true,
        ]);

        $this->createPushTokenForUser($user);
    }

    private function createPushTokenForUser(User $user): void
    {
        UserPushToken::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'token' => 'fcm_token_'.Str::random(10),
            'platform' => 'android',
            'is_active' => true,
        ]);
    }

    private function createSystemUser(): User
    {
        return User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'PeersGlobal',
            'last_name' => 'Unity',
            'display_name' => 'PeersGlobal Unity',
            'email' => 'info@peersglobal.com',
            'status' => 'active',
        ]);
    }

    public function test_liking_standard_post_notifies_author(): void
    {
        $author = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'John', 'last_name' => 'Author', 'display_name' => 'John Author', 'status' => 'active']);
        $liker = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'Jane', 'last_name' => 'Liker', 'display_name' => 'Jane Liker', 'status' => 'active']);

        $this->createPreferencesForUser($author);

        $post = Post::create([
            'user_id' => $author->id,
            'content_text' => 'Hello standard post',
            'post_type' => 'standard',
        ]);

        $response = $this->actingAs($liker, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/like");

        $response->assertStatus(200);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $author->id,
            'type' => 'post_like',
            'title' => 'Post Liked',
            'body' => 'Jane Liker liked your post',
        ]);
    }

    public function test_commenting_standard_post_notifies_author(): void
    {
        $author = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'John', 'last_name' => 'Author', 'display_name' => 'John Author', 'status' => 'active']);
        $commenter = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'Jane', 'last_name' => 'Commenter', 'display_name' => 'Jane Commenter', 'status' => 'active']);

        $this->createPreferencesForUser($author);

        $post = Post::create([
            'user_id' => $author->id,
            'content_text' => 'Hello standard post',
            'post_type' => 'standard',
        ]);

        $response = $this->actingAs($commenter, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/comments", [
                'content' => 'My awesome comment content',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $author->id,
            'type' => 'post_comment',
            'title' => 'New Comment on Post',
            'body' => 'My awesome comment content',
        ]);
    }

    public function test_liking_introduction_post_notifies_both_introducer_and_introduced(): void
    {
        $systemUser = $this->createSystemUser();
        $introducer = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'Chirag', 'last_name' => 'Introducer', 'display_name' => 'Chirag Introducer', 'status' => 'active']);
        $introduced = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'Hardik', 'last_name' => 'Introduced', 'display_name' => 'Hardik Introduced', 'introduced_by' => $introducer->id, 'status' => 'active']);
        $liker = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'Jane', 'last_name' => 'Liker', 'display_name' => 'Jane Liker', 'status' => 'active']);

        $this->createPreferencesForUser($introducer);
        $this->createPreferencesForUser($introduced);

        $post = Post::create([
            'user_id' => $systemUser->id,
            'post_type' => 'introduction',
            'source_type' => 'introduction',
            'source_id' => $introduced->id,
            'content_text' => 'Chirag introduced Hardik',
        ]);

        $response = $this->actingAs($liker, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/like");

        $response->assertStatus(200);

        // Verify introduced user notification
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $introduced->id,
            'type' => 'post_like',
            'title' => 'New Like on Introduction',
            'body' => 'Jane Liker liked your introduction post',
        ]);

        // Verify introducer notification
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $introducer->id,
            'type' => 'post_like',
            'title' => 'New Like on Introduction',
            'body' => 'Jane Liker liked the introduction of Hardik Introduced',
        ]);
    }

    public function test_commenting_introduction_post_notifies_both_introducer_and_introduced(): void
    {
        $systemUser = $this->createSystemUser();
        $introducer = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'Chirag', 'last_name' => 'Introducer', 'display_name' => 'Chirag Introducer', 'status' => 'active']);
        $introduced = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'Hardik', 'last_name' => 'Introduced', 'display_name' => 'Hardik Introduced', 'introduced_by' => $introducer->id, 'status' => 'active']);
        $commenter = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'Jane', 'last_name' => 'Commenter', 'display_name' => 'Jane Commenter', 'status' => 'active']);

        $this->createPreferencesForUser($introducer);
        $this->createPreferencesForUser($introduced);

        $post = Post::create([
            'user_id' => $systemUser->id,
            'post_type' => 'introduction',
            'source_type' => 'introduction',
            'source_id' => $introduced->id,
            'content_text' => 'Chirag introduced Hardik',
        ]);

        $response = $this->actingAs($commenter, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/comments", [
                'content' => 'Great introduction!',
            ]);

        $response->assertStatus(201);

        // Verify introduced user notification
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $introduced->id,
            'type' => 'post_comment',
            'title' => 'New Comment on Introduction',
            'body' => 'Great introduction!',
        ]);

        // Verify introducer notification
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $introducer->id,
            'type' => 'post_comment',
            'title' => 'New Comment on Introduction',
            'body' => 'Great introduction!',
        ]);
    }

    public function test_liking_birthday_post_notifies_subject_user(): void
    {
        $systemUser = $this->createSystemUser();
        $birthdayUser = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'Mohit', 'last_name' => 'Birthday', 'display_name' => 'Mohit Birthday', 'status' => 'active']);
        $liker = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'Jane', 'last_name' => 'Liker', 'display_name' => 'Jane Liker', 'status' => 'active']);

        $this->createPreferencesForUser($birthdayUser);

        $post = Post::create([
            'user_id' => $systemUser->id,
            'post_type' => 'birthday',
            'source_type' => 'birthday',
            'source_id' => $birthdayUser->id,
            'content_text' => 'Happy Birthday Mohit!',
        ]);

        $response = $this->actingAs($liker, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/like");

        $response->assertStatus(200);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $birthdayUser->id,
            'type' => 'post_like',
            'title' => 'Birthday Wish Liked',
            'body' => 'Jane Liker liked your birthday post',
        ]);
    }

    public function test_commenting_anniversary_post_notifies_subject_user(): void
    {
        $systemUser = $this->createSystemUser();
        $anniversaryUser = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'Rahul', 'last_name' => 'Anniversary', 'display_name' => 'Rahul Anniversary', 'status' => 'active']);
        $commenter = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'Jane', 'last_name' => 'Commenter', 'display_name' => 'Jane Commenter', 'status' => 'active']);

        $this->createPreferencesForUser($anniversaryUser);

        $post = Post::create([
            'user_id' => $systemUser->id,
            'post_type' => 'anniversary',
            'source_type' => 'anniversary',
            'source_id' => $anniversaryUser->id,
            'content_text' => 'Happy Anniversary Rahul!',
        ]);

        $response = $this->actingAs($commenter, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/comments", [
                'content' => 'Happy Anniversary both of you!',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $anniversaryUser->id,
            'type' => 'post_comment',
            'title' => 'New Anniversary Comment',
            'body' => 'Happy Anniversary both of you!',
        ]);
    }

    public function test_liking_global_peer_certificate_post_notifies_subject_user(): void
    {
        $systemUser = $this->createSystemUser();
        $certifiedUser = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'Dhruvil', 'last_name' => 'Certified', 'display_name' => 'Dhruvil Certified', 'status' => 'active']);
        $liker = User::create(['id' => Str::uuid()->toString(), 'first_name' => 'Jane', 'last_name' => 'Liker', 'display_name' => 'Jane Liker', 'status' => 'active']);

        $this->createPreferencesForUser($certifiedUser);

        $post = Post::create([
            'user_id' => $systemUser->id,
            'post_type' => 'global_peer_certificate',
            'source_type' => 'global_peer_certificate',
            'source_id' => $certifiedUser->id,
            'content_text' => 'Dhruvil Certified is now a Global Peer!',
        ]);

        $response = $this->actingAs($liker, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/like");

        $response->assertStatus(200);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $certifiedUser->id,
            'type' => 'post_like',
            'title' => 'Certificate Liked',
            'body' => 'Jane Liker liked your Global Peer Certificate post',
        ]);
    }
}
