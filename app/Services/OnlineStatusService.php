<?php

namespace App\Services;

use App\Events\MemberOnlineStatusUpdated;
use App\Models\Connection;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

class OnlineStatusService
{
    public function heartbeat(User $user, bool $broadcast = true): array
    {
        return $this->markOnline($user, $broadcast);
    }

    public function markOnline(User $user, bool $broadcast = true): array
    {
        $now = now()->utc();
        $userId = (string) $user->id;

        $user->forceFill([
            'is_online' => true,
            'last_seen_at' => $now,
        ])->save();

        $this->putCacheStatus($userId, true, $now);

        $payload = $this->formatUserStatus($user->fresh());

        if ($broadcast) {
            broadcast(new MemberOnlineStatusUpdated($payload))->toOthers();
        }

        return $payload;
    }

    public function markOffline(User $user, bool $broadcast = true, ?string $lastSeenTextOverride = null): array
    {
        $now = now()->utc();
        $userId = (string) $user->id;

        $user->forceFill([
            'is_online' => false,
            'last_seen_at' => $now,
        ])->save();

        $this->forgetCacheStatus($userId);

        $payload = $this->formatUserStatus($user->fresh(), $lastSeenTextOverride);

        if ($broadcast) {
            broadcast(new MemberOnlineStatusUpdated($payload))->toOthers();
        }

        return $payload;
    }

    public function getStatus(string $userId): array
    {
        return $this->formatUserStatus($this->resolveUser($userId));
    }

    public function getStatuses(array $userIds): array
    {
        return User::query()->whereIn('id', $userIds)->get(['id', 'is_online', 'last_seen_at'])
            ->map(fn (User $user) => $this->formatUserStatus($user))
            ->all();
    }

    public function getConnectionStatusesFor(User $authUser): array
    {
        $connections = Connection::query()
            ->with([
                'requester:id,display_name,company_name,profile_photo_url,profile_photo_id,profile_photo_file_id,is_online,last_seen_at',
                'addressee:id,display_name,company_name,profile_photo_url,profile_photo_id,profile_photo_file_id,is_online,last_seen_at',
            ])
            ->where('is_approved', true)
            ->where(function ($query) use ($authUser) {
                $query->where('requester_id', $authUser->id)
                    ->orWhere('addressee_id', $authUser->id);
            })
            ->get();

        return $connections->map(function (Connection $connection) use ($authUser) {
            $otherUser = (string) $connection->requester_id === (string) $authUser->id
                ? $connection->addressee
                : $connection->requester;

            if (! $otherUser) {
                return null;
            }

            $status = $this->formatUserStatus($otherUser);
            $fileId = $otherUser->profile_photo_file_id ?? $otherUser->profile_photo_id ?? null;

            return array_merge($status, [
                'display_name' => $otherUser->display_name,
                'company_name' => $otherUser->company_name,
                'profile_photo_url' => $fileId ? url('/api/v1/files/' . $fileId) : $otherUser->profile_photo_url,
            ]);
        })->filter()->values()->all();
    }

    public function markStaleUsersOffline(): int
    {
        $threshold = now()->subSeconds(120);

        $count = User::query()
            ->where('is_online', true)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', $threshold)
            ->update(['is_online' => false]);

        return $count;
    }

    private function resolveUser(string $userId): User
    {
        return User::query()->findOrFail($userId, ['id', 'is_online', 'last_seen_at']);
    }

    public function updateOnlineStatus(User $user, bool $isOnline, bool $broadcast = true): array
    {
        return $isOnline
            ? $this->markOnline($user, $broadcast)
            : $this->markOffline($user, $broadcast, 'Last seen just now');
    }

    private function formatUserStatus(User $user, ?string $lastSeenTextOverride = null): array
    {
        $lastSeenAt = $user->last_seen_at;
        $isOnline = (bool) $user->is_online;

        return [
            'user_id' => (string) $user->id,
            'is_online' => $isOnline,
            'last_seen_at' => $lastSeenAt?->copy()->utc()->toIso8601String(),
            'last_seen_text' => $lastSeenTextOverride ?? $this->lastSeenText($isOnline, $lastSeenAt),
        ];
    }

    private function lastSeenText(bool $isOnline, ?CarbonInterface $lastSeenAt): string
    {
        if ($isOnline) {
            return 'Online';
        }

        if (! $lastSeenAt) {
            return 'Offline';
        }

        return 'Last seen ' . $lastSeenAt->diffForHumans(now(), [
            'parts' => 2,
            'short' => false,
            'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW,
        ]);
    }

    private function putCacheStatus(string $userId, bool $isOnline, CarbonInterface $lastSeenAt): void
    {
        try {
            Cache::store(config('cache.default'))->put(
                "member-online-status:{$userId}",
                ['is_online' => $isOnline, 'last_seen_at' => $lastSeenAt->toIso8601String()],
                now()->addMinutes(10)
            );
        } catch (\Throwable) {
        }
    }

    private function forgetCacheStatus(string $userId): void
    {
        try {
            Cache::store(config('cache.default'))->forget("member-online-status:{$userId}");
        } catch (\Throwable) {
        }
    }
}
