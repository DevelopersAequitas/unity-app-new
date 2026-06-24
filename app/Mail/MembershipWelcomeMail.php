<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MembershipWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    private const PUBLIC_ATTACHMENTS = [
        'mail-attachments/dummy-pdf_2.pdf',
        'mail-attachments/dummy-pdf_2_1.pdf',
    ];

    public User $user;

    /**
     * @var array<int, array{path:string,name:string}>
     */
    public array $attachmentsConfig;

    /**
     * @param  array<int, array{path:string,name:string}>  $attachmentsConfig
     */
    public function __construct(User $user, array $attachmentsConfig = [])
    {
        $this->user = $user;
        $this->attachmentsConfig = $attachmentsConfig;
    }

    private function senderAddress(): string
    {
        $address = trim((string) config('mail.from.address'));

        return $address !== '' ? $address : 'pravin@peersunity.com';
    }

    private function senderName(): string
    {
        $name = trim((string) config('mail.from.name'));

        return $name !== '' ? $name : 'Peers Global';
    }

    public function build()
    {
        $mail = $this->from($this->senderAddress(), $this->senderName())
            ->subject('Welcome to your Peers Unity Membership')
            ->view('emails.membership.membership_welcome')
            ->with([
                'user' => $this->user,
            ]);

        foreach (self::PUBLIC_ATTACHMENTS as $file) {
            $path = Storage::disk('public')->path($file);

            if (! Storage::disk('public')->exists($file)) {
                Log::warning('Membership email attachment missing', [
                    'email_type' => 'membership_welcome',
                    'user_id' => (string) $this->user->id,
                    'file' => $file,
                    'full_path' => $path,
                ]);

                continue;
            }

            $mail->attach($path, [
                'as' => basename($file),
                'mime' => 'application/pdf',
            ]);
        }

        foreach ($this->attachmentsConfig as $attachment) {
            $mail->attach($attachment['path'], [
                'as' => $attachment['name'],
            ]);
        }

        return $mail;
    }
}
