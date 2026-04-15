<?php

namespace App\Services\LifeImpact;

use App\Models\BusinessDeal;
use App\Models\LifeImpactHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LifeImpactService
{
    public function addLifeImpact(
        string $userId,
        ?string $triggeredByUserId,
        string $activityType,
        ?string $activityId = null,
        int $impactValue = 0,
        string $title = '',
        ?string $description = null,
        array $meta = [],
        ?array $activitySnapshot = null,
        string $impactDirection = 'credit',
        string $status = 'active',
        ?string $reversedFromHistoryId = null,
    ): int {
        $impactValue = (int) $impactValue;
        $activityId = (is_string($activityId) && Str::isUuid($activityId))
            ? $activityId
            : null;

        if ($impactValue <= 0) {
            return $this->getCurrentTotal($userId);
        }

        return (int) DB::transaction(function () use ($userId, $impactValue, $activityType, $title, $triggeredByUserId, $activityId, $description, $meta, $activitySnapshot, $impactDirection, $status, $reversedFromHistoryId) {
            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'life_impacted_count' => DB::raw('COALESCE(life_impacted_count, 0) + ' . $impactValue),
                    'updated_at' => now(),
                ]);

            $this->createLifeImpactHistory(
                userId: $userId,
                triggeredByUserId: $triggeredByUserId,
                activityType: $activityType,
                activityId: $activityId,
                impactValue: $impactValue,
                title: $title,
                description: $description,
                meta: $meta,
                activitySnapshot: $activitySnapshot,
                impactDirection: $impactDirection,
                status: $status,
                reversedFromHistoryId: $reversedFromHistoryId,
            );

            return $this->getCurrentTotal($userId);
        });
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
        return $this->addLifeImpact(
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

    public function buildBusinessDealSnapshot(BusinessDeal $deal, bool $deleted = false, ?Carbon $deletedAt = null): array
    {
        return [
            'deal_id' => (string) $deal->id,
            'deal_date' => $deal->deal_date,
            'deal_amount' => $deal->deal_amount,
            'business_type' => $deal->business_type,
            'comment' => $deal->comment,
            'to_user_id' => $deal->to_user_id ? (string) $deal->to_user_id : null,
            'deleted' => $deleted,
            'deleted_at' => $deletedAt?->toISOString(),
        ];
    }

    public function reverseBusinessDealLifeImpact(BusinessDeal $deal, ?string $triggeredByUserId = null): int
    {
        return (int) DB::transaction(function () use ($deal, $triggeredByUserId) {
            $dealId = (string) $deal->id;
            $originalHistory = LifeImpactHistory::query()
                ->where('activity_type', 'business_deal')
                ->where('activity_id', $dealId)
                ->orderBy('created_at')
                ->first();

            if (! $originalHistory) {
                return $this->getCurrentTotal((string) $deal->from_user_id);
            }

            $impactedUserId = (string) ($originalHistory->user_id ?? $deal->from_user_id);

            $alreadyReversed = LifeImpactHistory::query()
                ->where('activity_type', 'business_deal_deleted')
                ->where('activity_id', $dealId)
                ->where('reversed_from_history_id', (string) $originalHistory->id)
                ->exists();

            if ($alreadyReversed) {
                return $this->getCurrentTotal($impactedUserId);
            }

            $deletedAt = now();
            $snapshot = $this->normalizeSnapshot($originalHistory->activity_snapshot, $originalHistory->meta);
            if (empty($snapshot)) {
                $snapshot = $this->buildBusinessDealSnapshot($deal);
            }

            $snapshot['deleted'] = true;
            $snapshot['deleted_at'] = $deletedAt->toISOString();

            $originalHistory->forceFill([
                'status' => 'deleted',
                'activity_snapshot' => $snapshot,
                'updated_at' => now(),
            ])->save();

            DB::table('users')
                ->where('id', $impactedUserId)
                ->update([
                    'life_impacted_count' => DB::raw('GREATEST(COALESCE(life_impacted_count, 0) - 5, 0)'),
                    'updated_at' => now(),
                ]);

            $this->createBusinessDealReversalHistory(
                userId: $impactedUserId,
                triggeredByUserId: $triggeredByUserId,
                dealId: $dealId,
                reversedFromHistoryId: (string) $originalHistory->id,
                snapshot: $snapshot,
            );

            return $this->getCurrentTotal($impactedUserId);
        });
    }

    public function createBusinessDealReversalHistory(
        string $userId,
        ?string $triggeredByUserId,
        string $dealId,
        string $reversedFromHistoryId,
        array $snapshot,
    ): LifeImpactHistory {
        return $this->createLifeImpactHistory(
            userId: $userId,
            triggeredByUserId: $triggeredByUserId,
            activityType: 'business_deal_deleted',
            activityId: $dealId,
            impactValue: -5,
            title: 'Business deal deleted',
            description: 'Life impact reversed because the business deal was deleted.',
            meta: $snapshot,
            activitySnapshot: $snapshot,
            impactDirection: 'debit',
            status: 'reversed',
            reversedFromHistoryId: $reversedFromHistoryId,
        );
    }

    public function createLifeImpactHistory(
        string $userId,
        ?string $triggeredByUserId,
        string $activityType,
        ?string $activityId,
        int $impactValue,
        string $title,
        ?string $description,
        array $meta = [],
        ?array $activitySnapshot = null,
        string $impactDirection = 'credit',
        string $status = 'active',
        ?string $reversedFromHistoryId = null,
    ): LifeImpactHistory {
        $payload = [
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'triggered_by_user_id' => $triggeredByUserId,
            'activity_type' => $activityType,
            'activity_id' => $activityId,
            'impact_value' => $impactValue,
            'impact_direction' => $impactDirection,
            'status' => $status,
            'reversed_from_history_id' => $reversedFromHistoryId,
            'title' => $title,
            'description' => $description,
            'meta' => $meta ?: null,
            'activity_snapshot' => $activitySnapshot,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return LifeImpactHistory::query()->create($payload);
    }

    public function normalizeSnapshot(mixed $snapshot, mixed $fallbackMeta = null): array
    {
        if (is_array($snapshot)) {
            return $snapshot;
        }

        if (is_array($fallbackMeta)) {
            return $fallbackMeta;
        }

        return [];
    }

    public function getCurrentTotal(string $userId): int
    {
        return (int) (DB::table('users')->where('id', $userId)->value('life_impacted_count') ?? 0);
    }
}
