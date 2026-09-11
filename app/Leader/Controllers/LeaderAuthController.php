<?php

declare(strict_types=1);

namespace App\Leader\Controllers;

use App\Http\Controllers\Controller;
use App\Leader\Requests\LeaderSendOtpRequest;
use App\Leader\Requests\LeaderUpdateProfileRequest;
use App\Leader\Requests\LeaderUploadAvatarRequest;
use App\Leader\Requests\LeaderVerifyOtpRequest;
use App\Leader\Services\LeaderAuthService;
use App\Models\User;
use App\Models\UserPushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class LeaderAuthController extends Controller
{
    public function __construct(
        private readonly LeaderAuthService $authService,
    ) {}

    /**
     * Request Login OTP.
     */
    public function sendOtp(LeaderSendOtpRequest $request): JsonResponse
    {
        $data = $this->authService->sendOtp((string) $request->validated('email_or_phone'));

        if (empty($data['is_registered'])) {
            return response()->json([
                'success' => false,
                'error_code' => 'USER_NOT_FOUND',
                'message' => 'No account found with the provided email or phone.',
                'data' => $data,
            ], 404);
        }

        if (empty($data['is_leader'])) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_A_LEADER',
                'message' => 'Access restricted. Only peers with an assigned leadership role can log into the Leader App.',
                'data' => $data,
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP has been sent successfully to your registered email/phone.',
            'data' => $data,
        ]);
    }

    /**
     * Verify Login OTP.
     */
    public function verifyOtp(LeaderVerifyOtpRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->verifyOtp(
                (string) $request->validated('email_or_phone'),
                (string) $request->validated('otp'),
            );

            $pushToken = $request->input('token')
                ?? $request->input('device_token')
                ?? $request->input('fcm_token')
                ?? $request->input('push_token')
                ?? $request->input('firebase_token');

            if (filled($pushToken) && ! empty($data['user']['id'])) {
                $user = User::find($data['user']['id']);
                if ($user) {
                    UserPushToken::registerTokenForUser($user, [
                        'token' => $pushToken,
                        'platform' => $request->input('platform') ?? $request->input('device_type'),
                        'device_id' => $request->input('device_id'),
                        'app_version' => $request->input('app_version'),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Authentication successful',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $isNotLeader = str_contains(strtolower($msg), 'leadership') || str_contains(strtolower($msg), 'leader');

            return response()->json([
                'success' => false,
                'error_code' => $isNotLeader ? 'NOT_A_LEADER' : 'INVALID_CREDENTIALS',
                'message' => $msg,
                'details' => null,
            ], $isNotLeader ? 403 : 422);
        }
    }

    /**
     * Update user profile details.
     */
    public function updateProfile(LeaderUpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->authService->updateProfile($user, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Upload and update user profile avatar.
     */
    public function uploadAvatar(LeaderUploadAvatarRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->authService->updateAvatar($user, $request->file('avatar'));

        return response()->json([
            'success' => true,
            'message' => 'Avatar updated successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Get user profile details with current role, managed circles, and dynamic capabilities.
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->authService->getProfile($user);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
