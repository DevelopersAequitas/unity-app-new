<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Auth\PhoneRegisterVerificationService;
use App\Services\Referrals\ReferralService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class FacebookAuthTest extends TestCase
{
    private const CLIENT_ID = '1149324997500029';

    private const CLIENT_SECRET = 'test-facebook-app-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('sqlite');

        config()->set('services.facebook.client_id', self::CLIENT_ID);
        config()->set('services.facebook.client_secret', self::CLIENT_SECRET);
        config()->set('services.facebook.client_token', '3a04a6596793f68e51158cd6082acaa4');
        config()->set('services.facebook.graph_version', 'v19.0');

        Cache::forget('facebook.oidc.jwks');

        $this->ensureTestSchema();
    }

    public function test_classic_facebook_login_creates_new_user_with_dob_and_location(): void
    {
        Http::fake([
            'https://graph.facebook.com/debug_token*' => Http::response([
                'data' => [
                    'app_id' => self::CLIENT_ID,
                    'is_valid' => true,
                    'expires_at' => now()->addDay()->timestamp,
                    'user_id' => '10229384756123456',
                ],
            ], 200),
            'https://graph.facebook.com/v19.0/me*' => Http::response([
                'id' => '10229384756123456',
                'name' => 'Aarav Patel',
                'first_name' => 'Aarav',
                'last_name' => 'Patel',
                'email' => 'aarav@example.com',
                'picture' => [
                    'data' => [
                        'url' => 'https://platform-lookaside.fbsbx.com/avatar.jpg',
                    ],
                ],
                'birthday' => '08/19/1994',
                'location' => [
                    'name' => 'Bengaluru, India',
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/facebook/login', [
            'access_token' => 'classic-valid-fb-token',
            'token_type' => 'classic',
            'device_token' => 'fcm-device-123',
            'platform' => 'mobile',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Authenticated successfully')
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.has_phone', false)
            ->assertJsonPath('data.user.email', 'aarav@example.com')
            ->assertJsonPath('data.user.phone', null)
            ->assertJsonPath('data.user.display_name', 'Aarav Patel')
            ->assertJsonPath('data.user.dob', '1994-08-19')
            ->assertJsonPath('data.user.location', 'Bengaluru, India');

        $this->assertNotEmpty($response->json('data.token'));

        $this->assertDatabaseHas('users', [
            'email' => 'aarav@example.com',
            'facebook_id' => '10229384756123456',
            'city_of_residence' => 'Bengaluru, India',
        ]);

        $createdUser = User::query()->where('email', 'aarav@example.com')->firstOrFail();
        $this->assertSame('1994-08-19', $createdUser->dob?->format('Y-m-d'));

        $this->assertDatabaseHas('social_accounts', [
            'provider' => 'facebook',
            'provider_id' => '10229384756123456',
            'email' => 'aarav@example.com',
        ]);
    }

    public function test_classic_facebook_login_ignores_partial_birthdays(): void
    {
        foreach (['08/19', '1994'] as $index => $partialBirthday) {
            $fbId = 'fb-partial-'.$index;

            Http::fake([
                'https://graph.facebook.com/debug_token*' => Http::response([
                    'data' => [
                        'app_id' => self::CLIENT_ID,
                        'is_valid' => true,
                        'expires_at' => now()->addHour()->timestamp,
                        'user_id' => $fbId,
                    ],
                ], 200),
                'https://graph.facebook.com/v19.0/me*' => Http::response([
                    'id' => $fbId,
                    'name' => 'Partial Birthday User',
                    'email' => "partial{$index}@example.com",
                    'birthday' => $partialBirthday,
                ], 200),
            ]);

            $response = $this->postJson('/api/v1/auth/facebook/login', [
                'access_token' => 'token-'.$index,
                'dob' => $partialBirthday,
            ]);

            $response->assertOk()
                ->assertJsonPath('data.user.dob', null);
        }
    }

    public function test_classic_facebook_login_rejects_invalid_or_wrong_app_tokens_without_fallback(): void
    {
        Http::fake([
            'https://graph.facebook.com/debug_token*' => Http::response([
                'data' => [
                    'app_id' => '999999999999',
                    'is_valid' => true,
                    'expires_at' => now()->addHour()->timestamp,
                    'user_id' => 'fb-spoof-1',
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/facebook/login', [
            'access_token' => 'wrong-app-token',
            'facebook_id' => 'fb-spoof-1',
            'email' => 'victim@example.com',
            'name' => 'Spoof Attempt',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid or expired Facebook token.');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_limited_login_oidc_jwt_authenticates_and_refreshes_jwks_on_unknown_kid(): void
    {
        $oldKey = $this->generateRsaKeyPair('kid-old');
        $newKey = $this->generateRsaKeyPair('kid-rotated');

        $jwt = $this->signJwt(
            [
                'alg' => 'RS256',
                'typ' => 'JWT',
                'kid' => 'kid-rotated',
            ],
            [
                'iss' => 'https://www.facebook.com',
                'aud' => self::CLIENT_ID,
                'exp' => now()->addHour()->timestamp,
                'iat' => now()->timestamp,
                'nonce' => 'nonce-ios-xyz',
                'sub' => 'fb-limited-101',
                'email' => 'ioslimited@example.com',
                'name' => 'Meera Nair',
                'given_name' => 'Meera',
                'family_name' => 'Nair',
                'picture' => 'https://platform-lookaside.fbsbx.com/meera.jpg',
            ],
            $newKey['privateKey']
        );

        Http::fake([
            'https://www.facebook.com/.well-known/oauth/openid/jwks/' => Http::sequence()
                ->push(['keys' => [$oldKey['jwk']]], 200)
                ->push(['keys' => [$oldKey['jwk'], $newKey['jwk']]], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/facebook/login', [
            'access_token' => $jwt,
            'token_type' => 'limited',
            'nonce' => 'nonce-ios-xyz',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'ioslimited@example.com')
            ->assertJsonPath('data.user.display_name', 'Meera Nair')
            ->assertJsonPath('data.user.phone', null);

        $this->assertDatabaseHas('users', [
            'facebook_id' => 'fb-limited-101',
            'email' => 'ioslimited@example.com',
        ]);
    }

    public function test_limited_login_rejects_nonce_mismatch_and_wrong_issuer(): void
    {
        $key = $this->generateRsaKeyPair('kid-test');

        Http::fake([
            'https://www.facebook.com/.well-known/oauth/openid/jwks/' => Http::response([
                'keys' => [$key['jwk']],
            ], 200),
        ]);

        $badNonceJwt = $this->signJwt(
            ['alg' => 'RS256', 'kid' => 'kid-test'],
            [
                'iss' => 'https://www.facebook.com',
                'aud' => self::CLIENT_ID,
                'exp' => now()->addHour()->timestamp,
                'nonce' => 'nonce-actual',
                'sub' => 'fb-limited-bad-nonce',
            ],
            $key['privateKey']
        );

        $this->postJson('/api/v1/auth/facebook/login', [
            'access_token' => $badNonceJwt,
            'token_type' => 'limited',
            'nonce' => 'nonce-different',
        ])->assertStatus(401)->assertJsonPath('success', false);

        $badIssuerJwt = $this->signJwt(
            ['alg' => 'RS256', 'kid' => 'kid-test'],
            [
                'iss' => 'https://evil.example.com',
                'aud' => self::CLIENT_ID,
                'exp' => now()->addHour()->timestamp,
                'sub' => 'fb-limited-bad-iss',
            ],
            $key['privateKey']
        );

        $this->postJson('/api/v1/auth/facebook/login', [
            'access_token' => $badIssuerJwt,
            'token_type' => 'limited',
        ])->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_existing_user_lookup_links_facebook_id_without_overwriting_populated_fields(): void
    {
        $customFileId = (string) Str::uuid();

        $existingUser = User::query()->create([
            'id' => (string) Str::uuid(),
            'email' => 'existing-fb@example.com',
            'phone' => '+919876543210',
            'first_name' => 'Custom',
            'last_name' => 'Peer',
            'display_name' => 'Custom Peer',
            'profile_photo_file_id' => $customFileId,
            'profile_photo_url' => 'https://cdn.peersunity.com/uploads/custom.jpg',
            'business_address' => '221B Baker Street, Pune',
            'status' => 'active',
        ]);

        Http::fake([
            'https://graph.facebook.com/debug_token*' => Http::response([
                'data' => [
                    'app_id' => self::CLIENT_ID,
                    'is_valid' => true,
                    'expires_at' => now()->addHour()->timestamp,
                    'user_id' => 'fb-linked-555',
                ],
            ], 200),
            'https://graph.facebook.com/v19.0/me*' => Http::response([
                'id' => 'fb-linked-555',
                'name' => 'Facebook Name Override',
                'first_name' => 'Facebook',
                'last_name' => 'Override',
                'email' => 'existing-fb@example.com',
                'picture' => [
                    'data' => [
                        'url' => 'https://platform-lookaside.fbsbx.com/new-fb.jpg',
                    ],
                ],
                'birthday' => '03/15/1991',
                'location' => [
                    'name' => 'Mumbai, India',
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/facebook/login', [
            'access_token' => 'classic-link-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_new_user', false)
            ->assertJsonPath('data.has_phone', true)
            ->assertJsonPath('data.user.display_name', 'Custom Peer')
            ->assertJsonPath('data.user.phone', '+919876543210')
            ->assertJsonPath('data.user.dob', '1991-03-15')
            ->assertJsonPath('data.user.location', 'Mumbai, India')
            ->assertJsonPath('data.user.address', '221B Baker Street, Pune');

        $existingUser->refresh();
        $this->assertSame('fb-linked-555', $existingUser->facebook_id);
        $this->assertSame('Custom Peer', $existingUser->display_name);
        $this->assertSame('https://cdn.peersunity.com/uploads/custom.jpg', $existingUser->getAttributes()['profile_photo_url']);
        $this->assertSame('221B Baker Street, Pune', $existingUser->business_address);
        $this->assertSame('1991-03-15', $existingUser->dob?->format('Y-m-d'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_email_less_facebook_user_does_not_collide_and_succeeds(): void
    {
        User::query()->create([
            'id' => (string) Str::uuid(),
            'email' => 'someone-else@example.com',
            'first_name' => 'Someone',
            'last_name' => 'Else',
            'display_name' => 'Someone Else',
            'status' => 'active',
        ]);

        Http::fake([
            'https://graph.facebook.com/debug_token*' => Http::response([
                'data' => [
                    'app_id' => self::CLIENT_ID,
                    'is_valid' => true,
                    'expires_at' => now()->addHour()->timestamp,
                    'user_id' => 'fb-no-email-777',
                ],
            ], 200),
            'https://graph.facebook.com/v19.0/me*' => Http::response([
                'id' => 'fb-no-email-777',
                'name' => 'Phone Only User',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/facebook/login', [
            'access_token' => 'no-email-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.user.email', '')
            ->assertJsonPath('data.user.phone', null)
            ->assertJsonPath('data.user.first_name', 'Phone')
            ->assertJsonPath('data.user.last_name', 'Only User');

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('users', [
            'facebook_id' => 'fb-no-email-777',
        ]);
    }

    public function test_social_registration_profile_backfill_accepts_onboarding_fields_and_verifies_phone_token(): void
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'email' => 'fb-onboarding@example.com',
            'facebook_id' => 'fb-onboard-1',
            'first_name' => 'Riya',
            'last_name' => 'Sharma',
            'display_name' => 'Riya Sharma',
            'phone' => null,
            'status' => 'active',
        ]);

        $referrer = User::query()->create([
            'id' => (string) Str::uuid(),
            'email' => 'referrer@example.com',
            'first_name' => 'Ref',
            'last_name' => 'User',
            'display_name' => 'Ref User',
            'status' => 'active',
        ]);

        $this->mock(ReferralService::class, function ($mock) use ($referrer): void {
            $mock->shouldReceive('validateReferralCodeOrFail')
                ->once()
                ->with('UNITY100')
                ->andReturn([
                    'referrer_user_id' => (string) $referrer->id,
                    'referral_code' => 'UNITY100',
                ]);

            $mock->shouldReceive('applyReferralOnRegistration')
                ->once()
                ->andReturn([
                    'referrer_user_id' => (string) $referrer->id,
                    'referral_code' => 'UNITY100',
                    'reward_status' => 'granted',
                ]);
        });

        /** @var PhoneRegisterVerificationService $phoneService */
        $phoneService = app(PhoneRegisterVerificationService::class);
        $validToken = $phoneService->generatePhoneVerificationToken('+919876540001');

        // Reject invalid phone token first
        $this->actingAs($user)->postJson('/api/v1/user/profile', [
            'phone' => '+919876540001',
            'phone_verification_token' => 'bad-token',
        ])->assertStatus(422);

        $response = $this->actingAs($user)->postJson('/api/v1/user/profile', [
            'mobile' => '+919876540001',
            'phone_verification_token' => $validToken,
            'first_name' => '',
            'dob' => '1993-05-20',
            'location' => 'Delhi, India',
            'address' => 'Connaught Place, Delhi',
            'company_name' => 'Acme Ventures',
            'website' => 'acmeventures.in',
            'main_business_category_id' => '2',
            'business_category_id' => 'other',
            'other_category_name' => 'AI Robotics',
            'referral_code' => 'UNITY100',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();
        $this->assertSame('+919876540001', $user->phone);
        $this->assertTrue((bool) $user->is_phone_verified);
        $this->assertSame('1993-05-20', $user->dob?->format('Y-m-d'));
        $this->assertSame('Delhi, India', $user->city_of_residence);
        $this->assertSame('Connaught Place, Delhi', $user->business_address);
        $this->assertSame('Acme Ventures', $user->company_name);
        $this->assertSame('https://acmeventures.in', $user->business_website);
        $this->assertSame('AI Robotics', $user->business_sub_category);
        $this->assertSame((string) $referrer->id, (string) $user->referred_by_user_id);
    }

    public function test_meta_data_deletion_callback_verifies_signature_and_unlinks_facebook_account(): void
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'email' => 'delete-me@example.com',
            'facebook_id' => 'fb-delete-900',
            'social_avatar_url' => 'https://platform-lookaside.fbsbx.com/old.jpg',
            'status' => 'active',
        ]);

        SocialAccount::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => (string) $user->id,
            'provider' => 'facebook',
            'provider_id' => 'fb-delete-900',
            'email' => 'delete-me@example.com',
        ]);

        $encodedPayload = $this->base64UrlEncode((string) json_encode([
            'algorithm' => 'HMAC-SHA256',
            'expires' => now()->addHour()->timestamp,
            'issued_at' => now()->timestamp,
            'user_id' => 'fb-delete-900',
        ]));
        $sig = hash_hmac('sha256', $encodedPayload, self::CLIENT_SECRET, true);
        $signedRequest = $this->base64UrlEncode($sig).'.'.$encodedPayload;

        // Invalid signature rejected
        $this->postJson('/api/v1/auth/facebook/data-deletion', [
            'signed_request' => 'invalidsig.'.$encodedPayload,
        ])->assertStatus(400);

        $response = $this->postJson('/api/v1/auth/facebook/data-deletion', [
            'signed_request' => $signedRequest,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['url', 'confirmation_code']);

        $user->refresh();
        $this->assertNull($user->facebook_id);
        $this->assertNull($user->social_avatar_url);
        $this->assertDatabaseMissing('social_accounts', [
            'provider' => 'facebook',
            'provider_id' => 'fb-delete-900',
        ]);
    }

    /**
     * @return array{privateKey: \OpenSSLAsymmetricKey, jwk: array<string, mixed>}
     */
    private function generateRsaKeyPair(string $kid): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = @openssl_pkey_new($config);
        if ($resource === false) {
            $fallbackConf = $this->resolveOpenSslConfigPath();
            if ($fallbackConf !== null) {
                $config['config'] = $fallbackConf;
                $resource = @openssl_pkey_new($config);
            }
        }

        if ($resource === false) {
            throw new RuntimeException('Unable to generate RSA keypair in test environment.');
        }

        $details = openssl_pkey_get_details($resource);
        if (! is_array($details) || ! isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new RuntimeException('Unable to inspect RSA public key details.');
        }

        return [
            'privateKey' => $resource,
            'jwk' => [
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => $kid,
                'n' => $this->base64UrlEncode($details['rsa']['n']),
                'e' => $this->base64UrlEncode($details['rsa']['e']),
            ],
        ];
    }

    private function resolveOpenSslConfigPath(): ?string
    {
        $candidates = [
            dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf',
            'C:\\tmp\\php-portable\\php\\extras\\ssl\\openssl.cnf',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $payload
     */
    private function signJwt(array $header, array $payload, \OpenSSLAsymmetricKey $privateKey): string
    {
        $segments = [
            $this->base64UrlEncode((string) json_encode($header)),
            $this->base64UrlEncode((string) json_encode($payload)),
        ];
        $signingInput = implode('.', $segments);

        openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function ensureTestSchema(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->timestamp('phone_verified_at')->nullable();
                $table->boolean('is_phone_verified')->default(false);
                $table->string('facebook_id')->nullable()->index();
                $table->string('google_id')->nullable();
                $table->string('oauth_provider')->nullable();
                $table->string('social_avatar_url')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('display_name')->nullable();
                $table->string('password_hash')->nullable();
                $table->string('password')->nullable();
                $table->uuid('profile_photo_file_id')->nullable();
                $table->string('profile_photo_url')->nullable();
                $table->date('dob')->nullable();
                $table->string('city')->nullable();
                $table->string('city_of_residence')->nullable();
                $table->text('business_address')->nullable();
                $table->string('company_name')->nullable();
                $table->string('business_website')->nullable();
                $table->uuid('city_id')->nullable();
                $table->uuid('active_circle_id')->nullable();
                $table->string('main_business_category_id')->nullable();
                $table->string('business_category_id')->nullable();
                $table->string('business_sub_category')->nullable();
                $table->uuid('introduced_by')->nullable();
                $table->uuid('referred_by_user_id')->nullable();
                $table->uuid('cover_photo_file_id')->nullable();
                $table->string('referral_code')->nullable();
                $table->integer('members_introduced_count')->default(0);
                $table->integer('life_impacted_count')->default(0);
                $table->integer('coins_balance')->default(0);
                $table->string('public_profile_slug')->nullable();
                $table->string('timezone')->nullable();
                $table->string('status')->default('active');
                $table->string('approval_status')->nullable();
                $table->string('membership_status')->nullable();
                $table->boolean('is_member')->default(false);
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

        if (! Schema::hasTable('life_impact_histories')) {
            Schema::create('life_impact_histories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('user_id');
                $table->integer('impact_value')->default(0);
                $table->integer('life_impacted')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('custom_category_requests')) {
            Schema::create('custom_category_requests', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->integer('level1_category_id')->nullable();
                $table->string('category_name');
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('referral_links')) {
            Schema::create('referral_links', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('referral_code')->unique();
                $table->string('referral_link')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('referraldata')) {
            Schema::create('referraldata', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('referrer_user_id');
                $table->uuid('referred_user_id');
                $table->string('referral_code')->nullable();
                $table->string('referrer_email')->nullable();
                $table->integer('coins')->default(0);
                $table->string('reward_status')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('coins_ledger')) {
            Schema::create('coins_ledger', function (Blueprint $table): void {
                $table->uuid('transaction_id')->primary();
                $table->uuid('user_id');
                $table->integer('amount')->default(0);
                $table->integer('balance_after')->default(0);
                $table->string('reference')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('circle_categories')) {
            Schema::create('circle_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->integer('level')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('level4_categories')) {
            Schema::create('level4_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('circles')) {
            Schema::create('circles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->uuid('city_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('circle_members')) {
            Schema::create('circle_members', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('circle_id');
                $table->uuid('user_id');
                $table->string('status')->default('approved');
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('paid_ends_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_links')) {
            Schema::create('user_links', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('label')->nullable();
                $table->string('url')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('files')) {
            Schema::create('files', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('disk')->nullable();
                $table->string('path')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_memberships')) {
            Schema::create('user_memberships', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('status')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
            });
        }
    }
}
