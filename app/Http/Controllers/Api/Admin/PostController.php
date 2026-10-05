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
                'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url',
                'circle:id,name',
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

        $items = collect($paginator->items())->map(function (Post $post): array {
            $data = $post->toArray();
            $mediaUrl = $post->video_path
                ? asset('storage/'.ltrim((string) $post->video_path, '/'))
                : ($post->media_url ? (Str::startsWith((string) $post->media_url, ['http://', 'https://']) ? (string) $post->media_url : url((string) $post->media_url)) : $post->media_url);

            $mediaType = ($post->video_path || preg_match('/\.(mp4|mov|webm|m4v)(\?.*)?$/i', (string) ($post->media_url ?? $mediaUrl ?? '')) || $post->media_type === 'video')
                ? 'video'
                : ($mediaUrl || $post->media_type === 'image' ? 'image' : null);

            $data['media_url'] = $mediaUrl;
            $data['media_type'] = $mediaType;

            return $data;
        })->values()->all();

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
                'circle:id,name',
                'comments.user:id,first_name,last_name,display_name,email,profile_photo_url,company_name',
                'reports.reporter:id,first_name,last_name,display_name,email',
            ])
            ->withCount(['likes', 'comments', 'reports'])
            ->find($id);

        if (! $post) {
            return $this->error('Post record not found', 404);
        }

        $data = $post->toArray();
        $mediaUrl = $post->video_path
            ? asset('storage/'.ltrim((string) $post->video_path, '/'))
            : ($post->media_url ? (Str::startsWith((string) $post->media_url, ['http://', 'https://']) ? (string) $post->media_url : url((string) $post->media_url)) : $post->media_url);

        $mediaType = ($post->video_path || preg_match('/\.(mp4|mov|webm|m4v)(\?.*)?$/i', (string) ($post->media_url ?? $mediaUrl ?? '')) || $post->media_type === 'video')
            ? 'video'
            : ($mediaUrl || $post->media_type === 'image' ? 'image' : null);

        $data['media_url'] = $mediaUrl;
        $data['media_type'] = $mediaType;

        return $this->success($data);
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

        $post->load(['user', 'circle']);
        $post->loadCount(['likes', 'comments', 'reports']);

        return $this->success($post, 'Post moderation status updated successfully.');
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

        $post->load(['user', 'circle']);
        $post->loadCount(['likes', 'comments', 'reports']);

        return $this->success($post, "Post status updated to '{$post->status}' successfully.");
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
        $post = Post::find($id);
        if (! $post) {
            return $this->error('Post record not found', 404);
        }

        $likes = $post->likes()
            ->with([
                'user:id,first_name,last_name,display_name,email,profile_photo_url,company_name,designation,active_circle_id',
                'user.circle:id,name',
                'user.circleMembers.circle:id,name',
            ])
            ->latest('created_at')
            ->get();

        $data = $likes->map(function ($like): array {
            $user = $like->user;
            $name = $user?->name ?? $user?->display_name ?? trim(($user?->first_name ?? '').' '.($user?->last_name ?? '')) ?: 'Peer Member';
            $circleName = $user?->circle?->name
                ?? $user?->circleMembers?->first()?->circle?->name
                ?? null;

            return [
                'id' => (string) ($user?->id ?? $like->id),
                'name' => $name,
                'avatar' => $user?->avatar ?? $user?->profile_photo_url,
                'company' => $user?->company_name,
                'circle_name' => $circleName,
                'liked_at' => $like->created_at?->toIso8601String() ?? (string) $like->created_at,
            ];
        })->values()->all();

        return $this->success($data);
    }

    /**
     * Return top-level comments (with nested replies and author details) for a specific post.
     */
    public function getComments(Request $request, string $id): JsonResponse
    {
        $post = Post::find($id);
        if (! $post) {
            return $this->error('Post record not found', 404);
        }

        $comments = $post->comments()
            ->with([
                'user:id,first_name,last_name,display_name,email,profile_photo_url,company_name,designation,active_circle_id',
                'children.user:id,first_name,last_name,display_name,email,profile_photo_url,company_name,designation',
            ])
            ->whereNull('parent_id')
            ->latest()
            ->get();

        $data = $comments->map(function ($comment): array {
            $user = $comment->user;
            $userName = $user?->name ?? $user?->display_name ?? trim(($user?->first_name ?? '').' '.($user?->last_name ?? '')) ?: 'Peer Member';

            $replies = $comment->children->map(function ($reply): array {
                $replyUser = $reply->user;
                $replyName = $replyUser?->name ?? $replyUser?->display_name ?? trim(($replyUser?->first_name ?? '').' '.($replyUser?->last_name ?? '')) ?: 'Peer Member';

                return [
                    'id' => $reply->id,
                    'parent_id' => $reply->parent_id,
                    'content' => $reply->content,
                    'created_at' => $reply->created_at?->toIso8601String() ?? (string) $reply->created_at,
                    'deleted_at' => $reply->deleted_at?->toIso8601String(),
                    'user' => $replyUser ? [
                        'id' => $replyUser->id,
                        'name' => $replyName,
                        'display_name' => $replyUser->display_name,
                        'avatar' => $replyUser->avatar ?? $replyUser->profile_photo_url,
                        'profile_photo_url' => $replyUser->profile_photo_url,
                        'company_name' => $replyUser->company_name,
                        'company' => $replyUser->company_name,
                    ] : null,
                ];
            })->values()->all();

            return [
                'id' => $comment->id,
                'post_id' => $comment->post_id,
                'user_id' => $comment->user_id,
                'parent_id' => $comment->parent_id,
                'content' => $comment->content,
                'created_at' => $comment->created_at?->toIso8601String() ?? (string) $comment->created_at,
                'deleted_at' => $comment->deleted_at?->toIso8601String(),
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $userName,
                    'display_name' => $user->display_name,
                    'avatar' => $user->avatar ?? $user->profile_photo_url,
                    'profile_photo_url' => $user->profile_photo_url,
                    'company_name' => $user->company_name,
                    'company' => $user->company_name,
                ] : null,
                'replies_count' => count($replies),
                'replies' => $replies,
            ];
        })->values()->all();

        return $this->success($data);
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
