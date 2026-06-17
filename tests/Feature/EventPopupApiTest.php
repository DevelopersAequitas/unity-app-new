<?php

namespace Tests\Feature;

use App\Events\EventPopupUpdated;
use App\Jobs\SendEventPopupNotificationJob;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventPopupApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-17 12:30:00'));
        $this->setUpSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_user_can_fetch_active_popup_events(): void
    {
        Sanctum::actingAs($this->user());
        $event = $this->event(['show_popup' => true, 'popup_title' => 'New Event Available']);

        $this->getJson('/api/v1/events/popups')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Event popups fetched successfully.')
            ->assertJsonPath('data.0.event_id', $event->id)
            ->assertJsonPath('data.0.show_popup', true)
            ->assertJsonPath('data.0.already_seen', false);
    }

    public function test_old_expired_events_are_not_returned(): void
    {
        Sanctum::actingAs($this->user());
        $this->event(['show_popup' => true, 'start_at' => now()->subDays(2), 'end_at' => now()->subDay()]);

        $this->getJson('/api/v1/events/popups')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_can_update_popup_settings_and_version_increments(): void
    {
        EventFacade::fake([EventPopupUpdated::class]);
        Bus::fake();
        Sanctum::actingAs($this->user());
        $event = $this->event(['popup_version' => 1, 'realtime_popup' => false]);

        $this->patchJson("/api/v1/admin/events/{$event->id}/popup-settings", [
            'show_popup' => true,
            'realtime_popup' => true,
            'popup_title' => 'New Event Alert',
            'popup_message' => 'A new event is available. Register now.',
            'popup_action_url' => null,
        ])->assertOk()
            ->assertJsonPath('data.popup_version', 2)
            ->assertJsonPath('data.realtime_popup', true);

        EventFacade::assertDispatched(EventPopupUpdated::class);
        Bus::assertDispatched(SendEventPopupNotificationJob::class);
    }


    public function test_admin_can_toggle_popup_flags_for_one_event(): void
    {
        EventFacade::fake([EventPopupUpdated::class]);
        Bus::fake();
        Sanctum::actingAs($this->user());
        $event = $this->event([
            'popup_version' => 5,
            'show_popup' => false,
            'realtime_popup' => false,
            'popup_title' => 'Keep This Title',
        ]);
        $otherEvent = $this->event(['popup_version' => 2, 'show_popup' => false]);

        $this->postJson("/api/v1/admin/events/{$event->id}/popup-toggle", [
            'show_popup' => true,
            'realtime_popup' => true,
            'popup_message' => 'A new event is available. Register now.',
            'popup_action_url' => null,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Event popup settings updated successfully.')
            ->assertJsonPath('data.event_id', $event->id)
            ->assertJsonPath('data.show_popup', true)
            ->assertJsonPath('data.realtime_popup', true)
            ->assertJsonPath('data.popup_title', 'Keep This Title')
            ->assertJsonPath('data.popup_version', 6);

        $event->refresh();
        $otherEvent->refresh();

        $this->assertTrue((bool) $event->show_popup);
        $this->assertTrue((bool) $event->realtime_popup);
        $this->assertSame(6, (int) $event->popup_version);
        $this->assertNotNull($event->popup_last_triggered_at);
        $this->assertSame(2, (int) $otherEvent->popup_version);

        EventFacade::assertDispatched(EventPopupUpdated::class);
        Bus::assertDispatched(SendEventPopupNotificationJob::class);
    }

    public function test_seen_api_stores_popup_view_and_already_seen_is_returned(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);
        $event = $this->event(['show_popup' => true, 'popup_version' => 3]);

        $this->postJson("/api/v1/events/popups/{$event->id}/seen", ['popup_version' => 3])
            ->assertOk();

        $this->assertDatabaseHas('event_popup_views', [
            'user_id' => $user->id,
            'event_id' => $event->id,
            'popup_version' => 3,
        ]);

        $this->getJson('/api/v1/events/popups')
            ->assertOk()
            ->assertJsonPath('data.0.already_seen', true);
    }

    private function setUpSchema(): void
    {
        foreach (['event_popup_views', 'events', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();
            $table->string('location_text')->nullable();
            $table->string('banner_url')->nullable();
            $table->string('event_type')->nullable();
            $table->boolean('show_popup')->default(false);
            $table->boolean('realtime_popup')->default(false);
            $table->string('popup_title')->nullable();
            $table->text('popup_message')->nullable();
            $table->string('popup_action_url')->nullable();
            $table->timestamp('popup_last_triggered_at')->nullable();
            $table->unsignedInteger('popup_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('event_popup_views', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('event_id');
            $table->unsignedInteger('popup_version');
            $table->timestamp('seen_at');
            $table->timestamps();
            $table->unique(['user_id', 'event_id', 'popup_version']);
        });
    }

    private function user(): User
    {
        return User::query()->create(['id' => (string) Str::uuid(), 'email' => Str::uuid().'@example.test', 'status' => 'active']);
    }

    private function event(array $overrides = []): Event
    {
        return Event::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'title' => 'Business Meet',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'location_text' => 'Ahmedabad, Gujarat',
            'event_type' => 'circle_event',
            'show_popup' => false,
            'realtime_popup' => false,
            'popup_version' => 1,
        ], $overrides));
    }
}
