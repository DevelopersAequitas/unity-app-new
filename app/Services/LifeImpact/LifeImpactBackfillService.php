<?php

namespace App\Services\LifeImpact;

use App\Models\LifeImpactHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LifeImpactBackfillService
{
    public function run(): array
    {
        $stats = [
            'business_deals_inserted' => 0,
            'referrals_inserted' => 0,
            'testimonials_inserted' => 0,
            'duplicates_skipped' => 0,
            'users_recalculated' => 0,
        ];

        $userIdsToRecalculate = [];

        $this->backfillBusinessDeals($stats, $userIdsToRecalculate);
        $this->backfillReferrals($stats, $userIdsToRecalculate);
        $this->backfillTestimonials($stats, $userIdsToRecalculate);

        $stats['users_recalculated'] = $this->recalculateUsersWithHistory();

        return $stats;
    }

    public function recalculateUsersWithHistory(bool $resetMissingUsersToZero = false): int
    {
        $historyTable = (new LifeImpactHistory())->getTable();
        $sumExpression = Schema::hasColumn($historyTable, 'impact_value')
            ? 'COALESCE(impact_value, 0)'
            : (Schema::hasColumn($historyTable, 'life_impacted')
                ? 'COALESCE(life_impacted, 0)'
                : '0');

        $totalsQuery = DB::table($historyTable)
            ->select('user_id', DB::raw("SUM({$sumExpression}) as total"))
            ->groupBy('user_id');

        if (Schema::hasColumn($historyTable, 'status')) {
            $totalsQuery->where('status', 'approved');
        }

        $totalsByUser = $totalsQuery->pluck('total', 'user_id');
        $userIdsWithHistory = $totalsByUser->keys()->filter()->values()->all();

        if (! empty($userIdsWithHistory)) {
            User::query()
                ->whereIn('id', $userIdsWithHistory)
                ->chunkById(300, function ($users) use ($totalsByUser): void {
                    foreach ($users as $user) {
                        $userId = (string) $user->id;
                        $total = (int) ($totalsByUser[$userId] ?? 0);

                        DB::table('users')
                            ->where('id', $userId)
                            ->update([
                                'life_impacted_count' => $total,
                                'updated_at' => now(),
                            ]);
                    }
                });
        }

        if ($resetMissingUsersToZero && Schema::hasColumn('users', 'life_impacted_count')) {
            $zeroQuery = DB::table('users');

            if (! empty($userIdsWithHistory)) {
                $zeroQuery->whereNotIn('id', $userIdsWithHistory);
            }

            $zeroQuery->update([
                'life_impacted_count' => 0,
                'updated_at' => now(),
            ]);
        }

        return count($userIdsWithHistory);
    }

    public function createManualImpact(array $data): LifeImpactHistory
    {
        $userId = (string) $data['user_id'];
        $impactValue = max(0, (int) $data['impact_value']);
        $referenceDate = $data['reference_date'] ?? now()->toDateString();

        $meta = array_filter([
            'manual' => true,
            'reference_date' => $referenceDate,
            'remark' => $this->normalizeNullableString($data['remark'] ?? null),
            'impact_type' => (string) $data['impact_type'],
            'impact_value' => $impactValue,
        ], fn ($value) => $value !== null && $value !== '');

        $history = $this->buildHistory([
            'user_id' => $userId,
            'triggered_by_user_id' => null,
            'activity_type' => 'manual',
            'activity_id' => null,
            'impact_value' => $impactValue,
            'title' => (string) $data['title'],
            'description' => $this->normalizeNullableString($data['description'] ?? null),
            'meta' => $meta,
            'action_key' => 'manual:' . Str::uuid()->toString(),
            'status' => 'approved',
            'created_at' => $referenceDate,
        ]);

        $history->save();

        $this->recalculateTotals([$userId]);

        return $history->refresh();
    }

    private function backfillBusinessDeals(array &$stats, array &$userIdsToRecalculate): void
    {
        $this->scanSourceTable(
            tableName: 'business_deals',
            activityType: 'business_deal',
            title: 'Closed a business deal',
            description: 'Backfilled life impact for historical business deal.',
            impactValue: 5,
            actionKeyPrefix: 'business_deal',
            userResolver: fn (object $row): ?string => $this->normalizeNullableString($row->from_user_id ?? null),
            triggeredByResolver: fn (object $row): ?string => $this->normalizeNullableString($row->from_user_id ?? null),
            metaResolver: fn (object $row): array => array_filter([
                'source' => 'backfill',
                'deal_date' => $row->deal_date ?? null,
                'deal_amount' => $row->deal_amount ?? null,
                'business_type' => $row->business_type ?? null,
                'comment' => $this->normalizeNullableString($row->comment ?? null),
                'to_user_id' => $this->normalizeNullableString($row->to_user_id ?? null),
            ], fn ($value) => $value !== null && $value !== ''),
            insertedCounterKey: 'business_deals_inserted',
            stats: $stats,
            userIdsToRecalculate: $userIdsToRecalculate,
        );
    }

    private function backfillReferrals(array &$stats, array &$userIdsToRecalculate): void
    {
        $this->scanSourceTable(
            tableName: 'referrals',
            activityType: 'referral',
            title: 'Gave a qualified business referral',
            description: 'Backfilled life impact for historical referral.',
            impactValue: 1,
            actionKeyPrefix: 'qualified_referral',
            userResolver: fn (object $row): ?string => $this->normalizeNullableString($row->from_user_id ?? null),
            triggeredByResolver: fn (object $row): ?string => $this->normalizeNullableString($row->from_user_id ?? null),
            metaResolver: fn (object $row): array => array_filter([
                'source' => 'backfill',
                'referral_type' => $this->normalizeNullableString($row->referral_type ?? null),
                'referral_date' => $row->referral_date ?? null,
                'referral_of' => $this->normalizeNullableString($row->referral_of ?? null),
                'to_user_id' => $this->normalizeNullableString($row->to_user_id ?? null),
                'remarks' => $this->normalizeNullableString($row->remarks ?? null),
            ], fn ($value) => $value !== null && $value !== ''),
            insertedCounterKey: 'referrals_inserted',
            stats: $stats,
            userIdsToRecalculate: $userIdsToRecalculate,
        );
    }

    private function backfillTestimonials(array &$stats, array &$userIdsToRecalculate): void
    {
        $this->scanSourceTable(
            tableName: 'testimonials',
            activityType: 'testimonial',
            title: 'Received a testimonial / review',
            description: 'Backfilled life impact for historical testimonial.',
            impactValue: 5,
            actionKeyPrefix: 'testimonial_received',
            userResolver: fn (object $row): ?string => $this->normalizeNullableString($row->from_user_id ?? null),
            triggeredByResolver: fn (object $row): ?string => $this->normalizeNullableString($row->from_user_id ?? null),
            metaResolver: fn (object $row): array => array_filter([
                'source' => 'backfill',
                'to_user_id' => $this->normalizeNullableString($row->to_user_id ?? null),
                'content' => $this->normalizeNullableString($row->content ?? null),
            ], fn ($value) => $value !== null && $value !== ''),
            insertedCounterKey: 'testimonials_inserted',
            stats: $stats,
            userIdsToRecalculate: $userIdsToRecalculate,
        );
    }

    private function scanSourceTable(
        string $tableName,
        string $activityType,
        string $title,
        string $description,
        int $impactValue,
        string $actionKeyPrefix,
        callable $userResolver,
        callable $triggeredByResolver,
        callable $metaResolver,
        string $insertedCounterKey,
        array &$stats,
        array &$userIdsToRecalculate,
    ): void {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $query = DB::table($tableName);

        if (Schema::hasColumn($tableName, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn($tableName, 'is_deleted')) {
            $query->where(function ($subQuery): void {
                $subQuery->where('is_deleted', false)
                    ->orWhereNull('is_deleted');
            });
        }

        $query
            ->orderBy('created_at')
            ->orderBy('id')
            ->chunk(300, function ($rows) use (
                $activityType,
                $title,
                $description,
                $impactValue,
                $actionKeyPrefix,
                $userResolver,
                $triggeredByResolver,
                $metaResolver,
                $insertedCounterKey,
                &$stats,
                &$userIdsToRecalculate
            ): void {
                foreach ($rows as $row) {
                    $activityId = $this->normalizeNullableString($row->id ?? null);

                    if ($activityId === null) {
                        continue;
                    }

                    $userId = $userResolver($row);
                    if ($userId === null || ! Str::isUuid($userId)) {
                        continue;
                    }

                    $actionKey = sprintf('%s:%s', $actionKeyPrefix, $activityId);

                    $alreadyExists = LifeImpactHistory::query()
                        ->where('action_key', $actionKey)
                        ->exists();

                    if (! $alreadyExists) {
                        $alreadyExists = LifeImpactHistory::query()
                            ->where('activity_type', $activityType)
                            ->where('activity_id', $activityId)
                            ->where('user_id', $userId)
                            ->exists();
                    }

                    if ($alreadyExists) {
                        $stats['duplicates_skipped']++;

                        continue;
                    }

                    $triggeredByUserId = $triggeredByResolver($row);
                    $triggeredByUserId = (is_string($triggeredByUserId) && Str::isUuid($triggeredByUserId))
                        ? $triggeredByUserId
                        : null;

                    $meta = $metaResolver($row);

                    $history = $this->buildHistory([
                        'user_id' => $userId,
                        'triggered_by_user_id' => $triggeredByUserId,
                        'activity_type' => $activityType,
                        'activity_id' => $activityId,
                        'impact_value' => $impactValue,
                        'title' => $title,
                        'description' => $description,
                        'meta' => is_array($meta) ? $meta : [],
                        'action_key' => $actionKey,
                        'status' => 'approved',
                        'created_at' => $row->created_at ?? now(),
                    ]);

                    $history->save();

                    $stats[$insertedCounterKey]++;
                    $userIdsToRecalculate[$userId] = true;
                }
            });
    }

    private function recalculateTotals(array $userIds): int
    {
        if (empty($userIds)) {
            return 0;
        }

        $historyTable = (new LifeImpactHistory())->getTable();
        $sumExpression = Schema::hasColumn($historyTable, 'impact_value')
            ? 'COALESCE(impact_value, 0)'
            : (Schema::hasColumn($historyTable, 'life_impacted')
                ? 'COALESCE(life_impacted, 0)'
                : '0');

        $totalsQuery = DB::table($historyTable)
            ->select('user_id', DB::raw("SUM({$sumExpression}) as total"))
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id');

        if (Schema::hasColumn($historyTable, 'status')) {
            $totalsQuery->where('status', 'approved');
        }

        $totalsByUser = $totalsQuery->pluck('total', 'user_id');

        User::query()
            ->whereIn('id', $userIds)
            ->chunkById(300, function ($users) use ($totalsByUser): void {
                foreach ($users as $user) {
                    $userId = (string) $user->id;
                    $total = (int) ($totalsByUser[$userId] ?? 0);

                    DB::table('users')
                        ->where('id', $userId)
                        ->update([
                            'life_impacted_count' => $total,
                            'updated_at' => now(),
                        ]);
                }
            });

        return count($userIds);
    }

    private function buildHistory(array $data): LifeImpactHistory
    {
        $history = new LifeImpactHistory();

        $history->id = (string) Str::uuid();
        $history->user_id = (string) $data['user_id'];
        $history->triggered_by_user_id = $data['triggered_by_user_id'] ?? null;
        $history->activity_type = (string) $data['activity_type'];
        $history->activity_id = $data['activity_id'] ?? null;
        $history->impact_value = (int) $data['impact_value'];
        $history->title = (string) $data['title'];
        $history->description = $this->normalizeNullableString($data['description'] ?? null);
        $history->meta = $data['meta'] ?? [];
        $history->action_key = (string) $data['action_key'];
        $history->created_at = $data['created_at'] ?? now();
        $history->updated_at = $data['created_at'] ?? now();

        $table = $history->getTable();

        if (Schema::hasColumn($table, 'status')) {
            $history->status = (string) ($data['status'] ?? 'approved');
        }

        if (Schema::hasColumn($table, 'life_impacted')) {
            $history->life_impacted = (int) $data['impact_value'];
        }

        if (Schema::hasColumn($table, 'counted_in_total')) {
            $history->counted_in_total = true;
        }

        if (Schema::hasColumn($table, 'impact_category')) {
            $history->impact_category = (string) $data['activity_type'];
        }

        if (Schema::hasColumn($table, 'action_label')) {
            $history->action_label = Str::of((string) $data['activity_type'])->replace('_', ' ')->title()->value();
        }

        if (Schema::hasColumn($table, 'remarks')) {
            $history->remarks = $this->normalizeNullableString($data['description'] ?? null);
        }

        return $history;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
