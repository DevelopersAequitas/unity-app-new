<?php

namespace App\Services\Membership;

use App\Mail\MembershipStatusChangedMail;
use App\Models\Notifications\AppNotification;
use App\Models\User;
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

    public function sendStatusChanged(User $user, ?string $previousStatus, ?string $newStatus, ?string $updatedBy = null, bool $email = true, string $source = 'admin_status_change'): ?AppNotification
    {
        if ((string) $previousStatus === (string) $newStatus) return null;
        $title = 'Membership Status Updated';
        $message = 'Your membership status changed from ' . $this->label($previousStatus) . ' to ' . $this->label($newStatus) . '.';
        $data = ['previous_status' => $previousStatus, 'updated_by_admin' => $updatedBy, 'source' => $source, 'updated_at' => now()->toIso8601String()];
        if ($email && filled($user->email)) {
            try { Mail::to($user->email)->send(new MembershipStatusChangedMail($user, $previousStatus, $newStatus, $updatedBy)); }
            catch (Throwable $e) { Log::warning('membership.status_email_failed', ['user_id' => $user->id, 'error' => $e->getMessage()]); }
        }
        return $this->store($user, 'membership_status_changed', $title, $message, $data, false);
    }

    public function sendManual(User $user, ?string $triggeredBy = null): ?AppNotification
    {
        $title = 'Membership Notification';
        $message = 'Here is your latest membership information.';
        if (filled($user->email)) {
            try { Mail::to($user->email)->send(new MembershipStatusChangedMail($user, $user->membership_status, $user->membership_status, $triggeredBy, true)); }
            catch (Throwable $e) { Log::warning('membership.manual_email_failed', ['user_id' => $user->id, 'error' => $e->getMessage()]); }
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

        try {
            if (! Schema::hasTable('app_notifications')) {
                Log::warning('membership.notification_table_missing', ['user_id' => $user->id, 'type' => $type]);
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

            DB::table('app_notifications')->insert($insert);

            Log::info('membership.notification_created', [
                'user_id' => $user->id,
                'type' => $type,
                'dedupe_key' => $dedupe,
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

    private function label(?string $status): string { return Str::headline(str_replace('_', ' ', (string) ($status ?: 'unknown'))); }
}
