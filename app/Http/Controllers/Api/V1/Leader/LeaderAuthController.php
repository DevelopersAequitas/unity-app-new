<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leader\LeaderSendOtpRequest;
use App\Http\Requests\Leader\LeaderVerifyOtpRequest;
use App\Services\Leader\LeaderAuthService;
use Illuminate\Http\JsonResponse;
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
}
