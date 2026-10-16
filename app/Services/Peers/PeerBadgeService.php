<?php

declare(strict_types=1);

namespace App\Services\Peers;

use App\Models\MilestoneBadge;
use App\Models\User;
use App\Models\UserMilestoneBadge;
use App\Services\Blocks\PeerBlockService;
use App\Services\MilestoneBadgeService;
use App\Services\ProfileVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class PeerBadgeService
{
    public function __construct(
        private readonly PeerBlockService $peerBlockService,
        private readonly ProfileVisibilityService $profileVisibilityService,
        private readonly MilestoneBadgeService $milestoneBadgeService,
    ) {}

    /**
     * Get badges for a specific peer profile.
     *
     * @param  array<string, mixed>  $filters
     * @return array{peer_found: bool, restricted: bool, data: array{total_count: int, badges: array<int, array<string, mixed>>}|null}
     */
    public function getPeerBadges(User $viewer, string $peerId, array $filters = []): array
    {
        $peer = User::query()
            ->where('id', $peerId)
            ->whereNull('deleted_at')
            ->first();

        if (! $peer) {
            return [
                'peer_found' => false,
                'restricted' => false,
                'data' => null,
            ];
        }

        if ($this->peerBlockService->isBlockedEitherWay((string) $viewer->id, (string) $peer->id)) {
            return [
                'peer_found' => false,
                'restricted' => false,
                'data' => null,
            ];
        }

        if (! $this->profileVisibilityService->canView($viewer, $peer)) {
            return [
                'peer_found' => true,
                'restricted' => true,
                'data' => null,
            ];
        }

        // Auto-recalculate to guarantee peer's latest milestones/honours are synced
        try {
            $this->milestoneBadgeService->calculateForUser($peer);
        } catch (\Throwable $e) {
            Log::warning('[PeerBadgeService] calculateForUser skipped: '.$e->getMessage(), [
                'peer_id' => $peer->id,
            ]);
        }

        $status = strtolower(trim((string) ($filters['status'] ?? 'earned')));
        if (! in_array($status, ['earned', 'locked', 'all'], true)) {
            $status = 'earned';
        }

        $limit = isset($filters['limit']) ? max(1, min((int) $filters['limit'], 100)) : 50;
        $sort = strtolower(trim((string) ($filters['sort'] ?? 'earned_at:desc')));

        [$sortField, $sortDirection] = $this->parseSort($sort);

        if ($status === 'earned') {
            return [
                'peer_found' => true,
                'restricted' => false,
                'data' => $this->getEarnedBadges($peer, $sortField, $sortDirection, $limit),
            ];
        }

        if ($status === 'locked') {
            return [
                'peer_found' => true,
                'restricted' => false,
                'data' => $this->getLockedBadges($peer, $sortField, $sortDirection, $limit),
            ];
        }

        return [
            'peer_found' => true,
            'restricted' => false,
            'data' => $this->getAllBadges($peer, $sortField, $sortDirection, $limit),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseSort(string $sort): array
    {
        $parts = explode(':', $sort);
        $field = $parts[0] ?? 'earned_at';
        $direction = strtolower($parts[1] ?? 'desc');

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $allowedFields = ['earned_at', 'created_at', 'required_count', 'sort_order'];
        if (! in_array($field, $allowedFields, true)) {
            $field = 'earned_at';
        }

        return [$field, $direction];
    }

    /**
     * @return array{total_count: int, badges: array<int, array<string, mixed>>}
     */
    private function getEarnedBadges(User $peer, string $sortField, string $sortDirection, int $limit): array
    {
        $query = UserMilestoneBadge::query()
            ->with('badge')
            ->where('user_id', $peer->id)
            ->where('status', UserMilestoneBadge::STATUS_EARNED)
            ->whereNull('revoked_at')
            ->whereHas('badge', function (Builder $q): void {
                $q->where('is_active', true);
            });

        if ($sortField === 'required_count' || $sortField === 'sort_order') {
            $query->join('milestone_badges', 'user_milestone_badges.badge_id', '=', 'milestone_badges.id')
                ->select('user_milestone_badges.*')
                ->orderBy("milestone_badges.{$sortField}", $sortDirection);
        } else {
            $query->orderBy($sortField, $sortDirection);
        }

        $totalCount = (clone $query)->count();
        $userBadges = $query->limit($limit)->get();

        $badges = [];
        foreach ($userBadges as $userBadge) {
            $badge = $userBadge->badge;
            if (! $badge) {
                continue;
            }

            $badges[] = [
                'id' => (string) $userBadge->id,
                'badge_id' => (string) $badge->id,
                'title' => (string) $badge->title,
                'name' => (string) $badge->title,
                'description' => $badge->description,
                'badge_image_url' => $badge->badge_image_url,
                'type' => (string) ($badge->type ?? $userBadge->milestone_type ?? ''),
                'status' => UserMilestoneBadge::STATUS_EARNED,
                'required_count' => (int) $badge->required_count,
                'achieved_count' => (int) $userBadge->achieved_count,
                'earned_at' => $userBadge->earned_at?->toIso8601String(),
            ];
        }

        return [
            'total_count' => $totalCount,
            'badges' => $badges,
        ];
    }

    /**
     * @return array{total_count: int, badges: array<int, array<string, mixed>>}
     */
    private function getLockedBadges(User $peer, string $sortField, string $sortDirection, int $limit): array
    {
        $earnedBadgeIds = UserMilestoneBadge::query()
            ->where('user_id', $peer->id)
            ->where('status', UserMilestoneBadge::STATUS_EARNED)
            ->whereNull('revoked_at')
            ->pluck('badge_id')
            ->all();

        $query = MilestoneBadge::query()
            ->where('is_active', true)
            ->whereNotIn('id', $earnedBadgeIds);

        $orderCol = in_array($sortField, ['required_count', 'sort_order', 'created_at'], true) ? $sortField : 'sort_order';
        $query->orderBy($orderCol, $sortDirection);

        $totalCount = (clone $query)->count();
        $lockedBadges = $query->limit($limit)->get();

        $badges = [];
        foreach ($lockedBadges as $badge) {
            $badges[] = [
                'id' => (string) $badge->id,
                'badge_id' => (string) $badge->id,
                'title' => (string) $badge->title,
                'name' => (string) $badge->title,
                'description' => $badge->description,
                'badge_image_url' => $badge->badge_image_url,
                'type' => (string) $badge->type,
                'status' => 'locked',
                'required_count' => (int) $badge->required_count,
                'achieved_count' => 0,
                'earned_at' => null,
            ];
        }

        return [
            'total_count' => $totalCount,
            'badges' => $badges,
        ];
    }

    /**
     * @return array{total_count: int, badges: array<int, array<string, mixed>>}
     */
    private function getAllBadges(User $peer, string $sortField, string $sortDirection, int $limit): array
    {
        $userBadgesMap = UserMilestoneBadge::query()
            ->where('user_id', $peer->id)
            ->where('status', UserMilestoneBadge::STATUS_EARNED)
            ->whereNull('revoked_at')
            ->get()
            ->keyBy('badge_id');

        $query = MilestoneBadge::query()
            ->where('is_active', true);

        $orderCol = in_array($sortField, ['required_count', 'sort_order', 'created_at'], true) ? $sortField : 'sort_order';
        $query->orderBy($orderCol, $sortDirection);

        $totalCount = (clone $query)->count();
        $allBadges = $query->limit($limit)->get();

        $badges = [];
        foreach ($allBadges as $badge) {
            $userBadge = $userBadgesMap->get($badge->id);
            $isEarned = $userBadge !== null;

            $badges[] = [
                'id' => (string) ($userBadge?->id ?? $badge->id),
                'badge_id' => (string) $badge->id,
                'title' => (string) $badge->title,
                'name' => (string) $badge->title,
                'description' => $badge->description,
                'badge_image_url' => $badge->badge_image_url,
                'type' => (string) $badge->type,
                'status' => $isEarned ? UserMilestoneBadge::STATUS_EARNED : 'locked',
                'required_count' => (int) $badge->required_count,
                'achieved_count' => $isEarned ? (int) $userBadge->achieved_count : 0,
                'earned_at' => $userBadge?->earned_at?->toIso8601String(),
            ];
        }

        return [
            'total_count' => $totalCount,
            'badges' => $badges,
        ];
    }
}
