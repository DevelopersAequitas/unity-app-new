<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\PhoneRegisterVerificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SocialAuthAndPaywallTest extends TestCase
{
    private string $testEmail = 'social_test_user@example.com';

    private string $testPhone = '+919876500112';

    protected function setUp(): void
    {
        parent::setUp();
        User::query()->where('email', $this->testEmail)->forceDelete();
        User::query()->where('phone', $this->testPhone)->forceDelete();
    }

    protected function tearDown(): void
    {
        User::query()->where('email', $this->testEmail)->forceDelete();
        User::query()->where('phone', $this->testPhone)->forceDelete();
        parent::tearDown();
    }

    public function test_google_login_returns_new_user_flag_when_not_registered(): void
    {
        $response = $this->postJson('/api/v1/auth/social/google', [
            'google_id' => '104928472910482019482',
            'email' => $this->testEmail,
            'name' => 'Sarah Connor',
            'photo_url' => 'https://example.com/avatar.jpg',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'is_new_user' => true,
                    'is_registered' => false,
                    'email' => $this->testEmail,
                    'google_id' => '104928472910482019482',
                ],
            ]);
    }

    public function test_complete_social_registration_with_valid_phone_token(): void
    {
        /** @var PhoneRegisterVerificationService $service */
        $service = app(PhoneRegisterVerificationService::class);
        $token = $service->generatePhoneVerificationToken($this->testPhone);

        $response = $this->postJson('/api/v1/auth/register/social-complete', [
            'provider' => 'google',
            'provider_user_id' => '104928472910482019482',
            'email' => $this->testEmail,
            'first_name' => 'Sarah',
            'last_name' => 'Connor',
            'phone' => $this->testPhone,
            'phone_verification_token' => $token,
            'dob' => '1995-04-12',
            'photo_url' => 'https://example.com/avatar.jpg',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'message' => 'Registration completed successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'email',
                        'phone',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => $this->testEmail,
            'phone' => $this->testPhone,
            'google_id' => '104928472910482019482',
        ]);
    }

    public function test_google_login_authenticates_existing_registered_user(): void
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Sarah',
            'last_name' => 'Connor',
            'display_name' => 'Sarah Connor',
            'email' => $this->testEmail,
            'phone' => $this->testPhone,
            'google_id' => '104928472910482019482',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/social/google', [
            'google_id' => '104928472910482019482',
            'email' => $this->testEmail,
            'name' => 'Sarah Connor',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'is_new_user' => false,
                    'is_registered' => true,
                    'user' => [
                        'email' => $this->testEmail,
                        'phone' => $this->testPhone,
                    ],
                ],
            ]);
    }

    public function test_first_registration_paywall_status_and_mark_presented(): void
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Paywall',
            'last_name' => 'Test',
            'display_name' => 'Paywall Test',
            'email' => $this->testEmail,
            'phone' => $this->testPhone,
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'has_seen_first_registration_paywall' => false,
        ]);

        // Check status -> should_show_paywall: true
        $resCheck = $this->actingAs($user)->getJson('/api/v1/user/first-registration-paywall-status');
        $resCheck->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'has_seen_first_registration_paywall' => false,
                    'should_show_paywall' => true,
                ],
            ]);

        // Mark presented
        $resMark = $this->actingAs($user)->postJson('/api/v1/user/first-registration-paywall-status', [
            'presented' => true,
        ]);
        $resMark->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'has_seen_first_registration_paywall' => true,
                    'should_show_paywall' => false,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => (string) $user->id,
            'has_seen_first_registration_paywall' => true,
        ]);
    }
}
