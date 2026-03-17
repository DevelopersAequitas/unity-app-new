<?php

namespace App\Services\Circulars;

use App\Jobs\SendPushNotificationJob;
use App\Models\Circular;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

class CircularNotificationService
{
    public function dispatchForCircular(Circular $circular, bool $forceResend = false): bool
    {
        if (! $circular->send_push_notification || $circular->status !== 'published') {
            return false;
        }

        if (! $forceResend && $circular->notification_sent_at) {
            return false;
        }

        $title = (string) $circular->title;
        $body = $this->notificationBody($circular);

        User::query()
            ->where('status', 'active')
            ->chunk(200, function ($users) use ($circular, $title, $body): void {
                foreach ($users as $user) {
                    $notification = Notification::create([
                        'user_id' => $user->id,
                        'type' => 'activity_update',
                        'payload' => [
                            'notification_type' => 'circular_published',
                            'title' => $title,
                            'body' => $body,
                            'to_user_id' => (string) $user->id,
                            'notifiable_type' => Circular::class,
                            'notifiable_id' => (string) $circular->id,
                            'data' => [
                                'action' => 'open_circular_detail',
                                'circular_id' => (string) $circular->id,
                            ],
                        ],
                        'is_read' => false,
                        'created_at' => now(),
                        'read_at' => null,
                    ]);

                    SendPushNotificationJob::dispatch($user, $title, $body, [
                        'type' => 'circular_published',
                        'notification_id' => (string) $notification->id,
                        'circular_id' => (string) $circular->id,
                        'action' => 'open_circular_detail',
                    ]);
                }
            });

        $circular->forceFill([
            'notification_sent_at' => now(),
        ])->save();

        return true;
    }

    private function notificationBody(Circular $circular): string
    {
        if (! empty($circular->summary)) {
            return (string) $circular->summary;
        }

        return Str::limit(strip_tags((string) $circular->content), 140, '...');
    }
}
