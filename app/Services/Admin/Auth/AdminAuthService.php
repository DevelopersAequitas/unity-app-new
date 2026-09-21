<?php

declare(strict_types=1);

namespace App\Services\Admin\Auth;

use App\Mail\AdminLoginOtpMail;
use App\Models\AdminLoginOtp;
use App\Models\AdminUser;
use App\Models\CircleMember;
use App\Models\Role;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AdminAuthService
{
    public function __construct(
        protected WhatsappNotificationService $whatsappNotificationService
    ) {}

    /**
     * Get all configured admin login methods.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableLoginMethods(): array
    {
        $methods = config('auth.admin_login_methods', [
            'email' => [
                'key' => 'email',
                'label' => 'Email OTP',
                'enabled' => true,
                'digits' => 4,
                'input_type' => 'email',
                'placeholder' => 'you@company.com',
                'color' => '#3b82f6',
            ],
            'whatsapp' => [
                'key' => 'whatsapp',
                'label' => 'WhatsApp OTP',
                'enabled' => true,
                'digits' => 4,
                'input_type' => 'tel',
                'placeholder' => 'e.g. 9876543210',
                'color' => '#10b981',
            ],
        ]);

        return array_values($methods);
    }

    /**
     * Get only enabled admin login methods.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEnabledLoginMethods(): array
    {
        return array_values(array_filter(
            $this->getAvailableLoginMethods(),
            fn (array $method): bool => ! empty($method['enabled'])
        ));
    }

    /**
     * Check if a specific login channel is currently enabled.
     */
    public function isMethodEnabled(string $channel): bool
    {
        $enabled = $this->getEnabledLoginMethods();
        foreach ($enabled as $method) {
            if (($method['key'] ?? '') === $channel) {
                return true;
            }
        }

        return false;
    }

    /**
     * Automatically detect whether an identifier is an email or a mobile number.
     */
    public function detectChannel(string $identifier): string
    {
        $identifier = trim($identifier);
        if (str_contains($identifier, '@') || filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false) {
            return 'email';
        }

        return 'whatsapp';
    }

    /**
     * Unified method to request OTP for admin login based on automatic channel detection.
     *
     * @return array{status: int, success: bool, message: string, data: array<string, mixed>|null}
     */
    public function requestOtp(string $identifier): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Email or mobile number is required.',
                'data' => null,
            ];
        }

        $channel = $this->detectChannel($identifier);

        if ($channel === 'email') {
            $result = $this->requestEmailOtp($identifier);
            if ($result['success'] && isset($result['data'])) {
                $result['data']['identifier'] = $identifier;
                $result['data']['channel'] = 'email';
                $result['data']['otp_length'] = 4;
            }

            return $result;
        }

        $result = $this->requestWhatsAppOtp($identifier);
        if ($result['success'] && isset($result['data'])) {
            $result['data']['identifier'] = $identifier;
            $result['data']['channel'] = 'whatsapp';
            $result['data']['otp_length'] = 4;
        }

        return $result;
    }

    /**
     * Unified method to verify OTP for admin login based on automatic channel detection.
     *
     * @return array{status: int, success: bool, message: string, data: array<string, mixed>|null}
     */
    public function verifyOtp(string $identifier, string $otp): array
    {
        $identifier = trim($identifier);
        $otp = trim($otp);

        if ($identifier === '' || $otp === '') {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Identifier and verification code are required.',
                'data' => null,
            ];
        }

        $channel = $this->detectChannel($identifier);

        if ($channel === 'email') {
            return $this->verifyEmailOtp($identifier, $otp);
        }

        return $this->verifyWhatsAppOtp($identifier, $otp);
    }

    /**
     * Request Email OTP for admin login.
     *
     * @return array{status: int, success: bool, message: string, data: array<string, mixed>|null}
     */
    public function requestEmailOtp(string $email): array
    {
        if (! $this->isMethodEnabled('email')) {
            return [
                'status' => 403,
                'success' => false,
                'message' => 'Email OTP login method is currently disabled.',
                'data' => null,
            ];
        }

        $email = strtolower(trim($email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'A valid email address is required.',
                'data' => null,
            ];
        }

        $bypassEmails = [
            'hardik@gmail.com',
            'harsh@gmail.com',
            'urvashi@gmail.com',
            'dhruvil@gmail.com',
            'chirag@gmail.com',
            'mohit@gmail.com',
            'rahul@gmail.com',
            'vinit@gmail.com',
        ];

        if (app()->environment('local')) {
            $bypassEmails[] = 'missurvashi300@gmail.com';
        }

        // Special password bypass user
        if ($email === 'harshchauhanwork26@gmail.com') {
            $adminUser = $this->resolveOrCreateAdminUser($email);

            return [
                'status' => 200,
                'success' => true,
                'message' => 'Enter password to login',
                'data' => [
                    'email' => $email,
                    'channel' => 'email',
                    'is_password_user' => true,
                ],
            ];
        }

        // Instant login bypass users
        if (in_array($email, $bypassEmails, true)) {
            $adminUser = $this->resolveOrCreateAdminUser($email);

            return [
                'status' => 200,
                'success' => true,
                'message' => 'Bypass login successful',
                'data' => [
                    'email' => $email,
                    'channel' => 'email',
                    'admin_user' => $adminUser,
                    'is_direct_login' => true,
                ],
            ];
        }

        $adminUser = $this->eligibleAdminByEmail($email);
        if (! $adminUser) {
            return [
                'status' => 403,
                'success' => false,
                'message' => 'You are not admin',
                'data' => null,
            ];
        }

        // Check 30s resend cooldown for email OTP
        $recentOtp = AdminLoginOtp::query()
            ->where('email', $email)
            ->orderByDesc('created_at')
            ->first();

        if ($recentOtp && $recentOtp->last_sent_at && $recentOtp->last_sent_at->diffInSeconds(now()->utc()) < 30) {
            return [
                'status' => 429,
                'success' => false,
                'message' => 'Please wait before requesting another OTP.',
                'data' => null,
            ];
        }

        $otp = (string) random_int(1000, 9999);
        $now = now()->utc();
        $expiresAt = $now->copy()->addMinutes(5);

        $otpRecord = AdminLoginOtp::query()
            ->where('email', $email)
            ->first();

        if (! $otpRecord) {
            $otpRecord = new AdminLoginOtp;
            $otpRecord->id = (string) Str::uuid();
            $otpRecord->email = $email;
        }

        $otpRecord->otp_hash = Hash::make($otp);
        $otpRecord->expires_at = $expiresAt;
        $otpRecord->last_sent_at = $now;
        $otpRecord->attempts = 0;
        $otpRecord->used_at = null;
        $otpRecord->save();

        $subject = 'Your Admin Login OTP';
        $body = "Your admin login OTP is {$otp}. It expires in 5 minutes.";
        $name = $adminUser->name ?: 'Admin';

        try {
            $mailable = new AdminLoginOtpMail($otp, $name, $subject);
            Mail::to($email)->send($mailable);

            if (class_exists(EmailLogService::class) && Schema::hasTable('email_logs')) {
                app(EmailLogService::class)->logSent([
                    'to_email' => $email,
                    'subject' => $subject,
                    'template_key' => 'admin_login_otp',
                    'source_module' => 'Admin Auth',
                    'body_text' => $body,
                    'payload' => ['purpose' => 'admin_login_otp'],
                ]);
            }
        } catch (Throwable $exception) {
            if (class_exists(EmailLogService::class) && Schema::hasTable('email_logs')) {
                app(EmailLogService::class)->logFailed([
                    'to_email' => $email,
                    'subject' => $subject,
                    'template_key' => 'admin_login_otp',
                    'source_module' => 'Admin Auth',
                    'body_text' => $body,
                    'payload' => ['purpose' => 'admin_login_otp'],
                ], $exception);
            }

            return [
                'status' => 500,
                'success' => false,
                'message' => 'Failed to send OTP: '.$exception->getMessage(),
                'data' => null,
            ];
        }

        return [
            'status' => 200,
            'success' => true,
            'message' => 'OTP sent',
            'data' => [
                'email' => $email,
                'channel' => 'email',
                'expires_in' => 300,
            ],
        ];
    }

    /**
     * Verify Email OTP for admin login.
     *
     * @return array{status: int, success: bool, message: string, data: array<string, mixed>|null}
     */
    public function verifyEmailOtp(string $email, string $otp): array
    {
        if (! $this->isMethodEnabled('email')) {
            return [
                'status' => 403,
                'success' => false,
                'message' => 'Email OTP login method is currently disabled.',
                'data' => null,
            ];
        }

        $email = strtolower(trim($email));
        $otp = trim($otp);

        if ($email === '' || $otp === '') {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Email and verification code are required.',
                'data' => null,
            ];
        }

        $isBypassPasswordUser = ($email === 'harshchauhanwork26@gmail.com');

        $adminUser = $this->eligibleAdminByEmail($email);
        if (! $adminUser) {
            return [
                'status' => 403,
                'success' => false,
                'message' => 'You are not admin',
                'data' => null,
            ];
        }

        if ($isBypassPasswordUser) {
            if ($otp !== 'Harsh@123') {
                return [
                    'status' => 422,
                    'success' => false,
                    'message' => 'Invalid password',
                    'data' => null,
                ];
            }

            return [
                'status' => 200,
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'admin_user' => $adminUser,
                    'channel' => 'email',
                ],
            ];
        }

        $result = DB::transaction(function () use ($email, $otp): array {
            $now = now()->utc();

            if (app()->environment('local') && $otp === '0000') {
                return ['status' => 200, 'message' => 'OTP verified (Local Bypass)'];
            }

            $otpRecord = AdminLoginOtp::query()
                ->where('email', $email)
                ->whereNull('used_at')
                ->where('expires_at', '>=', $now)
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if (! $otpRecord) {
                $anyRecord = AdminLoginOtp::query()
                    ->where('email', $email)
                    ->orderByDesc('created_at')
                    ->first();

                if ($anyRecord && $anyRecord->used_at !== null) {
                    return ['status' => 422, 'message' => 'This OTP has already been used.'];
                }

                if ($anyRecord && $anyRecord->expires_at < $now) {
                    return ['status' => 410, 'message' => 'OTP expired or invalid'];
                }

                return ['status' => 410, 'message' => 'OTP expired or invalid'];
            }

            if ($otpRecord->attempts >= 5) {
                return ['status' => 423, 'message' => 'Too many attempts'];
            }

            if (! Hash::check($otp, $otpRecord->otp_hash)) {
                $otpRecord->attempts += 1;
                $otpRecord->updated_at = $now;
                $otpRecord->save();

                return ['status' => 422, 'message' => 'Invalid OTP'];
            }

            $otpRecord->used_at = $now;
            $otpRecord->updated_at = $now;
            $otpRecord->attempts += 1;
            $otpRecord->save();

            return ['status' => 200, 'message' => 'OTP verified'];
        });

        if ($result['status'] !== 200) {
            return [
                'status' => $result['status'],
                'success' => false,
                'message' => $result['message'],
                'data' => null,
            ];
        }

        return [
            'status' => 200,
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'admin_user' => $adminUser,
                'channel' => 'email',
            ],
        ];
    }

    /**
     * Request a 4-digit WhatsApp OTP for an admin user.
     *
     * @return array{status: int, success: bool, message: string, data: array<string, mixed>|null}
     */
    public function requestWhatsAppOtp(string $mobile): array
    {
        if (! $this->isMethodEnabled('whatsapp')) {
            return [
                'status' => 403,
                'success' => false,
                'message' => 'WhatsApp OTP login method is currently disabled.',
                'data' => null,
            ];
        }

        $mobile = trim($mobile);
        if ($mobile === '') {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Mobile number is required.',
                'data' => null,
            ];
        }

        $resolved = $this->resolveAdminAndUserByMobile($mobile);
        if ($resolved['error'] !== null) {
            return [
                'status' => $resolved['status'],
                'success' => false,
                'message' => $resolved['error'],
                'data' => null,
            ];
        }

        /** @var AdminUser $adminUser */
        $adminUser = $resolved['admin_user'];
        /** @var User|null $user */
        $user = $resolved['user'];
        $phone = $resolved['phone'];

        $adminEmail = strtolower($adminUser->email ?? ($user?->email ?? ''));
        if ($adminEmail === '') {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Admin account has no associated email.',
                'data' => null,
            ];
        }

        // Check 30s resend cooldown for WhatsApp OTP
        $recentOtp = AdminLoginOtp::query()
            ->where('email', $adminEmail)
            ->orderByDesc('created_at')
            ->first();

        if ($recentOtp && $recentOtp->last_sent_at && $recentOtp->last_sent_at->diffInSeconds(now()->utc()) < 30) {
            return [
                'status' => 429,
                'success' => false,
                'message' => 'Please wait before requesting another OTP.',
                'data' => null,
            ];
        }

        $otp = (string) random_int(1000, 9999);
        $now = now()->utc();
        $expiresAt = $now->copy()->addMinutes(5);

        $otpRecord = AdminLoginOtp::query()
            ->where('email', $adminEmail)
            ->first();

        if (! $otpRecord) {
            $otpRecord = new AdminLoginOtp;
            $otpRecord->id = (string) Str::uuid();
            $otpRecord->email = $adminEmail;
        }

        $otpRecord->otp_hash = Hash::make($otp);
        $otpRecord->expires_at = $expiresAt;
        $otpRecord->last_sent_at = $now;
        $otpRecord->attempts = 0;
        $otpRecord->used_at = null;
        $otpRecord->save();

        $sent = $this->whatsappNotificationService->send(
            templateKey: 'otp_verification',
            phone: $phone,
            payload: [
                'code' => $otp,
                'otp' => $otp,
                'name' => $adminUser->name,
            ],
            userId: $user ? (string) $user->id : null
        );

        if (! $sent) {
            $errorMessage = WhatsappNotificationService::$lastError ?: 'Failed to deliver WhatsApp message via provider.';
            Log::error('Admin WhatsApp OTP dispatch failed.', [
                'admin_user_id' => $adminUser->id,
                'phone' => $phone,
                'error' => $errorMessage,
            ]);

            return [
                'status' => 502,
                'success' => false,
                'message' => 'Failed to send WhatsApp OTP: '.$errorMessage,
                'data' => null,
            ];
        }

        return [
            'status' => 200,
            'success' => true,
            'message' => 'OTP sent successfully via WhatsApp.',
            'data' => [
                'mobile' => $phone,
                'email' => $adminEmail,
                'channel' => 'whatsapp',
                'expires_in' => 300,
            ],
        ];
    }

    /**
     * Verify a 4-digit WhatsApp OTP for an admin user.
     *
     * @return array{status: int, success: bool, message: string, data: array<string, mixed>|null}
     */
    public function verifyWhatsAppOtp(string $mobile, string $otp): array
    {
        if (! $this->isMethodEnabled('whatsapp')) {
            return [
                'status' => 403,
                'success' => false,
                'message' => 'WhatsApp OTP login method is currently disabled.',
                'data' => null,
            ];
        }

        $mobile = trim($mobile);
        $otp = trim($otp);

        if ($mobile === '' || $otp === '') {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Mobile number and OTP are required.',
                'data' => null,
            ];
        }

        $resolved = $this->resolveAdminAndUserByMobile($mobile);
        if ($resolved['error'] !== null) {
            return [
                'status' => $resolved['status'],
                'success' => false,
                'message' => $resolved['error'],
                'data' => null,
            ];
        }

        /** @var AdminUser $adminUser */
        $adminUser = $resolved['admin_user'];
        /** @var User|null $user */
        $user = $resolved['user'];
        $phone = $resolved['phone'];

        $adminEmail = strtolower($adminUser->email ?? ($user?->email ?? ''));
        if ($adminEmail === '') {
            return [
                'status' => 422,
                'success' => false,
                'message' => 'Admin account has no associated email.',
                'data' => null,
            ];
        }

        $result = DB::transaction(function () use ($adminEmail, $otp): array {
            $now = now()->utc();

            if (app()->environment(['local', 'testing']) && ($otp === '0000' || $otp === '000000')) {
                return ['status' => 200, 'message' => 'OTP verified (Local Bypass)'];
            }

            $otpRecord = AdminLoginOtp::query()
                ->where('email', $adminEmail)
                ->whereNull('used_at')
                ->where('expires_at', '>=', $now)
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if (! $otpRecord) {
                $anyRecord = AdminLoginOtp::query()
                    ->where('email', $adminEmail)
                    ->orderByDesc('created_at')
                    ->first();

                if ($anyRecord && $anyRecord->used_at !== null) {
                    return ['status' => 422, 'message' => 'This OTP has already been used.'];
                }

                if ($anyRecord && $anyRecord->expires_at < $now) {
                    return ['status' => 410, 'message' => 'OTP has expired.'];
                }

                return ['status' => 422, 'message' => 'Invalid OTP.'];
            }

            if ($otpRecord->attempts >= 5) {
                return ['status' => 429, 'message' => 'Too many attempts.'];
            }

            if (! Hash::check($otp, $otpRecord->otp_hash)) {
                $otpRecord->attempts += 1;
                $otpRecord->updated_at = $now;
                $otpRecord->save();

                return ['status' => 422, 'message' => 'Invalid OTP.'];
            }

            $otpRecord->used_at = $now;
            $otpRecord->updated_at = $now;
            $otpRecord->attempts += 1;
            $otpRecord->save();

            return ['status' => 200, 'message' => 'OTP verified.'];
        });

        if ($result['status'] !== 200) {
            return [
                'status' => $result['status'],
                'success' => false,
                'message' => $result['message'],
                'data' => null,
            ];
        }

        return [
            'status' => 200,
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'admin_user' => $adminUser,
                'user' => $user,
                'channel' => 'whatsapp',
            ],
        ];
    }

    /**
     * Resolve eligible admin from email address.
     */
    public function eligibleAdminByEmail(string $email): ?AdminUser
    {
        $adminUser = AdminUser::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($adminUser) {
            return $adminUser;
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user) {
            return null;
        }

        if (method_exists($user, 'trashed') && $user->trashed()) {
            return null;
        }

        if (($user->membership_status ?? '') === 'suspended' || in_array($user->status ?? 'active', ['inactive', 'rejected'], true)) {
            return null;
        }

        $eligibleRoles = ['chair', 'vice_chair', 'secretary', 'founder', 'director'];

        $isEligibleLeader = false;
        if (Schema::hasTable('circle_members')) {
            $isEligibleLeader = CircleMember::query()
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereIn(DB::raw('circle_members.role::text'), $eligibleRoles)
                ->exists();
        }

        if (! $isEligibleLeader) {
            return null;
        }

        return DB::transaction(function () use ($user): AdminUser {
            $adminUser = AdminUser::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
                ->first();

            if (! $adminUser) {
                $adminUser = AdminUser::create([
                    'id' => (string) Str::uuid(),
                    'name' => $this->resolveAdminName($user),
                    'email' => strtolower($user->email),
                ]);
            }

            if (Schema::hasTable('roles') && Schema::hasTable('admin_user_roles')) {
                $circleLeaderRoleId = Role::idByKey('circle_leader') ?? Role::mustIdByKey('circle_leader');

                if ($circleLeaderRoleId) {
                    $hasCircleLeaderRole = DB::table('admin_user_roles')
                        ->where('user_id', $adminUser->id)
                        ->where('role_id', $circleLeaderRoleId)
                        ->exists();

                    if (! $hasCircleLeaderRole) {
                        DB::table('admin_user_roles')->insert([
                            'user_id' => $adminUser->id,
                            'role_id' => $circleLeaderRoleId,
                        ]);

                        Cache::forget('admin-access:roles:'.$adminUser->id);
                    }
                }
            }

            return $adminUser;
        });
    }

    /**
     * Resolve eligible admin from mobile number.
     *
     * @return array{admin_user: AdminUser|null, user: User|null, phone: string, status: int, error: string|null}
     */
    public function resolveAdminAndUserByMobile(string $mobile): array
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return [
                'admin_user' => null,
                'user' => null,
                'phone' => '',
                'status' => 422,
                'error' => 'Invalid mobile number.',
            ];
        }

        $normalized = WhatsappNotificationService::normalizePhone($mobile);
        if ($normalized === '') {
            return [
                'admin_user' => null,
                'user' => null,
                'phone' => '',
                'status' => 422,
                'error' => 'Invalid mobile number.',
            ];
        }

        $phone = $normalized;
        $e164WithPlus = '+'.$normalized;
        $cleanRaw = preg_replace('/[^\d+]/', '', $mobile) ?? $mobile;
        $isIndianNumber = str_starts_with($normalized, '91') && strlen($normalized) === 12;
        $nationalDigits = $isIndianNumber ? substr($normalized, 2) : $normalized;

        $userQuery = User::query()
            ->where('phone', $mobile)
            ->orWhere('phone', $cleanRaw)
            ->orWhere('phone', $normalized)
            ->orWhere('phone', $e164WithPlus);

        if ($isIndianNumber) {
            $userQuery->orWhere('phone', $nationalDigits)
                ->orWhere('phone', 'like', '%'.$nationalDigits);
        } else {
            $userQuery->orWhere('phone', 'like', '%'.$normalized);
        }

        if (Schema::hasColumn('users', 'secondary_mobile')) {
            $userQuery->orWhere('secondary_mobile', $mobile)
                ->orWhere('secondary_mobile', $cleanRaw)
                ->orWhere('secondary_mobile', $normalized)
                ->orWhere('secondary_mobile', $e164WithPlus);

            if ($isIndianNumber) {
                $userQuery->orWhere('secondary_mobile', $nationalDigits)
                    ->orWhere('secondary_mobile', 'like', '%'.$nationalDigits);
            } else {
                $userQuery->orWhere('secondary_mobile', 'like', '%'.$normalized);
            }
        }

        $user = $userQuery->first();

        if (! $user) {
            $candidates = User::query()
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->get();

            foreach ($candidates as $candidate) {
                $candNormalized = WhatsappNotificationService::normalizePhone((string) $candidate->phone);
                $candClean = preg_replace('/[^\d+]/', '', (string) $candidate->phone);
                $candDigits = preg_replace('/\D+/', '', (string) $candidate->phone);
                if (
                    $candNormalized === $normalized
                    || $candDigits === $normalized
                    || $candDigits === $digits
                    || $candClean === $mobile
                    || $candClean === $cleanRaw
                    || ($isIndianNumber && strlen($candDigits) === 10 && $candDigits === $nationalDigits)
                ) {
                    $user = $candidate;
                    break;
                }
            }
        }

        $adminUser = null;
        if ($user && filled($user->email)) {
            $adminUser = AdminUser::query()->whereRaw('LOWER(email) = ?', [strtolower($user->email)])->first();
        }

        if (! $adminUser && Schema::hasColumn('admin_users', 'phone')) {
            $adminUser = AdminUser::query()
                ->where('phone', $mobile)
                ->orWhere('phone', $cleanRaw)
                ->orWhere('phone', $normalized)
                ->orWhere('phone', $e164WithPlus)
                ->orWhere('phone', $nationalDigits)
                ->first();
        }

        if (! $user && ! $adminUser) {
            return [
                'admin_user' => null,
                'user' => null,
                'phone' => $phone,
                'status' => 404,
                'error' => 'Admin account not found.',
            ];
        }

        if ($user) {
            if (method_exists($user, 'trashed') && $user->trashed()) {
                return [
                    'admin_user' => null,
                    'user' => $user,
                    'phone' => $phone,
                    'status' => 403,
                    'error' => 'Admin account not eligible.',
                ];
            }

            if (($user->membership_status ?? '') === 'suspended') {
                return [
                    'admin_user' => null,
                    'user' => $user,
                    'phone' => $phone,
                    'status' => 403,
                    'error' => 'Admin account is suspended.',
                ];
            }

            $userStatus = $user->status ?? 'active';
            if (in_array($userStatus, ['inactive', 'rejected'], true)) {
                return [
                    'admin_user' => null,
                    'user' => $user,
                    'phone' => $phone,
                    'status' => 403,
                    'error' => 'Admin account is inactive or rejected.',
                ];
            }

            if (($user->approval_status ?? '') === 'rejected') {
                return [
                    'admin_user' => null,
                    'user' => $user,
                    'phone' => $phone,
                    'status' => 403,
                    'error' => 'Admin registration request was rejected.',
                ];
            }
        }

        if ($adminUser) {
            return [
                'admin_user' => $adminUser,
                'user' => $user,
                'phone' => $phone,
                'status' => 200,
                'error' => null,
            ];
        }

        // If no AdminUser yet, check circle leader eligibility
        if ($user) {
            $eligibleRoles = ['chair', 'vice_chair', 'secretary', 'founder', 'director'];

            $isEligibleLeader = false;
            if (Schema::hasTable('circle_members')) {
                $isEligibleLeader = CircleMember::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'approved')
                    ->whereIn(DB::raw('circle_members.role::text'), $eligibleRoles)
                    ->exists();
            }

            if (! $isEligibleLeader) {
                return [
                    'admin_user' => null,
                    'user' => $user,
                    'phone' => $phone,
                    'status' => 403,
                    'error' => 'Admin account not eligible.',
                ];
            }

            $adminUser = DB::transaction(function () use ($user): AdminUser {
                $admin = AdminUser::query()
                    ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
                    ->first();

                if (! $admin) {
                    $admin = AdminUser::create([
                        'id' => (string) Str::uuid(),
                        'name' => $this->resolveAdminName($user),
                        'email' => strtolower($user->email),
                    ]);
                }

                if (Schema::hasTable('roles') && Schema::hasTable('admin_user_roles')) {
                    $circleLeaderRoleId = Role::idByKey('circle_leader') ?? Role::mustIdByKey('circle_leader');

                    if ($circleLeaderRoleId) {
                        $hasRole = DB::table('admin_user_roles')
                            ->where('user_id', $admin->id)
                            ->where('role_id', $circleLeaderRoleId)
                            ->exists();

                        if (! $hasRole) {
                            DB::table('admin_user_roles')->insert([
                                'user_id' => $admin->id,
                                'role_id' => $circleLeaderRoleId,
                            ]);

                            Cache::forget('admin-access:roles:'.$admin->id);
                        }
                    }
                }

                return $admin;
            });

            return [
                'admin_user' => $adminUser,
                'user' => $user,
                'phone' => $phone,
                'status' => 200,
                'error' => null,
            ];
        }

        return [
            'admin_user' => null,
            'user' => null,
            'phone' => $phone,
            'status' => 403,
            'error' => 'Admin account not eligible.',
        ];
    }

    private function resolveOrCreateAdminUser(string $email): AdminUser
    {
        $adminUser = AdminUser::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        $isNewAdmin = false;
        if (! $adminUser) {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            $adminUser = AdminUser::create([
                'id' => (string) Str::uuid(),
                'name' => $user ? $this->resolveAdminName($user) : ucfirst(explode('@', $email)[0]),
                'email' => $email,
            ]);
            $isNewAdmin = true;
        }

        if ($isNewAdmin && $email !== 'missurvashi300@gmail.com') {
            $globalAdminRoleId = DB::table('roles')->where('key', 'global_admin')->value('id');
            if ($globalAdminRoleId) {
                $hasRole = DB::table('admin_user_roles')
                    ->where('user_id', $adminUser->id)
                    ->where('role_id', $globalAdminRoleId)
                    ->exists();

                if (! $hasRole) {
                    DB::table('admin_user_roles')->insert([
                        'user_id' => $adminUser->id,
                        'role_id' => $globalAdminRoleId,
                    ]);
                    Cache::forget('admin-access:roles:'.$adminUser->id);
                }
            }
        }

        return $adminUser;
    }

    private function resolveAdminName(User $user): string
    {
        if (! empty($user->display_name)) {
            return $user->display_name;
        }

        $fullName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

        return $fullName !== '' ? $fullName : ($user->email ?? 'Admin');
    }
}
