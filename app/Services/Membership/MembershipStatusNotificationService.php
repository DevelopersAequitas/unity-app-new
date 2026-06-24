<?php

namespace App\Services\Membership;

use App\Mail\MembershipStatusChangedMail;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use App\Models\UserMembership;
use App\Models\UserPushToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use App\Services\Firebase\FcmService as FirebaseFcmService;
use Throwable;

class MembershipStatusNotificationService
{
    public function __construct(private readonly MembershipEmailAttachmentService $attachmentService)
    {
    }

    public function sendIfEligible(User $user, string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        $user->refresh();

        if (! $this->isEligibleUnityPeer($user, $newStatus)) {
            Log::info('membership_status_notification_skipped_ineligible_user', [
                'user_id' => (string) $user->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            return;
        }

        $membershipEndsAt = $this->membershipExpiryDate($user);
        $statusLabel = ucwords(str_replace('_', ' ', $newStatus));
        $expiryLabel = $membershipEndsAt?->format('d-M-Y') ?? 'Not available';
        $title = 'Membership Updated';
        $body = "Your Unity Peer membership has been updated.\nStatus: {$statusLabel}\nExpiry Date: {$expiryLabel}";
        $data = [
            'type' => 'membership_status_changed',
            'membership_status' => $newStatus,
            'previous_membership_status' => $oldStatus,
            'membership_ends_at' => $membershipEndsAt?->toDateString(),
            'screen' => 'membership',
        ];

        $this->createInAppNotification($user, $title, $body, $data);
        $this->sendPushNotification($user, $title, $body, $data);
        $this->sendEmail($user, $newStatus, $membershipEndsAt);
    }

    private function isEligibleUnityPeer(User $user, string $newStatus): bool
    {
        if ((bool) ($user->is_active ?? true) === false || $user->deleted_at !== null || $user->gdpr_deleted_at !== null) {
            return false;
        }

        if ($user->roles()->whereIn('key', ['admin', 'super_admin'])->exists()) {
            return false;
        }

        if ($newStatus !== 'only_unity_peer') {
            return false;
        }

        if (! Schema::hasTable('user_memberships')) {
            return false;
        }

        return UserMembership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->exists();
    }

    private function membershipExpiryDate(User $user): ?Carbon
    {
        $membership = Schema::hasTable('user_memberships')
            ? UserMembership::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->orderByDesc('ends_at')
                ->first()
            : null;

        return $membership?->ends_at
            ?? $user->membership_ends_at
            ?? $user->membership_expiry;
    }

    private function createInAppNotification(User $user, string $title, string $body, array $data): ?AppNotification
    {
        $dedupeKey = 'membership_status_changed:' . $user->id . ':' . $data['previous_membership_status'] . ':' . $data['membership_status'];

        try {
            $existing = AppNotification::query()
                ->where('user_id', $user->id)
                ->where('dedupe_key', $dedupeKey)
                ->where('created_at', '>=', now()->subDay())
                ->first();

            if ($existing) {
                Log::info('membership_status_in_app_notification_duplicate_skipped', [
                    'user_id' => (string) $user->id,
                    'notification_id' => (string) $existing->id,
                    'dedupe_key' => $dedupeKey,
                    'delivery_status' => 'duplicate_skipped',
                ]);

                return $existing;
            }

            $notification = AppNotification::create([
                'user_id' => $user->id,
                'type' => 'membership_status_changed',
                'category' => 'membership',
                'title' => $title,
                'body' => $body,
                'channel' => 'in_app',
                'priority' => 'high',
                'screen' => 'membership',
                'data' => $data,
                'dedupe_key' => $dedupeKey,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info('membership_status_in_app_notification_sent', [
                'user_id' => (string) $user->id,
                'notification_id' => (string) $notification->id,
                'dedupe_key' => $dedupeKey,
                'delivery_status' => 'sent',
            ]);

            return $notification;
        } catch (Throwable $throwable) {
            Log::error('membership_status_in_app_notification_failed', [
                'user_id' => (string) $user->id,
                'error' => $throwable->getMessage(),
                'delivery_status' => 'failed',
            ]);

            return null;
        }
    }

    private function sendPushNotification(User $user, string $title, string $body, array $data): void
    {
        try {
            if (! Schema::hasTable('user_push_tokens')) {
                Log::info('membership_status_push_skipped', ['user_id' => (string) $user->id, 'delivery_status' => 'token_table_missing']);
                return;
            }

            $tokenColumn = Schema::hasColumn('user_push_tokens', 'fcm_token') ? 'fcm_token' : (Schema::hasColumn('user_push_tokens', 'token') ? 'token' : null);

            if ($tokenColumn === null) {
                Log::info('membership_status_push_skipped', ['user_id' => (string) $user->id, 'delivery_status' => 'token_column_missing']);
                return;
            }

            $query = UserPushToken::query()->where('user_id', $user->id)->whereNotNull($tokenColumn)->where($tokenColumn, '!=', '');

            if (Schema::hasColumn('user_push_tokens', 'is_active')) {
                $query->where('is_active', true);
            }

            $tokens = $query->get();

            if ($tokens->isEmpty()) {
                Log::info('membership_status_push_skipped', ['user_id' => (string) $user->id, 'delivery_status' => 'no_active_tokens']);
                return;
            }

            $fcm = app(FirebaseFcmService::class);

            foreach ($tokens as $tokenModel) {
                try {
                    $response = $fcm->sendToDevice(
                        (string) $tokenModel->{$tokenColumn},
                        $title,
                        $body,
                        $data,
                        null,
                        1,
                        [
                            'user_id' => (string) $user->id,
                            'device_id' => $tokenModel->device_id ?? null,
                            'platform' => $tokenModel->platform ?? null,
                            'notification_type' => 'membership_status_changed',
                        ]
                    );

                    Log::info('membership_status_push_sent', [
                        'user_id' => (string) $user->id,
                        'push_token_id' => (string) ($tokenModel->id ?? ''),
                        'delivery_status' => 'sent',
                        'provider_response' => $response,
                    ]);
                } catch (Throwable $throwable) {
                    Log::warning('membership_status_push_failed', [
                        'user_id' => (string) $user->id,
                        'push_token_id' => (string) ($tokenModel->id ?? ''),
                        'delivery_status' => 'failed',
                        'error' => $throwable->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $throwable) {
            Log::warning('membership_status_push_lookup_failed', [
                'user_id' => (string) $user->id,
                'delivery_status' => 'failed',
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function sendEmail(User $user, string $membershipStatus, ?Carbon $membershipEndsAt): void
    {
        if (blank($user->email)) {
            Log::info('membership_status_email_skipped_missing_email', ['user_id' => (string) $user->id]);
            return;
        }

        try {
            $attachments = $this->attachmentService->resolve('membership_status');
            $subject = 'Your Unity Peer Membership Status Updated';
            Mail::to($user->email)->send(new MembershipStatusChangedMail($user, $membershipStatus, $membershipEndsAt, $attachments));

            Log::info('membership_status_email_sent', [
                'user_id' => (string) $user->id,
                'from_email' => 'pravin@peersunity.com',
                'to_email' => $user->email,
                'subject' => $subject,
                'mail_status' => 'sent',
                'smtp_response' => null,
                'attachments_count' => count($attachments),
            ]);
        } catch (Throwable $throwable) {
            Log::error('membership_status_email_failed', [
                'user_id' => (string) $user->id,
                'email' => $user->email,
                'from_email' => 'pravin@peersunity.com',
                'to_email' => $user->email,
                'subject' => 'Your Unity Peer Membership Status Updated',
                'mail_status' => 'failed',
                'smtp_response' => $throwable->getMessage(),
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
