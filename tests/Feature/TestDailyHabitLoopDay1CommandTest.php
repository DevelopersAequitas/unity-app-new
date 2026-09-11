<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class TestDailyHabitLoopDay1CommandTest extends TestCase
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
     * Test missing user by ID.
     */
    public function test_missing_user(): void
    {
        $this->artisan('habit-loop:test-day1', ['user_id' => (string) Str::uuid()])
            ->assertFailed()
            ->expectsOutputToContain('User not found with ID');
    }

    /**
     * Test user without phone.
     */
    public function test_user_without_phone(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => null,
            'timezone' => 'UTC',
        ]);

        $this->artisan('habit-loop:test-day1', ['user_id' => $user->id])
            ->assertFailed()
            ->expectsOutputToContain('does not have a phone or secondary mobile number configured');
    }

    /**
     * Test missing active template.
     */
    public function test_missing_active_template(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'timezone' => 'UTC',
        ]);

        $this->artisan('habit-loop:test-day1', ['user_id' => $user->id])
            ->assertFailed()
            ->expectsOutputToContain("Active 'day_1_complete_profile' WhatsApp template not found in database");
    }

    /**
     * Test auto-selection of eligible user when no argument is supplied.
     */
    public function test_auto_selects_eligible_user(): void
    {
        // 1. Ineligible user (no phone)
        User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Ineligible',
            'email' => 'ineligible@example.com',
            'phone' => null,
            'timezone' => 'UTC',
        ]);

        // 2. Eligible user (first one)
        $eligible1 = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Bob',
            'email' => 'bob@example.com',
            'phone' => '9111111111',
            'timezone' => 'UTC',
        ]);
        $eligible1->refresh();

        // 3. Another eligible user
        User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Charlie',
            'email' => 'charlie@example.com',
            'phone' => '9222222222',
            'timezone' => 'UTC',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'day_1_complete_profile',
            'webhook_url' => 'https://webhook.example.com/day1',
            'webhook_secret' => 'SECRET_BOB_123',
        ]);

        Http::fake([
            'https://webhook.example.com/day1' => Http::response(['success' => true], 200),
        ]);

        // Run command without argument
        $this->artisan('habit-loop:test-day1')
            ->assertSuccessful()
            ->expectsOutputToContain('Resetting/scheduling Day 1 tracking record for User: Bob')
            ->expectsOutputToContain("User ID: {$eligible1->id}")
            ->expectsOutputToContain('User Name: Bob')
            ->expectsOutputToContain('User Phone: 9111111111')
            ->expectsOutputToContain('Template Key: day_1_complete_profile')
            ->expectsOutputToContain('Template Name: day_1_complete_profile')
            ->expectsOutputToContain('Webhook URL: https://webhook.example.com/day1')
            ->expectsOutputToContain('Webhook Secret (Masked): SE**********23')
            ->expectsOutputToContain('Job Dispatched Status: sent');

        // Verify sent message uses the auto-selected user
        Http::assertSent(function ($request) use ($eligible1): bool {
            return $request->url() === 'https://webhook.example.com/day1'
                && str_contains($request->body(), $eligible1->phone);
        });
    }

    /**
     * Test command fails cleanly when no eligible user exists.
     */
    public function test_fails_cleanly_when_no_eligible_user_exists(): void
    {
        // Setup only ineligible user
        User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'NoPhone',
            'email' => 'nophone@example.com',
            'phone' => null,
            'timezone' => 'UTC',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'day_1_complete_profile',
            'webhook_url' => 'https://webhook.example.com/day1',
            'webhook_secret' => 'SECRET_123',
        ]);

        $this->artisan('habit-loop:test-day1')
            ->assertFailed()
            ->expectsOutputToContain('No eligible user found with a valid phone number and first name.');
    }

    /**
     * Test successful day 1 local test dispatch with exact output verification.
     */
    public function test_successful_day1_dispatch(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alice',
            'email' => 'alice@example.com',
            'phone' => '9999999999',
            'timezone' => 'Asia/Kolkata',
        ]);
        $user->refresh();

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'Welcome Template',
            'webhook_url' => 'https://webhook.example.com/day1',
            'webhook_secret' => 'SECRET_XYZ_456',
        ]);

        Http::fake([
            'https://webhook.example.com/day1' => Http::response(['success' => true], 200),
        ]);

        $this->artisan('habit-loop:test-day1', ['user_id' => $user->id])
            ->assertSuccessful()
            ->expectsOutputToContain('Resetting/scheduling Day 1 tracking record for User: Alice')
            ->expectsOutputToContain("User ID: {$user->id}")
            ->expectsOutputToContain('User Phone: 9999999999')
            ->expectsOutputToContain('Template Key: day_1_complete_profile')
            ->expectsOutputToContain('Webhook URL: https://webhook.example.com/day1')
            ->expectsOutputToContain('Webhook Secret (Masked): SE**********56')
            ->expectsOutputToContain('Job Dispatched Status: sent');

        // Check database state
        $this->assertDatabaseHas('daily_habit_sends', [
            'user_id' => $user->id,
            'day_number' => 1,
            'status' => 'sent',
            'error_message' => null,
        ]);
    }

    /**
     * Test that the command captures and prints detailed HTTP failure response correctly (Requirement 11).
     */
    public function test_captures_http_failure_response_correctly(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alice',
            'email' => 'alice@example.com',
            'phone' => '9999999999',
            'timezone' => 'Asia/Kolkata',
        ]);
        $user->refresh();

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'template_key' => 'day_1_complete_profile',
            'template_name' => 'Welcome Template',
            'webhook_url' => 'https://webhook.example.com/day1',
            'webhook_secret' => 'SECRET_XYZ_456',
        ]);

        // Fake a 400 Bad Request response with some json error payload
        Http::fake([
            'https://webhook.example.com/day1' => Http::response([
                'error' => 'Invalid template placeholder mapping',
                'code' => 'placeholder_mismatch',
            ], 400, ['X-Custom-Header' => 'CustomValue']),
        ]);

        $exitCode = Artisan::call('habit-loop:test-day1', ['user_id' => $user->id]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Job Dispatched Status: failed: Daily Habit Loop Send failed: FlexiMsg HTTP Non-2xx response:', $output);
        $this->assertStringContainsString('"status_code":400', $output);
        $this->assertStringContainsString('Invalid template placeholder mapping', $output);
        $this->assertStringContainsString('https://webhook.example.com/day1', $output);
        $this->assertStringContainsString('CustomValue', $output);
    }
}
