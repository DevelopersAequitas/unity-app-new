<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\Notifications\AppNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasTable('app_notifications')) {
            return;
        }

        $notifications = Notification::query()
            ->where(function ($query): void {
                $query->where('type', 'like', '%connection%')
                    ->orWhere('payload->notification_type', 'like', '%connection%');
            })
            ->get();

        foreach ($notifications as $notification) {
            $payload = (array) ($notification->payload ?? []);
            $data = (array) ($payload['data'] ?? []);
            $type = (string) ($payload['notification_type'] ?? $notification->type ?? 'connection_request');
            $title = (string) ($payload['title'] ?? 'New Connection Notification');
            $body = (string) ($payload['body'] ?? 'You have a new connection update.');
            $screen = match ($type) {
                'connection_request', 'connection_received' => '/connection-requests',
                'connection_accepted', 'connection' => '/my-connections',
                default => '/connection-requests',
            };

            $exists = AppNotification::where('user_id', $notification->user_id)
                ->where(function ($q) use ($notification, $type): void {
                    $q->where('data->notification_id', (string) $notification->id)
                        ->orWhere(function ($sub) use ($notification, $type): void {
                            $sub->where('category', $type)
                                ->where('created_at', $notification->created_at);
                        });
                })
                ->exists();

            if (! $exists) {
                $referenceId = ! empty($payload['notifiable_id'])
                    ? (string) $payload['notifiable_id']
                    : (! empty($data['request_id']) ? (string) $data['request_id'] : null);

                if ($referenceId !== null && ! Str::isUuid($referenceId)) {
                    $referenceId = null;
                }

                AppNotification::create([
                    'user_id' => $notification->user_id,
                    'type' => $type,
                    'category' => $type,
                    'title' => $title,
                    'body' => $body,
                    'channel' => 'push',
                    'priority' => 'high',
                    'reference_type' => $payload['notifiable_type'] ?? 'App\\Models\\Connection',
                    'reference_id' => $referenceId,
                    'screen' => $screen,
                    'data' => array_merge($data, [
                        'notification_id' => (string) $notification->id,
                        'from_user_id' => (string) ($payload['from_user_id'] ?? ''),
                        'to_user_id' => (string) ($payload['to_user_id'] ?? $notification->user_id),
                        'notifiable_type' => $payload['notifiable_type'] ?? 'App\\Models\\Connection',
                        'notifiable_id' => $referenceId,
                        'screen' => $screen,
                        'navigation_screen' => $screen,
                    ]),
                    'status' => 'delivered',
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at ?: now(),
                    'updated_at' => $notification->created_at ?: now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe no-op
    }
};
