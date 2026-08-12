<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Services\Events\EventOccurrenceGeneratorService;
use App\Services\Events\EventService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventUpdateDateAndLocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpInMemoryDatabase();
    }

    public function test_updating_event_start_date_updates_occurrence_for_one_time_event(): void
    {
        $event = Event::query()->create([
            'title' => 'Test Event',
            'event_type' => 'circle_meeting',
            'mode' => 'offline',
            'start_at' => '2026-07-28 17:00:00',
            'end_at' => '2026-07-28 19:00:00',
            'recurrence_type' => 'none',
        ]);

        $generator = app(EventOccurrenceGeneratorService::class);
        $generator->generate($event);

        $this->assertEquals(1, EventOccurrence::query()->where('event_id', $event->id)->count());
        $occurrence = EventOccurrence::query()->where('event_id', $event->id)->first();
        $this->assertEquals('2026-07-28', $occurrence->start_at->toDateString());

        // Update event date to 2026-08-04
        $eventService = app(EventService::class);
        $eventService->update($event, [
            'title' => 'Test Event',
            'start_at' => '2026-08-04 17:00:00',
            'end_at' => '2026-08-04 19:00:00',
            'recurrence_type' => 'none',
        ]);

        $event->refresh();
        $this->assertEquals(1, EventOccurrence::query()->where('event_id', $event->id)->count());
        $updatedOccurrence = EventOccurrence::query()->where('event_id', $event->id)->first();
        $this->assertEquals('2026-08-04', $updatedOccurrence->start_at->toDateString());
    }

    public function test_updating_monthly_recurring_event_start_date_uses_fixed_day_pattern_and_starts_on_correct_date(): void
    {
        $event = Event::query()->create([
            'title' => 'MSME One Meet',
            'event_type' => 'circle_meeting',
            'mode' => 'offline',
            'start_at' => '2026-10-06 08:00:00',
            'end_at' => '2026-10-06 10:00:00',
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_ends_at' => '2027-08-04 00:00:00',
            'recurrence_week_of_month' => 1,
            'recurrence_day_of_week' => 2,
        ]);

        $generator = app(EventOccurrenceGeneratorService::class);
        $generator->generate($event);

        // Update event date to 2026-09-02 with fixed day pattern
        $eventService = app(EventService::class);
        $eventService->update($event, [
            'title' => 'MSME One Meet',
            'event_type' => 'circle_meeting',
            'mode' => 'offline',
            'start_at' => '2026-09-02 08:00:00',
            'end_at' => '2026-09-02 10:00:00',
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_ends_at' => '2027-08-04 00:00:00',
            'monthly_pattern' => 'fixed',
            'recurrence_day_of_month' => 2,
        ]);

        $event->refresh();
        $firstOccurrence = EventOccurrence::query()->where('event_id', $event->id)->orderBy('start_at')->first();
        $this->assertNotNull($firstOccurrence);
        $this->assertEquals('2026-09-02', $firstOccurrence->start_at->toDateString());
        $this->assertEquals('08:00:00', $firstOccurrence->start_at->format('H:i:s'));
    }

    public function test_clearing_address_line_removes_it_from_metadata_and_recalculates_location_text(): void
    {
        $event = Event::query()->create([
            'title' => 'Venue Event',
            'event_type' => 'circle_meeting',
            'mode' => 'offline',
            'start_at' => '2026-07-28 17:00:00',
            'end_at' => '2026-07-28 19:00:00',
            'recurrence_type' => 'none',
            'location_text' => 'Crowne Plaza, Crowne Plaza, Ahmedabad, Gujarat',
            'metadata' => [
                'venue_name' => 'Crowne Plaza',
                'address_line' => 'Crowne Plaza',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
            ],
        ]);

        $eventService = app(EventService::class);
        $eventService->update($event, [
            'title' => 'Venue Event',
            'event_type' => 'circle_meeting',
            'mode' => 'offline',
            'start_at' => '2026-07-28 17:00:00',
            'end_at' => '2026-07-28 19:00:00',
            'recurrence_type' => 'none',
            'venue_name' => 'Crowne Plaza',
            'address_line' => '', // Cleared
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
        ]);

        $event->refresh();

        $this->assertArrayNotHasKey('address_line', (array) $event->metadata);
        $this->assertEquals('Crowne Plaza, Ahmedabad, Gujarat', $event->location_text);
    }

    private function setUpInMemoryDatabase(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_occurrences');
        Schema::dropIfExists('events');
        Schema::dropIfExists('circles');

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->boolean('is_virtual')->default(false);
            $table->text('location_text')->nullable();
            $table->json('agenda')->nullable();
            $table->json('speakers')->nullable();
            $table->text('banner_url')->nullable();
            $table->string('visibility')->default('members');
            $table->boolean('is_paid')->default(false);
            $table->json('metadata')->nullable();
            $table->string('event_type')->nullable();
            $table->string('event_category')->nullable();
            $table->string('mode')->nullable();
            $table->integer('registration_limit')->nullable();
            $table->decimal('ticket_price', 10, 2)->default(0);
            $table->boolean('qr_checkin_enabled')->default(false);
            $table->boolean('is_public')->default(false);
            $table->string('recurrence_type')->nullable();
            $table->integer('recurrence_interval')->nullable()->default(1);
            $table->integer('recurrence_day_of_week')->nullable();
            $table->integer('recurrence_week_of_month')->nullable();
            $table->integer('recurrence_day_of_month')->nullable();
            $table->integer('recurrence_month')->nullable();
            $table->timestamp('recurrence_ends_at')->nullable();
            $table->boolean('visitor_registration_enabled')->default(false);
            $table->boolean('member_registration_enabled')->default(true);
            $table->text('online_meeting_url')->nullable();
            $table->text('zoho_form_url')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_occurrences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->date('occurrence_date')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('status')->nullable();
            $table->integer('sequence')->default(1);
            $table->integer('registered_count')->default(0);
            $table->integer('checked_in_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('occurrence_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('status')->nullable();
            $table->boolean('payment_required')->default(false);
            $table->string('payment_status')->nullable();
            $table->string('checkin_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
