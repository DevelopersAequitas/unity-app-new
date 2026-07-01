<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoginOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;

    public User $user;

    public function __construct(string $otp, User $user)
    {
        $this->otp = $otp;
        $this->user = $user;
    }

    public function build()
    {
        return $this->subject('Your Peers Global Unity login OTP')
            ->view('emails.auth.login_otp')
            ->with([
                'otp' => $this->otp,
                'user' => $this->user,
            ]);
    }
}
