<?php

namespace App\Services\Membership;

use App\Mail\MembershipWelcomeMail;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Membership email skipped: invalid recipient email for user ' . $freshUser->id;
            Log::warning($message, [
                'type' => 'membership_welcome',
                'user_id' => (string) $freshUser->id,
                'to' => $email,
            ]);

            $freshUser->forceFill([
                'welcome_membership_email_sent_at' => null,
                'welcome_membership_email_status' => 'failed',
                'welcome_membership_email_error' => 'Invalid recipient email: ' . $email,
                'welcome_membership_email_plan_code' => $freshUser->zoho_plan_code,
            ])->save();

            return ['sent' => false, 'reason' => 'invalid_email'];
        }

        if (! $this->isEligiblePaidMembershipUser($freshUser)) {
            Log::info('membership.welcome_email.skipped', [
                'user_id' => (string) $freshUser->id,
                'reason' => 'not_paid',
                'membership_status' => (string) ($freshUser->membership_status ?? ''),
            ]);

            return ['sent' => false, 'reason' => 'not_paid'];
        }

        $attachments = $this->resolveAttachments();
        $mailable = new MembershipWelcomeMail($freshUser, $attachments);

        try {
            Log::info('Membership email sending started', [
                'type' => 'membership_welcome',
                'user_id' => (string) $freshUser->id,
                'to' => $email,
                'from' => (string) config('peers.membership_welcome_from_email'),
                'mail_host' => (string) config('mail.mailers.smtp.host'),
                'mail_username' => (string) config('mail.mailers.smtp.username'),
                'mail_port' => (string) config('mail.mailers.smtp.port'),
                'mailer' => (string) config('mail.default'),
                'queue_connection' => (string) config('queue.default'),
                'subject' => 'Welcome to Peers Global Unity',
                'attachments_count' => count($attachments),
            ]);

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
            ]);

            Log::info('Membership email sent successfully', [
                'type' => 'membership_welcome',
                'user_id' => (string) $freshUser->id,
                'to' => $email,
                'from' => (string) config('peers.membership_welcome_from_email'),
                'attachments_count' => count($attachments),
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

            Log::warning('Membership email failed', [
                'type' => 'membership_welcome',
                'user_id' => (string) $freshUser->id,
                'to' => $email,
                'from' => (string) config('peers.membership_welcome_from_email'),
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            return ['sent' => false, 'reason' => 'failed'];
        }
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
                'path' => (string) (config('peers.membership_welcome_attachment_path_1') ?: config('membership_welcome.attachment_1_path', '')),
                'name' => (string) config('membership_welcome.attachment_1_name', ''),
            ],
            [
                'path' => (string) (config('peers.membership_welcome_attachment_path_2') ?: config('membership_welcome.attachment_2_path', '')),
                'name' => (string) config('membership_welcome.attachment_2_name', ''),
            ],
        ];

        $attachments = [];
        $attachedPaths = [];

        foreach ($attachmentConfigs as $index => $attachmentConfig) {
            $path = trim((string) Arr::get($attachmentConfig, 'path', ''));
            $name = trim((string) Arr::get($attachmentConfig, 'name', ''));

            if ($path === '') {
                Log::warning('membership.welcome_email.attachment_missing_path', [
                    'slot' => $index + 1,
                ]);

                continue;
            }

            $resolvedPath = $this->resolveAttachmentPath($path);

            if (! is_file($resolvedPath)) {
                Log::warning('membership.welcome_email.attachment_not_found', [
                    'slot' => $index + 1,
                    'path' => $path,
                    'resolved_path' => $resolvedPath,
                ]);

                continue;
            }

            $dedupeKey = realpath($resolvedPath) ?: $resolvedPath;
            if (isset($attachedPaths[$dedupeKey])) {
                Log::warning('membership.welcome_email.duplicate_attachment_skipped', [
                    'slot' => $index + 1,
                    'path' => $path,
                    'resolved_path' => $resolvedPath,
                ]);

                continue;
            }

            $attachedPaths[$dedupeKey] = true;

            $attachments[] = [
                'path' => $resolvedPath,
                'name' => $name !== '' ? $name : basename($resolvedPath),
            ];
        }

        if ($attachments === []) {
            Log::warning('membership.welcome_email.attachments_unavailable', [
                'message' => 'No configured membership welcome attachments were found; email will be sent without attachments.',
            ]);
        }

        return $attachments;
    }

    private function resolveAttachmentPath(string $path): string
    {
        if (Str::startsWith($path, ['/'])) {
            return $path;
        }

        return base_path($path);
    }
}
