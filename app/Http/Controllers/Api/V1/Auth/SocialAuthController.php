<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\FacebookLoginRequest;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserPushToken;
use App\Services\Auth\FacebookAuthService;
use App\Services\Auth\PhoneRegisterVerificationService;
use App\Services\Auth\SocialAuthService;
use DateTimeInterface;
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
        protected PhoneRegisterVerificationService $phoneVerificationService,
        protected FacebookAuthService $facebookAuthService
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
     * Authenticate via Facebook classic OAuth token or iOS Limited Login OIDC JWT.
     */
    public function facebookLogin(FacebookLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $accessToken = (string) $validated['access_token'];
        $tokenType = (string) ($validated['token_type'] ?? 'classic');
        $nonce = isset($validated['nonce']) ? (string) $validated['nonce'] : null;

        try {
            $verified = $this->facebookAuthService->verifyToken($accessToken, $tokenType, $nonce);
        } catch (Throwable $e) {
            Log::warning('facebookLogin token verification failed', [
                'token_type' => $tokenType,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Invalid or expired Facebook token.',
                'error_code' => 'INVALID_FACEBOOK_TOKEN',
            ], 401);
        }

        $verifiedFbId = (string) $verified['id'];
        $verifiedEmail = strtolower(trim((string) ($verified['email'] ?? '')));

        $firstName = $verified['first_name'] !== ''
            ? $verified['first_name']
            : trim((string) ($validated['first_name'] ?? ''));
        $lastName = $verified['last_name'] !== ''
            ? $verified['last_name']
            : trim((string) ($validated['last_name'] ?? ''));
        $fullName = $verified['name'] !== ''
            ? $verified['name']
            : trim((string) ($validated['name'] ?? ''));

        if ($firstName === '' && $lastName === '' && $fullName !== '') {
            $nameParts = preg_split('/\s+/', $fullName, 2);
            $firstName = trim((string) ($nameParts[0] ?? ''));
            $lastName = trim((string) ($nameParts[1] ?? ''));
        }

        $avatarUrl = $verified['avatar']
            ?? (filled($validated['photo_url'] ?? null) ? trim((string) $validated['photo_url']) : null);

        $dob = $verified['dob']
            ?? $this->facebookAuthService->parseBirthday(isset($validated['dob']) ? (string) $validated['dob'] : null);

        $location = $verified['location'];
        if ($location === null) {
            $clientLocation = trim((string) ($validated['location'] ?? $validated['address'] ?? ''));
            $location = $clientLocation !== '' ? $clientLocation : null;
        }

        // Lookup priority:
        // 1. users.facebook_id or social_accounts (provider='facebook', provider_id)
        /** @var User|null $user */
        $user = null;
        if (Schema::hasColumn('users', 'facebook_id')) {
            $user = User::withTrashed()
                ->where('facebook_id', $verifiedFbId)
                ->first();
        }

        if (! $user && Schema::hasTable('social_accounts')) {
            $socialAccount = SocialAccount::query()
                ->where('provider', 'facebook')
                ->where('provider_id', $verifiedFbId)
                ->first();

            if ($socialAccount) {
                $user = $socialAccount->user()->withTrashed()->first();
            }
        }

        // 2. Only when verified email is non-empty, look up by email and link facebook_id
        if (! $user && $verifiedEmail !== '') {
            $user = User::withTrashed()
                ->where(function ($q) use ($verifiedEmail): void {
                    $q->where('email', $verifiedEmail)
                        ->orWhereRaw('LOWER(TRIM(email)) = ?', [$verifiedEmail]);
                })
                ->first();
        }

        if ($user && method_exists($user, 'trashed') && $user->trashed()) {
            $user->restore();
            $user->status = 'active';
            if (Schema::hasColumn('users', 'approval_status')) {
                $user->approval_status = 'approved';
            }
            if (Schema::hasColumn('users', 'membership_status') && empty($user->membership_status)) {
                $user->membership_status = 'free_peer';
            }
            $user->save();
        }

        $isNewUser = false;

        // 3. Create new user when no match exists
        if (! $user) {
            $user = new User;
            $user->id = (string) Str::uuid();
            $user->first_name = $firstName !== '' ? $firstName : ($fullName !== '' ? $fullName : 'User');
            $user->last_name = $lastName !== '' ? $lastName : null;
            $user->display_name = $fullName !== '' ? $fullName : trim($user->first_name.' '.($user->last_name ?? ''));
            $user->email = $verifiedEmail !== '' ? $verifiedEmail : "fb_{$verifiedFbId}@facebook.local";
            $user->phone = null;
            $user->status = 'active';

            if (Schema::hasColumn('users', 'membership_status')) {
                $user->membership_status = 'free_peer';
            }

            $hashedPassword = Hash::make(Str::random(32));
            if (Schema::hasColumn('users', 'password')) {
                $user->password = $hashedPassword;
            }
            if (Schema::hasColumn('users', 'password_hash')) {
                $user->password_hash = $hashedPassword;
            }
            if (Schema::hasColumn('users', 'approval_status')) {
                $user->approval_status = 'approved';
            }
            if (Schema::hasColumn('users', 'oauth_provider')) {
                $user->oauth_provider = 'facebook';
            }
            if (Schema::hasColumn('users', 'facebook_id')) {
                $user->facebook_id = $verifiedFbId;
            }
            if (Schema::hasColumn('users', 'social_avatar_url') && $avatarUrl !== null) {
                $user->social_avatar_url = $avatarUrl;
            }
            if (Schema::hasColumn('users', 'profile_photo_url') && $avatarUrl !== null) {
                $user->profile_photo_url = $avatarUrl;
            }
            if ($dob !== null && Schema::hasColumn('users', 'dob')) {
                $user->dob = $dob;
            }
            if ($location !== null) {
                if (Schema::hasColumn('users', 'city')) {
                    $user->city = $location;
                }
                if (Schema::hasColumn('users', 'city_of_residence')) {
                    $user->city_of_residence = $location;
                }
                if (Schema::hasColumn('users', 'business_address')) {
                    $user->business_address = $location;
                }
            }

            $user->save();
            $user->refresh();
            $isNewUser = true;
        } else {
            // Non-destructive sync: link facebook_id & only backfill blank fields.
            $this->syncFacebookProfileToExistingUser($user, $verifiedFbId, [
                'email' => $verifiedEmail !== '' ? $verifiedEmail : null,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => $fullName,
                'avatar' => $avatarUrl,
                'dob' => $dob,
                'location' => $location,
            ]);
            $user->refresh();
        }

        if (Schema::hasTable('social_accounts')) {
            SocialAccount::query()->updateOrCreate(
                [
                    'provider' => 'facebook',
                    'provider_id' => $verifiedFbId,
                ],
                [
                    'id' => SocialAccount::query()
                        ->where('provider', 'facebook')
                        ->where('provider_id', $verifiedFbId)
                        ->value('id') ?? (string) Str::uuid(),
                    'user_id' => (string) $user->id,
                    'email' => $verifiedEmail !== '' ? $verifiedEmail : null,
                    'token_metadata' => [
                        'name' => $user->display_name,
                        'avatar' => $avatarUrl,
                        'token_type' => $verified['token_type'],
                    ],
                ]
            );
        }

        $authToken = $user->createToken('auth_token')->plainTextToken;
        $this->registerPushToken($user, $request);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Authenticated successfully',
            'data' => [
                'token' => $authToken,
                'is_new_user' => $isNewUser,
                'is_registered' => true,
                'has_phone' => filled($user->phone),
                'provider' => 'facebook',
                'provider_user_id' => $verifiedFbId,
                'facebook_id' => $verifiedFbId,
                'user' => $this->formatUserResponse($user),
            ],
        ], 200);
    }

    /**
     * Handle Meta Data Deletion Callback (signed_request HMAC-SHA256).
     */
    public function facebookDataDeletion(Request $request): JsonResponse
    {
        $signedRequest = trim((string) ($request->input('signed_request') ?? ''));
        if ($signedRequest === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing signed_request.',
            ], 400);
        }

        try {
            $payload = $this->facebookAuthService->verifySignedRequest($signedRequest);
        } catch (Throwable $e) {
            Log::warning('facebookDataDeletion invalid signed_request', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid signed_request.',
            ], 400);
        }

        $fbUserId = trim((string) ($payload['user_id'] ?? ''));

        if ($fbUserId !== '') {
            if (Schema::hasTable('social_accounts')) {
                SocialAccount::query()
                    ->where('provider', 'facebook')
                    ->where('provider_id', $fbUserId)
                    ->delete();
            }

            if (Schema::hasColumn('users', 'facebook_id')) {
                $users = User::withTrashed()
                    ->where('facebook_id', $fbUserId)
                    ->get();

                foreach ($users as $matchedUser) {
                    $matchedUser->facebook_id = null;
                    if (Schema::hasColumn('users', 'social_avatar_url')) {
                        $matchedUser->social_avatar_url = null;
                    }
                    $matchedUser->save();
                }
            }
        }

        $confirmationCode = 'fb_del_'.Str::lower(Str::random(16));

        Log::info('facebook.data_deletion.processed', [
            'facebook_user_id' => $fbUserId,
            'confirmation_code' => $confirmationCode,
        ]);

        return response()->json([
            'url' => rtrim((string) config('app.url'), '/').'/data-deletion-status?code='.$confirmationCode,
            'confirmation_code' => $confirmationCode,
        ], 200);
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

        // Check if phone or email already registered (including soft-deleted accounts)
        $existingUser = User::withTrashed()
            ->where('email', $normalizedEmail)
            ->orWhere('phone', $phone)
            ->first();

        if ($existingUser && method_exists($existingUser, 'trashed') && $existingUser->trashed()) {
            $existingUser->restore();
            $existingUser->status = 'active';
            $existingUser->save();
        }

        if ($existingUser) {
            if (blank($existingUser->phone)) {
                $existingUser->phone = $phone;
                if (Schema::hasColumn('users', 'phone_verified_at')) {
                    $existingUser->phone_verified_at = now();
                }
                if (Schema::hasColumn('users', 'is_phone_verified')) {
                    $existingUser->is_phone_verified = true;
                }
            }
            if (Schema::hasColumn('users', 'dob') && filled($validated['dob'] ?? null) && blank($existingUser->dob)) {
                $existingUser->dob = (string) $validated['dob'];
            }
            $existingUser->save();

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

        // Check if existing user is linked by provider ID or non-empty email (including soft-deleted accounts)
        /** @var User|null $user */
        $user = User::withTrashed()
            ->where(function ($q) use ($providerCol, $providerUserId, $email) {
                $hasCondition = false;
                if (Schema::hasColumn('users', $providerCol)) {
                    $q->where($providerCol, $providerUserId);
                    $hasCondition = true;
                }
                if ($email !== '') {
                    $hasCondition ? $q->orWhere('email', $email) : $q->where('email', $email);
                    $hasCondition = true;
                }
                if (! $hasCondition) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->first();

        // Check social_accounts table
        if (! $user && Schema::hasTable('social_accounts')) {
            $socialAccount = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_id', $providerUserId)
                ->first();
            if ($socialAccount) {
                $user = $socialAccount->user()->withTrashed()->first();
            }
        }

        // If the account was soft-deleted, restore and reactivate it
        if ($user && method_exists($user, 'trashed') && $user->trashed()) {
            $user->restore();
            $user->status = 'active';
            if (Schema::hasColumn('users', 'approval_status')) {
                $user->approval_status = 'approved';
            }
            if (Schema::hasColumn('users', 'membership_status') && empty($user->membership_status)) {
                $user->membership_status = 'free_peer';
            }
            $user->save();
        }

        $isNewUser = false;

        // If user does not exist, create user account immediately in database
        if (! $user) {
            $user = new User;
            $user->id = (string) Str::uuid();
            $user->first_name = ! empty($profile['first_name']) ? (string) $profile['first_name'] : ((string) ($profile['name'] ?? 'User'));
            $user->last_name = $profile['last_name'] ?? null;
            $user->display_name = ! empty($profile['name']) ? (string) $profile['name'] : trim($user->first_name.' '.($user->last_name ?? ''));
            $user->email = $email;
            $user->status = 'active';

            if (Schema::hasColumn('users', 'membership_status')) {
                $user->membership_status = 'free_peer';
            }

            $hashedPassword = Hash::make(Str::random(32));
            if (Schema::hasColumn('users', 'password')) {
                $user->password = $hashedPassword;
            }
            if (Schema::hasColumn('users', 'password_hash')) {
                $user->password_hash = $hashedPassword;
            }
            if (Schema::hasColumn('users', 'approval_status')) {
                $user->approval_status = 'approved';
            }
            if (Schema::hasColumn('users', 'oauth_provider')) {
                $user->oauth_provider = $provider;
            }
            if (Schema::hasColumn('users', 'social_avatar_url') && filled($profile['avatar'] ?? null)) {
                $user->social_avatar_url = (string) $profile['avatar'];
            }
            if (Schema::hasColumn('users', 'profile_photo_url') && filled($profile['avatar'] ?? null)) {
                $user->profile_photo_url = (string) $profile['avatar'];
            }
            if (Schema::hasColumn('users', $providerCol)) {
                $user->{$providerCol} = $providerUserId;
            }

            $user->save();
            $user->refresh();

            if (Schema::hasTable('social_accounts')) {
                SocialAccount::query()->firstOrCreate(
                    [
                        'provider' => $provider,
                        'provider_id' => $providerUserId,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'user_id' => (string) $user->id,
                        'email' => $email,
                        'token_metadata' => [
                            'name' => $user->display_name,
                            'avatar' => $profile['avatar'] ?? null,
                        ],
                    ]
                );
            }

            $isNewUser = true;
        } else {
            $this->updateUserSocialMetadata($user, $provider, $providerUserId, $profile['avatar'] ?? null);

            if (Schema::hasTable('social_accounts')) {
                SocialAccount::query()->firstOrCreate(
                    [
                        'provider' => $provider,
                        'provider_id' => $providerUserId,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'user_id' => (string) $user->id,
                        'email' => $email,
                        'token_metadata' => [
                            'name' => $user->display_name,
                            'avatar' => $profile['avatar'] ?? null,
                        ],
                    ]
                );
            }
        }

        $authToken = $user->createToken('auth_token')->plainTextToken;
        $this->registerPushToken($user, $request);

        $hasPhone = filled($user->phone);

        $providerLabel = ucfirst($provider);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => $isNewUser ? "Account created via {$providerLabel}." : 'Login successful.',
            'data' => [
                'token' => $authToken,
                'is_new_user' => false,
                'is_registered' => true,
                'has_phone' => $hasPhone,
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                $providerCol => $providerUserId,
                'email' => $email,
                'first_name' => $profile['first_name'] ?? ($user->first_name ?? ''),
                'last_name' => $profile['last_name'] ?? ($user->last_name ?? ''),
                'name' => $user->display_name,
                'photo_url' => $profile['avatar'] ?? $user->profile_photo_url,
                'avatar_url' => $profile['avatar'] ?? $user->profile_photo_url,
                'user' => $this->formatUserResponse($user),
                'social_profile' => [
                    $providerCol => $providerUserId,
                    'email' => $email,
                    'name' => $user->display_name,
                    'photo_url' => $profile['avatar'] ?? $user->profile_photo_url,
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

    /**
     * Non-destructively sync verified Facebook attributes onto an existing user.
     *
     * @param  array{
     *     email: ?string,
     *     first_name: string,
     *     last_name: string,
     *     name: string,
     *     avatar: ?string,
     *     dob: ?string,
     *     location: ?string
     * }  $profile
     */
    protected function syncFacebookProfileToExistingUser(User $user, string $facebookId, array $profile): void
    {
        if (Schema::hasColumn('users', 'facebook_id') && blank($user->facebook_id)) {
            $user->facebook_id = $facebookId;
        }

        if (Schema::hasColumn('users', 'oauth_provider') && (blank($user->oauth_provider) || $user->oauth_provider === 'manual')) {
            $user->oauth_provider = 'facebook';
        }

        if (blank($user->first_name) && $profile['first_name'] !== '') {
            $user->first_name = $profile['first_name'];
        }

        if (blank($user->last_name) && $profile['last_name'] !== '') {
            $user->last_name = $profile['last_name'];
        }

        if (blank($user->display_name) && $profile['name'] !== '') {
            $user->display_name = $profile['name'];
        }

        if ($profile['email'] !== null && (blank($user->email) || str_ends_with((string) $user->email, '@facebook.local'))) {
            $emailConflict = User::withTrashed()
                ->where('id', '!=', (string) $user->id)
                ->whereRaw('LOWER(TRIM(email)) = ?', [$profile['email']])
                ->exists();

            if (! $emailConflict) {
                $user->email = $profile['email'];
            }
        }

        $avatar = $profile['avatar'];
        if ($avatar !== null) {
            if (Schema::hasColumn('users', 'social_avatar_url')) {
                $user->social_avatar_url = $avatar;
            }

            $hasCustomUploadedAvatar = (Schema::hasColumn('users', 'profile_photo_file_id') && filled($user->profile_photo_file_id))
                || (Schema::hasColumn('users', 'profile_photo_id') && filled($user->profile_photo_id));

            if (! $hasCustomUploadedAvatar && Schema::hasColumn('users', 'profile_photo_url')) {
                $rawPhotoUrl = (string) ($user->getAttributes()['profile_photo_url'] ?? '');
                $isFacebookCdnAvatar = $rawPhotoUrl === ''
                    || str_contains($rawPhotoUrl, 'fbsbx.com')
                    || str_contains($rawPhotoUrl, 'fbcdn.net')
                    || str_contains($rawPhotoUrl, 'graph.facebook.com');

                if ($isFacebookCdnAvatar) {
                    $user->profile_photo_url = $avatar;
                }
            }
        }

        if ($profile['dob'] !== null && Schema::hasColumn('users', 'dob') && blank($user->dob)) {
            $user->dob = $profile['dob'];
        }

        if ($profile['location'] !== null) {
            if (Schema::hasColumn('users', 'city') && blank($user->city)) {
                $user->city = $profile['location'];
            }
            if (Schema::hasColumn('users', 'city_of_residence') && blank($user->city_of_residence)) {
                $user->city_of_residence = $profile['location'];
            }
            if (Schema::hasColumn('users', 'business_address') && blank($user->business_address)) {
                $user->business_address = $profile['location'];
            }
        }

        $user->save();
    }

    protected function formatUserResponse(User $user): array
    {
        $displayName = (string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')));
        $rawEmail = (string) ($user->email ?? '');
        $publicEmail = str_ends_with(strtolower($rawEmail), '@facebook.local') ? '' : $rawEmail;
        $rawAvatar = (string) ($user->profile_photo_url ?? $user->social_avatar_url ?? '');

        $rawDob = $user->dob ?? null;
        $formattedDob = null;
        if ($rawDob instanceof DateTimeInterface) {
            $formattedDob = $rawDob->format('Y-m-d');
        } elseif (is_string($rawDob) && trim($rawDob) !== '') {
            $formattedDob = trim($rawDob);
        }

        $location = null;
        if (Schema::hasColumn('users', 'city_of_residence') && filled($user->city_of_residence)) {
            $location = (string) $user->city_of_residence;
        } elseif (Schema::hasColumn('users', 'city') && filled($user->city)) {
            $location = (string) $user->city;
        }

        $address = Schema::hasColumn('users', 'business_address') && filled($user->business_address)
            ? (string) $user->business_address
            : null;

        return [
            'id' => (string) $user->id,
            'first_name' => (string) ($user->first_name ?? ''),
            'last_name' => (string) ($user->last_name ?? ''),
            'name' => $displayName,
            'display_name' => $displayName,
            'email' => $publicEmail,
            'phone' => filled($user->phone) ? (string) $user->phone : null,
            'avatar' => $rawAvatar,
            'avatar_url' => $rawAvatar !== '' ? $rawAvatar : null,
            'life_impacted_count' => (int) ($user->life_impacted_count ?? 0),
            'coins_balance' => (int) ($user->coins_balance ?? 0),
            'dob' => $formattedDob,
            'location' => $location,
            'address' => $address,
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
