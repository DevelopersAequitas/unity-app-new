<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\CoinHistoryItemResource;
use App\Models\Activity;
use App\Models\CoinLedger;
use App\Services\Coins\CoinActivityTitleResolver;
use App\Services\Coins\CoinLedgerRelatedUserService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CoinHistoryController extends BaseApiController
{
    public function index(Request $request, CoinActivityTitleResolver $titleResolver, CoinLedgerRelatedUserService $relatedUserService)
    {
        $validator = validator($request->all(), [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'type' => ['sometimes', 'string', Rule::in([
                'testimonial',
                'referral',
                'requirement',
                'business_deal',
                'p2p_meeting',
            ])],
            'from_date' => ['sometimes', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'date_format:Y-m-d'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation error', 422, $validator->errors());
        }

        $data = $validator->validated();
        $user = $request->user();

        $perPage = (int) ($data['per_page'] ?? 20);
        $limit = (int) ($data['limit'] ?? 2000);

        $query = CoinLedger::query()
            ->where('user_id', $user->id);

        if (! empty($data['type'])) {
            $query->whereHas('activity', function ($activityQuery) use ($data) {
                $activityQuery->where('type', $data['type']);
            });
        }

        if (! empty($data['from_date'])) {
            $fromDate = CarbonImmutable::createFromFormat('Y-m-d', $data['from_date'])->startOfDay();
            $query->where('created_at', '>=', $fromDate);
        }

        if (! empty($data['to_date'])) {
            $toDate = CarbonImmutable::createFromFormat('Y-m-d', $data['to_date'])->endOfDay();
            $query->where('created_at', '<=', $toDate);
        }

        $ledgerItems = $query
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $activities = $this->loadActivities($ledgerItems);
        $activityTitles = $titleResolver->resolveTitles($activities->values());

        $transformed = $ledgerItems->map(function ($ledger) use ($activities, $activityTitles, $titleResolver, $relatedUserService, $user) {
            $activity = $ledger->activity_id ? $activities->get($ledger->activity_id) : null;
            $activityType = $activity->type ?? null;
            $activityId = $ledger->activity_id;

            $reasonKey = $ledger->reason_key ?? $ledger->reference ?? ($activityType ? 'activity_'.$activityType : 'coins_transaction');
            $reasonLabel = $this->formatReasonLabel($reasonKey);

            $relatedUser = null;

            $relatedUserId = $ledger->related_user_id ?? ($activity->related_user_id ?? null);
            if ($relatedUserId) {
                $relatedUser = $relatedUserService->loadUserById($relatedUserId);
            }

            if (! $relatedUser) {
                $fallback = $relatedUserService->enrichLedgerItem($ledger, $user->id, $reasonLabel);
                $relatedUser = $fallback['related_user'] ?? null;
                $activityType = $activityType ?? $fallback['activity_type'];
                $activityId = $activityId ?? $fallback['activity_id'];
            }

            $activityTitle = $activity
                ? ($activityTitles[$activity->id] ?? $titleResolver->defaultTitle($activityType))
                : $titleResolver->defaultTitle($activityType);

            return [
                'id' => $ledger->transaction_id ?? $ledger->id ?? null,
                'coins_delta' => (int) ($ledger->coins_delta ?? $ledger->amount ?? 0),
                'reason_label' => $reasonLabel,
                'activity_type' => $activityType,
                'activity_id' => $activityId,
                'activity_title' => $activityTitle,
                'related_user' => $relatedUser,
                'created_at' => $ledger->created_at,
            ];
        });

        return $this->success([
            'current_coins_balance' => (int) $user->coins_balance,
            'items' => CoinHistoryItemResource::collection($transformed),
        ], 'Coins history fetched successfully');
    }

    private function loadActivities($ledgerItems)
    {
        $activityIds = $ledgerItems->pluck('activity_id')
            ->filter()
            ->unique()
            ->values();

        if ($activityIds->isEmpty()) {
            return collect();
        }

        return Activity::whereIn('id', $activityIds)->get()->keyBy('id');
    }

    private function formatReasonLabel(?string $reasonKey): string
    {
        if (! $reasonKey) {
            return 'Coins Update';
        }

        $normalized = Str::of($reasonKey)
            ->replace(['_', '-'], ' ')
            ->trim();

        if ($normalized->isEmpty()) {
            return 'Coins Update';
        }

        return $normalized->headline()->toString();
    }
}
