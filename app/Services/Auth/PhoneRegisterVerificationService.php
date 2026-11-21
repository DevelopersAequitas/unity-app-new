<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\ContactChangeOtp;
use App\Models\User;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class PhoneRegisterVerificationService
{
    private const OTP_EXPIRES_SECONDS = 600; // 10 minutes

    private const MAX_ATTEMPTS = 5;

    private const COOLDOWN_SECONDS = 30;

    private const HOURLY_MAX_REQUESTS = 5;

    private const TOKEN_TTL_MINUTES = 15;

    public function __construct(
        protected WhatsappNotificationService $whatsappNotificationService
    ) {}

    /**
     * Request a WhatsApp OTP for phone verification before registration.
     *
     * @return array{status: int, success: bool, message: string, data: array<string, mixed>|null}
     */
    public function requestOtp(string $rawPhone, ?string $countryCode = null, ?string $clientIp = null): array
    {
        $e164Phone = $this->normalizePhoneToE164($rawPhone, $countryCode);
        if ($e164Phone === '') {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Please enter a valid phone number.',
                'data' => null,
            ];
        }

        // 409 check: number already registered
        if ($this->phoneAlreadyRegistered($e164Phone)) {
            return [
                'status' => 409,
                'success' => false,
                'message' => 'This phone number is already registered with an account.',
                'data' => null,
            ];
        }

        $rateLimitError = $this->checkRateLimit($e164Phone, $clientIp);
        if ($rateLimitError !== null) {
            return $rateLimitError;
        }

        $otp = (string) random_int(100000, 999999);

        ContactChangeOtp::query()->create([
            'user_id' => null,
            'channel' => 'whatsapp_register',
            'target_value' => $e164Phone,
            'otp_hash' => Hash::make($otp),
            'attempts' => 0,
            'expires_at' => now()->addSeconds(self::OTP_EXPIRES_SECONDS),
            'consumed_at' => null,
        ]);

        try {
            $sent = $this->whatsappNotificationService->send(
                templateKey: 'otp_verification',
                phone: $e164Phone,
                payload: [
                    'code' => $otp,
                ]
            );

            if (! $sent) {
                Log::warning('WhatsApp OTP dispatch returned false for phone registration', [
                    'target_phone' => $e164Phone,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Failed to send WhatsApp registration OTP: '.$e->getMessage(), [
                'target_phone' => $e164Phone,
            ]);

            return [
                'status' => 503,
                'success' => false,
                'message' => 'Failed to send WhatsApp verification OTP. Please try again later.',
                'data' => null,
            ];
        }

        $this->hitRateLimit($e164Phone, $clientIp);

        return [
            'status' => 200,
            'success' => true,
            'message' => 'OTP sent successfully via WhatsApp.',
            'data' => [
                'expires_in_seconds' => self::OTP_EXPIRES_SECONDS,
            ],
        ];
    }

    /**
     * Verify WhatsApp OTP and generate phone verification token for registration.
     *
     * @return array{status: int, success: bool, message: string, phone_verification_token?: string}
     */
    public function verifyOtp(string $rawPhone, string $otp, ?string $countryCode = null): array
    {
        $e164Phone = $this->normalizePhoneToE164($rawPhone, $countryCode);
        if ($e164Phone === '') {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Please enter a valid phone number.',
            ];
        }

        /** @var ContactChangeOtp|null $record */
        $record = ContactChangeOtp::query()
            ->whereNull('user_id')
            ->where('channel', 'whatsapp_register')
            ->where('target_value', $e164Phone)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $record) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ];
        }

        if (now()->greaterThan($record->expires_at)) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ];
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Maximum verification attempts exceeded. Please request a new OTP.',
            ];
        }

        if (! Hash::check($otp, $record->otp_hash)) {
            $record->increment('attempts');

            return [
                'status' => 422,
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ];
        }

        $record->consumed_at = now();
        $record->save();

        // Issue signed phone verification token bound to the phone
        $token = $this->generatePhoneVerificationToken($e164Phone);

        return [
            'status' => 200,
            'success' => true,
            'message' => 'Phone number verified successfully.',
            'phone_verification_token' => $token,
        ];
    }

    /**
     * Generate an encrypted, timestamped token bound to the normalized phone.
     */
    public function generatePhoneVerificationToken(string $e164Phone): string
    {
        $payload = [
            'phone' => $e164Phone,
            'digits' => WhatsappNotificationService::normalizePhone($e164Phone),
            'purpose' => 'phone_registration_verification',
            'issued_at' => now()->timestamp,
            'expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES)->timestamp,
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Validate the phone verification token matches the submitted registration phone.
     */
    public function validatePhoneVerificationToken(string $token, string $rawPhone, ?string $countryCode = null): bool
    {
        try {
            $decrypted = Crypt::decryptString($token);
            $payload = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($payload) || ($payload['purpose'] ?? '') !== 'phone_registration_verification') {
                return false;
            }

            $expiresAt = (int) ($payload['expires_at'] ?? 0);
            if (now()->timestamp > $expiresAt) {
                return false;
            }

            $tokenDigits = (string) ($payload['digits'] ?? '');
            if ($tokenDigits === '') {
                return false;
            }

            $inputNormalized = $this->normalizePhoneToE164($rawPhone, $countryCode);
            $inputDigits = WhatsappNotificationService::normalizePhone($inputNormalized);

            if ($inputDigits === $tokenDigits) {
                return true;
            }

            // Also match national 10-digit suffix if applicable
            return strlen($inputDigits) >= 10
                && strlen($tokenDigits) >= 10
                && substr($inputDigits, -10) === substr($tokenDigits, -10);
        } catch (Throwable $e) {
            Log::warning('validatePhoneVerificationToken failed: '.$e->getMessage());

            return false;
        }
    }

    public function normalizePhoneToE164(string $rawPhone, ?string $countryCode = null): string
    {
        $trimmed = trim($rawPhone);
        if ($trimmed === '') {
            return '';
        }

        if (! str_starts_with($trimmed, '+') && $countryCode !== null && trim($countryCode) !== '') {
            $ccDigits = preg_replace('/\D+/', '', $countryCode) ?? '';
            $phoneDigits = preg_replace('/\D+/', '', $trimmed) ?? '';
            if ($ccDigits !== '' && $phoneDigits !== '' && ! str_starts_with($phoneDigits, $ccDigits)) {
                $trimmed = '+'.$ccDigits.$phoneDigits;
            }
        }

        $normalizedDigits = WhatsappNotificationService::normalizePhone($trimmed);
        if ($normalizedDigits === '') {
            return '';
        }

        return '+'.$normalizedDigits;
    }

    public function phoneAlreadyRegistered(string $e164Phone): bool
    {
        $normalizedDigits = WhatsappNotificationService::normalizePhone($e164Phone);
        $nationalDigits = strlen($normalizedDigits) >= 10 ? substr($normalizedDigits, -10) : $normalizedDigits;

        return User::query()
            ->where(function ($query) use ($e164Phone, $normalizedDigits, $nationalDigits): void {
                $query->where('phone', $e164Phone)
                    ->orWhere('phone', $normalizedDigits)
                    ->orWhere('phone', '+'.$normalizedDigits)
                    ->orWhere('phone', $nationalDigits)
                    ->orWhere('phone', 'like', '%'.$nationalDigits);
            })
            ->exists();
    }

    /**
     * @return array{status: int, success: bool, message: string, data: null}|null
     */
    protected function checkRateLimit(string $targetPhone, ?string $clientIp): ?array
    {
        $phoneKey = 'phone-register-cooldown:'.md5($targetPhone);
        if (RateLimiter::tooManyAttempts($phoneKey, 1)) {
            $seconds = RateLimiter::availableIn($phoneKey);

            return [
                'status' => 429,
                'success' => false,
                'message' => "Please wait {$seconds} seconds before requesting another OTP.",
                'data' => null,
            ];
        }

        $hourlyKey = 'phone-register-hourly:'.md5($targetPhone);
        if (RateLimiter::tooManyAttempts($hourlyKey, self::HOURLY_MAX_REQUESTS)) {
            return [
                'status' => 429,
                'success' => false,
                'message' => 'Too many OTP requests for this phone number. Please try again after an hour.',
                'data' => null,
            ];
        }

        if ($clientIp !== null && $clientIp !== '') {
            $ipKey = 'phone-register-ip-hourly:'.md5($clientIp);
            if (RateLimiter::tooManyAttempts($ipKey, 15)) {
                return [
                    'status' => 429,
                    'success' => false,
                    'message' => 'Too many OTP requests from your IP. Please try again after an hour.',
                    'data' => null,
                ];
            }
        }

        return null;
    }

    protected function hitRateLimit(string $targetPhone, ?string $clientIp): void
    {
        $phoneKey = 'phone-register-cooldown:'.md5($targetPhone);
        $hourlyKey = 'phone-register-hourly:'.md5($targetPhone);

        RateLimiter::hit($phoneKey, self::COOLDOWN_SECONDS);
        RateLimiter::hit($hourlyKey, 3600);

        if ($clientIp !== null && $clientIp !== '') {
            $ipKey = 'phone-register-ip-hourly:'.md5($clientIp);
            RateLimiter::hit($ipKey, 3600);
        }
    }
}
