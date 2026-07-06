<?php

namespace App\Http\Controllers\Api\V1\Forms;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Forms\StorePeerRecommendationRequest;
use App\Models\PeerRecommendation;
use App\Services\Coins\CoinsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeerRecommendationController extends BaseApiController
{
    public function store(StorePeerRecommendationRequest $request, CoinsService $coinsService)
    {
        $authUser = $request->user();
        $data = $request->validated();

        $result = DB::transaction(function () use ($authUser, $data, $coinsService) {
            $recommendation = PeerRecommendation::create([
                'user_id' => $authUser->id,
                'peer_name' => $data['peer_name'],
                'peer_mobile' => $data['peer_mobile'] ?? null,
                'peer_email' => $data['peer_email'] ?? null,
                'peer_city' => $data['peer_city'] ?? null,
                'peer_business' => $data['peer_business'] ?? null,
                'how_well_known' => $data['how_well_known'],
                'is_aware' => (bool) $data['is_aware'],
                'note' => $data['note'] ?? null,
                'coins_awarded' => false,
            ]);

            $currentBalance = null;

            if (! $recommendation->coins_awarded) {
                $amount = (int) config('coins.recommend_peer', 0);
                $ledger = $coinsService->reward($authUser, $amount, 'Recommend a Peer');

                if ($ledger) {
                    $recommendation->coins_awarded = true;
                    $recommendation->coins_awarded_at = now();
                    $recommendation->save();
                    $currentBalance = $ledger->balance_after;
                }
            }

            return [$recommendation, $currentBalance];
        });

        /** @var PeerRecommendation $recommendation */
        [$recommendation, $currentBalance] = $result;

        $payload = [
            'id' => $recommendation->id,
            'coins_awarded' => (bool) $recommendation->coins_awarded,
        ];

        if ($currentBalance !== null) {
            $payload['current_coins_balance'] = (int) $currentBalance;
        }

        return $this->success($payload, 'Peer recommendation submitted successfully.', 201);
    }

    public function myIndex(Request $request)
    {
        $authUser = $request->user();

        $items = PeerRecommendation::query()
            ->where('user_id', $authUser->id)
            ->orderByDesc('created_at')
            ->select([
                'id',
                'peer_name',
                'peer_mobile',
                'how_well_known',
                'is_aware',
                'note',
                'created_at',
            ])
            ->get();

        return $this->success([
            'items' => $items,
        ], 'Peer recommendations fetched successfully.');
    }
}
