<?php

namespace App\Services\Notifications;

use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyUserService
{
    public function notifyUser(User $to, User $from, string $type, array $data = [], ?Model $notifiable = null): ?Notification
    {
        if ((string) $to->id === (string) $from->id) {
            return null;
        }

        if (($to->status ?? null) !== null && $to->status !== 'active') {
            return null;
        }

        $title = (string) Arr::get($data, 'title', 'New Notification');
        $body = (string) Arr::get($data, 'body', 'You have a new notification');

        $notificationPayload = [
            'notification_type' => $type,
            'title' => $title,
            'body' => $body,
            'from_user_id' => (string) $from->id,
            'to_user_id' => (string) $to->id,
            'data' => $data,
            'notifiable_type' => $notifiable ? $notifiable::class : null,
            'notifiable_id' => $notifiable ? (string) $notifiable->getKey() : null,
        ];

        $recentDuplicate = Notification::query()
            ->where('user_id', $to->id)
            ->where('type', 'activity_update')
            ->where('created_at', '>=', now()->subSeconds(60))
            ->where('payload->notification_type', $type)
            ->where('payload->from_user_id', (string) $from->id)
            ->where('payload->to_user_id', (string) $to->id)
            ->where('payload->notifiable_type', $notificationPayload['notifiable_type'])
            ->where('payload->notifiable_id', $notificationPayload['notifiable_id'])
            ->first();

        if ($recentDuplicate) {
            return $recentDuplicate;
        }

        $notification = Notification::create([
            'user_id' => $to->id,
            'type' => 'activity_update',
            'payload' => $notificationPayload,
            'is_read' => false,
            'created_at' => now(),
            'read_at' => null,
        ]);

        $appNotificationType = (string) ($data['type'] ?? $data['notification_type'] ?? $type);
        $screen = (string) ($data['navigation_screen'] ?? $data['screen'] ?? match ($type) {
            'connection_request', 'connection_received' => '/connection-requests',
            'connection_accepted', 'connection' => '/my-connections',
            default => $type,
        });

        try {
            AppNotification::create([
                'user_id' => $to->id,
                'type' => $appNotificationType,
                'category' => $type,
                'title' => $title,
                'body' => $body,
                'channel' => 'push',
                'priority' => 'high',
                'reference_type' => $notifiable ? get_class($notifiable) : null,
                'reference_id' => $notifiable ? (string) $notifiable->getKey() : null,
                'screen' => $screen,
                'data' => array_merge($data, [
                    'notification_id' => (string) $notification->id,
                    'from_user_id' => (string) $from->id,
                    'to_user_id' => (string) $to->id,
                    'notifiable_type' => $notifiable ? get_class($notifiable) : null,
                    'notifiable_id' => $notifiable ? (string) $notifiable->getKey() : null,
                    'screen' => $screen,
                    'navigation_screen' => $screen,
                ]),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to create AppNotification in NotifyUserService', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            SendPushNotificationJob::dispatch(
                $to,
                $title,
                $body,
                [
                    'type' => $type,
                    'notification_id' => (string) $notification->id,
                    'from_user_id' => (string) $from->id,
                    'to_user_id' => (string) $to->id,
                    'notifiable_type' => $notificationPayload['notifiable_type'],
                    'notifiable_id' => $notificationPayload['notifiable_id'],
                    'data' => $data,
                ]
            );
        } catch (Throwable $e) {
            Log::error('Failed to dispatch push notification job', [
                'notification_id' => (string) $notification->id,
                'type' => $type,
                'from_user_id' => (string) $from->id,
                'to_user_id' => (string) $to->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $notification;
    }
}
