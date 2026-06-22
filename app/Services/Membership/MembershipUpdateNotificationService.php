<?php

namespace App\Services\Membership;

use App\Mail\MembershipUpdatedMail;
use App\Models\User;
use App\Models\UserPushToken;
use App\Services\EmailLogs\EmailLogService;
use App\Services\Firebase\FcmService as FirebaseFcmService;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MembershipUpdateNotificationService
{
    private const TITLE = 'Membership Updated';
    private const TYPE = 'membership_updated';

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly FirebaseFcmService $firebaseFcmService,
        private readonly EmailLogService $emailLogService,
    ) {
    }

    /**
     * @param  array{membership_status:mixed,membership_expiry:mixed}  $oldValues
     */
    public function sendIfMembershipChanged(User $user, array $oldValues, ?string $adminNote = null): void
    {
        $freshUser = User::query()->find($user->id);

        if (! $freshUser) {
            Log::warning('membership.update_notifications.user_not_found', [
                'user_id' => (string) $user->id,
            ]);

            return;
        }

        $changes = $this->resolveChanges($oldValues, $freshUser);
        if (! $changes['status_changed'] && ! $changes['expiry_changed']) {
            return;
        }

        $body = $this->notificationBody($changes);
        $details = $this->emailDetails($freshUser, $changes, $adminNote, $body);
        $payload = [
            'type' => self::TYPE,
            'screen' => 'membership',
            'reference_type' => 'user',
            'reference_id' => (string) $freshUser->id,
            'old_membership_status' => $changes['old_status'],
            'new_membership_status' => $changes['new_status'],
            'old_membership_expiry' => $changes['old_expiry'],
            'new_membership_expiry' => $changes['new_expiry'],
            'status_changed' => $changes['status_changed'],
            'expiry_changed' => $changes['expiry_changed'],
        ];

        $this->createInAppNotification($freshUser, $body, $payload);
        $this->sendPushNotification($freshUser, $body, $payload);
        $this->sendEmail($freshUser, $details);
    }

    /**
     * @param  array{membership_status:mixed,membership_expiry:mixed}  $oldValues
     * @return array{status_changed:bool,expiry_changed:bool,old_status:string,new_status:string,old_expiry:?string,new_expiry:?string,old_status_label:string,new_status_label:string,old_expiry_label:string,new_expiry_label:string}
     */
    private function resolveChanges(array $oldValues, User $freshUser): array
    {
        $oldStatus = (string) ($oldValues['membership_status'] ?? '');
        $newStatus = (string) ($freshUser->membership_status ?? '');
        $oldExpiry = $this->normalizeDateTime($oldValues['membership_expiry'] ?? null);
        $newExpiry = $this->normalizeDateTime($this->membershipExpiryValue($freshUser));

        return [
            'status_changed' => $oldStatus !== $newStatus,
            'expiry_changed' => $oldExpiry !== $newExpiry,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'old_expiry' => $oldExpiry,
            'new_expiry' => $newExpiry,
            'old_status_label' => $this->membershipLabel($oldStatus),
            'new_status_label' => $this->membershipLabel($newStatus),
            'old_expiry_label' => $this->dateLabel($oldExpiry),
            'new_expiry_label' => $this->dateLabel($newExpiry),
        ];
    }

    /**
     * @param  array{status_changed:bool,expiry_changed:bool,new_status_label:string,new_expiry_label:string}  $changes
     */
    private function notificationBody(array $changes): string
    {
        if ($changes['status_changed'] && $changes['expiry_changed']) {
            return "Your membership status has been updated to {$changes['new_status_label']} and your membership expiry date has been updated to {$changes['new_expiry_label']}.";
        }

        if ($changes['status_changed']) {
            return "Your membership status has been updated to {$changes['new_status_label']}.";
        }

        return "Your membership expiry date has been updated to {$changes['new_expiry_label']}.";
    }

    /**
     * @param  array{old_status_label:string,new_status_label:string,old_expiry_label:string,new_expiry_label:string}  $changes
     * @return array{old_status:string,new_status:string,old_expiry:string,new_expiry:string,email:string,updated_at:string,admin_note:?string,notification_body:string}
     */
    private function emailDetails(User $user, array $changes, ?string $adminNote, string $body): array
    {
        return [
            'old_status' => $changes['old_status_label'],
            'new_status' => $changes['new_status_label'],
            'old_expiry' => $changes['old_expiry_label'],
            'new_expiry' => $changes['new_expiry_label'],
            'email' => $this->emptyLabel((string) ($user->email ?? '')),
            'updated_at' => now()->format('d-m-Y h:i A'),
            'admin_note' => filled($adminNote) ? trim((string) $adminNote) : null,
            'notification_body' => $body,
        ];
    }

    private function createInAppNotification(User $user, string $body, array $payload): void
    {
        try {
            $this->notificationService->createInAppNotification(
                $user,
                self::TYPE,
                self::TITLE,
                $body,
                $payload,
                [
                    'channel' => 'in_app',
                    'priority' => 'high',
                    'reference_type' => 'user',
                    'reference_id' => (string) $user->id,
                    'dedupe_key' => self::TYPE . ':' . $user->id . ':' . now()->timestamp,
                ]
            );
        } catch (Throwable $throwable) {
            Log::warning('membership.update_notifications.in_app_failed', [
                'user_id' => (string) $user->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function sendPushNotification(User $user, string $body, array $payload): void
    {
        try {
            if (! Schema::hasTable('user_push_tokens')) {
                Log::warning('membership.update_notifications.push_token_table_missing', ['user_id' => (string) $user->id]);
                return;
            }

            $tokenColumn = Schema::hasColumn('user_push_tokens', 'fcm_token') ? 'fcm_token' : (Schema::hasColumn('user_push_tokens', 'token') ? 'token' : null);
            if ($tokenColumn === null) {
                Log::warning('membership.update_notifications.push_token_column_missing', ['user_id' => (string) $user->id]);
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
                Log::info('membership.update_notifications.push_token_missing', ['user_id' => (string) $user->id]);
                return;
            }

            $this->firebaseFcmService->sendToDevice(
                (string) $pushToken->{$tokenColumn},
                self::TITLE,
                $body,
                $payload,
                null,
                1,
                [
                    'user_id' => (string) $user->id,
                    'device_id' => $pushToken->device_id ?? null,
                    'platform' => $pushToken->platform ?? null,
                    'notification_type' => self::TYPE,
                ]
            );
        } catch (Throwable $throwable) {
            Log::warning('membership.update_notifications.push_failed', [
                'user_id' => (string) $user->id,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{old_status:string,new_status:string,old_expiry:string,new_expiry:string,email:string,updated_at:string,admin_note:?string,notification_body:string}  $details
     */
    private function sendEmail(User $user, array $details): void
    {
        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            Log::warning('membership.update_notifications.email_missing', ['user_id' => (string) $user->id]);
            return;
        }

        $attachments = $this->resolveAttachments();
        $mailable = new MembershipUpdatedMail($user, $details, $attachments);
        $ccEmail = trim((string) config('membership_update.cc_email', config('membership_update.membership_update_cc_email', '')));

        try {
            $pendingMail = Mail::to($email);
            if ($ccEmail !== '') {
                $pendingMail->bcc($ccEmail);
            }

            $pendingMail->send($mailable);

            $this->emailLogService->logMailableSent($mailable, [
                'user_id' => (string) $user->id,
                'to_email' => $email,
                'to_name' => $this->peerName($user),
                'template_key' => self::TYPE,
                'source_module' => 'membership',
                'related_type' => 'user',
                'related_id' => (string) $user->id,
                'payload' => [
                    'cc_email_configured' => $ccEmail !== '',
                    'attachments_count' => count($attachments),
                    'details' => $details,
                ],
            ]);
        } catch (Throwable $throwable) {
            $this->emailLogService->logMailableFailed($mailable, [
                'user_id' => (string) $user->id,
                'to_email' => $email,
                'to_name' => $this->peerName($user),
                'template_key' => self::TYPE,
                'source_module' => 'membership',
                'related_type' => 'user',
                'related_id' => (string) $user->id,
                'payload' => [
                    'cc_email_configured' => $ccEmail !== '',
                    'attachments_count' => count($attachments),
                    'details' => $details,
                ],
            ], $throwable);

            Log::warning('membership.update_notifications.email_failed', [
                'user_id' => (string) $user->id,
                'email' => $email,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, array{path:string,name:string}>
     */
    private function resolveAttachments(): array
    {
        $path = trim((string) config('membership_update.attachment_path', ''));
        if ($path === '') {
            Log::warning('membership.update_notifications.attachment_missing_path');
            return [];
        }

        if (! Str::startsWith($path, ['/'])) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            Log::warning('membership.update_notifications.attachment_not_found', ['path' => $path]);
            return [];
        }

        $name = trim((string) config('membership_update.attachment_name', ''));

        return [[
            'path' => $path,
            'name' => $name !== '' ? $name : basename($path),
        ]];
    }

    private function membershipLabel(?string $value): string
    {
        $value = (string) $value;
        if ($value === '') {
            return '—';
        }

        $labels = [
            'free_trial_peer' => 'Free Trial Peer',
            'free_peer' => 'Free Peer',
            'only_unity_peer' => 'Only Unity Peer',
            'circle_peer' => 'Circle Peer',
            'multi_circle_peer' => 'Multi Circle Peer',
        ];

        $normalized = strtolower(trim(str_replace(' ', '_', $value)));

        return $labels[$normalized] ?? Str::headline(str_replace('_', ' ', $value));
    }

    private function membershipExpiryValue(User $user): mixed
    {
        foreach ([
            'membership_ends_at',
            'membership_expiry',
            'membership_expiry_date',
            'membership_expires_at',
            'membership_end_date',
            'expires_at',
        ] as $attribute) {
            $value = $user->getAttribute($attribute);

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->seconds(0)->toDateTimeString();
        } catch (Throwable) {
            return (string) $value;
        }
    }

    private function dateLabel(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        try {
            return Carbon::parse($value)->format('d-m-Y');
        } catch (Throwable) {
            return $value;
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
