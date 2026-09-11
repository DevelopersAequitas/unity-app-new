<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Mail\LoginOtpMail;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class OtpService
{
    public function __construct(
        protected WhatsappNotificationService $whatsappNotificationService,
        protected EmailLogService $emailLogService
    ) {}

    /**
     * Handle unified OTP request for a user.
     *
     * @return array{status: int, success: bool, message: string, error_code?: string, data?: array<string, mixed>|null}
     */
    public function requestOtp(string $email, string $channel = 'email', ?string $ip = null): array
    {
        $normalizedEmail = strtolower(trim($email));
        $normalizedChannel = strtolower(trim($channel ?: 'email'));

        // 1. Session & Rate Limiting: Max 3 requests per 5 minutes per email/IP
        $throttleKey = 'request-otp:'.$normalizedEmail.'|'.($ip ?? '0.0.0.0');
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);
            $timeString = $minutes > 1 ? "{$minutes} minutes" : ($minutes === 1 ? '1 minute' : "{$seconds} seconds");

            return [
                'status' => 429,
                'success' => false,
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'message' => "Too many requests. Please try again in {$timeString}.",
                'data' => null,
            ];
        }

        RateLimiter::hit($throttleKey, 300);

        // 2. Query user record
        $user = User::query()->where('email', $normalizedEmail)->first();

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

        // 3. Generate 4-digit cryptographically secure OTP valid for 5 minutes (300 seconds)
        $otp = (string) random_int(1000, 9999);
        $expiresIn = 300;

        OtpCode::query()->create([
            'user_id' => (string) $user->id,
            'email' => (string) $user->email,
            'purpose' => 'login_otp',
            'code' => Hash::make($otp),
            'expires_at' => now()->addSeconds($expiresIn),
            'used_at' => null,
        ]);

        // 4. Channel Selection & Dispatch Logic
        if ($normalizedChannel === 'whatsapp') {
            $phone = trim((string) ($user->phone ?? ''));

            if ($phone !== '') {
                $whatsappSent = false;
                try {
                    $whatsappSent = $this->whatsappNotificationService->send(
                        templateKey: 'otp_verification',
                        phone: $phone,
                        payload: [
                            'code' => $otp,
                        ],
                        userId: (string) $user->id
                    );
                } catch (Throwable $e) {
                    Log::error('Unified request-otp WhatsApp dispatch exception: '.$e->getMessage(), [
                        'user_id' => (string) $user->id,
                        'phone' => $phone,
                    ]);
                    $whatsappSent = false;
                }

                if ($whatsappSent) {
                    return [
                        'status' => 200,
                        'success' => true,
                        'message' => 'OTP sent successfully to your WhatsApp.',
                        'data' => [
                            'email' => (string) $user->email,
                            'channel' => 'whatsapp',
                            'expires_in' => $expiresIn,
                        ],
                    ];
                }
            }

            // Fallback Rule: If no phone number is linked or WhatsApp dispatch fails, fall back to email
            $fallbackReason = $phone === ''
                ? 'No phone number linked for WhatsApp'
                : 'WhatsApp delivery unavailable';

            $emailResult = $this->sendOtpEmail($user, $otp);
            if (! $emailResult['success']) {
                return $emailResult;
            }

            return [
                'status' => 200,
                'success' => true,
                'message' => "OTP sent successfully to your email ({$fallbackReason}).",
                'data' => [
                    'email' => (string) $user->email,
                    'channel' => 'email',
                    'expires_in' => $expiresIn,
                ],
            ];
        }

        // Default Channel: "email"
        $emailResult = $this->sendOtpEmail($user, $otp);
        if (! $emailResult['success']) {
            return $emailResult;
        }

        return [
            'status' => 200,
            'success' => true,
            'message' => 'OTP sent successfully to your email.',
            'data' => [
                'email' => (string) $user->email,
                'channel' => 'email',
                'expires_in' => $expiresIn,
            ],
        ];
    }

    /**
     * Dispatch OTP email and record email logs.
     *
     * @return array{status: int, success: bool, message: string, data?: array<string, mixed>|null}
     */
    protected function sendOtpEmail(User $user, string $otp): array
    {
        $mailable = new LoginOtpMail($otp, $user);

        try {
            Mail::to($user->email)->send($mailable);

            $this->emailLogService->logMailableSent($mailable, [
                'user_id' => (string) $user->id,
                'to_email' => (string) $user->email,
                'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))),
                'template_key' => 'login_otp',
                'source_module' => 'Auth',
                'related_type' => User::class,
                'related_id' => (string) $user->id,
                'payload' => [
                    'purpose' => 'login_otp',
                ],
            ]);

            return [
                'status' => 200,
                'success' => true,
                'message' => 'OTP sent successfully to your email.',
            ];
        } catch (Throwable $exception) {
            $this->emailLogService->logMailableFailed($mailable, [
                'user_id' => (string) $user->id,
                'to_email' => (string) $user->email,
                'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))),
                'template_key' => 'login_otp',
                'source_module' => 'Auth',
                'related_type' => User::class,
                'related_id' => (string) $user->id,
                'payload' => [
                    'purpose' => 'login_otp',
                ],
            ], $exception);

            Log::error('Failed to send OTP email: '.$exception->getMessage(), [
                'user_id' => (string) $user->id,
                'email' => (string) $user->email,
            ]);

            return [
                'status' => 503,
                'success' => false,
                'message' => 'Failed to send OTP email due to a mail server issue. Please try again later or request OTP via WhatsApp.',
                'data' => null,
            ];
        }
    }
}
