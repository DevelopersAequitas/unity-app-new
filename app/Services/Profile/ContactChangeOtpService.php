<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Mail\LoginOtpMail;
use App\Models\ContactChangeOtp;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ContactChangeOtpService
{
    private const OTP_EXPIRES_SECONDS = 600;

    private const MAX_ATTEMPTS = 5;

    private const COOLDOWN_SECONDS = 30;

    private const HOURLY_MAX_REQUESTS = 5;

    public function __construct(
        protected WhatsappNotificationService $whatsappNotificationService,
        protected EmailLogService $emailLogService
    ) {}

    /**
     * Request an Email OTP to verify the user's current email.
     *
     * @return array{status: int, success: bool, message: string, data: array<string, mixed>|null}
     */
    public function requestCurrentEmailOtp(User $user): array
    {
        $currentEmail = strtolower(trim((string) ($user->email ?? '')));
        if ($currentEmail === '' || ! filter_var($currentEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'No valid email address found on your profile.',
                'data' => null,
            ];
        }

        $rateLimitError = $this->checkRateLimit((string) $user->id, 'verify_email', $currentEmail);
        if ($rateLimitError !== null) {
            return $rateLimitError;
        }

        $otp = $this->generateOtp();

        ContactChangeOtp::query()->create([
            'user_id' => (string) $user->id,
            'channel' => 'verify_email',
            'target_value' => $currentEmail,
            'otp_hash' => Hash::make($otp),
            'attempts' => 0,
            'expires_at' => now()->addSeconds(self::OTP_EXPIRES_SECONDS),
            'consumed_at' => null,
        ]);

        $mailable = new LoginOtpMail($otp, $user);

        try {
            Mail::to($currentEmail)->send($mailable);

            $this->emailLogService->logMailableSent($mailable, [
                'user_id' => (string) $user->id,
                'to_email' => $currentEmail,
                'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))),
                'template_key' => 'contact_verify_email_otp',
                'source_module' => 'Profile',
                'related_type' => User::class,
                'related_id' => (string) $user->id,
                'payload' => ['purpose' => 'verify_current_email_otp'],
            ]);
        } catch (Throwable $exception) {
            $this->emailLogService->logMailableFailed($mailable, [
                'user_id' => (string) $user->id,
                'to_email' => $currentEmail,
                'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))),
                'template_key' => 'contact_verify_email_otp',
                'source_module' => 'Profile',
                'related_type' => User::class,
                'related_id' => (string) $user->id,
                'payload' => ['purpose' => 'verify_current_email_otp'],
            ], $exception);

            Log::error('Failed to send verification email OTP: '.$exception->getMessage(), [
                'user_id' => (string) $user->id,
                'target_email' => $currentEmail,
            ]);

            return [
                'status' => 503,
                'success' => false,
                'message' => 'Failed to send verification OTP email. Please try again later.',
                'data' => null,
            ];
        }

        $this->hitRateLimit((string) $user->id, 'verify_email', $currentEmail);

        return [
            'status' => 200,
            'success' => true,
            'message' => "Verification OTP sent to {$currentEmail}",
            'data' => [
                'expires_in_seconds' => self::OTP_EXPIRES_SECONDS,
            ],
        ];
    }

    /**
     * Verify OTP and mark the user's current email as verified.
     *
     * @return array{status: int, success: bool, message: string, user?: User}
     */
    public function verifyCurrentEmailOtp(User $user, string $otp, ?string $email = null): array
    {
        $targetEmail = strtolower(trim((string) ($email ?: $user->email ?: '')));

        $record = ContactChangeOtp::query()
            ->where('user_id', (string) $user->id)
            ->where('channel', 'verify_email')
            ->where('target_value', $targetEmail)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $record || now()->greaterThan($record->expires_at) || $record->attempts >= self::MAX_ATTEMPTS) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Invalid or expired OTP.',
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

        DB::transaction(function () use ($user, $record): void {
            if (Schema::hasColumn('users', 'email_verified_at')) {
                $user->email_verified_at = now();
            }
            $user->save();

            $record->consumed_at = now();
            $record->save();

            ContactChangeOtp::query()
                ->where('user_id', (string) $user->id)
                ->where('channel', 'verify_email')
                ->whereNull('consumed_at')
                ->where('id', '!=', (string) $record->id)
                ->update(['consumed_at' => now()]);
        });

        $user->refresh();

        return [
            'status' => 200,
            'success' => true,
            'message' => 'Email verified successfully.',
            'user' => $user,
        ];
    }

    /**
     * Request an Email OTP to change the user's primary email.
     *
     * @return array{status: int, success: bool, message: string, data: array<string, mixed>|null}
     */
    public function requestEmailChangeOtp(User $user, string $email): array
    {
        $normalizedEmail = strtolower(trim($email));
        $currentEmail = strtolower(trim((string) ($user->email ?? '')));

        if ($normalizedEmail === $currentEmail) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'New email must be different from your current email.',
                'data' => null,
            ];
        }

        $exists = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->where('id', '!=', (string) $user->id)
            ->exists();

        if ($exists) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'This email address is already registered with another account.',
                'data' => null,
            ];
        }

        $rateLimitError = $this->checkRateLimit((string) $user->id, 'email', $normalizedEmail);
        if ($rateLimitError !== null) {
            return $rateLimitError;
        }

        $otp = $this->generateOtp();

        ContactChangeOtp::query()->create([
            'user_id' => (string) $user->id,
            'channel' => 'email',
            'target_value' => $normalizedEmail,
            'otp_hash' => Hash::make($otp),
            'attempts' => 0,
            'expires_at' => now()->addSeconds(self::OTP_EXPIRES_SECONDS),
            'consumed_at' => null,
        ]);

        $mailable = new LoginOtpMail($otp, $user);

        try {
            Mail::to($normalizedEmail)->send($mailable);

            $this->emailLogService->logMailableSent($mailable, [
                'user_id' => (string) $user->id,
                'to_email' => $normalizedEmail,
                'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))),
                'template_key' => 'contact_change_email_otp',
                'source_module' => 'Profile',
                'related_type' => User::class,
                'related_id' => (string) $user->id,
                'payload' => ['purpose' => 'change_email_otp'],
            ]);
        } catch (Throwable $exception) {
            $this->emailLogService->logMailableFailed($mailable, [
                'user_id' => (string) $user->id,
                'to_email' => $normalizedEmail,
                'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))),
                'template_key' => 'contact_change_email_otp',
                'source_module' => 'Profile',
                'related_type' => User::class,
                'related_id' => (string) $user->id,
                'payload' => ['purpose' => 'change_email_otp'],
            ], $exception);

            Log::error('Failed to send email change OTP: '.$exception->getMessage(), [
                'user_id' => (string) $user->id,
                'target_email' => $normalizedEmail,
            ]);

            return [
                'status' => 503,
                'success' => false,
                'message' => 'Failed to send verification OTP email. Please try again later.',
                'data' => null,
            ];
        }

        $this->hitRateLimit((string) $user->id, 'email', $normalizedEmail);

        return [
            'status' => 200,
            'success' => true,
            'message' => "Verification OTP sent to {$normalizedEmail}",
            'data' => [
                'expires_in_seconds' => self::OTP_EXPIRES_SECONDS,
            ],
        ];
    }

    /**
     * Verify Email OTP and atomically update users.email.
     *
     * @return array{status: int, success: bool, message: string, user?: User}
     */
    public function verifyEmailChangeOtp(User $user, string $email, string $otp): array
    {
        $normalizedEmail = strtolower(trim($email));

        $record = ContactChangeOtp::query()
            ->where('user_id', (string) $user->id)
            ->where('channel', 'email')
            ->where('target_value', $normalizedEmail)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $record || now()->greaterThan($record->expires_at) || $record->attempts >= self::MAX_ATTEMPTS) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Invalid or expired OTP.',
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

        $exists = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->where('id', '!=', (string) $user->id)
            ->exists();

        if ($exists) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'This email address is already registered with another account.',
            ];
        }

        DB::transaction(function () use ($user, $normalizedEmail, $record): void {
            $user->email = $normalizedEmail;
            if (Schema::hasColumn('users', 'email_verified_at')) {
                $user->email_verified_at = now();
            }
            $user->save();

            $record->consumed_at = now();
            $record->save();

            ContactChangeOtp::query()
                ->where('user_id', (string) $user->id)
                ->where('channel', 'email')
                ->whereNull('consumed_at')
                ->where('id', '!=', (string) $record->id)
                ->update(['consumed_at' => now()]);
        });

        $user->refresh();

        return [
            'status' => 200,
            'success' => true,
            'message' => 'Primary email updated successfully.',
            'user' => $user,
        ];
    }

    /**
     * Request a WhatsApp OTP to change the user's primary phone number.
     *
     * @return array{status: int, success: bool, message: string, data: array<string, mixed>|null}
     */
    public function requestPhoneChangeOtp(User $user, string $rawPhone, ?string $countryCode = null): array
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

        if ($this->isSamePhone((string) ($user->phone ?? ''), $e164Phone)) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'New phone number must be different from your current number.',
                'data' => null,
            ];
        }

        if ($this->phoneBelongsToAnotherUser($e164Phone, (string) $user->id)) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'This phone number is already registered with another account.',
                'data' => null,
            ];
        }

        $rateLimitError = $this->checkRateLimit((string) $user->id, 'whatsapp', $e164Phone);
        if ($rateLimitError !== null) {
            return $rateLimitError;
        }

        $otp = $this->generateOtp();

        ContactChangeOtp::query()->create([
            'user_id' => (string) $user->id,
            'channel' => 'whatsapp',
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
                ],
                userId: (string) $user->id
            );

            if (! $sent) {
                Log::warning('WhatsApp OTP dispatch returned false for phone change', [
                    'user_id' => (string) $user->id,
                    'target_phone' => $e164Phone,
                ]);
            }
        } catch (Throwable $exception) {
            Log::error('Failed to send WhatsApp phone change OTP: '.$exception->getMessage(), [
                'user_id' => (string) $user->id,
                'target_phone' => $e164Phone,
            ]);

            return [
                'status' => 503,
                'success' => false,
                'message' => 'Failed to send WhatsApp verification OTP. Please try again later.',
                'data' => null,
            ];
        }

        $this->hitRateLimit((string) $user->id, 'whatsapp', $e164Phone);

        return [
            'status' => 200,
            'success' => true,
            'message' => "WhatsApp verification OTP sent to {$e164Phone}",
            'data' => [
                'expires_in_seconds' => self::OTP_EXPIRES_SECONDS,
            ],
        ];
    }

    /**
     * Verify WhatsApp OTP and atomically update users.phone.
     *
     * @return array{status: int, success: bool, message: string, user?: User}
     */
    public function verifyPhoneChangeOtp(User $user, string $rawPhone, string $otp, ?string $countryCode = null): array
    {
        $e164Phone = $this->normalizePhoneToE164($rawPhone, $countryCode);
        if ($e164Phone === '') {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ];
        }

        $record = ContactChangeOtp::query()
            ->where('user_id', (string) $user->id)
            ->where('channel', 'whatsapp')
            ->where('target_value', $e164Phone)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if (! $record || now()->greaterThan($record->expires_at) || $record->attempts >= self::MAX_ATTEMPTS) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Invalid or expired OTP.',
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

        if ($this->phoneBelongsToAnotherUser($e164Phone, (string) $user->id)) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'This phone number is already registered with another account.',
            ];
        }

        DB::transaction(function () use ($user, $e164Phone, $record): void {
            $user->phone = $e164Phone;
            if (Schema::hasColumn('users', 'mobile')) {
                $user->mobile = $e164Phone;
            }
            if (Schema::hasColumn('users', 'phone_verified_at')) {
                $user->phone_verified_at = now();
            }
            $user->save();

            $record->consumed_at = now();
            $record->save();

            ContactChangeOtp::query()
                ->where('user_id', (string) $user->id)
                ->where('channel', 'whatsapp')
                ->whereNull('consumed_at')
                ->where('id', '!=', (string) $record->id)
                ->update(['consumed_at' => now()]);
        });

        $user->refresh();

        return [
            'status' => 200,
            'success' => true,
            'message' => 'Primary phone number updated successfully.',
            'user' => $user,
        ];
    }

    protected function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{status: int, success: bool, message: string, data: null}|null
     */
    protected function checkRateLimit(string $userId, string $channel, string $target): ?array
    {
        $cooldownKey = "contact-change-cooldown:{$channel}:{$userId}:{$target}";
        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $seconds = RateLimiter::availableIn($cooldownKey);

            return [
                'status' => 429,
                'success' => false,
                'message' => "Please wait {$seconds} seconds before requesting another OTP.",
                'data' => null,
            ];
        }

        $hourlyKey = "contact-change-hourly:{$channel}:{$userId}:{$target}";
        if (RateLimiter::tooManyAttempts($hourlyKey, self::HOURLY_MAX_REQUESTS)) {
            return [
                'status' => 429,
                'success' => false,
                'message' => 'Too many OTP requests. Please try again after an hour.',
                'data' => null,
            ];
        }

        return null;
    }

    protected function hitRateLimit(string $userId, string $channel, string $target): void
    {
        $cooldownKey = "contact-change-cooldown:{$channel}:{$userId}:{$target}";
        $hourlyKey = "contact-change-hourly:{$channel}:{$userId}:{$target}";

        RateLimiter::hit($cooldownKey, self::COOLDOWN_SECONDS);
        RateLimiter::hit($hourlyKey, 3600);
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

    protected function isSamePhone(string $currentPhone, string $newE164Phone): bool
    {
        if (trim($currentPhone) === '') {
            return false;
        }

        $currentDigits = WhatsappNotificationService::normalizePhone($currentPhone);
        $newDigits = WhatsappNotificationService::normalizePhone($newE164Phone);

        if ($currentDigits === '' || $newDigits === '') {
            return false;
        }

        if ($currentDigits === $newDigits) {
            return true;
        }

        return strlen($currentDigits) >= 10
            && strlen($newDigits) >= 10
            && substr($currentDigits, -10) === substr($newDigits, -10);
    }

    protected function phoneBelongsToAnotherUser(string $e164Phone, string $excludeUserId): bool
    {
        $normalizedDigits = WhatsappNotificationService::normalizePhone($e164Phone);
        $nationalDigits = strlen($normalizedDigits) >= 10 ? substr($normalizedDigits, -10) : $normalizedDigits;

        return User::query()
            ->where('id', '!=', $excludeUserId)
            ->where(function ($query) use ($e164Phone, $normalizedDigits, $nationalDigits): void {
                $query->where('phone', $e164Phone)
                    ->orWhere('phone', $normalizedDigits)
                    ->orWhere('phone', '+'.$normalizedDigits)
                    ->orWhere('phone', $nationalDigits)
                    ->orWhere('phone', 'like', '%'.$nationalDigits);
            })
            ->exists();
    }
}
