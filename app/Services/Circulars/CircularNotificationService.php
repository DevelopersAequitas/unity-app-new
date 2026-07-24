<?php

namespace App\Services\Circulars;

use App\Jobs\SendPushNotificationJob;
use App\Models\Circular;
use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CircularNotificationService
{
    public function send(Circular $circular): void
    {
        if (! $circular->send_push_notification) {
            return;
        }

        if ($circular->notification_sent_at) {
            return;
        }

        $users = User::query()
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->get(['id']);

        foreach ($users as $user) {
            try {
                $notification = Notification::create([
                    'user_id' => $user->id,
                    'type' => 'activity_update',
                    'payload' => [
                        'notification_type' => 'circular',
                        'title' => $circular->title,
                        'body' => $circular->summary ?? 'New circular available',
                        'circular_id' => (string) $circular->id,
                    ],
                    'is_read' => false,
                    'created_at' => now(),
                    'read_at' => null,
                ]);

                try {
                    AppNotification::create([
                        'user_id' => $user->id,
                        'type' => 'circular',
                        'category' => 'circular',
                        'title' => (string) $circular->title,
                        'body' => (string) ($circular->summary ?? 'New circular available'),
                        'message' => (string) ($circular->summary ?? 'New circular available'),
                        'channel' => 'push',
                        'priority' => 'medium',
                        'reference_type' => Circular::class,
                        'reference_id' => (string) $circular->id,
                        'screen' => 'circulars',
                        'data' => [
                            'notification_id' => (string) $notification->id,
                            'circular_id' => (string) $circular->id,
                            'type' => 'circular',
                        ],
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Failed to create AppNotification in CircularNotificationService', [
                        'error' => $e->getMessage(),
                    ]);
                }

                SendPushNotificationJob::dispatch(
                    $user,
                    (string) $circular->title,
                    (string) ($circular->summary ?? 'New circular available'),
                    [
                        'type' => 'circular',
                        'notification_id' => (string) $notification->id,
                        'circular_id' => (string) $circular->id,
                    ]
                );
            } catch (\Throwable $exception) {
                Log::warning('Failed sending circular notification', [
                    'circular_id' => (string) $circular->id,
                    'user_id' => (string) $user->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        DB::table('circulars')
            ->where('id', $circular->id)
            ->update(['notification_sent_at' => DB::raw('NOW()')]);
    }
}
