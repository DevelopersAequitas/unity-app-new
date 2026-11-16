<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SpeedNetworking\SpeedNetworkingBlockedPeer;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingEventSkip;
use App\Models\User;
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
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
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
            ->assertJsonPath('data.peer.id', (string) $userA->id);

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
            ->assertJsonPath('data.both_accepted', true);

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
}
