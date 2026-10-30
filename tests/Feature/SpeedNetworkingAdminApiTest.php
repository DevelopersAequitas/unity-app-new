<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Event;
use App\Models\Role;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpeedNetworkingAdminApiTest extends TestCase
{
    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $this->setUpDatabase();
    }

    private function setUpDatabase(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_occurrences');
        Schema::dropIfExists('events');
        Schema::dropIfExists('circles');
        Schema::dropIfExists('speed_networking_matches');
        Schema::dropIfExists('speed_networking_participants');
        Schema::dropIfExists('speed_networking_events');
        Schema::dropIfExists('admin_user_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('users');

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('state_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('banner_url')->nullable();
            $table->string('status')->default('scheduled');
            $table->boolean('is_active')->default(true);
            $table->string('event_type')->default('speed_networking');
            $table->string('event_category')->nullable();
            $table->string('mode')->default('online');
            $table->string('recurrence_type')->nullable();
            $table->string('location_text')->nullable();
            $table->string('online_meeting_url')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_occurrences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_circles', function (Blueprint $table): void {
            $table->uuid('id')->nullable();
            $table->uuid('event_id');
            $table->uuid('circle_id');
            $table->timestamps();
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('occurrence_id')->nullable();
            $table->uuid('user_id');
            $table->string('status')->default('registered');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password_hash')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('admin_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('key')->unique();
            $table->timestamps();
        });

        Schema::create('admin_user_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->timestamps();
        });

        Schema::create('speed_networking_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('banner_url')->nullable();
            $table->string('status')->default('scheduled');
            $table->integer('round_duration_seconds')->default(180);
            $table->integer('break_duration_seconds')->default(20);
            $table->integer('total_rounds')->default(5);
            $table->integer('current_round')->default(0);
            $table->timestamp('scheduled_start_time')->nullable();
            $table->timestamp('scheduled_end_time')->nullable();
            $table->timestamp('actual_start_time')->nullable();
            $table->timestamp('actual_end_time')->nullable();
            $table->decimal('ticket_price', 10, 2)->default(0);
            $table->boolean('is_paid')->default(false);
            $table->string('currency', 10)->default('INR');
            $table->uuid('circle_id')->nullable();
            $table->boolean('circle_restricted')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('speed_networking_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('user_id');
            $table->string('status')->default('lobby');
            $table->integer('rounds_completed')->default(0);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();
        });

        Schema::create('speed_networking_matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->integer('round_number');
            $table->integer('table_number');
            $table->uuid('user_a_id');
            $table->uuid('user_b_id')->nullable();
            $table->string('channel_id');
            $table->string('status')->default('active');
            $table->boolean('is_break_round')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        $this->admin = AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Global Admin User',
            'email' => 'admin@peersunity.com',
        ]);

        $role = Role::create([
            'id' => (string) Str::uuid(),
            'name' => 'Global Admin',
            'key' => 'global_admin',
        ]);

        DB::table('admin_user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->admin->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_speed_networking_index_renders_successfully_with_events(): void
    {
        SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Global Founders Speed Networking',
            'description' => 'Connect 1-on-1 with fellow entrepreneurs',
            'status' => 'scheduled',
            'round_duration_seconds' => 180,
            'break_duration_seconds' => 20,
            'total_rounds' => 5,
            'current_round' => 1,
            'ticket_price' => 0,
            'is_paid' => false,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/speed-networking');

        $response->assertStatus(200);
        $response->assertSee('Global Founders Speed Networking');
    }

    public function test_admin_speed_networking_api_list_events(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@peersunity.com',
        ]);

        SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Tech Pioneers Networking',
            'description' => 'Fast networking for tech founders',
            'status' => 'lobby_open',
            'round_duration_seconds' => 300,
            'break_duration_seconds' => 30,
            'total_rounds' => 3,
            'current_round' => 0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/admin/speed-networking/events');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonFragment([
            'title' => 'Tech Pioneers Networking',
        ]);
    }

    public function test_speed_networking_event_automatically_calculates_scheduled_end_time_from_duration(): void
    {
        $start = Carbon::parse('2026-10-30T10:00:00Z');

        $event = SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Auto Duration Test',
            'status' => 'scheduled',
            'round_duration_seconds' => 180,
            'break_duration_seconds' => 20,
            'total_rounds' => 5,
            'scheduled_start_time' => $start,
            'scheduled_end_time' => null,
        ]);

        // 5 rounds: 5 * 180 + 4 * 20 = 900 + 80 = 980 seconds
        $expectedEnd = $start->copy()->addSeconds(980);

        $this->assertNotNull($event->scheduled_end_time);
        $this->assertEquals($expectedEnd->toIso8601String(), $event->scheduled_end_time->toIso8601String());
        $this->assertEquals(980, $event->duration_seconds);
        $this->assertEquals(17, $event->duration_minutes);

        $array = $event->toArray();
        $this->assertArrayHasKey('scheduled_end_time', $array);
        $this->assertNotNull($array['scheduled_end_time']);
        $this->assertArrayHasKey('end_time', $array);
        $this->assertNotNull($array['end_time']);
    }

    public function test_admin_api_creates_event_with_auto_calculated_end_time_from_duration_minutes(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin2@peersunity.com',
        ]);
        Sanctum::actingAs($user);

        $startTime = '2026-11-01T14:00:00Z';
        $payload = [
            'title' => 'Custom Duration Event',
            'scheduled_start_time' => $startTime,
            'duration_minutes' => 45,
            'round_duration_seconds' => 300,
            'break_duration_seconds' => 30,
        ];

        $response = $this->postJson('/api/v1/admin/speed-networking/events', $payload);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertNotNull($data['scheduled_end_time']);
        $expectedEnd = Carbon::parse($startTime)->addMinutes(45)->toIso8601String();
        $this->assertEquals($expectedEnd, Carbon::parse($data['scheduled_end_time'])->toIso8601String());
    }

    public function test_api_active_event_returns_auto_calculated_end_time(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'member@peersunity.com',
        ]);
        Sanctum::actingAs($user);

        $start = Carbon::now()->addMinutes(10);
        $event = SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Active Test Event',
            'status' => 'scheduled',
            'round_duration_seconds' => 180,
            'break_duration_seconds' => 20,
            'total_rounds' => 5,
            'scheduled_start_time' => $start,
            'scheduled_end_time' => null,
        ]);

        $response = $this->getJson('/api/v1/speed-networking/active-event');
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotNull($data);
        $this->assertNotNull($data['scheduled_end_time']);
        $this->assertNotNull($data['end_time']);
        $this->assertEquals(980, $data['duration_seconds']);
        $this->assertEquals(17, $data['duration_minutes']);
    }

    public function test_speed_networking_show_event_returns_scheduled_end_time_and_duration_minutes(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'viewer@peersunity.com',
        ]);
        Sanctum::actingAs($user);

        $start = Carbon::now()->startOfSecond()->addHours(1);
        $event = SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Show Event Test',
            'status' => 'scheduled',
            'round_duration_seconds' => 180,
            'break_duration_seconds' => 20,
            'total_rounds' => 5,
            'scheduled_start_time' => $start,
            'scheduled_end_time' => null,
        ]);

        $response = $this->getJson("/api/v1/speed-networking/events/{$event->id}");
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotNull($data);
        $this->assertEquals((string) $event->id, $data['id']);
        $this->assertNotNull($data['scheduled_end_time']);
        $this->assertEquals(17, $data['duration_minutes']);
        $this->assertEquals(980, $data['duration_seconds']);
        $expectedEnd = $start->copy()->addSeconds(980)->utc()->toISOString();
        $this->assertEquals($expectedEnd, $data['scheduled_end_time']);
    }

    public function test_active_event_auto_transitions_to_completed_when_past_scheduled_end_time(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'member3@peersunity.com',
        ]);
        Sanctum::actingAs($user);

        $start = Carbon::now()->subHours(2);
        $event = SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Expired Event',
            'status' => 'scheduled',
            'round_duration_seconds' => 180,
            'break_duration_seconds' => 20,
            'total_rounds' => 5,
            'scheduled_start_time' => $start,
            'scheduled_end_time' => $start->copy()->addSeconds(980),
        ]);

        $response = $this->getJson('/api/v1/speed-networking/active-event');
        $response->assertStatus(200);
        $this->assertNull($response->json('data'));

        $event->refresh();
        $this->assertEquals('completed', $event->status);
    }

    public function test_events_all_endpoint_returns_scheduled_end_time_and_duration_minutes(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'eventsall@peersunity.com',
        ]);
        Sanctum::actingAs($user);

        $start = Carbon::now()->addDays(2)->startOfHour();
        $end = $start->copy()->addMinutes(45);

        $event = Event::create([
            'id' => (string) Str::uuid(),
            'title' => 'Virtual Speed Networking Circle Event',
            'event_type' => 'speed_networking',
            'start_at' => $start,
            'end_at' => $end,
            'is_active' => true,
            'status' => 'scheduled',
            'metadata' => [
                'round_duration_seconds' => 300,
                'break_duration_seconds' => 30,
                'total_rounds' => 6,
                'duration_minutes' => 45,
            ],
        ]);

        $response = $this->getJson('/api/v1/events/all');
        $response->assertStatus(200);
        $upcoming = $response->json('data.upcoming_events');
        $this->assertNotEmpty($upcoming);

        $found = collect($upcoming)->firstWhere('event_id', (string) $event->id);
        $this->assertNotNull($found);
        $this->assertEquals(45, $found['duration_minutes']);
        $this->assertEquals($end->copy()->utc()->toISOString(), $found['scheduled_end_time']);
    }

    public function test_admin_creates_event_with_start_time_and_30_min_duration_strictly_sets_end_time_to_30_mins_later(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'duration30@peersunity.com',
        ]);
        Sanctum::actingAs($user);

        $startTime = '2026-10-30T12:30:00Z';
        $payload = [
            'title' => '30 Min Duration Test',
            'scheduled_start_time' => $startTime,
            'duration_minutes' => 30,
            // Even if an incorrect/stale 21:00 scheduled_end_time is passed by client
            'scheduled_end_time' => '2026-10-30T21:00:00Z',
            'round_duration_seconds' => 180,
            'break_duration_seconds' => 20,
            'total_rounds' => 5,
        ];

        $response = $this->postJson('/api/v1/admin/speed-networking/events', $payload);

        $response->assertStatus(201);
        $data = $response->json('data');

        // 12:30 + 30 mins = 13:00, NOT 21:00!
        $expectedEnd = Carbon::parse($startTime)->addMinutes(30)->copy()->utc()->toISOString();
        $this->assertEquals($expectedEnd, Carbon::parse($data['scheduled_end_time'])->copy()->utc()->toISOString());
        $this->assertEquals(30, $data['duration_minutes']);
    }

    public function test_speed_networking_live_tables_returns_scheduled_end_time_and_duration(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'livetables@peersunity.com',
        ]);
        Sanctum::actingAs($user);

        $start = Carbon::parse('2026-10-30T12:30:00Z');
        $event = SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Live Tables Duration Test',
            'status' => 'in_progress',
            'round_duration_seconds' => 180,
            'break_duration_seconds' => 20,
            'total_rounds' => 5,
            'scheduled_start_time' => $start,
            'metadata' => [
                'duration_minutes' => 30,
            ],
        ]);

        $response = $this->getJson("/api/v1/admin/speed-networking/events/{$event->id}/live-tables");

        $response->assertStatus(200);
        $eventData = $response->json('data.event');
        $this->assertNotNull($eventData);
        $this->assertEquals(30, $eventData['duration_minutes']);
        $this->assertEquals('2026-10-30T13:00:00.000000Z', Carbon::parse($eventData['scheduled_end_time'])->copy()->utc()->toISOString());
    }

    public function test_speed_networking_event_with_null_scheduled_end_time_in_db_returns_computed_end_time(): void
    {
        $start = Carbon::parse('2026-10-30T05:41:00Z');
        $event = new SpeedNetworkingEvent;
        $event->setRawAttributes([
            'id' => (string) Str::uuid(),
            'title' => 'Null End Time Event',
            'scheduled_start_time' => '2026-10-30 05:41:00',
            'scheduled_end_time' => null,
            'actual_start_time' => '2026-10-30 05:41:02',
            'actual_end_time' => null,
            'round_duration_seconds' => 180,
            'break_duration_seconds' => 20,
            'total_rounds' => 5,
            'metadata' => json_encode(['duration_minutes' => 30]),
        ], true);

        // Accessor should calculate 05:41 + 30 mins = 06:11:00
        $this->assertNotNull($event->scheduled_end_time);
        $this->assertEquals('2026-10-30T06:11:00.000000Z', $event->scheduled_end_time->copy()->utc()->toISOString());

        $array = $event->toArray();
        $this->assertNotNull($array['scheduled_end_time']);
        $this->assertEquals(30, $array['duration_minutes']);
    }

    public function test_speed_networking_event_with_no_explicit_duration_and_null_end_time_calculates_from_rounds_and_is_never_null(): void
    {
        $event = new SpeedNetworkingEvent;
        $event->setRawAttributes([
            'id' => (string) Str::uuid(),
            'title' => 'No Metadata Event',
            'scheduled_start_time' => '2026-10-30 05:41:00',
            'scheduled_end_time' => null,
            'actual_start_time' => '2026-10-30 05:41:02',
            'actual_end_time' => null,
            'round_duration_seconds' => 180,
            'break_duration_seconds' => 20,
            'total_rounds' => 5,
            'metadata' => null,
        ], true);

        $this->assertNotNull($event->scheduled_end_time);
        // 5 * 180 + 4 * 20 = 980 seconds (16m 20s)
        $expected = Carbon::parse('2026-10-30 05:41:00')->addSeconds(980)->copy()->utc()->toISOString();
        $this->assertEquals($expected, $event->scheduled_end_time->copy()->utc()->toISOString());

        $array = $event->toArray();
        $this->assertNotNull($array['scheduled_end_time']);
        $this->assertEquals($expected, $array['scheduled_end_time']);
    }

    public function test_active_event_endpoint_never_returns_null_scheduled_end_time(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Active',
            'last_name' => 'Member',
            'email' => 'active_never_null@peersunity.com',
        ]);
        Sanctum::actingAs($user);

        $event = SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Active Null End Event',
            'status' => 'scheduled',
            'round_duration_seconds' => 180,
            'break_duration_seconds' => 20,
            'total_rounds' => 5,
            'scheduled_start_time' => Carbon::now()->addHour(),
            'scheduled_end_time' => null,
        ]);

        $response = $this->getJson('/api/v1/speed-networking/active-event');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotNull($data);
        $this->assertNotNull($data['scheduled_end_time']);
        $this->assertNotNull($data['duration_minutes']);
        $this->assertGreaterThan(0, $data['duration_minutes']);
    }
}
