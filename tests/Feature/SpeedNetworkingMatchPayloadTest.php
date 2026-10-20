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
}
