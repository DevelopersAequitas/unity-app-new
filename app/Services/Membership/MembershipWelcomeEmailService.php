<?php

namespace App\Services\Membership;

use App\Mail\MembershipWelcomeMail;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
                'welcome_membership_email_status' => 'Sent',
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
            $freshUser->forceFill([
                'welcome_membership_email_status' => 'failed',
                'welcome_membership_email_error' => Str::limit($throwable->getMessage(), 2000, ''),
                'welcome_membership_email_plan_code' => $freshUser->zoho_plan_code,
            ])->save();
            $this->emailLogService->logMailableFailed($mailable, [
                'user_id' => (string) $freshUser->id,
                'to_email' => $email,
                'template_key' => 'membership_welcome',
                'source_module' => 'membership',
            ], $throwable);
            Log::warning('membership.welcome_email.failed', ['user_id' => $freshUser->id, 'error' => $throwable->getMessage()]);
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
            $path = trim((string) config("membership_welcome.attachment_{$slot}_path", ''));
            $name = trim((string) config("membership_welcome.attachment_{$slot}_name", ''));
            if ($path !== '' && is_file($path)) $attachments[] = ['path' => $path, 'name' => $name !== '' ? $name : basename($path)];
        }
        return $attachments;
    }
}
