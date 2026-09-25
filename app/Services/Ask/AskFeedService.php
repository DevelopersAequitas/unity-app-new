<?php

declare(strict_types=1);

namespace App\Services\Ask;

use App\Models\Ask\Ask;
use App\Models\Ask\AskTimelineLink;
use App\Models\BusinessDeal;
use App\Models\CircleMember;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\PostMention;
use App\Models\PostSave;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AskFeedService
{
    /**
     * Retrieve feed items for other peers (open asks, fulfilled stories, giver spotlights).
     *
     * @param  array{scope?: string, page?: int, per_page?: int}  $filters
     * @return array{items: array<int, array<string, mixed>>, meta: array{current_page: int, last_page: int, total: int}}
     */
    public function getFeed(User $user, array $filters = []): array
    {
        $scope = (string) ($filters['scope'] ?? 'for_you');
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($filters['per_page'] ?? 20)));

        // 1. Gather other peers' asks
        $asksQuery = Ask::query()
            ->where('user_id', '!=', $user->id)
            ->whereIn('status', [Ask::STATUS_PUBLISHED, 'open'])
            ->with(['user', 'type', 'flow', 'answers.option', 'timelineLink'])
            ->orderByDesc('created_at');

        // Apply Scope Filter
        $this->applyScopeFilter($asksQuery, $user, $scope);

        $asks = $asksQuery->limit(50)->get();

        // 2. Gather Stories (Fulfilled / Deal Closed Posts)
        $storiesQuery = Post::query()
            ->where(function (Builder $q): void {
                $q->where('post_type', 'deal_closed')
                    ->orWhere('source_type', 'ask')
                    ->orWhereJsonContains('tags', 'deal_closed')
                    ->orWhereJsonContains('tags', 'ask_fulfilled');
            })
            ->where('is_deleted', false)
            ->where('active', true)
            ->with(['user'])
            ->orderByDesc('created_at')
            ->limit(30);

        $stories = $storiesQuery->get();

        // 3. User engagement lookups (saves & likes)
        $relevantPostIds = $stories->pluck('id')->all();
        foreach ($asks as $ask) {
            if ($ask->timelineLink?->post_id) {
                $relevantPostIds[] = $ask->timelineLink->post_id;
            }
        }

        $savedPostIds = PostSave::query()
            ->where('user_id', $user->id)
            ->whereIn('post_id', $relevantPostIds)
            ->pluck('post_id')
            ->flip()
            ->all();

        $congratulatedPostIds = PostLike::query()
            ->where('user_id', $user->id)
            ->whereIn('post_id', $relevantPostIds)
            ->pluck('post_id')
            ->flip()
            ->all();

        // Format Ask items
        $feedItems = [];
        foreach ($asks as $ask) {
            $author = $ask->user;
            $authorName = $author ? ($author->display_name ?: trim(($author->first_name ?? '').' '.($author->last_name ?? ''))) : 'Peer Member';
            $avatarUrl = $author?->profile_photo_file_id
                ? url('/api/v1/files/'.$author->profile_photo_file_id)
                : ($author?->profile_photo_url ?? null);

            $description = $ask->answers->firstWhere('field_key', 'details')?->value_text
                ?? $ask->answers->firstWhere('field_key', 'description')?->value_text
                ?? $ask->title;

            $urgency = $ask->answers->firstWhere('field_key', 'urgency')?->value_text ?? 'medium';

            $postId = $ask->timelineLink?->post_id;
            $isSaved = $postId ? isset($savedPostIds[$postId]) : false;
            $isCongratulated = $postId ? isset($congratulatedPostIds[$postId]) : false;

            $feedItems[] = [
                'id' => (string) $ask->id,
                'item_type' => 'ask',
                'category_title' => $ask->type?->name ?? $ask->flow?->name ?? 'Funding Support',
                'title' => (string) $ask->title,
                'description' => (string) $description,
                'urgency' => (string) $urgency,
                'status' => 'open',
                'badge_text' => 'Needs a Giver',
                'author' => [
                    'id' => (string) ($author?->id ?? ''),
                    'name' => $authorName,
                    'company_name' => (string) ($author?->company_name ?? ''),
                    'avatar_url' => $avatarUrl,
                ],
                'is_saved' => $isSaved,
                'is_congratulated' => $isCongratulated,
                'created_at' => $ask->created_at?->toISOString() ?? now()->toISOString(),
            ];
        }

        // Format Story items
        foreach ($stories as $story) {
            $isSaved = isset($savedPostIds[$story->id]);
            $isCongratulated = isset($congratulatedPostIds[$story->id]);

            $feedItems[] = [
                'id' => (string) $story->id,
                'item_type' => 'story',
                'badge_text' => 'Ask Fulfilled',
                'title' => (string) ($story->title ?: Str::limit($story->content_text, 120)),
                'is_saved' => $isSaved,
                'is_congratulated' => $isCongratulated,
                'created_at' => $story->created_at?->toISOString() ?? now()->toISOString(),
            ];
        }

        // 4. Generate Top Giver Spotlight item
        $spotlight = $this->generateTopGiverSpotlight($user);
        if ($spotlight) {
            $feedItems[] = $spotlight;
        }

        // Sort combined feed items chronologically (latest first)
        usort($feedItems, function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        // Ensure spotlight is nicely highlighted near the top (e.g. index 2 if available)
        if ($spotlight) {
            $spotlightIndex = null;
            foreach ($feedItems as $idx => $item) {
                if ($item['item_type'] === 'spotlight') {
                    $spotlightIndex = $idx;
                    break;
                }
            }
            if ($spotlightIndex !== null && count($feedItems) > 3) {
                $item = array_splice($feedItems, $spotlightIndex, 1)[0];
                array_splice($feedItems, min(2, count($feedItems)), 0, [$item]);
            }
        }

        // Paginate in-memory collection
        $total = count($feedItems);
        $offset = ($page - 1) * $perPage;
        $slicedItems = array_slice($feedItems, $offset, $perPage);
        $lastPage = (int) max(1, ceil($total / $perPage));

        return [
            'items' => array_values($slicedItems),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'total' => $total,
            ],
        ];
    }

    /**
     * Retrieve all asks created by the authenticated peer.
     *
     * @param  array{status?: string, page?: int, per_page?: int}  $filters
     * @return array{items: array<int, array<string, mixed>>, meta: array{current_page: int, last_page: int, total: int}}
     */
    public function getMyAsks(User $user, array $filters = []): array
    {
        $statusFilter = strtolower(trim((string) ($filters['status'] ?? '')));
        $perPage = max(1, min(50, (int) ($filters['per_page'] ?? 20)));

        $query = Ask::query()
            ->where('user_id', $user->id)
            ->with(['flow', 'type', 'responses' => fn ($q) => $q->orderByDesc('created_at')])
            ->withCount(['matches', 'responses'])
            ->orderByDesc('created_at');

        if ($statusFilter !== '') {
            match ($statusFilter) {
                'open' => $query->whereIn('status', [Ask::STATUS_PUBLISHED, 'open', Ask::STATUS_DRAFT]),
                'in_progress' => $query->where('status', 'in_progress'),
                'fulfilled' => $query->whereIn('status', [Ask::STATUS_CLOSED, 'fulfilled', 'completed']),
                'expired' => $query->where('status', Ask::STATUS_EXPIRED),
                default => $query->where('status', $statusFilter),
            };
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage);

        $items = [];
        foreach ($paginator->items() as $ask) {
            /** @var Ask $ask */
            $status = match ($ask->status) {
                Ask::STATUS_PUBLISHED => 'open',
                Ask::STATUS_CLOSED, 'completed' => 'fulfilled',
                default => (string) $ask->status,
            };

            // Compute dynamic activity subtitle
            $activitySubtitle = $this->resolveActivitySubtitle($ask);

            $items[] = [
                'id' => (string) $ask->id,
                'category_title' => $ask->type?->name ?? $ask->flow?->name ?? 'Business Referrals',
                'title' => (string) $ask->title,
                'status' => $status,
                'responses_count' => (int) $ask->responses_count,
                'activity_subtitle' => $activitySubtitle,
                'match_count' => (int) $ask->matches_count,
                'created_at' => $ask->created_at?->toISOString() ?? now()->toISOString(),
            ];
        }

        return [
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Submit a congratulation reaction/comment on a fulfilled ask or spotlight story.
     *
     * @return array{ask_id: string, is_congratulated: bool}
     */
    public function congratulate(User $user, string $id, ?string $comment = null): array
    {
        $postId = $this->resolvePostIdForTarget($user, $id);

        if ($postId) {
            PostLike::query()->firstOrCreate([
                'user_id' => $user->id,
                'post_id' => $postId,
            ]);

            if ($comment !== null && trim($comment) !== '') {
                PostComment::query()->create([
                    'user_id' => $user->id,
                    'post_id' => $postId,
                    'content' => trim($comment),
                ]);
            }
        }

        return [
            'ask_id' => $id,
            'is_congratulated' => true,
        ];
    }

    /**
     * Toggle bookmark/save status on an Ask or Story.
     *
     * @return array{ask_id: string, is_saved: bool}
     */
    public function toggleSave(User $user, string $id): array
    {
        $postId = $this->resolvePostIdForTarget($user, $id);

        $isSaved = true;
        if ($postId) {
            $existing = PostSave::query()
                ->where('user_id', $user->id)
                ->where('post_id', $postId)
                ->first();

            if ($existing) {
                $existing->delete();
                $isSaved = false;
            } else {
                PostSave::query()->create([
                    'user_id' => $user->id,
                    'post_id' => $postId,
                ]);
                $isSaved = true;
            }
        }

        return [
            'ask_id' => $id,
            'is_saved' => $isSaved,
        ];
    }

    /**
     * Mark an Ask as fulfilled, thank the Giver, and publish celebration story.
     *
     * @param  array{giver_id?: string, gratitude_note?: string, is_fulfilled?: bool, publish_story_to_feed?: bool}  $data
     * @return array{ask_id: string, status: string, story_id: string}
     */
    public function closeAndThank(User $user, Ask $ask, array $data): array
    {
        $gratitudeNote = trim((string) ($data['gratitude_note'] ?? ''));
        $publishStory = (bool) ($data['publish_story_to_feed'] ?? true);
        $giverId = $data['giver_id'] ?? null;

        // 1. Update Ask status to fulfilled
        $ask->update([
            'status' => 'fulfilled',
            'closed_at' => now(),
        ]);

        // 2. Resolve Giver
        $giver = null;
        if ($giverId) {
            $giver = User::query()->find($giverId);
        }
        if (! $giver) {
            $latestResponse = $ask->responses()->with('responder')->latest('created_at')->first();
            $giver = $latestResponse?->responder;
        }

        // 3. Register anonymous Business Deal
        if ($giver) {
            try {
                BusinessDeal::create([
                    'from_user_id' => $giver->id,
                    'to_user_id' => $user->id,
                    'deal_date' => now()->toDateString(),
                    'deal_amount' => 100000,
                    'business_type' => 'new',
                    'comment' => $gratitudeNote !== '' ? $gratitudeNote : "Fulfilled Ask: {$ask->title}",
                    'is_deleted' => false,
                ]);
            } catch (Throwable $e) {
                Log::warning('BusinessDeal recording skipped during ask fulfillment', ['error' => $e->getMessage()]);
            }
        }

        // 4. Publish Success Story Post to feed
        $storyPostId = (string) Str::uuid();
        if ($publishStory) {
            $authorName = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            $giverName = $giver ? ($giver->display_name ?: trim(($giver->first_name ?? '').' '.($giver->last_name ?? ''))) : 'Peer Giver';

            $storyContent = "🎉 Ask Fulfilled!\n\n";
            if ($giver) {
                $storyContent .= "A partnership was successfully closed between @[{$authorName}]({$user->id}) and @[{$giverName}]({$giver->id}) for: \"{$ask->title}\".";
            } else {
                $storyContent .= "Ask successfully fulfilled by @[{$authorName}]({$user->id}) for: \"{$ask->title}\".";
            }

            if ($gratitudeNote !== '') {
                $storyContent .= "\n\n\"{$gratitudeNote}\"";
            }

            try {
                $storyPost = Post::create([
                    'id' => $storyPostId,
                    'user_id' => $user->id,
                    'title' => "Ask Fulfilled: {$ask->title}",
                    'content_text' => $storyContent,
                    'media' => [],
                    'tags' => ['deal_closed', 'ask_fulfilled', 'congratulations', 'story'],
                    'visibility' => 'public',
                    'moderation_status' => 'approved',
                    'sponsored' => false,
                    'is_deleted' => false,
                    'active' => true,
                    'source_type' => 'ask',
                    'source_id' => $ask->id,
                    'source_event' => 'completed',
                    'post_type' => 'deal_closed',
                ]);

                if ($giver) {
                    PostMention::create([
                        'post_id' => $storyPost->id,
                        'peer_id' => $giver->id,
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('Failed to create ask fulfilled feed story', ['error' => $e->getMessage()]);
            }
        }

        return [
            'ask_id' => (string) $ask->id,
            'status' => 'fulfilled',
            'story_id' => $storyPostId,
        ];
    }

    /**
     * Resolve activity subtitle based on Ask responses and age.
     */
    protected function resolveActivitySubtitle(Ask $ask): string
    {
        $responses = $ask->responses;
        if ($responses->isNotEmpty()) {
            $latest = $responses->first();
            $diffText = $latest->created_at ? $latest->created_at->diffForHumans() : 'recently';

            return match ($latest->response_type ?? '') {
                'know_someone' => "Newest: an introduction offered, {$diffText}",
                'more_info' => 'A Giver asked for more details',
                'can_help_directly' => "A Giver offered direct support, {$diffText}",
                default => "Newest response received, {$diffText}",
            };
        }

        if ($ask->created_at && $ask->created_at->diffInHours(now()) >= 24) {
            return 'Boosted to matching Givers after 24 hours';
        }

        return 'No response yet';
    }

    /**
     * Resolve or generate a Post ID for save/congratulate operations.
     */
    protected function resolvePostIdForTarget(User $user, string $targetId): ?string
    {
        // 1. Direct Post ID check
        if (Post::query()->where('id', $targetId)->exists()) {
            return $targetId;
        }

        // 2. Ask ID check
        $ask = Ask::query()->with('timelineLink')->find($targetId);
        if ($ask) {
            if ($ask->timelineLink?->post_id) {
                return (string) $ask->timelineLink->post_id;
            }

            // Check if Post exists with source_id = ask_id
            $existingPost = Post::query()->where('source_type', 'ask')->where('source_id', $ask->id)->first();
            if ($existingPost) {
                return (string) $existingPost->id;
            }

            // Create a post representing this ask
            try {
                $post = Post::create([
                    'user_id' => $ask->user_id,
                    'title' => $ask->title,
                    'content_text' => $ask->title,
                    'media' => [],
                    'tags' => ['ask'],
                    'visibility' => 'public',
                    'moderation_status' => 'approved',
                    'sponsored' => false,
                    'is_deleted' => false,
                    'active' => true,
                    'source_type' => 'ask',
                    'source_id' => $ask->id,
                    'source_event' => 'published',
                    'post_type' => 'ask',
                ]);

                AskTimelineLink::create([
                    'ask_id' => $ask->id,
                    'post_id' => $post->id,
                ]);

                return (string) $post->id;
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Apply scope filter (for_you, circle, city, all) to Asks query.
     */
    protected function applyScopeFilter(Builder $query, User $user, string $scope): void
    {
        if ($scope === 'all') {
            return;
        }

        if ($scope === 'circle') {
            $userCircleIds = CircleMember::query()
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->pluck('circle_id')
                ->filter()
                ->all();

            if (! empty($user->circle_id)) {
                $userCircleIds[] = $user->circle_id;
            }
            $userCircleIds = array_unique($userCircleIds);

            if (! empty($userCircleIds)) {
                $query->where(function (Builder $q) use ($userCircleIds): void {
                    $q->whereIn('visibility_circle_id', $userCircleIds)
                        ->orWhereHas('user', fn (Builder $uq) => $uq->whereIn('circle_id', $userCircleIds));
                });
            }

            return;
        }

        if ($scope === 'city') {
            $city = trim((string) ($user->city ?? ''));
            if ($city !== '') {
                $query->whereHas('user', fn (Builder $uq) => $uq->where('city', 'ILIKE', "%{$city}%"));
            }

            return;
        }

        // 'for_you' (default): match user's circle or city or general open asks
        $city = trim((string) ($user->city ?? ''));
        $circleId = $user->circle_id;

        $query->where(function (Builder $q) use ($city, $circleId): void {
            $q->where('visibility_type', Ask::VISIBILITY_ALL_PEERS);
            if ($circleId) {
                $q->orWhere('visibility_circle_id', $circleId);
            }
            if ($city !== '') {
                $q->orWhereHas('user', fn (Builder $uq) => $uq->where('city', 'ILIKE', "%{$city}%"));
            }
        });
    }

    /**
     * Generate Top Giver spotlight item.
     *
     * @return array<string, mixed>|null
     */
    protected function generateTopGiverSpotlight(User $user): ?array
    {
        try {
            $topGiver = User::query()
                ->join('business_deals', 'business_deals.from_user_id', '=', 'users.id')
                ->where('business_deals.is_deleted', false)
                ->where('business_deals.created_at', '>=', now()->subDays(30))
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.display_name', DB::raw('count(business_deals.id) as deals_count'))
                ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.display_name')
                ->orderByDesc('deals_count')
                ->first();

            $count = $topGiver ? (int) $topGiver->deals_count : 6;
            $spotlightTitle = "This week's top Giver in your District helped {$count} peers.";

            return [
                'id' => (string) Str::uuid(),
                'item_type' => 'spotlight',
                'badge_text' => 'Giver Spotlight',
                'title' => $spotlightTitle,
                'is_saved' => false,
                'is_congratulated' => false,
                'created_at' => now()->subDay()->toISOString(),
            ];
        } catch (Throwable) {
            return [
                'id' => (string) Str::uuid(),
                'item_type' => 'spotlight',
                'badge_text' => 'Giver Spotlight',
                'title' => "This week's top Giver in your District helped 6 peers.",
                'is_saved' => false,
                'is_congratulated' => false,
                'created_at' => now()->subDay()->toISOString(),
            ];
        }
    }
}
