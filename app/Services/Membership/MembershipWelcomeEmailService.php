<?php

namespace App\Services\Membership;

use App\Mail\MembershipWelcomeMail;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserMembership;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
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

        $eligibility = $this->welcomeEmailEligibility($freshUser);
        if (! $eligibility['eligible']) {
            Log::info('membership.welcome_email.skipped', [
                'user_id' => (string) $freshUser->id,
                'reason' => $eligibility['reason'],
                'membership_status' => (string) ($freshUser->membership_status ?? ''),
                'approval_status' => (string) ($freshUser->approval_status ?? ''),
                'checks' => $eligibility['checks'],
            ]);

            return ['sent' => false, 'reason' => $eligibility['reason']];
        }

        $attachments = $this->resolveAttachments();
        $fromAddress = $this->senderAddress();
        $mailable = new MembershipWelcomeMail($freshUser, $attachments);

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
                    'from_address' => $fromAddress,
                ],
            ]);

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
                    'from_address' => $fromAddress,
                ],
            ], $throwable);

            $isUnauthorizedSender = $this->isUnauthorizedSenderError($message);

            Log::warning('membership.welcome_email.failed', [
                'user_id' => (string) $freshUser->id,
                'email_type' => 'membership_welcome',
                'from_address' => $fromAddress,
                'to_address' => $email,
                'smtp_error_message' => $throwable->getMessage(),
                'reason' => $isUnauthorizedSender ? 'sender_unauthorized' : 'failed',
            ]);

            return ['sent' => false, 'reason' => $isUnauthorizedSender ? 'sender_unauthorized' : 'failed'];
        }
    }

    private function senderAddress(): string
    {
        $address = trim((string) config('mail.from.address'));

        return $address !== '' ? $address : 'pravin@peersunity.com';
    }

    private function isUnauthorizedSenderError(string $message): bool
    {
        $normalized = strtolower($message);

        return str_contains($normalized, '553')
            || str_contains($normalized, 'sender is not allowed to relay')
            || str_contains($normalized, 'not allowed to relay')
            || str_contains($normalized, 'sender email is not authorized');
    }

    /**
     * @return array{eligible:bool,reason:string,checks:array<string,bool>}
     */
    private function welcomeEmailEligibility(User $user): array
    {
        $isUnityPeer = (string) ($user->membership_status ?? '') === 'only_unity_peer';

        if (! $isUnityPeer) {
            return [
                'eligible' => false,
                'reason' => 'not_unity_peer',
                'checks' => ['is_unity_peer' => false],
            ];
        }

        $checks = [
            'is_unity_peer' => true,
            'has_successful_payment' => $this->hasSuccessfulMembershipPayment($user),
            'has_active_membership_record' => $this->hasActiveMembershipRecord($user),
            'approval_status_approved' => in_array(strtolower((string) ($user->approval_status ?? '')), ['approved', 'active'], true),
            'membership_start_exists' => filled($user->membership_starts_at) || filled($user->membership_start_date),
            'membership_expiry_exists' => filled($user->membership_ends_at) || filled($user->membership_end_date) || filled($user->membership_expiry),
        ];

        $hasActiveMembershipSignal = $checks['has_successful_payment']
            || $checks['has_active_membership_record']
            || $checks['approval_status_approved']
            || $checks['membership_start_exists']
            || $checks['membership_expiry_exists'];

        return [
            'eligible' => $hasActiveMembershipSignal,
            'reason' => $hasActiveMembershipSignal ? 'eligible' : 'membership_inactive',
            'checks' => $checks,
        ];
    }

    private function hasSuccessfulMembershipPayment(User $user): bool
    {
        if (! Schema::hasTable('payments')) {
            return false;
        }

        return Payment::query()
            ->where('user_id', $user->id)
            ->where(function ($query): void {
                $query->whereIn('status', ['success', 'paid', 'completed', 'captured', 'payment_success'])
                    ->orWhereNotNull('paid_at');
            })
            ->exists();
    }

    private function hasActiveMembershipRecord(User $user): bool
    {
        if (! Schema::hasTable('user_memberships')) {
            return false;
        }

        return UserMembership::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'approved'])
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->exists();
    }

    private function resolveAttachments(): array
    {
        $attachmentConfigs = [
            [
                'path' => (string) config('membership_welcome.attachment_1_path', ''),
                'name' => (string) config('membership_welcome.attachment_1_name', ''),
            ],
            [
                'path' => (string) config('membership_welcome.attachment_2_path', ''),
                'name' => (string) config('membership_welcome.attachment_2_name', ''),
            ],
        ];

        $attachments = [];

        foreach ($attachmentConfigs as $index => $attachmentConfig) {
            $path = trim((string) Arr::get($attachmentConfig, 'path', ''));
            $name = trim((string) Arr::get($attachmentConfig, 'name', ''));

            if ($path === '') {
                Log::warning('membership.welcome_email.attachment_missing_path', [
                    'slot' => $index + 1,
                ]);

                continue;
            }

            if (! is_file($path)) {
                Log::warning('membership.welcome_email.attachment_not_found', [
                    'slot' => $index + 1,
                    'path' => $path,
                ]);

                continue;
            }

            $attachments[] = [
                'path' => $path,
                'name' => $name !== '' ? $name : basename($path),
            ];
        }

        return $attachments;
    }
}
