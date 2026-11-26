<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SpeedNetworking\SpeedNetworkingBlockedPeer;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingEventSkip;
use App\Models\SpeedNetworking\SpeedNetworkingMatch;
use App\Models\SpeedNetworking\SpeedNetworkingParticipant;
use App\Models\User;
use App\Services\SpeedNetworking\AgoraTokenService;
use App\Services\SpeedNetworking\SpeedNetworkingService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SpeedNetworkingHardeningAndConcurrencyTest extends TestCase
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
                $table->string('status')->default('searching');
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
                $table->string('match_status', 32)->default('preview');
                $table->string('user_a_decision', 16)->default('pending');
                $table->string('user_b_decision', 16)->default('pending');
                $table->integer('preview_duration_seconds')->default(15);
                $table->timestamp('preview_expires_at')->nullable();
                $table->timestamp('call_started_at')->nullable();
                $table->timestamp('call_expires_at')->nullable();
                $table->integer('duration_seconds')->nullable();
                $table->text('user_a_notes')->nullable();
                $table->text('user_b_notes')->nullable();
                $table->text('meeting_notes')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('speed_networking_event_skips')) {
            Schema::create('speed_networking_event_skips', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('event_id', 64)->index();
                $table->string('user_id', 64)->index();
                $table->string('skipped_peer_id', 64)->index();
                $table->unsignedSmallInteger('skip_count')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('speed_networking_blocked_peers')) {
            Schema::create('speed_networking_blocked_peers', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('blocker_id', 64)->index();
                $table->string('blocked_id', 64)->index();
                $table->string('event_id', 64)->nullable()->index();
                $table->string('reason', 255)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('life_impact_histories')) {
            Schema::create('life_impact_histories', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->uuid('triggered_by_user_id')->nullable();
                $table->string('activity_type')->nullable();
                $table->string('source_type')->nullable();
                $table->string('source_id')->nullable();
                $table->integer('impact_value')->default(1);
                $table->integer('life_impacted')->default(1);
                $table->string('action')->nullable();
                $table->text('description')->nullable();
                $table->string('status')->default('approved');
                $table->boolean('counted_in_total')->default(true);
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    private function createTestUser(string $name): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => $name,
            'display_name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'.'.Str::random(5).'@example.com',
            'company_name' => $name.' Ventures',
            'designation' => 'Founder',
            'city' => 'Ahmedabad',
            'status' => 'active',
        ]);
    }

    private function createLiveEvent(int $roundDurationSeconds = 300, int $maxSkipLimit = 2): SpeedNetworkingEvent
    {
        return SpeedNetworkingEvent::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Speed Collaboration Hardened',
            'status' => SpeedNetworkingEvent::STATUS_IN_PROGRESS,
            'round_duration_seconds' => $roundDurationSeconds,
            'break_duration_seconds' => 20,
            'max_skip_limit' => $maxSkipLimit,
            'preview_duration_seconds' => 15,
            'total_rounds' => 5,
            'current_round' => 1,
            'scheduled_start_time' => now()->subMinutes(5),
            'scheduled_end_time' => now()->addMinutes(45),
            'actual_start_time' => now()->subMinutes(5),
            'is_paid' => false,
            'ticket_price' => 0,
        ]);
    }

    public function test_preview_timeout_auto_connects_when_neither_user_skips_or_blocks(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 07:00:00 UTC'));

        $event = $this->createLiveEvent(300);
        $userA = $this->createTestUser('Alpha');
        $userB = $this->createTestUser('Beta');

        Sanctum::actingAs($userA);
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();

        Sanctum::actingAs($userB);
        $joinB = $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();
        $matchId = (string) $joinB->json('data.match_id');
        $channelId = (string) $joinB->json('data.channel_id');

        $this->assertNotEmpty($joinB->json('data.preview_expires_at'));
        $this->assertNotEmpty($joinB->json('data.server_time'));
        $this->assertTrue((bool) $joinB->json('data.auto_connect_on_preview_timeout'));

        // User B accepts, User A does nothing -> countdown remains active without pushing out expiry
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'connect',
            'match_id' => $matchId,
            'channel_id' => $channelId,
        ])->assertOk()->assertJsonPath('data.match_status', 'preview');

        // Advance past 15s preview_expires_at without User A skipping or blocking
        Carbon::setTestNow(Carbon::now()->addSeconds(16));

        // Heartbeat / sweep automatically connects both users into in_call
        Sanctum::actingAs($userA);
        $hbA = $this->postJson("/api/v1/speed-networking/events/{$event->id}/heartbeat", [
            'user_status' => 'preview',
        ]);
        $hbA->assertOk()
            ->assertJsonPath('has_active_match', true)
            ->assertJsonPath('match.match_id', $matchId)
            ->assertJsonPath('match.match_status', 'in_call')
            ->assertJsonPath('match.my_decision', 'accepted')
            ->assertJsonPath('match.peer_decision', 'accepted')
            ->assertJsonPath('match.both_accepted', true);

        $match = SpeedNetworkingMatch::findOrFail($matchId);
        $this->assertSame(SpeedNetworkingMatch::MATCH_STATUS_IN_CALL, $match->match_status);
        $this->assertSame(SpeedNetworkingMatch::STATUS_ACTIVE, $match->status);
        $this->assertNotNull($match->call_started_at);
        $this->assertNotNull($match->call_expires_at);

        $this->assertSame(
            SpeedNetworkingParticipant::STATUS_IN_CALL,
            SpeedNetworkingParticipant::where('event_id', $event->id)->where('user_id', $userA->id)->value('status')
        );
        $this->assertSame(
            SpeedNetworkingParticipant::STATUS_IN_CALL,
            SpeedNetworkingParticipant::where('event_id', $event->id)->where('user_id', $userB->id)->value('status')
        );
    }

    public function test_skip_wins_before_preview_timeout_and_routes_to_find_next_peer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 07:05:00 UTC'));

        $event = $this->createLiveEvent(300, 1);
        $user1 = $this->createTestUser('Peer One');
        $user2 = $this->createTestUser('Peer Two');
        $user3 = $this->createTestUser('Peer Three');

        Sanctum::actingAs($user1);
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();

        Sanctum::actingAs($user2);
        $join2 = $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();
        $matchId = (string) $join2->json('data.match_id');

        // Peer 2 presses Connect before timer ends
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'connect',
            'match_id' => $matchId,
        ])->assertOk()->assertJsonPath('data.match_status', 'preview');

        // Peer 1 presses Skip before 15s ends -> Skip wins, routes to Find Next Peer screen
        Sanctum::actingAs($user1);
        $skipRes = $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'skip',
            'match_id' => $matchId,
            'peer_id' => (string) $user2->id,
        ]);
        $skipRes->assertOk()
            ->assertJsonPath('data.match_status', 'skipped')
            ->assertJsonPath('data.next_step', 'next_peer_required')
            ->assertJsonPath('data.skip_count', 1)
            ->assertJsonPath('data.max_skip_limit', 1);

        $this->assertSame(
            SpeedNetworkingParticipant::STATUS_NEXT_PEER_REQUIRED,
            SpeedNetworkingParticipant::where('event_id', $event->id)->where('user_id', $user1->id)->value('status')
        );

        // Peer 2 leaves so Peer 3 pairs specifically with Peer 1 when Peer 1 taps Find Next Peer
        Sanctum::actingAs($user2);
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/leave")->assertOk();

        Sanctum::actingAs($user3);
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();

        Sanctum::actingAs($user1);
        $next1 = $this->postJson("/api/v1/speed-networking/events/{$event->id}/next-match");
        $next1->assertOk()
            ->assertJsonPath('is_waiting', false)
            ->assertJsonPath('data.match_status', 'preview')
            ->assertJsonPath('data.peer.id', (string) $user3->id);
    }

    public function test_early_next_peer_after_two_minutes_qualifies_p2p_and_searches_directly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 07:10:00 UTC'));

        $event = $this->createLiveEvent(300, 2);
        $user1 = $this->createTestUser('Caller One');
        $user3 = $this->createTestUser('Partner Three');
        $user4 = $this->createTestUser('Next Four');

        SpeedNetworkingParticipant::create([
            'event_id' => $event->id,
            'user_id' => $user1->id,
            'status' => SpeedNetworkingParticipant::STATUS_IN_CALL,
            'joined_at' => now()->subMinutes(3),
            'last_heartbeat_at' => now(),
        ]);
        SpeedNetworkingParticipant::create([
            'event_id' => $event->id,
            'user_id' => $user3->id,
            'status' => SpeedNetworkingParticipant::STATUS_IN_CALL,
            'joined_at' => now()->subMinutes(3),
            'last_heartbeat_at' => now(),
        ]);
        SpeedNetworkingParticipant::create([
            'event_id' => $event->id,
            'user_id' => $user4->id,
            'status' => SpeedNetworkingParticipant::STATUS_SEARCHING,
            'joined_at' => now(),
            'last_heartbeat_at' => now(),
        ]);

        // Active call between user1 and user3 that has lasted 130 seconds (> 2 minutes)
        $match = SpeedNetworkingMatch::create([
            'event_id' => $event->id,
            'round_number' => 1,
            'table_number' => 1,
            'channel_id' => 'sn_early_next_1',
            'user_a_id' => $user1->id,
            'user_b_id' => $user3->id,
            'status' => SpeedNetworkingMatch::STATUS_ACTIVE,
            'match_status' => SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
            'user_a_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'user_b_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'call_started_at' => now()->subSeconds(130),
            'call_expires_at' => now()->addSeconds(170),
            'started_at' => now()->subSeconds(130),
        ]);

        // User 1 taps Next Peer directly before the full 300s round expires
        Sanctum::actingAs($user1);
        $nextRes = $this->postJson("/api/v1/speed-networking/events/{$event->id}/next-match");
        $nextRes->assertOk()
            ->assertJsonPath('is_waiting', false)
            ->assertJsonPath('data.match_status', 'preview')
            ->assertJsonPath('data.peer.id', (string) $user4->id);

        $match->refresh();
        $this->assertSame(SpeedNetworkingMatch::MATCH_STATUS_COMPLETED, $match->match_status);
        $this->assertGreaterThanOrEqual(120, (int) $match->duration_seconds);

        // Partner 3 was also freed from IN_CALL into SEARCHING
        $this->assertSame(
            SpeedNetworkingParticipant::STATUS_SEARCHING,
            SpeedNetworkingParticipant::where('event_id', $event->id)->where('user_id', $user3->id)->value('status')
        );
    }

    public function test_five_person_flow_recycles_skipped_peer_fifo_up_to_dynamic_admin_skip_limit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 07:20:00 UTC'));

        $event = $this->createLiveEvent(300, 2);
        $u1 = $this->createTestUser('User One');
        $u2 = $this->createTestUser('User Two');
        $u3 = $this->createTestUser('User Three');
        $u4 = $this->createTestUser('User Four');
        $u5 = $this->createTestUser('User Five');

        foreach ([$u1, $u2, $u3, $u4, $u5] as $u) {
            SpeedNetworkingParticipant::create([
                'event_id' => $event->id,
                'user_id' => $u->id,
                'status' => SpeedNetworkingParticipant::STATUS_SEARCHING,
                'joined_at' => now(),
                'last_heartbeat_at' => now(),
            ]);
        }

        // U1 skipped U2 once (oldest skip in FIFO) and skipped U4 earlier/later, wait: test U1 blocked U4, completed calls with U3 and U5
        SpeedNetworkingEventSkip::create([
            'event_id' => $event->id,
            'user_id' => $u1->id,
            'skipped_peer_id' => $u2->id,
            'skip_count' => 1,
            'created_at' => now()->subSeconds(30),
            'updated_at' => now()->subSeconds(30),
        ]);

        SpeedNetworkingBlockedPeer::create([
            'blocker_id' => $u1->id,
            'blocked_id' => $u4->id,
            'event_id' => $event->id,
            'reason' => 'Blocked',
        ]);

        foreach ([$u3, $u5] as $idx => $metUser) {
            SpeedNetworkingMatch::create([
                'event_id' => $event->id,
                'round_number' => $idx + 1,
                'table_number' => $idx + 1,
                'channel_id' => 'sn_met_'.$idx,
                'user_a_id' => $u1->id,
                'user_b_id' => $metUser->id,
                'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
                'match_status' => SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
                'user_a_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
                'user_b_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
                'duration_seconds' => 150,
                'call_started_at' => now()->subMinutes(5),
                'ended_at' => now()->subMinutes(2),
            ]);
        }

        // No fresh unskipped/unmet/unblocked peers remain for U1 -> FIFO recycles skipped U2!
        Sanctum::actingAs($u1);
        $recycledRes = $this->postJson("/api/v1/speed-networking/events/{$event->id}/next-match");
        $recycledRes->assertOk()
            ->assertJsonPath('is_waiting', false)
            ->assertJsonPath('data.match_status', 'preview')
            ->assertJsonPath('data.peer.id', (string) $u2->id)
            ->assertJsonPath('data.is_recycled_skip', true)
            ->assertJsonPath('data.skip_count', 1)
            ->assertJsonPath('data.max_skip_limit', 2);

        $recycledMatchId = (string) $recycledRes->json('data.match_id');

        // U1 skips U2 a 2nd time -> skip_count becomes 2 == max_skip_limit(2)
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'skip',
            'match_id' => $recycledMatchId,
            'peer_id' => (string) $u2->id,
        ])->assertOk()
            ->assertJsonPath('data.skip_count', 2)
            ->assertJsonPath('data.max_skip_limit', 2);

        // Next match must NOT show U2 (or blocked U4) because max_skip_limit=2 is reached
        $afterCap = $this->postJson("/api/v1/speed-networking/events/{$event->id}/next-match");
        $afterCap->assertOk()
            ->assertJsonPath('is_waiting', true)
            ->assertJsonPath('match', null);

        // Admin dynamically raises max_skip_limit to 3 -> U2 becomes eligible again
        $admin = $this->createTestUser('Admin User');
        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/admin/speed-networking/events/{$event->id}", [
            'max_skip_limit' => 3,
        ])->assertOk()
            ->assertJsonPath('success', true);

        Sanctum::actingAs($u1);
        $reopened = $this->postJson("/api/v1/speed-networking/events/{$event->id}/next-match");
        $reopened->assertOk()
            ->assertJsonPath('is_waiting', false)
            ->assertJsonPath('data.peer.id', (string) $u2->id)
            ->assertJsonPath('data.skip_count', 2)
            ->assertJsonPath('data.max_skip_limit', 3);
    }

    public function test_admin_live_controls_pause_extend_time_reset_skips_and_moderation_kpis(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 07:30:00 UTC'));

        $event = $this->createLiveEvent(300, 2);
        $admin = $this->createTestUser('Founder Host');
        $u1 = $this->createTestUser('Attendee A');
        $u2 = $this->createTestUser('Attendee B');

        Sanctum::actingAs($u1);
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();
        Sanctum::actingAs($u2);
        $join2 = $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();
        $matchId = (string) $join2->json('data.match_id');

        $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'skip',
            'match_id' => $matchId,
            'peer_id' => (string) $u1->id,
        ])->assertOk();

        // Admin pauses the session -> live-tables reports is_paused=true & live_metrics, and users wait
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/speed-networking/events/{$event->id}/pause")
            ->assertOk()
            ->assertJsonPath('data.status', 'paused');

        $livePaused = $this->getJson("/api/v1/admin/speed-networking/events/{$event->id}/live-tables");
        $livePaused->assertOk()
            ->assertJsonPath('data.is_paused', true)
            ->assertJsonPath('data.event.max_skip_limit', 2)
            ->assertJsonPath('data.live_metrics.total_skips_count', 1);

        // Extend time by 10 minutes
        $origEnd = $event->fresh()->scheduled_end_time;
        $this->postJson("/api/v1/admin/speed-networking/events/{$event->id}/extend-time", [
            'additional_minutes' => 10,
        ])->assertOk();
        $this->assertTrue($event->fresh()->scheduled_end_time->greaterThan($origEnd));

        // Moderation endpoint lists skip record; reset-skips clears it
        $this->getJson("/api/v1/admin/speed-networking/events/{$event->id}/moderation")
            ->assertOk()
            ->assertJsonCount(1, 'data.skips');

        $this->postJson("/api/v1/admin/speed-networking/events/{$event->id}/reset-skips")
            ->assertOk()
            ->assertJsonPath('data.cleared_count', 1);

        // Resume session
        $this->postJson("/api/v1/admin/speed-networking/events/{$event->id}/resume")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_skip_takes_precedence_over_single_sided_preview_accept(): void
    {
        $event = $this->createLiveEvent();
        $userA = $this->createTestUser('Alpha');
        $userB = $this->createTestUser('Beta');

        Sanctum::actingAs($userA);
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();

        Sanctum::actingAs($userB);
        $joinB = $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();
        $matchId = (string) $joinB->json('data.match_id');
        $channelId = (string) $joinB->json('data.channel_id');

        // User B presses Connect first (match remains uncommitted 'preview')
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'connect',
            'match_id' => $matchId,
            'channel_id' => $channelId,
        ])->assertOk()->assertJsonPath('data.match_status', 'preview');

        // User A presses Skip -> Skip wins!
        Sanctum::actingAs($userA);
        $skipRes = $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'skip',
            'match_id' => $matchId,
            'channel_id' => $channelId,
        ]);
        $skipRes->assertOk()
            ->assertJsonPath('data.match_status', 'skipped')
            ->assertJsonPath('data.my_decision', 'skip')
            ->assertJsonPath('data.peer_decision', 'accepted')
            ->assertJsonPath('data.both_accepted', false)
            ->assertJsonPath('data.next_step', 'next_peer_required');

        // User B heartbeat reports peer_decision = skip
        Sanctum::actingAs($userB);
        $hbB = $this->postJson("/api/v1/speed-networking/events/{$event->id}/heartbeat", [
            'user_status' => 'accepted',
        ]);
        $hbB->assertOk()
            ->assertJsonPath('has_active_match', false)
            ->assertJsonPath('match.match_status', 'skipped')
            ->assertJsonPath('match.peer_decision', 'skip')
            ->assertJsonPath('match.next_step', 'next_peer_required');
    }

    public function test_skip_returns_409_conflict_when_match_already_in_call(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 07:15:00 UTC'));

        $event = $this->createLiveEvent(300);
        $userA = $this->createTestUser('Alpha');
        $userB = $this->createTestUser('Beta');

        Sanctum::actingAs($userA);
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();

        Sanctum::actingAs($userB);
        $joinB = $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();
        $matchId = (string) $joinB->json('data.match_id');

        // Both accept -> in_call
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'connect',
            'match_id' => $matchId,
        ])->assertOk();

        Sanctum::actingAs($userA);
        $connectA = $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'connect',
            'match_id' => $matchId,
        ]);
        $connectA->assertOk()
            ->assertJsonPath('data.match_status', 'in_call')
            ->assertJsonPath('data.both_accepted', true)
            ->assertJsonPath('data.round_duration_seconds', 300)
            ->assertJsonPath('data.call_started_at', '2026-11-26T07:15:00.000000Z')
            ->assertJsonPath('data.call_expires_at', '2026-11-26T07:20:00.000000Z');

        // Late skip on active in_call returns 409 Conflict with authoritative match payload
        Sanctum::actingAs($userB);
        $lateSkip = $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'skip',
            'match_id' => $matchId,
        ]);
        $lateSkip->assertStatus(409)
            ->assertJsonPath('status', 'conflict')
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.match_status', 'in_call')
            ->assertJsonPath('data.both_accepted', true)
            ->assertJsonPath('data.call_expires_at', '2026-11-26T07:20:00.000000Z');

        // DB row stays in_call
        $this->assertSame(SpeedNetworkingMatch::MATCH_STATUS_IN_CALL, SpeedNetworkingMatch::findOrFail($matchId)->match_status);
    }

    public function test_block_always_wins_over_connect_and_cancels_in_call(): void
    {
        $event = $this->createLiveEvent();
        $userA = $this->createTestUser('Alpha');
        $userB = $this->createTestUser('Beta');

        Sanctum::actingAs($userA);
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();

        Sanctum::actingAs($userB);
        $joinB = $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();
        $matchId = (string) $joinB->json('data.match_id');

        // User B accepts first
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'connect',
            'match_id' => $matchId,
        ])->assertOk();

        // User A blocks User B -> permanently blocked, no auto-match
        Sanctum::actingAs($userA);
        $blockRes = $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'block',
            'peer_id' => (string) $userB->id,
            'match_id' => $matchId,
        ]);
        $blockRes->assertOk()
            ->assertJsonPath('data.match_status', 'blocked')
            ->assertJsonPath('data.my_decision', 'block')
            ->assertJsonPath('data.next_step', 'find_next_immediately');

        $this->assertSame(
            SpeedNetworkingParticipant::STATUS_SEARCHING,
            SpeedNetworkingParticipant::where('event_id', $event->id)->where('user_id', $userA->id)->value('status')
        );

        $this->assertTrue(
            SpeedNetworkingBlockedPeer::where('blocker_id', $userA->id)->where('blocked_id', $userB->id)->exists()
        );

        // Retried connect by User B sees blocked, never in_call
        Sanctum::actingAs($userB);
        $retryB = $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'connect',
            'match_id' => $matchId,
        ]);
        $retryB->assertOk()
            ->assertJsonPath('data.match_status', 'blocked')
            ->assertJsonPath('data.peer_decision', 'block')
            ->assertJsonPath('data.both_accepted', false);
    }

    public function test_heartbeat_respects_next_peer_required_and_idle_without_auto_matching(): void
    {
        $event = $this->createLiveEvent();
        $userA = $this->createTestUser('Alpha');
        $userB = $this->createTestUser('Beta');
        $userC = $this->createTestUser('Gamma');

        Sanctum::actingAs($userA);
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();

        Sanctum::actingAs($userB);
        $joinB = $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();
        $matchId = (string) $joinB->json('data.match_id');

        // User B skips User A -> moves to next_peer_required
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'skip',
            'match_id' => $matchId,
        ])->assertOk();

        // User C joins -> User A is searching, wait: User C pairs with User A. Let's make User D available too!
        Sanctum::actingAs($userC);
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();

        $userD = $this->createTestUser('Delta');
        Sanctum::actingAs($userD);
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();

        // User B sends heartbeats with next_peer_required and idle -> must NOT auto-match with Delta!
        Sanctum::actingAs($userB);
        $hb1 = $this->postJson("/api/v1/speed-networking/events/{$event->id}/heartbeat", [
            'user_status' => 'next_peer_required',
        ]);
        $hb1->assertOk()
            ->assertJsonPath('is_waiting', true)
            ->assertJsonPath('has_active_match', false)
            ->assertJsonPath('match', null)
            ->assertJsonPath('data.match_status', 'next_peer_required');

        $hb2 = $this->postJson("/api/v1/speed-networking/events/{$event->id}/heartbeat", [
            'user_status' => 'idle',
        ]);
        $hb2->assertOk()
            ->assertJsonPath('is_waiting', true)
            ->assertJsonPath('has_active_match', false)
            ->assertJsonPath('match', null);

        // Even an empty-status heartbeat while DB status is next_peer_required/idle must not auto-match
        $hb3 = $this->postJson("/api/v1/speed-networking/events/{$event->id}/heartbeat");
        $hb3->assertOk()
            ->assertJsonPath('has_active_match', false);

        // Explicit POST /next-match DOES pair User B with Delta
        $nextB = $this->postJson("/api/v1/speed-networking/events/{$event->id}/next-match");
        $nextB->assertOk()
            ->assertJsonPath('is_waiting', false)
            ->assertJsonPath('data.match_status', 'preview')
            ->assertJsonPath('data.peer.id', (string) $userD->id);
    }

    public function test_live_split_counts_exclude_expired_meetings_and_separate_in_call_from_available(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 07:10:00 UTC'));

        $event = $this->createLiveEvent(300);
        $inCallA = $this->createTestUser('InCall A');
        $inCallB = $this->createTestUser('InCall B');
        $waitingC = $this->createTestUser('Waiting C');
        $waitingD = $this->createTestUser('Waiting D');
        $staleE = $this->createTestUser('Stale E');
        $staleF = $this->createTestUser('Stale F');

        foreach ([$inCallA, $inCallB] as $u) {
            SpeedNetworkingParticipant::create([
                'event_id' => $event->id,
                'user_id' => $u->id,
                'status' => SpeedNetworkingParticipant::STATUS_IN_CALL,
                'joined_at' => now()->subMinutes(2),
                'last_heartbeat_at' => now()->subSeconds(5),
            ]);
        }

        foreach ([$waitingC, $waitingD] as $u) {
            SpeedNetworkingParticipant::create([
                'event_id' => $event->id,
                'user_id' => $u->id,
                'status' => SpeedNetworkingParticipant::STATUS_SEARCHING,
                'joined_at' => now()->subMinutes(1),
                'last_heartbeat_at' => now()->subSeconds(5),
            ]);
        }

        foreach ([$staleE, $staleF] as $u) {
            SpeedNetworkingParticipant::create([
                'event_id' => $event->id,
                'user_id' => $u->id,
                'status' => SpeedNetworkingParticipant::STATUS_IN_CALL,
                'joined_at' => now()->subMinutes(10),
                'last_heartbeat_at' => now()->subSeconds(5),
            ]);
        }

        // Active non-expired call
        SpeedNetworkingMatch::create([
            'event_id' => $event->id,
            'round_number' => 1,
            'table_number' => 1,
            'channel_id' => 'sn_active_1',
            'user_a_id' => $inCallA->id,
            'user_b_id' => $inCallB->id,
            'status' => SpeedNetworkingMatch::STATUS_ACTIVE,
            'match_status' => SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
            'user_a_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'user_b_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'call_started_at' => now()->subMinutes(1),
            'call_expires_at' => now()->addMinutes(4),
            'started_at' => now()->subMinutes(1),
        ]);

        // Stale expired call (still marked in_call in DB before sweep)
        SpeedNetworkingMatch::create([
            'event_id' => $event->id,
            'round_number' => 1,
            'table_number' => 2,
            'channel_id' => 'sn_stale_2',
            'user_a_id' => $staleE->id,
            'user_b_id' => $staleF->id,
            'status' => SpeedNetworkingMatch::STATUS_ACTIVE,
            'match_status' => SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
            'user_a_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'user_b_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'call_started_at' => now()->subMinutes(6),
            'call_expires_at' => now()->subMinute(),
            'started_at' => now()->subMinutes(6),
        ]);

        Sanctum::actingAs($waitingC);
        $hb = $this->postJson("/api/v1/speed-networking/events/{$event->id}/heartbeat", [
            'user_status' => 'next_peer_required',
        ]);

        // Stale call was swept; waitingC is next_peer_required so only waitingD is available; inCallA & inCallB are in meetings
        $hb->assertOk()
            ->assertJsonPath('peers_in_meetings_count', 2)
            ->assertJsonPath('available_peers_count', 1)
            ->assertJsonPath('active_attendees_count', 6)
            ->assertJsonPath('data.peers_in_meetings_count', 2)
            ->assertJsonPath('data.available_peers_count', 1)
            ->assertJsonPath('data.active_attendees_count', 6);
    }

    public function test_scheduled_sweep_finalizes_expired_calls_and_state_reconciles_offline_peer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 07:00:00 UTC'));

        $event = $this->createLiveEvent(300);
        $userA = $this->createTestUser('Offline A');
        $userB = $this->createTestUser('Offline B');

        $match = SpeedNetworkingMatch::create([
            'event_id' => $event->id,
            'round_number' => 1,
            'table_number' => 1,
            'channel_id' => 'sn_sweep_test',
            'user_a_id' => $userA->id,
            'user_b_id' => $userB->id,
            'status' => SpeedNetworkingMatch::STATUS_ACTIVE,
            'match_status' => SpeedNetworkingMatch::MATCH_STATUS_IN_CALL,
            'user_a_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'user_b_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'call_started_at' => now(),
            'call_expires_at' => now()->addSeconds(300),
            'started_at' => now(),
        ]);

        SpeedNetworkingParticipant::create([
            'event_id' => $event->id,
            'user_id' => $userA->id,
            'status' => SpeedNetworkingParticipant::STATUS_IN_CALL,
            'joined_at' => now(),
            'last_heartbeat_at' => now(),
        ]);
        SpeedNetworkingParticipant::create([
            'event_id' => $event->id,
            'user_id' => $userB->id,
            'status' => SpeedNetworkingParticipant::STATUS_IN_CALL,
            'joined_at' => now(),
            'last_heartbeat_at' => now(),
        ]);

        // Advance past call_expires_at while both peers are offline
        Carbon::setTestNow(Carbon::parse('2026-11-26 07:05:05 UTC'));

        $this->artisan('speed-networking:sweep-expired')
            ->assertExitCode(0);

        $match->refresh();
        $this->assertSame(SpeedNetworkingMatch::STATUS_COMPLETED, $match->status);
        $this->assertSame(SpeedNetworkingMatch::MATCH_STATUS_COMPLETED, $match->match_status);
        $this->assertSame(300, $match->duration_seconds);
        $this->assertSame('2026-11-26T07:05:00.000000Z', $match->ended_at->copy()->utc()->toISOString());

        // Offline peer reconnects via GET /state and GET /next-match without creating duplicate match rows
        Sanctum::actingAs($userA);
        $stateRes = $this->getJson("/api/v1/speed-networking/events/{$event->id}/state");
        $stateRes->assertOk()
            ->assertJsonPath('state', 'next_peer_required')
            ->assertJsonPath('has_active_match', false)
            ->assertJsonPath('summary_available', true)
            ->assertJsonPath('round_duration_seconds', 300)
            ->assertJsonPath('server_time', '2026-11-26T07:05:05.000000Z');

        $nextGetRes = $this->getJson("/api/v1/speed-networking/events/{$event->id}/next-match");
        $nextGetRes->assertOk()
            ->assertJsonPath('is_waiting', true)
            ->assertJsonPath('match', null);

        $this->assertSame(1, SpeedNetworkingMatch::where('event_id', $event->id)->count());
    }

    public function test_single_active_match_guarantee_prevents_double_candidate_assignment(): void
    {
        $mockAgora = Mockery::mock(AgoraTokenService::class);
        $mockAgora->shouldReceive('generateToken')->andReturn('mock_token');

        $service = new SpeedNetworkingService($mockAgora);
        $event = $this->createLiveEvent();

        $userA = $this->createTestUser('User A');
        $userB = $this->createTestUser('User B');
        $userC = $this->createTestUser('User C');

        foreach ([$userA, $userB, $userC] as $u) {
            SpeedNetworkingParticipant::create([
                'event_id' => $event->id,
                'user_id' => $u->id,
                'status' => SpeedNetworkingParticipant::STATUS_SEARCHING,
                'joined_at' => now(),
                'last_heartbeat_at' => now(),
            ]);
        }

        // User A matches with one candidate (e.g. User C or B)
        $resA = $service->findOrCreateMatchForUser($event, $userA);
        $this->assertFalse($resA['is_waiting']);
        $pairedWithA = (string) $resA['peer']['id'];

        // Identify the remaining unpaired user
        $thirdUser = $pairedWithA === (string) $userB->id ? $userC : $userB;

        // Third user searches -> must wait, MUST NOT steal A or A's partner!
        $resThird = $service->findOrCreateMatchForUser($event, $thirdUser);
        $this->assertTrue($resThird['is_waiting']);
        $this->assertNull($resThird['peer']);

        // Calling for A's partner returns the existing match with A, not a second match
        $pairedPartner = $pairedWithA === (string) $userB->id ? $userB : $userC;
        $resPartner = $service->findOrCreateMatchForUser($event, $pairedPartner);
        $this->assertFalse($resPartner['is_waiting']);
        $this->assertSame((string) $userA->id, (string) $resPartner['peer']['id']);
        $this->assertSame($resA['match_id'], $resPartner['match_id']);

        $this->assertSame(1, SpeedNetworkingMatch::where('event_id', $event->id)->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)->count());
    }

    public function test_idempotency_keys_replay_cached_responses_and_reject_unauthorized_match_action(): void
    {
        $event = $this->createLiveEvent();
        $userA = $this->createTestUser('Alpha');
        $userB = $this->createTestUser('Beta');
        $intruder = $this->createTestUser('Intruder');

        Sanctum::actingAs($userA);
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();

        Sanctum::actingAs($userB);
        $joinB = $this->postJson("/api/v1/speed-networking/events/{$event->id}/join")->assertOk();
        $matchId = (string) $joinB->json('data.match_id');

        // Intruder cannot act on someone else's match
        Sanctum::actingAs($intruder);
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'skip',
            'match_id' => $matchId,
        ])->assertStatus(403);

        // Invalid action rejected with 422
        Sanctum::actingAs($userB);
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'invalid_action',
            'match_id' => $matchId,
        ])->assertStatus(422);

        // First skip with Idempotency-Key header
        $firstSkip = $this->withHeader('Idempotency-Key', 'idem-skip-1')
            ->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
                'action' => 'skip',
                'match_id' => $matchId,
            ]);
        $firstSkip->assertOk()
            ->assertJsonPath('data.match_status', 'skipped')
            ->assertHeaderMissing('X-Idempotent-Replay');

        // Replay same Idempotency-Key returns identical cached response
        $replaySkip = $this->withHeader('Idempotency-Key', 'idem-skip-1')
            ->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
                'action' => 'skip',
                'match_id' => $matchId,
            ]);
        $replaySkip->assertOk()
            ->assertHeader('X-Idempotent-Replay', 'true')
            ->assertJsonPath('data.match_id', $matchId)
            ->assertJsonPath('data.match_status', 'skipped');

        // Leave idempotency
        $firstLeave = $this->postJson("/api/v1/speed-networking/events/{$event->id}/leave", [
            'reason' => 'user_left',
            'idempotency_key' => 'idem-leave-1',
        ]);
        $firstLeave->assertOk()->assertHeaderMissing('X-Idempotent-Replay');

        $replayLeave = $this->postJson("/api/v1/speed-networking/events/{$event->id}/leave", [
            'reason' => 'user_left',
            'idempotency_key' => 'idem-leave-1',
        ]);
        $replayLeave->assertOk()->assertHeader('X-Idempotent-Replay', 'true');
    }
}
