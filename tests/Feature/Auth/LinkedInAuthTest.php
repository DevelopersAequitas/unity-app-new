<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LinkedInAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.linkedin.client_id', 'test-client-id');
        config()->set('services.linkedin.client_secret', 'test-client-secret');
        config()->set('services.linkedin.redirect_uri', 'https://dev.peersunity.com/api/v1/auth/linkedin/callback');

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('display_name')->nullable();
                $table->string('password_hash')->nullable();
                $table->string('password')->nullable();
                $table->string('company_name')->nullable();
                $table->string('designation')->nullable();
                $table->string('city')->nullable();
                $table->string('status')->default('active');
                $table->string('approval_status')->nullable();
                $table->string('membership_status')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('social_accounts')) {
            Schema::create('social_accounts', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('provider');
                $table->string('provider_id');
                $table->string('email')->nullable();
                $table->json('token_metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_linkedin_profile_and_subsequent_registration_with_same_auth_code(): void
    {
        Cache::flush();

        $callCount = 0;
        Http::fake([
            'https://www.linkedin.com/oauth/v2/accessToken' => function () use (&$callCount) {
                $callCount++;
                if ($callCount > 1) {
                    // LinkedIn only allows code exchange ONCE!
                    return Http::response(['error' => 'invalid_grant', 'error_description' => 'Code already used'], 400);
                }

                return Http::response(['access_token' => 'mocked-access-token-123'], 200);
            },
            'https://api.linkedin.com/v2/userinfo' => Http::response([
                'sub' => 'linkedin-user-sub-123',
                'email' => 'jane.doe@example.com',
                'given_name' => 'Jane',
                'family_name' => 'Doe',
                'name' => 'Jane Doe',
                'picture' => 'https://example.com/avatar.jpg',
            ], 200),
        ]);

        $authCode = 'single-use-oauth-code-xyz';

        // 1. First inspection: POST /profile
        $profileResponse = $this->postJson('/api/v1/auth/linkedin/profile', [
            'code' => $authCode,
            'redirect_uri' => 'https://dev.peersunity.com/api/v1/auth/linkedin/callback',
        ]);

        $profileResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_registered', false)
            ->assertJsonPath('data.email', 'jane.doe@example.com')
            ->assertJsonPath('data.first_name', 'Jane')
            ->assertJsonPath('data.token', 'mocked-access-token-123');

        // 2. Second submission: POST /register with the SAME code (simulating Flutter form submission)
        $registerResponse = $this->postJson('/api/v1/auth/linkedin/register', [
            'code' => $authCode,
            'redirect_uri' => 'https://dev.peersunity.com/api/v1/auth/linkedin/callback',
            'phone' => '+919876543210',
            'company_name' => 'Acme Inc',
            'designation' => 'Founder',
        ]);

        $registerResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jane.doe@example.com',
            'phone' => '+919876543210',
            'company_name' => 'Acme Inc',
        ]);

        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'linkedin',
            'provider_id' => 'linkedin-user-sub-123',
        ]);

        // Assert that accessToken endpoint was called only once because second call was cached
        $this->assertSame(1, $callCount);
    }
}
