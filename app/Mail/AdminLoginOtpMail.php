<?php

namespace App\Mail;

use App\Models\AdminUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminLoginOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public AdminUser $adminUser,
        public string $subjectLine = 'Your Admin Login OTP',
        public string $purpose = 'admin_login_otp'
    ) {
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('emails.auth.admin_login_otp')
            ->with([
                'otp' => $this->otp,
                'adminUser' => $this->adminUser,
                'purpose' => $this->purpose,
                'subjectLine' => $this->subjectLine,
            ]);
    }
}
