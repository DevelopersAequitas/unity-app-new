<?php

namespace App\Services\Membership;

use App\Jobs\Notifications\SendNotificationChannelJob;
use App\Mail\MembershipExpiredMail;
use App\Mail\MembershipStatusUpdatedMail;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class MembershipLifecycleNotificationService
{
    public function sendStatusUpdated(User $user, string $status): void
    {
        $statusLabel = $this->statusLabel($status);
        $dedupeKey = 'membership_status_updated:' . $user->id . ':' . $status . ':' . optional($user->updated_at)->timestamp;

        $this->sendMail(new MembershipStatusUpdatedMail($user, $statusLabel), $user, 'membership_status_updated');
        $this->createNotification(
            $user,
            'membership_status_updated',
            'Membership Status Updated',
            "Your membership status is now:\n{$statusLabel}",
            $dedupeKey,
            [
                'membership_status' => $status,
                'membership_status_label' => $statusLabel,
            ]
        );
    }

    public function sendExpired(User $user): void
    {
        $expiry = optional($user->membership_ends_at ?? $user->membership_expiry)->format('Ymd') ?: now()->format('Ymd');
        $dedupeKey = 'membership_expired:' . $user->id . ':' . $expiry;

        $this->sendMail(new MembershipExpiredMail($user), $user, 'membership_expired');
        $this->createNotification(
            $user,
            'membership_expired',
            'Membership Expired',
            "Your membership has expired.\n\nPlease renew to continue your membership benefits.",
            $dedupeKey,
            [
                'membership_status' => (string) ($user->membership_status ?? ''),
                'expired_at' => now()->toDateTimeString(),
            ]
        );
    }

    private function sendMail(object $mailable, User $user, string $templateKey): void
    {
        $email = trim((string) ($user->email ?? ''));

        if ($email === '') {
            Log::warning('membership.lifecycle.email_skipped_missing_email', [
                'user_id' => (string) $user->id,
                'template_key' => $templateKey,
            ]);

            return;
        }

        try {
            Mail::to($email)->send($mailable);

            Log::info('membership.lifecycle.email_sent', [
                'user_id' => (string) $user->id,
                'email' => $email,
                'template_key' => $templateKey,
            ]);
        } catch (Throwable $throwable) {
            Log::error('membership.lifecycle.email_failed', [
                'user_id' => (string) $user->id,
                'email' => $email,
                'template_key' => $templateKey,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ]);
        }
    }

    private function createNotification(User $user, string $type, string $title, string $body, string $dedupeKey, array $data): void
    {
        try {
            $existing = AppNotification::query()
                ->where('user_id', $user->id)
                ->where('dedupe_key', $dedupeKey)
                ->exists();

            if ($existing) {
                Log::info('membership.lifecycle.notification_skipped_duplicate', [
                    'user_id' => (string) $user->id,
                    'type' => $type,
                    'dedupe_key' => $dedupeKey,
                ]);

                return;
            }

            $notification = AppNotification::create([
                'user_id' => $user->id,
                'type' => $type,
                'category' => 'membership',
                'title' => $title,
                'body' => $body,
                'channel' => 'push',
                'priority' => 'high',
                'screen' => 'membership',
                'data' => array_merge($data, [
                    'type' => $type,
                    'screen' => 'membership',
                    'tap_destination' => 'membership',
                ]),
                'dedupe_key' => $dedupeKey,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            SendNotificationChannelJob::dispatch((string) $notification->id, 'push');

            Log::info('membership.lifecycle.notification_created', [
                'user_id' => (string) $user->id,
                'notification_id' => (string) $notification->id,
                'type' => $type,
                'dedupe_key' => $dedupeKey,
            ]);
        } catch (Throwable $throwable) {
            Log::error('membership.lifecycle.notification_failed', [
                'user_id' => (string) $user->id,
                'type' => $type,
                'dedupe_key' => $dedupeKey,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ]);
        }
    }

    private function statusLabel(string $status): string
    {
        return Str::headline(str_replace('_', ' ', $status));
    }
}
