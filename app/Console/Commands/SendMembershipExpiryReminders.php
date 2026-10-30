<?php

namespace App\Console\Commands;

use App\Events\UserNotificationCreated;
use App\Jobs\SendFcmNotificationJob;
use App\Mail\MembershipExpiryReminderMail;
use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use App\Notifications\MembershipExpiryNotification;
use App\Services\EmailLogs\EmailLogService;
use App\Services\Membership\MembershipNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SendMembershipExpiryReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memberships:send-expiry-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send membership expiry reminder emails on day 3 and day 7, and deactivate peers after 10 days.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting membership expiry reminder email process...');

        $freeStatuses = [
            User::STATUS_FREE,
            'Free Peer',
            'free_peer',
            'expired',
        ];

        // Fetch all users whose membership has expired (membership_ends_at is in the past, i.e., less than now)
        $expiredUsers = User::query()
            ->whereIn('membership_status', $freeStatuses)
            ->whereNotNull('membership_ends_at')
            ->where('membership_ends_at', '<', now())
            ->get();

        $this->info('Found '.$expiredUsers->count().' expired users.');

        $today = \Illuminate\Support\Carbon::today();
        $sentCount = 0;
        $deactivatedCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        $sentEmails = [];

        foreach ($expiredUsers as $user) {
            // Skip users already inactive or trashed
            if ($user->trashed() || strtolower((string) ($user->status ?? '')) === 'inactive') {
                $skippedCount++;

                continue;
            }

            $endsAt = \Illuminate\Support\Carbon::parse($user->membership_ends_at)->startOfDay();
            $daysSinceExpiry = (int) $endsAt->diffInDays($today, false);

            if ($daysSinceExpiry < 0) {
                $skippedCount++;

                continue;
            }

            // After 10 days without rejoining/renewing unity membership, peer is automatically deactivated
            if ($daysSinceExpiry >= 10) {
                $hasRenewed = ! in_array((string) $user->membership_status, $freeStatuses, true)
                    || ($user->membership_ends_at && \Illuminate\Support\Carbon::parse($user->membership_ends_at)->isFuture());

                if ($hasRenewed) {
                    $skippedCount++;

                    continue;
                }

                try {
                    if (Schema::hasColumn('users', 'status')) {
                        $user->status = 'inactive';
                    }
                    if (Schema::hasColumn('users', 'is_active')) {
                        $user->is_active = false;
                    }
                    $user->save();
                    $user->delete();

                    if (method_exists($user, 'tokens')) {
                        $user->tokens()->delete();
                    }

                    Log::info('membership.peer_automatically_deactivated_after_expiry', [
                        'user_id' => (string) $user->id,
                        'email' => (string) $user->email,
                        'days_since_expiry' => $daysSinceExpiry,
                        'membership_ends_at' => optional($user->membership_ends_at)->toDateTimeString(),
                    ]);

                    $deactivatedCount++;
                } catch (Throwable $exception) {
                    $failedCount++;
                    Log::error('membership.peer_automatic_deactivation_failed', [
                        'user_id' => (string) $user->id,
                        'error' => $exception->getMessage(),
                    ]);
                }

                continue;
            }

            // Only send reminder emails on day 3 and day 7; do not send on any other day
            if ($daysSinceExpiry !== 3 && $daysSinceExpiry !== 7) {
                $skippedCount++;

                continue;
            }

            $email = strtolower(trim((string) $user->email));

            // Prevent duplicate emails within the same scheduled execution
            if ($email === '' || in_array($email, $sentEmails, true)) {
                $skippedCount++;

                continue;
            }

            // Check if reminder for this specific day was already sent
            $alreadySentForDay = \App\Models\EmailLog::query()
                ->where('user_id', (string) $user->id)
                ->where('template_key', 'membership_expiry_reminder')
                ->where('status', 'sent')
                ->where('created_at', '>=', $user->membership_ends_at)
                ->where(function ($q) use ($daysSinceExpiry) {
                    $q->where('payload->day', $daysSinceExpiry)
                        ->orWhere('payload->day', (string) $daysSinceExpiry);
                })
                ->exists();

            if ($alreadySentForDay) {
                $skippedCount++;

                continue;
            }

            // Create Mailable instance
            $mailable = new MembershipExpiryReminderMail($user, $daysSinceExpiry);
            $emailSent = false;
            $emailError = null;

            try {
                // Queue email with a progressive delay to avoid rate limiting
                $delay = now()->addSeconds($sentCount * 2);
                Mail::to($user->email)->later($delay, $mailable);
                $emailSent = true;
            } catch (Throwable $exception) {
                $emailError = $exception;
            }

            try {
                // Create and trigger membership expiry notification
                $notificationObj = new MembershipExpiryNotification($user, $daysSinceExpiry);
                $notifPayload = $notificationObj->toArray($user);

                $appNotificationId = null;
                $appNotificationType = null;
                $appNotificationData = null;
                $appNotificationCreatedAt = null;

                if (Schema::hasTable('app_notifications')) {
                    // Create in-app AppNotification for the new system
                    $appNotification = AppNotification::create([
                        'user_id' => $user->id,
                        'type' => 'membership_expired',
                        'category' => 'membership',
                        'title' => (string) $notifPayload['title'],
                        'body' => (string) $notifPayload['body'],
                        'message' => (string) $notifPayload['body'],
                        'channel' => 'push',
                        'priority' => 'high',
                        'screen' => 'membership',
                        'data' => array_merge($notifPayload, [
                            'screen' => 'membership',
                            'tap_destination' => 'membership',
                            'reminder_day' => $daysSinceExpiry,
                        ]),
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

                    $appNotificationId = $appNotification->id;
                    $appNotificationType = $appNotification->type;
                    $appNotificationData = $appNotification->data;
                    $appNotificationCreatedAt = optional($appNotification->created_at)->toISOString();
                }

                $dbNotification = Notification::forceCreate([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'type' => 'system',
                    'payload' => $notifPayload,
                    'is_read' => false,
                    'created_at' => now(),
                    'read_at' => null,
                ]);

                event(new UserNotificationCreated((string) $user->id, [
                    'id' => (string) ($appNotificationId ?: $dbNotification->id),
                    'type' => (string) ($appNotificationType ?: $dbNotification->type),
                    'payload' => $appNotificationData ?: $dbNotification->payload,
                    'created_at' => $appNotificationCreatedAt ?: optional($dbNotification->created_at)->toISOString(),
                ]));

                SendFcmNotificationJob::dispatch(
                    (string) $user->id,
                    (string) $notifPayload['title'],
                    (string) $notifPayload['body'],
                    [
                        'notification_type' => 'membership_expired',
                        'notification_id' => (string) ($appNotificationId ?: $dbNotification->id),
                        'reminder_day' => (string) $daysSinceExpiry,
                    ]
                );

                if ($emailSent) {
                    // Log to EmailLogService (database)
                    app(EmailLogService::class)->logMailableSent($mailable, [
                        'user_id' => (string) $user->id,
                        'to_email' => (string) $user->email,
                        'to_name' => (string) $user->display_name,
                        'template_key' => 'membership_expiry_reminder',
                        'source_module' => 'membership',
                        'related_type' => 'user',
                        'related_id' => (string) $user->id,
                        'payload' => [
                            'flow' => 'membership_expiry_reminder_command',
                            'day' => $daysSinceExpiry,
                            'membership_ends_at' => optional($user->membership_ends_at)->toDateTimeString(),
                            'notification_id' => (string) $dbNotification->id,
                        ],
                    ]);

                    // Log using the project's standard logging system
                    Log::info('membership.expiry_reminder_email_sent', [
                        'user_id' => (string) $user->id,
                        'display_name' => (string) $user->display_name,
                        'email' => (string) $user->email,
                        'membership_ends_at' => optional($user->membership_ends_at)->toDateString(),
                        'days_since_expiry' => $daysSinceExpiry,
                        'sent_at' => now()->toDateTimeString(),
                        'status' => 'sent',
                        'notification_id' => (string) $dbNotification->id,
                        'user_email' => (string) $user->email,
                        'membership_end_date' => optional($user->membership_ends_at)->toDateString(),
                        'user_status' => (string) $user->membership_status,
                        'reminder_type' => 'expired_membership',
                        'email_status' => 'sent',
                        'notification_status' => 'sent',
                    ]);

                    app(MembershipNotificationService::class)->recordEmailSent(
                        $user,
                        'membership_expiry_email_sent',
                        (string) $user->email,
                        'membership_expiry_reminder_command'
                    );

                    $sentEmails[] = $email;
                    $sentCount++;
                } else {
                    throw $emailError ?: new \Exception('Email sending failed');
                }
            } catch (Throwable $exception) {
                $failedCount++;

                // Log failure to EmailLogService
                app(EmailLogService::class)->logMailableFailed($mailable, [
                    'user_id' => (string) $user->id,
                    'to_email' => (string) $user->email,
                    'to_name' => (string) $user->display_name,
                    'template_key' => 'membership_expiry_reminder',
                    'source_module' => 'membership',
                    'related_type' => 'user',
                    'related_id' => (string) $user->id,
                    'payload' => [
                        'flow' => 'membership_expiry_reminder_command',
                        'day' => $daysSinceExpiry,
                        'membership_ends_at' => optional($user->membership_ends_at)->toDateTimeString(),
                    ],
                ], $exception);

                // Log failure using the project's standard logging system
                Log::warning('membership.expiry_reminder_email_failed', [
                    'user_id' => (string) $user->id,
                    'display_name' => (string) $user->display_name,
                    'email' => (string) $user->email,
                    'membership_ends_at' => optional($user->membership_ends_at)->toDateString(),
                    'days_since_expiry' => $daysSinceExpiry,
                    'sent_at' => now()->toDateTimeString(),
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                    'user_email' => (string) $user->email,
                    'membership_end_date' => optional($user->membership_ends_at)->toDateString(),
                    'user_status' => (string) $user->membership_status,
                    'reminder_type' => 'expired_membership',
                    'email_status' => $emailSent ? 'sent' : 'failed',
                    'notification_status' => 'failed',
                ]);
            }
        }

        $this->info("Execution summary: Sent: {$sentCount}, Deactivated: {$deactivatedCount}, Failed: {$failedCount}, Skipped: {$skippedCount}");

        return self::SUCCESS;
    }
}
