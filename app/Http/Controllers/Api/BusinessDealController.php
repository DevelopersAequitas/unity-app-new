<?php

namespace App\Http\Controllers\Api;

use App\Events\ActivityCreated;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Activity\StoreBusinessDealRequest;
use App\Models\BusinessDeal;
use App\Models\Post;
use App\Models\User;
use App\Services\Blocks\PeerBlockService;
use App\Services\Coins\CoinsService;
use App\Services\LifeImpact\LifeImpactService;
use App\Services\Notifications\NotifyUserService;
use Throwable;

class BusinessDealController extends BaseApiController
{
    protected function addUrlsToMedia(?array $media): array
    {
        if (empty($media)) {
            return [];
        }

        return collect($media)->map(function ($item) {
            $id = $item['id'] ?? null;
            $type = $item['type'] ?? 'image';

            return [
                'id' => $id,
                'type' => $type,
                'url' => $id ? url('/api/v1/files/' . $id) : null,
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
                'user_id' => $deal->from_user_id ?? $deal->user_id ?? $deal->created_by ?? $deal->to_user_id,
                'circle_id' => null,
                'content_text' => $contentText,
                'media' => $mediaForPost,
                'tags' => ['business_deal'],
                'visibility' => 'public',
                'moderation_status' => 'pending',
                'sponsored' => false,
                'is_deleted' => false,
            ]);
        } catch (Throwable $e) {
            \Log::error('Failed to create post for business deal', [
                'business_deal_id' => $deal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function index(\Illuminate\Http\Request $request)
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

    public function store(StoreBusinessDealRequest $request, NotifyUserService $notifyUserService, PeerBlockService $peerBlockService)
    {
        $authUser = $request->user();
        $targetUserId = (string) $request->input('to_user_id');

        if ($peerBlockService->isBlockedEitherWay((string) $authUser->id, $targetUserId)) {
            return $this->error('You cannot interact with this peer.', 422);
        }

        try {
            $businessDeal = BusinessDeal::create([
                'from_user_id' => $authUser->id,
                'to_user_id' => $request->input('to_user_id'),
                'deal_date' => $request->input('deal_date'),
                'deal_amount' => $request->input('deal_amount'),
                'business_type' => $request->input('business_type'),
                'comment' => $request->input('comment'),
                'is_deleted' => false,
            ]);

            $coinsLedger = app(CoinsService::class)->rewardForActivity(
                $authUser,
                'business_deal',
                null,
                'Activity: business_deal',
                $authUser->id
            );

            if ($coinsLedger) {
                $businessDeal->setAttribute('coins', [
                    'earned' => $coinsLedger->amount,
                    'balance_after' => $coinsLedger->balance_after,
                ]);
            }

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

            $lifeImpactService = app(LifeImpactService::class);
            $snapshot = $lifeImpactService->buildBusinessDealSnapshot($businessDeal);

            $updatedLifeImpact = $this->increaseLifeImpact(
                (string) $authUser->id,
                5,
                'business_deal',
                'Closed a business deal',
                (string) $authUser->id,
                (string) $businessDeal->id,
                'Life impact added for business deal activity.',
                $snapshot,
                $snapshot,
                'credit',
                'active',
            );
            $businessDeal->setAttribute('life_impacted_count', $updatedLifeImpact);

            return $this->success($businessDeal, 'Business deal saved successfully', 201);
        } catch (Throwable $e) {
            return $this->error('Something went wrong', 500);
        }
    }

    public function show(\Illuminate\Http\Request $request, string $id)
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

    public function destroy(\Illuminate\Http\Request $request, string $id)
    {
        $authUser = $request->user();

        $businessDeal = BusinessDeal::query()
            ->where('id', $id)
            ->where(function ($q) use ($authUser) {
                $q->where('from_user_id', (string) $authUser->id)
                    ->orWhere('to_user_id', (string) $authUser->id);
            })
            ->first();

        if (! $businessDeal) {
            return $this->error('Business deal not found', 404);
        }

        if ($businessDeal->is_deleted || $businessDeal->deleted_at !== null) {
            return $this->success([
                'id' => (string) $businessDeal->id,
                'life_impacted_count' => app(LifeImpactService::class)->getCurrentTotal((string) $businessDeal->from_user_id),
            ], 'Business deal already deleted');
        }

        try {
            $businessDeal->is_deleted = true;
            $businessDeal->save();
            $businessDeal->delete();

            $updatedLifeImpact = app(LifeImpactService::class)
                ->reverseBusinessDealLifeImpact($businessDeal, (string) $authUser->id);

            return $this->success([
                'id' => (string) $businessDeal->id,
                'life_impacted_count' => $updatedLifeImpact,
            ], 'Business deal deleted successfully');
        } catch (Throwable $e) {
            return $this->error('Something went wrong', 500);
        }
    }
}
