<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileModel;
use App\Models\P2pMeeting;
use App\Models\Post;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingMatch;
use App\Models\User;
use App\Services\Coins\CoinsService;
use App\Services\Creative\SpeedNetworkingP2pCreativeGenerator;
use App\Services\LifeImpact\LifeImpactService;
use App\Services\SpeedNetworking\SpeedNetworkingP2pService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class SpeedNetworkingP2pConceptTest extends TestCase
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
            $table->string('status')->default('active');
            $table->integer('coins_balance')->default(0);
            $table->integer('life_impacted_count')->default(0);
            $table->string('company_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('speed_networking_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->integer('round_duration_seconds')->default(120);
            $table->integer('break_duration_seconds')->default(20);
            $table->integer('total_rounds')->default(5);
            $table->integer('current_round')->default(0);
            $table->date('start_date')->nullable();
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
            $table->string('status')->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('p2p_meetings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('initiator_user_id');
            $table->string('peer_user_id');
            $table->string('p2p_meeting_request_id')->nullable();
            $table->date('meeting_date');
            $table->string('meeting_place')->nullable();
            $table->text('remarks')->nullable();
            $table->json('media')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('user_id');
            $table->string('circle_id')->nullable();
            $table->text('content_text')->nullable();
            $table->json('media')->nullable();
            $table->json('tags')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('source_event')->nullable();
            $table->string('visibility')->default('public');
            $table->string('moderation_status')->default('approved');
            $table->boolean('sponsored')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('uploader_user_id')->nullable();
            $table->string('s3_key')->nullable();
            $table->string('mime_type')->nullable();
            $table->integer('size_bytes')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('duration')->nullable();
            $table->boolean('is_orphaned')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_speed_networking_match_converts_to_p2p_meeting_with_rewards_and_no_timeline_post(): void
    {
        $userA = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Alice Johnson',
            'display_name' => 'Alice Johnson',
            'email' => 'alice@example.com',
            'coins_balance' => 100,
            'life_impacted_count' => 10,
        ]);

        $userB = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Bob Smith',
            'display_name' => 'Bob Smith',
            'email' => 'bob@example.com',
            'coins_balance' => 50,
            'life_impacted_count' => 5,
        ]);

        $event = SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Global Speed Conclave 2026',
            'status' => SpeedNetworkingEvent::STATUS_IN_PROGRESS,
            'total_rounds' => 3,
            'current_round' => 1,
            'start_date' => now()->toDateString(),
        ]);

        $match = SpeedNetworkingMatch::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'round_number' => 1,
            'table_number' => 1,
            'user_a_id' => $userA->id,
            'user_b_id' => $userB->id,
            'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
            'started_at' => now()->subMinutes(5),
            'ended_at' => now(),
        ]);

        // Mock creative generator to avoid requiring physical font/image disk files in test
        $fakeFile = new FileModel([
            'id' => (string) Str::uuid(),
            'uploader_user_id' => $userA->id,
            's3_key' => 'speed_networking/fake_creative.webp',
            'mime_type' => 'image/webp',
        ]);

        $mockGenerator = Mockery::mock(SpeedNetworkingP2pCreativeGenerator::class);
        $mockGenerator->shouldReceive('generate')
            ->once()
            ->with(
                Mockery::on(fn ($u) => (string) $u->id === (string) $userA->id),
                Mockery::on(fn ($u) => (string) $u->id === (string) $userB->id),
                Mockery::type(SpeedNetworkingEvent::class),
                Mockery::type(SpeedNetworkingMatch::class)
            )
            ->andReturn($fakeFile);

        $mockCoinsService = Mockery::mock(CoinsService::class);
        $mockCoinsService->shouldReceive('rewardForActivity')
            ->twice()
            ->andReturn(null);

        $mockLifeImpactService = Mockery::mock(LifeImpactService::class);
        $mockLifeImpactService->shouldReceive('addLifeImpact')
            ->twice()
            ->andReturn(15);

        $p2pService = new SpeedNetworkingP2pService(
            $mockGenerator,
            $mockCoinsService,
            $mockLifeImpactService
        );

        $meeting = $p2pService->completeMatchAsP2pMeeting($match, $event);

        // 1. P2pMeeting created
        $this->assertNotNull($meeting);
        $this->assertEquals($userA->id, $meeting->initiator_user_id);
        $this->assertEquals($userB->id, $meeting->peer_user_id);
        $this->assertStringContainsString('Speed Networking Round 1', $meeting->remarks);
        $this->assertStringContainsString($match->id, $meeting->remarks);

        // 2. Creative attached to media
        $this->assertNotEmpty($meeting->media);
        $this->assertEquals($fakeFile->id, $meeting->media[0]['file_id']);
        $this->assertEquals('image', $meeting->media[0]['media_type']);

        // 3. NO timeline post was created
        $this->assertEquals(0, Post::count(), 'Creative must NOT be posted in timeline feed');

        // 4. Idempotency test: calling again returns the same meeting without calling generator
        $repeatMeeting = $p2pService->completeMatchAsP2pMeeting($match, $event);
        $this->assertEquals($meeting->id, $repeatMeeting->id);
        $this->assertEquals(1, P2pMeeting::count());
    }

    public function test_complete_all_matches_for_event(): void
    {
        $userA = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Charlie',
            'display_name' => 'Charlie Brown',
            'email' => 'charlie@example.com',
        ]);

        $userB = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dana',
            'display_name' => 'Dana Scully',
            'email' => 'dana@example.com',
        ]);

        $event = SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Circle Speed Summit',
            'status' => SpeedNetworkingEvent::STATUS_COMPLETED,
            'total_rounds' => 1,
            'current_round' => 1,
        ]);

        $match = SpeedNetworkingMatch::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'round_number' => 1,
            'table_number' => 1,
            'user_a_id' => $userA->id,
            'user_b_id' => $userB->id,
            'status' => SpeedNetworkingMatch::STATUS_COMPLETED,
        ]);

        $fakeFile = new FileModel([
            'id' => (string) Str::uuid(),
            'uploader_user_id' => $userA->id,
            's3_key' => 'speed_networking/charlie_dana.webp',
            'mime_type' => 'image/webp',
        ]);

        $mockGenerator = Mockery::mock(SpeedNetworkingP2pCreativeGenerator::class);
        $mockGenerator->shouldReceive('generate')->once()->andReturn($fakeFile);

        $mockCoins = Mockery::mock(CoinsService::class);
        $mockCoins->shouldReceive('rewardForActivity')->twice()->andReturn(null);

        $mockImpact = Mockery::mock(LifeImpactService::class);
        $mockImpact->shouldReceive('addLifeImpact')->twice()->andReturn(20);

        $p2pService = new SpeedNetworkingP2pService($mockGenerator, $mockCoins, $mockImpact);
        $meetings = $p2pService->completeAllMatchesForEvent($event);

        $this->assertCount(1, $meetings);
        $this->assertEquals(1, P2pMeeting::count());
        $this->assertEquals(0, Post::count());
    }
}

