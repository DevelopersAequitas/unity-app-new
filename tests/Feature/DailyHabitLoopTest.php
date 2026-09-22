<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendDailyHabitWhatsappJob;
use App\Models\Notifications\DailyHabitSend;
use App\Models\User;
use App\Models\WhatsappMessageDeliveryLog;
use App\Models\WhatsappTemplate;
use App\Services\Notifications\DailyHabitLoopService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyHabitLoopTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropIfExists('whatsapp_message_delivery_logs');
        Schema::dropIfExists('daily_habit_sends');
        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('circles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('whatsapp_templates');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('secondary_mobile', 20)->nullable();
            $table->string('timezone', 100)->nullable();
            $table->uuid('active_circle_id')->nullable();
            $table->string('status', 50)->default('active');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->uuid('founder_id')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id')->index();
            $table->uuid('user_id')->index();
            $table->string('status', 50)->default('approved');
            $table->timestamps();
        });

        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('template_key')->unique();
            $table->string('template_name');
            $table->string('webhook_url');
            $table->string('webhook_secret');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('daily_habit_sends', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->integer('day_number');
            $table->timestamp('scheduled_at')->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->string('status', 50)->default('scheduled');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'day_number']);
        });

        Schema::create('whatsapp_message_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('notification_id', 100)->nullable()->index();
            $table->string('template_key', 100);
            $table->string('template_name', 255)->nullable();
            $table->string('phone', 50);
            $table->text('creative_url')->nullable();
            $table->string('provider', 50)->default('fleximsg');
            $table->string('provider_message_id', 255)->nullable()->index();
            $table->string('status', 50)->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Test 1: New user registration initializes the Daily Habit journey (Days 1–12 pre-scheduled).
     */
    public function test_new_user_registration_initializes_daily_habit_journey(): void
    {
        $regTime = Carbon::parse('2026-09-21 10:30:00', 'UTC');
        Carbon::setTestNow($regTime);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        $service = new DailyHabitLoopService;
        $service->startJourney($user, $regTime);

        $this->assertSame(12, DailyHabitSend::where('user_id', $user->id)->count());

        // Check Day 1 at +24h and Day 12 at +288h
        $this->assertDatabaseHas('daily_habit_sends', [
            'user_id' => $user->id,
            'day_number' => 1,
            'status' => 'scheduled',
            'scheduled_at' => '2026-09-22 10:30:00',
        ]);

        $this->assertDatabaseHas('daily_habit_sends', [
            'user_id' => $user->id,
            'day_number' => 12,
            'status' => 'scheduled',
            'scheduled_at' => '2026-10-03 10:30:00',
        ]);
    }

    /**
     * Test 2: Day 1 becomes due after 24 hours.
     */
    public function test_day_1_becomes_due_after_24_hours(): void
    {
        $regTime = Carbon::parse('2026-09-21 10:30:00', 'UTC');
        Carbon::setTestNow($regTime);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        $service = new DailyHabitLoopService;
        $service->startJourney($user, $regTime);

        // Advance 23h59m: 0 due
        Carbon::setTestNow($regTime->copy()->addHours(23)->addMinutes(59));
        $this->assertSame(0, DailyHabitSend::where('status', 'scheduled')->where('scheduled_at', '<=', now())->count());

        // Advance 24h: Day 1 is due
        Carbon::setTestNow($regTime->copy()->addHours(24));
        $due = DailyHabitSend::where('status', 'scheduled')->where('scheduled_at', '<=', now())->get();
        $this->assertCount(1, $due);
        $this->assertSame(1, $due->first()->day_number);
    }

    /**
     * Test 3: Day 2 becomes due after 48 hours.
     */
    public function test_day_2_becomes_due_after_48_hours(): void
    {
        $regTime = Carbon::parse('2026-09-21 10:30:00', 'UTC');
        Carbon::setTestNow($regTime);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        $service = new DailyHabitLoopService;
        $service->startJourney($user, $regTime);

        // Advance 48 hours
        Carbon::setTestNow($regTime->copy()->addHours(48));

        $day2 = DailyHabitSend::where('user_id', $user->id)
            ->where('day_number', 2)
            ->firstOrFail();

        $this->assertTrue($day2->scheduled_at->lessThanOrEqualTo(now()));
        $this->assertSame('2026-09-23 10:30:00', $day2->scheduled_at->toDateTimeString());
    }

    /**
     * Test 4: Day 3 becomes due after 72 hours.
     */
    public function test_day_3_becomes_due_after_72_hours(): void
    {
        $regTime = Carbon::parse('2026-09-21 10:30:00', 'UTC');
        Carbon::setTestNow($regTime);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        $service = new DailyHabitLoopService;
        $service->startJourney($user, $regTime);

        // Advance 72 hours
        Carbon::setTestNow($regTime->copy()->addHours(72));

        $day3 = DailyHabitSend::where('user_id', $user->id)
            ->where('day_number', 3)
            ->firstOrFail();

        $this->assertTrue($day3->scheduled_at->lessThanOrEqualTo(now()));
        $this->assertSame('2026-09-24 10:30:00', $day3->scheduled_at->toDateTimeString());
    }

    /**
     * Test 5: Day 12 becomes due after 288 hours.
     */
    public function test_day_12_becomes_due_after_288_hours(): void
    {
        $regTime = Carbon::parse('2026-09-21 10:30:00', 'UTC');
        Carbon::setTestNow($regTime);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        $service = new DailyHabitLoopService;
        $service->startJourney($user, $regTime);

        // Advance 288 hours (12 days)
        Carbon::setTestNow($regTime->copy()->addHours(288));

        $day12 = DailyHabitSend::where('user_id', $user->id)
            ->where('day_number', 12)
            ->firstOrFail();

        $this->assertTrue($day12->scheduled_at->lessThanOrEqualTo(now()));
        $this->assertSame('2026-10-03 10:30:00', $day12->scheduled_at->toDateTimeString());
    }

    /**
     * Test 6: Different users have independent schedules based on their own registration timestamps.
     */
    public function test_different_users_have_independent_schedules(): void
    {
        $timeA = Carbon::parse('2026-09-21 10:00:00', 'UTC');
        $userA = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'UserA',
            'email' => 'usera@example.com',
            'phone' => '9876543201',
            'timezone' => 'UTC',
        ]);

        $timeB = Carbon::parse('2026-09-21 15:00:00', 'UTC');
        $userB = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'UserB',
            'email' => 'userb@example.com',
            'phone' => '9876543202',
            'timezone' => 'UTC',
        ]);

        $service = new DailyHabitLoopService;
        $service->startJourney($userA, $timeA);
        $service->startJourney($userB, $timeB);

        // At 11:00 AM next day: User A Day 1 is due, User B Day 1 is NOT yet due
        Carbon::setTestNow(Carbon::parse('2026-09-22 11:00:00', 'UTC'));
        $dueSends = DailyHabitSend::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->pluck('user_id')
            ->toArray();

        $this->assertContains($userA->id, $dueSends);
        $this->assertNotContains($userB->id, $dueSends);
    }

    /**
     * Test 7: Day 5 failure does NOT prevent Day 6.
     */
    public function test_day_5_failure_does_not_prevent_day_6(): void
    {
        $regTime = Carbon::parse('2026-09-21 10:30:00', 'UTC');
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'User56',
            'email' => 'user56@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        $service = new DailyHabitLoopService;
        $service->startJourney($user, $regTime);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_5',
            'template_name' => 'Day 5 Template',
            'webhook_url' => 'https://webhook.example.com/day5',
            'webhook_secret' => 'SECRET_D5',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_6',
            'template_name' => 'Day 6 Template',
            'webhook_url' => 'https://webhook.example.com/day6',
            'webhook_secret' => 'SECRET_D6',
        ]);

        // Day 5 execution at T0 + 120h -> Fails with provider 500 error
        Carbon::setTestNow($regTime->copy()->addHours(120));
        $send5 = DailyHabitSend::where('user_id', $user->id)->where('day_number', 5)->firstOrFail();

        Http::fake([
            'https://webhook.example.com/day5' => Http::response(['error' => 'Server Error'], 500),
            'https://webhook.example.com/day6' => Http::response(['success' => true, 'wamid' => 'WAMID_D6_OK'], 200),
        ]);

        try {
            dispatch_sync(new SendDailyHabitWhatsappJob($send5->id));
        } catch (\Throwable) {
            // Expected failure
        }

        $this->assertSame('failed', $send5->refresh()->status);

        // 24 hours later: Day 6 execution at T0 + 144h -> MUST STILL SEND
        Carbon::setTestNow($regTime->copy()->addHours(144));
        $send6 = DailyHabitSend::where('user_id', $user->id)->where('day_number', 6)->firstOrFail();

        dispatch_sync(new SendDailyHabitWhatsappJob($send6->id));

        $this->assertSame('sent', $send6->refresh()->status);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://webhook.example.com/day6';
        });
    }

    /**
     * Test 8: Day 1 failure does NOT prevent Day 2.
     */
    public function test_day_1_failure_does_not_prevent_day_2(): void
    {
        $regTime = Carbon::parse('2026-09-21 10:30:00', 'UTC');
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'User12',
            'email' => 'user12@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        $service = new DailyHabitLoopService;
        $service->startJourney($user, $regTime);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'Day 1 Template',
            'webhook_url' => 'https://webhook.example.com/day1',
            'webhook_secret' => 'SECRET_D1',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'business_referrals_day_2',
            'template_name' => 'Day 2 Template',
            'webhook_url' => 'https://webhook.example.com/day2',
            'webhook_secret' => 'SECRET_D2',
        ]);

        // Day 1 fails
        Carbon::setTestNow($regTime->copy()->addHours(24));
        $send1 = DailyHabitSend::where('user_id', $user->id)->where('day_number', 1)->firstOrFail();

        Http::fake([
            'https://webhook.example.com/day1' => Http::response(['error' => 'Failure'], 500),
            'https://webhook.example.com/day2' => Http::response(['success' => true, 'wamid' => 'WAMID_D2_OK'], 200),
        ]);

        try {
            dispatch_sync(new SendDailyHabitWhatsappJob($send1->id));
        } catch (\Throwable) {
            // Expected
        }

        $this->assertSame('failed', $send1->refresh()->status);

        // Day 2 at T0 + 48h must still send
        Carbon::setTestNow($regTime->copy()->addHours(48));
        $send2 = DailyHabitSend::where('user_id', $user->id)->where('day_number', 2)->firstOrFail();

        dispatch_sync(new SendDailyHabitWhatsappJob($send2->id));

        $this->assertSame('sent', $send2->refresh()->status);
    }

    /**
     * Test 9: Day 8 failure does NOT prevent Day 9.
     */
    public function test_day_8_failure_does_not_prevent_day_9(): void
    {
        $regTime = Carbon::parse('2026-09-21 10:30:00', 'UTC');
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'User89',
            'email' => 'user89@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        $service = new DailyHabitLoopService;
        $service->startJourney($user, $regTime);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_8',
            'template_name' => 'Day 8 Template',
            'webhook_url' => 'https://webhook.example.com/day8',
            'webhook_secret' => 'SECRET_D8',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_9',
            'template_name' => 'Day 9 Template',
            'webhook_url' => 'https://webhook.example.com/day9',
            'webhook_secret' => 'SECRET_D9',
        ]);

        // Day 8 fails at T0 + 192h
        Carbon::setTestNow($regTime->copy()->addHours(192));
        $send8 = DailyHabitSend::where('user_id', $user->id)->where('day_number', 8)->firstOrFail();

        Http::fake([
            'https://webhook.example.com/day8' => Http::response(['error' => 'Timeout'], 504),
            'https://webhook.example.com/day9' => Http::response(['success' => true, 'wamid' => 'WAMID_D9_OK'], 200),
        ]);

        try {
            dispatch_sync(new SendDailyHabitWhatsappJob($send8->id));
        } catch (\Throwable) {
            // Expected
        }

        $this->assertSame('failed', $send8->refresh()->status);

        // Day 9 executes at T0 + 216h
        Carbon::setTestNow($regTime->copy()->addHours(216));
        $send9 = DailyHabitSend::where('user_id', $user->id)->where('day_number', 9)->firstOrFail();

        dispatch_sync(new SendDailyHabitWhatsappJob($send9->id));

        $this->assertSame('sent', $send9->refresh()->status);
    }

    /**
     * Test 10: A processed Day is not sent again.
     */
    public function test_processed_day_is_not_sent_again(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alice',
            'email' => 'alice@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'Day 1',
            'webhook_url' => 'https://webhook.example.com/day1',
            'webhook_secret' => 'SECRET_D1',
        ]);

        $send = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'day_number' => 1,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        Http::fake([
            'https://webhook.example.com/day1' => Http::response(['success' => true], 200),
        ]);

        // First execution
        dispatch_sync(new SendDailyHabitWhatsappJob($send->id));
        $this->assertSame('sent', $send->refresh()->status);

        // Second execution
        dispatch_sync(new SendDailyHabitWhatsappJob($send->id));

        Http::assertSentCount(1);
    }

    /**
     * Test 11: Repeated scheduler execution does not create duplicate sends.
     */
    public function test_repeated_scheduler_execution_does_not_create_duplicate_sends(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'SchedulerUser',
            'email' => 'sched@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'Day 1',
            'webhook_url' => 'https://webhook.example.com/day1',
            'webhook_secret' => 'SECRET_D1',
        ]);

        $send = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'day_number' => 1,
            'scheduled_at' => now()->subMinute(),
            'status' => 'scheduled',
        ]);

        Http::fake([
            'https://webhook.example.com/day1' => Http::response(['success' => true], 200),
        ]);

        // First scheduler run dispatches 1
        $this->artisan('habit-loop:send-daily')
            ->expectsOutput('Dispatched 1 Daily Habit Loop WhatsApp jobs.')
            ->assertExitCode(0);

        // Mark as sent
        $send->refresh();
        $this->assertSame('sent', $send->status);

        // Second scheduler run dispatches 0
        $this->artisan('habit-loop:send-daily')
            ->expectsOutput('Dispatched 0 Daily Habit Loop WhatsApp jobs.')
            ->assertExitCode(0);

        Http::assertSentCount(1);
    }

    /**
     * Test 12: Each Day resolves its template from whatsapp_templates.
     */
    public function test_each_day_resolves_template_from_whatsapp_templates(): void
    {
        $service = new DailyHabitLoopService;

        // Day 1
        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'Day 1 Profile',
            'webhook_url' => 'https://webhook.example.com/d1',
            'webhook_secret' => 'SEC1',
        ]);
        $this->assertSame('day_1_complete_profile', $service->resolveTemplateForDay(1)?->template_key);

        // Day 2
        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'business_referrals_day_2',
            'template_name' => 'Day 2 Referrals',
            'webhook_url' => 'https://webhook.example.com/d2',
            'webhook_secret' => 'SEC2',
        ]);
        $this->assertSame('business_referrals_day_2', $service->resolveTemplateForDay(2)?->template_key);

        // Day 4
        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_4_business_referral',
            'template_name' => 'Day 4 Deals',
            'webhook_url' => 'https://webhook.example.com/d4',
            'webhook_secret' => 'SEC4',
        ]);
        $this->assertSame('day_4_business_referral', $service->resolveTemplateForDay(4)?->template_key);

        // Day 7
        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_7_introduce_yourself_circle',
            'template_name' => 'Day 7 Circle',
            'webhook_url' => 'https://webhook.example.com/d7',
            'webhook_secret' => 'SEC7',
        ]);
        $this->assertSame('day_7_introduce_yourself_circle', $service->resolveTemplateForDay(7)?->template_key);

        // Dynamic days
        foreach ([3, 5, 6, 8, 9, 10, 11, 12] as $d) {
            WhatsappTemplate::create([
                'id' => (string) Str::uuid(),
                'template_key' => "day_{$d}",
                'template_name' => "Day {$d} Name",
                'webhook_url' => "https://webhook.example.com/d{$d}",
                'webhook_secret' => "SEC{$d}",
            ]);
            $this->assertSame("day_{$d}", $service->resolveTemplateForDay($d)?->template_key);
        }
    }

    /**
     * Test 13: Each attempt/result is recorded in whatsapp_message_delivery_logs.
     */
    public function test_each_attempt_is_recorded_in_delivery_logs(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'LogUser',
            'email' => 'loguser@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'Day 1 Profile',
            'webhook_url' => 'https://webhook.example.com/day1',
            'webhook_secret' => 'SECRET_D1',
        ]);

        $send = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'day_number' => 1,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        Http::fake([
            'https://webhook.example.com/day1' => Http::response(['success' => true, 'wamid' => 'WAMID_RECORD_1'], 200),
        ]);

        dispatch_sync(new SendDailyHabitWhatsappJob($send->id));

        $this->assertDatabaseHas('whatsapp_message_delivery_logs', [
            'user_id' => $user->id,
            'template_key' => 'day_1_complete_profile',
            'status' => 'sent',
            'provider_message_id' => 'WAMID_RECORD_1',
        ]);
    }

    /**
     * Test 14: Day 12 is the final Day.
     */
    public function test_day_12_is_final_day(): void
    {
        $regTime = Carbon::parse('2026-09-21 10:30:00', 'UTC');
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'FinalUser',
            'email' => 'final@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        $service = new DailyHabitLoopService;
        $service->startJourney($user, $regTime);

        $maxDay = DailyHabitSend::where('user_id', $user->id)->max('day_number');
        $this->assertSame(12, $maxDay);
    }

    /**
     * Test 15: Day 13 is never attempted.
     */
    public function test_day_13_is_never_attempted(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'No13',
            'email' => 'no13@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        $service = new DailyHabitLoopService;
        $service->scheduleNextDay($user, 12, now());

        $this->assertFalse(DailyHabitSend::where('user_id', $user->id)->where('day_number', 13)->exists());

        // If a Day 13 send record is dispatched to Job, it must fail permanently
        $send13 = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'day_number' => 13,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        dispatch_sync(new SendDailyHabitWhatsappJob($send13->id));

        $this->assertSame('failed', $send13->refresh()->status);
        $this->assertStringContainsString('exceeds maximum 12-day sequence', (string) $send13->error_message);
    }

    /**
     * Test 16: FlexiMSG/provider failure is logged without blocking future Days.
     */
    public function test_provider_failure_logged_without_blocking_future_days(): void
    {
        $regTime = Carbon::parse('2026-09-21 10:30:00', 'UTC');
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'ProviderFailUser',
            'email' => 'prov@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        $service = new DailyHabitLoopService;
        $service->startJourney($user, $regTime);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_5',
            'template_name' => 'Day 5 Template',
            'webhook_url' => 'https://webhook.example.com/day5',
            'webhook_secret' => 'SECRET_D5',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_6',
            'template_name' => 'Day 6 Template',
            'webhook_url' => 'https://webhook.example.com/day6',
            'webhook_secret' => 'SECRET_D6',
        ]);

        // FlexiMSG returns whatsapp_triggered = false with error_message
        Http::fake([
            'https://webhook.example.com/day5' => Http::response([
                'success' => true,
                'whatsapp_triggered' => false,
                'error_message' => 'Invalid secret key',
                'log_id' => 'FLEXI_FAIL_SECRET',
            ], 200),
            'https://webhook.example.com/day6' => Http::response(['success' => true, 'wamid' => 'WAMID_D6_SUCCESS'], 200),
        ]);

        Carbon::setTestNow($regTime->copy()->addHours(120));
        $send5 = DailyHabitSend::where('user_id', $user->id)->where('day_number', 5)->firstOrFail();

        try {
            dispatch_sync(new SendDailyHabitWhatsappJob($send5->id));
        } catch (\Throwable) {
            // Expected
        }

        // Check delivery log for Day 5
        $deliveryLog5 = WhatsappMessageDeliveryLog::where('user_id', $user->id)
            ->where('template_key', 'day_5')
            ->first();

        $this->assertNotNull($deliveryLog5);
        $this->assertSame('failed', $deliveryLog5->status);
        $this->assertStringContainsString('Invalid secret key', (string) $deliveryLog5->error_message);
        $this->assertSame('failed', $send5->refresh()->status);

        // Day 6 executes 24 hours later and succeeds
        Carbon::setTestNow($regTime->copy()->addHours(144));
        $send6 = DailyHabitSend::where('user_id', $user->id)->where('day_number', 6)->firstOrFail();

        dispatch_sync(new SendDailyHabitWhatsappJob($send6->id));

        $this->assertSame('sent', $send6->refresh()->status);

        $deliveryLog6 = WhatsappMessageDeliveryLog::where('user_id', $user->id)
            ->where('template_key', 'day_6')
            ->first();

        $this->assertNotNull($deliveryLog6);
        $this->assertSame('sent', $deliveryLog6->status);
    }
}
