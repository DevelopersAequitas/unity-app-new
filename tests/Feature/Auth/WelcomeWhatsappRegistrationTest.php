<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Jobs\SendWelcomeWhatsappJob;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WelcomeWhatsappRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabaseSchema();

        WhatsappTemplate::query()->create([
            'id' => '7e2bfb69-ae58-4c30-95b0-b682aba34357',
            'template_key' => 'welcome',
            'template_name' => 'Welcome to the Tribe',
            'webhook_url' => 'https://webhook.example.com/whatsapp/welcome',
            'webhook_secret' => 'TEST_SECRET_123',
            'is_active' => true,
        ]);
    }

    public function test_1_register_new_user_triggers_welcome_whatsapp(): void
    {
        Http::fake([
            'https://webhook.example.com/whatsapp/welcome' => Http::response(['success' => true], 200),
        ]);

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

        $this->assertDatabaseHas('users', [
            'email' => 'jay.shah@example.com',
            'first_name' => 'Jay',
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://webhook.example.com/whatsapp/welcome'
                && $request->hasHeader('X-Webhook-Secret', 'TEST_SECRET_123')
                && $request['phone'] === '919876543210'
                && $request['first_name'] === 'Jay';
        });

        $user = User::where('email', 'jay.shah@example.com')->firstOrFail();
        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $user->id,
            'channel' => 'whatsapp',
            'provider' => 'welcome',
            'status' => 'sent',
        ]);
    }

    public function test_2_webhook_unavailable_allows_registration_to_succeed_and_logs_error(): void
    {
        Http::fake([
            'https://webhook.example.com/whatsapp/welcome' => Http::response(['error' => 'Service Unavailable'], 500),
        ]);

        $payload = [
            'first_name' => 'Failed',
            'last_name' => 'Webhook',
            'email' => 'webhook.fail@example.com',
            'phone' => '+919876543211',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'email' => 'webhook.fail@example.com',
        ]);

        $user = User::where('email', 'webhook.fail@example.com')->firstOrFail();
        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $user->id,
            'channel' => 'whatsapp',
            'provider' => 'welcome',
            'status' => 'failed',
        ]);
    }

    public function test_3_register_another_user_triggers_welcome(): void
    {
        Http::fake([
            'https://webhook.example.com/whatsapp/welcome' => Http::response(['success' => true], 200),
        ]);

        $payload = [
            'first_name' => 'Second',
            'last_name' => 'User',
            'email' => 'second.user@example.com',
            'phone' => '919876543212',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)->assertJson(['success' => true]);

        Http::assertSent(function (Request $request): bool {
            return $request['phone'] === '919876543212' && $request['first_name'] === 'Second';
        });
    }

    public function test_4_login_existing_user_does_not_send_welcome(): void
    {
        Http::fake();

        User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => 'existing@example.com',
            'phone' => '9876543213',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'existing@example.com',
            'password' => 'password123',
        ]);

        Http::assertNothingSent();
    }

    public function test_5_verify_otp_does_not_send_welcome(): void
    {
        Http::fake([
            'https://fleximsg.com/api/webhooks/*' => Http::response(['success' => true], 200),
        ]);

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'otp_verification',
            'template_name' => 'OTP Verification',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/otp',
            'webhook_secret' => 'SECRET_KEY_123',
            'is_active' => true,
        ]);

        User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Otp',
            'last_name' => 'User',
            'email' => 'otp@example.com',
            'phone' => '9876543214',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        // Request OTP for whatsapp auth
        $this->postJson('/api/v1/auth/request-whatsapp-otp', [
            'mobile' => '9876543214',
        ]);

        // Assert that the webhook sent was for OTP, NOT welcome template
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://fleximsg.com/api/webhooks/otp'
                && isset($request['code']);
        });

        Http::assertNotSent(function (Request $request): bool {
            return $request->url() === 'https://webhook.example.com/whatsapp/welcome';
        });
    }

    public function test_6_update_profile_does_not_send_welcome(): void
    {
        Http::fake();

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Profile',
            'last_name' => 'User',
            'email' => 'profile@example.com',
            'phone' => '9876543215',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/profile', [
                'first_name' => 'ProfileUpdated',
            ]);

        Http::assertNothingSent();
    }

    public function test_7_retried_welcome_job_sends_only_once(): void
    {
        Http::fake([
            'https://webhook.example.com/whatsapp/welcome' => Http::response(['success' => true], 200),
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Retry',
            'last_name' => 'User',
            'email' => 'retry@example.com',
            'phone' => '9876543216',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        // Dispatch job first time
        SendWelcomeWhatsappJob::dispatchSync($user->id);

        Http::assertSentCount(1);

        // Dispatch job second time
        SendWelcomeWhatsappJob::dispatchSync($user->id);

        // Webhook count remains 1 due to duplicate protection
        Http::assertSentCount(1);
    }

    public function test_8_admin_panel_member_creation_triggers_welcome_whatsapp(): void
    {
        Http::fake([
            'https://webhook.example.com/whatsapp/welcome' => Http::response(['success' => true], 200),
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'AdminCreated',
            'last_name' => 'User',
            'email' => 'admin.created@example.com',
            'phone' => '9876543217',
            'registration_source' => 'Admin Panel',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        SendWelcomeWhatsappJob::dispatchSync($user->id);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://webhook.example.com/whatsapp/welcome'
                && $request['phone'] === '919876543217'
                && $request['first_name'] === 'AdminCreated';
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
