<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
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
        Schema::dropIfExists('speed_networking_matches');
        Schema::dropIfExists('speed_networking_participants');
        Schema::dropIfExists('speed_networking_events');
        Schema::dropIfExists('admin_user_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('users');

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
}
