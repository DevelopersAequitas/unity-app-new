<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventFeedback;
use App\Models\Notifications\AppNotification;
use App\Models\Notifications\NotificationCampaign;
use App\Models\User;
use App\Services\Events\EventFeedbackService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventFeedbackApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge();
        DB::reconnect();

        Carbon::setTestNow(Carbon::parse('2026-10-16 15:00:00'));
        $this->setUpInMemoryDatabase();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_submit_feedback_successfully_when_attended(): void
    {
        $user = $this->unityUser();
        $eventId = (string) Str::uuid();

        $this->insertEvent($eventId, 'Flagship Summit', '2026-10-16 09:00:00', '2026-10-16 13:00:00');

        DB::table('event_registrations')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'user_id' => $user->id,
            'status' => 'confirmed',
            'checkin_status' => 'checked_in',
            'checked_in_at' => '2026-10-16 09:15:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'event_id' => $eventId,
            'occurrence_id' => (string) Str::uuid(),
            'rating' => 5,
            'feedback' => 'Great networking and insightful speaker sessions! Really enjoyed connecting with fellow entrepreneurs.',
            'tags' => [
                '🤝 Great Networking',
                '🎤 Inspiring Speakers',
                '💡 Valuable Insights',
            ],
            'would_recommend' => true,
            'notification_id' => (string) Str::uuid(),
        ];

        $response = $this->postJson("/api/v1/events/{$eventId}/feedback", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Feedback submitted successfully.')
            ->assertJsonPath('data.event_id', $eventId)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.feedback', 'Great networking and insightful speaker sessions! Really enjoyed connecting with fellow entrepreneurs.')
            ->assertJsonPath('data.would_recommend', true)
            ->assertJsonCount(3, 'data.tags');

        $this->assertDatabaseHas('event_feedbacks', [
            'event_id' => $eventId,
            'user_id' => $user->id,
            'rating' => 5,
            'would_recommend' => 1,
        ]);
    }

    public function test_submit_feedback_validation_failure_on_invalid_rating(): void
    {
        $user = $this->unityUser();
        $eventId = (string) Str::uuid();

        $this->insertEvent($eventId, 'Some Event', '2026-10-16 09:00:00', '2026-10-16 13:00:00');

        DB::table('event_registrations')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'user_id' => $user->id,
            'status' => 'confirmed',
            'checkin_status' => 'checked_in',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'event_id' => $eventId,
            'rating' => 6, // Invalid rating > 5
        ];

        $response = $this->postJson("/api/v1/events/{$eventId}/feedback", $payload);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'rating',
                ],
            ]);
    }

    public function test_submit_feedback_rejected_when_not_attended(): void
    {
        $user = $this->unityUser();
        $eventId = (string) Str::uuid();

        $this->insertEvent($eventId, 'Unattended Event', '2026-10-16 09:00:00', '2026-10-16 13:00:00');

        Sanctum::actingAs($user);

        $payload = [
            'event_id' => $eventId,
            'rating' => 5,
        ];

        $response = $this->postJson("/api/v1/events/{$eventId}/feedback", $payload);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You must attend or register for the event to submit feedback.');
    }

    public function test_submit_feedback_prevents_duplicate(): void
    {
        $user = $this->unityUser();
        $eventId = (string) Str::uuid();

        $this->insertEvent($eventId, 'Event', '2026-10-16 09:00:00', '2026-10-16 13:00:00');

        DB::table('event_registrations')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'user_id' => $user->id,
            'status' => 'confirmed',
            'checkin_status' => 'checked_in',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('event_feedbacks')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'user_id' => $user->id,
            'rating' => 5,
            'feedback' => 'First feedback',
            'tags' => json_encode([]),
            'would_recommend' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'event_id' => $eventId,
            'rating' => 4,
            'feedback' => 'Second feedback',
        ];

        $response = $this->postJson("/api/v1/events/{$eventId}/feedback", $payload);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You have already submitted feedback for this event.');
    }

    public function test_get_my_feedback_returns_data_when_exists(): void
    {
        $user = $this->unityUser();
        $eventId = (string) Str::uuid();

        $this->insertEvent($eventId, 'Annual Meet', '2026-10-16 09:00:00', '2026-10-16 13:00:00');

        $feedbackId = (string) Str::uuid();
        DB::table('event_feedbacks')->insert([
            'id' => $feedbackId,
            'event_id' => $eventId,
            'user_id' => $user->id,
            'rating' => 5,
            'feedback' => 'Great networking and insightful speaker sessions!',
            'tags' => json_encode(['🤝 Great Networking', '🎤 Inspiring Speakers']),
            'would_recommend' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/events/{$eventId}/my-feedback");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Feedback retrieved successfully.')
            ->assertJsonPath('data.id', $feedbackId)
            ->assertJsonPath('data.event_id', $eventId)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.feedback', 'Great networking and insightful speaker sessions!')
            ->assertJsonPath('data.would_recommend', true)
            ->assertJsonCount(2, 'data.tags');
    }

    public function test_get_my_feedback_returns_null_when_not_submitted(): void
    {
        $user = $this->unityUser();
        $eventId = (string) Str::uuid();

        $this->insertEvent($eventId, 'Annual Meet', '2026-10-16 09:00:00', '2026-10-16 13:00:00');

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/events/{$eventId}/my-feedback");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'No feedback submitted yet.')
            ->assertJsonPath('data', null);
    }

    public function test_feedback_summary_returns_aggregated_stats_and_reviews(): void
    {
        $organizer = $this->unityUser();
        $user1 = $this->unityUser();
        $user2 = $this->unityUser();
        $eventId = (string) Str::uuid();

        // Organizer is creator
        $this->insertEvent($eventId, 'Masterclass', '2026-10-16 09:00:00', '2026-10-16 13:00:00', $organizer->id);

        // Feedback 1: 5 stars, would_recommend true
        DB::table('event_feedbacks')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'user_id' => $user1->id,
            'rating' => 5,
            'feedback' => 'Phenomenal session!',
            'tags' => json_encode(['🤝 Great Networking', '🎤 Inspiring Speakers']),
            'would_recommend' => true,
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        // Feedback 2: 4 stars, would_recommend true
        DB::table('event_feedbacks')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'user_id' => $user2->id,
            'rating' => 4,
            'feedback' => 'Insightful content.',
            'tags' => json_encode(['🤝 Great Networking', '💡 Valuable Insights']),
            'would_recommend' => true,
            'created_at' => now()->subMinutes(15),
            'updated_at' => now()->subMinutes(15),
        ]);

        Sanctum::actingAs($organizer);

        $response = $this->getJson("/api/v1/events/{$eventId}/feedback-summary");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Feedback summary fetched.')
            ->assertJsonPath('data.event_id', $eventId)
            ->assertJsonPath('data.total_responses', 2)
            ->assertJsonPath('data.average_rating', 4.5)
            ->assertJsonPath('data.recommendation_rate', 100)
            ->assertJsonPath('data.rating_breakdown.5_star', 1)
            ->assertJsonPath('data.rating_breakdown.4_star', 1)
            ->assertJsonPath('data.rating_breakdown.3_star', 0)
            ->assertJsonPath('data.top_tags.0.tag', '🤝 Great Networking')
            ->assertJsonPath('data.top_tags.0.count', 2)
            ->assertJsonCount(2, 'data.feedbacks');
    }

    public function test_feedback_summary_forbidden_for_regular_members(): void
    {
        $creator = $this->unityUser();
        $regularUser = $this->unityUser();
        $eventId = (string) Str::uuid();

        $this->insertEvent($eventId, 'Masterclass', '2026-10-16 09:00:00', '2026-10-16 13:00:00', $creator->id);

        Sanctum::actingAs($regularUser);

        $response = $this->getJson("/api/v1/events/{$eventId}/feedback-summary");

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthorized to view feedback summary.');
    }

    public function test_automated_push_notification_service_dispatches_proper_payload(): void
    {
        $user = $this->unityUser();
        $eventId = (string) Str::uuid();

        // Event ended 1 hour 15 minutes ago (within 1-2 hour window)
        $this->insertEvent($eventId, 'Tech Meetup', '2026-10-16 10:00:00', '2026-10-16 13:45:00');

        DB::table('event_registrations')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'user_id' => $user->id,
            'status' => 'confirmed',
            'checkin_status' => 'checked_in',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(EventFeedbackService::class);
        $result = $service->sendPostEventFeedbackNotifications();

        $this->assertGreaterThanOrEqual(1, $result['events_processed']);
        $this->assertEquals(1, $result['notifications_sent']);

        // Check in-app notification created with exact specification schema
        $notification = AppNotification::query()
            ->where('user_id', $user->id)
            ->where('type', 'post_event_feedback')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('Share feedback for Tech Meetup', $notification->title);
        $this->assertEquals('How was your event experience?', $notification->body);

        $payload = $notification->dataPayload();
        $this->assertEquals('post_event_feedback', $payload['type']);
        $this->assertEquals('post_event_feedback', $payload['activity_type']);
        $this->assertEquals('post_event_feedback', $payload['category']);
        $this->assertEquals('event_feedback', $payload['screen']);
        $this->assertEquals('event_feedback', $payload['navigation_screen']);
        $this->assertEquals('event_feedback', $payload['tap_destination']);
        $this->assertEquals('event', $payload['reference_type']);
        $this->assertEquals($eventId, $payload['reference_id']);
        $this->assertEquals($eventId, $payload['event_id']);
        $this->assertEquals($user->id, $payload['user_id']);
        $this->assertEquals('FLUTTER_NOTIFICATION_CLICK', $payload['click_action']);

        // Running again should deduplicate
        $secondRun = $service->sendPostEventFeedbackNotifications();
        $this->assertEquals(0, $secondRun['notifications_sent']);
        $this->assertGreaterThanOrEqual(1, $secondRun['skipped']);
    }

    private function unityUser(): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Harsh',
            'last_name' => 'Chauhan',
            'display_name' => 'Harsh Chauhan',
            'email' => 'user-'.Str::uuid().'@example.com',
            'phone' => (string) random_int(1000000000, 9999999999),
            'password_hash' => Hash::make('password'),
        ]);
    }

    private function insertEvent(string $id, string $title, string $startAt, string $endAt, ?string $creatorId = null): void
    {
        DB::table('events')->insert([
            'id' => $id,
            'circle_id' => null,
            'title' => $title,
            'description' => 'Event description',
            'start_at' => $startAt,
            'end_at' => $endAt,
            'is_virtual' => false,
            'location_text' => 'Ahmedabad',
            'visibility' => 'members',
            'is_paid' => false,
            'event_type' => 'circle_meeting',
            'event_category' => 'networking',
            'mode' => 'offline',
            'qr_checkin_enabled' => false,
            'is_public' => false,
            'visitor_registration_enabled' => false,
            'member_registration_enabled' => true,
            'status' => 'scheduled',
            'is_active' => true,
            'created_by_user_id' => $creatorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function setUpInMemoryDatabase(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_delivery_logs');
        Schema::dropIfExists('notification_suppression_logs');
        Schema::dropIfExists('notification_campaigns');
        Schema::dropIfExists('event_feedbacks');
        Schema::dropIfExists('event_feedback');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_rsvps');
        Schema::dropIfExists('event_occurrences');
        Schema::dropIfExists('events');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('password_hash');
            $table->string('profile_photo_url', 500)->nullable();
            $table->uuid('profile_photo_id')->nullable();
            $table->string('status', 50)->default('active');
            $table->string('city', 100)->nullable();
            $table->decimal('coin_balance', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->boolean('is_virtual')->default(false);
            $table->text('location_text')->nullable();
            $table->json('agenda')->nullable();
            $table->json('speakers')->nullable();
            $table->text('banner_url')->nullable();
            $table->string('visibility')->default('members');
            $table->boolean('is_paid')->default(false);
            $table->json('metadata')->nullable();
            $table->string('event_type')->nullable();
            $table->string('event_category')->nullable();
            $table->string('mode')->nullable();
            $table->integer('registration_limit')->nullable();
            $table->decimal('ticket_price', 10, 2)->default(0);
            $table->boolean('qr_checkin_enabled')->default(false);
            $table->boolean('is_public')->default(false);
            $table->string('recurrence_type')->nullable();
            $table->boolean('visitor_registration_enabled')->default(false);
            $table->boolean('member_registration_enabled')->default(true);
            $table->text('online_meeting_url')->nullable();
            $table->text('zoho_form_url')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('organizer_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_occurrences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->date('occurrence_date')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('status')->nullable();
            $table->integer('sequence')->default(1);
            $table->integer('registration_limit')->nullable();
            $table->integer('registered_count')->default(0);
            $table->integer('checked_in_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('occurrence_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('status')->nullable();
            $table->boolean('payment_required')->default(false);
            $table->string('payment_status')->nullable();
            $table->string('checkin_status')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_rsvps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('user_id');
            $table->string('status');
            $table->boolean('checked_in')->default(false);
            $table->timestamp('checkin_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_feedbacks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('occurrence_id')->nullable();
            $table->uuid('user_id');
            $table->unsignedSmallInteger('rating');
            $table->text('feedback')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('would_recommend')->nullable()->default(true);
            $table->uuid('notification_id')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id'], 'uq_event_user_feedback');
        });

        Schema::create('notification_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->string('category')->nullable();
            $table->string('channel')->default('push');
            $table->string('trigger_type')->nullable();
            $table->string('frequency')->nullable();
            $table->string('priority')->default('medium');
            $table->string('audience_type')->nullable();
            $table->text('title_template')->nullable();
            $table->text('body_template')->nullable();
            $table->string('tap_screen')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
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
            $table->json('data')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('read_at')->nullable();
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
            $table->timestamps();
        });

        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('notification_id')->nullable();
            $table->uuid('campaign_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('channel')->nullable();
            $table->string('provider')->nullable();
            $table->string('status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
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

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('tokenable_type');
            $table->uuid('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
}
