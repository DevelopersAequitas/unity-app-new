<?php

namespace App\Http\Controllers\Api;

use App\Events\ActivityCreated;
use App\Http\Requests\Activity\StoreBusinessDealRequest;
use App\Models\BusinessDeal;
use App\Models\Post;
use App\Models\User;
use App\Services\Blocks\PeerBlockService;
use App\Services\Coins\CoinsService;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
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
                'url' => $id ? url('/api/v1/files/'.$id) : null,
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
                'source_type' => 'business_deal',
                'source_id' => $deal->id,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to create post for business deal', [
                'business_deal_id' => $deal->id,
                'error' => $e->getMessage(),
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
            $businessDeal->setAttribute('post_id', $this->resolveTimelinePostId('business_deal', (string) $businessDeal->id));

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
                        'body' => ($authUser->display_name ?? $authUser->name ?? 'A member').' recorded a business deal with you',
                    ],
                    $businessDeal
                );
            }

            $updatedLifeImpact = $this->increaseLifeImpact(
                (string) $authUser->id,
                5,
                'business_deal',
                'Closed a business deal',
                (string) $authUser->id,
                (string) $businessDeal->id,
                'Life impact added for business deal activity.',
                [
                    'deal_date' => $businessDeal->deal_date,
                    'deal_amount' => $businessDeal->deal_amount,
                    'business_type' => $businessDeal->business_type,
                    'comment' => $businessDeal->comment,
                    'to_user_id' => $businessDeal->to_user_id ? (string) $businessDeal->to_user_id : null,
                ]
            );
            $businessDeal->setAttribute('life_impacted_count', $updatedLifeImpact);

            // Postman example (business deal create):
            // {
            //   "to_user_id": "<receiver-user-uuid>",
            //   "deal_date": "2026-01-20",
            //   "deal_amount": 5000,
            //   "business_type": "B2B",
            //   "comment": "Closed via referral"
            // }
            // Verify SQL:
            // select * from notifications where user_id = '<receiver-user-uuid>' order by created_at desc limit 20;

            if ($businessDeal->getAttribute('post_id') === null) {
                $businessDeal->setAttribute('post_id', $this->resolveTimelinePostId('business_deal', (string) $businessDeal->id));
            }

            return $this->success($businessDeal, 'Business deal saved successfully', 201);
        } catch (Throwable $e) {
            return $this->error('Something went wrong', 500);
        }
    }

    private function resolveTimelinePostId(string $sourceType, string $sourceId): ?string
    {
        return Post::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('is_deleted', false)
            ->latest('created_at')
            ->value('id');
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

    public function userBusinessDealsStats(Request $request, string $userId): \Illuminate\Http\JsonResponse
    {
        $user = User::find($userId);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null,
            ], 404);
        }

        $baseQuery = BusinessDeal::query()
            ->where(function ($q) {
                $q->where('is_deleted', false)
                    ->orWhereNull('is_deleted');
            })
            ->whereNull('deleted_at');

        $givenCount = (clone $baseQuery)
            ->where('from_user_id', $userId)
            ->count();

        $receivedCount = (clone $baseQuery)
            ->where('to_user_id', $userId)
            ->count();

        $totalCount = $givenCount + $receivedCount;

        return $this->success([
            'user' => [
                'id' => (string) $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'display_name' => $user->display_name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'email' => $user->email,
                'phone' => $user->phone,
                'company_name' => $user->company_name,
                'designation' => $user->designation,
                'profile_photo_url' => $user->profile_photo_url ?? $user->profile_photo ?? null,
            ],
            'business_deals_given' => $givenCount,
            'business_deals_received' => $receivedCount,
            'total_business_deals' => $totalCount,
        ]);
    }

    public function userBusinessDealsList(Request $request, string $userId): \Illuminate\Http\JsonResponse
    {
        $user = User::find($userId);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null,
            ], 404);
        }

        $baseQuery = BusinessDeal::query()
            ->where(function ($q) {
                $q->where('is_deleted', false)
                    ->orWhereNull('is_deleted');
            })
            ->whereNull('deleted_at');

        $givenCount = (clone $baseQuery)
            ->where('from_user_id', $userId)
            ->count();

        $receivedCount = (clone $baseQuery)
            ->where('to_user_id', $userId)
            ->count();

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        $paginator = (clone $baseQuery)
            ->with(['fromUser', 'toUser'])
            ->where(function ($q) use ($userId) {
                $q->where('from_user_id', $userId)
                  ->orWhere('to_user_id', $userId);
            })
            ->orderBy('deal_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $items = collect($paginator->items())->map(function (BusinessDeal $deal): array {
            $fromUser = $deal->fromUser;
            $toUser = $deal->toUser;

            return [
                'id' => (string) $deal->id,
                'from_user_id' => (string) $deal->from_user_id,
                'to_user_id' => (string) $deal->to_user_id,
                'deal_date' => $deal->deal_date ? Carbon::parse($deal->deal_date)->format('Y-m-d') : '',
                'deal_amount' => $deal->deal_amount,
                'business_type' => $deal->business_type ?? '',
                'comment' => $deal->comment ?? '',
                'created_at' => $deal->created_at ? Carbon::parse($deal->created_at)->timezone('Asia/Kolkata')->format('Y-m-d H:i:s') : '',
                'updated_at' => $deal->updated_at ? Carbon::parse($deal->updated_at)->timezone('Asia/Kolkata')->format('Y-m-d H:i:s') : '',
                'from_user' => $fromUser ? [
                    'id' => (string) $fromUser->id,
                    'display_name' => $fromUser->display_name ?? trim(($fromUser->first_name ?? '') . ' ' . ($fromUser->last_name ?? '')),
                    'first_name' => $fromUser->first_name,
                    'last_name' => $fromUser->last_name,
                    'email' => $fromUser->email,
                    'phone' => $fromUser->phone,
                    'company_name' => $fromUser->company_name,
                    'designation' => $fromUser->designation,
                    'profile_photo_url' => $fromUser->profile_photo_url ?? $fromUser->profile_photo ?? null,
                ] : null,
                'to_user' => $toUser ? [
                    'id' => (string) $toUser->id,
                    'display_name' => $toUser->display_name ?? trim(($toUser->first_name ?? '') . ' ' . ($toUser->last_name ?? '')),
                    'first_name' => $toUser->first_name,
                    'last_name' => $toUser->last_name,
                    'email' => $toUser->email,
                    'phone' => $toUser->phone,
                    'company_name' => $toUser->company_name,
                    'designation' => $toUser->designation,
                    'profile_photo_url' => $toUser->profile_photo_url ?? $toUser->profile_photo ?? null,
                ] : null,
            ];
        })->values()->all();

        return $this->success([
            'business_deals_given' => $givenCount,
            'business_deals_received' => $receivedCount,
            'total_business_deals' => $givenCount + $receivedCount,
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
