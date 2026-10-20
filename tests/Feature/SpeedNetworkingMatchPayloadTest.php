<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingMatch;
use App\Models\User;
use App\Services\SpeedNetworking\AgoraTokenService;
use App\Services\SpeedNetworking\SpeedNetworkingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SpeedNetworkingMatchPayloadTest extends TestCase
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

        Schema::create('connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('requester_id');
            $table->string('addressee_id');
            $table->boolean('is_approved')->default(false);
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
            $table->string('banner_url')->nullable();
            $table->string('status')->default('draft');
            $table->integer('round_duration_seconds')->default(120);
            $table->integer('break_duration_seconds')->default(20);
            $table->integer('total_rounds')->default(5);
            $table->integer('current_round')->default(0);
            $table->timestamp('scheduled_start_time')->nullable();
            $table->timestamp('actual_start_time')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('speed_networking_matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_id');
            $table->integer('round_number')->default(1);
            $table->integer('table_number')->default(1);
            $table->string('channel_id')->nullable();
            $table->string('user_a_id');
            $table->string('user_b_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('company_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('city')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function test_format_match_response_handles_array_attributes_without_error(): void
    {
        $mockAgora = Mockery::mock(AgoraTokenService::class);
        $mockAgora->shouldReceive('generateToken')->andReturn('mock_token');

        $service = new SpeedNetworkingService($mockAgora);

        $event = new SpeedNetworkingEvent([
            'id' => 'evt-123',
            'title' => 'Speed Networking Session',
            'round_duration_seconds' => 180,
            'break_duration_seconds' => 30,
            'total_rounds' => 5,
        ]);

        $peer = new User([
            'id' => 'user-peer-123',
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'skills' => ['Laravel', 'Vue.js'],
            'short_bio' => null,
            'experience_summary' => null,
            'superpower' => null,
            'i_can_help_with' => ['Tech Architecture', 'Backend Scaling'],
            'business_sub_category' => null,
            'company_type' => 'Technology & IT',
        ]);

        $match = new SpeedNetworkingMatch([
            'id' => 'match-123',
            'event_id' => 'evt-123',
            'round_number' => 1,
            'table_number' => 1,
            'channel_id' => 'sn_channel_123',
            'user_a_id' => 'user-current-123',
            'user_b_id' => 'user-peer-123',
        ]);
        $match->setRelation('userA', new User(['id' => 'user-current-123']));
        $match->setRelation('userB', $peer);

        $reflection = new \ReflectionClass(SpeedNetworkingService::class);
        $method = $reflection->getMethod('formatMatchResponse');
        $method->setAccessible(true);

        $response = $method->invoke($service, $event, $match, 'user-current-123');

        $this->assertIsArray($response);
        $this->assertEquals('mock_token', $response['agora_token']);
        $this->assertNotNull($response['peer']);
        $this->assertEquals('Tech Architecture, Backend Scaling', $response['peer']['bio']);
        $this->assertEquals(['Laravel', 'Vue.js'], $response['peer']['skills']);
        $this->assertEquals('Technology & IT', $response['peer']['industry']);
    }

    public function test_four_mobiles_non_repeating_and_exhaustion(): void
    {
        $mockAgora = Mockery::mock(AgoraTokenService::class);
        $mockAgora->shouldReceive('generateToken')->andReturn('mock_token');

        $service = new SpeedNetworkingService($mockAgora);

        $event = SpeedNetworkingEvent::create([
            'id' => 'evt-test-4',
            'title' => 'Speed Networking 4 Mobiles',
            'status' => SpeedNetworkingEvent::STATUS_IN_PROGRESS,
            'round_duration_seconds' => 120,
            'break_duration_seconds' => 20,
            'total_rounds' => 5,
            'current_round' => 1,
        ]);

        $userA = User::create(['id' => 'user-a', 'first_name' => 'Alice']);
        $userB = User::create(['id' => 'user-b', 'first_name' => 'Bob']);
        $userC = User::create(['id' => 'user-c', 'first_name' => 'Charlie']);
        $userD = User::create(['id' => 'user-d', 'first_name' => 'David']);

        // --- ROUND 1 ---
        // User A joins first (no one waiting, so A is in waiting room)
        $joinA = $service->joinEvent($event, $userA);
        $this->assertTrue($joinA['is_break_round']);

        // User B joins -> pairs with User A
        $joinB = $service->joinEvent($event, $userB);
        $this->assertFalse($joinB['is_break_round']);
        $this->assertEquals('user-a', $joinB['peer']['id']);

        // User A picks up the match
        $matchA = $service->getNextMatch($event, $userA);
        $this->assertFalse($matchA['is_break_round']);
        $this->assertEquals('user-b', $matchA['peer']['id']);

        // User C joins (A and B are in call, so C waits)
        $joinC = $service->joinEvent($event, $userC);
        $this->assertTrue($joinC['is_break_round']);

        // User D joins -> pairs with User C
        $joinD = $service->joinEvent($event, $userD);
        $this->assertFalse($joinD['is_break_round']);
        $this->assertEquals('user-c', $joinD['peer']['id']);

        // User C picks up the match
        $matchC = $service->getNextMatch($event, $userC);
        $this->assertFalse($matchC['is_break_round']);
        $this->assertEquals('user-d', $matchC['peer']['id']);

        // Active matches in round 1 must not contain duplicate participants
        $activeMatches = SpeedNetworkingMatch::where('event_id', $event->id)
            ->where('status', SpeedNetworkingMatch::STATUS_ACTIVE)
            ->get();
        $this->assertCount(2, $activeMatches);

        $peerOfA_round1 = 'user-b';
        $peerOfC_round1 = 'user-d';

        // --- ROUND 2 ---
        // All 4 users request next match
        $nextA = $service->getNextMatch($event, $userA);
        $nextB = $service->getNextMatch($event, $userB);
        $nextC = $service->getNextMatch($event, $userC);
        $nextD = $service->getNextMatch($event, $userD);

        // Verify A did NOT meet the same person from Round 1
        if (! $nextA['is_break_round']) {
            $this->assertNotEquals($peerOfA_round1, $nextA['peer']['id']);
        }
        if (! $nextC['is_break_round']) {
            $this->assertNotEquals($peerOfC_round1, $nextC['peer']['id']);
        }

        // Users advance until all possible pairs have met
        $allA_Peers = [];
        for ($round = 1; $round <= 6; $round++) {
            $resA = $service->getNextMatch($event, $userA);
            $resB = $service->getNextMatch($event, $userB);
            $resC = $service->getNextMatch($event, $userC);
            $resD = $service->getNextMatch($event, $userD);

            if (! empty($resA['peer']['id'])) {
                $allA_Peers[] = $resA['peer']['id'];
            }
        }

        // Final call for A - must have met all peers!
        $finalA = $service->getNextMatch($event, $userA);
        $this->assertTrue($finalA['has_met_all_peers']);
        $this->assertTrue($finalA['completed']);
        $this->assertEquals('You have met all peers in this event.', $finalA['message']);
        $this->assertNull($finalA['peer']);

        // Check that A never met the same peer more than once in matches
        $matchesWithA = SpeedNetworkingMatch::where('event_id', $event->id)
            ->where(function ($q): void {
                $q->where('user_a_id', 'user-a')->orWhere('user_b_id', 'user-a');
            })
            ->whereNotNull('user_b_id')
            ->get()
            ->map(fn ($m) => $m->user_a_id === 'user-a' ? $m->user_b_id : $m->user_a_id)
            ->all();

        // The count of matches with A should equal the count of unique peers A met
        $this->assertCount(count(array_unique($matchesWithA)), $matchesWithA);
        // And A should have met all 3 peers (user-b, user-c, user-d)
        $this->assertEqualsCanonicalizing(['user-b', 'user-c', 'user-d'], array_unique($matchesWithA));
    }
}
