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

    public function sendIfEligible(User $user, bool $force = false, string $flow = 'membership_purchase', array $uploadedAttachments = []): array
    {
        $freshUser = User::query()->find($user->id);
        if (! $freshUser) return ['sent' => false, 'reason' => 'user_not_found'];
        if (! config('membership_welcome.enabled', true)) return ['sent' => false, 'reason' => 'disabled'];
        if (! $force && filled($freshUser->welcome_membership_email_sent_at)) return ['sent' => false, 'reason' => 'already_sent'];

        if (! $freshUser) {
            Log::warning('membership.welcome_email.user_not_found', [
                'user_id' => (string) $user->id,
            ]);

            return ['sent' => false, 'reason' => 'user_not_found'];
        }

        Log::info('membership.welcome_email.check_started', [
            'user_id' => (string) $freshUser->id,
            'membership_status' => (string) ($freshUser->membership_status ?? ''),
            'zoho_plan_code' => (string) ($freshUser->zoho_plan_code ?? ''),
        ]);

        if (! config('membership_welcome.enabled', true)) {
            Log::info('membership.welcome_email.skipped', [
                'user_id' => (string) $freshUser->id,
                'reason' => 'disabled',
            ]);

            return ['sent' => false, 'reason' => 'disabled'];
        }

        $lastPayment = $freshUser->last_payment_at;
        if (filled($freshUser->welcome_membership_email_sent_at)) {
            Log::info('membership.welcome_email.skipped', [
                'user_id' => (string) $freshUser->id,
                'reason' => 'already_sent',
            ]);

            return ['sent' => false, 'reason' => 'already_sent'];
        }

        $email = trim((string) ($freshUser->email ?? ''));
        if ($email === '') return ['sent' => false, 'reason' => 'missing_email'];
        if (! $this->isEligiblePaidMembershipUser($freshUser) && ! $force) return ['sent' => false, 'reason' => 'not_paid'];

        $attachments = $this->resolveAttachments($uploadedAttachments);
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
                'payload' => ['flow' => $flow, 'membership_status' => $freshUser->membership_status, 'membership_expiry' => optional($freshUser->membership_ends_at ?? $freshUser->membership_expiry)->toDateString(), 'attachments' => $this->publicAttachmentPayload($attachments)],
            ]);
            $this->notifications->recordEmailSent($freshUser, 'membership_welcome_email_sent', $email, $flow);
            $this->notifications->sendMembershipWelcome($freshUser, $flow, $this->publicAttachmentPayload($attachments));
            Log::info('membership.welcome_email.sent', [
                'user_id' => (string) $freshUser->id,
                'to_email' => $email,
                'from_address' => config('mail.membership_from.address', 'support@peersglobal.com'),
            ]);
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
                'from_address' => config('mail.membership_from.address', 'support@peersglobal.com'),
                'smtp_username' => config('mail.mailers.smtp.username'),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
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

    private function resolveAttachments(array $uploadedAttachments = []): array
    {
        $attachments = $this->normalizeUploadedAttachments($uploadedAttachments);

        Log::info('membership.welcome_email.settings_loaded', [
            'enabled' => (bool) config('membership_welcome.enabled', true),
            'attachment_slots' => 2,
        ]);

        foreach ([1, 2] as $slot) {
            $fileId = trim((string) $this->settingValue(
                "welcome_email_attachment_{$slot}_file_id",
                config("membership_welcome.attachment_{$slot}_file_id", '')
            ));

            Log::info('membership.welcome_email.attachment_setting_loaded', [
                'slot' => $slot,
                'file_id_configured' => $fileId !== '',
            ]);

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
                'mime' => $file->mime_type ?: null,
            ];

            Log::info('membership.welcome_email.attachment_attached', [
                'slot' => $slot,
                'file_id' => $fileId,
                'disk' => $disk,
                'path' => $file->s3_key,
            ]);
        }

        if ($attachments === []) {
            Log::warning('membership.welcome_email.attachments_empty');
        }

        return $attachments;
    }


    private function normalizeUploadedAttachments(array $uploadedAttachments): array
    {
        return collect($uploadedAttachments)
            ->filter(fn ($attachment): bool => is_array($attachment) && filled($attachment['id'] ?? null) && filled($attachment['url'] ?? null))
            ->map(fn (array $attachment): array => $this->resolveUploadedAttachment($attachment))
            ->values()
            ->all();
    }

    private function resolveUploadedAttachment(array $attachment): array
    {
        $fileId = (string) $attachment['id'];
        $url = (string) $attachment['url'];
        $file = FileModel::query()->find($fileId);
        $disk = (string) ($attachment['disk'] ?? config('filesystems.default', 'public'));
        $s3Key = (string) ($attachment['s3_key'] ?? $file?->s3_key ?? '');
        $name = (string) ($attachment['original_name'] ?? $attachment['name'] ?? ($s3Key !== '' ? basename($s3Key) : basename(parse_url($url, PHP_URL_PATH) ?: 'membership-document.pdf')));
        $mime = $attachment['mime_type'] ?? $attachment['mime'] ?? $file?->mime_type ?? null;
        $exists = false;
        $resolvedPath = null;
        $isReadable = false;

        try {
            if ($s3Key !== '') {
                $exists = Storage::disk($disk)->exists($s3Key);
                try {
                    $resolvedPath = Storage::disk($disk)->path($s3Key);
                    $isReadable = is_string($resolvedPath) && is_readable($resolvedPath);
                } catch (Throwable) {
                    $resolvedPath = null;
                    $isReadable = $exists;
                }
            }
        } catch (Throwable $throwable) {
            Log::warning('membership.welcome_email.uploaded_attachment_lookup_failed', [
                'file_id' => $fileId,
                'url' => $url,
                's3_key' => $s3Key,
                'disk' => $disk,
                'error' => $throwable->getMessage(),
            ]);
        }

        Log::info('membership.welcome_email.uploaded_attachment_resolved', [
            'file_id' => $fileId,
            'url' => $url,
            's3_key' => $s3Key,
            'disk' => $disk,
            'resolved_path' => $resolvedPath,
            'storage_exists' => $exists,
            'is_readable' => $isReadable,
            'will_attach_from_storage' => $s3Key !== '' && $exists,
        ]);

        return array_filter([
            'id' => $fileId,
            'url' => $url,
            'disk' => $s3Key !== '' && $exists ? $disk : null,
            'path' => $s3Key !== '' && $exists ? $s3Key : null,
            's3_key' => $s3Key !== '' ? $s3Key : null,
            'mime' => $mime,
            'name' => $name,
            'resolved_path' => $resolvedPath,
            'storage_exists' => $exists,
            'is_readable' => $isReadable,
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    private function publicAttachmentPayload(array $attachments): array
    {
        return collect($attachments)
            ->filter(fn (array $attachment): bool => filled($attachment['url'] ?? null) || filled($attachment['id'] ?? null))
            ->map(fn (array $attachment): array => array_filter([
                'id' => $attachment['id'] ?? null,
                'url' => $attachment['url'] ?? null,
                'mime_type' => $attachment['mime'] ?? null,
                'original_name' => $attachment['name'] ?? null,
                's3_key' => $attachment['s3_key'] ?? $attachment['path'] ?? null,
            ], fn ($value): bool => $value !== null && $value !== ''))
            ->values()
            ->all();
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
