<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Activity\StoreBusinessDealRequest;
use App\Events\ActivityCreated;
use App\Models\Post;
use App\Models\BusinessDeal;
use App\Models\User;
use App\Services\Blocks\PeerBlockService;
use App\Services\Coins\CoinsService;
use App\Services\Notifications\NotifyUserService;
use App\Services\LifeImpact\LifeImpactService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class BusinessDealController extends BaseApiController
{
    protected function addUrlsToMedia(?array $media): array
    {
        if (empty($media)) {
            return [];
        }

        return collect($media)->map(function ($item) {
            $id   = $item['id']   ?? null;
            $type = $item['type'] ?? 'image';

            return [
                'id'   => $id,
                'type' => $type,
                'url'  => $id ? url('/api/v1/files/' . $id) : null,
            ];
        })->all();
    }

    /**
     * Create a feed post for a newly created business deal.
     */
    protected function createPostForBusinessDeal(BusinessDeal $deal): void
    {
        try {
            // Right now business deals have no media; keep empty array.
            $mediaForPost = [];

            $toUser = User::find($deal->to_user_id);
            $fromUser = User::find($deal->from_user_id ?? $deal->user_id ?? $deal->created_by ?? $deal->to_user_id);

            $contentText = $this->buildActivityPostMessage('business_deal', $toUser, [
                'actor_user' => $fromUser,
                'amount' => $deal->deal_amount ?? null,
            ]);

            Post::create([
                'user_id'           => $deal->from_user_id ?? $deal->user_id ?? $deal->created_by ?? $deal->to_user_id,
                'circle_id'         => null,
                'content_text'      => $contentText,
                'media'             => $mediaForPost,
                'tags'              => ['business_deal'],
                'visibility'        => 'public',
                'moderation_status' => 'pending',
                'sponsored'         => false,
                'is_deleted'        => false,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to create post for business deal', [
                'business_deal_id' => $deal->id,
                'error'            => $e->getMessage(),
            ]);
        }
    }

    public function index(Request $request)
    {
        $authUser = $request->user();
        $filter = $request->input('filter', 'given');
        $businessType = $request->input('business_type');

        $query = BusinessDeal::query()
            ->where('is_deleted', false)
            ->whereNull('deleted_at');

        if ($filter === 'received') {
            $query->where('to_user_id', $authUser->id);
        } elseif ($filter === 'all') {
            $query->where(function ($q) use ($authUser) {
                $q->where('from_user_id', $authUser->id)
                    ->orWhere('to_user_id', $authUser->id);
            });
        } else {
            $query->where('from_user_id', $authUser->id);
        }

        if ($businessType) {
            $query->where('business_type', $businessType);
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $paginator = $query
            ->orderBy('deal_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->success([
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(
        StoreBusinessDealRequest $request,
        NotifyUserService $notifyUserService,
        PeerBlockService $peerBlockService,
        LifeImpactService $lifeImpactService
    ) {
        $authUser = $request->user();
        $targetUserId = (string) $request->input('to_user_id');

        if ($peerBlockService->isBlockedEitherWay((string) $authUser->id, $targetUserId)) {
            return $this->error('You cannot interact with this peer.', 422);
        }

        try {
            [$businessDeal, $coinsLedger, $lifeImpact] = DB::transaction(function () use ($request, $authUser, $lifeImpactService) {
                $businessDeal = BusinessDeal::create([
                    'from_user_id' => $authUser->id,
                    'to_user_id' => $request->input('to_user_id'),
                    'deal_date' => $request->input('deal_date'),
                    'deal_amount' => $request->input('deal_amount'),
                    'business_type' => $request->input('business_type'),
                    'comment' => $request->input('comment'),
                    'is_deleted' => false,
                ]);

                Log::info('business_deal.created', [
                    'business_deal_id' => (string) $businessDeal->id,
                    'from_user_id' => (string) $authUser->id,
                    'to_user_id' => (string) $businessDeal->to_user_id,
                ]);

                $coinsLedger = app(CoinsService::class)->rewardForActivity(
                    $authUser,
                    'business_deal',
                    null,
                    'Activity: business_deal',
                    $authUser->id
                );

                $lifeImpact = $lifeImpactService->createBusinessDealImpact($authUser, $businessDeal);

                return [$businessDeal, $coinsLedger, $lifeImpact];
            });

            $coinsPayload = $coinsLedger ? [
                'earned' => $coinsLedger->amount,
                'balance_after' => $coinsLedger->balance_after,
            ] : null;

            $lifeImpactPayload = [
                'earned' => (int) ($lifeImpact['earned'] ?? 0),
                'total_after' => (int) ($lifeImpact['total_after'] ?? 0),
            ];

            $responsePayload = $businessDeal->toArray();

            if ($coinsPayload !== null) {
                $responsePayload['coins'] = $coinsPayload;
            }

            $responsePayload['life_impact'] = $lifeImpactPayload;

            Log::info('business_deal.response_payload_ready', [
                'business_deal_id' => (string) $businessDeal->id,
                'has_coins' => array_key_exists('coins', $responsePayload),
                'has_life_impact' => array_key_exists('life_impact', $responsePayload),
                'life_impact_earned' => (int) ($responsePayload['life_impact']['earned'] ?? 0),
                'life_impact_total_after' => (int) ($responsePayload['life_impact']['total_after'] ?? 0),
            ]);

            $this->createPostForBusinessDeal($businessDeal);

            event(new ActivityCreated(
                'Business Deal',
                $businessDeal,
                (string) $authUser->id,
                $businessDeal->to_user_id ? (string) $businessDeal->to_user_id : null
            ));

            $targetUser = User::find($businessDeal->to_user_id);

            if ($targetUser) {
                $notifyUserService->notifyUser(
                    $targetUser,
                    $authUser,
                    'activity_business_deal',
                    [
                        'activity_type' => 'business_deal',
                        'activity_id' => (string) $businessDeal->id,
                        'title' => 'New Business Deal',
                        'body' => ($authUser->display_name ?? $authUser->name ?? 'A member') . ' recorded a business deal with you',
                    ],
                    $businessDeal
                );
            }

            return $this->success($responsePayload, 'Business deal saved successfully', 201);
        } catch (Throwable $e) {
            Log::error('business_deal.create_failed', [
                'user_id' => (string) $authUser->id,
                'to_user_id' => $targetUserId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Something went wrong', 500);
        }
    }

    public function show(Request $request, string $id)
    {
        $authUser = $request->user();

        $businessDeal = BusinessDeal::where('id', $id)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($authUser) {
                $q->where('from_user_id', $authUser->id)
                    ->orWhere('to_user_id', $authUser->id);
            })
            ->first();

        if (! $businessDeal) {
            return $this->error('Business deal not found', 404);
        }

        return $this->success($businessDeal);
    }
}
