<?php

namespace App\Services\LifeImpact;

use App\Models\LifeImpactHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LifeImpactService
{
    public function incrementAndLog(
        string $userId,
        int $points,
        string $activityType,
        string $title,
        ?string $triggeredByUserId = null,
        ?string $activityId = null,
        ?string $description = null,
        ?array $meta = null,
    ): int {
        $impactValue = (int) $points;

        if ($impactValue <= 0) {
            return $this->getCurrentTotal($userId);
        }

        return (int) DB::transaction(function () use ($userId, $impactValue, $activityType, $title, $triggeredByUserId, $activityId, $description, $meta) {
            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'life_impacted_count' => DB::raw('COALESCE(life_impacted_count, 0) + ' . $impactValue),
                    'updated_at' => now(),
                ]);

            LifeImpactHistory::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'triggered_by_user_id' => $triggeredByUserId,
                'activity_type' => $activityType,
                'activity_id' => $activityId,
                'impact_value' => $impactValue,
                'title' => $title,
                'description' => $description,
                'meta' => $meta,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->getCurrentTotal($userId);
        });
    }

    public function getCurrentTotal(string $userId): int
    {
        return (int) (DB::table('users')->where('id', $userId)->value('life_impacted_count') ?? 0);
    }
}
