<?php

namespace App\Services;

use App\Events\MemberOnlineStatusUpdated;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class OnlineStatusService
{
    private const STALE_SECONDS = 120;

    public function heartbeat(User $user, bool $broadcast = true): array
    {
        $now = now()->utc();

        $user->forceFill([
            'is_online' => true,
            'last_seen_at' => $now,
        ])->save();

        $this->putCacheStatus((string) $user->id, true, $now);

        $payload = $this->formatUserStatus($user->fresh(['id', 'is_online', 'last_seen_at']));

        if ($broadcast) {
            broadcast(new MemberOnlineStatusUpdated($payload))->toOthers();
        }

        return $payload;
    }

    public function markOffline(User $user, bool $broadcast = true): array
    {
        $now = now()->utc();

        $user->forceFill([
            'is_online' => false,
            'last_seen_at' => $now,
        ])->save();

        $this->forgetCacheStatus((string) $user->id);

        $payload = $this->formatUserStatus($user->fresh(['id', 'is_online', 'last_seen_at']));

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

    public function markStaleUsersOffline(): int
    {
        $threshold = now()->subSeconds(self::STALE_SECONDS);

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

    private function formatUserStatus(User $user): array
    {
        $lastSeenAt = $user->last_seen_at;
        $isOnline = (bool) $user->is_online;

        if ($isOnline && $lastSeenAt instanceof CarbonInterface && $lastSeenAt->lt(now()->subSeconds(self::STALE_SECONDS))) {
            $isOnline = false;
        }

        return [
            'user_id' => (string) $user->id,
            'is_online' => $isOnline,
            'last_seen_at' => $lastSeenAt?->copy()->utc()->toIso8601String(),
            'last_seen_text' => $this->lastSeenText($isOnline, $lastSeenAt),
        ];
    }

    private function lastSeenText(bool $isOnline, ?CarbonInterface $lastSeenAt): string
    {
        if ($isOnline) {
            return 'Online';
        }

        if (! $lastSeenAt) {
            return 'Last seen recently';
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
