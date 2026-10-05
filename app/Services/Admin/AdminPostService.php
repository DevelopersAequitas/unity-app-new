<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Post;
use Illuminate\Support\Facades\DB;

class AdminPostService
{
    /**
     * Update post status, active flags, and moderation state.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateStatus(Post $post, array $data): Post
    {
        return DB::transaction(function () use ($post, $data): Post {
            $status = $data['status'] ?? null;

            if ($status === null && (isset($data['active']) || isset($data['is_active']))) {
                $boolVal = (bool) ($data['active'] ?? $data['is_active']);
                $status = $boolVal ? 'active' : 'inactive';
            }

            if ($status !== null) {
                if (in_array($status, ['approved', 'published', 'active'], true)) {
                    $post->status = 'active';
                    $post->active = true;
                    $post->is_active = true;
                    $post->moderation_status = $data['moderation_status'] ?? 'approved';
                    $post->is_deleted = false;
                    if ($post->trashed()) {
                        $post->restore();
                    }
                } elseif (in_array($status, ['rejected', 'hidden', 'inactive'], true)) {
                    $post->status = 'inactive';
                    $post->active = false;
                    $post->is_active = false;
                    if ($status === 'rejected') {
                        $post->moderation_status = 'rejected';
                    }
                } elseif ($status === 'pending') {
                    $post->status = 'inactive';
                    $post->active = false;
                    $post->is_active = false;
                    $post->moderation_status = 'pending';
                } elseif ($status === 'flagged') {
                    $post->status = 'flagged';
                    $post->active = false;
                    $post->is_active = false;
                    $post->moderation_status = 'flagged';
                }
            }

            if (! empty($data['moderation_status'])) {
                $post->moderation_status = (string) $data['moderation_status'];
            }

            $post->save();

            return $post;
        });
    }

    /**
     * Toggle post between active and inactive.
     */
    public function toggleStatus(Post $post): Post
    {
        $isActive = ($post->status === 'active' || (bool) $post->active || (bool) $post->is_active);
        $newStatus = $isActive ? 'inactive' : 'active';

        return $this->updateStatus($post, [
            'status' => $newStatus,
        ]);
    }

    /**
     * Soft-delete post and mark as inactive.
     */
    public function destroy(Post $post): void
    {
        DB::transaction(function () use ($post): void {
            $post->is_deleted = true;
            $post->active = false;
            $post->is_active = false;
            $post->status = 'inactive';
            $post->save();
            $post->delete();
        });
    }
}
