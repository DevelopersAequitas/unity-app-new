<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AwardCoinsHistory;
use App\Models\User;
use App\Support\CoinMilestoneResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoinMilestoneController extends Controller
{
    /**
     * Get the latest/highest milestone rank and progress toward the next one.
     */
    public function latest(string $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Get highest achieved milestone record from database
        $latestRecord = AwardCoinsHistory::where('user_id', $user->id)
            ->orderBy('coins_earned', 'desc')
            ->first();

        $currentCoins = (int) ($user->coins_balance ?? 0);
        $milestones = CoinMilestoneResolver::getMilestones();
        
        $currentMilestoneData = null;
        $nextMilestoneData = null;

        if ($latestRecord) {
            $currentMilestoneData = [
                'medal_rank' => $latestRecord->medal_rank,
                'title' => $latestRecord->title,
                'meaning' => $latestRecord->meaning,
                'threshold' => (int) $latestRecord->coins_earned,
                'achieved_at' => $latestRecord->achieved_at,
            ];

            // Find the next milestone threshold
            foreach ($milestones as $milestone) {
                if ((int) $milestone['threshold'] > (int) $latestRecord->coins_earned) {
                    $nextMilestoneData = $milestone;
                    break;
                }
            }
        } else {
            // No milestone achieved yet. Next is the first milestone.
            if (!empty($milestones)) {
                $nextMilestoneData = $milestones[0];
            }
        }

        $nextMilestoneResponse = null;
        if ($nextMilestoneData) {
            $threshold = (int) $nextMilestoneData['threshold'];
            $coinsNeeded = max(0, $threshold - $currentCoins);
            
            // Calculate progress percentage since last milestone (or from 0 if no milestone achieved yet)
            $prevThreshold = $latestRecord ? (int) $latestRecord->coins_earned : 0;
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

        return response()->json([
            'success' => true,
            'message' => 'Latest coin milestone and progress fetched successfully',
            'data' => [
                'current_coins_balance' => $currentCoins,
                'current_milestone' => $currentMilestoneData,
                'next_milestone' => $nextMilestoneResponse,
            ],
        ]);
    }

    /**
     * Get the full milestones history for a user.
     */
    public function history(string $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $history = AwardCoinsHistory::where('user_id', $user->id)
            ->orderBy('coins_earned', 'asc')
            ->get()
            ->map(function ($record) {
                return [
                    'medal_rank' => $record->medal_rank,
                    'title' => $record->title,
                    'meaning' => $record->meaning,
                    'coins_earned' => (int) $record->coins_earned,
                    'achieved_at' => $record->achieved_at,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Coin milestones history fetched successfully',
            'data' => $history,
        ]);
    }
}
