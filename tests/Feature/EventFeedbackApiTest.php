<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
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

        Carbon::setTestNow(Carbon::parse('2026-06-09 15:00:00'));
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

        // 1. Create event that has ended
        $this->insertEvent($eventId, 'Ended Event', '2026-06-09 10:00:00', '2026-06-09 12:00:00');

        // 2. Mark user as checked-in (attended) in event_registrations
        DB::table('event_registrations')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'user_id' => $user->id,
            'checkin_status' => 'checked_in',
            'checked_in_at' => '2026-06-09 10:15:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        // 3. Post feedback
        $payload = [
            'event_id' => $eventId,
            'overall_rating' => 5,
            'content_rating' => 4,
            'venue_rating' => 5,
            'networking_rating' => 4,
            'would_recommend' => true,
            'what_worked' => 'Great sessions',
            'what_to_improve' => 'None',
            'additional_comments' => 'Keep it up',
        ];

        $response = $this->postJson('/api/v1/event-feedbacks', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Feedback submitted successfully.')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'event_id',
                    'respondent_user_id',
                    'respondent_name',
                    'overall_rating',
                    'content_rating',
                    'venue_rating',
                    'networking_rating',
                    'would_recommend',
                    'what_worked',
                    'what_to_improve',
                    'additional_comments',
                    'submitted_at',
                ],
            ]);

        // Check DB entry
        $this->assertDatabaseHas('event_feedback', [
            'event_id' => $eventId,
            'respondent_user_id' => $user->id,
            'overall_rating' => 5,
            'what_worked' => 'Great sessions',
        ]);
    }

    public function test_submit_feedback_rejected_when_not_attended(): void
    {
        $user = $this->unityUser();
        $eventId = (string) Str::uuid();

        $this->insertEvent($eventId, 'Some Event', '2026-06-09 10:00:00', '2026-06-09 12:00:00');

        Sanctum::actingAs($user);

        $payload = [
            'event_id' => $eventId,
            'overall_rating' => 5,
        ];

        $response = $this->postJson('/api/v1/event-feedbacks', $payload);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You must attend the event to submit feedback.');
    }

    public function test_check_pending_returns_event_if_attended_and_not_reviewed(): void
    {
        $user = $this->unityUser();
        $eventId = (string) Str::uuid();

        // Event ended 2 hours ago (test now is 15:00:00)
        $this->insertEvent($eventId, 'Past Event', '2026-06-09 10:00:00', '2026-06-09 13:00:00');

        // Checkin via RSVP
        DB::table('event_rsvps')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'user_id' => $user->id,
            'status' => 'yes',
            'checked_in' => true,
            'checkin_at' => '2026-06-09 10:15:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        // Call check-pending
        $response = $this->getJson('/api/v1/event-feedbacks/check-pending');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Pending event feedback found.')
            ->assertJsonPath('data.event.id', $eventId)
            ->assertJsonPath('data.event.title', 'Past Event');
    }

    public function test_check_pending_returns_null_if_already_reviewed(): void
    {
        $user = $this->unityUser();
        $eventId = (string) Str::uuid();

        $this->insertEvent($eventId, 'Past Event', '2026-06-09 10:00:00', '2026-06-09 13:00:00');

        DB::table('event_rsvps')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'user_id' => $user->id,
            'status' => 'yes',
            'checked_in' => true,
            'checkin_at' => '2026-06-09 10:15:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert feedback
        DB::table('event_feedback')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'respondent_user_id' => $user->id,
            'respondent_name' => $user->display_name,
            'overall_rating' => 4,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/event-feedbacks/check-pending');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'No pending feedbacks.')
            ->assertJsonPath('data', null);
    }

    public function test_my_feedbacks_list(): void
    {
        $user = $this->unityUser();
        $eventId = (string) Str::uuid();

        $this->insertEvent($eventId, 'My Event', '2026-06-09 10:00:00', '2026-06-09 13:00:00');

        DB::table('event_feedback')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'respondent_user_id' => $user->id,
            'respondent_name' => $user->display_name,
            'overall_rating' => 4,
            'what_worked' => 'Loved the content',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/event-feedbacks/my');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.event_id', $eventId)
            ->assertJsonPath('data.items.0.what_worked', 'Loved the content');
    }

    public function test_event_feedbacks_detail_and_stats(): void
    {
        $user1 = $this->unityUser();
        $user2 = $this->unityUser();
        $eventId = (string) Str::uuid();

        $this->insertEvent($eventId, 'Public Event', '2026-06-09 10:00:00', '2026-06-09 13:00:00');

        // Review 1
        DB::table('event_feedback')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'respondent_user_id' => $user1->id,
            'respondent_name' => $user1->display_name,
            'overall_rating' => 5,
            'would_recommend' => true,
            'submitted_at' => now()->subMinutes(10),
        ]);

        // Review 2
        DB::table('event_feedback')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'respondent_user_id' => $user2->id,
            'respondent_name' => $user2->display_name,
            'overall_rating' => 3,
            'would_recommend' => false,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($user1);

        $response = $this->getJson('/api/v1/event-feedbacks/event/'.$eventId);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.total_reviews', 2)
            ->assertJsonPath('data.stats.avg_overall', 4) // (5 + 3) / 2 = 4
            ->assertJsonPath('data.stats.recommend_percentage', 50) // 1 out of 2 = 50%
            ->assertJsonCount(2, 'data.items');
    }

    private function unityUser(): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Peer',
            'last_name' => 'Member',
            'display_name' => 'Peer Member '.Str::random(4),
            'email' => 'peer-'.Str::uuid().'@example.com',
            'phone' => (string) random_int(1000000000, 9999999999),
            'password_hash' => Hash::make('password'),
        ]);
    }

    private function insertEvent(string $id, string $title, string $startAt, string $endAt): void
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function setUpInMemoryDatabase(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('event_feedback');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_rsvps');
        Schema::dropIfExists('events');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('password_hash');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('events', function (Blueprint $table) {
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_registrations', function (Blueprint $table) {
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

        Schema::create('event_rsvps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('user_id');
            $table->string('status');
            $table->boolean('checked_in')->default(false);
            $table->timestamp('checkin_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('respondent_user_id')->nullable();
            $table->string('respondent_name')->nullable();
            $table->integer('overall_rating');
            $table->integer('content_rating')->nullable();
            $table->integer('venue_rating')->nullable();
            $table->integer('networking_rating')->nullable();
            $table->boolean('would_recommend')->nullable();
            $table->text('what_worked')->nullable();
            $table->text('what_to_improve')->nullable();
            $table->text('additional_comments')->nullable();
            $table->timestamp('submitted_at');
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
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
