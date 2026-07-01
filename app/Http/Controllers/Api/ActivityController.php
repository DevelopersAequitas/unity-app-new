<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Activity\StoreActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\CoinLedgerResource;
use App\Models\Activity;
use App\Models\CoinLedger;
use Illuminate\Http\Request;

class ActivityController extends BaseApiController
{
    public function store(StoreActivityRequest $request)
    {
        $authUser = $request->user();
        $data = $request->validated();

        $activity = new Activity;
        $activity->user_id = $authUser->id;
        $activity->related_user_id = $data['related_user_id'] ?? null;
        $activity->circle_id = $data['circle_id'] ?? null;
        $activity->event_id = $data['event_id'] ?? null;
        $activity->type = $data['type'];
        $activity->description = $data['description'] ?? null;
        $activity->status = 'pending';
        $activity->requires_verification = true;
        $activity->coins_awarded = 0;
        $activity->coins_ledger_id = null;
        $activity->save();

        $activity->load(['circle', 'event']);

        return $this->success(new ActivityResource($activity), 'Activity submitted for review', 201);
    }

    public function myActivities(Request $request)
    {
        $authUser = $request->user();

        $query = Activity::with(['circle', 'event'])
            ->where('user_id', $authUser->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $data = [
            'items' => ActivityResource::collection($paginator),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        return $this->success($data);
    }

    public function myCoinsSummary(Request $request)
    {
        $authUser = $request->user();

        $coinsBalance = (int) $authUser->coins_balance;

        $stats = CoinLedger::where('user_id', $authUser->id)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as total_earned,
                COALESCE(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END), 0) as total_spent,
                COUNT(*) as transactions_count
            ')
            ->first();

        return $this->success([
            'coins_balance' => $coinsBalance,
            'total_earned' => (int) ($stats->total_earned ?? 0),
            'total_spent' => (int) ($stats->total_spent ?? 0),
            'transactions_count' => (int) ($stats->transactions_count ?? 0),
        ]);
    }

    public function myCoinsLedger(Request $request)
    {
        $authUser = $request->user();

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $paginator = CoinLedger::where('user_id', $authUser->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $data = [
            'items' => CoinLedgerResource::collection($paginator),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        return $this->success($data);
    }
}
