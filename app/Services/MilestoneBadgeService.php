<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MilestoneBadge;
use App\Models\User;
use App\Models\UserMilestoneBadge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MilestoneBadgeService
{
    public function calculateForUserId(string $userId): void
    {
        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $this->calculateForUser($user);
    }

    public function calculateForUser(User $user): void
    {
        if (! Schema::hasTable('milestone_badges') || ! Schema::hasTable('user_milestone_badges')) {
            return;
        }

        $lifeImpactCount = (int) ($user->life_impacted_count ?? 0);
        $coinsBalance = (int) ($user->coins_balance ?? 0);
        $membersIntroducedCount = (int) ($user->members_introduced_count ?? 0);

        $categories = [
            MilestoneBadge::TYPE_LIFE_IMPACT => $lifeImpactCount,
            MilestoneBadge::TYPE_COINS => $coinsBalance,
            MilestoneBadge::TYPE_MEMBER_INTRODUCTION => $membersIntroducedCount,
        ];

        DB::transaction(function () use ($user, $categories): void {
            foreach ($categories as $type => $currentValue) {
                $badges = MilestoneBadge::query()
                    ->where('type', $type)
                    ->where('is_active', true)
                    ->orderBy('required_count', 'asc')
                    ->get();

                foreach ($badges as $badge) {
                    $existingRecord = UserMilestoneBadge::query()
                        ->where('user_id', $user->id)
                        ->where('badge_id', $badge->id)
                        ->first();

                    if ($currentValue >= $badge->required_count) {
                        if ($existingRecord) {
                            if ($existingRecord->status !== UserMilestoneBadge::STATUS_EARNED) {
                                $existingRecord->update([
                                    'status' => UserMilestoneBadge::STATUS_EARNED,
                                    'achieved_count' => $currentValue,
                                    'earned_at' => now(),
                                    'revoked_at' => null,
                                ]);
                            } else {
                                $existingRecord->update([
                                    'achieved_count' => $currentValue,
                                ]);
                            }
                        } else {
                            UserMilestoneBadge::create([
                                'user_id' => $user->id,
                                'badge_id' => $badge->id,
                                'milestone_type' => $type,
                                'achieved_count' => $currentValue,
                                'status' => UserMilestoneBadge::STATUS_EARNED,
                                'earned_at' => now(),
                                'revoked_at' => null,
                            ]);
                        }
                    } else {
                        if ($existingRecord && $existingRecord->status === UserMilestoneBadge::STATUS_EARNED) {
                            $existingRecord->update([
                                'status' => UserMilestoneBadge::STATUS_REVOKED,
                                'revoked_at' => now(),
                                'achieved_count' => $currentValue,
                            ]);
                        }
                    }
                }
            }
        });
    }
}
