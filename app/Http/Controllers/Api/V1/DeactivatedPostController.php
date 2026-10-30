<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Posts\GetDeactivatedPostsRequest;
use App\Services\Posts\DeactivatedPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeactivatedPostController extends BaseApiController
{
    public function __construct(
        protected DeactivatedPostService $deactivatedPostService
    ) {}

    /**
     * Get paginated deactivated posts.
     * Optionally filtered by user_id and search keyword.
     */
    public function index(GetDeactivatedPostsRequest $request): JsonResponse
    {
        $userId = $request->targetUserId();
        $perPage = (int) ($request->validated('per_page') ?? 15);
        $search = $request->validated('search');

        $filters = array_filter([
            'user_id' => $userId,
            'search' => $search,
        ]);

        $paginator = $this->deactivatedPostService->getDeactivatedPosts($filters, $perPage);

        return $this->success(
            data: [
                'items' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            message: 'Deactivated posts retrieved successfully.'
        );
    }

    /**
     * Get a single deactivated post by ID.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $post = $this->deactivatedPostService->getDeactivatedPostById($id);

        if (! $post) {
            return $this->error('Deactivated post not found.', 404);
        }

        return $this->success(
            data: $post,
            message: 'Deactivated post retrieved successfully.'
        );
    }

    /**
     * Get the latest deactivated post to show for the user (e.g. for modal / notice alert).
     */
    public function notice(GetDeactivatedPostsRequest $request): JsonResponse
    {
        $userId = $request->targetUserId();
        if (empty($userId)) {
            return $this->error('User ID is required or user must be authenticated.', 401);
        }

        $post = $this->deactivatedPostService->getLatestDeactivatedPostForUser($userId);

        if (! $post) {
            return $this->success(
                data: [
                    'has_deactivated_post' => false,
                    'post' => null,
                ],
                message: 'No deactivated posts found.'
            );
        }

        return $this->success(
            data: [
                'has_deactivated_post' => true,
                'post' => $post,
            ],
            message: 'Latest deactivated post retrieved successfully.'
        );
    }
}

