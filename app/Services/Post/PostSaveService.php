<?php

declare(strict_types=1);

namespace App\Services\Post;

use App\Models\Post;
use App\Models\PostSave;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PostSaveService
{
    /**
     * Store / save a post for the authenticated peer.
     *
     * @return array{post_id: string, is_saved: bool, saves_count: int, saved_at: ?string}
     */
    public function save(User $user, string $postId): array
    {
        $post = $this->findActivePost($postId);

        $save = PostSave::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'post_id' => $post->id,
            ]
        );

        $savesCount = PostSave::query()
            ->where('post_id', $post->id)
            ->count();

        return [
            'post_id' => (string) $post->id,
            'is_saved' => true,
            'saves_count' => $savesCount,
            'saved_at' => $save->created_at?->toIso8601String(),
        ];
    }

    /**
     * Unsave / remove a saved post for the authenticated peer.
     *
     * @return array{post_id: string, is_saved: bool, saves_count: int}
     */
    public function unsave(User $user, string $postId): array
    {
        $post = $this->findActivePost($postId);

        PostSave::query()
            ->where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->delete();

        $savesCount = PostSave::query()
            ->where('post_id', $post->id)
            ->count();

        return [
            'post_id' => (string) $post->id,
            'is_saved' => false,
            'saves_count' => $savesCount,
        ];
    }

    /**
     * Toggle save/unsave state of a post for the authenticated peer.
     *
     * @return array{post_id: string, is_saved: bool, saves_count: int, saved_at?: ?string}
     */
    public function toggle(User $user, string $postId): array
    {
        $post = $this->findActivePost($postId);

        $existingSave = PostSave::query()
            ->where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->first();

        if ($existingSave) {
            $existingSave->delete();

            $savesCount = PostSave::query()
                ->where('post_id', $post->id)
                ->count();

            return [
                'post_id' => (string) $post->id,
                'is_saved' => false,
                'saves_count' => $savesCount,
            ];
        }

        $newSave = PostSave::query()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        $savesCount = PostSave::query()
            ->where('post_id', $post->id)
            ->count();

        return [
            'post_id' => (string) $post->id,
            'is_saved' => true,
            'saves_count' => $savesCount,
            'saved_at' => $newSave->created_at?->toIso8601String(),
        ];
    }

    /**
     * Retrieve saved posts for a peer.
     */
    public function getSavedPosts(User $user, ?int $perPage = null): LengthAwarePaginator|Collection
    {
        $query = Post::query()
            ->select('posts.*')
            ->join('post_saves', 'post_saves.post_id', '=', 'posts.id')
            ->where('post_saves.user_id', $user->id)
            ->where('posts.is_deleted', false)
            ->whereNull('posts.deleted_at')
            ->with([
                'author:id,display_name,first_name,last_name,profile_photo_file_id',
                'circle:id,name',
            ])
            ->withCount(['likes', 'comments', 'saves'])
            ->withExists([
                'likes as is_liked_by_me' => fn ($q) => $q->where('user_id', $user->id),
                'saves as is_saved_by_me' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->orderByDesc('post_saves.created_at');

        if ($perPage !== null && $perPage > 0) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Find active (non-deleted) post by ID or fail.
     */
    private function findActivePost(string $postId): Post
    {
        return Post::query()
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->findOrFail($postId);
    }
}
