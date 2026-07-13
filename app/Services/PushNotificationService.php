<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public function send(User $toUser, string $title, string $body, array $data = []): void
    {
        SendPushNotificationJob::dispatch($toUser, $title, $body, $data);
    }

    public function storeAndSend(User $toUser, string $title, string $body, array $payload, array $pushData = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $toUser->id,
            'type' => 'activity_update',
            'payload' => $payload,
            'is_read' => false,
            'created_at' => now(),
            'read_at' => null,
        ]);

        try {
            AppNotification::create([
                'user_id' => $toUser->id,
                'type' => 'activity_update',
                'category' => $payload['notification_type'] ?? null,
                'title' => $title,
                'body' => $body,
                'message' => $body,
                'channel' => 'push',
                'priority' => 'medium',
                'reference_type' => $payload['notifiable_type'] ?? null,
                'reference_id' => $payload['notifiable_id'] ?? null,
                'screen' => $payload['notification_type'] ?? 'notifications',
                'data' => array_merge($payload, [
                    'notification_id' => (string) $notification->id,
                ]),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create AppNotification in PushNotificationService', [
                'error' => $e->getMessage(),
            ]);
        }

        $this->send($toUser, $title, $body, $pushData);

        return $notification;
    }
}
