<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leader\LeaderSendOtpRequest;
use App\Http\Requests\Leader\LeaderUpdateProfileRequest;
use App\Http\Requests\Leader\LeaderUploadAvatarRequest;
use App\Http\Requests\Leader\LeaderVerifyOtpRequest;
use App\Models\User;
use App\Services\Leader\LeaderAuthService;
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

            return response()->json([
                'success' => true,
                'message' => 'Authentication successful',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_CREDENTIALS',
                'message' => $e->getMessage(),
                'details' => null,
            ], 422);
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
