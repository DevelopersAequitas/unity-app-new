<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\ContactChangeOtp;
use App\Models\User;
use App\Services\Auth\PhoneRegisterVerificationService;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class PhoneRegisterVerificationTest extends TestCase
{
    private string $testPhone = '+919988776655';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('phone-register-cooldown:'.md5($this->testPhone));
        RateLimiter::clear('phone-register-hourly:'.md5($this->testPhone));
        User::query()->where('phone', $this->testPhone)->forceDelete();
        ContactChangeOtp::query()->where('target_value', $this->testPhone)->delete();

        // Fix Postgres sequences in test database if out-of-sync
        try {
            $maxTokenId = (int) (DB::table('personal_access_tokens')->max('id') ?? 0);
            DB::statement("SELECT setval('personal_access_tokens_id_seq', {$maxTokenId} + 1, false)");
            $maxJobId = (int) (DB::table('jobs')->max('id') ?? 0);
            DB::statement("SELECT setval('jobs_id_seq', {$maxJobId} + 1, false)");
        } catch (\Throwable) {
            // Ignore if sequence doesn't exist
        }
    }

    protected function tearDown(): void
    {
        User::query()->where('phone', $this->testPhone)->forceDelete();
        ContactChangeOtp::query()->where('target_value', $this->testPhone)->delete();
        parent::tearDown();
    }

    public function test_can_request_otp_for_unregistered_phone(): void
    {
        $mockWhatsapp = Mockery::mock(WhatsappNotificationService::class);
        $mockWhatsapp->shouldReceive('send')->once()->andReturn(true);
        $this->app->instance(WhatsappNotificationService::class, $mockWhatsapp);

        $response = $this->postJson('/api/v1/auth/register/phone/request-otp', [
            'phone' => $this->testPhone,
            'channel' => 'whatsapp',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'OTP sent successfully via WhatsApp.',
            ]);

        $this->assertDatabaseHas('contact_change_otps', [
            'channel' => 'whatsapp_register',
            'target_value' => $this->testPhone,
            'consumed_at' => null,
        ]);
    }

    public function test_request_otp_returns_409_if_phone_already_registered(): void
    {
        User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => 'unique_existing_'.Str::random(8).'@example.com',
            'phone' => $this->testPhone,
            'password_hash' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/register/phone/request-otp', [
            'phone' => $this->testPhone,
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'This phone number is already registered with an account.',
            ]);
    }

    public function test_can_verify_otp_and_receive_verification_token(): void
    {
        ContactChangeOtp::query()->create([
            'user_id' => null,
            'channel' => 'whatsapp_register',
            'target_value' => $this->testPhone,
            'otp_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/register/phone/verify-otp', [
            'phone' => $this->testPhone,
            'otp' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Phone number verified successfully.',
            ])
            ->assertJsonStructure([
                'phone_verification_token',
                'verification_token',
            ]);

        $token = $response->json('phone_verification_token');
        $this->assertNotEmpty($token);

        /** @var PhoneRegisterVerificationService $service */
        $service = app(PhoneRegisterVerificationService::class);
        $this->assertTrue($service->validatePhoneVerificationToken($token, $this->testPhone));
        $this->assertFalse($service->validatePhoneVerificationToken($token, '+919999999999'));
    }

    public function test_register_fails_without_phone_verification_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'New',
            'last_name' => 'Peer',
            'email' => 'unique_newpeer_'.Str::random(8).'@example.com',
            'phone' => $this->testPhone,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'success' => false,
            ]);
    }

    public function test_register_succeeds_with_valid_token_and_sets_phone_verified_at(): void
    {
        Queue::fake();

        /** @var PhoneRegisterVerificationService $service */
        $service = app(PhoneRegisterVerificationService::class);
        $token = $service->generatePhoneVerificationToken($this->testPhone);

        $testEmail = 'unique_verified_'.Str::random(8).'@example.com';

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Verified',
            'last_name' => 'Peer',
            'email' => $testEmail,
            'phone' => $this->testPhone,
            'phone_verification_token' => $token,
            'phone_verified' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $user = User::query()->where('email', $testEmail)->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertNull($user->email_verified_at);

        // Clean up created user
        $user->forceDelete();
    }
}
