<?php

namespace App\Console\Commands;

use App\Jobs\SendPushNotificationJob;
use App\Models\Connection;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPendingConnectionRequestReminders extends Command
{
    protected $signature = 'connections:send-pending-reminders';

    protected $description = 'Send daily reminder notifications for pending connection requests.';

    public function handle(): int
    {
        $now = now();

        $pendingByReceiver = Connection::query()
            ->selectRaw('addressee_id, COUNT(*) as pending_count')
            ->where('is_approved', false)
            ->groupBy('addressee_id')
            ->get();

        $usersChecked = $pendingByReceiver->count();
        $notificationsSent = 0;

        foreach ($pendingByReceiver as $pendingReceiver) {
            $receiverId = (string) $pendingReceiver->addressee_id;
            $pendingCount = (int) $pendingReceiver->pending_count;

            if ($pendingCount <= 0) {
                continue;
            }

            $receiver = User::query()->find($receiverId);
            if (! $receiver || (($receiver->status ?? null) !== null && $receiver->status !== 'active')) {
                continue;
            }

            $alreadySent = Notification::query()
                ->where('user_id', $receiverId)
                ->where('type', 'activity_update')
                ->where('created_at', '>=', $now->copy()->subDay())
                ->where('payload->notification_type', 'connection_request_pending_reminder')
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $body = $pendingCount === 1
                ? 'You have 1 pending connection request. Please check and respond.'
                : "You have {$pendingCount} pending connection requests. Please check and respond.";

            try {
                $notification = Notification::query()->create([
                    'user_id' => $receiverId,
                    'type' => 'activity_update',
                    'payload' => [
                        'notification_type' => 'connection_request_pending_reminder',
                        'title' => 'Pending Connection Requests',
                        'body' => $body,
                        'from_user_id' => null,
                        'to_user_id' => $receiverId,
                        'data' => [
                            'pending_count' => $pendingCount,
                            'action' => 'connection_request_reminder',
                            'target_type' => 'connection_requests',
                        ],
                        'notifiable_type' => Connection::class,
                        'notifiable_id' => null,
                    ],
                    'is_read' => false,
                    'created_at' => $now->copy(),
                    'read_at' => null,
                ]);

                SendPushNotificationJob::dispatch(
                    $receiver,
                    'Pending Connection Requests',
                    $body,
                    [
                        'type' => 'connection_request_pending_reminder',
                        'notification_id' => (string) $notification->id,
                        'from_user_id' => null,
                        'to_user_id' => $receiverId,
                        'notifiable_type' => Connection::class,
                        'notifiable_id' => null,
                        'data' => [
                            'pending_count' => $pendingCount,
                            'action' => 'connection_request_reminder',
                            'target_type' => 'connection_requests',
                        ],
                    ]
                );

                $notificationsSent++;
            } catch (Throwable $e) {
                Log::error('Pending connection reminder notification failed', [
                    'receiver_user_id' => $receiverId,
                    'pending_count' => $pendingCount,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Pending connection reminder run completed', [
            'users_checked' => $usersChecked,
            'notifications_sent' => $notificationsSent,
        ]);

        $this->info("Users checked: {$usersChecked}");
        $this->info("Reminder notifications sent: {$notificationsSent}");

        return self::SUCCESS;
    }
}
