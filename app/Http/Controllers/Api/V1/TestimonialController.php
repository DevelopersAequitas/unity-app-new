<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\StorePeerTestimonialRequest;
use App\Http\Resources\V1\TestimonialResource;
use App\Events\ActivityCreated;
use App\Models\Post;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Blocks\PeerBlockService;
use App\Services\Coins\CoinsService;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TestimonialController extends BaseApiController
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

    protected function createPostForTestimonial(Testimonial $testimonial): void
    {
        try {
            $mediaForPost = $this->addUrlsToMedia($testimonial->media ?? []);
            $toUser = User::find($testimonial->to_user_id);

            $contentText = $this->buildActivityPostMessage('testimonial', $toUser, [
                'testimonial_message' => $testimonial->content ?? '',
            ]);

            Post::create([
                'user_id'           => $testimonial->from_user_id,
                'circle_id'         => null,
                'content_text'      => $contentText,
                'media'             => $mediaForPost,
                'tags'              => ['testimonial'],
                'visibility'        => 'public',
                'moderation_status' => 'pending',
                'sponsored'         => false,
                'is_deleted'        => false,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to create post for testimonial', [
                'testimonial_id' => $testimonial->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    public function store(StorePeerTestimonialRequest $request, NotifyUserService $notifyUserService, PeerBlockService $peerBlockService)
    {
        $authUser = $request->user();
        $targetUserId = (string) $request->input('given_to_user_id');

        if ($authUser->id === $targetUserId) {
            return $this->error('You cannot give a testimonial to yourself.', 422);
        }

        if ($peerBlockService->isBlockedEitherWay((string) $authUser->id, $targetUserId)) {
            return $this->error('You cannot interact with this peer.', 422);
        }

        $content = $request->input('message') ?? $request->input('testimonial_text');
        $rating = $request->input('rating');

        $media = null;
        if ($request->filled('media_id')) {
            $media = [[
                'id' => $request->input('media_id'),
                'type' => 'image',
            ]];
        }

        try {
            $testimonial = Testimonial::create([
                'from_user_id' => $authUser->id,
                'to_user_id' => $targetUserId,
                'content' => $content,
                'rating' => $rating,
                'media' => $media,
                'is_deleted' => false,
            ]);

            // Load relationships for the resource response
            $testimonial->load(['fromUser', 'toUser']);

            $coinsLedger = app(CoinsService::class)->rewardForActivity(
                $authUser,
                'testimonial',
                null,
                'Activity: testimonial',
                $authUser->id
            );

            if ($coinsLedger) {
                $testimonial->setAttribute('coins', [
                    'earned' => $coinsLedger->amount,
                    'balance_after' => $coinsLedger->balance_after,
                ]);
            }

            $this->createPostForTestimonial($testimonial);

            event(new ActivityCreated(
                'Testimonial',
                $testimonial,
                (string) $authUser->id,
                $testimonial->to_user_id ? (string) $testimonial->to_user_id : null
            ));

            $targetUser = User::find($testimonial->to_user_id);

            if ($targetUser) {
                $notifyUserService->notifyUser(
                    $targetUser,
                    $authUser,
                    'activity_testimonial',
                    [
                        'activity_type' => 'testimonial',
                        'activity_id' => (string) $testimonial->id,
                        'title' => 'New Testimonial',
                        'body' => ($authUser->display_name ?? $authUser->name ?? 'A member') . ' sent you a testimonial',
                    ],
                    $testimonial
                );
            }

            $updatedLifeImpact = $this->increaseLifeImpact(
                (string) $authUser->id,
                5,
                'testimonial',
                'Received a testimonial / review',
                (string) $authUser->id,
                (string) $testimonial->id,
                'Life impact added for testimonial activity.',
                [
                    'content' => $testimonial->content,
                    'media_ids' => collect($testimonial->media ?? [])->pluck('id')->filter()->values()->all(),
                    'from_user_id' => $testimonial->from_user_id ? (string) $testimonial->from_user_id : null,
                    'to_user_id' => $testimonial->to_user_id ? (string) $testimonial->to_user_id : null,
                ]
            );

            $resource = new TestimonialResource($testimonial);
            $data = $resource->toArray($request);

            if ($testimonial->getAttribute('coins')) {
                $data['coins'] = $testimonial->getAttribute('coins');
            }
            $data['life_impacted_count'] = $updatedLifeImpact;

            return $this->success($data, 'Testimonial saved successfully', 201);
        } catch (Throwable $e) {
            Log::error('Testimonial V1 creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('Something went wrong', 500);
        }
    }

    public function given(Request $request)
    {
        $authUser = $request->user();
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $paginator = Testimonial::query()
            ->with(['fromUser', 'toUser'])
            ->where('from_user_id', $authUser->id)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $givenToTotal = Testimonial::query()
            ->where('from_user_id', $authUser->id)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->distinct('to_user_id')
            ->count('to_user_id');

        return $this->success([
            'summary' => [
                'total_testimonials' => $paginator->total(),
                'given_by_total' => $paginator->total(),
                'given_to_total' => $givenToTotal,
            ],
            'items' => TestimonialResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function received(Request $request)
    {
        $authUser = $request->user();
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $paginator = Testimonial::query()
            ->with(['fromUser', 'toUser'])
            ->where('to_user_id', $authUser->id)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $givenByTotal = Testimonial::query()
            ->where('to_user_id', $authUser->id)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->distinct('from_user_id')
            ->count('from_user_id');

        return $this->success([
            'summary' => [
                'total_testimonials' => $paginator->total(),
                'given_by_total' => $givenByTotal,
                'given_to_total' => $paginator->total(),
            ],
            'items' => TestimonialResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function userTestimonials(Request $request, User $user)
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $paginator = Testimonial::query()
            ->with(['fromUser', 'toUser'])
            ->where('to_user_id', $user->id)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $givenByTotal = Testimonial::query()
            ->where('to_user_id', $user->id)
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->distinct('from_user_id')
            ->count('from_user_id');

        return $this->success([
            'summary' => [
                'total_testimonials' => $paginator->total(),
                'given_by_total' => $givenByTotal,
                'given_to_total' => $paginator->total(),
            ],
            'items' => TestimonialResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
