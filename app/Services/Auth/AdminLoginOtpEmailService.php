<?php

namespace App\Services\Auth;

use App\Mail\AdminLoginOtpMail;
use App\Models\AdminUser;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AdminLoginOtpEmailService
{
    public function __construct(private readonly EmailLogService $emailLogService)
    {
    }

    /**
     * Send an admin login OTP and write the matching email log entry.
     *
     * @throws Throwable
     */
    public function send(AdminUser $adminUser, string $otp, string $subject = 'Your Admin Login OTP', string $purpose = 'admin_login_otp'): void
    {
        $mailable = new AdminLoginOtpMail($otp, $adminUser, $subject, $purpose);
        $logContext = $this->logContext($adminUser, $subject, $purpose);

        try {
            Mail::to($adminUser->email)->send($mailable);
            $this->emailLogService->logMailableSent($mailable, $logContext);
        } catch (Throwable $exception) {
            $this->emailLogService->logMailableFailed($mailable, $logContext, $exception);

            throw $exception;
        }
    }

    private function logContext(AdminUser $adminUser, string $subject, string $purpose): array
    {
        return [
            'user_id' => null,
            'to_email' => (string) $adminUser->email,
            'to_name' => (string) ($adminUser->name ?: $adminUser->email),
            'template_key' => $purpose,
            'subject' => $subject,
            'source_module' => 'AdminAuth',
            'related_type' => AdminUser::class,
            'related_id' => (string) $adminUser->id,
            'source_type' => AdminUser::class,
            'source_id' => (string) $adminUser->id,
            'source_event' => $purpose,
            'provider' => config('mail.default'),
            'message_id' => null,
            'payload' => [
                'purpose' => $purpose,
            ],
        ];
    }
}
