<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\SocialAccount;
use App\Models\User;
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
            default => throw new RuntimeException("Unsupported social provider: {$provider}"),
        };

        // 1. Check if social account is already linked
        /** @var SocialAccount|null $socialAccount */
        $socialAccount = SocialAccount::query()
            ->where('provider', $normalizedProvider)
            ->where('provider_id', (string) $profile['id'])
            ->first();

        if ($socialAccount && $socialAccount->user) {
            $user = $socialAccount->user;

            if ($timezone && Schema::hasColumn('users', 'timezone')) {
                $user->timezone = $timezone;
                $user->save();
            }

            return $user;
        }

        // 2. Find existing user by email
        $normalizedEmail = strtolower(trim((string) $profile['email']));
        /** @var User|null $user */
        $user = User::query()
            ->where('email', $normalizedEmail)
            ->orWhereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->first();

        // 3. Create user if not found
        if (! $user) {
            $randomPassword = Str::random(32);
            $firstName = ! empty($profile['first_name']) ? (string) $profile['first_name'] : ((string) ($profile['name'] ?? 'User'));
            $lastName = ! empty($profile['last_name']) ? (string) $profile['last_name'] : null;
            $displayName = ! empty($profile['name']) ? (string) $profile['name'] : trim($firstName.' '.($lastName ?? ''));

            $userData = [
                'id' => (string) Str::uuid(),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'display_name' => $displayName ?: 'User',
                'email' => $normalizedEmail,
                'password' => Hash::make($randomPassword),
                'status' => 'active',
            ];

            if (Schema::hasColumn('users', 'password_hash')) {
                $userData['password_hash'] = Hash::make($randomPassword);
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
                'email' => $profile['email'],
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
     * Verify Facebook access token via Facebook Graph API.
     *
     * @return array{id: string, email: string, first_name: string, last_name: string, name: string, avatar: ?string}
     */
    public function verifyFacebook(string $accessToken): array
    {
        $response = Http::timeout(10)->get('https://graph.facebook.com/v19.0/me', [
            'fields' => 'id,first_name,last_name,name,email,picture.type(large)',
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            Log::warning('SocialAuth.verifyFacebook: Graph API call failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new RuntimeException('Invalid Facebook access token.');
        }

        $data = $response->json();
        $id = $data['id'] ?? null;

        if (! $id) {
            throw new RuntimeException('Facebook profile did not return a valid user ID.');
        }

        $email = $data['email'] ?? "{$id}@facebook.com";

        return [
            'id' => (string) $id,
            'email' => (string) $email,
            'first_name' => (string) ($data['first_name'] ?? ''),
            'last_name' => (string) ($data['last_name'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'avatar' => isset($data['picture']['data']['url']) ? (string) $data['picture']['data']['url'] : null,
        ];
    }

    /**
     * Verify LinkedIn credentials (code or access token) via OpenID Connect.
     *
     * @return array{id: string, email: string, first_name: string, last_name: string, name: string, avatar: ?string}
     */
    public function verifyLinkedIn(string $codeOrToken): array
    {
        $accessToken = $codeOrToken;

        // If authorization code is provided, exchange for access token
        if (! str_starts_with($codeOrToken, 'AQ') && strlen($codeOrToken) < 120) {
            $clientId = (string) config('services.linkedin.client_id');
            $clientSecret = (string) config('services.linkedin.client_secret');
            $redirectUri = (string) config('services.linkedin.redirect_uri');

            if ($clientId !== '' && $clientSecret !== '') {
                $tokenResponse = Http::asForm()->timeout(10)->post('https://www.linkedin.com/oauth/v2/accessToken', [
                    'grant_type' => 'authorization_code',
                    'code' => $codeOrToken,
                    'redirect_uri' => $redirectUri,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);

                if ($tokenResponse->failed()) {
                    Log::warning('SocialAuth.verifyLinkedIn: code exchange failed', [
                        'status' => $tokenResponse->status(),
                        'body' => $tokenResponse->json(),
                    ]);
                    throw new RuntimeException('Failed to exchange LinkedIn authorization code.');
                }

                $accessToken = (string) ($tokenResponse->json('access_token') ?? '');
            }
        }

        $response = Http::withToken($accessToken)
            ->timeout(10)
            ->get('https://api.linkedin.com/v2/userinfo');

        if ($response->failed()) {
            Log::warning('SocialAuth.verifyLinkedIn: userinfo endpoint failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new RuntimeException('Invalid LinkedIn access token.');
        }

        $data = $response->json();
        $sub = $data['sub'] ?? null;

        if (! $sub) {
            throw new RuntimeException('LinkedIn profile did not return a valid user identity.');
        }

        $email = $data['email'] ?? "{$sub}@linkedin.com";

        return [
            'id' => (string) $sub,
            'email' => (string) $email,
            'first_name' => (string) ($data['given_name'] ?? ''),
            'last_name' => (string) ($data['family_name'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
            'avatar' => isset($data['picture']) ? (string) $data['picture'] : null,
        ];
    }
}
