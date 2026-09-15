<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Post\StorePostSaveRequest;
use App\Http\Resources\PostResource;
use App\Models\User;
use App\Services\Post\PostSaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PostSaveController extends BaseApiController
{
    public function __construct(
        protected readonly PostSaveService $postSaveService
    ) {}

    /**
     * Store a saved post for the peer.
     * Can be invoked as POST /posts/save with { "post_id": "uuid" } or POST /posts/{id}/save.
     */
    public function store(StorePostSaveRequest $request, ?string $id = null): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $postId = $id ?? (string) $request->validated('post_id');

        $action = $request->validated('action');

        $result = match ($action) {
            'unsave' => $this->postSaveService->unsave($user, $postId),
            'toggle' => $this->postSaveService->toggle($user, $postId),
            default => $this->postSaveService->save($user, $postId),
        };

        $message = $result['is_saved'] ? 'Post saved successfully' : 'Post unsaved successfully';

        return $this->success($result, $message);
    }

    /**
     * Save a post for the peer.
     */
    public function save(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->postSaveService->save($user, $id);

        return $this->success($result, 'Post saved successfully');
    }

    /**
     * Unsave / remove a saved post for the peer.
     */
    public function unsave(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->postSaveService->unsave($user, $id);

        return $this->success($result, 'Post unsaved successfully');
    }

    /**
     * Remove a saved post for the peer (REST DELETE alias for unsave).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        return $this->unsave($request, $id);
    }

    /**
     * Toggle saving a post for the peer.
     */
    public function toggle(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->postSaveService->toggle($user, $id);

        $message = $result['is_saved'] ? 'Post saved' : 'Post unsaved';

        return $this->success($result, $message);
    }

    /**
     * Get the peer's saved posts.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $perPage = $request->has('per_page')
            ? max(1, min((int) $request->integer('per_page', 20), 100))
            : null;

        $posts = $this->postSaveService->getSavedPosts($user, $perPage);

        if ($posts instanceof LengthAwarePaginator) {
            return $this->success([
                'items' => PostResource::collection($posts->items()),
                'pagination' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                ],
            ], 'Saved posts fetched successfully');
        }

        return $this->success([
            'items' => PostResource::collection($posts),
        ], 'Saved posts fetched successfully');
    }
}
