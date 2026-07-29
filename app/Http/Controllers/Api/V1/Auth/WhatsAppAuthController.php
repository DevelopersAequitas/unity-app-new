<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestWhatsAppOtpRequest;
use App\Http\Requests\Auth\VerifyWhatsAppOtpRequest;
use App\Services\Auth\WhatsAppOtpAuthService;
use Illuminate\Http\JsonResponse;

class WhatsAppAuthController extends Controller
{
    public function __construct(
        protected WhatsAppOtpAuthService $whatsAppOtpAuthService
    ) {}

    /**
     * Handle request for WhatsApp OTP.
     */
    public function requestOtp(RequestWhatsAppOtpRequest $request): JsonResponse
    {
        $mobile = (string) $request->input('mobile');

        $result = $this->whatsAppOtpAuthService->requestOtp($mobile);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'],
        ], $result['status']);
    }

    /**
     * Handle verification of WhatsApp OTP.
     */
    public function verifyOtp(VerifyWhatsAppOtpRequest $request): JsonResponse
    {
        $mobile = (string) $request->input('mobile');
        $otp = (string) $request->input('otp');
        $deviceName = $request->input('device_name') ? (string) $request->input('device_name') : null;

        $deviceMeta = array_merge($request->all(), [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $result = $this->whatsAppOtpAuthService->verifyOtp($mobile, $otp, $deviceName, $deviceMeta);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'],
        ], $result['status']);
    }
}
