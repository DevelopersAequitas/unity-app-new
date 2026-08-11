<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CoinsLedger;
use App\Models\User;
use App\Models\UserMilestoneBadge;
use App\Support\CoinMilestoneResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class CoinMilestoneController extends Controller
{
    /**
     * Get the latest/highest milestone rank and progress toward the next one.
     */
    public function latest(string $userId): JsonResponse
    {
        $user = User::find($userId);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $currentCoins = (int) ($user->coins_balance ?? 0);
        $milestones = CoinMilestoneResolver::getMilestones();

        $currentMilestoneData = null;
        $nextMilestoneData = null;
        $lastMilestoneThreshold = 0;

        $resolvedCurrent = CoinMilestoneResolver::resolve($currentCoins);
        if ($resolvedCurrent['title'] !== null) {
            foreach ($milestones as $milestone) {
                if ($currentCoins >= (int) $milestone['threshold']) {
                    $currentMilestoneData = [
                        'medal_rank' => $milestone['medal_rank'],
                        'title' => $milestone['title'],
                        'meaning' => $milestone['meaning'],
                        'threshold' => (int) $milestone['threshold'],
                    ];
                    $lastMilestoneThreshold = (int) $milestone['threshold'];
                } else {
                    $nextMilestoneData = $milestone;
                    break;
                }
            }
        } else {
            if (! empty($milestones)) {
                $nextMilestoneData = $milestones[0];
            }
        }

        $nextMilestoneResponse = null;
        if ($nextMilestoneData) {
            $threshold = (int) $nextMilestoneData['threshold'];
            $coinsNeeded = max(0, $threshold - $currentCoins);

            $prevThreshold = $lastMilestoneThreshold;
            $range = $threshold - $prevThreshold;
            $progressInRange = max(0, $currentCoins - $prevThreshold);

            $progressPercentage = $range > 0 ? round(($progressInRange / $range) * 100, 2) : 0;
            $progressPercentage = min(100.0, $progressPercentage);

            $nextMilestoneResponse = [
                'medal_rank' => $nextMilestoneData['medal_rank'],
                'title' => $nextMilestoneData['title'],
                'threshold' => $threshold,
                'coins_needed' => $coinsNeeded,
                'progress_percentage' => $progressPercentage,
            ];
        }

        $dynamicBadges = $this->getLatestDynamicBadges($user);

        return response()->json([
            'success' => true,
            'message' => 'Latest coin milestone and progress fetched successfully',
            'data' => [
                'current_coins_balance' => $currentCoins,
                'current_milestone' => $currentMilestoneData,
                'next_milestone' => $nextMilestoneResponse,
                'badges' => $dynamicBadges,
            ],
        ]);
    }

    /**
     * Get the full milestones history for a user.
     */
    public function history(string $userId): JsonResponse
    {
        $user = User::find($userId);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $ledgerHistory = [];
        if (Schema::hasTable('coins_ledger')) {
            $ledgerHistory = CoinsLedger::query()
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function (CoinsLedger $record): array {
                    $resolved = CoinMilestoneResolver::resolve($record->balance_after);

                    return [
                        'transaction_id' => $record->transaction_id,
                        'amount' => (int) $record->amount,
                        'balance_after' => (int) $record->balance_after,
                        'reference' => $record->reference,
                        'medal_rank' => $resolved['medal_rank'],
                        'title' => $resolved['title'],
                        'meaning' => $resolved['meaning'],
                        'created_at' => $record->created_at?->toIso8601String(),
                    ];
                });
        }

        $dynamicBadgeHistory = $this->getDynamicBadgeHistory($user);

        return response()->json([
            'success' => true,
            'message' => 'Coin milestones history fetched successfully',
            'data' => $ledgerHistory,
            'badges' => $dynamicBadgeHistory,
        ]);
    }

    private function getLatestDynamicBadges(User $user): array
    {
        $categorized = [
            'life_impact' => [],
            'coins' => [],
            'member_introduction' => [],
        ];

        if (! Schema::hasTable('user_milestone_badges') || ! Schema::hasTable('milestone_badges')) {
            return $categorized;
        }

        $earnedBadges = UserMilestoneBadge::query()
            ->with('badge')
            ->where('user_id', $user->id)
            ->where('status', UserMilestoneBadge::STATUS_EARNED)
            ->get();

        foreach ($earnedBadges as $userBadge) {
            $badge = $userBadge->badge;
            if (! $badge || ! $badge->is_active) {
                continue;
            }

            $type = $userBadge->milestone_type ?? $badge->type;
            if (! isset($categorized[$type])) {
                $categorized[$type] = [];
            }

            $categorized[$type][] = [
                'badge_id' => $badge->id,
                'title' => $badge->title,
                'type' => $type,
                'description' => $badge->description,
                'badge_image_url' => $badge->badge_image_url,
                'required_count' => (int) $badge->required_count,
                'achieved_count' => (int) $userBadge->achieved_count,
                'status' => $userBadge->status,
                'earned_at' => $userBadge->earned_at?->toIso8601String(),
            ];
        }

        return $categorized;
    }

    private function getDynamicBadgeHistory(User $user): array
    {
        $categorized = [
            'life_impact' => [],
            'coins' => [],
            'member_introduction' => [],
        ];

        if (! Schema::hasTable('user_milestone_badges') || ! Schema::hasTable('milestone_badges')) {
            return $categorized;
        }

        $allBadges = UserMilestoneBadge::query()
            ->with('badge')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($allBadges as $userBadge) {
            $badge = $userBadge->badge;
            if (! $badge) {
                continue;
            }

            $type = $userBadge->milestone_type ?? $badge->type;
            if (! isset($categorized[$type])) {
                $categorized[$type] = [];
            }

            $categorized[$type][] = [
                'badge_id' => $badge->id,
                'title' => $badge->title,
                'type' => $type,
                'description' => $badge->description,
                'badge_image_url' => $badge->badge_image_url,
                'required_count' => (int) $badge->required_count,
                'achieved_count' => (int) $userBadge->achieved_count,
                'status' => $userBadge->status,
                'earned_at' => $userBadge->earned_at?->toIso8601String(),
                'revoked_at' => $userBadge->revoked_at?->toIso8601String(),
            ];
        }

        return $categorized;
    }
}
