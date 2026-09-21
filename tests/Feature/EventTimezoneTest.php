<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Resources\Event\EventDetailResource;
use App\Http\Resources\Event\EventOccurrenceListResource;
use App\Models\Circle;
use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventTimezoneTest extends TestCase
{
    private string $originalTz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalTz = date_default_timezone_get();
        date_default_timezone_set('UTC');

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
            'app.timezone' => 'UTC',
        ]);
        DB::purge();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTz);

        parent::tearDown();
    }

    protected function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('role')->default('member');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('status')->default('active');
            $table->string('state_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('organizer_user_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->string('event_type')->default('circle_meeting');
            $table->string('event_category')->nullable();
            $table->string('state_name')->nullable();
            $table->string('mode')->default('offline');
            $table->text('location_text')->nullable();
            $table->string('visibility')->default('public');
            $table->boolean('is_virtual')->default(false);
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_public')->default(false);
            $table->boolean('member_registration_enabled')->default(true);
            $table->boolean('visitor_registration_enabled')->default(false);
            $table->boolean('qr_checkin_enabled')->default(true);
            $table->string('recurrence_type')->default('none');
            $table->integer('recurrence_interval')->nullable();
            $table->integer('recurrence_day_of_week')->nullable();
            $table->integer('recurrence_week_of_month')->nullable();
            $table->integer('recurrence_day_of_month')->nullable();
            $table->integer('recurrence_month')->nullable();
            $table->dateTime('recurrence_ends_at')->nullable();
            $table->decimal('ticket_price', 10, 2)->nullable();
            $table->integer('registration_limit')->nullable();
            $table->string('online_meeting_url')->nullable();
            $table->string('zoho_form_url')->nullable();
            $table->string('banner_url')->nullable();
            $table->json('agenda')->nullable();
            $table->json('speakers')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_occurrences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->date('occurrence_date');
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->integer('sequence')->default(1);
            $table->integer('registration_limit')->nullable();
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
            $table->string('status')->default('registered');
            $table->string('payment_status')->nullable();
            $table->boolean('payment_required')->default(false);
            $table->string('checkin_status')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_event_created_with_local_time_is_converted_to_utc(): void
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'display_name' => 'Admin User',
            'email' => 'admin@peersunity.com',
        ]);

        $circle = Circle::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'MSME ONE Ahmedabad',
        ]);

        $service = app(EventService::class);

        // Input 02:35 PM (14:35) on 2026-09-22 in Asia/Kolkata (IST = UTC+05:30)
        $event = $service->create([
            'title' => 'This is 2ed event for PG Unity',
            'circle_id' => $circle->id,
            'start_at' => '2026-09-22 14:35:00',
            'end_at' => '2026-09-22 15:35:00',
            'timezone' => 'Asia/Kolkata',
            'mode' => 'offline',
            'event_type' => 'circle_meeting',
            'recurrence_type' => 'none',
        ], $user);

        // Database start_at must be in UTC: 14:35 - 5h30m = 09:05:00
        $this->assertEquals('2026-09-22 09:05:00', Carbon::parse($event->start_at)->toDateTimeString());
        $this->assertEquals('2026-09-22 10:05:00', Carbon::parse($event->end_at)->toDateTimeString());

        // Occurrence should match UTC
        $occurrence = $event->occurrences->first();
        $this->assertNotNull($occurrence);
        $this->assertEquals('2026-09-22 09:05:00', Carbon::parse($occurrence->start_at)->toDateTimeString());
        $this->assertEquals('2026-09-22', $occurrence->occurrence_date->toDateString());

        // API Resource presentation
        $occurrence->load('event.circle', 'registrations');
        $request = Request::create('/api/v1/events', 'GET');
        $resource = (new EventOccurrenceListResource($occurrence))->toArray($request);

        // ISO format must have Z (UTC)
        $this->assertEquals('2026-09-22T09:05:00.000000Z', $resource['start_at']);
        $this->assertEquals('2026-09-22T10:05:00.000000Z', $resource['end_at']);

        // Human display fields must reflect local time (02:35 PM - 03:35 PM, 14:35:00)
        $this->assertEquals('14:35:00', $resource['start_time']);
        $this->assertEquals('2026-09-22', $resource['start_date']);
        $this->assertEquals('Sep 22, 2026', $resource['display_date']);
        $this->assertEquals('02:35 PM - 03:35 PM', $resource['display_time']);
    }

    public function test_event_detail_resource_returns_utc_iso_and_local_display_time(): void
    {
        $circle = Circle::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'MSME ONE Ahmedabad',
        ]);

        $event = Event::query()->create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'title' => 'Test Timezone Event',
            'start_at' => '2026-09-22 09:05:00', // Stored in UTC
            'end_at' => '2026-09-22 10:05:00',
            'event_type' => 'circle_meeting',
            'metadata' => ['timezone' => 'Asia/Kolkata'],
        ]);

        $request = Request::create('/api/v1/events/'.$event->id, 'GET');
        $resource = (new EventDetailResource($event))->toArray($request);

        $this->assertEquals('2026-09-22T09:05:00.000000Z', $resource['start_at']);
        $this->assertEquals('2026-09-22T10:05:00.000000Z', $resource['end_at']);
        $this->assertEquals('14:35:00', $resource['start_time']);
        $this->assertEquals('2026-09-22', $resource['start_date']);
        $this->assertEquals('Sep 22, 2026', $resource['display_date']);
        $this->assertEquals('02:35 PM - 03:35 PM', $resource['display_time']);
    }

    public function test_custom_timezone_via_request_header(): void
    {
        $circle = Circle::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'MSME ONE Dubai',
        ]);

        $event = Event::query()->create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'title' => 'Dubai Meet',
            'start_at' => '2026-09-22 09:05:00', // 09:05 UTC is 13:05 in Asia/Dubai (+04:00)
            'end_at' => '2026-09-22 10:05:00',
            'event_type' => 'circle_meeting',
            'metadata' => ['timezone' => 'Asia/Kolkata'],
        ]);

        $request = Request::create('/api/v1/events/'.$event->id, 'GET');
        $request->headers->set('X-Timezone', 'Asia/Dubai');
        $resource = (new EventDetailResource($event))->toArray($request);

        $this->assertEquals('2026-09-22T09:05:00.000000Z', $resource['start_at']);
        $this->assertEquals('13:05:00', $resource['start_time']);
        $this->assertEquals('01:05 PM - 02:05 PM', $resource['display_time']);
    }
}
