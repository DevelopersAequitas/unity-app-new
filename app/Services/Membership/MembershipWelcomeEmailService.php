<?php

namespace App\Services\Membership;

use App\Mail\MembershipWelcomeMail;
use App\Models\User;
use App\Models\UserPushToken;
use App\Services\EmailLogs\EmailLogService;
use App\Services\Firebase\FcmService as FirebaseFcmService;
use App\Services\Notifications\NotificationService;
use App\Support\Membership\MembershipStatusLabels;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MembershipWelcomeEmailService
{
    private const NOTIFICATION_TITLE = 'Welcome to Peers Global Unity';
    private const NOTIFICATION_BODY = 'Your membership has been activated successfully. Welcome to Peers Global Unity.';
    private const NOTIFICATION_TYPE = 'membership_welcome';

    public function __construct(
        private readonly EmailLogService $emailLogService,
        private readonly NotificationService $notificationService,
        private readonly FirebaseFcmService $firebaseFcmService,
    ) {
    }

    public function sendAfterFirstSuccessfulMembershipPayment(User $user): array
    {
        $freshUser = User::query()->find($user->id);

        if (! $freshUser) {
            Log::warning('membership.welcome_email.user_not_found', ['user_id' => (string) $user->id]);

            return ['sent' => false, 'reason' => 'user_not_found'];
        }

        $successfulPaymentCount = $this->successfulMembershipPaymentCount($freshUser);
        if ($successfulPaymentCount !== 1) {
            Log::info('membership.welcome_email.skipped', [
                'user_id' => (string) $freshUser->id,
                'reason' => 'not_first_successful_payment',
                'successful_membership_payments' => $successfulPaymentCount,
            ]);

            return ['sent' => false, 'reason' => 'not_first_successful_payment'];
        }

        return $this->sendIfEligible($freshUser);
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

        if (! $this->isEligiblePaidMembershipUser($freshUser)) {
            Log::info('membership.welcome_email.skipped', [
                'user_id' => (string) $freshUser->id,
                'reason' => 'not_paid',
                'membership_status' => (string) ($freshUser->membership_status ?? ''),
            ]);

            return ['sent' => false, 'reason' => 'not_paid'];
        }

        $attachments = $this->resolveAttachments();
        $details = $this->emailDetails($freshUser);
        $mailable = new MembershipWelcomeMail($freshUser, $attachments, $details);
        $ccEmail = trim((string) config('membership_welcome.cc_email', config('membership_welcome.membership_welcome_cc_email', '')));

        try {
            $pendingMail = Mail::to($email);
            if ($ccEmail !== '') {
                $pendingMail->bcc($ccEmail);
            }

            $pendingMail->send($mailable);

            $freshUser->forceFill([
                'welcome_membership_email_sent_at' => now(),
                'welcome_membership_email_status' => 'sent',
                'welcome_membership_email_error' => null,
                'welcome_membership_email_plan_code' => $this->planCodeAtSend($freshUser),
            ])->save();

            $this->emailLogService->logMailableSent($mailable, [
                'user_id' => (string) $freshUser->id,
                'to_email' => $email,
                'to_name' => $this->peerName($freshUser),
                'template_key' => 'membership_welcome',
                'source_module' => 'membership',
                'related_type' => 'user',
                'related_id' => (string) $freshUser->id,
                'payload' => [
                    'flow' => 'membership_activation',
                    'flow' => 'zoho_membership_activation',
                    'membership_status' => (string) ($freshUser->membership_status ?? ''),
                    'plan_code' => $this->planCodeAtSend($freshUser),
                    'cc_email_configured' => $ccEmail !== '',
                    'attachments_count' => count($attachments),
                ],
            ]);

            $this->sendWelcomeNotifications($freshUser);

            Log::info('membership.welcome_email.sent', [
                'user_id' => (string) $freshUser->id,
                'attachments_count' => count($attachments),
            ]);

            return ['sent' => true, 'reason' => 'sent'];
        } catch (Throwable $throwable) {
            $message = Str::limit($throwable->getMessage(), 2000, '');

            $freshUser->forceFill([
                'welcome_membership_email_sent_at' => null,
                'welcome_membership_email_status' => 'failed',
                'welcome_membership_email_error' => $message,
                'welcome_membership_email_plan_code' => $this->planCodeAtSend($freshUser),
            ])->save();

            $this->emailLogService->logMailableFailed($mailable, [
                'user_id' => (string) $freshUser->id,
                'to_email' => $email,
                'to_name' => $this->peerName($freshUser),
                'template_key' => 'membership_welcome',
                'source_module' => 'membership',
                'related_type' => 'user',
                'related_id' => (string) $freshUser->id,
                'payload' => [
                    'flow' => 'membership_activation',
                    'flow' => 'zoho_membership_activation',
                    'membership_status' => (string) ($freshUser->membership_status ?? ''),
                    'plan_code' => $this->planCodeAtSend($freshUser),
                    'cc_email_configured' => $ccEmail !== '',
                    'attachments_count' => count($attachments),
                ],
            ], $throwable);

            Log::warning('membership.welcome_email.failed', [
                'user_id' => (string) $freshUser->id,
                'message' => $throwable->getMessage(),
            ]);

            return ['sent' => false, 'reason' => 'failed'];
        }
    }

    private function successfulMembershipPaymentCount(User $user): int
    {
        try {
            if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'user_id') || ! Schema::hasColumn('payments', 'status')) {
                Log::warning('membership.welcome_email.payment_count_unavailable', ['user_id' => (string) $user->id]);
                return 0;
            }

            $query = DB::table('payments')
                ->where('user_id', $user->id)
                ->whereIn(DB::raw('LOWER(status)'), ['success', 'paid', 'completed', 'successful']);

            $hasMembershipPlanId = Schema::hasColumn('payments', 'membership_plan_id');
            $hasZohoPlanCode = Schema::hasColumn('payments', 'zoho_plan_code');

            if ($hasMembershipPlanId || $hasZohoPlanCode) {
                $query->where(function ($paymentQuery) use ($hasMembershipPlanId, $hasZohoPlanCode): void {
                    if ($hasMembershipPlanId) {
                        $paymentQuery->whereNotNull('membership_plan_id');
                    }

                    if ($hasZohoPlanCode) {
                        $method = $hasMembershipPlanId ? 'orWhereNotNull' : 'whereNotNull';
                        $paymentQuery->{$method}('zoho_plan_code');
                    }
                });
            }

            return (int) $query->count();
        } catch (Throwable $throwable) {
            Log::warning('membership.welcome_email.payment_count_failed', [
                'user_id' => (string) $user->id,
                'message' => $throwable->getMessage(),
            ]);

            return 0;
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

    private function sendWelcomeNotifications(User $user): void
    {
        $payload = [
            'type' => self::NOTIFICATION_TYPE,
            'screen' => 'membership',
            'reference_type' => 'user',
            'reference_id' => (string) $user->id,
        ];

        try {
            $this->notificationService->createInAppNotification(
                $user,
                self::NOTIFICATION_TYPE,
                self::NOTIFICATION_TITLE,
                self::NOTIFICATION_BODY,
                $payload,
                [
                    'channel' => 'in_app',
                    'priority' => 'high',
                    'reference_type' => 'user',
                    'reference_id' => (string) $user->id,
                    'dedupe_key' => self::NOTIFICATION_TYPE . ':' . $user->id,
                ]
            );
        } catch (Throwable $throwable) {
            Log::warning('membership.welcome_email.in_app_failed', [
                'user_id' => (string) $user->id,
                'message' => $throwable->getMessage(),
            ]);
        }

        $this->sendWelcomePush($user, $payload);
    }

    private function sendWelcomePush(User $user, array $payload): void
    {
        try {
            if (! Schema::hasTable('user_push_tokens')) {
                Log::warning('membership.welcome_email.push_token_table_missing', ['user_id' => (string) $user->id]);
                return;
            }

            $tokenColumn = Schema::hasColumn('user_push_tokens', 'fcm_token') ? 'fcm_token' : (Schema::hasColumn('user_push_tokens', 'token') ? 'token' : null);
            if ($tokenColumn === null) {
                Log::warning('membership.welcome_email.push_token_column_missing', ['user_id' => (string) $user->id]);
                return;
            }

            $query = UserPushToken::query()->where('user_id', $user->id);
            if (Schema::hasColumn('user_push_tokens', 'is_active')) {
                $query->where('is_active', true);
            }
            if (Schema::hasColumn('user_push_tokens', 'last_seen_at')) {
                $query->latest('last_seen_at');
            } else {
                $query->latest('updated_at');
            }

            $pushToken = $query->whereNotNull($tokenColumn)->where($tokenColumn, '!=', '')->first();
            if (! $pushToken) {
                Log::info('membership.welcome_email.no_active_push_token_found', ['user_id' => (string) $user->id]);
                return;
            }

            $this->firebaseFcmService->sendToDevice(
                (string) $pushToken->{$tokenColumn},
                self::NOTIFICATION_TITLE,
                self::NOTIFICATION_BODY,
                $payload,
                null,
                1,
                [
                    'user_id' => (string) $user->id,
                    'device_id' => $pushToken->device_id ?? null,
                    'platform' => $pushToken->platform ?? null,
                    'notification_type' => self::NOTIFICATION_TYPE,
                ]
            );
        } catch (Throwable $throwable) {
            Log::warning('membership.welcome_email.push_failed', [
                'user_id' => (string) $user->id,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, array{path:string,name:string}>
     */
    private function resolveAttachments(): array
    {
        $attachmentConfigs = [
            [
                'slot' => 1,
                'path' => (string) config('membership_welcome.membership_welcome_attachment_path_1', ''),
                'name' => (string) config('membership_welcome.membership_welcome_attachment_name_1', ''),
            ],
            [
                'slot' => 2,
                'path' => (string) config('membership_welcome.membership_welcome_attachment_path_2', ''),
                'name' => (string) config('membership_welcome.membership_welcome_attachment_name_2', ''),
            ],
        ];

        $attachments = [];
        $seenPaths = [];

        foreach ($attachmentConfigs as $attachmentConfig) {
            $slot = (int) Arr::get($attachmentConfig, 'slot');
            $path = trim((string) Arr::get($attachmentConfig, 'path', ''));
            $name = trim((string) Arr::get($attachmentConfig, 'name', ''));

            if ($path === '') {
                Log::warning('membership.welcome_email.attachment_missing_path', ['slot' => $slot]);
                continue;
            }

            if (! Str::startsWith($path, ['/'])) {
                $path = base_path($path);
            }

            $dedupeKey = $this->attachmentDedupeKey($path);
            if (isset($seenPaths[$dedupeKey])) {
                Log::info('Duplicate welcome attachment path skipped', [
                    'slot' => $slot,
                    'path' => $path,
                ]);

                continue;
            }
            $seenPaths[$dedupeKey] = true;

            if (! is_file($path)) {
                Log::warning('membership.welcome_email.attachment_not_found', [
                    'slot' => $slot,
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

    private function attachmentDedupeKey(string $path): string
    {
        $realPath = realpath($path);

        return $realPath !== false ? $realPath : $path;
    }

    private function emailDetails(User $user): array
    {
        return [
            'peer_name' => $this->emptyLabel($this->peerName($user)),
            'email' => $this->emptyLabel((string) ($user->email ?? '')),
            'membership_status' => $this->emptyLabel($this->membershipLabel((string) ($user->membership_status ?? ''))),
            'membership_start_date' => $this->dateLabel($user->membership_starts_at ?? $user->membership_start_date ?? null),
            'membership_expiry_date' => $this->dateLabel($user->membership_ends_at ?? $user->membership_expiry ?? $user->membership_end_date ?? null),
            'payment_date' => $this->dateTimeLabel($this->latestSuccessfulPaymentDate($user)),
            'plan_code' => $this->emptyLabel($this->planCodeAtSend($user)),
        ];
    }

    private function latestSuccessfulPaymentDate(User $user): mixed
    {
        try {
            if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'user_id')) {
                return null;
            }

            $query = DB::table('payments')->where('user_id', $user->id);
            if (Schema::hasColumn('payments', 'status')) {
                $query->whereIn(DB::raw('LOWER(status)'), ['success', 'paid', 'completed', 'successful']);
            }

            if (Schema::hasColumn('payments', 'paid_at')) {
                $paidAt = (clone $query)->latest('paid_at')->value('paid_at');
                if ($paidAt) {
                    return $paidAt;
                }
            }

            return Schema::hasColumn('payments', 'created_at')
                ? (clone $query)->latest('created_at')->value('created_at')
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function planCodeAtSend(User $user): string
    {
        return (string) ($user->zoho_plan_code ?: $user->membership_status ?: $user->membership_tier ?: '');
    }

    private function membershipLabel(string $value): string
    {
        return MembershipStatusLabels::label($value);
    }

    private function dateLabel(mixed $value): string
    {
        if (blank($value)) {
            return '—';
        }

        try {
            return Carbon::parse($value)->format('d-m-Y');
        } catch (Throwable) {
            return '—';
        }
    }

    private function dateTimeLabel(mixed $value): string
    {
        if (blank($value)) {
            return '—';
        }

        try {
            return Carbon::parse($value)->format('d-m-Y h:i A');
        } catch (Throwable) {
            return '—';
        }
    }

    private function emptyLabel(?string $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : '—';
    }

    private function peerName(User $user): string
    {
        return $user->display_name
            ?: trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? '')))
            ?: ($user->name ?: 'Peer');
    }
}
