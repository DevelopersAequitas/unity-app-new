<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminLoginOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;

    public string $name;

    public string $subjectLine;

    public function __construct(string $otp, string $name, string $subjectLine = 'Your Admin Login OTP')
    {
        $this->otp = $otp;
        $this->name = $name;
        $this->subjectLine = $subjectLine;
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('emails.auth.admin_login_otp')
            ->with([
                'otp' => $this->otp,
                'name' => $this->name,
            ]);
    }
}
