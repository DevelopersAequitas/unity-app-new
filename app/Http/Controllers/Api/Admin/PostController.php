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

    /**
     * Show detailed community post with author, engagement, comments, and reports.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $post = Post::withTrashed()
            ->with([
                'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,phone,designation',
                'circle:id,name',
                'comments.user:id,first_name,last_name,display_name,email,profile_photo_url',
                'reports.reporter:id,first_name,last_name,display_name,email',
            ])
            ->withCount(['likes', 'comments', 'reports'])
            ->find($id);

        if (! $post) {
            return $this->error('Post record not found', 404);
        }

        return $this->success($post);
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
}
