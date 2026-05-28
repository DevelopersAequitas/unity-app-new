<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Activity\StoreBusinessDealRequest;
use App\Events\ActivityCreated;
use App\Models\Post;
use App\Models\BusinessDeal;
use App\Models\BusinessDealMedia;
use App\Models\FileModel;
use App\Models\User;
use App\Services\Blocks\PeerBlockService;
use App\Services\Coins\CoinsService;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    private function normalizeCreativeMediaInput(Request $request): array
    {
        $files = $request->file('creative_media');

        if ($files === null && $request->hasFile('creative_media.0')) {
            $files = $request->file('creative_media', []);
        }

        if ($files === null) {
            return [];
        }

        return is_array($files) ? array_values($files) : [$files];
    }

    private function mediaTypeFromMime(?string $mimeType): string
    {
        $mime = strtolower((string) $mimeType);

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }

        return 'document';
    }

    private function uploadCreativeMedia(array $files, BusinessDeal $businessDeal, string $uploaderUserId): array
    {
        $disk = config('filesystems.default', 'public');

        return collect($files)->map(function ($file) use ($businessDeal, $uploaderUserId, $disk) {
            $extension = $file->getClientOriginalExtension() ?: 'bin';
            $path = $file->storeAs('business-deals/' . $businessDeal->id, Str::uuid() . '.' . $extension, $disk);

            $fileModel = FileModel::create([
                'uploader_user_id' => $uploaderUserId,
                's3_key' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);

            return BusinessDealMedia::create([
                'business_deal_id' => $businessDeal->id,
                'file_id' => $fileModel->id,
                'media_path' => $path,
                'media_url' => url('/api/v1/files/' . $fileModel->id),
                'media_type' => $this->mediaTypeFromMime($file->getMimeType()),
                'mime_type' => $file->getMimeType(),
                'original_name' => $file->getClientOriginalName(),
                'size_bytes' => $file->getSize(),
            ]);
        })->all();
    }

    private function formatCreativeMedia(BusinessDeal $businessDeal): array
    {
        return $businessDeal->creativeMedia->map(function (BusinessDealMedia $media) {
            return [
                'id' => (string) $media->id,
                'file_id' => $media->file_id,
                'media_type' => $media->media_type,
                'mime_type' => $media->mime_type,
                'original_name' => $media->original_name,
                'size_bytes' => $media->size_bytes,
                'url' => $media->file_id ? url('/api/v1/files/' . $media->file_id) : $media->media_url,
            ];
        })->values()->all();
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
                'source_type'       => 'business_deal',
                'source_id'         => $deal->id,
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
            ->with('creativeMedia')
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
            'items' => collect($paginator->items())->map(function (BusinessDeal $deal) {
                $deal->setAttribute('creative_media', $this->formatCreativeMedia($deal));

                return $deal;
            })->values()->all(),
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
            $creativeFiles = $this->normalizeCreativeMediaInput($request);

            $businessDeal = DB::transaction(function () use ($authUser, $request, $notifyUserService, $creativeFiles) {
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
                        'body' => ($authUser->display_name ?? $authUser->name ?? 'A member') . ' recorded a business deal with you',
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

            if (! empty($creativeFiles)) {
                $this->uploadCreativeMedia($creativeFiles, $businessDeal, (string) $authUser->id);
                $businessDeal->load('creativeMedia');
            }

            $businessDeal->setAttribute('creative_media', $this->formatCreativeMedia($businessDeal));

            if ($businessDeal->getAttribute('post_id') === null) {
                $businessDeal->setAttribute('post_id', $this->resolveTimelinePostId('business_deal', (string) $businessDeal->id));
            }

                return $businessDeal;
            });

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

        $businessDeal = BusinessDeal::with('creativeMedia')->where('id', $id)
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

        $businessDeal->setAttribute('creative_media', $this->formatCreativeMedia($businessDeal));

        return $this->success($businessDeal);
    }
}
