<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingMatch;
use App\Models\SpeedNetworking\SpeedNetworkingParticipant;
use App\Models\User;
use App\Services\SpeedNetworking\AgoraTokenService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SpeedNetworkingExtendConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        $this->ensureTestTables();

        $agoraMock = Mockery::mock(AgoraTokenService::class);
        $agoraMock->shouldReceive('getAppId')->andReturn('test-agora-app-id');
        $agoraMock->shouldReceive('generateToken')->andReturn('test-agora-token');
        $this->app->instance(AgoraTokenService::class, $agoraMock);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ensureTestTables(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('display_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('company_name')->nullable();
                $table->string('designation')->nullable();
                $table->string('city')->nullable();
                $table->string('profile_photo_url')->nullable();
                $table->integer('coins_balance')->default(0);
                $table->integer('life_impacted_count')->default(0);
                $table->string('status')->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('speed_networking_events')) {
            Schema::create('speed_networking_events', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('banner_url')->nullable();
                $table->string('status')->default('in_progress');
                $table->integer('round_duration_seconds')->default(300);
                $table->integer('break_duration_seconds')->default(20);
                $table->unsignedSmallInteger('max_skip_limit')->default(2);
                $table->unsignedSmallInteger('preview_duration_seconds')->default(15);
                $table->integer('total_rounds')->default(5);
                $table->integer('current_round')->default(1);
                $table->timestamp('scheduled_start_time')->nullable();
                $table->timestamp('scheduled_end_time')->nullable();
                $table->timestamp('actual_start_time')->nullable();
                $table->timestamp('actual_end_time')->nullable();
                $table->decimal('ticket_price', 10, 2)->default(0);
                $table->boolean('is_paid')->default(false);
                $table->string('currency')->default('INR');
                $table->uuid('circle_id')->nullable();
                $table->boolean('circle_restricted')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('speed_networking_participants')) {
            Schema::create('speed_networking_participants', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('event_id')->index();
                $table->uuid('user_id')->index();
                $table->string('status')->default('in_call');
                $table->integer('rounds_completed')->default(0);
                $table->integer('total_talk_time_seconds')->default(0);
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('last_heartbeat_at')->nullable();
                $table->timestamp('break_started_at')->nullable();
                $table->integer('break_remaining_seconds')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('speed_networking_matches')) {
            Schema::create('speed_networking_matches', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('event_id')->index();
                $table->integer('round_number')->default(1);
                $table->integer('table_number')->default(1);
                $table->string('channel_id')->nullable();
                $table->text('agora_token')->nullable();
                $table->uuid('user_a_id')->index();
                $table->uuid('user_b_id')->nullable()->index();
                $table->string('status')->default('active');
                $table->string('match_status', 32)->default('in_call');
                $table->string('user_a_decision', 16)->default('accepted');
                $table->string('user_b_decision', 16)->default('accepted');
                $table->integer('preview_duration_seconds')->default(15);
                $table->timestamp('preview_expires_at')->nullable();
                $table->timestamp('call_started_at')->nullable();
                $table->timestamp('call_expires_at')->nullable();
                $table->boolean('extension_used')->default(false);
                $table->integer('extended_seconds')->default(0);
                $table->timestamp('extended_at')->nullable();
                $table->string('extended_by_user_id', 64)->nullable();
                $table->integer('duration_seconds')->nullable();
                $table->text('user_a_notes')->nullable();
                $table->text('user_b_notes')->nullable();
                $table->text('meeting_notes')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('speed_networking_blocked_peers')) {
            Schema::create('speed_networking_blocked_peers', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('blocker_id', 64);
                $table->string('blocked_id', 64);
                $table->string('event_id', 64)->nullable();
                $table->string('reason', 255)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('speed_networking_event_skips')) {
            Schema::create('speed_networking_event_skips', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('event_id', 64);
                $table->string('user_id', 64);
                $table->string('skipped_peer_id', 64);
                $table->unsignedSmallInteger('skip_count')->default(1);
                $table->timestamps();
            });
        }
    }

    private function createUser(string $name): User
    {
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->first_name = $name;
        $user->display_name = $name;
        $user->email = strtolower($name).'_'.Str::random(4).'@example.com';
        $user->status = 'active';
        $user->save();

        return $user;
    }

    /**
     * @return array{event: SpeedNetworkingEvent, userA: User, userB: User, match: SpeedNetworkingMatch}
     */
    private function createActiveCallFixture(int $roundDurationSeconds, Carbon $startedAt): array
    {
        $userA = $this->createUser('Alice');
        $userB = $this->createUser('Bob');

        $event = SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Speed Collaboration Test',
            'status' => SpeedNetworkingEvent::STATUS_IN_PROGRESS,
            'round_duration_seconds' => $roundDurationSeconds,
            'break_duration_seconds' => 20,
            'total_rounds' => 5,
            'current_round' => 1,
            'scheduled_start_time' => $startedAt->copy()->subMinutes(5),
            'scheduled_end_time' => $startedAt->copy()->addMinutes(45),
            'actual_start_time' => $startedAt->copy()->subMinutes(5),
            'is_paid' => false,
            'ticket_price' => 0,
        ]);

        foreach ([$userA, $userB] as $u) {
            SpeedNetworkingParticipant::create([
                'id' => (string) Str::uuid(),
                'event_id' => $event->id,
                'user_id' => $u->id,
                'status' => SpeedNetworkingParticipant::STATUS_IN_CALL,
                'joined_at' => $startedAt->copy()->subMinutes(2),
                'last_heartbeat_at' => $startedAt->copy(),
            ]);
        }

        $match = SpeedNetworkingMatch::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'round_number' => 1,
            'table_number' => 1,
            'channel_id' => 'sn_test_channel_1',
            'user_a_id' => $userA->id,
            'user_b_id' => $userB->id,
            'status' => SpeedNetworkingMatch::STATUS_ACTIVE,
            'match_status' => SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
            'user_a_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'user_b_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'call_started_at' => $startedAt->copy(),
            'call_expires_at' => $startedAt->copy()->addSeconds($roundDurationSeconds),
            'started_at' => $startedAt->copy(),
        ]);

        return compact('event', 'userA', 'userB', 'match');
    }

    public function test_extension_at_beginning_middle_and_near_end_preserves_remaining_time(): void
    {
        // Scenario A: Beginning (30s elapsed of 5m call -> remaining 270s -> after +60s = 330s)
        $startA = Carbon::parse('2026-11-27 10:00:00');
        Carbon::setTestNow($startA->copy()->addSeconds(30));
        $fixtureA = $this->createActiveCallFixture(300, $startA);

        Sanctum::actingAs($fixtureA['userA']);
        $resA = $this->postJson("/api/v1/speed-networking/events/{$fixtureA['event']->id}/extend-connection", [
            'match_id' => $fixtureA['match']->id,
        ]);

        $expectedExpiryA = $startA->copy()->addMinutes(6)->utc()->toISOString();
        $resA->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'extended')
            ->assertJsonPath('data.extension_used', true)
            ->assertJsonPath('data.extended_seconds', 60)
            ->assertJsonPath('data.can_extend', false)
            ->assertJsonPath('data.remaining_seconds', 330)
            ->assertJsonPath('data.call_expires_at', $expectedExpiryA);

        // Scenario B: Middle (2m elapsed of 5m call -> remaining 180s -> after +60s = 240s)
        $startB = Carbon::parse('2026-11-27 10:00:00');
        Carbon::setTestNow($startB->copy()->addMinutes(2));
        $fixtureB = $this->createActiveCallFixture(300, $startB);

        Sanctum::actingAs($fixtureB['userB']);
        $resB = $this->postJson("/api/v1/events/{$fixtureB['event']->id}/speed-networking/extend-connection");

        $expectedExpiryB = $startB->copy()->addMinutes(6)->utc()->toISOString();
        $resB->assertOk()
            ->assertJsonPath('data.remaining_seconds', 240)
            ->assertJsonPath('data.call_expires_at', $expectedExpiryB);

        // Scenario C: Near end (4m40s elapsed -> remaining 20s -> after +60s = 80s)
        $startC = Carbon::parse('2026-11-27 10:00:00');
        Carbon::setTestNow($startC->copy()->addMinutes(4)->addSeconds(40));
        $fixtureC = $this->createActiveCallFixture(300, $startC);

        Sanctum::actingAs($fixtureC['userA']);
        $resC = $this->postJson("/api/v1/speed-networking/events/{$fixtureC['event']->id}/extend-connection");

        $expectedExpiryC = $startC->copy()->addMinutes(6)->utc()->toISOString();
        $resC->assertOk()
            ->assertJsonPath('data.remaining_seconds', 80)
            ->assertJsonPath('data.call_expires_at', $expectedExpiryC);
    }

    public function test_dynamic_initial_durations_are_preserved(): void
    {
        foreach ([180, 300, 600] as $duration) {
            $start = Carbon::parse('2026-11-27 11:00:00');
            Carbon::setTestNow($start->copy()->addSeconds(10));
            $fixture = $this->createActiveCallFixture($duration, $start);

            Sanctum::actingAs($fixture['userA']);
            $res = $this->postJson("/api/v1/speed-networking/events/{$fixture['event']->id}/extend-connection");

            $expectedExpiry = $start->copy()->addSeconds($duration + 60)->utc()->toISOString();
            $res->assertOk()
                ->assertJsonPath('data.call_expires_at', $expectedExpiry)
                ->assertJsonPath('data.remaining_seconds', ($duration - 10) + 60);
        }
    }

    public function test_only_one_extension_allowed_shared_between_both_participants_and_both_receive_updated_timer(): void
    {
        $start = Carbon::parse('2026-11-27 12:00:00');
        Carbon::setTestNow($start->copy()->addSeconds(60));
        $fixture = $this->createActiveCallFixture(300, $start);
        $expectedExpiry = $start->copy()->addMinutes(6)->utc()->toISOString();

        // Before extension, Participant B sees can_extend = true via state
        Sanctum::actingAs($fixture['userB']);
        $this->getJson("/api/v1/speed-networking/events/{$fixture['event']->id}/state")
            ->assertOk()
            ->assertJsonPath('data.match.can_extend', true)
            ->assertJsonPath('data.match.extension_used', false)
            ->assertJsonPath('data.match.remaining_seconds', 240);

        // Participant A requests extension
        Sanctum::actingAs($fixture['userA']);
        $this->postJson("/api/v1/speed-networking/events/{$fixture['event']->id}/extend-connection")
            ->assertOk()
            ->assertJsonPath('data.call_expires_at', $expectedExpiry)
            ->assertJsonPath('data.remaining_seconds', 300)
            ->assertJsonPath('data.can_extend', false);

        // Participant A tries again -> rejected 409, expiry unchanged
        $this->postJson("/api/v1/speed-networking/events/{$fixture['event']->id}/extend-connection")
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_extended')
            ->assertJsonPath('data.call_expires_at', $expectedExpiry)
            ->assertJsonPath('data.can_extend', false);

        // Participant B tries to extend -> rejected 409, expiry unchanged
        Sanctum::actingAs($fixture['userB']);
        $this->postJson("/api/v1/speed-networking/events/{$fixture['event']->id}/extend-connection")
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_extended')
            ->assertJsonPath('data.call_expires_at', $expectedExpiry)
            ->assertJsonPath('data.can_extend', false);

        // Participant B polls state -> sees updated expiry, remaining_seconds=300, extension_used=true, can_extend=false
        $this->getJson("/api/v1/speed-networking/events/{$fixture['event']->id}/state")
            ->assertOk()
            ->assertJsonPath('data.match.call_expires_at', $expectedExpiry)
            ->assertJsonPath('data.match.remaining_seconds', 300)
            ->assertJsonPath('data.match.extension_used', true)
            ->assertJsonPath('data.match.extended_seconds', 60)
            ->assertJsonPath('data.match.can_extend', false);
    }

    public function test_expired_and_inactive_connections_cannot_be_extended(): void
    {
        $start = Carbon::parse('2026-11-27 13:00:00');
        $fixture = $this->createActiveCallFixture(300, $start);

        // Move past expiry (13:05:01)
        Carbon::setTestNow($start->copy()->addSeconds(301));
        Sanctum::actingAs($fixture['userA']);

        $this->postJson("/api/v1/speed-networking/events/{$fixture['event']->id}/extend-connection", [
            'match_id' => $fixture['match']->id,
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'connection_expired');

        // Completed match cannot be extended
        $fixture['match']->update([
            'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
            'match_status' => SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
            'ended_at' => $start->copy()->addSeconds(300),
        ]);

        Carbon::setTestNow($start->copy()->addSeconds(100));
        $this->postJson("/api/v1/speed-networking/events/{$fixture['event']->id}/extend-connection", [
            'match_id' => $fixture['match']->id,
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'connection_inactive');
    }
}
