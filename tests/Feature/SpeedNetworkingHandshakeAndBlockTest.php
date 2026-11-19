<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\P2pMeeting;
use App\Models\SpeedNetworking\SpeedNetworkingBlockedPeer;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingEventSkip;
use App\Models\SpeedNetworking\SpeedNetworkingMatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpeedNetworkingHandshakeAndBlockTest extends TestCase
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
                $table->integer('round_duration_seconds')->default(180);
                $table->integer('break_duration_seconds')->default(20);
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
                $table->integer('duration_seconds')->nullable();
                $table->text('user_a_notes')->nullable();
                $table->text('user_b_notes')->nullable();
                $table->text('meeting_notes')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('p2p_meetings')) {
            Schema::create('p2p_meetings', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('initiator_user_id')->index();
                $table->uuid('peer_user_id')->index();
                $table->string('meeting_date')->nullable();
                $table->string('meeting_place')->nullable();
                $table->text('remarks')->nullable();
                $table->json('media')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('coins_ledgers')) {
            Schema::create('coins_ledgers', function (Blueprint $table): void {
                $table->uuid('transaction_id')->primary();
                $table->uuid('user_id')->index();
                $table->integer('amount')->default(0);
                $table->integer('balance_after')->default(0);
                $table->string('activity_type')->nullable();
                $table->string('reference')->nullable();
                $table->string('reference_type')->nullable();
                $table->string('reference_id')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamp('created_at')->nullable();
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

    private function createLiveEvent(string $title = 'Founders Speed Collaboration'): SpeedNetworkingEvent
    {
        return SpeedNetworkingEvent::query()->create([
            'id' => (string) Str::uuid(),
            'title' => $title,
            'status' => SpeedNetworkingEvent::STATUS_IN_PROGRESS,
            'round_duration_seconds' => 180,
            'break_duration_seconds' => 20,
            'total_rounds' => 5,
            'current_round' => 1,
            'scheduled_start_time' => now()->subMinutes(5),
            'scheduled_end_time' => now()->addMinutes(30),
            'actual_start_time' => now()->subMinutes(5),
            'is_paid' => false,
            'ticket_price' => 0,
        ]);
    }

    public function test_mutual_preview_and_two_sided_connect_handshake(): void
    {
        $event = $this->createLiveEvent();
        $userA = $this->createTestUser('Peer Alpha');
        $userB = $this->createTestUser('Peer Beta');

        // 1. User A joins -> waiting
        Sanctum::actingAs($userA);
        $joinA = $this->postJson("/api/v1/speed-networking/events/{$event->id}/join");
        $joinA->assertOk()
            ->assertJsonPath('data.is_waiting', true);

        // 2. User B joins -> immediately paired with User A in 'preview'
        Sanctum::actingAs($userB);
        $joinB = $this->postJson("/api/v1/speed-networking/events/{$event->id}/join");
        $joinB->assertOk()
            ->assertJsonPath('data.is_waiting', false)
            ->assertJsonPath('data.match_status', 'preview')
            ->assertJsonPath('data.my_decision', 'pending')
            ->assertJsonPath('data.peer_decision', 'pending')
            ->assertJsonPath('data.both_accepted', false)
            ->assertJsonPath('data.peer.id', (string) $userA->id)
            ->assertJsonStructure([
                'data' => [
                    'peer' => [
                        'id',
                        'name',
                        'avatar_url',
                        'city',
                        'designation',
                        'company',
                        'industry',
                        'category',
                        'skills',
                        'life_impacted_count',
                        'is_verified',
                        'is_pro',
                        'connection_status',
                        'is_following',
                        'bio',
                        'round',
                    ],
                ],
            ]);

        $matchId = (string) $joinB->json('data.match_id');
        $channelId = (string) $joinB->json('data.channel_id');
        $this->assertNotEmpty($matchId);
        $this->assertNotEmpty($channelId);

        // 3. User A polls heartbeat -> receives the exact same preview match with User B
        Sanctum::actingAs($userA);
        $hbA = $this->postJson("/api/v1/speed-networking/events/{$event->id}/heartbeat", [
            'user_status' => 'waiting',
        ]);
        $hbA->assertOk()
            ->assertJsonPath('data.has_active_match', true)
            ->assertJsonPath('data.match.match_id', $matchId)
            ->assertJsonPath('data.match.channel_id', $channelId)
            ->assertJsonPath('data.match.match_status', 'preview')
            ->assertJsonPath('data.match.my_decision', 'pending')
            ->assertJsonPath('data.match.peer_decision', 'pending')
            ->assertJsonPath('data.match.both_accepted', false)
            ->assertJsonPath('data.match.peer.id', (string) $userB->id);

        // 4. Preview-only match MUST NOT appear in summary yet
        $summaryBeforeConnect = $this->getJson("/api/v1/speed-networking/events/{$event->id}/summary");
        $summaryBeforeConnect->assertOk()
            ->assertJsonCount(0, 'data.interacted_peers');

        // 5. User A taps Connect -> waiting for User B
        Sanctum::actingAs($userA);
        $connectA = $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'connect',
            'peer_id' => (string) $userB->id,
            'match_id' => $matchId,
            'channel_id' => $channelId,
        ]);
        $connectA->assertOk()
            ->assertJsonPath('data.match_status', 'preview')
            ->assertJsonPath('data.my_decision', 'accepted')
            ->assertJsonPath('data.peer_decision', 'pending')
            ->assertJsonPath('data.both_accepted', false);

        // 6. User B polls heartbeat -> sees peer_decision = 'accepted'
        Sanctum::actingAs($userB);
        $hbB = $this->postJson("/api/v1/speed-networking/events/{$event->id}/heartbeat", [
            'user_status' => 'preview',
        ]);
        $hbB->assertOk()
            ->assertJsonPath('data.match.my_decision', 'pending')
            ->assertJsonPath('data.match.peer_decision', 'accepted')
            ->assertJsonPath('data.match.both_accepted', false);

        // 7. User B taps Connect -> BOTH ACCEPTED -> transitions to 'in_call'
        Sanctum::actingAs($userB);
        $connectB = $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'connect',
            'peer_id' => (string) $userA->id,
            'match_id' => $matchId,
            'channel_id' => $channelId,
        ]);
        $connectB->assertOk()
            ->assertJsonPath('data.match_status', 'in_call')
            ->assertJsonPath('data.my_decision', 'accepted')
            ->assertJsonPath('data.peer_decision', 'accepted')
            ->assertJsonPath('data.both_accepted', true)
            ->assertJsonStructure([
                'data' => [
                    'peer' => [
                        'id',
                        'name',
                        'avatar_url',
                        'city',
                        'designation',
                        'company',
                        'industry',
                        'category',
                        'skills',
                        'life_impacted_count',
                        'is_verified',
                        'is_pro',
                        'connection_status',
                        'is_following',
                        'bio',
                        'round',
                    ],
                ],
            ]);

        // 8. Summary now includes the connected peer
        $summaryAfterConnect = $this->getJson("/api/v1/speed-networking/events/{$event->id}/summary");
        $summaryAfterConnect->assertOk()
            ->assertJsonCount(1, 'data.interacted_peers')
            ->assertJsonPath('data.interacted_peers.0.id', (string) $userA->id);
    }

    public function test_skip_frees_both_peers_and_prevents_rematch_in_same_event(): void
    {
        $event = $this->createLiveEvent();
        $userA = $this->createTestUser('Skipper One');
        $userB = $this->createTestUser('Skipped Two');

        Sanctum::actingAs($userA);
        $this->postJson("/api/v1/speed-networking/events/{$event->id}/join");

        Sanctum::actingAs($userB);
        $joinB = $this->postJson("/api/v1/speed-networking/events/{$event->id}/join");
        $matchId = (string) $joinB->json('data.match_id');

        // User B skips User A
        $skipRes = $this->postJson("/api/v1/speed-networking/events/{$event->id}/match-action", [
            'action' => 'skip',
            'peer_id' => (string) $userA->id,
            'match_id' => $matchId,
        ]);
        $skipRes->assertOk()
            ->assertJsonPath('data.match_status', 'skipped')
            ->assertJsonPath('data.is_waiting', true);

        $this->assertTrue(
            SpeedNetworkingEventSkip::where('event_id', $event->id)
                ->where('user_id', $userB->id)
                ->where('skipped_peer_id', $userA->id)
                ->exists()
        );

        // User A's next preview heartbeat notifies them that partner skipped
        Sanctum::actingAs($userA);
        $hbA = $this->postJson("/api/v1/speed-networking/events/{$event->id}/heartbeat", [
            'user_status' => 'preview',
        ]);
        $hbA->assertOk()
            ->assertJsonPath('data.has_active_match', false)
            ->assertJsonPath('data.match.match_status', 'skipped')
            ->assertJsonPath('data.match.peer_decision', 'skip')
            ->assertJsonPath('data.match.is_waiting', true);

        // Calling next-match does NOT re-pair User A and User B in this event
        $nextA = $this->postJson("/api/v1/speed-networking/events/{$event->id}/next-match");
        $nextA->assertOk()
            ->assertJsonPath('data.is_waiting', true)
            ->assertJsonPath('data.completed', false);
    }

    public function test_speed_networking_scoped_block_and_unblock_across_events(): void
    {
        $event1 = $this->createLiveEvent('Event One');
        $event2 = $this->createLiveEvent('Event Two');
        $userA = $this->createTestUser('Blocker Peer');
        $userB = $this->createTestUser('Blocked Peer');

        // 1. User A blocks User B in Speed Networking
        Sanctum::actingAs($userA);
        $blockRes = $this->postJson('/api/v1/speed-networking/blocked-peers', [
            'peer_id' => (string) $userB->id,
            'event_id' => (string) $event1->id,
            'reason' => 'Blocked during Speed Networking',
        ]);
        $blockRes->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(
            SpeedNetworkingBlockedPeer::where('blocker_id', $userA->id)
                ->where('blocked_id', $userB->id)
                ->exists()
        );

        // 2. Verify GET /blocked-peers lists User B
        $listRes = $this->getJson('/api/v1/speed-networking/blocked-peers');
        $listRes->assertOk()
            ->assertJsonCount(1, 'data.blocked_peers')
            ->assertJsonPath('data.blocked_peers.0.id', (string) $userB->id);

        // 3. Both join Event 1 and Event 2 -> must NOT match bidirectionally
        Sanctum::actingAs($userA);
        $this->postJson("/api/v1/speed-networking/events/{$event2->id}/join");

        Sanctum::actingAs($userB);
        $joinB = $this->postJson("/api/v1/speed-networking/events/{$event2->id}/join");
        $joinB->assertOk()
            ->assertJsonPath('data.is_waiting', true);

        // 4. User A unblocks User B -> now they CAN match in Event 2!
        Sanctum::actingAs($userA);
        $unblockRes = $this->deleteJson("/api/v1/speed-networking/blocked-peers/{$userB->id}");
        $unblockRes->assertOk()
            ->assertJsonPath('success', true);

        $nextMatchA = $this->postJson("/api/v1/speed-networking/events/{$event2->id}/next-match");
        $nextMatchA->assertOk()
            ->assertJsonPath('data.is_waiting', false)
            ->assertJsonPath('data.match_status', 'preview')
            ->assertJsonPath('data.peer.id', (string) $userB->id);
    }

    public function test_minimum_two_minute_connection_threshold_for_p2p_life_impact_and_coins(): void
    {
        $event = $this->createLiveEvent('Threshold Speed Collaboration');
        $userA = $this->createTestUser('Main Peer');
        $shortPeer = $this->createTestUser('Short Call Peer');
        $qualifiedPeer = $this->createTestUser('Qualified Call Peer');

        // Round 1: Short 1-on-1 call lasting 45 seconds (< 120s threshold)
        $shortMatch = SpeedNetworkingMatch::create([
            'event_id' => $event->id,
            'round_number' => 1,
            'table_number' => 1,
            'channel_id' => 'sn_short_call_1',
            'user_a_id' => $userA->id,
            'user_b_id' => $shortPeer->id,
            'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
            'match_status' => SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
            'user_a_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'user_b_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'call_started_at' => now()->subSeconds(200),
            'started_at' => now()->subSeconds(200),
            'ended_at' => now()->subSeconds(155),
            'duration_seconds' => 45,
        ]);

        // Round 2: Qualified 1-on-1 call lasting 180 seconds (>= 120s threshold)
        SpeedNetworkingMatch::create([
            'event_id' => $event->id,
            'round_number' => 2,
            'table_number' => 2,
            'channel_id' => 'sn_qualified_call_2',
            'user_a_id' => $userA->id,
            'user_b_id' => $qualifiedPeer->id,
            'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
            'match_status' => SpeedNetworkingMatch::MATCH_STATUS_COMPLETED,
            'user_a_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'user_b_decision' => SpeedNetworkingMatch::DECISION_ACCEPTED,
            'call_started_at' => now()->subSeconds(180),
            'started_at' => now()->subSeconds(180),
            'ended_at' => now(),
            'duration_seconds' => 180,
        ]);

        Sanctum::actingAs($userA);

        // Save personal meeting notes on the short (< 120s) interaction via /complete-p2p
        $shortCompleteRes = $this->postJson("/api/v1/speed-networking/events/{$event->id}/complete-p2p", [
            'peer_id' => (string) $shortPeer->id,
            'notes' => 'Brief intro - follow up next week',
            'remarks' => 'Brief intro - follow up next week',
            'meeting_notes' => 'Brief intro - follow up next week',
            'interaction_duration_seconds' => 45,
            'log_p2p' => false,
        ]);

        $shortCompleteRes->assertOk()
            ->assertJsonPath('data.p2p_logged', false)
            ->assertJsonPath('data.p2p_meeting_id', null)
            ->assertJsonPath('data.life_impact_earned', 0)
            ->assertJsonPath('data.coins_earned', 0)
            ->assertJsonPath('data.meeting_notes', 'Brief intro - follow up next week');

        // Verify NO P2P meeting was created for the 45s match
        $this->assertSame(
            0,
            P2pMeeting::where('remarks', 'like', "%[sn_match:{$shortMatch->id}]%")->count()
        );

        // Fetch session summary
        $summaryRes = $this->getJson("/api/v1/speed-networking/events/{$event->id}/summary");
        $summaryRes->assertOk()
            ->assertJsonPath('data.peers_met_count', 2)
            ->assertJsonPath('data.rounds_completed', 2)
            ->assertJsonPath('data.qualifying_peers_count', 1)
            ->assertJsonPath('data.life_impact_earned', 1)
            ->assertJsonPath('data.coins_earned', 3000)
            ->assertJsonCount(2, 'data.interacted_peers');

        // Peer 0 (45s < 120s): appears in interacted_peers with notes preserved, but p2p_logged = false
        $summaryRes->assertJsonPath('data.interacted_peers.0.id', (string) $shortPeer->id)
            ->assertJsonPath('data.interacted_peers.0.interaction_duration_seconds', 45)
            ->assertJsonPath('data.interacted_peers.0.p2p_logged', false)
            ->assertJsonPath('data.interacted_peers.0.p2p_meeting_id', null)
            ->assertJsonPath('data.interacted_peers.0.meeting_notes', 'Brief intro - follow up next week');

        // Peer 1 (180s >= 120s): p2p_logged = true and P2P meeting created
        $summaryRes->assertJsonPath('data.interacted_peers.1.id', (string) $qualifiedPeer->id)
            ->assertJsonPath('data.interacted_peers.1.interaction_duration_seconds', 180)
            ->assertJsonPath('data.interacted_peers.1.p2p_logged', true);

        $this->assertNotNull($summaryRes->json('data.interacted_peers.1.p2p_meeting_id'));
        $this->assertSame(1, P2pMeeting::count());
    }

    public function test_event_strictly_starts_and_ends_based_on_scheduled_start_and_end_times(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-19 11:40:00'));

        $scheduledStart = Carbon::parse('2026-11-19 12:00:00');
        $scheduledEnd = Carbon::parse('2026-11-19 12:30:00');

        $event = SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Exact Time Speed Collaboration',
            'description' => 'Starts at 12:00, ends at 12:30',
            'status' => SpeedNetworkingEvent::STATUS_SCHEDULED,
            'round_duration_seconds' => 180,
            'break_duration_seconds' => 20,
            'total_rounds' => 5,
            'current_round' => 0,
            'scheduled_start_time' => $scheduledStart,
            'scheduled_end_time' => $scheduledEnd,
            'metadata' => [
                'duration_seconds' => 1800,
                'duration_minutes' => 30,
            ],
        ]);

        $userA = $this->createTestUser('Peer Alpha');
        $userB = $this->createTestUser('Peer Beta');

        $expectedStartIso = $scheduledStart->copy()->utc()->toISOString();
        $expectedEndIso = $scheduledEnd->copy()->utc()->toISOString();

        // 1. BEFORE scheduled_start_time (11:40 < 12:00):
        // Even if 2 peers join lobby / send heartbeats early, event MUST NOT start early!
        Sanctum::actingAs($userA);
        $activeBefore = $this->getJson('/api/v1/speed-networking/active-event');
        $activeBefore->assertOk()
            ->assertJsonPath('data.id', (string) $event->id)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.scheduled_start_time', $expectedStartIso)
            ->assertJsonPath('data.scheduled_end_time', $expectedEndIso)
            ->assertJsonPath('data.end_time', $expectedEndIso)
            ->assertJsonPath('data.estimated_end_time', $expectedEndIso);

        $joinBeforeA = $this->postJson("/api/v1/speed-networking/events/{$event->id}/join");
        $joinBeforeA->assertOk()
            ->assertJsonPath('data.is_waiting', true)
            ->assertJsonPath('data.current_round', 0);

        Sanctum::actingAs($userB);
        $joinBeforeB = $this->postJson("/api/v1/speed-networking/events/{$event->id}/join");
        $joinBeforeB->assertOk()
            ->assertJsonPath('data.is_waiting', true)
            ->assertJsonPath('data.current_round', 0);

        $hbBeforeB = $this->postJson("/api/v1/speed-networking/events/{$event->id}/heartbeat", [
            'user_status' => 'waiting',
        ]);
        $hbBeforeB->assertOk()
            ->assertJsonPath('has_active_match', false);

        $this->assertNotSame(SpeedNetworkingEvent::STATUS_IN_PROGRESS, $event->fresh()->status);

        // 2. AT scheduled_start_time (12:00:00):
        // Event MUST automatically transition to live / in_progress and start Round 1
        Carbon::setTestNow($scheduledStart);

        $activeAtStart = $this->getJson('/api/v1/speed-networking/active-event');
        $activeAtStart->assertOk()
            ->assertJsonPath('data.id', (string) $event->id)
            ->assertJsonPath('data.status', 'live')
            ->assertJsonPath('data.current_round', 1)
            ->assertJsonPath('data.scheduled_start_time', $expectedStartIso)
            ->assertJsonPath('data.scheduled_end_time', $expectedEndIso)
            ->assertJsonPath('data.end_time', $expectedEndIso)
            ->assertJsonPath('data.estimated_end_time', $expectedEndIso);

        $showAtStart = $this->getJson("/api/v1/speed-networking/events/{$event->id}");
        $showAtStart->assertOk()
            ->assertJsonPath('data.status', 'live')
            ->assertJsonPath('data.scheduled_start_time', $expectedStartIso)
            ->assertJsonPath('data.scheduled_end_time', $expectedEndIso)
            ->assertJsonPath('data.estimated_end_time', $expectedEndIso);

        $this->assertSame(SpeedNetworkingEvent::STATUS_IN_PROGRESS, $event->fresh()->status);

        // 3. AT 12:29:59 (1 second before scheduled_end_time): event is still live
        Carbon::setTestNow($scheduledEnd->copy()->subSecond());
        $activeOneSecBeforeEnd = $this->getJson('/api/v1/speed-networking/active-event');
        $activeOneSecBeforeEnd->assertOk()
            ->assertJsonPath('data.id', (string) $event->id)
            ->assertJsonPath('data.status', 'live');

        // 4. AT scheduled_end_time (12:30:00):
        // Event MUST automatically end and conclude active sessions
        Carbon::setTestNow($scheduledEnd);

        $hbAtEnd = $this->postJson("/api/v1/speed-networking/events/{$event->id}/heartbeat", [
            'user_status' => 'waiting',
        ]);
        $hbAtEnd->assertOk()
            ->assertJsonPath('status', 'event_ended')
            ->assertJsonPath('data.status', 'event_ended')
            ->assertJsonPath('data.completed', true);

        $activeAtEnd = $this->getJson('/api/v1/speed-networking/active-event');
        $activeAtEnd->assertOk()
            ->assertJsonPath('data', null);

        $joinAtEnd = $this->postJson("/api/v1/speed-networking/events/{$event->id}/join");
        $joinAtEnd->assertOk()
            ->assertJsonPath('data.status', 'event_ended')
            ->assertJsonPath('data.completed', true);

        $this->assertSame(SpeedNetworkingEvent::STATUS_COMPLETED, $event->fresh()->status);
        $this->assertNotNull($event->fresh()->actual_end_time);

        Carbon::setTestNow();
    }
}
