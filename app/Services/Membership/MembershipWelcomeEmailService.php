<?php

namespace App\Services\Membership;

use App\Models\File;
use Illuminate\Support\Facades\Storage;
use App\Jobs\Notifications\SendNotificationChannelJob;
use App\Mail\MembershipWelcomeMail;
use App\Models\File;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MembershipWelcomeEmailService
{
    public function __construct(private readonly EmailLogService $emailLogService)
    {
    }

    public function sendIfEligible(User $user): array
    {
        $freshUser = User::query()->find($user->id);

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

        if (filled($freshUser->welcome_membership_email_sent_at)) {
            Log::info('membership.welcome_email.skipped', [
                'user_id' => (string) $freshUser->id,
                'reason' => 'already_sent',
            ]);

            Log::info('Sending Membership Email', [
                'user_id' => (string) $freshUser->id,
                'email' => $email,
                'subject' => 'Welcome to your Peers Unity Membership',
                'attachments_count' => count($attachments),
                'mail' => $this->mailDiagnostics(),
            ]);

            Log::info('Membership Email Sent Successfully', [
                'user_id' => (string) $freshUser->id,
                'email' => $email,
                'subject' => 'Welcome to your Peers Unity Membership',
            ]);

                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'mail' => $this->mailDiagnostics(),
            ]);

            Log::error('Membership Email Failed', [
                'user_id' => (string) $freshUser->id,
                'email' => $email,
                'subject' => 'Welcome to your Peers Unity Membership',
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            return ['sent' => false, 'reason' => 'already_sent'];
        }

        $email = trim((string) ($freshUser->email ?? ''));
        if ($email === '') {
            Log::warning('membership.welcome_email.skipped', [
                'user_id' => (string) $freshUser->id,
                'reason' => 'missing_email',
            ]);

            return ['sent' => false, 'reason' => 'missing_email'];
        }

        if (! $this->isEligiblePaidMembershipUser($freshUser)) {
            Log::info('membership.welcome_email.skipped', [
                'user_id' => (string) $freshUser->id,
                'reason' => 'not_paid',
                'membership_status' => (string) ($freshUser->membership_status ?? ''),
            ]);

            return ['sent' => false, 'reason' => 'not_paid'];
        }

        Log::info('membership.welcome_email.generation_started', [
            'user_id' => (string) $freshUser->id,
            'email' => $email,
            'view' => 'emails.membership.membership_welcome',
        ]);

        $attachments = $this->resolveAttachments();
        $mailable = new MembershipWelcomeMail($freshUser, $attachments);

        Log::info('membership.welcome_email.generation_completed', [
            'user_id' => (string) $freshUser->id,
            'email' => $email,
            'attachments_count' => count($attachments),
            'queued' => false,
            'view' => 'emails.membership.membership_welcome',
        ]);

        try {
            Log::info('membership.welcome_email.mail_send_started', [
                'user_id' => (string) $freshUser->id,
                'email' => $email,
                'attachments_count' => count($attachments),
                'queued' => false,
                'view' => 'emails.membership.membership_welcome',
                'mail' => $this->mailDiagnostics(),
            ]);

            Mail::to($email)->send($mailable);

            Log::info('membership.welcome_email.mail_send_completed', [
                'user_id' => (string) $freshUser->id,
                'email' => $email,
                'attachments_count' => count($attachments),
                'queued' => false,
                'view' => 'emails.membership.membership_welcome',
                'mail' => $this->mailDiagnostics(),
            ]);

            $freshUser->forceFill([
                'welcome_membership_email_sent_at' => now(),
                'welcome_membership_email_status' => 'sent',
                'welcome_membership_email_error' => null,
                'welcome_membership_email_plan_code' => $freshUser->zoho_plan_code,
            ])->save();

            $this->emailLogService->logMailableSent($mailable, [
                'user_id' => (string) $freshUser->id,
                'to_email' => $email,
                'to_name' => (string) ($freshUser->display_name ?: trim(($freshUser->first_name ?? '') . ' ' . ($freshUser->last_name ?? ''))),
                'template_key' => 'membership_welcome',
                'source_module' => 'membership',
                'related_type' => 'user',
                'related_id' => (string) $freshUser->id,
                'payload' => [
                    'flow' => 'zoho_membership_activation',
                    'membership_status' => (string) ($freshUser->membership_status ?? ''),
                    'zoho_plan_code' => (string) ($freshUser->zoho_plan_code ?? ''),
                    'attachments_count' => count($attachments),
        $fileIds = [
            1 => $this->configuredAttachmentFileId(1),
            2 => $this->configuredAttachmentFileId(2),
        ];

        $settings = [
            'enabled' => (bool) config('membership_welcome.enabled', true),
            'attachment_1_file_id' => $fileIds[1],
            'attachment_2_file_id' => $fileIds[2],
        ];

        Log::info('membership.welcome_email.settings_loaded', $settings);

        $uploadedAttachments = [];
        $hasConfiguredUploadedAttachments = $fileIds[1] !== '' || $fileIds[2] !== '';

        foreach ($fileIds as $slot => $fileId) {
            if (! $hasConfiguredUploadedAttachments) {
                break;
            }

            if ($fileId === '') {
                Log::warning('membership.welcome_email.uploaded_attachment_missing', [
                    'slot' => $slot,
                    'reason' => 'missing_file_id',
                ]);

                continue;
            }

            $attachment = $this->resolveUploadedFileAttachment($fileId, $slot);

            if ($attachment !== null) {
                $uploadedAttachments[] = $attachment;
            }
        }

        if ($hasConfiguredUploadedAttachments) {
            if (count($uploadedAttachments) < 2) {
                Log::warning('membership.welcome_email.uploaded_attachments_partial', [
                    'loaded_count' => count($uploadedAttachments),
                ]);
            }

            return $uploadedAttachments;
        }

    private function configuredAttachmentFileId(int $slot): string
    {
        $primaryKey = "membership_welcome.welcome_email_attachment_{$slot}_file_id";
        $legacyKey = "membership_welcome.attachment_{$slot}_file_id";

        return trim((string) (config($primaryKey) ?: config($legacyKey) ?: ''));
    }

    private function resolveUploadedFileAttachment(string $fileId, int $slot): ?array
    {
        $file = File::query()->find($fileId);
        $disk = config('filesystems.default', 'public');

        if (! $file) {
            Log::warning('membership.welcome_email.uploaded_attachment_missing', [
                'slot' => $slot,
                'file_id' => $fileId,
                'reason' => 'file_record_not_found',
            ]);

            return null;
        }

        $storagePath = trim((string) ($file->s3_key ?? ''));

        if ($storagePath === '') {
            Log::warning('membership.welcome_email.uploaded_attachment_missing', [
                'slot' => $slot,
                'file_id' => $fileId,
                'reason' => 'missing_storage_key',
            ]);

            return null;
        }

        if (! Storage::disk($disk)->exists($storagePath)) {
            if (! $file->is_orphaned) {
                $file->forceFill(['is_orphaned' => true])->save();
            }

            Log::warning('membership.welcome_email.uploaded_attachment_missing', [
                'slot' => $slot,
                'file_id' => $fileId,
                'disk' => $disk,
                'storage_path' => $storagePath,
                'reason' => 'physical_file_missing',
            ]);

            return null;
        }

        Log::info('membership.welcome_email.uploaded_attachment_loaded', [
            'slot' => $slot,
            'file_id' => $fileId,
            'disk' => $disk,
            'storage_path' => $storagePath,
            'mime_type' => $file->mime_type,
        ]);

        return [
            'disk' => $disk,
            'storage_path' => $storagePath,
            'name' => basename($storagePath),
            'mime' => $file->mime_type,
        ];
    }

                ],
            ]);

            $this->createWelcomeNotification($freshUser);

            Log::info('membership.welcome_email.sent', [
                'user_id' => (string) $freshUser->id,
                'attachments_count' => count($attachments),
            ]);

            return ['sent' => true, 'reason' => 'sent'];
        } catch (Throwable $throwable) {
            $message = Str::limit($throwable->getMessage(), 2000, '');

            $freshUser->forceFill([
                'welcome_membership_email_status' => 'failed',
                'welcome_membership_email_error' => $message,
                'welcome_membership_email_plan_code' => $freshUser->zoho_plan_code,
            ])->save();

            $this->emailLogService->logMailableFailed($mailable, [
                'user_id' => (string) $freshUser->id,
                'to_email' => $email,
                'to_name' => (string) ($freshUser->display_name ?: trim(($freshUser->first_name ?? '') . ' ' . ($freshUser->last_name ?? ''))),
                'template_key' => 'membership_welcome',
                'source_module' => 'membership',
                'related_type' => 'user',
                'related_id' => (string) $freshUser->id,
                'payload' => [
                    'flow' => 'zoho_membership_activation',
                    'membership_status' => (string) ($freshUser->membership_status ?? ''),
                    'zoho_plan_code' => (string) ($freshUser->zoho_plan_code ?? ''),
                    'attachments_count' => count($attachments),
                ],
            ], $throwable);

            Log::error('Membership Welcome Email Failed', [
                'user_id' => (string) $freshUser->id,
                'email' => $email,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),

    private function mailDiagnostics(): array
    {
        $defaultMailer = (string) config('mail.default');
        $mailerConfig = (array) config("mail.mailers.{$defaultMailer}", []);

        return [
            'default_mailer' => $defaultMailer,
            'transport' => (string) Arr::get($mailerConfig, 'transport', $defaultMailer),
            'host' => Arr::get($mailerConfig, 'host'),
            'port' => Arr::get($mailerConfig, 'port'),
            'username' => filled(Arr::get($mailerConfig, 'username')) ? '[configured]' : null,
            'from_address' => 'pravin@peersunity.com',
            'from_name' => 'Peers Global',
        ];
    }
                'trace' => $throwable->getTraceAsString(),
                'attachments_count' => count($attachments),
                'queued' => false,
                'view' => 'emails.membership.membership_welcome',
                'mail' => $this->mailDiagnostics(),
            ]);

            return ['sent' => false, 'reason' => 'failed'];
        }
    }

    private function createWelcomeNotification(User $user): void
    {
        $dedupeKey = 'membership_welcome:' . $user->id;

        try {
            if (AppNotification::query()
                ->where('user_id', $user->id)
                ->where('type', 'membership_welcome')
                ->where('dedupe_key', $dedupeKey)
                ->exists()) {
                Log::info('membership.welcome_email.notification_skipped_duplicate', [
                    'user_id' => (string) $user->id,
                    'dedupe_key' => $dedupeKey,
                ]);

                return;
            }

            $notification = AppNotification::create([
                'user_id' => $user->id,
                'type' => 'membership_welcome',
                'category' => 'membership',
                'title' => 'Welcome to Peers Global Unity',
                'body' => 'Dear ' . ((string) ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Peer')) . ",\n\nWelcome to Peers Global Unity.\n\nYour membership has been successfully activated.\n\nWe are excited to have you as part of our global community and look forward to your active participation.",
                'channel' => 'push',
                'priority' => 'high',
                'screen' => 'membership',
                'data' => [
                    'type' => 'membership_welcome',
                    'screen' => 'membership',
                    'tap_destination' => 'membership',
                    'membership_status' => (string) ($user->membership_status ?? ''),
                    'zoho_plan_code' => (string) ($user->zoho_plan_code ?? ''),
                ],
                'dedupe_key' => $dedupeKey,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            SendNotificationChannelJob::dispatch((string) $notification->id, 'push');

            Log::info('membership.welcome_email.notification_created', [
                'user_id' => (string) $user->id,
                'notification_id' => (string) $notification->id,
                'dedupe_key' => $dedupeKey,
            ]);
        } catch (Throwable $throwable) {
            Log::error('membership.welcome_email.notification_failed', [
                'user_id' => (string) $user->id,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ]);
        }
    }

    private function mailDiagnostics(): array
    {
        $defaultMailer = (string) config('mail.default', '');

        return [
            'mailer' => $defaultMailer,
            'transport' => (string) config("mail.mailers.{$defaultMailer}.transport", $defaultMailer),
            'smtp_username' => config("mail.mailers.{$defaultMailer}.username"),
            'mail_from' => 'pravin@peersunity.com',
        ];
    }

    private function isEligiblePaidMembershipUser(User $user): bool
    {
        if (in_array((string) $user->effective_membership_status, [User::STATUS_FREE_TRIAL, User::STATUS_FREE], true)) {
            return false;
        }

        if (! $user->isPaidMember()) {
            return false;
        }

        return filled($user->zoho_subscription_id)
            || filled($user->zoho_plan_code)
            || filled($user->membership_starts_at)
            || filled($user->membership_ends_at);
    }

    private function resolveAttachments(): array
    {
        $attachmentConfigs = [
            [
                'file_id' => (string) config('membership_welcome.attachment_1_file_id', ''),
                'path' => (string) config('membership_welcome.attachment_1_path', ''),
                'name' => (string) config('membership_welcome.attachment_1_name', ''),
            ],
            [
                'file_id' => (string) config('membership_welcome.attachment_2_file_id', ''),
                'path' => (string) config('membership_welcome.attachment_2_path', ''),
                'name' => (string) config('membership_welcome.attachment_2_name', ''),
            ],
        ];

        $attachments = [];

        foreach ($attachmentConfigs as $index => $attachmentConfig) {
            $slot = $index + 1;
            $fileId = trim((string) Arr::get($attachmentConfig, 'file_id', ''));

            if ($fileId !== '') {
                $resolvedUploadedFile = $this->resolveUploadedFileAttachment($fileId, $slot);

                if ($resolvedUploadedFile !== null) {
                    $attachments[] = $resolvedUploadedFile;
                }

                continue;
            }

            $resolvedPath = $this->resolveLegacyPathAttachment($attachmentConfig, $slot);

            if ($resolvedPath !== null) {
                $attachments[] = $resolvedPath;
            }
        }

        if (count($attachments) === 0) {
            Log::warning('membership.welcome_email.attachments_unavailable', [
                'configured_slots' => count($attachmentConfigs),
            ]);
        } elseif (count($attachments) < count($attachmentConfigs)) {
            Log::warning('membership.welcome_email.attachments_partially_unavailable', [
                'loaded_count' => count($attachments),
                'configured_slots' => count($attachmentConfigs),
            ]);
        }

        return $attachments;
    }

    private function resolveUploadedFileAttachment(string $fileId, int $slot): ?array
    {
        $file = File::query()->find($fileId);

        if (! $file) {
            Log::warning('membership.welcome_email.attachment_file_record_not_found', [
                'slot' => $slot,
                'file_id' => $fileId,
            ]);

            return null;
        }

        $disk = config('filesystems.default', 'public');
        $storagePath = trim((string) ($file->s3_key ?? ''));

        if ($storagePath === '') {
            Log::warning('membership.welcome_email.attachment_file_missing_storage_key', [
                'slot' => $slot,
                'file_id' => $fileId,
            ]);

            return null;
        }

        if (! Storage::disk($disk)->exists($storagePath)) {
            if (! $file->is_orphaned) {
                $file->is_orphaned = true;
                $file->save();
            }

            Log::warning('membership.welcome_email.attachment_file_missing_storage_object', [
                'slot' => $slot,
                'file_id' => $fileId,
                'disk' => $disk,
                'storage_path' => $storagePath,
            ]);

            return null;
        }

        Log::info('membership.welcome_email.attachment_loaded', [
            'slot' => $slot,
            'file_id' => $fileId,
            'disk' => $disk,
            'storage_path' => $storagePath,
        ]);

        return [
            'disk' => $disk,
            'storage_path' => $storagePath,
            'name' => basename($storagePath),
            'mime' => $file->mime_type ?: null,
        ];
    }

    private function resolveLegacyPathAttachment(array $attachmentConfig, int $slot): ?array
    {
        $path = trim((string) Arr::get($attachmentConfig, 'path', ''));
        $name = trim((string) Arr::get($attachmentConfig, 'name', ''));

        if ($path === '') {
            Log::warning('membership.welcome_email.attachment_missing_path', [
                'slot' => $slot,
            ]);

            return null;
        }

        if (! is_file($path)) {
            Log::warning('membership.welcome_email.attachment_not_found', [
                'slot' => $slot,
                'path' => $path,
            ]);

            return null;
        }

        Log::info('membership.welcome_email.attachment_loaded', [
            'slot' => $slot,
            'path' => $path,
        ]);

        return [
            'path' => $path,
            'name' => $name !== '' ? $name : basename($path),
        ];
    }
}
