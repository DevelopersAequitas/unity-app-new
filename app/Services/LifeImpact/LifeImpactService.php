<?php

namespace App\Services\LifeImpact;

use App\Models\LifeImpactHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LifeImpactService
{
    public function addImpact(
        string $userId,
        ?string $triggeredByUserId,
        string $activityType,
        ?string $activityId = null,
        int $impactValue = 0,
        string $title = '',
        ?string $description = null,
        array $meta = [],
    ): int {
        $impactValue = (int) $impactValue;
        $activityId = (is_string($activityId) && Str::isUuid($activityId))
            ? $activityId
            : null;

        if ($impactValue <= 0) {
            return $this->recalculateUserLifeImpact($userId);
        }

        return (int) DB::transaction(function () use ($userId, $triggeredByUserId, $activityType, $activityId, $impactValue, $title, $description, $meta) {
            $existing = null;

            if ($activityId !== null) {
                $existing = LifeImpactHistory::query()
                    ->where('user_id', $userId)
                    ->where('activity_type', $activityType)
                    ->where('activity_id', $activityId)
                    ->first();
            }

            if ($existing) {
                $existing->fill([
                    'triggered_by_user_id' => $triggeredByUserId,
                    'impact_value' => $impactValue,
                    'title' => $title,
                    'description' => $description,
                    'meta' => $meta ?: null,
                ]);
                $existing->save();

                Log::info('life_impact.duplicate_prevented', [
                    'performed_by_user_id' => $userId,
                    'activity_type' => $activityType,
                    'activity_id' => $activityId,
                    'life_impact_history_id' => (string) $existing->id,
                ]);
            } else {
                LifeImpactHistory::query()->create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'triggered_by_user_id' => $triggeredByUserId,
                    'activity_type' => $activityType,
                    'activity_id' => $activityId,
                    'impact_value' => $impactValue,
                    'title' => $title,
                    'description' => $description,
                    'meta' => $meta ?: null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info('life_impact.added', [
                    'performed_by_user_id' => $userId,
                    'activity_type' => $activityType,
                    'activity_id' => $activityId,
                    'impact_value' => $impactValue,
                ]);
            }

            return $this->recalculateUserLifeImpact($userId);
        });
    }

    public function addLifeImpact(
        string $userId,
        ?string $triggeredByUserId,
        string $activityType,
        ?string $activityId = null,
        int $impactValue = 0,
        string $title = '',
        ?string $description = null,
        array $meta = [],
    ): int {
        return $this->addImpact(
            $userId,
            $triggeredByUserId,
            $activityType,
            $activityId,
            $impactValue,
            $title,
            $description,
            $meta,
        );
    }

    public function removeImpactBySource(string $userId, string $activityType, ?string $activityId): int
    {
        if (! is_string($activityId) || ! Str::isUuid($activityId)) {
            Log::warning('life_impact.source_not_found', [
                'performed_by_user_id' => $userId,
                'activity_type' => $activityType,
                'activity_id' => $activityId,
            ]);

            return $this->recalculateUserLifeImpact($userId);
        }

        return (int) DB::transaction(function () use ($userId, $activityType, $activityId) {
            $query = LifeImpactHistory::query()
                ->where('user_id', $userId)
                ->where('activity_type', $activityType)
                ->where('activity_id', $activityId);

            $matched = (clone $query)->count();
            $removed = $query->delete();

            Log::info('life_impact.removed', [
                'performed_by_user_id' => $userId,
                'activity_type' => $activityType,
                'activity_id' => $activityId,
                'matched_rows' => $matched,
                'removed_rows' => $removed,
            ]);

            return $this->recalculateUserLifeImpact($userId);
        });
    }

    public function getValidImpactTotalForUser(User|string $userOrId): int
    {
        $userId = $userOrId instanceof User ? (string) $userOrId->id : (string) $userOrId;

        return (int) $this->validHistoryQueryForUser($userId)->sum('impact_value');
    }

    public function recalculateUserLifeImpact(User|string $userOrId): int
    {
        $userId = $userOrId instanceof User ? (string) $userOrId->id : (string) $userOrId;

        $oldTotal = (int) (DB::table('users')->where('id', $userId)->value('life_impacted_count') ?? 0);
        $newTotal = $this->getValidImpactTotalForUser($userId);

        DB::table('users')
            ->where('id', $userId)
            ->update([
                'life_impacted_count' => $newTotal,
                'updated_at' => now(),
            ]);

        Log::info('life_impact.total_recalculated', [
            'performed_by_user_id' => $userId,
            'old_total' => $oldTotal,
            'new_total' => $newTotal,
        ]);

        return $newTotal;
    }

    public function recalculateUserLifeImpactedCount(User|string $userOrId): int
    {
        return $this->recalculateUserLifeImpact($userOrId);
    }

    public function getCurrentTotal(string $userId): int
    {
        return $this->recalculateUserLifeImpact($userId);
    }

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
        return $this->addImpact(
            $userId,
            $triggeredByUserId,
            $activityType,
            $activityId,
            (int) $points,
            $title,
            $description,
            $meta ?? []
        );
    }

    private function validHistoryQueryForUser(string $userId): Builder
    {
        $query = LifeImpactHistory::query()->where('user_id', $userId);

        if (Schema::hasColumn('life_impact_histories', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('life_impact_histories', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('life_impact_histories', 'status')) {
            $query->where('status', 'active');
        }

        return $query;
    }
}
