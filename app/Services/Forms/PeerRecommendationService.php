<?php

declare(strict_types=1);

namespace App\Services\Forms;

use App\Models\PeerRecommendation;
use App\Models\User;
use App\Services\Coins\CoinsService;
use App\Services\LifeImpact\LifeImpactService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PeerRecommendationService
{
    public function __construct(
        protected CoinsService $coinsService,
        protected LifeImpactService $lifeImpactService
    ) {}

    /**
     * Submit a new peer recommendation and handle post-submission gamification.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     recommendation: PeerRecommendation,
     *     coins_earned: int,
     *     current_balance: int,
     *     impact_points: int,
     *     updated_life_impact: int
     * }
     */
    public function submit(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data): array {
            $peerCity = $data['peer_city'] ?? $data['peer_city_country'] ?? null;

            $mainCategoryId = null;
            if (isset($data['main_business_category_id']) && $data['main_business_category_id'] !== '') {
                $mainCategoryId = (int) $data['main_business_category_id'];
            } elseif (isset($data['main_category_id']) && $data['main_category_id'] !== '') {
                $mainCategoryId = (int) $data['main_category_id'];
            } elseif (isset($data['category_id']) && $data['category_id'] !== '') {
                $mainCategoryId = (int) $data['category_id'];
            }

            $mainCategory = $data['main_business_category']
                ?? $data['main_category']
                ?? $data['category']
                ?? $data['peer_category']
                ?? null;

            $subcategoryId = null;
            if (isset($data['business_subcategory_id']) && $data['business_subcategory_id'] !== '') {
                $subcategoryId = (string) $data['business_subcategory_id'];
            } elseif (isset($data['subcategory_id']) && $data['subcategory_id'] !== '') {
                $subcategoryId = (string) $data['subcategory_id'];
            }

            $subcategory = $data['business_subcategory']
                ?? $data['subcategory']
                ?? null;

            $isAware = isset($data['is_aware']) ? (bool) $data['is_aware'] : true;

            /** @var PeerRecommendation $recommendation */
            $recommendation = PeerRecommendation::create([
                'user_id' => (string) $user->id,
                'peer_name' => (string) $data['peer_name'],
                'peer_mobile' => (string) $data['peer_mobile'],
                'peer_email' => $data['peer_email'] ?? null,
                'peer_city' => $peerCity,
                'peer_business' => $data['peer_business'] ?? null,
                'peer_industry' => $data['peer_industry'] ?? null,
                'why_valuable' => $data['why_valuable'] ?? null,
                'category' => $mainCategory,
                'category_id' => $mainCategoryId,
                'main_business_category_id' => $mainCategoryId,
                'main_business_category' => $mainCategory,
                'business_subcategory_id' => $subcategoryId,
                'business_subcategory' => $subcategory,
                'circle_id' => isset($data['circle_id']) ? (string) $data['circle_id'] : null,
                'circle_name' => $data['circle_name'] ?? null,
                'how_well_known' => (string) ($data['how_well_known'] ?? 'business_associate'),
                'is_aware' => $isAware,
                'note' => $data['note'] ?? null,
                'status' => 'pending',
                'coins_awarded' => false,
            ]);

            $coinsEarned = 0;
            $currentBalance = (int) ($user->coins_balance ?? 0);

            if (! $recommendation->coins_awarded) {
                $configuredAmount = (int) config('coins.activity_rewards.recommend_peer', 0);
                $amount = $configuredAmount > 0 ? $configuredAmount : (int) config('coins.recommend_peer', 1000);
                $ledger = $this->coinsService->reward($user, $amount, 'Recommend a Peer');

                if ($ledger) {
                    $recommendation->coins_awarded = true;
                    $recommendation->coins_awarded_at = now();
                    $recommendation->save();
                    $coinsEarned = (int) $ledger->amount;
                    $currentBalance = (int) $ledger->balance_after;
                }
            }

            $impactPoints = (int) config('impact.activity_rewards.recommend_peer', 5);
            $updatedLifeImpact = $this->lifeImpactService->addLifeImpact(
                (string) $user->id,
                (string) $user->id,
                'recommend_peer',
                (string) $recommendation->id,
                $impactPoints,
                'Recommended a peer to the community',
                'Life impact added for peer recommendation activity.',
                [
                    'peer_name' => $recommendation->peer_name,
                    'category' => $recommendation->category,
                ]
            );

            return [
                'recommendation' => $recommendation,
                'coins_earned' => $coinsEarned,
                'current_balance' => $currentBalance,
                'impact_points' => $impactPoints,
                'updated_life_impact' => $updatedLifeImpact,
            ];
        });
    }

    /**
     * Get paginated recommendations submitted by the user.
     */
    public function getMyRecommendations(User $user, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);

        return PeerRecommendation::query()
            ->where('user_id', (string) $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
