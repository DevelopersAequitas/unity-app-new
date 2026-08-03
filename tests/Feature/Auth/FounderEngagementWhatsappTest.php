<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Jobs\SendFounderEngagementJob;
use App\Jobs\SendWelcomeWhatsappJob;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class FounderEngagementWhatsappTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabaseSchema();
    }

    public function test_1_registration_dispatches_welcome_and_founder_engagement_jobs(): void
    {
        Queue::fake();

        $payload = [
            'first_name' => 'Jay',
            'last_name' => 'Shah',
            'email' => 'jay.shah@example.com',
            'phone' => '9876543210',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)->assertJson(['success' => true]);

        $user = User::where('email', 'jay.shah@example.com')->firstOrFail();

        Queue::assertPushed(SendWelcomeWhatsappJob::class, function (SendWelcomeWhatsappJob $job) use ($user): bool {
            return $job->userId === (string) $user->id;
        });

        Queue::assertPushed(SendFounderEngagementJob::class, function (SendFounderEngagementJob $job) use ($user): bool {
            return $job->userId === (string) $user->id
                && $job->delay !== null;
        });
    }

    public function test_2_founder_engagement_job_executes_successfully_and_calls_webhook(): void
    {
        Http::fake([
            'https://fleximsg.com/api/webhooks/*' => Http::response(['success' => true], 200),
        ]);

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'engagement_founder',
            'template_name' => 'peer_engagement',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/7c8374a9-00d3-451a-93e2-8c9e915c5d76',
            'webhook_secret' => 'PGU_eng_3hr_9fK2@Lm8#QvR7Xp1!Nc5Rw4ZdYt6HsA',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jay',
            'last_name' => 'Shah',
            'email' => 'jay.engagement@example.com',
            'phone' => '9876543210',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        SendFounderEngagementJob::dispatchSync((string) $user->id);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://fleximsg.com/api/webhooks/7c8374a9-00d3-451a-93e2-8c9e915c5d76'
                && $request->hasHeader('X-Webhook-Secret', 'PGU_eng_3hr_9fK2@Lm8#QvR7Xp1!Nc5Rw4ZdYt6HsA')
                && $request['mobile'] === '919876543210'
                && $request['first_name'] === 'Jay'
                && $request['media_url'] === 'https://peersunity.com/api/v1/files/019fc673-313b-7135-96dd-ca70a094f2ad';
        });

        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $user->id,
            'channel' => 'whatsapp',
            'provider' => 'engagement_founder',
            'status' => 'sent',
        ]);
    }

    public function test_3_founder_engagement_job_skips_when_template_is_inactive(): void
    {
        Http::fake();

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'engagement_founder',
            'template_name' => 'peer_engagement',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/inactive',
            'webhook_secret' => 'PGU_eng_3hr_9fK2@Lm8#QvR7Xp1!Nc5Rw4ZdYt6HsA',
            'is_active' => false,
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Inactive',
            'last_name' => 'Template',
            'email' => 'inactive.template@example.com',
            'phone' => '9876543211',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        SendFounderEngagementJob::dispatchSync((string) $user->id);

        Http::assertNothingSent();

        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $user->id,
            'channel' => 'whatsapp',
            'provider' => 'engagement_founder',
            'status' => 'failed',
        ]);
    }

    public function test_4_founder_engagement_job_handles_http_failure_gracefully(): void
    {
        Http::fake([
            'https://fleximsg.com/api/webhooks/*' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'engagement_founder',
            'template_name' => 'peer_engagement',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/error',
            'webhook_secret' => 'PGU_eng_3hr_9fK2@Lm8#QvR7Xp1!Nc5Rw4ZdYt6HsA',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Http',
            'last_name' => 'Error',
            'email' => 'http.error@example.com',
            'phone' => '9876543212',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        SendFounderEngagementJob::dispatchSync((string) $user->id);

        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $user->id,
            'channel' => 'whatsapp',
            'provider' => 'engagement_founder',
            'status' => 'failed',
        ]);
    }

    public function test_5_retried_job_does_not_send_duplicate_engagement_message(): void
    {
        Http::fake([
            'https://fleximsg.com/api/webhooks/*' => Http::response(['success' => true], 200),
        ]);

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'engagement_founder',
            'template_name' => 'peer_engagement',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/duplicate',
            'webhook_secret' => 'PGU_eng_3hr_9fK2@Lm8#QvR7Xp1!Nc5Rw4ZdYt6HsA',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Duplicate',
            'last_name' => 'Protection',
            'email' => 'duplicate.protection@example.com',
            'phone' => '9876543213',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        SendFounderEngagementJob::dispatchSync((string) $user->id);
        Http::assertSentCount(1);

        SendFounderEngagementJob::dispatchSync((string) $user->id);
        Http::assertSentCount(1);
    }

    public function test_6_founder_engagement_job_skips_restricted_or_blocked_users(): void
    {
        Http::fake();

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'engagement_founder',
            'template_name' => 'peer_engagement',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/blocked',
            'webhook_secret' => 'PGU_eng_3hr_9fK2@Lm8#QvR7Xp1!Nc5Rw4ZdYt6HsA',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Blocked',
            'last_name' => 'User',
            'email' => 'blocked.user@example.com',
            'phone' => '9876543214',
            'password_hash' => Hash::make('password123'),
            'status' => 'blocked',
        ]);

        SendFounderEngagementJob::dispatchSync((string) $user->id);

        Http::assertNothingSent();
    }

    private function setUpDatabaseSchema(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('joined_circle_categories');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('notification_delivery_logs');
        Schema::dropIfExists('otp_codes');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('secondary_mobile', 20)->nullable();
            $table->string('password_hash');
            $table->string('company_name', 150)->nullable();
            $table->string('designation', 100)->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('status', 50)->default('active');
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
            $table->string('webhook_secret');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('notification_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->uuid('campaign_id')->nullable();
            $table->string('channel')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('email', 255)->nullable();
            $table->string('purpose', 50)->default('login');
            $table->string('code', 255);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }
}
