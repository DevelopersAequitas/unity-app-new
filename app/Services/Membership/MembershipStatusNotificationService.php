<?php

namespace App\Services\Membership;

use App\Mail\MembershipStatusChangedMail;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use App\Models\UserMembership;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
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
        $title = 'Membership Status Updated';
        $body = "Your Unity Peer membership status is now {$statusLabel}.";
        $data = [
            'type' => 'membership_status_changed',
            'membership_status' => $newStatus,
            'previous_membership_status' => $oldStatus,
            'membership_ends_at' => $membershipEndsAt?->toDateString(),
            'screen' => 'membership',
        ];

        $this->createInAppNotification($user, $title, $body, $data);
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

    private function createInAppNotification(User $user, string $title, string $body, array $data): void
    {
        try {
            AppNotification::create([
                'user_id' => $user->id,
                'type' => 'membership_status_changed',
                'category' => 'membership',
                'title' => $title,
                'body' => $body,
                'channel' => 'in_app',
                'priority' => 'high',
                'screen' => 'membership',
                'data' => $data,
                'dedupe_key' => 'membership_status_changed:' . $user->id . ':' . $data['previous_membership_status'] . ':' . $data['membership_status'],
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (Throwable $throwable) {
            Log::error('membership_status_in_app_notification_failed', [
                'user_id' => (string) $user->id,
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
            Mail::to($user->email)->send(new MembershipStatusChangedMail($user, $membershipStatus, $membershipEndsAt, $attachments));
        } catch (Throwable $throwable) {
            Log::error('membership_status_email_failed', [
                'user_id' => (string) $user->id,
                'email' => $user->email,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
