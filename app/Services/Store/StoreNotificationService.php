<?php

namespace App\Services\Store;

use App\Models\NotificationPreference;
use App\Models\Store\NotificationEvent;
use App\Models\Store\NotificationLog;
use App\Models\User;
use App\Models\UserPushToken;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class StoreNotificationService
{
    public function getUserNotifications(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return NotificationEvent::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getNotification(User $user, string $id): NotificationEvent
    {
        return NotificationEvent::where('id', $id)->where('user_id', $user->id)->firstOrFail();
    }

    public function markAsRead(User $user, string $id): bool
    {
        $event = $this->getNotification($user, $id);
        $event->update(['status' => 'READ']);

        NotificationLog::where('event_id', $event->id)->where('user_id', $user->id)->update(['read_at' => now()]);

        return true;
    }

    public function registerDevice(User $user, array $data): bool
    {
        if (class_exists(UserPushToken::class)) {
            UserPushToken::updateOrCreate(
                ['user_id' => $user->id, 'device_id' => $data['device_id']],
                [
                    'token' => $data['push_token'] ?? $data['token'] ?? '',
                    'platform' => $data['platform'] ?? 'ANDROID',
                    'app_version' => $data['app_version'] ?? null,
                    'updated_at' => now(),
                ]
            );
        }

        return true;
    }

    public function removeDevice(User $user, string $deviceId): bool
    {
        if (class_exists(UserPushToken::class)) {
            UserPushToken::where('user_id', $user->id)->where('device_id', $deviceId)->delete();
        }

        return true;
    }
}
