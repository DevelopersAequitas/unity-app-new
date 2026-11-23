<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Event;
use App\Models\Notifications\NotificationCampaign;
use App\Models\SpeedNetworking\SpeedNetworkingEvent;
use App\Models\User;
use App\Services\Notifications\CampaignService;
use App\Services\Notifications\NotificationAudienceService;
use App\Services\Notifications\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventLiveNotificationTest extends TestCase
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

        $this->setUpDatabase();
    }

    private function setUpDatabase(): void
    {
        Schema::dropIfExists('notification_campaign_runs');
        Schema::dropIfExists('notification_campaigns');
        Schema::dropIfExists('speed_networking_events');
        Schema::dropIfExists('events');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->unique();
            $table->string('city')->nullable();
            $table->string('status')->default('active');
            $table->string('membership_status')->default('active');
            $table->integer('coin_balance')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->boolean('is_active')->default(true);
            $table->string('event_type')->nullable();
            $table->string('banner_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('speed_networking_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('status')->default('scheduled');
            $table->timestamp('scheduled_start_time')->nullable();
            $table->timestamp('scheduled_end_time')->nullable();
            $table->timestamp('actual_start_time')->nullable();
            $table->timestamp('actual_end_time')->nullable();
            $table->integer('round_duration_seconds')->default(180);
            $table->integer('break_duration_seconds')->default(30);
            $table->integer('total_rounds')->default(5);
            $table->string('banner_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notification_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('channel')->default('push');
            $table->string('trigger_type')->nullable();
            $table->string('frequency')->nullable();
            $table->string('priority')->default('urgent');
            $table->string('audience_type')->nullable();
            $table->string('title_template');
            $table->text('body_template');
            $table->string('tap_screen')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_campaign_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id');
            $table->string('run_type')->default('scheduled');
            $table->string('status')->default('running');
            $table->integer('audience_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_event_starting_now_notification_is_skipped_when_event_is_not_live(): void
    {
        // 1. Create a user
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'Peer',
            'name' => 'Test Peer',
            'display_name' => 'Test Peer',
            'email' => 'peer_'.Str::random(6).'@example.com',
            'membership_status' => 'active',
        ]);

        // 2. Create a future Speed Networking event (scheduled for 2 hours in the future)
        $futureStart = Carbon::now()->addHours(2);
        $futureEnd = Carbon::now()->addHours(3);

        $event = Event::create([
            'id' => (string) Str::uuid(),
            'title' => '1:1 Speed Collaboration',
            'start_at' => $futureStart,
            'end_at' => $futureEnd,
            'status' => 'scheduled',
            'is_active' => true,
            'event_type' => 'speed_networking',
        ]);

        SpeedNetworkingEvent::create([
            'id' => $event->id,
            'title' => '1:1 Speed Collaboration',
            'status' => SpeedNetworkingEvent::STATUS_SCHEDULED,
            'scheduled_start_time' => $futureStart,
            'scheduled_end_time' => $futureEnd,
            'round_duration_seconds' => 180,
            'break_duration_seconds' => 30,
            'total_rounds' => 5,
        ]);

        // Mock NotificationService: sendToUser MUST NEVER be called
        $mockNotifications = $this->createMock(NotificationService::class);
        $mockNotifications->expects($this->never())->method('sendToUser');

        $audienceService = $this->createMock(NotificationAudienceService::class);

        $service = new CampaignService($mockNotifications, $audienceService);

        // Verify findCurrentLiveEvent returns null for future event
        $this->assertNull($service->findCurrentLiveEvent());

        // Create campaign instance for event_starting_now
        $campaign = NotificationCampaign::create([
            'id' => (string) Str::uuid(),
            'code' => 'event_starting_now',
            'name' => 'Event starting now',
            'category' => 'Upcoming event reminder',
            'trigger_type' => 'event_live_reminder',
            'frequency' => 'every-five-minutes',
            'priority' => 'urgent',
            'audience_type' => 'event_attendees',
            'title_template' => '[Event Title] is live now',
            'body_template' => 'Tap to join the live meeting.',
            'tap_screen' => 'live_meeting',
            'is_active' => true,
        ]);

        // Run campaign: MUST be skipped because event is NOT live!
        $run = $service->runCampaign($campaign);

        $this->assertEquals('skipped', $run->status);
        $this->assertEquals(0, $run->sent_count);
    }

    public function test_event_starting_now_notification_sends_when_event_is_actually_live(): void
    {
        // 1. Create a user
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Active',
            'last_name' => 'Peer',
            'name' => 'Active Peer',
            'display_name' => 'Active Peer',
            'email' => 'active_'.Str::random(6).'@example.com',
            'membership_status' => 'active',
        ]);

        // 2. Create an event that is ACTUALLY live right now
        $startTime = Carbon::now()->subMinutes(5);
        $endTime = Carbon::now()->addMinutes(25);

        $event = Event::create([
            'id' => (string) Str::uuid(),
            'title' => '1:1 Speed Collaboration',
            'start_at' => $startTime,
            'end_at' => $endTime,
            'status' => 'in_progress',
            'is_active' => true,
            'event_type' => 'speed_networking',
        ]);

        SpeedNetworkingEvent::create([
            'id' => $event->id,
            'title' => '1:1 Speed Collaboration',
            'status' => SpeedNetworkingEvent::STATUS_IN_PROGRESS,
            'actual_start_time' => $startTime,
            'scheduled_start_time' => $startTime,
            'scheduled_end_time' => $endTime,
            'round_duration_seconds' => 180,
            'break_duration_seconds' => 30,
            'total_rounds' => 5,
        ]);

        $mockNotifications = $this->createMock(NotificationService::class);
        $mockNotifications->method('renderTemplate')->willReturnCallback(function ($tpl, $vars) {
            return str_replace(['[Event Title]'], [$vars['event_title'] ?? ''], $tpl);
        });
        // Expect notification to be sent because event IS live!
        $mockNotifications->expects($this->once())
            ->method('sendToUser')
            ->with(
                $this->anything(),
                $this->equalTo('event_starting_now'),
                $this->equalTo('1:1 Speed Collaboration is live now'),
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn(new AppNotification);

        $audienceService = $this->createMock(NotificationAudienceService::class);

        $service = new CampaignService($mockNotifications, $audienceService);

        // findCurrentLiveEvent MUST return the live event
        $live = $service->findCurrentLiveEvent();
        $this->assertNotNull($live);
        $this->assertEquals('1:1 Speed Collaboration', $live->title);

        $campaign = NotificationCampaign::create([
            'id' => (string) Str::uuid(),
            'code' => 'event_starting_now',
            'name' => 'Event starting now',
            'category' => 'Upcoming event reminder',
            'trigger_type' => 'event_live_reminder',
            'frequency' => 'every-five-minutes',
            'priority' => 'urgent',
            'audience_type' => 'event_attendees',
            'title_template' => '[Event Title] is live now',
            'body_template' => 'Tap to join the live meeting.',
            'tap_screen' => 'live_meeting',
            'is_active' => true,
        ]);

        $run = $service->runCampaign($campaign);

        $this->assertEquals('finished', $run->status);
        $this->assertEquals(1, $run->sent_count);
    }
}
