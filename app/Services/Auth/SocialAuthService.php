<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Creative\WelcomeCreativeService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class SocialAuthService
{
    /**
     * Authenticate or register a user via a social provider.
     *
     * @throws RuntimeException
     */
    public function handleSocialUser(string $provider, string $token, ?string $timezone = null): User
    {
        $normalizedProvider = strtolower(trim($provider));

        $profile = match ($normalizedProvider) {
            'google' => $this->verifyGoogle($token),
            'facebook' => $this->verifyFacebook($token),
            'linkedin' => $this->verifyLinkedIn($token),
            'apple' => $this->verifyApple($token),
            default => throw new RuntimeException("Unsupported social provider: {$provider}"),
        };

        return $this->findOrCreateSocialUser($normalizedProvider, $profile, $timezone);
    }

    /**
     * Find existing user or register new user from social profile and link social account.
     *
     * @param  array{id: string, email: string, first_name?: string, last_name?: string, name?: string, avatar?: ?string}  $profile
     */
    public function findOrCreateSocialUser(string $provider, array $profile, ?string $timezone = null): User
    {
        $normalizedProvider = strtolower(trim($provider));

        // 1. Check if social account is already linked or provider column exists on users
        /** @var SocialAccount|null $socialAccount */
        $socialAccount = SocialAccount::query()
            ->where('provider', $normalizedProvider)
            ->where('provider_id', (string) $profile['id'])
            ->first();

        if ($socialAccount) {
            $user = $socialAccount->user()->withTrashed()->first();

            if ($user) {
                if (method_exists($user, 'trashed') && $user->trashed()) {
                    $user->restore();
                    $user->status = 'active';
                    $user->save();
                }

                if ($timezone && Schema::hasColumn('users', 'timezone')) {
                    $user->timezone = $timezone;
                    $user->save();
                }

                return $user;
            }
        }

        $providerColumn = "{$normalizedProvider}_id";
        /** @var User|null $user */
        $user = null;

        if (Schema::hasColumn('users', $providerColumn)) {
            $user = User::withTrashed()
                ->where($providerColumn, (string) $profile['id'])
                ->first();
        }

        // 2. Find existing user by verified non-empty email (including soft-deleted accounts)
        $normalizedEmail = strtolower(trim((string) ($profile['email'] ?? '')));
        if (! $user && $normalizedEmail !== '') {
            $user = User::withTrashed()
                ->where('email', $normalizedEmail)
                ->orWhereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
                ->first();
        }

        if ($user && method_exists($user, 'trashed') && $user->trashed()) {
            $user->restore();
            $user->status = 'active';
            $user->save();
        }

        // 3. Create user if not found
        if (! $user) {
            $randomPassword = Str::random(32);
            $firstName = ! empty($profile['first_name']) ? (string) $profile['first_name'] : ((string) ($profile['name'] ?? 'User'));
            $lastName = ! empty($profile['last_name']) ? (string) $profile['last_name'] : null;
            $displayName = ! empty($profile['name']) ? (string) $profile['name'] : trim($firstName.' '.($lastName ?? ''));

            $persistedEmail = $normalizedEmail !== ''
                ? $normalizedEmail
                : "{$normalizedProvider}_{$profile['id']}@{$normalizedProvider}.local";

            $hashedRandomPassword = Hash::make($randomPassword);
            $userData = [
                'id' => (string) Str::uuid(),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'display_name' => $displayName ?: 'User',
                'email' => $persistedEmail,
                'status' => 'active',
            ];

            if (Schema::hasColumn('users', $providerColumn)) {
                $userData[$providerColumn] = (string) $profile['id'];
            }

            if (Schema::hasColumn('users', 'password')) {
                $userData['password'] = $hashedRandomPassword;
            }
            if (Schema::hasColumn('users', 'password_hash')) {
                $userData['password_hash'] = $hashedRandomPassword;
            }

            if (Schema::hasColumn('users', 'approval_status')) {
                $userData['approval_status'] = 'approved';
            }

            if (Schema::hasColumn('users', 'profile_photo_url') && ! empty($profile['avatar'])) {
                $userData['profile_photo_url'] = (string) $profile['avatar'];
            }

            if (Schema::hasColumn('users', 'timezone') && ! empty($timezone)) {
                $userData['timezone'] = $timezone;
            }

            $user = User::query()->create($userData);

            try {
                app(WelcomeCreativeService::class)->generateAndPostWelcomeCreative($user);
            } catch (\Throwable $e) {
                Log::warning('SocialAuthService: welcome creative skipped', [
                    'user_id' => (string) $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 4. Link social account
        SocialAccount::query()->firstOrCreate(
            [
                'provider' => $normalizedProvider,
                'provider_id' => (string) $profile['id'],
            ],
            [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'email' => $normalizedEmail !== '' ? $normalizedEmail : null,
                'token_metadata' => [
                    'name' => $profile['name'] ?? null,
                    'avatar' => $profile['avatar'] ?? null,
                ],
            ]
        );

        return $user;
    }

    /**
     * Verify Google ID token via Google TokenInfo API.
     *
     * @return array{id: string, email: string, first_name: string, last_name: string, name: string, avatar: ?string}
     */
    public function verifyGoogle(string $idToken): array
    {
        $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $idToken,
        ]);

        if ($response->failed()) {
            Log::warning('SocialAuth.verifyGoogle: tokeninfo call failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new RuntimeException('Invalid Google ID token.');
        }

        $data = $response->json();
        $sub = $data['sub'] ?? null;
        $email = $data['email'] ?? null;

        if (! $sub || ! $email) {
            throw new RuntimeException('Google token does not contain required user identity fields.');
        }

        return [
            'id' => (string) $sub,
            'email' => (string) $email,
            'first_name' => (string) ($data['given_name'] ?? ''),
            'last_name' => (string) ($data['family_name'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'avatar' => isset($data['picture']) ? (string) $data['picture'] : null,
        ];
    }

    /**
     * Verify Facebook credentials (classic OAuth token or iOS Limited Login OIDC JWT).
     *
     * @return array{
     *     id: string,
     *     email: ?string,
     *     first_name: string,
     *     last_name: string,
     *     name: string,
     *     avatar: ?string,
     *     dob: ?string,
     *     location: ?string,
     *     token_type: string
     * }
     */
    public function verifyFacebook(string $accessToken, string $tokenType = 'classic', ?string $nonce = null): array
    {
        return app(FacebookAuthService::class)->verifyToken($accessToken, $tokenType, $nonce);
    }

    /**
     * Verify LinkedIn credentials (code or access token) via OpenID Connect.
     *
     * @return array{id: string, email: string, first_name: string, last_name: string, name: string, avatar: ?string}
     */
    public function verifyLinkedIn(string $codeOrToken): array
    {
        return app(LinkedInAuthService::class)->verifyLinkedIn($codeOrToken);
    }

    /**
     * Verify Sign in with Apple OIDC identityToken JWT.
     *
     * @return array{
     *     id: string,
     *     email: ?string,
     *     email_verified: bool,
     *     is_private_email: bool,
     *     first_name: string,
     *     last_name: string,
     *     name: string,
     *     avatar: ?string
     * }
     */
    public function verifyApple(string $identityToken, ?string $nonce = null): array
    {
        return app(AppleAuthService::class)->verifyIdentityToken($identityToken, $nonce);
    }
}
