<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Connection;
use App\Models\IntroVideoLike;
use App\Models\User;
use App\Models\UserFollow;
use App\Services\Coins\CoinsService;
use App\Services\LifeImpact\LifeImpactService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class IntroVideoController extends Controller
{
    /**
     * Store or update the authenticated user's intro video.
     *
     * Accepts an intro_video_id (UUID of an already-uploaded file)
     * and persists it as the user's profile_video_id.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'intro_video_id' => ['required', 'uuid', 'exists:files,id'],
        ]);

        $user = $request->user();
        $user->profile_video_id = $request->input('intro_video_id');
        $user->saveOrFail();
        $user->refresh();

        // Award coins for first-time intro video upload (idempotent duplicate protection)
        $coinsService = app(CoinsService::class);
        $coinsLedger = $coinsService->rewardForIntroVideo($user);
        if ($coinsLedger) {
            $user->refresh();

            $impactPoints = (int) config('impact.activity_rewards.introduction_video', 1);
            if ($impactPoints > 0) {
                app(LifeImpactService::class)->addLifeImpact(
                    (string) $user->id,
                    (string) $user->id,
                    'introduction_video',
                    (string) $user->profile_video_id,
                    $impactPoints,
                    'Uploaded an introduction video',
                    'Life impact awarded for uploading profile introduction video.'
                );
            }
        }

        $user->loadMissing(['level4Category', 'businessCategory', 'city']);
        $this->attachConnectionAndLikeStatuses($user, [$user]);

        $totalLifeImpact = app(LifeImpactService::class)->getCurrentTotal((string) $user->id);
        $responseData = $this->formatResponse($user, $user);

        $coinsEarned = $coinsLedger ? (int) $coinsLedger->amount : 0;
        $coinsBalance = (int) $user->coins_balance;
        $impactEarned = $coinsLedger ? (int) config('impact.activity_rewards.introduction_video', 1) : 0;

        $responseData['coins'] = [
            'earned' => $coinsEarned,
            'balance_after' => $coinsBalance,
        ];
        $responseData['life_impact'] = [
            'earned' => $impactEarned,
            'total' => $totalLifeImpact,
            'balance_after' => $totalLifeImpact,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Intro video updated successfully',
            'data' => $responseData,
        ]);
    }

    /**
     * Get the authenticated user's current intro video.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing(['level4Category', 'businessCategory', 'city']);
        $this->attachConnectionAndLikeStatuses($user, [$user]);

        return response()->json([
            'success' => true,
            'message' => 'Intro video fetched successfully',
            'data' => $this->formatResponse($user, $user),
        ]);
    }

    /**
     * Remove the authenticated user's intro video.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->profile_video_id = null;
        $user->saveOrFail();
        $user->refresh();

        $user->loadMissing(['level4Category', 'businessCategory', 'city']);
        $this->attachConnectionAndLikeStatuses($user, [$user]);

        return response()->json([
            'success' => true,
            'message' => 'Intro video removed successfully',
            'data' => $this->formatResponse($user, $user),
        ]);
    }

    /**
     * Get all users' intro videos with complete peer metadata, likes count, and like status.
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = auth('sanctum')->user() ?: ($request instanceof Request ? $request->user() : null);

        $query = User::query()
            ->whereNotNull('profile_video_id')
            ->with(['level4Category', 'businessCategory', 'city'])
            ->latest();

        if ($request->boolean('all', false) || $request->input('per_page') === 'all') {
            $users = $query->get();
            $this->attachConnectionAndLikeStatuses($authUser, $users);

            $data = $users->map(fn (User $user): array => $this->formatResponse($user, $authUser))->values()->all();

            return response()->json([
                'success' => true,
                'message' => 'Intro videos fetched successfully',
                'data' => $data,
            ]);
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $users = $query->paginate($perPage);
        $this->attachConnectionAndLikeStatuses($authUser, $users->items());

        $data = collect($users->items())->map(fn (User $user): array => $this->formatResponse($user, $authUser))->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Intro videos fetched successfully',
            'data' => $data,
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Like an intro video.
     */
    public function like(Request $request, string $id): JsonResponse
    {
        $authUser = auth('sanctum')->user() ?: $request->user();
        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $targetUser = $this->findTargetVideoOwner($id);
        if (! $targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'Intro video not found',
            ], 404);
        }

        if (Schema::hasTable('intro_video_likes')) {
            IntroVideoLike::query()->firstOrCreate([
                'user_id' => (string) $authUser->id,
                'video_owner_id' => (string) $targetUser->id,
            ], [
                'id' => (string) Str::uuid(),
                'intro_video_id' => $targetUser->profile_video_id ? (string) $targetUser->profile_video_id : null,
                'created_at' => now(),
            ]);
        }

        $likesCount = Schema::hasTable('intro_video_likes')
            ? IntroVideoLike::query()->where('video_owner_id', (string) $targetUser->id)->count()
            : 0;

        return response()->json([
            'success' => true,
            'message' => 'Intro video liked successfully',
            'data' => [
                'is_liked' => true,
                'likes_count' => $likesCount,
            ],
        ]);
    }

    /**
     * Unlike an intro video.
     */
    public function unlike(Request $request, string $id): JsonResponse
    {
        $authUser = auth('sanctum')->user() ?: $request->user();
        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $targetUser = $this->findTargetVideoOwner($id);
        if (! $targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'Intro video not found',
            ], 404);
        }

        if (Schema::hasTable('intro_video_likes')) {
            IntroVideoLike::query()
                ->where('user_id', (string) $authUser->id)
                ->where('video_owner_id', (string) $targetUser->id)
                ->delete();
        }

        $likesCount = Schema::hasTable('intro_video_likes')
            ? IntroVideoLike::query()->where('video_owner_id', (string) $targetUser->id)->count()
            : 0;

        return response()->json([
            'success' => true,
            'message' => 'Intro video unliked successfully',
            'data' => [
                'is_liked' => false,
                'likes_count' => $likesCount,
            ],
        ]);
    }

    /**
     * Toggle like/unlike for an intro video.
     */
    public function toggleLike(Request $request, string $id): JsonResponse
    {
        $authUser = auth('sanctum')->user() ?: $request->user();
        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $targetUser = $this->findTargetVideoOwner($id);
        if (! $targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'Intro video not found',
            ], 404);
        }

        $isLiked = false;
        $message = 'Intro video unliked successfully';

        if (Schema::hasTable('intro_video_likes')) {
            $existing = IntroVideoLike::query()
                ->where('user_id', (string) $authUser->id)
                ->where('video_owner_id', (string) $targetUser->id)
                ->first();

            if ($existing) {
                $existing->delete();
                $isLiked = false;
                $message = 'Intro video unliked successfully';
            } else {
                IntroVideoLike::query()->create([
                    'id' => (string) Str::uuid(),
                    'user_id' => (string) $authUser->id,
                    'video_owner_id' => (string) $targetUser->id,
                    'intro_video_id' => $targetUser->profile_video_id ? (string) $targetUser->profile_video_id : null,
                    'created_at' => now(),
                ]);
                $isLiked = true;
                $message = 'Intro video liked successfully';
            }
        }

        $likesCount = Schema::hasTable('intro_video_likes')
            ? IntroVideoLike::query()->where('video_owner_id', (string) $targetUser->id)->count()
            : 0;

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'is_liked' => $isLiked,
                'likes_count' => $likesCount,
            ],
        ]);
    }

    /**
     * Find target video owner by User ID or intro video file ID.
     */
    private function findTargetVideoOwner(string $id): ?User
    {
        return User::query()
            ->where(function ($q) use ($id): void {
                $q->where('id', $id)
                    ->orWhere('profile_video_id', $id);
            })
            ->whereNotNull('profile_video_id')
            ->first();
    }

    /**
     * Batch attach connection, following, pro statuses, and intro video like states.
     *
     * @param  Collection<int, User>|array<int, User>  $users
     */
    private function attachConnectionAndLikeStatuses(?User $authUser, mixed $users): void
    {
        $userCollection = $users instanceof Collection ? $users : collect($users);

        if ($userCollection->isEmpty()) {
            return;
        }

        $userIds = $userCollection->pluck('id')->map(fn ($id): string => (string) $id)->all();

        $likeCounts = collect();
        $likedVideoOwnerIds = [];

        if (Schema::hasTable('intro_video_likes') && ! empty($userIds)) {
            $likeCounts = IntroVideoLike::query()
                ->whereIn('video_owner_id', $userIds)
                ->selectRaw('video_owner_id, count(*) as count')
                ->groupBy('video_owner_id')
                ->pluck('count', 'video_owner_id');

            if ($authUser) {
                $likedVideoOwnerIds = IntroVideoLike::query()
                    ->where('user_id', (string) $authUser->id)
                    ->whereIn('video_owner_id', $userIds)
                    ->pluck('video_owner_id')
                    ->map(fn ($id): string => (string) $id)
                    ->all();
            }
        }

        if (! $authUser) {
            $userCollection->each(function (User $user) use ($likeCounts): void {
                $user->setAttribute('is_connected', false);
                $user->setAttribute('connection_status', null);
                $user->setAttribute('is_requested', false);
                $user->setAttribute('can_send_connection_request', true);
                $user->setAttribute('is_following', false);
                $user->setAttribute('is_pro', $this->calculateIsPro($user));
                $user->setAttribute('is_verified', (bool) ($user->is_verified ?? false));
                $user->setAttribute('likes_count', (int) ($likeCounts->get((string) $user->id) ?? 0));
                $user->setAttribute('is_liked', false);
            });

            return;
        }

        $authUserId = (string) $authUser->id;

        $connections = collect();
        if (Schema::hasTable('connections') && ! empty($userIds)) {
            $sent = Connection::query()
                ->where('requester_id', $authUserId)
                ->whereIn('addressee_id', $userIds);

            $connections = Connection::query()
                ->where('addressee_id', $authUserId)
                ->whereIn('requester_id', $userIds)
                ->union($sent)
                ->get()
                ->keyBy(function (Connection $connection) use ($authUserId): string {
                    return (string) ((string) $connection->requester_id === $authUserId
                        ? $connection->addressee_id
                        : $connection->requester_id);
                });
        }

        $followedUserIds = [];
        if (Schema::hasTable('user_follows') && ! empty($userIds)) {
            $followedUserIds = UserFollow::query()
                ->where('follower_id', $authUserId)
                ->whereIn('following_id', $userIds)
                ->whereIn('status', ['accepted', 'pending'])
                ->pluck('following_id')
                ->map(fn ($id): string => (string) $id)
                ->all();
        }

        $userCollection->each(function (User $user) use ($connections, $authUserId, $followedUserIds, $likeCounts, $likedVideoOwnerIds): void {
            $isSelf = (string) $user->id === $authUserId;

            $user->setAttribute('is_following', ! $isSelf && in_array((string) $user->id, $followedUserIds, true));
            $user->setAttribute('is_pro', $this->calculateIsPro($user));
            $user->setAttribute('is_verified', (bool) ($user->is_verified ?? false));
            $user->setAttribute('likes_count', (int) ($likeCounts->get((string) $user->id) ?? 0));
            $user->setAttribute('is_liked', in_array((string) $user->id, $likedVideoOwnerIds, true));

            if ($isSelf) {
                $user->setAttribute('is_connected', false);
                $user->setAttribute('connection_status', 'self');
                $user->setAttribute('is_requested', false);
                $user->setAttribute('can_send_connection_request', false);

                return;
            }

            $connection = $connections->get((string) $user->id);

            if (! $connection) {
                $user->setAttribute('is_connected', false);
                $user->setAttribute('connection_status', null);
                $user->setAttribute('is_requested', false);
                $user->setAttribute('can_send_connection_request', true);

                return;
            }

            $isConnected = (bool) $connection->is_approved;
            $isRequested = ! $connection->is_approved && (string) $connection->requester_id === $authUserId;
            $status = $isConnected
                ? 'connected'
                : ($isRequested ? 'pending_sent' : 'pending_received');

            $user->setAttribute('is_connected', $isConnected);
            $user->setAttribute('connection_status', $status);
            $user->setAttribute('is_requested', $isRequested);
            $user->setAttribute('can_send_connection_request', false);
        });
    }

    /**
     * Determine if a user qualifies as a PRO / Paid member.
     */
    private function calculateIsPro(User $user): bool
    {
        $rawVerified = $user->is_verified ?? null;
        if ($rawVerified !== null && (bool) $rawVerified) {
            return true;
        }

        if (method_exists($user, 'isPaidMember')) {
            return (bool) $user->isPaidMember();
        }

        $status = strtolower(trim((string) ($user->effective_membership_status ?? $user->membership_status ?? '')));

        return $status !== '' && ! in_array($status, ['free_peer', 'free_trial_peer', 'visitor', 'suspended', 'free peer', 'free'], true);
    }

    /**
     * Format the response with the user's complete peer information and intro video details.
     */
    private function formatResponse(User $user, ?User $authUser = null): array
    {
        $introVideoId = $user->profile_video_id;

        $displayName = $user->display_name;
        if (blank($displayName)) {
            $displayName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        }

        $categoryName = $user->level4Category?->name
            ?? $user->businessCategory?->name
            ?? $user->business_sub_category
            ?? null;

        $cityName = null;
        if ($user->relationLoaded('city') && $user->city) {
            $cityName = $user->city instanceof City ? $user->city->name : (string) $user->city;
        } else {
            $cityName = is_string($user->city) ? $user->city : ($user->city_of_residence ?? null);
        }

        $isBookmark = false;
        if ($authUser) {
            $bookmarks = $authUser->bookmarks ?? [];
            if (is_array($bookmarks)) {
                $isBookmark = in_array((string) $user->id, $bookmarks, true);
            }
        }

        $isConnected = (bool) ($user->getAttribute('is_connected') ?? false);
        $connectionStatus = $user->getAttribute('connection_status');
        $isRequested = (bool) ($user->getAttribute('is_requested') ?? false);
        $canSendConnectionRequest = (bool) ($user->getAttribute('can_send_connection_request') ?? (! $isConnected && ! $isRequested && (! $authUser || (string) $authUser->id !== (string) $user->id)));
        $isFollowing = (bool) ($user->getAttribute('is_following') ?? false);
        $isPro = (bool) ($user->getAttribute('is_pro') ?? false);
        $isVerified = (bool) ($user->getAttribute('is_verified') ?? ($user->is_verified ?? false));

        $lifeImpactCount = (int) ($user->life_impacted_count ?? 0);
        $likesCount = (int) ($user->getAttribute('likes_count') ?? 0);
        $isLiked = (bool) ($user->getAttribute('is_liked') ?? false);

        return [
            'id' => (string) $user->id,
            'user_id' => (string) $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'display_name' => $displayName,
            'name' => $displayName,
            'email' => $user->email,
            'phone' => $user->phone,
            'company_name' => $user->company_name,
            'designation' => $user->designation,
            'city_name' => $cityName,
            'level4_category' => $categoryName,
            'profile_photo_url' => $user->profile_photo_url,
            'intro_video_id' => $introVideoId,
            'intro_video_url' => $user->profile_video_url,
            'life_impacted_count' => $lifeImpactCount,
            'likes_count' => $likesCount,
            'is_liked' => $isLiked,
            'is_connected' => $isConnected,
            'connection_status' => $connectionStatus,
            'is_requested' => $isRequested,
            'can_send_connection_request' => $canSendConnectionRequest,
            'is_pro' => $isPro,
            'is_verified' => $isVerified,
            'is_following' => $isFollowing,
            'is_bookmarked' => $isBookmark,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
