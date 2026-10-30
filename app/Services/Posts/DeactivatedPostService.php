<?php

declare(strict_types=1);

namespace App\Services\Posts;

use App\Models\Notifications\AppNotification;
use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DeactivatedPostService
{
    public function __construct(
        protected PostDeactivationService $postDeactivationService
    ) {}

    /**
     * Get paginated deactivated posts with optional filtering.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getDeactivatedPosts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Post::withTrashed()
            ->with([
                'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,city,active_circle_id',
                'user.circle:id,name',
                'circle:id,name',
            ])
            ->where(function (Builder $q): void {
                $hasRemarks = Schema::hasColumn('posts', 'deactivation_remarks');
                $hasDeactivatedAt = Schema::hasColumn('posts', 'deactivated_at');

                $q->where(function (Builder $sub) use ($hasRemarks, $hasDeactivatedAt): void {
                    if ($hasRemarks) {
                        $sub->whereNotNull('deactivation_remarks');
                    }
                    if ($hasDeactivatedAt) {
                        $sub->orWhereNotNull('deactivated_at');
                    }
                    $sub->orWhere('status', 'inactive')
                        ->orWhere('active', false)
                        ->orWhere('is_deleted', true);
                });
            });

        // Filter by specific user if provided
        if (! empty($filters['user_id']) && $filters['user_id'] !== 'all') {
            $query->where('user_id', (string) $filters['user_id']);
        }

        // Search by content text, title, or deactivation reason
        if (! empty($filters['search'])) {
            $search = '%'.trim((string) $filters['search']).'%';
            $query->where(function (Builder $q) use ($search): void {
                $q->where('content_text', 'LIKE', $search)
                    ->orWhere('title', 'LIKE', $search);

                if (Schema::hasColumn('posts', 'deactivation_remarks')) {
                    $q->orWhere('deactivation_remarks', 'LIKE', $search);
                }
            });
        }

        $orderCol = Schema::hasColumn('posts', 'deactivated_at') ? 'deactivated_at' : 'updated_at';
        $paginator = $query->orderByDesc($orderCol)->paginate($perPage);

        $paginator->getCollection()->transform(function (Post $post): array {
            return $this->formatDeactivatedPost($post);
        });

        return $paginator;
    }

    /**
     * Get a single deactivated post by its ID.
     *
     * @return array<string, mixed>|null
     */
    public function getDeactivatedPostById(string $id, ?string $userId = null): ?array
    {
        $query = Post::withTrashed()
            ->with([
                'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,city,active_circle_id',
                'user.circle:id,name',
                'circle:id,name',
            ])
            ->where('id', $id)
            ->where(function (Builder $q): void {
                $hasRemarks = Schema::hasColumn('posts', 'deactivation_remarks');
                $hasDeactivatedAt = Schema::hasColumn('posts', 'deactivated_at');

                $q->where(function (Builder $sub) use ($hasRemarks, $hasDeactivatedAt): void {
                    if ($hasRemarks) {
                        $sub->whereNotNull('deactivation_remarks');
                    }
                    if ($hasDeactivatedAt) {
                        $sub->orWhereNotNull('deactivated_at');
                    }
                    $sub->orWhere('status', 'inactive')
                        ->orWhere('active', false)
                        ->orWhere('is_deleted', true);
                });
            });

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $post = $query->first();

        return $post ? $this->formatDeactivatedPost($post) : null;
    }

    /**
     * Get the latest deactivated post for a specific user.
     *
     * @return array<string, mixed>|null
     */
    public function getLatestDeactivatedPostForUser(string $userId): ?array
    {
        $orderCol = Schema::hasColumn('posts', 'deactivated_at') ? 'deactivated_at' : 'updated_at';

        $post = Post::withTrashed()
            ->with([
                'user:id,first_name,last_name,display_name,email,company_name,profile_photo_url,city,active_circle_id',
                'user.circle:id,name',
                'circle:id,name',
            ])
            ->where('user_id', $userId)
            ->where(function (Builder $q): void {
                $hasRemarks = Schema::hasColumn('posts', 'deactivation_remarks');
                $hasDeactivatedAt = Schema::hasColumn('posts', 'deactivated_at');

                $q->where(function (Builder $sub) use ($hasRemarks, $hasDeactivatedAt): void {
                    if ($hasRemarks) {
                        $sub->whereNotNull('deactivation_remarks');
                    }
                    if ($hasDeactivatedAt) {
                        $sub->orWhereNotNull('deactivated_at');
                    }
                    $sub->orWhere('status', 'inactive')
                        ->orWhere('active', false)
                        ->orWhere('is_deleted', true);
                });
            })
            ->orderByDesc($orderCol)
            ->first();

        return $post ? $this->formatDeactivatedPost($post) : null;
    }

    /**
     * Format a deactivated post into a clean API response payload.
     *
     * @return array<string, mixed>
     */
    public function formatDeactivatedPost(Post $post): array
    {
        $owner = $post->user;
        $ownerName = $owner
            ? ($owner->display_name ?: trim(($owner->first_name ?? '').' '.($owner->last_name ?? '')))
            : '—';

        $ownerAvatar = $owner?->profile_photo_url;
        $ownerAvatarUrl = $ownerAvatar
            ? (Str::startsWith((string) $ownerAvatar, ['http://', 'https://']) ? (string) $ownerAvatar : url((string) $ownerAvatar))
            : null;

        $notificationData = $this->resolveNotificationData((string) $post->user_id, (string) $post->id);

        $remarks = (string) ($post->deactivation_remarks ?: ($notificationData['remarks'] ?? ''));
        $coinsReversed = (int) ($notificationData['coins_reversed'] ?? 0);
        $impactReversed = (int) ($notificationData['impact_reversed'] ?? 0);
        $decreaseSummary = (string) ($notificationData['decrease_summary'] ?? '');

        $mediaUrl = $this->postDeactivationService->resolvePostImageUrl($post);

        return [
            'id' => (string) $post->id,
            'user_id' => (string) $post->user_id,
            'peer' => [
                'id' => (string) ($owner?->id ?? $post->user_id),
                'name' => $ownerName,
                'email' => $owner?->email,
                'company' => $owner?->company_name ?? '—',
                'city' => $owner?->city ?? '—',
                'avatar' => $ownerAvatarUrl,
                'circle' => $post->circle?->name ?? $owner?->circle?->name ?? null,
            ],
            'title' => (string) ($post->title ?: ''),
            'content_text' => (string) ($post->content_text ?: $post->description ?: ''),
            'media' => $post->media,
            'media_url' => $mediaUrl,
            'visibility' => (string) ($post->visibility ?? 'public'),
            'moderation_status' => (string) ($post->moderation_status ?? 'approved'),
            'active' => false,
            'is_active' => false,
            'status' => (string) ($post->status ?? 'inactive'),
            'reason' => $remarks,
            'deactivation_remarks' => $remarks,
            'coins_reversed' => $coinsReversed,
            'impact_reversed' => $impactReversed,
            'decrease_summary' => $decreaseSummary,
            'deactivated_at' => $post->deactivated_at?->toISOString() ?? $post->updated_at?->toISOString(),
            'deactivated_by' => (string) ($post->deactivated_by ?? ''),
            'created_at' => $post->created_at?->toISOString(),
            'updated_at' => $post->updated_at?->toISOString(),
        ];
    }

    /**
     * Helper to retrieve rich deactivation payload from AppNotification if available.
     *
     * @return array<string, mixed>
     */
    protected function resolveNotificationData(string $userId, string $postId): array
    {
        if (! Schema::hasTable('app_notifications')) {
            return [];
        }

        $notification = AppNotification::where('user_id', $userId)
            ->where('type', 'post_deactivated')
            ->where(function ($q) use ($postId): void {
                $q->where('reference_id', $postId)
                    ->orWhereRaw("data->>'post_id' = ?", [$postId]);
            })
            ->latest()
            ->first();

        return (array) ($notification?->data ?? []);
    }
}
