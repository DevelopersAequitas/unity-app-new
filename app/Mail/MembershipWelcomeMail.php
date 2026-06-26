<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MembershipWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    /**
     * @var array<int, array{disk:string,path:string,name:string,mime?:string|null}>
     */
    public array $attachmentsConfig;

    public ?string $bannerUrl;

    /**
     * @param  array<int, array{disk:string,path:string,name:string,mime?:string|null}>  $attachmentsConfig
     */
    public function __construct(User $user, array $attachmentsConfig = [], ?string $bannerUrl = null)
    {
        $this->user = $user;
        $this->attachmentsConfig = $attachmentsConfig;
        $this->bannerUrl = $bannerUrl;
    }

    public function build()
    {
        $mail = $this->subject('Welcome to your Peers Unity Membership')
            ->from(config('mail.membership_from.address', 'support@peersglobal.com'), config('mail.membership_from.name', 'Peers Global Unity'))
            ->view('emails.membership.membership_welcome')
            ->with([
                'user' => $this->user,
                'bannerUrl' => $this->bannerUrl,
                'attachmentLinks' => collect($this->attachmentsConfig)->filter(fn ($attachment) => ! empty($attachment['url']))->values()->all(),
            ]);

        foreach ($this->attachmentsConfig as $attachment) {
            if (empty($attachment['disk']) || empty($attachment['path'])) {
                continue;
            }

            try {
                $mail->attachFromStorageDisk($attachment['disk'], $attachment['path'], $attachment['name'], [
                    'mime' => $attachment['mime'] ?? null,
                ]);

                Log::info('membership.welcome_mail.attachment_added', [
                    'file_id' => $attachment['id'] ?? null,
                    'url' => $attachment['url'] ?? null,
                    's3_key' => $attachment['s3_key'] ?? $attachment['path'],
                    'disk' => $attachment['disk'],
                    'path' => $attachment['path'],
                    'resolved_path' => $attachment['resolved_path'] ?? null,
                    'storage_exists' => $attachment['storage_exists'] ?? null,
                    'is_readable' => $attachment['is_readable'] ?? null,
                    'name' => $attachment['name'],
                ]);
            } catch (\Throwable $throwable) {
                Log::warning('membership.welcome_mail.attachment_failed', [
                    'file_id' => $attachment['id'] ?? null,
                    'url' => $attachment['url'] ?? null,
                    's3_key' => $attachment['s3_key'] ?? $attachment['path'] ?? null,
                    'disk' => $attachment['disk'] ?? null,
                    'path' => $attachment['path'] ?? null,
                    'resolved_path' => $attachment['resolved_path'] ?? null,
                    'storage_exists' => $attachment['storage_exists'] ?? null,
                    'is_readable' => $attachment['is_readable'] ?? null,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        return $mail;
    }
}
