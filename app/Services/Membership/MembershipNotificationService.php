<?php

namespace App\Services\Membership;

use App\Mail\MembershipStatusChangedMail;
use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MembershipNotificationService
{
    public function sendFirstPurchase(User $user, string $source = 'membership_purchase'): ?AppNotification
    {
        return $this->store($user, 'membership_first_purchase', 'Welcome to Peers Global Unity', 'Your membership has been activated successfully.', ['source' => $source]);
    }

    public function sendMembershipWelcome(User $user, string $source = 'membership_purchase', array $attachments = []): ?AppNotification
    {
        $status = (string) ($user->membership_status ?? '');
        $expiry = optional($user->membership_ends_at ?? $user->membership_expiry)->toDateString();
        $message = 'Your membership has been activated successfully.';

        return $this->store($user, 'membership_welcome', 'Membership Activated', $message, [
            'source' => $source,
            'notification_type' => 'membership_welcome',
            'membership_status' => $status,
            'membership_expiry' => $expiry,
            'membership_plan_name' => (string) ($user->zoho_plan_code ?? 'Peers Global Unity Membership'),
            'membership_type' => (string) ($user->membership_type ?? $user->membership_status ?? ''),
            'transaction_id' => (string) ($user->zoho_last_invoice_id ?? ''),
            'uploaded_file_ids' => collect($attachments)->pluck('id')->filter()->values()->all(),
            'uploaded_file_urls' => collect($attachments)->pluck('url')->filter()->values()->all(),
            'attachments' => $attachments,
        ]);
    }

    public function recordEmailSent(User $user, string $emailType, string $sentTo, string $source = 'membership_email'): ?AppNotification
    {
        $titles = [
            'membership_welcome_email_sent' => 'Membership Welcome Email Sent',
            'membership_expiry_email_sent' => 'Membership Expiry Email Sent',
            'membership_status_email_sent' => 'Membership Status Email Sent',
        ];

        $title = $titles[$emailType] ?? 'Membership Email Sent';
        $message = $title . ' to ' . $sentTo . '.';

        return $this->store($user, $emailType, $title, $message, [
            'source' => $source,
            'source_type' => 'membership_email',
            'source_event' => $emailType,
            'email_type' => $emailType,
            'sent_email_address' => $sentTo,
            'recipient_email' => $sentTo,
            'sender_email' => config('mail.membership_from.address', 'support@peersglobal.com'),
            'sent_at' => now()->toIso8601String(),
            'related_type' => 'user',
            'related_id' => (string) $user->id,
        ], true);
    }

    public function sendStatusChanged(User $user, ?string $previousStatus, ?string $newStatus, ?string $updatedBy = null, bool $email = true, string $source = 'admin_status_change'): ?AppNotification
    {
        if ((string) $previousStatus === (string) $newStatus) return null;
        $title = 'Membership Status Updated';
        $message = 'Your membership status changed from ' . $this->label($previousStatus) . ' to ' . $this->label($newStatus) . '.';
        $data = ['previous_status' => $previousStatus, 'updated_by_admin' => $updatedBy, 'source' => $source, 'updated_at' => now()->toIso8601String()];
        if ($email && filled($user->email)) {
            try {
                $mailable = new MembershipStatusChangedMail($user, $previousStatus, $newStatus, $updatedBy);
                Mail::to($user->email)->send($mailable);
                app(EmailLogService::class)->logMailableSent($mailable, [
                    'user_id' => (string) $user->id,
                    'to_email' => (string) $user->email,
                    'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))),
                    'template_key' => 'membership_status_changed',
                    'source_module' => 'Membership',
                    'related_type' => User::class,
                    'related_id' => (string) $user->id,
                    'triggered_by' => $updatedBy,
                    'payload' => ['previous_status' => $previousStatus, 'new_status' => $newStatus, 'source' => $source],
                ]);
                $this->recordEmailSent($user, 'membership_status_email_sent', (string) $user->email, $source);
            }
            catch (Throwable $e) {
                if (isset($mailable)) {
                    app(EmailLogService::class)->logMailableFailed($mailable, [
                        'user_id' => (string) $user->id,
                        'to_email' => (string) $user->email,
                        'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))),
                        'template_key' => 'membership_status_changed',
                        'source_module' => 'Membership',
                        'related_type' => User::class,
                        'related_id' => (string) $user->id,
                        'triggered_by' => $updatedBy,
                    ], $e);
                }

                Log::warning('membership.status_email_failed', [
                    'user_id' => $user->id,
                    'to_email' => $user->email,
                    'from_address' => config('mail.membership_from.address', 'support@peersglobal.com'),
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }
        return $this->store($user, 'membership_status_changed', $title, $message, $data, false);
    }

    public function sendManual(User $user, ?string $triggeredBy = null): ?AppNotification
    {
        $title = 'Membership Notification';
        $message = 'Here is your latest membership information.';
        if (filled($user->email)) {
            try {
                $mailable = new MembershipStatusChangedMail($user, $user->membership_status, $user->membership_status, $triggeredBy, true);
                Mail::to($user->email)->send($mailable);
                app(EmailLogService::class)->logMailableSent($mailable, [
                    'user_id' => (string) $user->id,
                    'to_email' => (string) $user->email,
                    'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))),
                    'template_key' => 'membership_manual_notification',
                    'source_module' => 'Membership',
                    'related_type' => User::class,
                    'related_id' => (string) $user->id,
                    'triggered_by' => $triggeredBy,
                    'payload' => ['source' => 'manual_membership_notification'],
                ]);
                $this->recordEmailSent($user, 'membership_status_email_sent', (string) $user->email, 'manual_membership_notification');
            }
            catch (Throwable $e) {
                if (isset($mailable)) {
                    app(EmailLogService::class)->logMailableFailed($mailable, [
                        'user_id' => (string) $user->id,
                        'to_email' => (string) $user->email,
                        'to_name' => (string) ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))),
                        'template_key' => 'membership_manual_notification',
                        'source_module' => 'Membership',
                        'related_type' => User::class,
                        'related_id' => (string) $user->id,
                        'triggered_by' => $triggeredBy,
                    ], $e);
                }

                Log::warning('membership.manual_email_failed', [
                    'user_id' => $user->id,
                    'to_email' => $user->email,
                    'from_address' => config('mail.membership_from.address', 'support@peersglobal.com'),
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }
        return $this->store($user, 'membership_manual_trigger', $title, $message, ['triggered_by' => $triggeredBy, 'triggered_at' => now()->toIso8601String()], true);
    }

    private function store(User $user, string $type, string $title, string $message, array $extra = [], bool $minuteDedupe = false): ?AppNotification
    {
        $data = array_merge([
            'title' => $title, 'message' => $message,
            'membership_type' => (string) ($user->membership_type ?? $user->membership_status ?? ''),
            'membership_name' => (string) ($user->zoho_plan_code ?? 'Peers Global Unity Membership'),
            'membership_status' => (string) ($user->membership_status ?? ''),
            'start_date' => optional($user->membership_starts_at)->toDateString(),
            'expiry_date' => optional($user->membership_ends_at ?? $user->membership_expiry)->toDateString(),
            'membership_id' => (string) $user->id,
            'action_url' => '/membership',
        ], $extra);
        $dedupe = $type . ':' . $user->id . ':' . md5(json_encode($data)) . ($minuteDedupe ? ':' . now()->format('YmdHi') : '');

        Log::info('membership.notification_create_attempt', [
            'user_id' => (string) $user->id,
            'type' => $type,
            'payload' => $data,
        ]);

        try {
            if (! Schema::hasTable('app_notifications')) {
                Log::warning('membership.app_notification_table_missing', ['user_id' => $user->id, 'type' => $type]);
                $this->storeLegacyNotification($user, $type, $title, $message, $data);
                return null;
            }

            if (Schema::hasColumn('app_notifications', 'dedupe_key')
                && AppNotification::query()->where('dedupe_key', $dedupe)->exists()) {
                return null;
            }

            $payload = [
                'user_id' => $user->id,
                'type' => $type,
                'category' => 'membership',
                'title' => $title,
                'message' => $message,
                'body' => $message,
                'channel' => 'in_app',
                'priority' => 'high',
                'screen' => 'membership',
                'data' => $data,
                'payload' => $data,
                'dedupe_key' => $dedupe,
                'status' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $insert = ['id' => (string) Str::uuid()];
            foreach ($payload as $column => $value) {
                if (! Schema::hasColumn('app_notifications', $column)) {
                    continue;
                }

                $insert[$column] = in_array($column, ['data', 'payload'], true) && is_array($value)
                    ? json_encode($value)
                    : $value;
            }

            if (! isset($insert['user_id']) || ! isset($insert['type'])) {
                Log::warning('membership.notification_required_columns_missing', [
                    'user_id' => $user->id,
                    'type' => $type,
                    'columns' => array_keys($insert),
                ]);

                return null;
            }

            $this->storeLegacyNotification($user, $type, $title, $message, $data);

            DB::table('app_notifications')->insert($insert);

            Log::info('membership.notification_created', [
                'user_id' => (string) $user->id,
                'type' => $type,
                'notification_id' => $insert['id'],
                'dedupe_key' => $dedupe,
                'payload' => $data,
            ]);

            return AppNotification::query()->find($insert['id']);
        } catch (Throwable $exception) {
            Log::error('membership.notification_create_failed', [
                'user_id' => $user->id,
                'type' => $type,
                'exception_message' => $exception->getMessage(),
                'exception_file' => $exception->getFile(),
                'exception_line' => $exception->getLine(),
            ]);

            return null;
        }
    }


    private function storeLegacyNotification(User $user, string $type, string $title, string $message, array $data): ?Notification
    {
        if (! Schema::hasTable('notifications')) {
            Log::warning('membership.legacy_notification_table_missing', ['user_id' => (string) $user->id, 'type' => $type]);
            return null;
        }

        $payload = array_merge([
            'title' => $title,
            'body' => $message,
            'notification_type' => $type === 'membership_welcome' ? 'membership_welcome' : $type,
            'to_user_id' => (string) $user->id,
        ], $data);

        try {
            $recentDuplicate = Notification::query()
                ->where('user_id', $user->id)
                ->where('type', 'activity_update')
                ->where('created_at', '>=', now()->subMinutes(5))
                ->where('payload->notification_type', $payload['notification_type'])
                ->where('payload->to_user_id', (string) $user->id)
                ->first();

            if ($recentDuplicate) {
                Log::info('membership.legacy_notification_duplicate_skipped', [
                    'user_id' => (string) $user->id,
                    'type' => $type,
                    'notification_id' => (string) $recentDuplicate->id,
                    'payload' => $payload,
                ]);

                return $recentDuplicate;
            }

            $row = [
                'user_id' => $user->id,
                'type' => 'activity_update',
                'payload' => $payload,
                'is_read' => false,
                'created_at' => now(),
                'read_at' => null,
            ];

            foreach ([
                'title' => $title,
                'message' => $message,
                'source_type' => 'membership',
                'source_id' => (string) $user->id,
                'source_event' => $type,
            ] as $column => $value) {
                if (Schema::hasColumn('notifications', $column)) {
                    $row[$column] = $value;
                }
            }

            $notification = Notification::create($row);

            Log::info('membership.legacy_notification_created', [
                'user_id' => (string) $user->id,
                'type' => $type,
                'notification_id' => (string) $notification->id,
                'payload' => $payload,
            ]);

            return $notification;
        } catch (Throwable $exception) {
            Log::error('membership.legacy_notification_create_failed', [
                'user_id' => (string) $user->id,
                'type' => $type,
                'payload' => $payload,
                'exception_message' => $exception->getMessage(),
                'exception_file' => $exception->getFile(),
                'exception_line' => $exception->getLine(),
            ]);

            return null;
        }
    }

    private function label(?string $status): string { return Str::headline(str_replace('_', ' ', (string) ($status ?: 'unknown'))); }
}
