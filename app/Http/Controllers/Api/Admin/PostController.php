<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\UpdatePostStatusRequest;
use App\Models\Post;
use App\Services\Admin\AdminPostService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostController extends BaseApiController
{
    public function __construct(
        protected readonly AdminPostService $postService
    ) {}

    /**
     * Display a listing of community posts with engagement stats and moderation filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Post::query()
            ->with([
                'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,active_circle_id',
                'user.circle:id,name',
                'circle:id,name',
                'likes' => fn ($q) => $q->latest('created_at')->limit(20)->with([
                    'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,active_circle_id',
                    'user.circle:id,name',
                ]),
                'comments' => fn ($q) => $q->latest('created_at')->limit(50)->with([
                    'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,active_circle_id',
                    'user.circle:id,name',
                ]),
            ])
            ->withCount(['likes', 'comments', 'reports']);

        // Moderation / active status filter
        if ($status = $request->query('status')) {
            if ($status !== 'all') {
                if ($status === 'active') {
                    $query->where('status', 'active')->where('is_deleted', false);
                } elseif ($status === 'inactive') {
                    $query->where(function ($q): void {
                        $q->where('status', 'inactive')->orWhere('is_deleted', true);
                    });
                } elseif ($status === 'deleted') {
                    $query->where('is_deleted', true);
                } else {
                    $query->where(function ($q) use ($status): void {
                        $q->where('status', $status)->orWhere('moderation_status', $status);
                    });
                }
            }
        }

        // Post Type filter (ask, impact, general, birthday, collaboration)
        if ($postType = $request->query('post_type')) {
            if ($postType !== 'all') {
                $query->where('post_type', $postType);
            }
        }

        // Circle filter
        if ($circleId = $request->query('circle_id')) {
            if ($circleId !== 'all') {
                $query->where('circle_id', $circleId);
            }
        }

        // User / author filter
        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        // Filter for posts with reports
        if ($request->boolean('has_reports')) {
            $query->has('reports');
        }

        // Date range filters
        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // Debounced search (content text, title, author name)
        if ($search = $request->query('search')) {
            $search = trim((string) $search);
            $query->where(function ($q) use ($search): void {
                $q->where('content_text', 'ILIKE', "%{$search}%")
                    ->orWhere('title', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search): void {
                        $uq->where('display_name', 'ILIKE', "%{$search}%")
                            ->orWhere('first_name', 'ILIKE', "%{$search}%")
                            ->orWhere('last_name', 'ILIKE', "%{$search}%")
                            ->orWhere('email', 'ILIKE', "%{$search}%")
                            ->orWhere('company_name', 'ILIKE', "%{$search}%");
                    });
            });
        }

        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (Post $post): array => $this->formatPostItem($post))
            ->values()
            ->all();

        return $this->success([
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Show detailed community post with author, engagement, comments, and reports.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $post = Post::withTrashed()
            ->with([
                'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,phone,designation,active_circle_id',
                'user.circle:id,name',
                'circle:id,name',
                'likes' => fn ($q) => $q->latest('created_at')->limit(20)->with([
                    'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,active_circle_id',
                    'user.circle:id,name',
                ]),
                'comments' => fn ($q) => $q->latest('created_at')->limit(50)->with([
                    'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,active_circle_id',
                    'user.circle:id,name',
                ]),
                'reports.reporter:id,first_name,last_name,display_name,email',
            ])
            ->withCount(['likes', 'comments', 'reports'])
            ->find($id);

        if (! $post) {
            return $this->error('Post record not found', 404);
        }

        return $this->success($this->formatPostItem($post));
    }

    /**
     * Update post status / moderation_status (e.g. approve, reject, hide, restore, active, inactive).
     */
    public function updateStatus(UpdatePostStatusRequest $request, string $id): JsonResponse
    {
        $post = Post::withTrashed()->find($id);
        if (! $post) {
            return $this->error('Post record not found', 404);
        }

        $post = $this->postService->updateStatus($post, $request->validated());

        $post->load([
            'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,active_circle_id',
            'user.circle:id,name',
            'circle:id,name',
            'likes' => fn ($q) => $q->latest('created_at')->limit(20)->with([
                'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,active_circle_id',
                'user.circle:id,name',
            ]),
            'comments' => fn ($q) => $q->latest('created_at')->limit(50)->with([
                'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,active_circle_id',
                'user.circle:id,name',
            ]),
        ]);
        $post->loadCount(['likes', 'comments', 'reports']);

        return $this->success($this->formatPostItem($post), 'Post moderation status updated successfully.');
    }

    /**
     * Toggle post active/inactive status.
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $post = Post::withTrashed()->find($id);
        if (! $post) {
            return $this->error('Post record not found', 404);
        }

        $post = $this->postService->toggleStatus($post);

        $post->load([
            'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,active_circle_id',
            'user.circle:id,name',
            'circle:id,name',
            'likes' => fn ($q) => $q->latest('created_at')->limit(20)->with([
                'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,active_circle_id',
                'user.circle:id,name',
            ]),
            'comments' => fn ($q) => $q->latest('created_at')->limit(50)->with([
                'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,active_circle_id',
                'user.circle:id,name',
            ]),
        ]);
        $post->loadCount(['likes', 'comments', 'reports']);

        return $this->success($this->formatPostItem($post), "Post status updated to '{$post->status}' successfully.");
    }

    /**
     * Format a post model into an array with serialized media, aspect ratio, and eager-loaded engagement lists.
     */
    protected function formatPostItem(Post $post): array
    {
        $data = $post->toArray();
        $mediaUrl = $post->video_path
            ? asset('storage/'.ltrim((string) $post->video_path, '/'))
            : ($post->media_url ? (Str::startsWith((string) $post->media_url, ['http://', 'https://']) ? (string) $post->media_url : url((string) $post->media_url)) : $post->media_url);

        $mediaType = ($post->video_path || preg_match('/\.(mp4|mov|webm|m4v)(\?.*)?$/i', (string) ($post->media_url ?? $mediaUrl ?? '')) || $post->media_type === 'video')
            ? 'video'
            : ($mediaUrl || $post->media_type === 'image' ? 'image' : null);

        $data['media_url'] = $mediaUrl;
        $data['media_type'] = $mediaType;
        $data['aspect_ratio'] = '4:5';

        if ($post->relationLoaded('likes')) {
            $data['likes_list'] = $post->likes->map(function ($like): array {
                $user = $like->user;
                $avatar = $user?->avatar ?? $user?->profile_photo_url;
                $avatarUrl = $avatar
                    ? (Str::startsWith((string) $avatar, ['http://', 'https://']) ? (string) $avatar : url((string) $avatar))
                    : null;

                return [
                    'id' => (string) ($like->id ?? $user?->id),
                    'user_id' => (string) ($user?->id ?? $like->user_id),
                    'name' => $user?->name ?? $user?->display_name ?? 'Verified Peer',
                    'avatar' => $avatarUrl,
                    'company' => $user?->company_name ?? 'Independent Member',
                    'circle_name' => $user?->circle?->name ?? null,
                    'liked_at' => $like->created_at?->toISOString() ?? (is_string($like->created_at) ? $like->created_at : now()->toISOString()),
                ];
            })->values()->all();
        }

        if ($post->relationLoaded('comments')) {
            $data['comments_list'] = $post->comments->map(function ($comment): array {
                $user = $comment->user;
                $userName = $user?->name ?? $user?->display_name ?? 'Peer Member';
                $avatar = $user?->avatar ?? $user?->profile_photo_url;
                $avatarUrl = $avatar
                    ? (Str::startsWith((string) $avatar, ['http://', 'https://']) ? (string) $avatar : url((string) $avatar))
                    : null;

                return [
                    'id' => (string) $comment->id,
                    'author_id' => (string) ($user?->id ?? $comment->user_id),
                    'author_name' => $userName,
                    'author_avatar' => $avatarUrl,
                    'name' => $userName,
                    'avatar' => $avatarUrl,
                    'company' => $user?->company_name ?? 'Independent Member',
                    'circle_name' => $user?->circle?->name ?? null,
                    'content' => $comment->content ?? $comment->comment_text ?? '',
                    'created_at' => $comment->created_at?->toISOString() ?? (is_string($comment->created_at) ? $comment->created_at : now()->toISOString()),
                ];
            })->values()->all();
        }

        return $data;
    }

    /**
     * Remove / soft-delete a post from the community feed.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $post = Post::find($id);
        if (! $post) {
            return $this->error('Post record not found', 404);
        }

        $this->postService->destroy($post);

        return $this->success(['id' => $id, 'deleted' => true], 'Post removed from community feed successfully.');
    }

    /**
     * Return the roster of peers who liked a specific post.
     */
    public function getLikes(Request $request, string $id): JsonResponse
    {
        $post = Post::query()->where('id', $id)->first();

        if (! $post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found',
                'data' => [],
            ], 404);
        }

        $likesData = [];
        if (method_exists($post, 'likes')) {
            $likes = $post->likes()
                ->with([
                    'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,active_circle_id',
                    'user.circle:id,name',
                    'user.circleMembers.circle:id,name',
                ])
                ->latest('created_at')
                ->get();

            $likesData = $likes->map(function ($like): array {
                $user = $like->user;
                $circleName = $user?->circle?->name
                    ?? $user?->circleMembers?->first()?->circle?->name
                    ?? null;
                $avatar = $user?->avatar ?? $user?->profile_photo_url;
                $avatarUrl = $avatar
                    ? (Str::startsWith((string) $avatar, ['http://', 'https://']) ? (string) $avatar : url((string) $avatar))
                    : null;

                return [
                    'id' => (string) ($like->id ?? $user?->id),
                    'user_id' => (string) ($user?->id ?? $like->user_id),
                    'name' => $user?->name ?? $user?->display_name ?? 'Unknown Peer',
                    'avatar' => $avatarUrl,
                    'company' => $user?->company_name ?? 'Independent Member',
                    'circle_name' => $circleName,
                    'liked_at' => $like->created_at?->toISOString() ?? (is_string($like->created_at) ? $like->created_at : now()->toISOString()),
                ];
            })->values()->all();
        }

        return response()->json([
            'success' => true,
            'message' => 'Post likes retrieved successfully',
            'data' => $likesData,
        ], 200);
    }

    /**
     * Return top-level comments (with nested replies and author details) for a specific post.
     */
    public function getComments(Request $request, string $id): JsonResponse
    {
        $post = Post::query()->where('id', $id)->first();

        if (! $post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found',
                'data' => [],
            ], 404);
        }

        $commentsData = [];
        if (method_exists($post, 'comments')) {
            $comments = $post->comments()
                ->with([
                    'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,active_circle_id',
                    'user.circle:id,name',
                    'children.user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,active_circle_id',
                    'children.user.circle:id,name',
                ])
                ->whereNull('parent_id')
                ->latest()
                ->get();

            $commentsData = $comments->map(function ($comment): array {
                $user = $comment->user;
                $userName = $user?->name ?? $user?->display_name ?? 'Unknown Peer';
                $avatar = $user?->avatar ?? $user?->profile_photo_url;
                $avatarUrl = $avatar
                    ? (Str::startsWith((string) $avatar, ['http://', 'https://']) ? (string) $avatar : url((string) $avatar))
                    : null;
                $circleName = $user?->circle?->name ?? null;

                $replies = $comment->children->map(function ($reply): array {
                    $replyUser = $reply->user;
                    $replyName = $replyUser?->name ?? $replyUser?->display_name ?? 'Unknown Peer';
                    $replyAvatar = $replyUser?->avatar ?? $replyUser?->profile_photo_url;
                    $replyAvatarUrl = $replyAvatar
                        ? (Str::startsWith((string) $replyAvatar, ['http://', 'https://']) ? (string) $replyAvatar : url((string) $replyAvatar))
                        : null;

                    return [
                        'id' => (string) $reply->id,
                        'parent_id' => (string) $reply->parent_id,
                        'post_id' => (string) $reply->post_id,
                        'user_id' => (string) ($replyUser?->id ?? $reply->user_id),
                        'content' => $reply->content,
                        'name' => $replyName,
                        'avatar' => $replyAvatarUrl,
                        'company' => $replyUser?->company_name ?? 'Independent Member',
                        'circle_name' => $replyUser?->circle?->name ?? null,
                        'created_at' => $reply->created_at?->toISOString() ?? now()->toISOString(),
                        'deleted_at' => $reply->deleted_at?->toISOString(),
                        'user' => $replyUser ? [
                            'id' => (string) $replyUser->id,
                            'name' => $replyName,
                            'display_name' => $replyUser->display_name,
                            'avatar' => $replyAvatarUrl,
                            'profile_photo_url' => $replyAvatarUrl,
                            'company_name' => $replyUser->company_name ?? 'Independent Member',
                            'company' => $replyUser->company_name ?? 'Independent Member',
                            'circle_name' => $replyUser->circle?->name ?? null,
                        ] : null,
                    ];
                })->values()->all();

                return [
                    'id' => (string) $comment->id,
                    'post_id' => (string) $comment->post_id,
                    'user_id' => (string) ($user?->id ?? $comment->user_id),
                    'parent_id' => $comment->parent_id,
                    'content' => $comment->content,
                    'name' => $userName,
                    'avatar' => $avatarUrl,
                    'company' => $user?->company_name ?? 'Independent Member',
                    'circle_name' => $circleName,
                    'created_at' => $comment->created_at?->toISOString() ?? now()->toISOString(),
                    'deleted_at' => $comment->deleted_at?->toISOString(),
                    'user' => $user ? [
                        'id' => (string) $user->id,
                        'name' => $userName,
                        'display_name' => $user->display_name,
                        'avatar' => $avatarUrl,
                        'profile_photo_url' => $avatarUrl,
                        'company_name' => $user->company_name ?? 'Independent Member',
                        'company' => $user->company_name ?? 'Independent Member',
                        'circle_name' => $circleName,
                    ] : null,
                    'author' => $user ? [
                        'id' => (string) $user->id,
                        'name' => $userName,
                        'display_name' => $user->display_name,
                        'avatar' => $avatarUrl,
                        'profile_photo_url' => $avatarUrl,
                        'company_name' => $user->company_name ?? 'Independent Member',
                        'company' => $user->company_name ?? 'Independent Member',
                        'circle_name' => $circleName,
                    ] : null,
                    'replies_count' => count($replies),
                    'replies' => $replies,
                ];
            })->values()->all();
        }

        return response()->json([
            'success' => true,
            'message' => 'Post comments retrieved successfully',
            'data' => $commentsData,
        ], 200);
    }

    /**
     * Soft-delete a specific comment on a post (admin moderation).
     */
    public function deleteComment(Request $request, string $id, string $commentId): JsonResponse
    {
        $post = Post::find($id);
        if (! $post) {
            return $this->error('Post record not found', 404);
        }

        $comment = $post->comments()->where('id', $commentId)->first();
        if (! $comment) {
            return $this->error('Comment not found for this post', 404);
        }

        $comment->delete();

        return $this->success(
            ['id' => $commentId, 'post_id' => $id, 'deleted' => true],
            'Comment removed successfully.'
        );
    }
}
