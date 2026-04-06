<?php

namespace App\Services\Blocks;

use App\Models\PeerBlock;
use App\Models\User;
use RuntimeException;

class PeerBlockService
{
    public const INTERACTION_BLOCKED_MESSAGE = 'You cannot interact with this peer.';

    public function block(User $blocker, User $blocked, ?string $reason = null): PeerBlock
    {
        $this->assertNotSelf((string) $blocker->id, (string) $blocked->id);

        return PeerBlock::query()->firstOrCreate(
            [
                'blocker_user_id' => (string) $blocker->id,
                'blocked_user_id' => (string) $blocked->id,
            ],
            [
                'reason' => $this->normalizeReason($reason),
            ]
        );
    }

    public function unblock(User $blocker, User $blocked): bool
    {
        if ((string) $blocker->id === (string) $blocked->id) {
            return false;
        }

        $deleted = PeerBlock::query()
            ->where('blocker_user_id', (string) $blocker->id)
            ->where('blocked_user_id', (string) $blocked->id)
            ->delete();

        return $deleted > 0;
    }

    public function hasBlocked(string $blockerId, string $blockedId): bool
    {
        return PeerBlock::query()
            ->where('blocker_user_id', $blockerId)
            ->where('blocked_user_id', $blockedId)
            ->exists();
    }

    public function isBlockedEitherWay(string $userAId, string $userBId): bool
    {
        if ($userAId === $userBId) {
            return false;
        }

        return PeerBlock::query()
            ->where(function ($query) use ($userAId, $userBId): void {
                $query->where('blocker_user_id', $userAId)
                    ->where('blocked_user_id', $userBId);
            })
            ->orWhere(function ($query) use ($userAId, $userBId): void {
                $query->where('blocker_user_id', $userBId)
                    ->where('blocked_user_id', $userAId);
            })
            ->exists();
    }

    public function assertCanInteract(string $userAId, string $userBId): void
    {
        if ($this->isBlockedEitherWay($userAId, $userBId)) {
            throw new RuntimeException(self::INTERACTION_BLOCKED_MESSAGE);
        }
    }

    public function blockedUserIdsFor(string $userId): array
    {
        return PeerBlock::query()
            ->where('blocker_user_id', $userId)
            ->pluck('blocked_user_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    public function usersWhoBlockedMeIdsFor(string $userId): array
    {
        return PeerBlock::query()
            ->where('blocked_user_id', $userId)
            ->pluck('blocker_user_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    private function assertNotSelf(string $userAId, string $userBId): void
    {
        if ($userAId === $userBId) {
            throw new RuntimeException('You cannot block yourself.');
        }
    }

    private function normalizeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $reason = trim($reason);

        return $reason === '' ? null : $reason;
    }
}
