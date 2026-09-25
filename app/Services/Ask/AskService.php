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
use App\Models\Post;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
     */
    public function publish(Ask $ask, User $user): Ask
    {
        if ($ask->status === Ask::STATUS_PUBLISHED) {
            return $ask;
        }

        // Validate basic completeness
        if ($ask->answers()->count() === 0) {
            throw ValidationException::withMessages([
                'ask' => ['Please provide details for the Ask before publishing.'],
            ]);
        }

        return DB::transaction(function () use ($ask, $user): Ask {
            $oldStatus = $ask->status;

            $ask->update([
                'status' => Ask::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);

            AskStatusHistory::create([
                'ask_id' => $ask->id,
                'changed_by_user_id' => $user->id,
                'old_status' => $oldStatus,
                'new_status' => Ask::STATUS_PUBLISHED,
                'reason' => 'Published by user',
            ]);

            // Handle Timeline post creation if preference enabled
            if ($ask->publish_to_timeline && Schema::hasTable('posts')) {
                $post = Post::create([
                    'user_id' => $user->id,
                    'title' => $ask->title,
                    'content_text' => $ask->title."\n\n".($ask->flow?->name ?? 'Ask').': '.($ask->type?->name ?? ''),
                    'visibility' => $ask->visibility_type === Ask::VISIBILITY_CIRCLE ? 'circle' : 'public',
                    'circle_id' => $ask->visibility_circle_id,
                    'source_type' => 'ask',
                    'source_id' => $ask->id,
                    'post_type' => 'ask',
                    'active' => true,
                    'is_deleted' => false,
                    'moderation_status' => 'approved',
                ]);

                AskTimelineLink::query()->updateOrCreate(
                    ['ask_id' => $ask->id],
                    ['post_id' => $post->id]
                );
            }

            // Generate Matches
            $matches = $this->matchingService->generateMatches($ask);

            // Trigger Notifications to top matches
            $matchedUserIds = $matches->pluck('matched_user_id')->filter()->unique();
            if ($matchedUserIds->isNotEmpty()) {
                $matchedPeers = User::query()->whereIn('id', $matchedUserIds->take(20))->get();
                $this->notificationService->notifyAskPublished($ask, $matchedPeers);
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

        return Ask::query()
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
            ->when(! empty($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['date']), fn (Builder $q) => $q->whereDate('created_at', $filters['date']))
            ->with(['flow', 'type', 'answers.option'])
            ->withCount(['matches', 'responses'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
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
     * Update status (e.g. close, cancel) of an Ask.
     */
    public function updateStatus(Ask $ask, User $user, string $status, ?string $reason): Ask
    {
        $oldStatus = $ask->status;

        $updates = ['status' => $status];
        if ($status === Ask::STATUS_CLOSED) {
            $updates['closed_at'] = now();
        }

        $ask->update($updates);

        AskStatusHistory::create([
            'ask_id' => $ask->id,
            'changed_by_user_id' => $user->id,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'reason' => $reason,
        ]);

        return $ask;
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
}
