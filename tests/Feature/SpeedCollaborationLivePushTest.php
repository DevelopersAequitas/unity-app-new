<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\SpeedNetworking\SpeedNetworkingParticipant;
use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Firebase\FcmService as FirebaseFcmService;
use App\Services\SpeedNetworking\SpeedCollaborationLivePushService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SpeedCollaborationLivePushTest extends TestCase
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
                $table->string('display_name')->nullable();
                $table->string('email')->nullable();
                $table->string('android_fcm_token')->nullable();
                $table->string('ios_fcm_token')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('user_push_tokens')) {
            Schema::create('user_push_tokens', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->string('token')->unique();
                $table->string('platform', 32)->nullable();
                $table->string('device_id')->nullable();
                $table->string('app')->nullable();
                $table->string('app_version')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->text('live_activity_push_to_start_token')->nullable();
                $table->text('live_activity_token')->nullable();
                $table->string('live_activity_event_id', 64)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('speed_networking_events')) {
            Schema::create('speed_networking_events', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('status')->default('in_progress');
                $table->integer('round_duration_seconds')->default(300);
                $table->integer('break_duration_seconds')->default(20);
                $table->integer('total_rounds')->default(5);
                $table->integer('current_round')->default(1);
                $table->timestamp('scheduled_start_time')->nullable();
                $table->timestamp('scheduled_end_time')->nullable();
                $table->timestamp('actual_start_time')->nullable();
                $table->timestamp('actual_end_time')->nullable();
                $table->decimal('ticket_price', 10, 2)->default(0);
                $table->boolean('is_paid')->default(false);
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
                $table->string('status')->default('lobby');
                $table->timestamps();
            });
        }
    }

    public function test_ios_live_activity_push_to_start_and_update_tokens_are_stored(): void
    {
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->first_name = 'Aarav';
        $user->email = 'aarav@example.com';
        $user->status = 'active';
        $user->save();

        Sanctum::actingAs($user);

        // 1. Register push_to_start token without fcm_token
        $this->postJson('/api/v1/user/push-token', [
            'platform' => 'ios',
            'live_activity_token_type' => 'push_to_start',
            'live_activity_push_to_start_token' => 'aabbccdd11223344',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.live_activity_push_to_start_token', 'aabbccdd11223344');

        // 2. Register activity_update token with event_id
        $eventId = (string) Str::uuid();
        $this->postJson('/api/v1/user/push-token', [
            'platform' => 'ios',
            'live_activity_token_type' => 'activity_update',
            'live_activity_token' => 'eeff556677889900',
            'event_id' => $eventId,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.live_activity_token', 'eeff556677889900')
            ->assertJsonPath('data.event_id', $eventId);

        $row = UserPushToken::where('user_id', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('aabbccdd11223344', $row->live_activity_push_to_start_token);
        $this->assertSame('eeff556677889900', $row->live_activity_token);
        $this->assertSame($eventId, $row->live_activity_event_id);
    }

    public function test_android_sticky_live_and_ended_fcm_data_pushes_match_contract(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-27 17:00:00'));

        $user = new User;
        $user->id = (string) Str::uuid();
        $user->first_name = 'Neha';
        $user->email = 'neha@example.com';
        $user->status = 'active';
        $user->save();

        UserPushToken::create([
            'user_id' => $user->id,
            'token' => 'android_fcm_token_xyz',
            'platform' => 'android',
            'is_active' => true,
        ]);

        $event = SpeedNetworkingEvent::create([
            'id' => (string) Str::uuid(),
            'title' => 'Business Collaboration Day',
            'status' => SpeedNetworkingEvent::STATUS_IN_PROGRESS,
            'round_duration_seconds' => 300,
            'break_duration_seconds' => 20,
            'total_rounds' => 5,
            'scheduled_start_time' => Carbon::now(),
            'scheduled_end_time' => Carbon::now()->addMinutes(30),
        ]);

        SpeedNetworkingParticipant::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'lobby',
        ]);

        $fcmMock = Mockery::mock(FirebaseFcmService::class);
        $fcmMock->shouldReceive('sendDataOnlyToDevice')
            ->once()
            ->with(
                'android_fcm_token_xyz',
                Mockery::on(function (array $data) use ($event): bool {
                    return $data['type'] === 'speed_collaboration_live'
                        && $data['event_id'] === (string) $event->id
                        && $data['event_title'] === 'Business Collaboration Day'
                        && ! empty($data['end_time'])
                        && $data['participants_count'] === '1';
                }),
                Mockery::type('array')
            )
            ->andReturn(['success' => true, 'firebase_response' => ['name' => 'msg/1'], 'error' => null]);

        $fcmMock->shouldReceive('sendDataOnlyToDevice')
            ->once()
            ->with(
                'android_fcm_token_xyz',
                [
                    'type' => 'speed_collaboration_ended',
                    'event_id' => (string) $event->id,
                ],
                Mockery::type('array')
            )
            ->andReturn(['success' => true, 'firebase_response' => ['name' => 'msg/2'], 'error' => null]);

        $service = new SpeedCollaborationLivePushService($fcmMock);

        $startStats = $service->broadcastLiveStarted($event);
        $this->assertSame(1, $startStats['android_sent']);

        $endStats = $service->broadcastLiveEnded($event);
        $this->assertSame(1, $endStats['android_sent']);
    }
}
