<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Http\Resources\UserResource;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserLoginHistory;
use App\Models\UserPushToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class LinkedInAuthService
{
    public function __construct(
        protected SocialAuthService $socialAuthService
    ) {}

    /**
     * Generate the LinkedIn OAuth 2.0 authorization URL.
     */
    public function getAuthorizationUrl(?string $redirectUri = null, ?string $state = null): string
    {
        $clientId = (string) config('services.linkedin.client_id');
        if (blank($clientId)) {
            throw new RuntimeException('LinkedIn Client ID is not configured. Please set LINKEDIN_CLIENT_ID in your .env file.');
        }

        $resolvedRedirectUri = $redirectUri ?: (string) config('services.linkedin.redirect_uri');
        if (blank($resolvedRedirectUri)) {
            throw new RuntimeException('LinkedIn Redirect URI is not configured. Please provide a redirect_uri or set LINKEDIN_REDIRECT_URI in your .env file.');
        }

        $resolvedState = $state ?: Str::random(40);

        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $resolvedRedirectUri,
            'state' => $resolvedState,
            'scope' => 'openid profile email',
        ];

        return 'https://www.linkedin.com/oauth/v2/authorization?'.http_build_query($params);
    }

    /**
     * Exchange LinkedIn authorization code for an OAuth access token.
     *
     * @throws RuntimeException
     */
    public function exchangeCodeForToken(string $code, ?string $redirectUri = null): string
    {
        $clientId = (string) config('services.linkedin.client_id');
        $clientSecret = (string) config('services.linkedin.client_secret');
        $resolvedRedirectUri = $redirectUri ?: (string) config('services.linkedin.redirect_uri');

        if (blank($clientId) || blank($clientSecret)) {
            throw new RuntimeException('LinkedIn credentials are not configured. Please set LINKEDIN_CLIENT_ID and LINKEDIN_CLIENT_SECRET in .env.');
        }

        $response = Http::asForm()->timeout(15)->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $resolvedRedirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if ($response->failed()) {
            $error = $response->json('error_description') ?? $response->json('error') ?? 'Failed to exchange LinkedIn authorization code.';
            Log::warning('LinkedInAuthService.exchangeCodeForToken: request failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new RuntimeException((string) $error);
        }

        $accessToken = $response->json('access_token');
        if (blank($accessToken)) {
            throw new RuntimeException('LinkedIn did not return a valid access token in token response.');
        }

        return (string) $accessToken;
    }

    /**
     * Fetch user profile information using LinkedIn OpenID Connect userinfo endpoint.
     *
     * @return array{id: string, email: string, first_name: string, last_name: string, name: string, avatar: ?string}
     *
     * @throws RuntimeException
     */
    public function fetchUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->timeout(15)
            ->get('https://api.linkedin.com/v2/userinfo');

        if ($response->failed()) {
            Log::warning('LinkedInAuthService.fetchUserInfo: userinfo endpoint failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new RuntimeException('Invalid LinkedIn access token or failed to fetch profile information.');
        }

        $data = $response->json();
        $sub = $data['sub'] ?? null;

        if (blank($sub)) {
            throw new RuntimeException('LinkedIn profile response did not contain a valid user identity (sub).');
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

    /**
     * Verify LinkedIn code or access token and retrieve user profile.
     *
     * @return array{id: string, email: string, first_name: string, last_name: string, name: string, avatar: ?string}
     *
     * @throws RuntimeException
     */
    public function verifyLinkedIn(string $codeOrToken, ?string $redirectUri = null): array
    {
        $codeOrToken = trim($codeOrToken);
        if (blank($codeOrToken)) {
            throw new RuntimeException('LinkedIn authorization code or access token is required.');
        }

        $profileCacheKey = 'linkedin_profile_'.sha1($codeOrToken);
        $cachedProfile = Cache::get($profileCacheKey);
        if (is_array($cachedProfile) && ! empty($cachedProfile['id'])) {
            return $cachedProfile;
        }

        $accessToken = $codeOrToken;
        $tokenCacheKey = 'linkedin_code_token_'.sha1($codeOrToken);
        $cachedToken = Cache::get($tokenCacheKey);

        if (is_string($cachedToken) && filled($cachedToken)) {
            $accessToken = $cachedToken;
        } else {
            // If an authorization code was provided, exchange it for an access token
            try {
                $accessToken = $this->exchangeCodeForToken($codeOrToken, $redirectUri);
                Cache::put($tokenCacheKey, $accessToken, now()->addHours(2));
            } catch (\Throwable) {
                // If exchanging failed, codeOrToken may already be a direct access token
                $accessToken = $codeOrToken;
            }
        }

        // Also check if profile was cached under the access token
        $accessTokenProfileCacheKey = 'linkedin_profile_'.sha1($accessToken);
        $cachedAccessTokenProfile = Cache::get($accessTokenProfileCacheKey);
        if (is_array($cachedAccessTokenProfile) && ! empty($cachedAccessTokenProfile['id'])) {
            Cache::put($profileCacheKey, $cachedAccessTokenProfile, now()->addHours(2));

            return $cachedAccessTokenProfile;
        }

        $profile = $this->fetchUserInfo($accessToken);

        // Cache the profile under both codeOrToken and accessToken
        Cache::put($profileCacheKey, $profile, now()->addHours(2));
        Cache::put($accessTokenProfileCacheKey, $profile, now()->addHours(2));

        return $profile;
    }

    /**
     * Inspect LinkedIn profile and check existing registration status.
     *
     * @return array{success: bool, message: string, data: array<string, mixed>|null, status_code: int}
     */
    public function getProfile(string $codeOrToken, ?string $redirectUri = null): array
    {
        try {
            $profile = $this->verifyLinkedIn($codeOrToken, $redirectUri);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'status_code' => 400,
            ];
        }

        $existingSocial = SocialAccount::query()
            ->where('provider', 'linkedin')
            ->where('provider_id', (string) $profile['id'])
            ->first();

        $normalizedEmail = strtolower(trim((string) $profile['email']));
        $existingUser = $existingSocial?->user ?: User::query()
            ->where('email', $normalizedEmail)
            ->orWhereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->first();

        $tokenCacheKey = 'linkedin_code_token_'.sha1($codeOrToken);
        $resolvedToken = (string) Cache::get($tokenCacheKey, $codeOrToken);

        return [
            'success' => true,
            'message' => 'LinkedIn profile retrieved successfully.',
            'data' => [
                'is_registered' => $existingUser !== null,
                'token' => $resolvedToken,
                'access_token' => $resolvedToken,
                'linkedin_id' => (string) $profile['id'],
                'email' => (string) $profile['email'],
                'first_name' => (string) $profile['first_name'],
                'last_name' => (string) $profile['last_name'],
                'name' => (string) $profile['name'],
                'avatar' => $profile['avatar'] ?? null,
                'company_name' => $existingUser?->company_name ?? null,
                'designation' => $existingUser?->designation ?? null,
                'headline' => null,
                'city' => $existingUser?->city ?? null,
                'phone' => $existingUser?->phone ?? null,
                'dob' => $existingUser?->dob ?? null,
                'existing_user' => $existingUser ? [
                    'id' => (string) $existingUser->id,
                    'phone' => (string) $existingUser->phone,
                    'email' => (string) $existingUser->email,
                    'display_name' => (string) $existingUser->display_name,
                    'company_name' => (string) ($existingUser->company_name ?? ''),
                    'designation' => (string) ($existingUser->designation ?? ''),
                ] : null,
            ],
            'status_code' => 200,
        ];
    }

    /**
     * Register a new user with LinkedIn profile details and required phone number.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, message: string, data: array<string, mixed>|null, status_code: int}
     */
    public function registerWithLinkedIn(array $data): array
    {
        $codeOrToken = (string) ($data['token'] ?? $data['code'] ?? '');
        $redirectUri = isset($data['redirect_uri']) ? (string) $data['redirect_uri'] : null;

        try {
            $profile = $this->verifyLinkedIn($codeOrToken, $redirectUri);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'status_code' => 400,
            ];
        }

        $normalizedEmail = strtolower(trim((string) $profile['email']));

        // Check if user with email already exists
        $existingUser = User::query()
            ->where('email', $normalizedEmail)
            ->orWhereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->first();

        if ($existingUser) {
            // Link LinkedIn account to existing user if not already linked
            SocialAccount::query()->firstOrCreate(
                [
                    'provider' => 'linkedin',
                    'provider_id' => (string) $profile['id'],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'user_id' => $existingUser->id,
                    'email' => $profile['email'],
                    'token_metadata' => [
                        'name' => $profile['name'] ?? null,
                        'avatar' => $profile['avatar'] ?? null,
                    ],
                ]
            );

            return $this->authenticateAndLogin($codeOrToken, $redirectUri, isset($data['timezone']) ? (string) $data['timezone'] : null, $data);
        }

        $phone = trim((string) ($data['phone'] ?? $data['mobile_number'] ?? ''));
        if (filled($phone)) {
            $phoneUser = User::query()->where('phone', $phone)->first();
            if ($phoneUser) {
                // Link LinkedIn account to existing user with this phone number
                SocialAccount::query()->firstOrCreate(
                    [
                        'provider' => 'linkedin',
                        'provider_id' => (string) $profile['id'],
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'user_id' => $phoneUser->id,
                        'email' => $profile['email'],
                        'token_metadata' => [
                            'name' => $profile['name'] ?? null,
                            'avatar' => $profile['avatar'] ?? null,
                        ],
                    ]
                );

                return $this->authenticateAndLogin($codeOrToken, $redirectUri, isset($data['timezone']) ? (string) $data['timezone'] : null, $data);
            }
        }

        $password = ! empty($data['password']) ? (string) $data['password'] : Str::random(32);
        $firstName = ! empty($data['first_name']) ? (string) $data['first_name'] : ($profile['first_name'] ?: ($profile['name'] ?? 'User'));
        $lastName = ! empty($data['last_name']) ? (string) $data['last_name'] : ($profile['last_name'] ?? null);
        $displayName = trim($firstName.' '.($lastName ?? ''));

        $userData = [
            'id' => (string) Str::uuid(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $displayName ?: 'User',
            'email' => $normalizedEmail,
            'phone' => filled($phone) ? $phone : null,
            'password' => Hash::make($password),
            'status' => 'active',
        ];

        if (Schema::hasColumn('users', 'password_hash')) {
            $userData['password_hash'] = Hash::make($password);
        }
        if (Schema::hasColumn('users', 'approval_status')) {
            $userData['approval_status'] = 'approved';
        }
        if (Schema::hasColumn('users', 'profile_photo_url') && ! empty($profile['avatar'])) {
            $userData['profile_photo_url'] = (string) $profile['avatar'];
        }
        if (Schema::hasColumn('users', 'company_name') && ! empty($data['company_name'])) {
            $userData['company_name'] = (string) $data['company_name'];
        }
        if (Schema::hasColumn('users', 'designation') && ! empty($data['designation'])) {
            $userData['designation'] = (string) $data['designation'];
        }
        if (Schema::hasColumn('users', 'city') && ! empty($data['city'])) {
            $userData['city'] = (string) $data['city'];
        }
        if (Schema::hasColumn('users', 'city_id') && ! empty($data['city_id'])) {
            $userData['city_id'] = (string) $data['city_id'];
        }
        if (Schema::hasColumn('users', 'country_code') && ! empty($data['country_code'])) {
            $userData['country_code'] = (string) $data['country_code'];
        }
        if (Schema::hasColumn('users', 'dob') && ! empty($data['dob'])) {
            $userData['dob'] = (string) $data['dob'];
        }
        if (Schema::hasColumn('users', 'timezone') && ! empty($data['timezone'])) {
            $userData['timezone'] = (string) $data['timezone'];
        }
        if (Schema::hasColumn('users', 'main_business_category_id') && ! empty($data['main_business_category_id'])) {
            $userData['main_business_category_id'] = $data['main_business_category_id'];
        }
        if (Schema::hasColumn('users', 'business_category_id') && ! empty($data['business_category_id'])) {
            $userData['business_category_id'] = $data['business_category_id'];
        }

        $user = User::query()->create($userData);

        // Link SocialAccount
        SocialAccount::query()->firstOrCreate(
            [
                'provider' => 'linkedin',
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

        $token = $user->createToken('auth_token')->plainTextToken;

        $pushToken = $data['device_token']
            ?? $data['fcm_token']
            ?? $data['push_token']
            ?? null;

        if (filled($pushToken)) {
            UserPushToken::registerTokenForUser($user, [
                'token' => (string) $pushToken,
                'platform' => isset($data['platform']) ? (string) $data['platform'] : (isset($data['device_type']) ? (string) $data['device_type'] : null),
                'device_id' => isset($data['device_id']) ? (string) $data['device_id'] : null,
                'app_version' => isset($data['app_version']) ? (string) $data['app_version'] : null,
            ]);
        }

        if (filled($data['referral_code'] ?? null)) {
            try {
                app(\App\Services\Referrals\ReferralService::class)->applyReferralOnRegistration($user, (string) $data['referral_code']);
            } catch (\Throwable $e) {
                Log::warning('LinkedInAuthService.registerWithLinkedIn: referral failed', [
                    'user_id' => (string) $user->id,
                    'referral_code' => $data['referral_code'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (Schema::hasColumn('users', 'last_login_at')) {
            $user->last_login_at = now();
            $user->save();
        }

        if (Schema::hasTable('user_login_histories')) {
            UserLoginHistory::query()->create([
                'user_id' => (string) $user->id,
                'logged_in_at' => now(),
                'ip' => $data['ip'] ?? request()->ip(),
                'user_agent' => substr((string) ($data['user_agent'] ?? request()->userAgent() ?? ''), 0, 1000),
            ]);
        }

        return [
            'success' => true,
            'message' => 'Registration via LinkedIn completed successfully.',
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
            'status_code' => 201,
        ];
    }

    /**
     * Authenticate or register a user with LinkedIn credentials and generate login session.
     *
     * @param  array<string, mixed>  $deviceMeta
     * @return array{success: bool, message: string, data: array<string, mixed>|null, status_code: int}
     */
    public function authenticateAndLogin(
        string $codeOrToken,
        ?string $redirectUri = null,
        ?string $timezone = null,
        array $deviceMeta = []
    ): array {
        try {
            $profile = $this->verifyLinkedIn($codeOrToken, $redirectUri);
            $user = $this->socialAuthService->findOrCreateSocialUser('linkedin', $profile, $timezone);
        } catch (\Throwable $e) {
            Log::warning('LinkedInAuthService.authenticateAndLogin: failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'status_code' => 400,
            ];
        }

        $user->expireFreeTrialIfNeeded();
        $user->refresh();

        if ($user->membership_status === 'suspended') {
            return [
                'success' => false,
                'message' => 'Account is suspended',
                'data' => null,
                'status_code' => 403,
            ];
        }

        if (in_array($user->status, ['rejected', 'pending'], true)) {
            $message = $user->status === 'pending'
                ? 'Your registration request is under review. You will receive an email once it is approved.'
                : 'Your registration request has been rejected. Please contact support for further details.';

            return [
                'success' => false,
                'message' => $message,
                'data' => null,
                'status_code' => 403,
            ];
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $pushToken = $deviceMeta['device_token']
            ?? $deviceMeta['fcm_token']
            ?? $deviceMeta['push_token']
            ?? null;

        if (filled($pushToken)) {
            UserPushToken::registerTokenForUser($user, [
                'token' => (string) $pushToken,
                'platform' => isset($deviceMeta['platform']) ? (string) $deviceMeta['platform'] : (isset($deviceMeta['device_type']) ? (string) $deviceMeta['device_type'] : null),
                'device_id' => isset($deviceMeta['device_id']) ? (string) $deviceMeta['device_id'] : null,
                'app_version' => isset($deviceMeta['app_version']) ? (string) $deviceMeta['app_version'] : null,
            ]);
        }

        if (Schema::hasColumn('users', 'last_login_at')) {
            $user->last_login_at = now();
            $user->save();
        }

        if (Schema::hasTable('user_login_histories')) {
            UserLoginHistory::query()->create([
                'user_id' => (string) $user->id,
                'logged_in_at' => now(),
                'ip' => $deviceMeta['ip'] ?? request()->ip(),
                'user_agent' => substr((string) ($deviceMeta['user_agent'] ?? request()->userAgent() ?? ''), 0, 1000),
            ]);
        }

        return [
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
            'status_code' => 200,
        ];
    }
}
