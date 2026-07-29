<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Http\Resources\UserResource;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\UserLoginHistory;
use App\Models\UserPushToken;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Support\Facades\Hash;

class WhatsAppOtpAuthService
{
    public function __construct(
        protected WhatsappNotificationService $whatsappNotificationService
    ) {}

    /**
     * Request WhatsApp OTP for a user based on mobile number.
     *
     * @return array{status: int, success: bool, message: string, data: mixed}
     */
    public function requestOtp(string $mobile): array
    {
        $user = $this->findUserByMobile($mobile);

        if (! $user) {
            return [
                'status' => 404,
                'success' => false,
                'message' => 'You are not a registered user.',
                'data' => null,
            ];
        }

        $user->expireFreeTrialIfNeeded();
        $user->refresh();

        if ($user->membership_status === 'suspended') {
            return [
                'status' => 403,
                'success' => false,
                'message' => 'Account is suspended',
                'data' => null,
            ];
        }

        if (($user->status ?? 'active') !== 'active') {
            $message = 'Your account is inactive. Please contact support.';
            if ($user->status === 'inactive') {
                $message = 'Your registration request is under review. You will receive an email once it is approved.';
            } elseif ($user->status === 'rejected') {
                $message = 'Your registration request has been rejected. Please contact support for further details.';
            }

            return [
                'status' => 403,
                'success' => false,
                'message' => $message,
                'data' => null,
            ];
        }

        $otp = (string) random_int(1000, 9999);

        OtpCode::query()->create([
            'user_id' => $user->id,
            'email' => $user->email ?? $mobile,
            'purpose' => 'whatsapp_otp',
            'code' => Hash::make($otp),
            'expires_at' => now()->addMinutes(5),
            'used_at' => null,
        ]);

        $this->whatsappNotificationService->send(
            templateKey: 'otp_verification',
            phone: $mobile,
            payload: [
                'code' => $otp,
            ]
        );

        return [
            'status' => 200,
            'success' => true,
            'message' => 'OTP sent successfully via WhatsApp.',
            'data' => null,
        ];
    }

    /**
     * Verify WhatsApp OTP and authenticate user.
     *
     * @param  array<string, mixed>  $deviceMeta  Additional device parameters (push tokens, app version, IP, etc.)
     * @return array{status: int, success: bool, message: string, data: mixed}
     */
    public function verifyOtp(string $mobile, string $otp, ?string $deviceName = null, array $deviceMeta = []): array
    {
        $user = $this->findUserByMobile($mobile);

        if (! $user) {
            return [
                'status' => 404,
                'success' => false,
                'message' => 'You are not a registered user.',
                'data' => null,
            ];
        }

        $otpRecord = OtpCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', 'whatsapp_otp')
            ->whereNull('used_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $otpRecord) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Invalid OTP.',
                'data' => null,
            ];
        }

        if (now()->greaterThan($otpRecord->expires_at)) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'OTP has expired.',
                'data' => null,
            ];
        }

        if (! Hash::check($otp, $otpRecord->code)) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Invalid OTP.',
                'data' => null,
            ];
        }

        $otpRecord->used_at = now();
        $otpRecord->save();

        $user->expireFreeTrialIfNeeded();
        $user->refresh();

        if ($user->membership_status === 'suspended') {
            return [
                'status' => 403,
                'success' => false,
                'message' => 'Account is suspended',
                'data' => null,
            ];
        }

        if (($user->status ?? 'active') !== 'active') {
            $message = 'Your account is inactive. Please contact support.';
            if ($user->status === 'inactive') {
                $message = 'Your registration request is under review. You will receive an email once it is approved.';
            } elseif ($user->status === 'rejected') {
                $message = 'Your registration request has been rejected. Please contact support for further details.';
            }

            return [
                'status' => 403,
                'success' => false,
                'message' => $message,
                'data' => null,
            ];
        }

        $user->last_login_at = now();
        $user->save();
        $user->refresh();

        $token = $user->createToken($deviceName ?? 'api')->plainTextToken;

        $pushToken = $deviceMeta['token']
            ?? $deviceMeta['device_token']
            ?? $deviceMeta['fcm_token']
            ?? $deviceMeta['push_token']
            ?? $deviceMeta['firebase_token']
            ?? null;

        if (is_string($pushToken) && filled($pushToken)) {
            UserPushToken::registerTokenForUser($user, [
                'token' => $pushToken,
                'platform' => $deviceMeta['platform'] ?? $deviceMeta['device_type'] ?? null,
                'device_id' => $deviceMeta['device_id'] ?? null,
                'app_version' => $deviceMeta['app_version'] ?? null,
            ]);
        }

        UserLoginHistory::query()->create([
            'user_id' => $user->id,
            'logged_in_at' => now(),
            'ip' => $deviceMeta['ip'] ?? null,
            'user_agent' => isset($deviceMeta['user_agent']) ? substr((string) $deviceMeta['user_agent'], 0, 1000) : null,
        ]);

        return [
            'status' => 200,
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => new UserResource($user->load([
                    'city',
                    'activeCircle:id,name,slug,city_id',
                    'activeCircle.cityRef:id,name',
                    'circleMemberships' => fn ($query) => $query
                        ->where('status', (string) config('circle.member_joined_status', 'approved'))
                        ->whereNull('deleted_at')
                        ->whereNull('left_at')
                        ->where(function ($nested): void {
                            $nested->whereNull('paid_ends_at')->orWhere('paid_ends_at', '>=', now());
                        })
                        ->orderByDesc('joined_at')
                        ->with('circle:id,name,slug'),
                ])),
                'token' => $token,
            ],
        ];
    }

    /**
     * Locate a User record by mobile input.
     */
    public function findUserByMobile(string $mobile): ?User
    {
        $normalized = WhatsappNotificationService::normalizePhone($mobile);
        $nationalDigits = strlen($normalized) >= 10 ? substr($normalized, -10) : $mobile;

        return User::query()
            ->where('phone', $mobile)
            ->orWhere('phone', $normalized)
            ->orWhere('phone', '+'.$normalized)
            ->orWhere('phone', $nationalDigits)
            ->orWhere('phone', 'like', '%'.$nationalDigits)
            ->first();
    }
}
