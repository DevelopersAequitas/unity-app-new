<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Mail\LoginOtpMail;
use App\Models\ContactChangeOtp;
use App\Models\User;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ProfileContactChangeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('contact_change_otps')) {
            $this->markTestSkipped('contact_change_otps table is not migrated yet.');
        }

        Mail::fake();
    }

    public function test_email_change_request_and_verify_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'phone' => '+919876543210',
        ]);

        Sanctum::actingAs($user);

        // Same email rejected
        $this->postJson('/api/v1/profile/change-email/request-otp', [
            'email' => 'old@example.com',
        ])->assertStatus(422);

        // Request OTP for new email
        $this->postJson('/api/v1/profile/change-email/request-otp', [
            'email' => 'new@example.com',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.expires_in_seconds', 600);

        Mail::assertSent(LoginOtpMail::class);

        // Replace OTP hash with known value for verification test
        $record = ContactChangeOtp::query()
            ->where('user_id', (string) $user->id)
            ->where('channel', 'email')
            ->where('target_value', 'new@example.com')
            ->firstOrFail();
        $record->otp_hash = Hash::make('123456');
        $record->save();

        // Wrong OTP fails
        $this->postJson('/api/v1/profile/change-email/verify-otp', [
            'email' => 'new@example.com',
            'otp' => '999999',
        ])->assertStatus(422);

        // Correct OTP updates email
        $this->postJson('/api/v1/profile/change-email/verify-otp', [
            'email' => 'new@example.com',
            'otp' => '123456',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertSame('new@example.com', $user->fresh()->email);
    }

    public function test_phone_change_request_and_verify_flow(): void
    {
        $mock = Mockery::mock(WhatsappNotificationService::class);
        $mock->shouldReceive('send')->once()->andReturn(true);
        $this->app->instance(WhatsappNotificationService::class, $mock);

        $user = User::factory()->create([
            'email' => 'phoneuser@example.com',
            'phone' => '+919876543210',
        ]);

        Sanctum::actingAs($user);
        RateLimiter::clear("contact-change-cooldown:whatsapp:{$user->id}:+919988776655");
        RateLimiter::clear("contact-change-hourly:whatsapp:{$user->id}:+919988776655");

        // Same phone rejected
        $this->postJson('/api/v1/profile/change-phone/request-otp', [
            'phone' => '+919876543210',
        ])->assertStatus(422);

        // Request OTP for new phone
        $this->postJson('/api/v1/profile/change-phone/request-otp', [
            'phone' => '+919988776655',
            'country_code' => '+91',
            'channel' => 'whatsapp',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.expires_in_seconds', 600);

        $record = ContactChangeOtp::query()
            ->where('user_id', (string) $user->id)
            ->where('channel', 'whatsapp')
            ->where('target_value', '+919988776655')
            ->firstOrFail();
        $record->otp_hash = Hash::make('654321');
        $record->save();

        // Correct OTP updates phone
        $this->postJson('/api/v1/profile/change-phone/verify-otp', [
            'phone' => '+919988776655',
            'otp' => '654321',
            'channel' => 'whatsapp',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.phone', '+919988776655');

        $this->assertSame('+919988776655', $user->fresh()->phone);
    }

    public function test_profile_update_ignores_direct_email_and_phone_changes(): void
    {
        $user = User::factory()->create([
            'email' => 'keep@example.com',
            'phone' => '+919876543210',
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', [
            'first_name' => 'Updated',
            'email' => 'hacked@example.com',
            'phone' => '+911111111111',
        ])->assertOk();

        $user->refresh();
        $this->assertSame('keep@example.com', $user->email);
        $this->assertSame('+919876543210', $user->phone);
    }
}
