<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Forms;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Forms\StorePeerRecommendationRequest;
use App\Models\PeerRecommendation;
use App\Services\Forms\PeerRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeerRecommendationController extends BaseApiController
{
    public function __construct(
        protected PeerRecommendationService $recommendationService
    ) {}

    /**
     * Submit a new peer recommendation.
     */
    public function store(StorePeerRecommendationRequest $request): JsonResponse
    {
        $authUser = $request->user();
        $data = $request->validated();

        $result = $this->recommendationService->submit($authUser, $data);

        /** @var PeerRecommendation $recommendation */
        $recommendation = $result['recommendation'];

        $rewardData = $this->formatActivityRewardPayload(
            $result['coins_earned'],
            $result['current_balance'],
            $result['impact_points'],
            $result['updated_life_impact']
        );

        $payload = array_merge([
            'id' => (string) $recommendation->id,
            'peer_name' => $recommendation->peer_name,
            'peer_mobile' => $recommendation->peer_mobile,
            'created_at' => $recommendation->created_at?->toISOString() ?? (string) $recommendation->created_at,
            'coins_awarded' => (bool) $recommendation->coins_awarded,
            'current_coins_balance' => (int) $result['current_balance'],
        ], $rewardData);

        return $this->success($payload, 'Peer recommendation submitted successfully!', 201);
    }

    /**
     * Get past peer recommendations submitted by the authenticated user.
     */
    public function myIndex(Request $request): JsonResponse
    {
        $authUser = $request->user();

        $perPage = (int) ($request->query('per_page') ?? $request->query('limit') ?? 20);
        $page = (int) ($request->query('page') ?? 1);

        $paginator = $this->recommendationService->getMyRecommendations($authUser, $perPage, $page);

        $items = $paginator->getCollection()->map(function (PeerRecommendation $item): array {
            $formattedDate = $item->created_at?->toISOString() ?? ($item->created_at ? (string) $item->created_at : null);

            return [
                'id' => (string) $item->id,
                'peer_name' => $item->peer_name,
                'peer_mobile' => $item->peer_mobile,
                'peer_email' => $item->peer_email,
                'peer_city' => $item->peer_city,
                'peer_city_country' => $item->peer_city,
                'peer_business' => $item->peer_business,
                'main_business_category_id' => $item->main_business_category_id ?? $item->category_id,
                'main_business_category' => $item->main_business_category ?? $item->category,
                'business_subcategory_id' => $item->business_subcategory_id,
                'business_subcategory' => $item->business_subcategory,
                'how_well_known' => $item->how_well_known,
                'is_aware' => (bool) $item->is_aware,
                'why_valuable' => $item->why_valuable,
                'note' => $item->note,
                'circle_id' => $item->circle_id,
                'circle_name' => $item->circle_name,
                'status' => $item->status ?? 'pending',
                'created_at' => $formattedDate,
                'submitted_at' => $formattedDate,
            ];
        })->values();

        return $this->success([
            'items' => $items,
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ], 'Peer recommendations retrieved successfully.');
    }
}
