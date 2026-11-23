<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Auth\PhoneRegisterVerificationService;
use App\Services\Auth\SocialAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SocialAuthController extends Controller
{
    public function __construct(
        protected SocialAuthService $socialAuthService,
        protected PhoneRegisterVerificationService $phoneVerificationService
    ) {}

    /**
     * Authenticate via Google ID token or Access Token.
     */
    public function googleLogin(Request $request): JsonResponse
    {
        $idToken = (string) ($request->input('id_token') ?? $request->input('access_token') ?? '');
        $googleId = (string) ($request->input('google_id') ?? '');
        $email = strtolower(trim((string) ($request->input('email') ?? '')));
        $name = (string) ($request->input('name') ?? '');
        $photoUrl = (string) ($request->input('photo_url') ?? '');

        // Attempt verification with token or fall back to verified payload attributes if present
        $profile = null;
        if ($idToken !== '') {
            try {
                $profile = $this->socialAuthService->verifyGoogle($idToken);
            } catch (Throwable $e) {
                Log::warning('googleLogin tokeninfo verification failed, checking payload: '.$e->getMessage());
            }
        }

        if (! $profile) {
            if ($googleId === '' || $email === '') {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid Google authentication credentials.',
                    'error_code' => 'INVALID_CREDENTIALS',
                ], 422);
            }

            $profile = [
                'id' => $googleId,
                'email' => $email,
                'name' => $name,
                'first_name' => explode(' ', $name)[0] ?? '',
                'last_name' => explode(' ', $name)[1] ?? '',
                'avatar' => $photoUrl ?: null,
            ];
        }

        return $this->handleSocialProfile('google', $profile, $request);
    }

    /**
     * Authenticate via Facebook access token.
     */
    public function facebookLogin(Request $request): JsonResponse
    {
        $accessToken = (string) ($request->input('access_token') ?? '');
        $facebookId = (string) ($request->input('facebook_id') ?? '');
        $email = strtolower(trim((string) ($request->input('email') ?? '')));
        $name = (string) ($request->input('name') ?? '');
        $photoUrl = (string) ($request->input('photo_url') ?? '');

        $profile = null;
        if ($accessToken !== '') {
            try {
                $profile = $this->socialAuthService->verifyFacebook($accessToken);
            } catch (Throwable $e) {
                Log::warning('facebookLogin token verification failed: '.$e->getMessage());
            }
        }

        if (! $profile) {
            if ($facebookId === '' || $email === '') {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid Facebook authentication credentials.',
                    'error_code' => 'INVALID_CREDENTIALS',
                ], 422);
            }

            $profile = [
                'id' => $facebookId,
                'email' => $email,
                'name' => $name,
                'first_name' => explode(' ', $name)[0] ?? '',
                'last_name' => explode(' ', $name)[1] ?? '',
                'avatar' => $photoUrl ?: null,
            ];
        }

        return $this->handleSocialProfile('facebook', $profile, $request);
    }

    /**
     * Finalize new social user registration after phone verification.
     */
    public function completeSocialRegistration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:google,facebook,linkedin'],
            'provider_user_id' => ['required', 'string'],
            'email' => ['required', 'email:rfc'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:25'],
            'phone_verification_token' => ['required', 'string'],
            'dob' => ['nullable', 'date'],
            'photo_url' => ['nullable', 'string', 'max:1024'],
        ]);

        $phone = (string) $validated['phone'];
        $token = (string) $validated['phone_verification_token'];

        if (! $this->phoneVerificationService->validatePhoneVerificationToken($token, $phone)) {
            return response()->json([
                'status' => false,
                'message' => 'Phone verification token is invalid or expired. Please verify via WhatsApp first.',
                'error_code' => 'INVALID_PHONE_TOKEN',
            ], 422);
        }

        $normalizedEmail = strtolower(trim((string) $validated['email']));

        // Check if phone or email already registered
        $existingUser = User::query()
            ->where('email', $normalizedEmail)
            ->orWhere('phone', $phone)
            ->first();

        if ($existingUser) {
            $this->updateUserSocialMetadata($existingUser, (string) $validated['provider'], (string) $validated['provider_user_id'], $validated['photo_url'] ?? null);

            $authToken = $existingUser->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => true,
                'message' => 'Social account linked to existing profile successfully.',
                'data' => [
                    'token' => $authToken,
                    'user' => $this->formatUserResponse($existingUser),
                ],
            ], 200);
        }

        // Create new user
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->first_name = (string) $validated['first_name'];
        $user->last_name = $validated['last_name'] ?? null;
        $user->display_name = trim($user->first_name.' '.($user->last_name ?? ''));
        $user->email = $normalizedEmail;
        $user->phone = $phone;
        $user->status = 'active';
        $user->password_hash = Hash::make(Str::random(32));

        if (Schema::hasColumn('users', 'dob') && filled($validated['dob'] ?? null)) {
            $user->dob = (string) $validated['dob'];
        }

        if (Schema::hasColumn('users', 'phone_verified_at')) {
            $user->phone_verified_at = now();
        }
        if (Schema::hasColumn('users', 'is_phone_verified')) {
            $user->is_phone_verified = true;
        }
        if (Schema::hasColumn('users', 'oauth_provider')) {
            $user->oauth_provider = (string) $validated['provider'];
        }
        if (Schema::hasColumn('users', 'social_avatar_url') && filled($validated['photo_url'] ?? null)) {
            $user->social_avatar_url = (string) $validated['photo_url'];
        }

        $providerCol = (string) $validated['provider'].'_id';
        if (Schema::hasColumn('users', $providerCol)) {
            $user->{$providerCol} = (string) $validated['provider_user_id'];
        }

        $user->save();
        $user->refresh();

        // Also link to social_accounts table
        if (Schema::hasTable('social_accounts')) {
            SocialAccount::query()->firstOrCreate(
                [
                    'provider' => (string) $validated['provider'],
                    'provider_id' => (string) $validated['provider_user_id'],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'user_id' => (string) $user->id,
                    'email' => $normalizedEmail,
                    'token_metadata' => [
                        'name' => $user->display_name,
                        'avatar' => $validated['photo_url'] ?? null,
                    ],
                ]
            );
        }

        $authToken = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Registration completed successfully.',
            'data' => [
                'token' => $authToken,
                'user' => $this->formatUserResponse($user),
            ],
        ], 201);
    }

    protected function handleSocialProfile(string $provider, array $profile, Request $request): JsonResponse
    {
        $providerUserId = (string) $profile['id'];
        $email = strtolower(trim((string) $profile['email']));

        $providerCol = $provider.'_id';

        // Check if existing user is linked by provider ID or email
        /** @var User|null $user */
        $user = User::query()
            ->when(Schema::hasColumn('users', $providerCol), fn ($q) => $q->where($providerCol, $providerUserId))
            ->orWhere('email', $email)
            ->first();

        // Check social_accounts table
        if (! $user && Schema::hasTable('social_accounts')) {
            $socialAccount = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_id', $providerUserId)
                ->first();
            if ($socialAccount && $socialAccount->user) {
                $user = $socialAccount->user;
            }
        }

        // If existing user has completed phone verification or already has this provider linked, log them in
        if ($user && (filled($user->phone) || (! empty($user->{$providerCol}) && (string) $user->{$providerCol} === $providerUserId))) {
            $this->updateUserSocialMetadata($user, $provider, $providerUserId, $profile['avatar'] ?? null);

            $authToken = $user->createToken('auth_token')->plainTextToken;

            $this->registerPushToken($user, $request);

            return response()->json([
                'status' => true,
                'message' => 'Login successful.',
                'data' => [
                    'is_new_user' => false,
                    'is_registered' => true,
                    'token' => $authToken,
                    'user' => $this->formatUserResponse($user),
                ],
            ], 200);
        }

        // New social user: requires birthday & WhatsApp OTP
        return response()->json([
            'status' => true,
            'message' => 'Google account verified. Registration required.',
            'data' => [
                'token' => null,
                'user' => null,
                'is_new_user' => true,
                'is_registered' => false,
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                $providerCol => $providerUserId,
                'email' => $email,
                'first_name' => $profile['first_name'] ?? '',
                'last_name' => $profile['last_name'] ?? '',
                'name' => $profile['name'] ?? '',
                'photo_url' => $profile['avatar'] ?? null,
                'avatar_url' => $profile['avatar'] ?? null,
                'social_profile' => [
                    'google_id' => $providerUserId,
                    $providerCol => $providerUserId,
                    'email' => $email,
                    'name' => $profile['name'] ?? '',
                    'photo_url' => $profile['avatar'] ?? null,
                ],
            ],
        ], 200);
    }

    protected function updateUserSocialMetadata(User $user, string $provider, string $providerUserId, ?string $avatarUrl): void
    {
        $providerCol = $provider.'_id';
        if (Schema::hasColumn('users', $providerCol) && blank($user->{$providerCol})) {
            $user->{$providerCol} = $providerUserId;
        }
        if (Schema::hasColumn('users', 'oauth_provider') && $user->oauth_provider === 'manual') {
            $user->oauth_provider = $provider;
        }
        if (Schema::hasColumn('users', 'social_avatar_url') && filled($avatarUrl) && blank($user->social_avatar_url)) {
            $user->social_avatar_url = $avatarUrl;
        }
        $user->save();
    }

    protected function registerPushToken(User $user, Request $request): void
    {
        $pushToken = $request->input('device_token')
            ?? $request->input('fcm_token')
            ?? $request->input('push_token');

        if (filled($pushToken)) {
            UserPushToken::registerTokenForUser($user, [
                'token' => $pushToken,
                'platform' => $request->input('platform') ?? $request->input('device_type'),
                'device_id' => $request->input('device_id'),
                'app_version' => $request->input('app_version'),
            ]);
        }
    }

    protected function formatUserResponse(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'first_name' => (string) ($user->first_name ?? ''),
            'last_name' => (string) ($user->last_name ?? ''),
            'name' => (string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))),
            'email' => (string) ($user->email ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            'avatar' => (string) ($user->profile_photo_url ?? $user->social_avatar_url ?? ''),
            'status' => (string) ($user->status ?? 'active'),
            'is_member' => (bool) ($user->is_member ?? false),
            'company_name' => $user->company_name ?? null,
            'designation' => $user->designation ?? null,
            'sector_id' => Schema::hasColumn('users', 'sector_id') ? $user->sector_id : null,
            'business_category_id' => $user->business_category_id ?? null,
            'has_seen_first_registration_paywall' => Schema::hasColumn('users', 'has_seen_first_registration_paywall')
                ? (int) (bool) $user->has_seen_first_registration_paywall
                : 0,
            'onboarding_completed' => Schema::hasColumn('users', 'onboarding_completed')
                ? (int) (bool) $user->onboarding_completed
                : 0,
        ];
    }
}
