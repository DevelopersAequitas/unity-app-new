<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestPhoneRegisterOtpRequest;
use App\Http\Requests\Auth\VerifyPhoneRegisterOtpRequest;
use App\Services\Auth\PhoneRegisterVerificationService;
use Illuminate\Http\JsonResponse;

class PhoneRegisterVerificationController extends Controller
{
    public function __construct(
        protected PhoneRegisterVerificationService $verificationService
    ) {}

    /**
     * Request WhatsApp OTP for phone registration verification.
     */
    public function requestOtp(RequestPhoneRegisterOtpRequest $request): JsonResponse
    {
        $phone = (string) ($request->validated('phone') ?? $request->validated('mobile'));
        $countryCode = $request->validated('country_code');

        $result = $this->verificationService->requestOtp(
            rawPhone: $phone,
            countryCode: $countryCode !== null ? (string) $countryCode : null,
            clientIp: $request->ip()
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'],
        ], $result['status']);
    }

    /**
     * Verify WhatsApp OTP and obtain phone verification token.
     */
    public function verifyOtp(VerifyPhoneRegisterOtpRequest $request): JsonResponse
    {
        $phone = (string) ($request->validated('phone') ?? $request->validated('mobile'));
        $otp = (string) $request->validated('otp');
        $countryCode = $request->validated('country_code');

        $result = $this->verificationService->verifyOtp(
            rawPhone: $phone,
            otp: $otp,
            countryCode: $countryCode !== null ? (string) $countryCode : null
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => null,
            ], $result['status']);
        }

        $token = $result['phone_verification_token'] ?? '';

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'phone_verification_token' => $token,
            'verification_token' => $token,
            'data' => [
                'phone_verification_token' => $token,
                'verification_token' => $token,
            ],
        ], 200);
    }
}
