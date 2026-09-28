<?php

declare(strict_types=1);

namespace App\Services\Ask;

use App\Models\Ask\Ask;
use App\Models\Ask\AskAnswer;
use App\Models\Ask\AskFlow;
use App\Models\Ask\AskOptionGroup;
use App\Models\Ask\AskStatusHistory;
use App\Models\Ask\AskTimelineLink;
use App\Models\Ask\AskType;
use App\Models\BusinessDeal;
use App\Models\Post;
use App\Models\Referral;
use App\Models\User;
use App\Services\LifeImpact\LifeImpactService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AskService
{
    public function __construct(
        protected AskMatchingService $matchingService,
        protected AskNotificationService $notificationService
    ) {}

    /**
     * Create an initial Ask in draft status.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDraft(User $user, array $data): Ask
    {
        $flow = AskFlow::query()
            ->when(! empty($data['flow_id']) && Str::isUuid((string) $data['flow_id']), fn (Builder $q) => $q->where('id', $data['flow_id']))
            ->when(! empty($data['flow']), fn (Builder $q) => $q->orWhere('code', $data['flow']))
            ->firstOrFail();

        $type = AskType::query()
            ->where('flow_id', $flow->id)
            ->where(function (Builder $q) use ($data): void {
                if (! empty($data['type_id']) && Str::isUuid((string) $data['type_id'])) {
                    $q->where('id', $data['type_id']);
                }
                if (! empty($data['type'])) {
                    $q->orWhere('code', $data['type']);
                }
            })
            ->firstOrFail();

        $title = ! empty($data['title']) ? (string) $data['title'] : $type->name;

        return DB::transaction(function () use ($user, $flow, $type, $title): Ask {
            $ask = Ask::create([
                'user_id' => $user->id,
                'flow_id' => $flow->id,
                'type_id' => $type->id,
                'title' => $title,
                'status' => Ask::STATUS_DRAFT,
                'visibility_type' => Ask::VISIBILITY_ALL_PEERS,
                'publish_to_timeline' => true,
            ]);

            AskStatusHistory::create([
                'ask_id' => $ask->id,
                'changed_by_user_id' => $user->id,
                'old_status' => null,
                'new_status' => Ask::STATUS_DRAFT,
                'reason' => 'Draft created',
            ]);

            return $ask;
        });
    }

    /**
     * Save/replace dynamic answers for an Ask.
     *
     * @param  array<int, array<string, mixed>>  $answers
     */
    public function saveDetails(Ask $ask, array $answers): Ask
    {
        DB::transaction(function () use ($ask, $answers): void {
            // Delete existing answers with these field keys to allow updating
            $keys = array_filter(array_column($answers, 'field_key'));
            if (! empty($keys)) {
                AskAnswer::query()->where('ask_id', $ask->id)->whereIn('field_key', $keys)->delete();
            }

            $order = 0;
            foreach ($answers as $item) {
                $fieldKey = (string) ($item['field_key'] ?? '');
                if ($fieldKey === '') {
                    continue;
                }

                $optionGroupId = ! empty($item['option_group_id']) ? (string) $item['option_group_id'] : null;
                if (! $optionGroupId) {
                    $group = AskOptionGroup::query()->where('code', $fieldKey)->first();
                    $optionGroupId = $group?->id;
                }

                $resolveOptionId = function ($idOrCode, $groupId): ?string {
                    if (empty($idOrCode)) {
                        return null;
                    }
                    $strVal = (string) $idOrCode;
                    if (Str::isUuid($strVal)) {
                        return $strVal;
                    }

                    $opt = AskOption::query()
                        ->when($groupId, fn (Builder $q) => $q->where('option_group_id', $groupId))
                        ->where('code', $strVal)
                        ->first();

                    if (! $opt) {
                        $opt = AskOption::query()->where('code', $strVal)->first();
                    }

                    return $opt ? (string) $opt->id : null;
                };

                // If multiple option_ids provided
                if (! empty($item['option_ids']) && is_array($item['option_ids'])) {
                    foreach ($item['option_ids'] as $optVal) {
                        $resolvedOptId = $resolveOptionId($optVal, $optionGroupId);
                        if ($resolvedOptId) {
                            AskAnswer::create([
                                'ask_id' => $ask->id,
                                'option_group_id' => $optionGroupId,
                                'option_id' => $resolvedOptId,
                                'field_key' => $fieldKey,
                                'sort_order' => $order++,
                            ]);
                        }
                    }

                    continue;
                }

                // Single answer or option
                $singleOptId = ! empty($item['option_id']) ? $resolveOptionId($item['option_id'], $optionGroupId) : null;

                AskAnswer::create([
                    'ask_id' => $ask->id,
                    'option_group_id' => $optionGroupId,
                    'option_id' => $singleOptId,
                    'field_key' => $fieldKey,
                    'value_text' => isset($item['value_text']) ? (string) $item['value_text'] : null,
                    'value_number' => isset($item['value_number']) ? (float) $item['value_number'] : null,
                    'value_boolean' => isset($item['value_boolean']) ? (bool) $item['value_boolean'] : null,
                    'value_json' => isset($item['value_json']) && is_array($item['value_json']) ? $item['value_json'] : null,
                    'sort_order' => $order++,
                ]);

                // Auto-sync title if goal is provided and title was default/empty
                if ($fieldKey === 'goal' && ! empty($item['value_text']) && strlen((string) $item['value_text']) <= 255 && (empty($ask->title) || $ask->title === $ask->type?->name)) {
                    $ask->update(['title' => $item['value_text']]);
                }
            }
        });

        return $ask->fresh(['answers.option', 'flow', 'type']);
    }

    /**
     * Save Ask filters (industry, geography, business_stage, timeline, expected_outcome).
     *
     * @param  array<string, mixed>  $filters
     */
    public function saveFilters(Ask $ask, array $filters): Ask
    {
        $answersPayload = [];

        foreach ($filters as $key => $val) {
            if ($val === null || $val === '') {
                continue;
            }

            if (is_array($val)) {
                $answersPayload[] = [
                    'field_key' => $key,
                    'option_ids' => $val,
                ];
            } elseif (is_string($val)) {
                $answersPayload[] = [
                    'field_key' => $key,
                    'value_text' => $val,
                ];
            }
        }

        if (! empty($answersPayload)) {
            $this->saveDetails($ask, $answersPayload);
        }

        return $ask->fresh(['answers.option', 'flow', 'type']);
    }

    /**
     * Set Ask visibility.
     */
    public function setVisibility(Ask $ask, string $visibilityType, ?string $districtId, ?string $circleId): Ask
    {
        $ask->update([
            'visibility_type' => $visibilityType,
            'visibility_district_id' => $visibilityType === Ask::VISIBILITY_DISTRICT ? $districtId : null,
            'visibility_circle_id' => $visibilityType === Ask::VISIBILITY_CIRCLE ? $circleId : null,
        ]);

        return $ask;
    }

    /**
     * Set timeline preference.
     */
    public function setTimelinePreference(Ask $ask, bool $publishToTimeline): Ask
    {
        $ask->update([
            'publish_to_timeline' => $publishToTimeline,
        ]);

        return $ask;
    }

    /**
     * Load full Ask model for preview.
     */
    public function preview(Ask $ask): Ask
    {
        return $ask->loadMissing([
            'flow',
            'type',
            'answers.option',
            'answers.optionGroup',
            'district',
            'circle',
            'user',
        ]);
    }

    /**
     * Publish an Ask and trigger matching, timeline, and notification events.
     *
     * @param  array<string, mixed>  $options
     */
    public function publish(Ask $ask, User $user, array $options = []): Ask
    {
        // Handle timeline preference (default to true unless explicitly false)
        if (isset($options['post_to_timeline']) || isset($options['publish_to_timeline'])) {
            $ask->publish_to_timeline = (bool) ($options['post_to_timeline'] ?? $options['publish_to_timeline']);
        } elseif ($ask->publish_to_timeline === null) {
            $ask->publish_to_timeline = true;
        }

        if ($ask->status === Ask::STATUS_PUBLISHED) {
            if ($ask->publish_to_timeline) {
                $this->ensureTimelinePost($ask, $user, $options);
            }

            return $ask;
        }

        // Validate basic completeness
        if ($ask->answers()->count() === 0) {
            throw ValidationException::withMessages([
                'ask' => ['Please provide details for the Ask before publishing.'],
            ]);
        }

        return DB::transaction(function () use ($ask, $user, $options): Ask {
            $oldStatus = $ask->status;

            // Handle visibility parameter if provided (global, district, circle)
            $visibilityParam = $options['visibility'] ?? null;
            if ($visibilityParam !== null) {
                $mappedVisibility = match ($visibilityParam) {
                    'district' => Ask::VISIBILITY_DISTRICT,
                    'circle' => Ask::VISIBILITY_CIRCLE,
                    'global', 'all_peers' => Ask::VISIBILITY_ALL_PEERS,
                    default => $visibilityParam,
                };
                $ask->visibility_type = $mappedVisibility;
            }

            if (isset($options['post_to_timeline']) || isset($options['publish_to_timeline'])) {
                $ask->publish_to_timeline = (bool) ($options['post_to_timeline'] ?? $options['publish_to_timeline']);
            } elseif ($ask->publish_to_timeline === null) {
                $ask->publish_to_timeline = true;
            }

            $ask->status = Ask::STATUS_PUBLISHED;
            $ask->published_at = now();
            $ask->save();

            AskStatusHistory::create([
                'ask_id' => $ask->id,
                'changed_by_user_id' => $user->id,
                'old_status' => $oldStatus,
                'new_status' => Ask::STATUS_PUBLISHED,
                'reason' => 'Published by user',
            ]);

            // Handle Timeline post creation if preference enabled
            if ($ask->publish_to_timeline && Schema::hasTable('posts')) {
                $this->ensureTimelinePost($ask, $user, $options);
            }

            // Generate Matches
            $matches = $this->matchingService->generateMatches($ask);

            // Trigger Notifications to top matches or peers in circle/district
            $matchedUserIds = $matches->pluck('matched_user_id')->filter()->unique();
            if ($matchedUserIds->isNotEmpty()) {
                $matchedPeers = User::query()->whereIn('id', $matchedUserIds->take(20))->get();
                $this->notificationService->notifyAskPublished($ask, $matchedPeers);
            } elseif ($ask->visibility_type === Ask::VISIBILITY_CIRCLE && $ask->visibility_circle_id) {
                $circleUserIds = CircleMember::query()
                    ->where('circle_id', $ask->visibility_circle_id)
                    ->where('user_id', '!=', $user->id)
                    ->whereNull('deleted_at')
                    ->pluck('user_id');
                $peers = User::query()->whereIn('id', $circleUserIds->take(20))->get();
                if ($peers->isNotEmpty()) {
                    $this->notificationService->notifyAskPublished($ask, $peers);
                }
            } elseif ($ask->visibility_type === Ask::VISIBILITY_DISTRICT && $ask->visibility_district_id) {
                $districtPeers = User::query()
                    ->where('district_id', $ask->visibility_district_id)
                    ->where('id', '!=', $user->id)
                    ->take(20)
                    ->get();
                if ($districtPeers->isNotEmpty()) {
                    $this->notificationService->notifyAskPublished($ask, $districtPeers);
                }
            }

            return $ask->fresh(['flow', 'type', 'answers.option', 'matches', 'timelineLink']);
        });
    }

    /**
     * List user's asks with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listUserAsks(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        $query = Ask::query()
            ->where('user_id', $user->id)
            ->when(! empty($filters['flow']), function (Builder $q) use ($filters) {
                $q->whereHas('flow', function (Builder $fq) use ($filters): void {
                    $fq->where('code', $filters['flow']);
                    if (Str::isUuid((string) $filters['flow'])) {
                        $fq->orWhere('id', $filters['flow']);
                    }
                });
            })
            ->when(! empty($filters['type']), function (Builder $q) use ($filters) {
                $q->whereHas('type', function (Builder $tq) use ($filters): void {
                    $tq->where('code', $filters['type']);
                    if (Str::isUuid((string) $filters['type'])) {
                        $tq->orWhere('id', $filters['type']);
                    }
                });
            })
            ->when(! empty($filters['date']), fn (Builder $q) => $q->whereDate('created_at', $filters['date']))
            ->with(['flow', 'type', 'answers.option'])
            ->withCount(['matches', 'responses'])
            ->orderByDesc('created_at');

        $statusFilter = strtolower(trim((string) ($filters['status'] ?? 'all')));
        if ($statusFilter !== '' && $statusFilter !== 'all') {
            match ($statusFilter) {
                'open' => $query->whereIn('status', [Ask::STATUS_PUBLISHED, 'open', 'active', Ask::STATUS_DRAFT])
                    ->whereNotIn('status', ['fulfilled', Ask::STATUS_CLOSED, 'completed']),
                'in_progress' => $query->whereIn('status', ['in_progress', 'review', 'pending']),
                'fulfilled' => $query->where('status', 'fulfilled'),
                'expired', 'closed' => $query->whereIn('status', [Ask::STATUS_EXPIRED, Ask::STATUS_CLOSED, 'archived']),
                default => $query->where('status', $statusFilter),
            };
        }

        return $query->paginate($perPage);
    }

    /**
     * Get complete Ask details.
     */
    public function getAskDetails(Ask $ask): Ask
    {
        return $ask->loadMissing([
            'flow',
            'type',
            'answers.option',
            'answers.optionGroup',
            'district',
            'circle',
            'user',
            'timelineLink',
        ])->loadCount(['matches', 'responses']);
    }

    /**
     * Update an existing draft/published Ask.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAsk(Ask $ask, array $data): Ask
    {
        if (! empty($data['title'])) {
            $ask->update(['title' => $data['title']]);
        }

        if (! empty($data['answers']) && is_array($data['answers'])) {
            $this->saveDetails($ask, $data['answers']);
        }

        return $this->getAskDetails($ask);
    }

    /**
     * Update status (e.g. fulfill, close, cancel) of an Ask.
     *
     * @param  array<string, mixed>  $options
     */
    public function updateStatus(
        Ask $ask,
        User $user,
        string $status,
        ?string $reason = null,
        array $options = []
    ): Ask {
        $oldStatus = $ask->status;
        $updates = ['status' => $status];

        $outcomeStatus = $options['outcome_status'] ?? null;
        $approxValue = $options['approx_value'] ?? $options['approx_deal_value'] ?? null;
        $note = $options['note'] ?? $reason ?? null;
        $shareStory = (bool) ($options['share_story'] ?? false);
        $anonymousTotal = (bool) ($options['anonymous_total'] ?? false);

        if ($status === Ask::STATUS_FULFILLED || (! empty($outcomeStatus) && in_array($outcomeStatus, ['deal_closed', 'formalised', 'yes_fully'], true))) {
            $updates['fulfilled_at'] = now();
            $updates['closed_at'] = now();
        } elseif ($status === Ask::STATUS_CLOSED || $status === Ask::STATUS_CANCELLED) {
            $updates['closed_at'] = now();
        }

        if ($outcomeStatus !== null) {
            $updates['outcome_status'] = (string) $outcomeStatus;
        }

        if ($approxValue !== null) {
            $updates['approx_deal_value'] = (string) $approxValue;
        }

        if ($note !== null) {
            $updates['outcome_notes'] = (string) $note;
        }

        $metadata = (array) ($ask->metadata ?? []);
        if ($outcomeStatus !== null) {
            $metadata['outcome_status'] = $outcomeStatus;
        }
        if ($approxValue !== null) {
            $metadata['approx_value'] = $approxValue;
            $metadata['approx_deal_value'] = $approxValue;
        }
        if ($note !== null) {
            $metadata['outcome_notes'] = $note;
            $metadata['note'] = $note;
        }
        if (isset($options['anonymous_total'])) {
            $metadata['anonymous_total'] = $anonymousTotal;
        }
        if (isset($options['share_story'])) {
            $metadata['share_story'] = $shareStory;
        }
        if ($status === Ask::STATUS_FULFILLED) {
            $metadata['fulfilled_at'] = now()->toISOString();
        }
        $updates['metadata'] = $metadata;

        $ask->update($updates);

        AskStatusHistory::create([
            'ask_id' => $ask->id,
            'changed_by_user_id' => $user->id,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'reason' => $reason ?? $note,
        ]);

        // 2. Timeline Story Generation (if share_story == true)
        if ($shareStory) {
            $this->generateCelebrationStory($ask, $user, $note);
        }

        // 3. Business Value Aggregation (if approx_value is present)
        if ($approxValue !== null && $approxValue !== '') {
            $this->aggregateBusinessValue($ask, $user, (string) $approxValue, $outcomeStatus, $note, $anonymousTotal);
        }

        return $ask->fresh(['flow', 'type', 'answers.option', 'matches', 'timelineLink']);
    }

    /**
     * Generate celebration timeline story for a fulfilled ask.
     */
    protected function generateCelebrationStory(Ask $ask, User $user, ?string $note): void
    {
        try {
            $content = 'Successfully fulfilled: '.$ask->title;
            if ($note !== null && trim($note) !== '') {
                $content .= "\n\n".trim($note);
            }

            $storyPost = Post::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'title' => (string) $ask->title,
                'content_text' => $content,
                'media' => [],
                'tags' => ['ask_fulfilled', 'story', 'deal_closed'],
                'visibility' => 'public',
                'moderation_status' => 'approved',
                'sponsored' => false,
                'is_deleted' => false,
                'active' => true,
                'source_type' => 'ask',
                'source_id' => $ask->id,
                'source_event' => 'fulfilled',
                'post_type' => 'story',
            ]);

            AskTimelineLink::query()->updateOrCreate(
                ['ask_id' => $ask->id],
                ['post_id' => $storyPost->id]
            );
        } catch (Throwable $e) {
            Log::warning('Failed to generate celebration timeline story', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Aggregate business deal metrics and life impact for fulfilled ask.
     */
    protected function aggregateBusinessValue(
        Ask $ask,
        User $user,
        string $approxValue,
        ?string $outcomeStatus,
        ?string $note,
        bool $anonymousTotal
    ): void {
        $amountMap = [
            '<5_lakh' => 250000,
            'under_1_lakh' => 50000,
            '5_to_25_lakh' => 1500000,
            '1_to_10_lakh' => 500000,
            '25_lakh_to_1_cr' => 5000000,
            'above_10_lakh' => 1500000,
            '>1_cr' => 10000000,
        ];
        $dealAmount = $amountMap[$approxValue] ?? 500000;

        $latestResponse = $ask->responses()->with('responder')->latest('created_at')->first();
        $giver = $latestResponse?->responder;

        $fromUserId = $giver ? $giver->id : $user->id;
        $toUserId = $giver ? $user->id : $user->id;

        try {
            BusinessDeal::create([
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'deal_date' => now()->toDateString(),
                'deal_amount' => $dealAmount,
                'business_type' => 'new',
                'comment' => $note !== null && trim($note) !== '' ? trim($note) : "Fulfilled Ask: {$ask->title}",
                'is_deleted' => false,
            ]);
        } catch (Throwable $e) {
            Log::warning('BusinessDeal creation skipped during status update', ['error' => $e->getMessage()]);
        }

        try {
            app(LifeImpactService::class)->addLifeImpact(
                userId: (string) $user->id,
                triggeredByUserId: (string) $fromUserId,
                activityType: 'business_deal',
                activityId: (string) $ask->id,
                impactValue: 1,
                title: "Ask Fulfilled: {$ask->title}",
                description: $note !== null && trim($note) !== '' ? trim($note) : "Business value unlocked for Ask: {$ask->title}",
                meta: [
                    'ask_id' => (string) $ask->id,
                    'approx_value' => $approxValue,
                    'outcome_status' => $outcomeStatus,
                    'anonymous_total' => $anonymousTotal,
                ]
            );
        } catch (Throwable $e) {
            Log::warning('Life impact logging failed during ask fulfillment', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get status history of an Ask.
     *
     * @return Collection<int, AskStatusHistory>
     */
    public function getStatusHistory(Ask $ask): Collection
    {
        return AskStatusHistory::query()
            ->where('ask_id', $ask->id)
            ->with('changedBy')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Link an existing referral to this Ask.
     */
    public function linkReferral(Ask $ask, string $referralId): void
    {
        $referral = Referral::query()->findOrFail($referralId);

        if (Schema::hasColumn('referrals', 'source_ask_id')) {
            $referral->setAttribute('source_ask_id', $ask->id);
            $referral->save();
        }

        $this->notificationService->notifyReferralCreated($ask, $referral);
    }

    /**
     * Ensure a timeline post exists for the given Ask.
     *
     * @param  array<string, mixed>  $options
     */
    public function ensureTimelinePost(Ask $ask, ?User $user = null, array $options = []): ?Post
    {
        try {
            $user = $user ?? $ask->user ?? User::find($ask->user_id);
            if (! $user) {
                return null;
            }

            // Check if timeline post already exists via AskTimelineLink
            $existingLink = AskTimelineLink::query()->where('ask_id', $ask->id)->first();
            if ($existingLink && $existingLink->post_id) {
                $existingPost = Post::find($existingLink->post_id);
                if ($existingPost) {
                    return $existingPost;
                }
            }

            // Check if post exists with source_type = 'ask' and source_id = $ask->id
            $existingPost = Post::query()
                ->where('source_type', 'ask')
                ->where('source_id', $ask->id)
                ->first();

            if ($existingPost) {
                AskTimelineLink::query()->updateOrCreate(
                    ['ask_id' => $ask->id],
                    ['post_id' => $existingPost->id]
                );

                return $existingPost;
            }

            $ask->loadMissing(['flow', 'type']);
            $flowName = $ask->flow?->name ?? 'Ask';
            $typeName = $ask->type?->name ?? '';

            $contentText = ! empty($options['content_text'])
                ? (string) $options['content_text']
                : ($ask->title."\n\n".$flowName.($typeName !== '' ? ": {$typeName}" : ''));

            $tags = array_values(array_filter(['ask', strtolower((string) ($ask->flow?->code ?? ''))]));

            $post = Post::create([
                'user_id' => $user->id,
                'circle_id' => $ask->visibility_circle_id,
                'title' => $ask->title,
                'content_text' => $contentText,
                'media' => [],
                'tags' => $tags,
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

            AskTimelineLink::query()->updateOrCreate(
                ['ask_id' => $ask->id],
                ['post_id' => $post->id]
            );

            return $post;
        } catch (Throwable $e) {
            Log::warning('Failed to ensure timeline post for ask', [
                'ask_id' => (string) $ask->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
