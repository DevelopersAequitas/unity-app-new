<?php

declare(strict_types=1);

namespace App\Services\Admin\Auth;

class AdminWhatsAppAuthService extends AdminAuthService
{
    /**
     * Backward-compatible alias for requesting a WhatsApp OTP.
     *
     * @return array{status: int, success: bool, message: string, data: array<string, mixed>|null}
     */
    public function requestOtp(string $identifier): array
    {
        return $this->requestWhatsAppOtp($identifier);
    }

    /**
     * Backward-compatible alias for verifying a WhatsApp OTP.
     *
     * @return array{status: int, success: bool, message: string, data: array<string, mixed>|null}
     */
    public function verifyOtp(string $identifier, string $otp): array
    {
        return $this->verifyWhatsAppOtp($identifier, $otp);
    }
}
