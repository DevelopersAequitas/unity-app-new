<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppOtpApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('user_login_histories');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('circle_id')->nullable();
            $table->string('status')->default('approved');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('paid_ends_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->string('membership_status')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('email')->nullable();
            $table->string('purpose');
            $table->string('code');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
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

        Schema::create('user_login_histories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('user_id');
            $table->timestamp('logged_in_at')->nullable();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
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
        });

        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'otp_verification',
            'template_name' => 'OTP Verification',
            'webhook_url' => 'https://fleximsg.com/api/webhooks/test',
            'webhook_secret' => 'SECRET_123',
            'is_active' => true,
        ]);
    }

    public function test_request_whatsapp_otp_for_registered_user(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '9876543210',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/request-whatsapp-otp', [
            'mobile' => '9876543210',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'OTP sent successfully via WhatsApp.',
            ]);

        $this->assertDatabaseHas('otp_codes', [
            'user_id' => $user->id,
            'purpose' => 'whatsapp_otp',
        ]);
    }

    public function test_request_whatsapp_otp_for_unregistered_user(): void
    {
        $response = $this->postJson('/api/v1/auth/request-whatsapp-otp', [
            'mobile' => '9999999999',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'You are not a registered user.',
            ]);
    }

    public function test_verify_whatsapp_otp_success(): void
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '919876543210',
            'status' => 'active',
        ]);

        OtpCode::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'email' => 'jane@example.com',
            'purpose' => 'whatsapp_otp',
            'code' => Hash::make('4829'),
            'expires_at' => now()->addMinutes(5),
            'used_at' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/verify-whatsapp-otp', [
            'mobile' => '9876543210',
            'otp' => '4829',
            'device_name' => 'Android App',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'token',
                ],
            ]);

        $this->assertNotNull($response->json('data.token'));
    }

    public function test_verify_whatsapp_otp_invalid_code(): void
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'phone' => '9876543210',
            'status' => 'active',
        ]);

        OtpCode::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'purpose' => 'whatsapp_otp',
            'code' => Hash::make('4829'),
            'expires_at' => now()->addMinutes(5),
            'used_at' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/verify-whatsapp-otp', [
            'mobile' => '9876543210',
            'otp' => '9999',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid OTP.',
            ]);
    }
}
