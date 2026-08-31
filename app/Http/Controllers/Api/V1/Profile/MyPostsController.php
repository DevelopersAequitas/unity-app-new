<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\V1\PostLikeResource;
use App\Http\Resources\V1\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyPostsController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $posts = Post::query()
            ->where('user_id', $user->id)
            ->where('posts.is_deleted', false)
            ->whereNull('posts.deleted_at')
            ->with([
                'author:id,display_name,profile_photo_file_id',
                'comments' => function ($query) {
                    $query
                        ->with('user:id,display_name,profile_photo_file_id')
                        ->latest('created_at')
                        ->limit(2);
                },
            ])
            ->withCount(['likes', 'comments'])
            ->withExists([
                'likes as liked_by_me' => fn ($query) => $query->where('user_id', $user->id),
                'saves as is_saved_by_me' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->withCount('saves')
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'items' => PostResource::collection($posts),
        ]);
    }

    public function likes(Request $request, string $postId): JsonResponse
    {
        $user = $request->user();

        $post = Post::query()
            ->where('id', $postId)
            ->where('posts.is_deleted', false)
            ->whereNull('posts.deleted_at')
            ->first();

        if (! $post) {
            return $this->error('Post not found', 404);
        }

        if ($post->user_id !== $user->id && $post->visibility !== 'public') {
            return $this->error('Forbidden', 403);
        }

        $likes = $post->likes()
            ->with([
                'user.city',
                'user.businessCategory',
                'user.mainBusinessCategory',
            ])
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'items' => PostLikeResource::collection($likes),
        ]);
    }
}
