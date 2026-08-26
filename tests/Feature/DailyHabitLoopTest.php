<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendDailyHabitWhatsappJob;
use App\Models\Notifications\DailyHabitSend;
use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\Notifications\DailyHabitLoopService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyHabitLoopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('daily_habit_sends');
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
            $table->string('status', 50)->default('active');
            $table->softDeletes();
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
            $table->timestamp('journey_started_at');
            $table->integer('day_number');
            $table->timestamp('scheduled_at')->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->string('status', 50)->default('scheduled');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'day_number']);
        });
    }

    /**
     * Test Day 1 scheduling before 10 AM.
     */
    public function test_day_1_before_10_am(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '9876543210',
            'timezone' => 'Asia/Kolkata', // UTC +05:30
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'day_1_complete_profile',
            'webhook_url' => 'https://webhook.example.com/day1',
            'webhook_secret' => 'SECRET_D1',
        ]);

        // 9:30 AM local Asia/Kolkata is 04:00:00 UTC
        Carbon::setTestNow(Carbon::parse('2026-08-25 04:00:00', 'UTC'));

        $service = new DailyHabitLoopService;
        $service->startJourney($user);

        $this->assertDatabaseHas('daily_habit_sends', [
            'user_id' => $user->id,
            'day_number' => 1,
            'status' => 'scheduled',
            'scheduled_at' => '2026-08-25 04:30:00', // 10:00 AM local
        ]);
    }

    /**
     * Test Day 1 scheduling after 11 AM.
     */
    public function test_day_1_after_11_am(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '9876543210',
            'timezone' => 'Asia/Kolkata',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'day_1_complete_profile',
            'webhook_url' => 'https://webhook.example.com/day1',
            'webhook_secret' => 'SECRET_D1',
        ]);

        // 11:30 AM local Asia/Kolkata is 06:00:00 UTC
        Carbon::setTestNow(Carbon::parse('2026-08-25 06:00:00', 'UTC'));

        $service = new DailyHabitLoopService;
        $service->startJourney($user);

        $this->assertDatabaseHas('daily_habit_sends', [
            'user_id' => $user->id,
            'day_number' => 1,
            'status' => 'scheduled',
            'scheduled_at' => '2026-08-26 04:30:00', // Tomorrow 10:00 AM local
        ]);
    }

    /**
     * Test Day 1 scheduling at 2 PM.
     */
    public function test_day_1_at_2_pm(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '9876543210',
            'timezone' => 'Asia/Kolkata',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'day_1_complete_profile',
            'webhook_url' => 'https://webhook.example.com/day1',
            'webhook_secret' => 'SECRET_D1',
        ]);

        // 2:00 PM local Asia/Kolkata is 08:30:00 UTC
        Carbon::setTestNow(Carbon::parse('2026-08-25 08:30:00', 'UTC'));

        $service = new DailyHabitLoopService;
        $service->startJourney($user);

        $this->assertDatabaseHas('daily_habit_sends', [
            'user_id' => $user->id,
            'day_number' => 1,
            'status' => 'scheduled',
            'scheduled_at' => '2026-08-26 04:30:00',
        ]);
    }

    /**
     * Test consecutive day scheduling (Day 2 to Day 30).
     */
    public function test_consecutive_scheduling_day_2_to_30(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '9876543210',
            'timezone' => 'Asia/Kolkata',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'day_1_complete_profile',
            'webhook_url' => 'https://webhook.example.com/day1',
            'webhook_secret' => 'SECRET_D1',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_2_engagement',
            'template_name' => 'day_2_engagement',
            'webhook_url' => 'https://webhook.example.com/day2',
            'webhook_secret' => 'SECRET_D2',
        ]);

        $send = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'journey_started_at' => now(),
            'day_number' => 1,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        Http::fake([
            'https://webhook.example.com/day1' => Http::response(['success' => true], 200),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-25 05:00:00', 'UTC'));
        dispatch_sync(new SendDailyHabitWhatsappJob($send->id));

        $this->assertDatabaseHas('daily_habit_sends', [
            'id' => $send->id,
            'status' => 'sent',
            'sent_at' => '2026-08-25 05:00:00',
        ]);

        $this->assertDatabaseHas('daily_habit_sends', [
            'user_id' => $user->id,
            'day_number' => 2,
            'status' => 'scheduled',
            'scheduled_at' => '2026-08-26 05:00:00',
        ]);
    }

    /**
     * Test database template single source of truth behavior (Requirements 1-6).
     */
    public function test_database_single_source_of_truth(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alice',
            'email' => 'alice@example.com',
            'phone' => '9999999999',
            'timezone' => 'UTC',
        ]);

        // 1. Create a template in the database
        $template = WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'DB_Template_Name',
            'webhook_url' => 'https://webhook.example.com/original-url',
            'webhook_secret' => 'ORIGINAL_SECRET',
        ]);

        $send = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'journey_started_at' => now(),
            'day_number' => 1,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        // Mock endpoints
        Http::fake([
            'https://webhook.example.com/original-url' => Http::response(['success' => true], 200),
            'https://webhook.example.com/new-url' => Http::response(['success' => true], 200),
        ]);

        // Resolve template dynamically and assert variables are read from DB
        $service = new DailyHabitLoopService;
        $resolved = $service->resolveTemplateForDay(1);
        $this->assertNotNull($resolved);
        $this->assertSame('day_1_complete_profile', $resolved->template_key);
        $this->assertSame('DB_Template_Name', $resolved->template_name);
        $this->assertSame('https://webhook.example.com/original-url', $resolved->webhook_url);
        $this->assertSame('ORIGINAL_SECRET', $resolved->webhook_secret);

        // Verify sent message uses the original configuration
        dispatch_sync(new SendDailyHabitWhatsappJob($send->id));

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://webhook.example.com/original-url'
                && $request->hasHeader('X-Webhook-Secret', 'ORIGINAL_SECRET');
        });

        // Reset the send status to retry with a changed DB configuration
        $send->refresh();
        $send->update(['status' => 'scheduled', 'sent_at' => null]);

        // Change the URL, secret, and template name in the database
        $template->update([
            'template_name' => 'New_DB_Template_Name',
            'webhook_url' => 'https://webhook.example.com/new-url',
            'webhook_secret' => 'NEW_SECRET_123',
        ]);

        dispatch_sync(new SendDailyHabitWhatsappJob($send->id));

        // Verify the loop dynamically loads updated DB values (destination changes!)
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://webhook.example.com/new-url'
                && $request->hasHeader('X-Webhook-Secret', 'NEW_SECRET_123');
        });
    }

    /**
     * Test inactive template (Requirement 7).
     */
    public function test_inactive_template_not_sent(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        $template = WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'day_1_complete_profile',
            'webhook_url' => 'https://webhook.example.com/inactive',
            'webhook_secret' => 'SECRET_INACTIVE',
            'is_active' => false, // Inactive
        ]);

        $send = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'journey_started_at' => now(),
            'day_number' => 1,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        Http::fake();

        dispatch_sync(new SendDailyHabitWhatsappJob($send->id));

        Http::assertNothingSent();

        $this->assertDatabaseHas('daily_habit_sends', [
            'id' => $send->id,
            'status' => 'failed',
            'error_message' => 'Active template not found for day 1.',
        ]);
    }

    /**
     * Test missing template (Requirement 8).
     */
    public function test_missing_template_not_sent(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        // Template key is NOT created

        $send = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'journey_started_at' => now(),
            'day_number' => 1,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        Http::fake();

        dispatch_sync(new SendDailyHabitWhatsappJob($send->id));

        Http::assertNothingSent();

        $this->assertDatabaseHas('daily_habit_sends', [
            'id' => $send->id,
            'status' => 'failed',
            'error_message' => 'Active template not found for day 1.',
        ]);
    }

    /**
     * Test that daily_habit_sends contains delivery/scheduling state only (Requirement 9).
     */
    public function test_daily_habit_sends_state_only(): void
    {
        $columns = Schema::getColumnListing('daily_habit_sends');

        // Check columns to ensure we did not duplicate configuration fields
        $this->assertNotContains('template_name', $columns);
        $this->assertNotContains('webhook_url', $columns);
        $this->assertNotContains('webhook_secret', $columns);

        // Verify correct scheduling columns are present
        $this->assertContains('journey_started_at', $columns);
        $this->assertContains('day_number', $columns);
        $this->assertContains('scheduled_at', $columns);
        $this->assertContains('sent_at', $columns);
        $this->assertContains('status', $columns);
        $this->assertContains('error_message', $columns);
    }

    /**
     * Test no hardcoded FlexiMsg webhook config in loop code (Requirement 10).
     */
    public function test_no_hardcoded_configs_in_php_code(): void
    {
        $servicePath = app_path('Services/Notifications/DailyHabitLoopService.php');
        $jobPath = app_path('Jobs/SendDailyHabitWhatsappJob.php');
        $commandPath = app_path('Console/Commands/SendDailyHabitLoopCommand.php');

        $this->assertFileExists($servicePath);
        $this->assertFileExists($jobPath);
        $this->assertFileExists($commandPath);

        $serviceCode = file_get_contents($servicePath);
        $jobCode = file_get_contents($jobPath);
        $commandCode = file_get_contents($commandPath);

        // We must check that NO webhook endpoint prefix is hardcoded in loop logic
        $this->assertStringNotContainsString('fleximsg.com/api/webhooks', $serviceCode);
        $this->assertStringNotContainsString('fleximsg.com/api/webhooks', $jobCode);
        $this->assertStringNotContainsString('fleximsg.com/api/webhooks', $commandCode);

        // We must check that Day 1 template key name is NOT hardcoded in Job logic
        // (Job should resolve it dynamically via Service)
        $this->assertStringNotContainsString('day_1_complete_profile', $jobCode);

        // We must check that no template configuration secrets are hardcoded
        $this->assertStringNotContainsString('d50aedbe-166b-4a34-afbf-d5e85c4d1178', $serviceCode);
        $this->assertStringNotContainsString('d50aedbe-166b-4a34-afbf-d5e85c4d1178', $jobCode);
        $this->assertStringNotContainsString('d50aedbe-166b-4a34-afbf-d5e85c4d1178', $commandCode);
    }

    /**
     * Test duplicate job prevention.
     */
    public function test_duplicate_job_execution(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'day_1_complete_profile',
            'webhook_url' => 'https://webhook.example.com/duplicate',
            'webhook_secret' => 'SECRET_DUP',
        ]);

        $send = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'journey_started_at' => now(),
            'day_number' => 1,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        Http::fake([
            'https://webhook.example.com/duplicate' => Http::response(['success' => true], 200),
        ]);

        dispatch_sync(new SendDailyHabitWhatsappJob($send->id));
        dispatch_sync(new SendDailyHabitWhatsappJob($send->id));

        Http::assertSentCount(1);
    }

    /**
     * Test retry functionality after failure.
     */
    public function test_retry_after_failure(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '9876543210',
            'timezone' => 'UTC',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'day_1_complete_profile',
            'webhook_url' => 'https://webhook.example.com/retry-endpoint',
            'webhook_secret' => 'SECRET_RETRY',
        ]);

        $send = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'journey_started_at' => now(),
            'day_number' => 1,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        Http::fake([
            'https://webhook.example.com/retry-endpoint' => Http::sequence()
                ->push(['error' => 'Server Error'], 500)
                ->push(['success' => true], 200),
        ]);

        try {
            dispatch_sync(new SendDailyHabitWhatsappJob($send->id));
            $this->fail('Expected job to throw exception on failure');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Daily Habit Loop Send failed', $e->getMessage());
        }

        $send->refresh();
        $this->assertSame('failed', $send->status);
        $this->assertStringContainsString('FlexiMsg HTTP Non-2xx response', $send->error_message);

        dispatch_sync(new SendDailyHabitWhatsappJob($send->id));

        $this->assertDatabaseHas('daily_habit_sends', [
            'id' => $send->id,
            'status' => 'sent',
            'error_message' => null,
        ]);
    }

    /**
     * Test Day 2 template resolution.
     */
    public function test_day_2_template_resolution(): void
    {
        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'business_referrals_day_2',
            'template_name' => 'Business Referrals Day 2',
            'webhook_url' => 'https://webhook.example.com/day2',
            'webhook_secret' => 'SECRET_D2',
        ]);

        $service = new DailyHabitLoopService;
        $resolved = $service->resolveTemplateForDay(2);

        $this->assertNotNull($resolved);
        $this->assertSame('business_referrals_day_2', $resolved->template_key);
        $this->assertSame('Business Referrals Day 2', $resolved->template_name);
    }

    /**
     * Test Day 2 scheduling after Day 1 successfully sends.
     */
    public function test_day_2_scheduling_after_day_1_sent(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jane',
            'email' => 'jane@example.com',
            'phone' => '9876543211',
            'timezone' => 'Asia/Kolkata',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'Day 1 Complete Profile',
            'webhook_url' => 'https://webhook.example.com/day1',
            'webhook_secret' => 'SECRET_D1',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'business_referrals_day_2',
            'template_name' => 'Business Referrals Day 2',
            'webhook_url' => 'https://webhook.example.com/day2',
            'webhook_secret' => 'SECRET_D2',
        ]);

        $send = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'journey_started_at' => now(),
            'day_number' => 1,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        Http::fake([
            'https://webhook.example.com/day1' => Http::response(['success' => true], 200),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-25 05:00:00', 'UTC'));
        dispatch_sync(new SendDailyHabitWhatsappJob($send->id));

        // Assert Day 1 sent
        $this->assertDatabaseHas('daily_habit_sends', [
            'id' => $send->id,
            'status' => 'sent',
            'sent_at' => '2026-08-25 05:00:00',
        ]);

        // Assert Day 2 scheduled for exactly 24 hours later (2026-08-26 05:00:00 UTC)
        $this->assertDatabaseHas('daily_habit_sends', [
            'user_id' => $user->id,
            'day_number' => 2,
            'status' => 'scheduled',
            'scheduled_at' => '2026-08-26 05:00:00',
        ]);
    }

    /**
     * Test Day 2 sends successfully with correct phone number and TimelineLink payload.
     */
    public function test_day_2_sends_successfully_with_correct_payload(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jane',
            'email' => 'jane@example.com',
            'phone' => '9876543211',
            'timezone' => 'UTC',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'business_referrals_day_2',
            'template_name' => 'Business Referrals Day 2',
            'webhook_url' => 'https://webhook.example.com/day2',
            'webhook_secret' => 'SECRET_D2',
        ]);

        $send = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'journey_started_at' => now(),
            'day_number' => 2,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        Http::fake([
            'https://webhook.example.com/day2' => Http::response(['success' => true], 200),
        ]);

        dispatch_sync(new SendDailyHabitWhatsappJob($send->id));

        $this->assertDatabaseHas('daily_habit_sends', [
            'id' => $send->id,
            'status' => 'sent',
        ]);

        Http::assertSent(function (Request $request): bool {
            $expectedTimelineLink = rtrim((string) config('app.url'), '/').'/timeline';

            return $request->url() === 'https://webhook.example.com/day2'
                && $request->hasHeader('X-Webhook-Secret', 'SECRET_D2')
                && $request['phone'] === '919876543211'
                && $request['TimelineLink'] === $expectedTimelineLink;
        });
    }

    /**
     * Test duplicate job execution / scheduler runs again does NOT send Day 2 a second time.
     */
    public function test_day_2_does_not_send_twice(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jane',
            'email' => 'jane@example.com',
            'phone' => '9876543211',
            'timezone' => 'UTC',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'business_referrals_day_2',
            'template_name' => 'Business Referrals Day 2',
            'webhook_url' => 'https://webhook.example.com/day2',
            'webhook_secret' => 'SECRET_D2',
        ]);

        $send1 = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'journey_started_at' => now(),
            'day_number' => 2,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        Http::fake([
            'https://webhook.example.com/day2' => Http::response(['success' => true], 200),
        ]);

        // Send first time
        dispatch_sync(new SendDailyHabitWhatsappJob($send1->id));

        $this->assertSame('sent', $send1->refresh()->status);

        // Attempting to send again should be skipped by job
        dispatch_sync(new SendDailyHabitWhatsappJob($send1->id));

        // Creating a duplicate send record for day 2 should fail DB unique key or be protected
        $send2 = new DailyHabitSend([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'journey_started_at' => now(),
            'day_number' => 2,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        try {
            $send2->save();
            $this->fail('Expected QueryException due to unique key on user_id + day_number');
        } catch (QueryException $e) {
            $this->assertStringContainsString('UNIQUE constraint failed', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    /**
     * Test Day 1 behavior remains unchanged.
     */
    public function test_day_1_behavior_unchanged(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jane',
            'email' => 'jane@example.com',
            'phone' => '9876543211',
            'timezone' => 'UTC',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'Day 1 Complete Profile',
            'webhook_url' => 'https://webhook.example.com/day1',
            'webhook_secret' => 'SECRET_D1',
        ]);

        $send = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'journey_started_at' => now(),
            'day_number' => 1,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        Http::fake([
            'https://webhook.example.com/day1' => Http::response(['success' => true], 200),
        ]);

        dispatch_sync(new SendDailyHabitWhatsappJob($send->id));

        $this->assertDatabaseHas('daily_habit_sends', [
            'id' => $send->id,
            'status' => 'sent',
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://webhook.example.com/day1'
                && ! isset($request['TimelineLink']);
        });
    }

    /**
     * Test Day 2 retry/error-handling pattern.
     */
    public function test_day_2_failed_sends_retry(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jane',
            'email' => 'jane@example.com',
            'phone' => '9876543211',
            'timezone' => 'UTC',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'business_referrals_day_2',
            'template_name' => 'Business Referrals Day 2',
            'webhook_url' => 'https://webhook.example.com/day2',
            'webhook_secret' => 'SECRET_D2',
        ]);

        $send = DailyHabitSend::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'journey_started_at' => now(),
            'day_number' => 2,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        Http::fake([
            'https://webhook.example.com/day2' => Http::sequence()
                ->push(['error' => 'Bad gateway'], 502)
                ->push(['success' => true], 200),
        ]);

        try {
            dispatch_sync(new SendDailyHabitWhatsappJob($send->id));
            $this->fail('Expected exception on failure');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Daily Habit Loop Send failed', $e->getMessage());
        }

        $send->refresh();
        $this->assertSame('failed', $send->status);
        $this->assertStringContainsString('FlexiMsg HTTP Non-2xx response', $send->error_message);

        // Next dispatch succeeds
        dispatch_sync(new SendDailyHabitWhatsappJob($send->id));

        $send->refresh();
        $this->assertSame('sent', $send->status);
        $this->assertNull($send->error_message);
    }
}
