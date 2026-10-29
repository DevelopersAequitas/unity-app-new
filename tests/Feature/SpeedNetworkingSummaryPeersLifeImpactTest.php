<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Impact;
use App\Models\LifeImpactHistory;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingMatch;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpeedNetworkingSummaryPeersLifeImpactTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('city')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->integer('coins_balance')->default(0);
            $table->integer('life_impacted_count')->default(0);
            $table->integer('members_introduced_count')->default(0);
            $table->json('bookmarks')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_verified')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('requester_id');
            $table->string('addressee_id');
            $table->string('status')->default('connected');
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
        });

        Schema::create('speed_networking_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_id');
            $table->string('user_id');
            $table->string('status')->default('waiting');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();
        });

        Schema::create('speed_networking_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('completed');
            $table->integer('round_duration_seconds')->default(180);
            $table->integer('break_duration_seconds')->default(30);
            $table->integer('total_rounds')->default(5);
            $table->integer('current_round')->default(5);
            $table->timestamp('scheduled_start_time')->nullable();
            $table->timestamp('actual_start_time')->nullable();
            $table->timestamp('actual_end_time')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('speed_networking_matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_id');
            $table->integer('round_number')->default(1);
            $table->integer('table_number')->default(1);
            $table->string('channel_id')->nullable();
            $table->string('user_a_id');
            $table->string('user_b_id')->nullable();
            $table->string('status')->default('completed');
            $table->integer('duration_seconds')->default(180);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('life_impact_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('user_id');
            $table->string('triggered_by_user_id')->nullable();
            $table->string('activity_type')->nullable();
            $table->string('activity_id')->nullable();
            $table->integer('impact_value')->default(0);
            $table->integer('life_impacted')->default(0);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('approved');
            $table->boolean('counted_in_total')->default(true);
            $table->timestamps();
        });

        Schema::create('impacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('user_id');
            $table->integer('life_impacted')->default(0);
            $table->integer('impact_value')->default(0);
            $table->string('status')->default('approved');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('p2p_meetings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('initiator_user_id');
            $table->string('peer_user_id');
            $table->date('meeting_date');
            $table->string('meeting_place')->nullable();
            $table->text('remarks')->nullable();
            $table->json('media')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_follows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('follower_id');
            $table->string('following_id');
            $table->string('status')->default('accepted');
            $table->timestamps();
        });
    }

    public function test_speed_networking_summary_returns_peers_life_impact_count(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Current',
            'last_name' => 'User',
            'display_name' => 'Current User',
            'email' => 'current@peers.com',
            'life_impacted_count' => 10,
        ]);

        $peer1 = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Avinash',
            'last_name' => 'Vaghela',
            'display_name' => 'AVINASH VAGHELA',
            'email' => 'avinash@peers.com',
            'company_name' => 'Aequitas Information',
            'designation' => 'Director & CEO',
            'city' => 'Ahmedabad',
            'life_impacted_count' => 45,
        ]);

        $peer2 = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Rahul',
            'last_name' => 'Parmar',
            'display_name' => 'RAHUL PARMAR',
            'email' => 'rahul@peers.com',
            'company_name' => 'hdib',
            'designation' => 'Member',
            'city' => 'Ahmedabad',
            'life_impacted_count' => 0,
        ]);

        LifeImpactHistory::create([
            'id' => (string) Str::uuid(),
            'user_id' => $peer2->id,
            'impact_value' => 30,
            'life_impacted' => 30,
            'status' => 'approved',
            'counted_in_total' => true,
        ]);

        $peer3 = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Chirag',
            'last_name' => 'Mali',
            'display_name' => 'CHIRAG MALI',
            'email' => 'chirag@peers.com',
            'company_name' => 'TaskMate AI',
            'city' => 'Ahmedabad',
            'life_impacted_count' => 0,
        ]);

        Impact::create([
            'id' => (string) Str::uuid(),
            'user_id' => $peer3->id,
            'life_impacted' => 15,
            'status' => 'approved',
        ]);

        $peer4 = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Vishal',
            'last_name' => 'Parmar',
            'display_name' => 'VISHAL PARMAR',
            'email' => 'vishal@peers.com',
            'company_name' => 'Peers Global',
            'designation' => 'Director',
            'city' => 'Ahmedabad',
            'life_impacted_count' => 0,
        ]);

        $event = SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Entrepreneurs Speed Networking',
            'status' => 'completed',
            'round_duration_seconds' => 180,
            'total_rounds' => 4,
            'current_round' => 4,
        ]);

        foreach ([$peer1, $peer2, $peer3, $peer4] as $idx => $peer) {
            SpeedNetworkingMatch::create([
                'id' => (string) Str::uuid(),
                'event_id' => $event->id,
                'round_number' => $idx + 1,
                'table_number' => 1,
                'user_a_id' => $user->id,
                'user_b_id' => $peer->id,
                'status' => 'completed',
                'duration_seconds' => 180,
            ]);
        }

        // Bookmark peer1 and follow peer2
        $user->bookmarks = [(string) $peer1->id];
        $user->save();

        \Illuminate\Support\Facades\DB::table('user_follows')->insert([
            'id' => (string) Str::uuid(),
            'follower_id' => (string) $user->id,
            'following_id' => (string) $peer2->id,
            'status' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/speed-networking/events/{$event->id}/summary");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.completed', true)
            ->assertJsonPath('data.total_interactions_count', 4);

        $interactedPeers = $response->json('data.interacted_peers');
        $this->assertCount(4, $interactedPeers);

        $avinash = collect($interactedPeers)->firstWhere('id', (string) $peer1->id);
        $this->assertNotNull($avinash);
        $this->assertSame(45, $avinash['life_impacted_count']);
        $this->assertSame(45, $avinash['lives_impacted']);
        $this->assertSame(45, $avinash['life_impact_count']);
        $this->assertTrue($avinash['is_bookmark']);
        $this->assertFalse($avinash['is_following']);

        $rahul = collect($interactedPeers)->firstWhere('id', (string) $peer2->id);
        $this->assertNotNull($rahul);
        $this->assertSame(30, $rahul['life_impacted_count']);
        $this->assertSame(30, $rahul['lives_impacted']);
        $this->assertSame(30, $rahul['score']);
        $this->assertSame(30, $rahul['peer_score']);
        $this->assertSame(30, $rahul['impact_score']);
        $this->assertSame(30, $rahul['mutual_connections_count']);
        $this->assertFalse($rahul['is_bookmark']);
        $this->assertTrue($rahul['is_following']);

        $chirag = collect($interactedPeers)->firstWhere('id', (string) $peer3->id);
        $this->assertNotNull($chirag);
        $this->assertSame(15, $chirag['life_impacted_count']);
        $this->assertSame(15, $chirag['lives_impacted']);

        $vishal = collect($interactedPeers)->firstWhere('id', (string) $peer4->id);
        $this->assertNotNull($vishal);
        $this->assertGreaterThanOrEqual(1, $vishal['life_impacted_count']);
        $this->assertGreaterThanOrEqual(1, $vishal['lives_impacted']);
        $this->assertGreaterThanOrEqual(1, $vishal['life_impact_count']);

        // Check top-level life_impact stats in summary
        $this->assertSame(20, $response->json('data.life_impact_earned'));
        $this->assertSame(20, $response->json('data.life_impact.earned'));
        $this->assertGreaterThanOrEqual(20, $response->json('data.life_impact.total'));

        // Test alternative prefix route: /api/v1/events/{eventId}/speed-networking/summary
        $altResponse = $this->getJson("/api/v1/events/{$event->id}/speed-networking/summary");
        $altResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.interacted_peers.0.life_impacted_count', 45);
    }
}
