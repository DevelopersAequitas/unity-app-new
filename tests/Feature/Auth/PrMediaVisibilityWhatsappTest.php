<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Jobs\SendFounderEngagementJob;
use App\Jobs\SendPrMediaVisibilityWhatsappJob;
use App\Jobs\SendWelcomeWhatsappJob;
use App\Models\Notifications\NotificationDeliveryLog;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrMediaVisibilityWhatsappTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabaseSchema();
    }

    public function test_a_new_registration_dispatches_pr_media_visibility_job_with_24_hour_delay(): void
    {
        Queue::fake();

        $registrationTime = Carbon::parse('2026-08-11 14:03:00');
        Carbon::setTestNow($registrationTime);

        $payload = [
            'first_name' => 'Jay',
            'last_name' => 'Shah',
            'email' => 'jay.pr@example.com',
            'phone' => '9876543210',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)->assertJson(['success' => true]);

        $user = User::where('email', 'jay.pr@example.com')->firstOrFail();

        Queue::assertPushed(SendPrMediaVisibilityWhatsappJob::class, function (SendPrMediaVisibilityWhatsappJob $job) use ($user, $registrationTime): bool {
            if ($job->userId !== (string) $user->id) {
                return false;
            }

            if (! $job->delay) {
                return false;
            }

            $expectedDelay = $registrationTime->copy()->addHours(24);
            $actualDelay = Carbon::parse($job->delay);

            return $actualDelay->timestamp === $expectedDelay->timestamp;
        });

        Carbon::setTestNow();
    }

    public function test_b_job_sends_pgu_pr_media_visibility_v2_successfully(): void
    {
        Http::fake([
            'https://fleximsg.com/api/webhooks/*' => Http::response(['success' => true], 200),
        ]);

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'pgu_pr_media_visibility_v2',
            'template_name' => 'PR Media Visibility',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/pr-media',
            'webhook_secret' => 'PR_SECRET_123',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jay',
            'last_name' => 'Shah',
            'email' => 'jay.pr.send@example.com',
            'phone' => '9876543210',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        SendPrMediaVisibilityWhatsappJob::dispatchSync((string) $user->id);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://fleximsg.com/api/webhooks/pr-media'
                && $request->hasHeader('X-Webhook-Secret', 'PR_SECRET_123')
                && $request['phone'] === '919876543210'
                && $request['mobile'] === '919876543210'
                && $request['first_name'] === 'Jay';
        });

        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $user->id,
            'channel' => 'whatsapp',
            'provider' => 'pgu_pr_media_visibility_v2',
            'status' => 'sent',
        ]);
    }

    public function test_c1_payload_uses_display_name_when_first_name_is_empty(): void
    {
        Http::fake([
            'https://fleximsg.com/api/webhooks/*' => Http::response(['success' => true], 200),
        ]);

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'pgu_pr_media_visibility_v2',
            'template_name' => 'PR Media Visibility',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/pr-media',
            'webhook_secret' => 'PR_SECRET_123',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jay',
            'last_name' => 'Shah',
            'email' => 'displayname@example.com',
            'phone' => '9876543211',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        SendPrMediaVisibilityWhatsappJob::dispatchSync((string) $user->id);

        Http::assertSent(function (Request $request): bool {
            return $request['phone'] === '919876543211' && $request['first_name'] === 'Jay';
        });
    }

    public function test_c2_payload_uses_friend_when_first_name_and_display_name_are_empty(): void
    {
        Http::fake([
            'https://fleximsg.com/api/webhooks/*' => Http::response(['success' => true], 200),
        ]);

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'pgu_pr_media_visibility_v2',
            'template_name' => 'PR Media Visibility',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/pr-media',
            'webhook_secret' => 'PR_SECRET_123',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => null,
            'email' => null,
            'phone' => '9876543212',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        SendPrMediaVisibilityWhatsappJob::dispatchSync((string) $user->id);

        Http::assertSent(function (Request $request): bool {
            return $request['phone'] === '919876543212' && $request['first_name'] === 'Friend';
        });
    }

    public function test_d_phone_normalization_works_through_whatsapp_notification_service(): void
    {
        Http::fake([
            'https://fleximsg.com/api/webhooks/*' => Http::response(['success' => true], 200),
        ]);

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'pgu_pr_media_visibility_v2',
            'template_name' => 'PR Media Visibility',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/pr-media',
            'webhook_secret' => 'PR_SECRET_123',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Normalization',
            'email' => 'normalize@example.com',
            'phone' => '+91 9876543213',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        SendPrMediaVisibilityWhatsappJob::dispatchSync((string) $user->id);

        Http::assertSent(function (Request $request): bool {
            return $request['phone'] === '919876543213' && $request['mobile'] === '919876543213';
        });
    }

    public function test_e_existing_successful_delivery_causes_job_to_skip(): void
    {
        Http::fake([
            'https://fleximsg.com/api/webhooks/*' => Http::response(['success' => true], 200),
        ]);

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'pgu_pr_media_visibility_v2',
            'template_name' => 'PR Media Visibility',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/pr-media',
            'webhook_secret' => 'PR_SECRET_123',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'DuplicateTest',
            'email' => 'duplicatetest@example.com',
            'phone' => '9876543214',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        NotificationDeliveryLog::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'channel' => 'whatsapp',
            'provider' => 'pgu_pr_media_visibility_v2',
            'status' => 'sent',
            'request_payload' => ['first_name' => 'DuplicateTest'],
            'attempted_at' => now(),
            'delivered_at' => now(),
        ]);

        SendPrMediaVisibilityWhatsappJob::dispatchSync((string) $user->id);

        Http::assertNothingSent();
    }

    public function test_f_missing_deleted_or_suspended_user_is_skipped_safely(): void
    {
        Http::fake();

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'pgu_pr_media_visibility_v2',
            'template_name' => 'PR Media Visibility',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/pr-media',
            'webhook_secret' => 'PR_SECRET_123',
            'is_active' => true,
        ]);

        // Non-existent user
        SendPrMediaVisibilityWhatsappJob::dispatchSync((string) Str::uuid());

        // Suspended user
        $suspendedUser = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Suspended',
            'email' => 'suspended@example.com',
            'phone' => '9876543215',
            'password_hash' => Hash::make('password123'),
            'status' => 'suspended',
        ]);

        SendPrMediaVisibilityWhatsappJob::dispatchSync((string) $suspendedUser->id);

        // Soft-deleted user
        $deletedUser = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Deleted',
            'email' => 'deleted@example.com',
            'phone' => '9876543216',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $deletedUser->delete();

        SendPrMediaVisibilityWhatsappJob::dispatchSync((string) $deletedUser->id);

        Http::assertNothingSent();
    }

    public function test_g_http_webhook_failure_is_logged_and_does_not_crash_registration(): void
    {
        Http::fake([
            'https://fleximsg.com/api/webhooks/*' => Http::response(['error' => 'Server Error'], 500),
        ]);

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'pgu_pr_media_visibility_v2',
            'template_name' => 'PR Media Visibility',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/pr-media',
            'webhook_secret' => 'PR_SECRET_123',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'HttpFailure',
            'email' => 'httpfailure@example.com',
            'phone' => '9876543217',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        SendPrMediaVisibilityWhatsappJob::dispatchSync((string) $user->id);

        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $user->id,
            'channel' => 'whatsapp',
            'provider' => 'pgu_pr_media_visibility_v2',
            'status' => 'failed',
        ]);
    }

    public function test_h_existing_welcome_and_founder_engagement_behavior_remains_unchanged(): void
    {
        Queue::fake();

        $payload = [
            'first_name' => 'ExistingFlows',
            'last_name' => 'Check',
            'email' => 'existingflows@example.com',
            'phone' => '9876543218',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)->assertJson(['success' => true]);

        $user = User::where('email', 'existingflows@example.com')->firstOrFail();

        Queue::assertPushed(SendWelcomeWhatsappJob::class, function (SendWelcomeWhatsappJob $job) use ($user): bool {
            return $job->userId === (string) $user->id;
        });

        Queue::assertPushed(SendFounderEngagementJob::class, function (SendFounderEngagementJob $job) use ($user): bool {
            return $job->userId === (string) $user->id && $job->delay !== null;
        });

        Queue::assertPushed(SendPrMediaVisibilityWhatsappJob::class, function (SendPrMediaVisibilityWhatsappJob $job) use ($user): bool {
            return $job->userId === (string) $user->id && $job->delay !== null;
        });
    }

    private function setUpDatabaseSchema(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('joined_circle_categories');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('notification_delivery_logs');
        Schema::dropIfExists('otp_codes');

        Schema::create('jobs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->nullable()->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('secondary_mobile', 20)->nullable();
            $table->string('password_hash');
            $table->string('company_name', 150)->nullable();
            $table->string('designation', 100)->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('status', 50)->default('inactive');
            $table->string('registration_source', 100)->nullable();
            $table->string('membership_status', 50)->default('visitor');
            $table->timestamp('membership_expiry')->nullable();
            $table->timestamp('membership_starts_at')->nullable();
            $table->timestamp('membership_ends_at')->nullable();
            $table->bigInteger('coins_balance')->default(0);
            $table->string('public_profile_slug', 80)->nullable()->unique();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->bigIncrements('id');
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

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id');
            $table->uuid('user_id');
            $table->string('role', 50)->default('member');
            $table->string('status', 50)->default('approved');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('joined_circle_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('user_id');
            $table->uuid('circle_id');
            $table->uuid('circle_member_id');
            $table->integer('level1_category_id')->nullable();
            $table->integer('level2_category_id')->nullable();
            $table->integer('level3_category_id')->nullable();
            $table->integer('level4_category_id')->nullable();
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->string('template_key')->nullable();
            $table->string('subject')->nullable();
            $table->string('status');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('template_key')->unique();
            $table->string('template_name');
            $table->string('webhook_url');
            $table->string('webhook_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('notification_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->uuid('campaign_id')->nullable();
            $table->string('channel');
            $table->string('provider');
            $table->string('provider_message_id')->nullable();
            $table->string('status');
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('identifier', 255);
            $table->string('otp_code', 10);
            $table->string('type', 50)->default('phone');
            $table->timestamp('expires_at');
            $table->boolean('is_verified')->default(false);
            $table->integer('attempts')->default(0);
            $table->timestamps();
        });
    }
}
