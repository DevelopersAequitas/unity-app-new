<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('email')->unique();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('display_name')->nullable();
                $table->string('password_hash')->nullable();
                $table->string('password')->nullable();
                $table->string('profile_photo_url')->nullable();
                $table->string('timezone')->nullable();
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

                $table->unique(['provider', 'provider_id']);
            });
        }

        if (! Schema::hasTable('user_push_tokens')) {
            Schema::create('user_push_tokens', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('token');
                $table->string('platform')->nullable();
                $table->string('device_id')->nullable();
                $table->string('app_version')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_login_histories')) {
            Schema::create('user_login_histories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('user_id');
                $table->timestamp('logged_in_at')->nullable();
                $table->string('ip')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_google_social_login_creates_new_user_and_social_account(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-user-123456',
                'email' => 'newuser@example.com',
                'given_name' => 'Alice',
                'family_name' => 'Smith',
                'name' => 'Alice Smith',
                'picture' => 'https://example.com/avatar.jpg',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/social-login', [
            'provider' => 'google',
            'token' => 'valid-google-id-token',
            'device_token' => 'fcm-device-token-123',
            'platform' => 'android',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'display_name' => 'Alice Smith',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'google',
            'provider_id' => 'google-user-123456',
            'email' => 'newuser@example.com',
        ]);
    }

    public function test_google_social_login_links_to_existing_user_by_email(): void
    {
        $existingUser = User::query()->create([
            'id' => (string) Str::uuid(),
            'email' => 'existing@example.com',
            'first_name' => 'Existing',
            'last_name' => 'Peer',
            'display_name' => 'Existing Peer',
            'status' => 'active',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-user-987654',
                'email' => 'existing@example.com',
                'given_name' => 'Existing',
                'family_name' => 'Peer',
                'name' => 'Existing Peer',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/social-login', [
            'provider' => 'google',
            'token' => 'valid-google-token-existing',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $existingUser->id,
            'provider' => 'google',
            'provider_id' => 'google-user-987654',
        ]);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_facebook_social_login_success(): void
    {
        Http::fake([
            'https://graph.facebook.com/v19.0/me*' => Http::response([
                'id' => 'fb-user-555',
                'email' => 'fbuser@example.com',
                'first_name' => 'Bob',
                'last_name' => 'Jones',
                'name' => 'Bob Jones',
                'picture' => [
                    'data' => [
                        'url' => 'https://facebook.com/bob.jpg',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/auth/social-login', [
            'provider' => 'facebook',
            'token' => 'mock-fb-access-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'email' => 'fbuser@example.com',
            'first_name' => 'Bob',
            'last_name' => 'Jones',
        ]);

        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'facebook',
            'provider_id' => 'fb-user-555',
        ]);
    }

    public function test_linkedin_social_login_with_auth_code_exchange(): void
    {
        config()->set('services.linkedin.client_id', 'test-client-id');
        config()->set('services.linkedin.client_secret', 'test-client-secret');
        config()->set('services.linkedin.redirect_uri', 'yourapp://linkedin-callback');

        Http::fake([
            'https://www.linkedin.com/oauth/v2/accessToken' => Http::response([
                'access_token' => 'swapped-linkedin-token-777',
            ], 200),
            'https://api.linkedin.com/v2/userinfo' => Http::response([
                'sub' => 'li-sub-888',
                'email' => 'linkedinuser@example.com',
                'given_name' => 'Carol',
                'family_name' => 'Danvers',
                'name' => 'Carol Danvers',
                'picture' => 'https://example.com/carol.jpg',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/social-login', [
            'provider' => 'linkedin',
            'token' => 'auth_code_from_flutter',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'email' => 'linkedinuser@example.com',
            'first_name' => 'Carol',
            'last_name' => 'Danvers',
        ]);

        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'linkedin',
            'provider_id' => 'li-sub-888',
        ]);
    }

    public function test_social_login_rejects_inactive_user(): void
    {
        $inactiveUser = User::query()->create([
            'id' => (string) Str::uuid(),
            'email' => 'inactive@example.com',
            'first_name' => 'Pending',
            'last_name' => 'User',
            'display_name' => 'Pending User',
            'status' => 'inactive',
        ]);

        SocialAccount::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $inactiveUser->id,
            'provider' => 'google',
            'provider_id' => 'google-user-inactive',
            'email' => 'inactive@example.com',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-user-inactive',
                'email' => 'inactive@example.com',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/social-login', [
            'provider' => 'google',
            'token' => 'valid-token',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_social_login_handles_provider_verification_failure(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'error_description' => 'Invalid Value',
            ], 400),
        ]);

        $response = $this->postJson('/api/v1/auth/social-login', [
            'provider' => 'google',
            'token' => 'bad-token',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_social_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/social-login', [
            'provider' => 'unsupported_provider',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider', 'token']);
    }
}
