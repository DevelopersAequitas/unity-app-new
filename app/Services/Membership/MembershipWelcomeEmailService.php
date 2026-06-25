<?php

namespace App\Services\Membership;

use App\Mail\MembershipWelcomeMail;
use App\Models\FileModel;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MembershipWelcomeEmailService
{
    public function __construct(private readonly EmailLogService $emailLogService, private readonly MembershipNotificationService $notifications)
    {
    }

    public function sendIfEligible(User $user, bool $force = false, string $flow = 'membership_purchase'): array
    {
        $freshUser = User::query()->find($user->id);
        if (! $freshUser) return ['sent' => false, 'reason' => 'user_not_found'];
        if (! config('membership_welcome.enabled', true)) return ['sent' => false, 'reason' => 'disabled'];
        if (! $force && filled($freshUser->welcome_membership_email_sent_at)) return ['sent' => false, 'reason' => 'already_sent'];
        $email = trim((string) ($freshUser->email ?? ''));
        if ($email === '') return ['sent' => false, 'reason' => 'missing_email'];
        if (! $this->isEligiblePaidMembershipUser($freshUser) && ! $force) return ['sent' => false, 'reason' => 'not_paid'];

        $attachments = $this->resolveAttachments();
        $mailable = new MembershipWelcomeMail($freshUser, $attachments, $this->resolveBannerUrl());

        try {
            Mail::to($email)->send($mailable);
            $freshUser->forceFill([
                'welcome_membership_email_sent_at' => now(),
                'welcome_membership_email_status' => 'sent',
                'welcome_membership_email_error' => null,
                'welcome_membership_email_plan_code' => $freshUser->zoho_plan_code,
            ])->save();
            $this->emailLogService->logMailableSent($mailable, [
                'user_id' => (string) $freshUser->id,
                'to_email' => $email,
                'to_name' => $freshUser->display_name,
                'template_key' => 'membership_welcome',
                'source_module' => 'membership',
                'related_type' => 'user',
                'related_id' => (string) $freshUser->id,
                'payload' => ['flow' => $flow, 'membership_status' => $freshUser->membership_status],
            ]);
            $this->notifications->sendFirstPurchase($freshUser, $flow);
            return ['sent' => true, 'reason' => 'sent'];
        } catch (Throwable $throwable) {
            $message = Str::limit($throwable->getMessage(), 2000, '');

            $freshUser->forceFill([
                'welcome_membership_email_sent_at' => null,
                'welcome_membership_email_status' => 'failed',
                'welcome_membership_email_error' => $message,
                'welcome_membership_email_plan_code' => $freshUser->zoho_plan_code,
            ])->save();
            $this->emailLogService->logMailableFailed($mailable, [
                'user_id' => (string) $freshUser->id,
                'to_email' => $email,
                'template_key' => 'membership_welcome',
                'source_module' => 'membership',
            ], $throwable);
            Log::warning('membership.welcome_email.failed', [
                'user_id' => $freshUser->id,
                'error' => $throwable->getMessage(),
                'from_address' => config('mail.from.address'),
                'smtp_username' => config('mail.mailers.smtp.username'),
            ]);
            return ['sent' => false, 'reason' => 'failed'];
        }
    }

    private function isEligiblePaidMembershipUser(User $user): bool
    {
        return filled($user->last_payment_at) || ! in_array((string) $user->membership_status, ['visitor', 'free_peer', 'free_trial_peer', ''], true);
    }

    private function resolveBannerUrl(): ?string
    {
        $url = trim((string) config('membership_welcome.banner_url', ''));
        if ($url !== '') return $url;
        $fileId = trim((string) config('membership_welcome.banner_file_id', ''));
        return $fileId !== '' ? url('/api/v1/files/' . $fileId) : null;
    }

    private function resolveAttachments(): array
    {
        $attachments = [];
        foreach ([1, 2] as $slot) {
            $fileId = trim((string) $this->settingValue(
                "welcome_email_attachment_{$slot}_file_id",
                config("membership_welcome.attachment_{$slot}_file_id", '')
            ));

            if ($fileId === '') {
                Log::warning('membership.welcome_email.attachment_missing', ['slot' => $slot, 'reason' => 'not_configured']);
                continue;
            }

            $file = FileModel::query()->find($fileId);
            if (! $file || blank($file->s3_key)) {
                Log::warning('membership.welcome_email.attachment_missing', ['slot' => $slot, 'file_id' => $fileId, 'reason' => 'file_record_missing']);
                continue;
            }

            $disk = config('filesystems.default', 'public');
            if (! Storage::disk($disk)->exists($file->s3_key)) {
                Log::warning('membership.welcome_email.attachment_missing', ['slot' => $slot, 'file_id' => $fileId, 'reason' => 'storage_file_missing']);
                continue;
            }

            $attachments[] = [
                'disk' => $disk,
                'path' => $file->s3_key,
                'name' => (string) config("membership_welcome.attachment_{$slot}_name", basename($file->s3_key)),
            ];
        }

        if ($attachments === []) {
            Log::warning('membership.welcome_email.attachments_empty');
        }

        return $attachments;
    }

    private function settingValue(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('app_config_settings') || ! Schema::hasColumn('app_config_settings', $key)) {
            return $default;
        }

        try {
            $value = DB::table('app_config_settings')
                ->where('is_active', true)
                ->latest('updated_at')
                ->value($key);
        } catch (Throwable $throwable) {
            Log::warning('membership.welcome_email.setting_lookup_failed', [
                'key' => $key,
                'message' => $throwable->getMessage(),
            ]);

            return $default;
        }

        return filled($value) ? $value : $default;
    }
}
