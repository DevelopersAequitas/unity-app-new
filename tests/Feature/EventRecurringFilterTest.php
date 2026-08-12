<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventRecurringFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpInMemoryDatabase();
    }

    private function setUpInMemoryDatabase(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('events');
        Schema::dropIfExists('event_occurrences');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('personal_access_tokens');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('password_hash');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title')->nullable();
            $table->string('event_type')->nullable();
            $table->string('recurrence_type')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('scheduled');
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

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('tokenable_type');
            $table->uuid('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }

    public function test_events_api_returns_only_earliest_upcoming_occurrence_for_recurring_events(): void
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'display_name' => 'Test User',
            'email' => 'testuser@example.com',
            'phone' => '9876543210',
            'password_hash' => Hash::make('password'),
        ]);

        Sanctum::actingAs($user);

        $recurringEvent = Event::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'MSME ONE',
            'event_type' => 'public_event',
            'recurrence_type' => 'monthly',
            'start_at' => '2026-09-07 10:40:00',
            'end_at' => '2026-09-07 11:40:00',
        ]);

        EventOccurrence::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => $recurringEvent->id,
            'occurrence_date' => '2026-09-07',
            'start_at' => '2026-09-07 10:40:00',
            'end_at' => '2026-09-07 11:40:00',
            'status' => 'scheduled',
        ]);

        EventOccurrence::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => $recurringEvent->id,
            'occurrence_date' => '2026-10-05',
            'start_at' => '2026-10-05 10:40:00',
            'end_at' => '2026-10-05 11:40:00',
            'status' => 'scheduled',
        ]);

        EventOccurrence::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => $recurringEvent->id,
            'occurrence_date' => '2026-11-02',
            'start_at' => '2026-11-02 10:40:00',
            'end_at' => '2026-11-02 11:40:00',
            'status' => 'scheduled',
        ]);

        $response = $this->getJson('/api/v1/events');

        $response->assertStatus(200);

        $items = $response->json('data.items');
        $msmeItems = array_filter($items, fn (array $item) => ($item['title'] ?? '') === 'MSME ONE');

        $this->assertCount(1, $msmeItems);
        $firstMsme = array_values($msmeItems)[0];
        $this->assertStringContainsString('2026-09-07', $firstMsme['start_at'] ?? $firstMsme['occurrence_date'] ?? '');
    }

    public function test_events_api_excludes_past_occurrences_for_non_recurring_events(): void
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'display_name' => 'Test User',
            'email' => 'testuser2@example.com',
            'phone' => '9876543211',
            'password_hash' => Hash::make('password'),
        ]);

        Sanctum::actingAs($user);

        $futureDate = Carbon::now()->addDays(5)->format('Y-m-d H:i:s');
        $pastDate = Carbon::now()->subDays(5)->format('Y-m-d H:i:s');

        $upcomingEvent = Event::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Upcoming One-Time Event',
            'event_type' => 'public_event',
            'recurrence_type' => 'none',
            'start_at' => $futureDate,
        ]);

        EventOccurrence::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => $upcomingEvent->id,
            'occurrence_date' => Carbon::now()->addDays(5)->toDateString(),
            'start_at' => $futureDate,
            'status' => 'scheduled',
        ]);

        $pastEvent = Event::query()->create([
            'id' => (string) Str::uuid(),
            'title' => 'Past One-Time Event',
            'event_type' => 'public_event',
            'recurrence_type' => 'none',
            'start_at' => $pastDate,
        ]);

        EventOccurrence::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => $pastEvent->id,
            'occurrence_date' => Carbon::now()->subDays(5)->toDateString(),
            'start_at' => $pastDate,
            'status' => 'scheduled',
        ]);

        $response = $this->getJson('/api/v1/events');

        $response->assertStatus(200);

        $items = $response->json('data.items');
        $titles = array_column($items, 'title');

        $this->assertContains('Upcoming One-Time Event', $titles);
        $this->assertNotContains('Past One-Time Event', $titles);
    }
}
