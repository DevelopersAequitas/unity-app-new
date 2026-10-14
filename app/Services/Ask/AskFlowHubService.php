<?php

declare(strict_types=1);

namespace App\Services\Ask;

use App\Models\Ask\Ask;
use App\Models\Ask\AskResponse;
use App\Models\Ask\AskResponseStatusHistory;
use App\Models\Ask\AskType;
use App\Models\BusinessDeal;
use App\Models\CircleMember;
use App\Models\District;
use App\Models\PeerReferral;
use App\Models\PostSave;
use App\Models\Referral;
use App\Models\User;
use App\Services\Notifications\NotifyUserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AskFlowHubService
{
    public function __construct(
        protected NotifyUserService $notifyUserService,
        protected AskService $askService
    ) {}

    /**
     * Resolve standard user details for ask feeds and cards.
     *
     * @return array{id: string, display_name: string, avatar_url: ?string, company_name: string, city: string}
     */
    public function formatAuthor(?User $user): array
    {
        if (! $user) {
            return [
                'id' => '',
                'display_name' => 'Peer Member',
                'avatar_url' => null,
                'company_name' => '',
                'city' => '',
            ];
        }

        $displayName = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        if ($displayName === '') {
            $displayName = 'Peer Member';
        }

        $avatarUrl = $user->profile_photo_file_id
            ? url('/api/v1/files/'.$user->profile_photo_file_id)
            : ($user->profile_photo_url ?? null);

        $city = $user->city_of_residence ?: (is_string($user->city) ? $user->city : data_get($user, 'city.name', ''));

        return [
            'id' => (string) $user->id,
            'display_name' => $displayName,
            'avatar_url' => $avatarUrl,
            'company_name' => (string) ($user->company_name ?? ''),
            'city' => (string) $city,
        ];
    }

    /**
     * 1. Global Feed API for a specific Ask Flow (collaboration, referral, help)
     *
     * @param  array{page?: int, limit?: int, category_id?: string, search?: string}  $params
     * @return array{items: array<int, mixed>, pagination: array{current_page: int, has_more: bool, total: int}}
     */
    public function getGlobalFeed(User $user, string $flowCode, array $params = []): array
    {
        $normalizedFlow = $this->normalizeFlowCode($flowCode);
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 20)));
        $categoryId = filled($params['category_id'] ?? null) ? trim((string) $params['category_id']) : null;
        $search = filled($params['search'] ?? null) ? trim((string) $params['search']) : null;

        $query = Ask::query()
            ->whereHas('flow', function (Builder $fq) use ($normalizedFlow): void {
                $fq->where('code', $normalizedFlow);
            })
            ->whereIn('status', [Ask::STATUS_PUBLISHED, 'open', 'active'])
            ->whereNotIn('status', ['fulfilled', Ask::STATUS_CLOSED, 'completed', Ask::STATUS_CANCELLED, Ask::STATUS_EXPIRED, Ask::STATUS_DRAFT])
            ->with(['user', 'type', 'flow', 'answers', 'timelineLink'])
            ->withCount('responses')
            ->orderByDesc('created_at');

        $this->applyVisibilityScope($query, $user);

        if ($categoryId !== null) {
            $isUuid = Str::isUuid($categoryId);
            $query->where(function (Builder $cq) use ($categoryId, $isUuid): void {
                if ($isUuid) {
                    $cq->where('type_id', $categoryId)
                        ->orWhereHas('type', function (Builder $tq) use ($categoryId): void {
                            $tq->where('code', $categoryId)
                                ->orWhere('name', 'ILIKE', '%'.$categoryId.'%');
                        });
                } else {
                    $cq->whereHas('type', function (Builder $tq) use ($categoryId): void {
                        $tq->where('code', $categoryId)
                            ->orWhere('name', 'ILIKE', '%'.$categoryId.'%');
                    });
                }
            });
        }

        if ($search !== null) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function (Builder $sq) use ($like): void {
                $sq->where('title', 'ILIKE', $like)
                    ->orWhereHas('answers', function (Builder $aq) use ($like): void {
                        $aq->where('value_text', 'ILIKE', $like);
                    })
                    ->orWhereHas('user', function (Builder $uq) use ($like): void {
                        $uq->where('display_name', 'ILIKE', $like)
                            ->orWhere('first_name', 'ILIKE', $like)
                            ->orWhere('last_name', 'ILIKE', $like)
                            ->orWhere('company_name', 'ILIKE', $like);
                    });
            });
        }

        if ($normalizedFlow === 'referral') {
            // Also retrieve peer referrals from referrals table
            $refQuery = Referral::query()
                ->where(function (Builder $q): void {
                    $q->where('is_deleted', false)
                        ->orWhereNull('is_deleted');
                })
                ->whereNull('deleted_at')
                ->with(['fromUser', 'toUser', 'status'])
                ->orderByDesc('created_at');

            if ($search !== null) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $refQuery->where(function (Builder $sq) use ($like): void {
                    $sq->where('referral_of', 'ILIKE', $like)
                        ->orWhere('remarks', 'ILIKE', $like)
                        ->orWhere('referral_type', 'ILIKE', $like)
                        ->orWhereHas('fromUser', function (Builder $uq) use ($like): void {
                            $uq->where('display_name', 'ILIKE', $like)
                                ->orWhere('first_name', 'ILIKE', $like)
                                ->orWhere('last_name', 'ILIKE', $like)
                                ->orWhere('company_name', 'ILIKE', $like);
                        })
                        ->orWhereHas('toUser', function (Builder $uq) use ($like): void {
                            $uq->where('display_name', 'ILIKE', $like)
                                ->orWhere('first_name', 'ILIKE', $like)
                                ->orWhere('last_name', 'ILIKE', $like)
                                ->orWhere('company_name', 'ILIKE', $like);
                        });
                });
            }

            $referralRecords = $refQuery->get();
            $askRecords = $query->get();

            // Lookup bookmarks/saves
            $postIds = [];
            foreach ($askRecords as $ask) {
                if ($ask->timelineLink?->post_id) {
                    $postIds[] = $ask->timelineLink->post_id;
                }
            }
            $savedPostIds = [];
            if (! empty($postIds)) {
                $savedPostIds = PostSave::query()
                    ->where('user_id', $user->id)
                    ->whereIn('post_id', $postIds)
                    ->pluck('post_id')
                    ->flip()
                    ->all();
            }

            $combinedItems = [];

            foreach ($askRecords as $ask) {
                /** @var Ask $ask */
                $author = $this->formatAuthor($ask->user);
                $description = $ask->answers->firstWhere('field_key', 'details')?->value_text
                    ?? $ask->answers->firstWhere('field_key', 'description')?->value_text
                    ?? $ask->title;

                $offeringInReturn = $ask->answers->firstWhere('field_key', 'what_i_offer')?->value_text
                    ?? $ask->answers->firstWhere('field_key', 'offering_in_return')?->value_text
                    ?? ($ask->metadata['offering_in_return'] ?? null);

                $postId = $ask->timelineLink?->post_id;
                $isSaved = $postId ? isset($savedPostIds[$postId]) : false;

                $combinedItems[] = [
                    'id' => (string) $ask->id,
                    'flow_code' => 'referral',
                    'title' => (string) $ask->title,
                    'referral_of' => (string) $ask->title,
                    'description' => (string) $description,
                    'remarks' => (string) $description,
                    'offering_in_return' => $offeringInReturn ? (string) $offeringInReturn : 'Direct business referrals in reciprocal networks.',
                    'referral_type' => 'b2b_referral',
                    'status' => 'open',
                    'status_id' => 1,
                    'status_label' => 'Open',
                    'created_at' => $ask->created_at?->toISOString() ?? now()->toISOString(),
                    'author' => $author,
                    'from_user' => $author,
                    'responses_count' => (int) $ask->responses_count,
                    'is_saved' => $isSaved,
                ];
            }

            foreach ($referralRecords as $ref) {
                /** @var Referral $ref */
                $author = $this->formatAuthor($ref->fromUser);
                $recipient = $this->formatAuthor($ref->toUser);

                $statusName = $ref->status?->name ?? 'Open';

                $combinedItems[] = [
                    'id' => (string) $ref->id,
                    'flow_code' => 'referral',
                    'title' => (string) ($ref->referral_of ?: 'Business Referral'),
                    'referral_of' => (string) ($ref->referral_of ?: 'Business Referral'),
                    'description' => (string) ($ref->remarks ?: ('Referral for '.$ref->referral_of)),
                    'remarks' => (string) ($ref->remarks ?? ''),
                    'offering_in_return' => (string) ($ref->referral_type === 'b2b_referral' ? 'B2B Referral' : ($ref->referral_type ?: 'Direct business referral')),
                    'referral_type' => (string) ($ref->referral_type ?? 'b2b_referral'),
                    'status' => strtolower($statusName) === 'deal_closed' ? 'fulfilled' : 'open',
                    'status_id' => (int) ($ref->status_id ?? 1),
                    'status_label' => (string) $statusName,
                    'hot_value' => (int) ($ref->hot_value ?? 1),
                    'phone' => (string) ($ref->phone ?? ''),
                    'email' => (string) ($ref->email ?? ''),
                    'address' => (string) ($ref->address ?? ''),
                    'created_at' => $ref->created_at?->toISOString() ?? now()->toISOString(),
                    'author' => $author,
                    'from_user' => $author,
                    'to_user' => $recipient,
                    'recipient' => $recipient,
                    'responses_count' => $ref->to_user_id ? 1 : 0,
                    'is_saved' => false,
                ];
            }

            usort($combinedItems, fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

            $total = count($combinedItems);
            $pagedItems = array_slice($combinedItems, ($page - 1) * $limit, $limit);
            $hasMore = ($page * $limit) < $total;

            return [
                'items' => $pagedItems,
                'data' => $pagedItems,
                'pagination' => [
                    'current_page' => $page,
                    'has_more' => $hasMore,
                    'total' => $total,
                ],
            ];
        }

        $paginator = $query->paginate(perPage: $limit, page: $page);

        // Batch lookup bookmarks/saves for current user
        $postIds = [];
        foreach ($paginator->items() as $ask) {
            if ($ask->timelineLink?->post_id) {
                $postIds[] = $ask->timelineLink->post_id;
            }
        }
        $savedPostIds = [];
        if (! empty($postIds)) {
            $savedPostIds = PostSave::query()
                ->where('user_id', $user->id)
                ->whereIn('post_id', $postIds)
                ->pluck('post_id')
                ->flip()
                ->all();
        }

        $items = [];
        foreach ($paginator->items() as $ask) {
            /** @var Ask $ask */
            $author = $this->formatAuthor($ask->user);
            $description = $ask->answers->firstWhere('field_key', 'details')?->value_text
                ?? $ask->answers->firstWhere('field_key', 'description')?->value_text
                ?? $ask->title;

            $postId = $ask->timelineLink?->post_id;
            $isSaved = $postId ? isset($savedPostIds[$postId]) : false;

            if ($normalizedFlow === 'collaboration') {
                $items[] = [
                    'id' => (string) $ask->id,
                    'flow_code' => 'collaboration',
                    'title' => (string) $ask->title,
                    'description' => (string) $description,
                    'category' => (string) ($ask->type?->name ?? 'Distribution Partner'),
                    'status' => 'open',
                    'created_at' => $ask->created_at?->toISOString() ?? now()->toISOString(),
                    'author' => $author,
                    'responses_count' => (int) $ask->responses_count,
                    'is_saved' => $isSaved,
                ];
            } else {
                // help flow
                $items[] = [
                    'id' => (string) $ask->id,
                    'flow_code' => 'help',
                    'title' => (string) $ask->title,
                    'description' => (string) $description,
                    'status' => 'open',
                    'created_at' => $ask->created_at?->toISOString() ?? now()->toISOString(),
                    'author' => $author,
                    'responses_count' => (int) $ask->responses_count,
                ];
            }
        }

        return [
            'items' => $items,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'has_more' => $paginator->hasMorePages(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * 2. My Asks & History API for a specific Flow
     *
     * @param  array{page?: int, limit?: int}  $params
     * @return array<string, mixed>
     */
    public function getMyAsks(User $user, string $flowCode, array $params = []): array
    {
        $normalizedFlow = $this->normalizeFlowCode($flowCode);

        if ($normalizedFlow === 'referral') {
            return $this->getMyReferralAsks($user, $params);
        }

        if ($normalizedFlow === 'collaboration') {
            return $this->getMyCollaborationAsks($user, $params);
        }

        return $this->getMyHelpAsks($user, $params);
    }

    /**
     * Format comprehensive ask details for mobile, leader and admin flows.
     *
     * @return array<string, mixed>
     */
    public function formatFullAskDetails(
        Ask $ask,
        string $flowCode,
        ?array $acceptedPeer = null,
        ?array $fulfilledBy = null
    ): array {
        $answersGrouped = [];
        $answersList = [];
        $whatIBring = [];
        $whatINeed = [];
        $industryList = [];
        $geographyList = [];
        $businessStageList = [];
        $timelineList = [];
        $expectedOutcomeList = [];
        $goal = null;
        $description = null;

        if ($ask->relationLoaded('answers')) {
            foreach ($ask->answers as $answer) {
                /** @var AskAnswer $answer */
                $fieldKey = (string) $answer->field_key;
                $opt = $answer->option;
                $group = $answer->optionGroup;

                $optLabel = $opt?->label ?? $opt?->name ?? $answer->value_text;
                $optCode = $opt?->code;

                $answersList[] = [
                    'id' => (string) $answer->id,
                    'field_key' => $fieldKey,
                    'option_group_id' => $answer->option_group_id ? (string) $answer->option_group_id : null,
                    'group_code' => $group?->code ?? $fieldKey,
                    'group_name' => $group?->name ?? ucfirst(str_replace('_', ' ', $fieldKey)),
                    'option_id' => $answer->option_id ? (string) $answer->option_id : null,
                    'code' => $optCode,
                    'label' => $optLabel,
                    'value' => $optLabel ?? $answer->value_text ?? $answer->value_number ?? $answer->value_boolean ?? $answer->value_json,
                    'value_text' => $answer->value_text,
                    'value_number' => $answer->value_number !== null ? (float) $answer->value_number : null,
                    'value_boolean' => $answer->value_boolean !== null ? (bool) $answer->value_boolean : null,
                    'value_json' => $answer->value_json,
                    'sort_order' => (int) $answer->sort_order,
                ];

                if ($answer->option_id && $opt) {
                    if (! isset($answersGrouped[$fieldKey])) {
                        $answersGrouped[$fieldKey] = [];
                    }
                    $answersGrouped[$fieldKey][] = [
                        'option_id' => (string) $answer->option_id,
                        'code' => $optCode,
                        'label' => $optLabel,
                    ];
                } elseif ($answer->value_text !== null) {
                    $answersGrouped[$fieldKey] = $answer->value_text;
                } elseif ($answer->value_number !== null) {
                    $answersGrouped[$fieldKey] = (float) $answer->value_number;
                } elseif ($answer->value_boolean !== null) {
                    $answersGrouped[$fieldKey] = (bool) $answer->value_boolean;
                } elseif ($answer->value_json !== null) {
                    $answersGrouped[$fieldKey] = $answer->value_json;
                }

                $isBringKey = in_array($fieldKey, ['what_i_bring', 'collaboration_bring', 'bring'], true)
                    || (is_string($optCode) && (str_starts_with($optCode, 'cf_bring_') || str_starts_with($optCode, 'jv_bring_') || str_starts_with($optCode, 'sp_bring_')));

                if ($isBringKey) {
                    $whatIBring[] = [
                        'id' => (string) ($opt?->id ?? $answer->id),
                        'option_id' => (string) ($opt?->id ?? $answer->id),
                        'code' => (string) ($optCode ?? ''),
                        'label' => (string) ($optLabel ?? ''),
                    ];
                }

                $isNeedKey = in_array($fieldKey, ['what_i_need', 'collaboration_need', 'need'], true)
                    || (is_string($optCode) && (str_starts_with($optCode, 'cf_need_') || str_starts_with($optCode, 'jv_need_') || str_starts_with($optCode, 'sp_need_')));

                if ($isNeedKey) {
                    $whatINeed[] = [
                        'id' => (string) ($opt?->id ?? $answer->id),
                        'option_id' => (string) ($opt?->id ?? $answer->id),
                        'code' => (string) ($optCode ?? ''),
                        'label' => (string) ($optLabel ?? ''),
                    ];
                }

                if ($fieldKey === 'industry' && filled($optLabel ?? $answer->value_text)) {
                    $industryList[] = (string) ($optLabel ?? $answer->value_text);
                }
                if ($fieldKey === 'geography' && filled($optLabel ?? $answer->value_text)) {
                    $geographyList[] = (string) ($optLabel ?? $answer->value_text);
                }
                if ($fieldKey === 'business_stage' && filled($optLabel ?? $answer->value_text)) {
                    $businessStageList[] = (string) ($optLabel ?? $answer->value_text);
                }
                if ($fieldKey === 'timeline' && filled($optLabel ?? $answer->value_text)) {
                    $timelineList[] = (string) ($optLabel ?? $answer->value_text);
                }
                if (in_array($fieldKey, ['expected_outcome', 'expected_impact', 'impact'], true) && filled($optLabel ?? $answer->value_text)) {
                    $expectedOutcomeList[] = (string) ($optLabel ?? $answer->value_text);
                }
                if ($fieldKey === 'goal' && filled($answer->value_text ?: $optLabel)) {
                    $goal = (string) ($answer->value_text ?: $optLabel);
                }
                if (in_array($fieldKey, ['description', 'details'], true) && filled($answer->value_text)) {
                    $description = (string) $answer->value_text;
                }
            }
        }

        if (empty($description)) {
            $description = $goal ?: ($ask->title ?: '');
        }
        if (empty($goal)) {
            $goal = $description ?: $ask->title;
        }

        $formattedResponses = [];
        if ($ask->relationLoaded('responses')) {
            foreach ($ask->responses as $resp) {
                /** @var AskResponse $resp */
                $responder = $resp->responder;
                $introduced = $resp->introducedUser;
                $contact = $resp->contact;

                $responderName = $responder ? ($responder->display_name ?: trim(($responder->first_name ?? '').' '.($responder->last_name ?? ''))) : 'Peer Member';
                $introducedName = $introduced ? ($introduced->display_name ?: trim(($introduced->first_name ?? '').' '.($introduced->last_name ?? ''))) : ($contact?->full_name ?? null);

                $formattedResponses[] = [
                    'id' => (string) $resp->id,
                    'response_id' => (string) $resp->id,
                    'status' => (string) $resp->status,
                    'response_type' => (string) ($resp->response_type ?? 'response'),
                    'message' => (string) ($resp->message ?? ''),
                    'responded_at' => $resp->responded_at?->toISOString() ?? $resp->created_at?->toISOString(),
                    'responder' => $responder ? [
                        'id' => (string) $responder->id,
                        'display_name' => $responderName,
                        'company_name' => (string) ($responder->company_name ?? ''),
                        'designation' => (string) ($responder->designation ?? ''),
                        'avatar_url' => $responder->profile_photo_file_id
                            ? url('/api/v1/files/'.$responder->profile_photo_file_id)
                            : ($responder->profile_photo_url ?? null),
                    ] : null,
                    'introduced_peer' => $introduced ? [
                        'id' => (string) $introduced->id,
                        'display_name' => $introducedName,
                        'company_name' => (string) ($introduced->company_name ?? ''),
                        'designation' => (string) ($introduced->designation ?? ''),
                        'avatar_url' => $introduced->profile_photo_file_id
                            ? url('/api/v1/files/'.$introduced->profile_photo_file_id)
                            : ($introduced->profile_photo_url ?? null),
                    ] : ($contact ? [
                        'id' => (string) $contact->id,
                        'display_name' => (string) $contact->full_name,
                        'designation' => (string) ($contact->designation ?? ''),
                        'company_name' => (string) ($contact->company_name ?? ''),
                        'phone' => (string) ($contact->phone ?? ''),
                        'email' => (string) ($contact->email ?? ''),
                    ] : null),
                ];
            }
        }

        $responsesCount = (int) ($ask->responses_count ?? count($formattedResponses));
        $matchesCount = (int) ($ask->matches_count ?? ($ask->relationLoaded('matches') ? $ask->matches->count() : 0));

        $rawStatus = strtolower(trim((string) $ask->status));
        $status = match ($rawStatus) {
            Ask::STATUS_FULFILLED, 'fulfilled', 'completed' => 'fulfilled',
            Ask::STATUS_CLOSED, Ask::STATUS_CANCELLED, Ask::STATUS_EXPIRED, 'closed', 'cancelled', 'expired', 'archived' => 'closed',
            Ask::STATUS_IN_PROGRESS, 'in_progress', 'review', 'pending' => 'in_progress',
            default => ($acceptedPeer !== null || $fulfilledBy !== null ? 'in_progress' : 'open'),
        };

        $totalSteps = 4;
        $progressStep = match ($status) {
            'fulfilled', 'closed' => 4,
            'in_progress' => 3,
            default => ($responsesCount > 0 ? 2 : 1),
        };

        return [
            // Core Identification
            'id' => (string) $ask->id,
            'flow_code' => $flowCode,
            'flow' => $ask->flow ? [
                'id' => (string) $ask->flow->id,
                'code' => (string) $ask->flow->code,
                'name' => (string) $ask->flow->name,
                'description' => (string) ($ask->flow->description ?? ''),
            ] : [
                'id' => '',
                'code' => $flowCode,
                'name' => ucfirst($flowCode),
                'description' => '',
            ],
            'type' => $ask->type ? [
                'id' => (string) $ask->type->id,
                'code' => (string) $ask->type->code,
                'name' => (string) $ask->type->name,
                'description' => (string) ($ask->type->description ?? ''),
                'level' => (int) ($ask->type->level ?? 1),
            ] : null,
            'type_id' => $ask->type_id ? (string) $ask->type_id : null,
            'category' => (string) ($ask->type?->name ?? ucfirst($flowCode)),
            'category_name' => (string) ($ask->type?->name ?? ucfirst($flowCode)),
            'sub_type' => (string) ($ask->type?->code ?? $flowCode),
            'sub_type_name' => (string) ($ask->type?->name ?? ucfirst($flowCode)),
            'title' => (string) $ask->title,
            'description' => (string) $description,
            'goal' => $goal,

            // Status & Journey Progress
            'status' => $status,
            'raw_status' => (string) $ask->status,
            'progress_step' => $progressStep,
            'total_steps' => $totalSteps,
            'responses_count' => $responsesCount,
            'matches_count' => $matchesCount,
            'accepted_peer' => $acceptedPeer,
            'fulfilled_by' => $fulfilledBy ?? $acceptedPeer,

            // Flow Structured Dynamic Options
            'what_i_bring' => $whatIBring,
            'what_i_need' => $whatINeed,
            'collaboration_bring' => $whatIBring,
            'collaboration_need' => $whatINeed,
            'industry' => ! empty($industryList) ? implode(', ', $industryList) : null,
            'industry_list' => $industryList,
            'geography' => ! empty($geographyList) ? implode(', ', $geographyList) : null,
            'geography_list' => $geographyList,
            'business_stage' => ! empty($businessStageList) ? implode(', ', $businessStageList) : null,
            'business_stage_list' => $businessStageList,
            'timeline' => ! empty($timelineList) ? implode(', ', $timelineList) : null,
            'timeline_list' => $timelineList,
            'expected_outcome' => ! empty($expectedOutcomeList) ? implode(', ', $expectedOutcomeList) : null,
            'expected_outcome_list' => $expectedOutcomeList,
            'expected_impact' => ! empty($expectedOutcomeList) ? implode(', ', $expectedOutcomeList) : null,

            // Dynamic Answers
            'answers' => $answersList,
            'answers_by_key' => $answersGrouped,

            // Visibility
            'visibility' => [
                'visibility_type' => (string) ($ask->visibility_type ?? Ask::VISIBILITY_ALL_PEERS),
                'district_id' => $ask->visibility_district_id ? (string) $ask->visibility_district_id : null,
                'district' => $ask->district?->name,
                'circle_id' => $ask->visibility_circle_id ? (string) $ask->visibility_circle_id : null,
                'circle' => $ask->circle?->name,
            ],
            'visibility_type' => (string) ($ask->visibility_type ?? Ask::VISIBILITY_ALL_PEERS),
            'publish_to_timeline' => (bool) $ask->publish_to_timeline,
            'post_to_timeline' => (bool) $ask->publish_to_timeline,

            // Responses
            'responses' => $formattedResponses,

            // Deal Info & Outcomes
            'approx_deal_value' => $ask->approx_deal_value ?? ($ask->metadata['approx_value'] ?? null),
            'approx_value' => $ask->approx_deal_value ?? ($ask->metadata['approx_value'] ?? null),
            'outcome_status' => $ask->outcome_status ?? ($ask->metadata['outcome_status'] ?? null),
            'outcome_notes' => $ask->outcome_notes ?? ($ask->metadata['outcome_notes'] ?? null),

            // Author details
            'author' => $this->formatAuthor($ask->user),
            'user' => $this->formatAuthor($ask->user),

            // Timestamps
            'published_at' => $ask->published_at?->toISOString(),
            'expires_at' => $ask->expires_at?->toISOString(),
            'closed_at' => $ask->closed_at?->toISOString(),
            'fulfilled_at' => $ask->fulfilled_at?->toISOString() ?? ($ask->metadata['fulfilled_at'] ?? null),
            'created_at' => $ask->created_at?->toISOString() ?? now()->toISOString(),
            'updated_at' => $ask->updated_at?->toISOString() ?? now()->toISOString(),
        ];
    }

    /**
     * Get single ask full details for a flow.
     *
     * @return array<string, mixed>
     */
    public function getAskDetails(User $user, string $flowCode, string $id): array
    {
        $normalizedFlow = $this->normalizeFlowCode($flowCode);

        $ask = Ask::query()
            ->where('id', $id)
            ->with([
                'flow',
                'type',
                'district',
                'circle',
                'user.city',
                'answers.optionGroup',
                'answers.option',
                'responses.responder',
                'responses.introducedUser',
                'responses.contact',
            ])
            ->withCount(['responses', 'matches'])
            ->firstOrFail();

        $acceptedResponse = $ask->responses->firstWhere('status', AskResponse::STATUS_ACCEPTED)
            ?? $ask->responses->firstWhere('status', AskResponse::STATUS_IN_PROGRESS)
            ?? $ask->responses->firstWhere('status', AskResponse::STATUS_COMPLETED);

        $acceptedPeer = null;
        if ($acceptedResponse) {
            $peerUser = $acceptedResponse->introducedUser ?? $acceptedResponse->responder;
            if ($peerUser) {
                $acceptedPeer = [
                    'id' => (string) $peerUser->id,
                    'display_name' => $peerUser->display_name ?: trim(($peerUser->first_name ?? '').' '.($peerUser->last_name ?? '')),
                    'company_name' => (string) ($peerUser->company_name ?? ''),
                    'avatar_url' => $peerUser->profile_photo_file_id
                        ? url('/api/v1/files/'.$peerUser->profile_photo_file_id)
                        : ($peerUser->profile_photo_url ?? null),
                ];
            }
        }

        return $this->formatFullAskDetails($ask, $normalizedFlow, $acceptedPeer);
    }

    /**
     * Collaboration: My Collaborations & History
     *
     * @param  array{page?: int, limit?: int}  $params
     * @return array{items: array<int, mixed>, data: array<int, mixed>}
     */
    protected function getMyCollaborationAsks(User $user, array $params = []): array
    {
        $statusFilter = strtolower(trim((string) ($params['status'] ?? 'all')));
        $categoryId = filled($params['category_id'] ?? null) ? trim((string) $params['category_id']) : null;
        $search = filled($params['search'] ?? null) ? trim((string) $params['search']) : null;
        $districtId = filled($params['district_id'] ?? null) ? trim((string) $params['district_id']) : null;
        $circleId = filled($params['circle_id'] ?? null) ? trim((string) $params['circle_id']) : null;
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? ($params['per_page'] ?? 20))));

        $collabFlowIds = DB::table('ask_flows')
            ->whereIn('code', ['collaboration', 'collaborations', 'collaborator', 'find_a_collaborator', 'collab'])
            ->pluck('id')
            ->filter()
            ->all();
        $collabFlowIds[] = '96f66560-416d-4993-8cf5-aa1ba2c59dd9';
        $collabFlowIds = array_values(array_unique($collabFlowIds));

        $query = Ask::query()
            ->where('user_id', $user->id)
            ->where(function (Builder $q) use ($collabFlowIds): void {
                $q->whereIn('flow_id', $collabFlowIds)
                    ->orWhereHas('flow', fn (Builder $fq) => $fq->whereIn('code', ['collaboration', 'collaborations', 'collaborator', 'find_a_collaborator', 'collab']))
                    ->orWhereHas('type', fn (Builder $tq) => $tq->whereIn('flow_id', $collabFlowIds)->orWhere('flow_id', fn ($sub) => $sub->select('id')->from('ask_flows')->whereIn('code', ['collaboration', 'collaborations', 'collaborator', 'find_a_collaborator', 'collab'])));
            })
            ->with([
                'flow',
                'type',
                'district',
                'circle',
                'user.city',
                'answers.optionGroup',
                'answers.option',
                'responses.responder',
                'responses.introducedUser',
                'responses.contact',
            ])
            ->withCount(['responses', 'matches'])
            ->orderByDesc('created_at');

        if ($statusFilter !== '' && $statusFilter !== 'all') {
            match ($statusFilter) {
                'closed' => $query->whereIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['closed', 'cancelled', 'expired', 'archived']),
                'fulfilled' => $query->where(function (Builder $sq): void {
                    $sq->whereIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['fulfilled', 'completed'])
                        ->orWhereNotNull('fulfilled_at');
                }),
                'in_progress' => $query->where(function (Builder $sq): void {
                    $sq->whereIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['in_progress', 'review', 'pending'])
                        ->orWhereHas('responses', fn (Builder $rq) => $rq->whereIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['accepted', 'in_progress', 'completed']));
                }),
                'open' => $query->where(function (Builder $sq): void {
                    $sq->whereNotIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['closed', 'cancelled', 'expired', 'archived', 'fulfilled', 'completed']);
                }),
                default => $query->whereRaw('LOWER(status) = ?', [$statusFilter]),
            };
        }

        if ($categoryId !== null) {
            $isUuid = Str::isUuid($categoryId);
            $query->where(function (Builder $cq) use ($categoryId, $isUuid): void {
                if ($isUuid) {
                    $cq->where('type_id', $categoryId)
                        ->orWhereHas('type', function (Builder $tq) use ($categoryId): void {
                            $tq->where('code', $categoryId)
                                ->orWhere('name', 'ILIKE', '%'.$categoryId.'%');
                        });
                } else {
                    $cq->whereHas('type', function (Builder $tq) use ($categoryId): void {
                        $tq->where('code', $categoryId)
                            ->orWhere('name', 'ILIKE', '%'.$categoryId.'%');
                    });
                }
            });
        }

        if ($search !== null) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function (Builder $sq) use ($like): void {
                $sq->where('title', 'ILIKE', $like)
                    ->orWhereHas('answers', fn (Builder $aq) => $aq->where('value_text', 'ILIKE', $like));
            });
        }

        if ($districtId !== null && Str::isUuid($districtId)) {
            $query->where('visibility_district_id', $districtId);
        }

        if ($circleId !== null && Str::isUuid($circleId)) {
            $query->where('visibility_circle_id', $circleId);
        }

        $asks = $query->get();

        $items = [];
        foreach ($asks as $ask) {
            /** @var Ask $ask */
            $acceptedResponse = $ask->responses->firstWhere('status', AskResponse::STATUS_ACCEPTED)
                ?? $ask->responses->firstWhere('status', AskResponse::STATUS_IN_PROGRESS)
                ?? $ask->responses->firstWhere('status', AskResponse::STATUS_COMPLETED);

            $acceptedPeer = null;
            if ($acceptedResponse) {
                $peerUser = $acceptedResponse->introducedUser ?? $acceptedResponse->responder;
                if ($peerUser) {
                    $acceptedPeer = [
                        'id' => (string) $peerUser->id,
                        'display_name' => $peerUser->display_name ?: trim(($peerUser->first_name ?? '').' '.($peerUser->last_name ?? '')),
                        'company_name' => (string) ($peerUser->company_name ?? ''),
                        'avatar_url' => $peerUser->profile_photo_file_id
                            ? url('/api/v1/files/'.$peerUser->profile_photo_file_id)
                            : ($peerUser->profile_photo_url ?? null),
                    ];
                }
            }

            $formatted = $this->formatFullAskDetails($ask, 'collaboration', $acceptedPeer);

            // Double check in-memory status filter to ensure 100% precision
            if ($statusFilter !== '' && $statusFilter !== 'all') {
                if ($formatted['status'] !== $statusFilter && (string) $ask->status !== $statusFilter) {
                    continue;
                }
            }

            $items[] = $formatted;
        }

        $total = count($items);
        $pagedItems = array_slice($items, ($page - 1) * $limit, $limit);
        $hasMore = ($page * $limit) < $total;

        return [
            'items' => $pagedItems,
            'data' => $pagedItems,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'has_more' => $hasMore,
                'total' => $total,
                'last_page' => (int) ceil($total / $limit),
                'authenticated_user_id' => (string) $user->id,
            ],
        ];
    }

    /**
     * Referral: My Referral Asks, Given Referrals & Received Introductions
     *
     * @param  array{page?: int, limit?: int}  $params
     * @return array<string, mixed>
     */
    protected function getMyReferralAsks(User $user, array $params = []): array
    {
        $statusFilter = strtolower(trim((string) ($params['status'] ?? 'all')));
        $categoryId = filled($params['category_id'] ?? null) ? trim((string) $params['category_id']) : null;
        $search = filled($params['search'] ?? null) ? trim((string) $params['search']) : null;
        $districtId = filled($params['district_id'] ?? null) ? trim((string) $params['district_id']) : null;
        $circleId = filled($params['circle_id'] ?? null) ? trim((string) $params['circle_id']) : null;
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? ($params['per_page'] ?? 20))));

        $refFlowIds = DB::table('ask_flows')
            ->whereIn('code', ['referral', 'referrals', 'ask_for_referral'])
            ->pluck('id')
            ->filter()
            ->all();
        $refFlowIds[] = 'bdab6003-95a6-4102-b235-f3461773cd0d';
        $refFlowIds = array_values(array_unique($refFlowIds));

        $askQuery = Ask::query()
            ->where('user_id', $user->id)
            ->where(function (Builder $q) use ($refFlowIds): void {
                $q->whereIn('flow_id', $refFlowIds)
                    ->orWhereHas('flow', fn (Builder $fq) => $fq->whereIn('code', ['referral', 'referrals', 'ask_for_referral']))
                    ->orWhereHas('type', fn (Builder $tq) => $tq->whereIn('flow_id', $refFlowIds)->orWhere('flow_id', fn ($sub) => $sub->select('id')->from('ask_flows')->whereIn('code', ['referral', 'referrals', 'ask_for_referral'])));
            })
            ->with([
                'flow',
                'type',
                'district',
                'circle',
                'user.city',
                'answers.optionGroup',
                'answers.option',
                'responses.responder',
                'responses.contact',
                'responses.introducedUser',
            ])
            ->withCount(['responses', 'matches'])
            ->orderByDesc('created_at');

        if ($statusFilter !== '' && $statusFilter !== 'all') {
            match ($statusFilter) {
                'closed' => $askQuery->whereIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['closed', 'cancelled', 'expired', 'archived']),
                'fulfilled' => $askQuery->where(function (Builder $sq): void {
                    $sq->whereIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['fulfilled', 'completed'])
                        ->orWhereNotNull('fulfilled_at');
                }),
                'in_progress' => $askQuery->where(function (Builder $sq): void {
                    $sq->whereIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['in_progress', 'review', 'pending'])
                        ->orWhereHas('responses', fn (Builder $rq) => $rq->whereIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['accepted', 'in_progress', 'completed']));
                }),
                'open' => $askQuery->where(function (Builder $sq): void {
                    $sq->whereNotIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['closed', 'cancelled', 'expired', 'archived', 'fulfilled', 'completed']);
                }),
                default => $askQuery->whereRaw('LOWER(status) = ?', [$statusFilter]),
            };
        }

        if ($categoryId !== null) {
            $isUuid = Str::isUuid($categoryId);
            $askQuery->where(function (Builder $cq) use ($categoryId, $isUuid): void {
                if ($isUuid) {
                    $cq->where('type_id', $categoryId)
                        ->orWhereHas('type', function (Builder $tq) use ($categoryId): void {
                            $tq->where('code', $categoryId)
                                ->orWhere('name', 'ILIKE', '%'.$categoryId.'%');
                        });
                } else {
                    $cq->whereHas('type', function (Builder $tq) use ($categoryId): void {
                        $tq->where('code', $categoryId)
                            ->orWhere('name', 'ILIKE', '%'.$categoryId.'%');
                    });
                }
            });
        }

        if ($search !== null) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $askQuery->where(function (Builder $sq) use ($like): void {
                $sq->where('title', 'ILIKE', $like)
                    ->orWhereHas('answers', fn (Builder $aq) => $aq->where('value_text', 'ILIKE', $like));
            });
        }

        if ($districtId !== null && Str::isUuid($districtId)) {
            $askQuery->where('visibility_district_id', $districtId);
        }

        if ($circleId !== null && Str::isUuid($circleId)) {
            $askQuery->where('visibility_circle_id', $circleId);
        }

        $asks = $askQuery->get();

        $myAsks = [];
        foreach ($asks as $ask) {
            /** @var Ask $ask */
            $formatted = $this->formatFullAskDetails($ask, 'referral');
            $formatted['referral_of'] = (string) $ask->title;

            if ($statusFilter !== '' && $statusFilter !== 'all') {
                if ($formatted['status'] !== $statusFilter && (string) $ask->status !== $statusFilter) {
                    continue;
                }
            }

            $myAsks[] = $formatted;
        }

        // Given Referrals from referrals table (where from_user_id = auth user)
        $givenReferralsQuery = Referral::query()
            ->where('from_user_id', $user->id)
            ->where(function (Builder $q): void {
                $q->where('is_deleted', false)
                    ->orWhereNull('is_deleted');
            })
            ->whereNull('deleted_at')
            ->with(['toUser', 'status'])
            ->orderByDesc('created_at')
            ->get();

        $givenReferrals = [];
        foreach ($givenReferralsQuery as $ref) {
            /** @var Referral $ref */
            $toUser = $ref->toUser;
            $recipientName = $toUser ? ($toUser->display_name ?: trim(($toUser->first_name ?? '').' '.($toUser->last_name ?? ''))) : 'Peer Member';
            $recipientAvatar = $toUser?->profile_photo_file_id
                ? url('/api/v1/files/'.$toUser->profile_photo_file_id)
                : ($toUser?->profile_photo_url ?? null);

            $statusName = $ref->status?->name ?? 'Contacted';
            $responseObj = [
                'response_id' => (string) $ref->id,
                'responder' => [
                    'id' => (string) ($toUser?->id ?? ''),
                    'display_name' => $recipientName,
                    'avatar_url' => $recipientAvatar,
                    'company_name' => (string) ($toUser?->company_name ?? ''),
                ],
                'contact_name' => (string) ($ref->referral_of ?: 'Contact Person'),
                'contact_phone' => (string) ($ref->phone ?? ''),
                'contact_email' => (string) ($ref->email ?? ''),
                'contact_designation' => (string) ($ref->referral_type ?? 'Referral Contact'),
                'status' => in_array(strtolower($statusName), ['accepted', 'completed', 'deal_closed', 'connected'], true) ? 'connected' : strtolower($statusName),
                'status_id' => (int) ($ref->status_id ?? 1),
                'status_label' => (string) $statusName,
            ];

            $item = [
                'id' => (string) $ref->id,
                'flow_code' => 'referral',
                'title' => (string) ($ref->referral_of ?: 'Business Referral'),
                'referral_of' => (string) ($ref->referral_of ?: 'Business Referral'),
                'description' => (string) ($ref->remarks ?: ('Referral for '.$ref->referral_of)),
                'remarks' => (string) ($ref->remarks ?? ''),
                'referral_type' => (string) ($ref->referral_type ?? 'b2b_referral'),
                'phone' => (string) ($ref->phone ?? ''),
                'email' => (string) ($ref->email ?? ''),
                'address' => (string) ($ref->address ?? ''),
                'hot_value' => (int) ($ref->hot_value ?? 1),
                'status' => strtolower($statusName) === 'deal_closed' ? 'fulfilled' : 'in_progress',
                'status_id' => (int) ($ref->status_id ?? 1),
                'status_label' => (string) $statusName,
                'responses' => [$responseObj],
                'responses_count' => 1,
                'created_at' => $ref->created_at?->toISOString() ?? now()->toISOString(),
                'to_user' => [
                    'id' => (string) ($toUser?->id ?? ''),
                    'display_name' => $recipientName,
                    'avatar_url' => $recipientAvatar,
                ],
                'recipient' => [
                    'id' => (string) ($toUser?->id ?? ''),
                    'display_name' => $recipientName,
                    'avatar_url' => $recipientAvatar,
                ],
            ];

            $givenReferrals[] = $item;
        }

        // Responses user provided to other peers' Referral Asks
        $respondedAsks = AskResponse::query()
            ->where('responder_user_id', $user->id)
            ->where(function (Builder $q): void {
                $q->whereHas('ask.flow', fn (Builder $fq) => $fq->whereIn('code', ['referral', 'referrals', 'ask_for_referral']))
                    ->orWhereIn('response_type', [AskResponse::TYPE_CAN_INTRODUCE_PEER, AskResponse::TYPE_KNOW_SOMEONE]);
            })
            ->with(['ask', 'contact', 'introducedUser'])
            ->orderByDesc('created_at')
            ->get();

        foreach ($respondedAsks as $resp) {
            $ask = $resp->ask;
            $contactName = $resp->contact?->full_name
                ?? ($resp->introducedUser ? ($resp->introducedUser->display_name ?: trim(($resp->introducedUser->first_name ?? '').' '.($resp->introducedUser->last_name ?? ''))) : 'Contact Person');
            $statusName = $resp->status ?? 'pending';

            $givenReferrals[] = [
                'id' => (string) $resp->id,
                'ask_id' => $ask ? (string) $ask->id : null,
                'flow_code' => 'referral',
                'title' => (string) ($ask?->title ?: 'Referral Response'),
                'referral_of' => (string) ($contactName ?: ($ask?->title ?? 'Business Referral')),
                'description' => (string) ($resp->message ?: ('Introduction for '.($ask?->title ?? 'Ask'))),
                'remarks' => (string) ($resp->message ?? ''),
                'referral_type' => (string) ($resp->response_type ?? 'referral_response'),
                'phone' => (string) ($resp->contact?->phone ?? ''),
                'email' => (string) ($resp->contact?->email ?? ''),
                'status' => in_array($resp->status, [AskResponse::STATUS_ACCEPTED, AskResponse::STATUS_IN_PROGRESS, 'connected'], true) ? 'connected' : (string) $resp->status,
                'status_id' => 1,
                'status_label' => ucfirst((string) $statusName),
                'responses' => [],
                'responses_count' => 0,
                'created_at' => $resp->created_at?->toISOString() ?? now()->toISOString(),
            ];
        }

        // Circle Peer Referrals (peer_referrals table)
        if (Schema::hasTable('peer_referrals')) {
            $peerRefs = PeerReferral::query()
                ->where('referrer_user_id', $user->id)
                ->with(['mainCircle', 'circle'])
                ->orderByDesc('created_at')
                ->get();

            foreach ($peerRefs as $pRef) {
                $statusName = $pRef->status ?? 'pending';
                $givenReferrals[] = [
                    'id' => (string) $pRef->id,
                    'flow_code' => 'referral',
                    'title' => (string) ($pRef->referred_name ? 'Peer Recommendation: '.$pRef->referred_name : 'Peer Recommendation'),
                    'referral_of' => (string) ($pRef->referred_name ?? 'Peer Recommendation'),
                    'description' => (string) ($pRef->message ?: ('Recommended peer '.($pRef->referred_name ?? ''))),
                    'remarks' => (string) ($pRef->message ?? ''),
                    'referral_type' => 'peer_recommendation',
                    'phone' => (string) ($pRef->referred_phone ?? ''),
                    'email' => (string) ($pRef->referred_email ?? ''),
                    'company_name' => (string) ($pRef->referred_company_name ?? ''),
                    'designation' => (string) ($pRef->referred_designation ?? ''),
                    'status' => (string) $statusName,
                    'status_id' => 1,
                    'status_label' => ucfirst((string) $statusName),
                    'responses' => [],
                    'responses_count' => 0,
                    'created_at' => $pRef->created_at?->toISOString() ?? now()->toISOString(),
                ];
            }
        }

        // Received Introductions from referrals table (where to_user_id = auth user)
        $referrals = Referral::query()
            ->where('to_user_id', $user->id)
            ->where(function (Builder $q): void {
                $q->where('is_deleted', false)
                    ->orWhereNull('is_deleted');
            })
            ->whereNull('deleted_at')
            ->with(['fromUser', 'status'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $receivedIntroductions = [];
        foreach ($referrals as $ref) {
            /** @var Referral $ref */
            $introducedBy = $ref->fromUser;
            $statusName = $ref->status?->name ?? 'Contacted';

            $receivedIntroductions[] = [
                'id' => (string) $ref->id,
                'flow_code' => 'referral',
                'referral_of' => (string) ($ref->referral_of ?? 'Business Referral'),
                'title' => (string) ($ref->referral_of ?? 'Business Referral'),
                'remarks' => (string) ($ref->remarks ?? ''),
                'phone' => (string) ($ref->phone ?? ''),
                'email' => (string) ($ref->email ?? ''),
                'address' => (string) ($ref->address ?? ''),
                'hot_value' => (int) ($ref->hot_value ?? 1),
                'introduced_by' => [
                    'id' => (string) ($introducedBy?->id ?? ''),
                    'display_name' => $introducedBy ? ($introducedBy->display_name ?: trim(($introducedBy->first_name ?? '').' '.($introducedBy->last_name ?? ''))) : 'Peer Member',
                    'avatar_url' => $introducedBy?->profile_photo_file_id
                        ? url('/api/v1/files/'.$introducedBy->profile_photo_file_id)
                        : ($introducedBy?->profile_photo_url ?? null),
                ],
                'status_id' => (int) ($ref->status_id ?? 1),
                'status_label' => (string) $statusName,
                'status' => strtolower($statusName),
                'created_at' => $ref->created_at?->toISOString() ?? now()->toISOString(),
            ];
        }

        // Introductions received where auth user was introduced to an Ask
        $introducedToMe = AskResponse::query()
            ->where('introduced_user_id', $user->id)
            ->with(['ask', 'responder'])
            ->orderByDesc('created_at')
            ->get();

        foreach ($introducedToMe as $resp) {
            $ask = $resp->ask;
            $responder = $resp->responder;
            $receivedIntroductions[] = [
                'id' => (string) $resp->id,
                'ask_id' => $ask ? (string) $ask->id : null,
                'flow_code' => 'referral',
                'referral_of' => (string) ($ask?->title ?? 'Business Referral'),
                'title' => (string) ($ask?->title ?? 'Business Referral'),
                'remarks' => (string) ($resp->message ?? ''),
                'phone' => '',
                'email' => '',
                'address' => '',
                'hot_value' => 1,
                'introduced_by' => [
                    'id' => (string) ($responder?->id ?? ''),
                    'display_name' => $responder ? ($responder->display_name ?: trim(($responder->first_name ?? '').' '.($responder->last_name ?? ''))) : 'Peer Member',
                    'avatar_url' => $responder?->profile_photo_file_id
                        ? url('/api/v1/files/'.$responder->profile_photo_file_id)
                        : ($responder?->profile_photo_url ?? null),
                ],
                'status_id' => 1,
                'status_label' => ucfirst((string) ($resp->status ?? 'pending')),
                'status' => (string) ($resp->status ?? 'pending'),
                'created_at' => $resp->created_at?->toISOString() ?? now()->toISOString(),
            ];
        }

        $filter = strtolower(trim((string) ($params['filter'] ?? 'all')));

        if ($filter === 'given') {
            $allMyAsks = $givenReferrals;
        } elseif ($filter === 'received' || $filter === 'introductions') {
            $allMyAsks = $receivedIntroductions;
        } elseif ($filter === 'asks' || $filter === 'requirements') {
            $allMyAsks = $myAsks;
        } else {
            // Default: includes all requirements, given referrals, and received introductions so nothing is hidden
            $allMyAsks = array_merge($myAsks, $givenReferrals, $receivedIntroductions);
        }

        usort($allMyAsks, fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        usort($givenReferrals, fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        usort($receivedIntroductions, fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        $total = count($allMyAsks);
        $pagedAll = array_slice($allMyAsks, ($page - 1) * $limit, $limit);
        $hasMore = ($page * $limit) < $total;

        return [
            'items' => $pagedAll,
            'my_asks' => $myAsks,
            'data' => $pagedAll,
            'given_referrals' => $givenReferrals,
            'received_introductions' => $receivedIntroductions,
            'referrals' => $pagedAll,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'has_more' => $hasMore,
                'total' => $total,
                'last_page' => (int) ceil($total / $limit),
                'authenticated_user_id' => (string) $user->id,
            ],
        ];
    }

    /**
     * Help: My Help Requests & Guidance History
     *
     * @param  array{page?: int, limit?: int}  $params
     * @return array{items: array<int, mixed>, data: array<int, mixed>}
     */
    protected function getMyHelpAsks(User $user, array $params = []): array
    {
        $statusFilter = strtolower(trim((string) ($params['status'] ?? 'all')));
        $categoryId = filled($params['category_id'] ?? null) ? trim((string) $params['category_id']) : null;
        $search = filled($params['search'] ?? null) ? trim((string) $params['search']) : null;
        $districtId = filled($params['district_id'] ?? null) ? trim((string) $params['district_id']) : null;
        $circleId = filled($params['circle_id'] ?? null) ? trim((string) $params['circle_id']) : null;
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? ($params['per_page'] ?? 20))));

        $helpFlowIds = DB::table('ask_flows')
            ->whereIn('code', ['help', 'get_help', 'guidance'])
            ->pluck('id')
            ->filter()
            ->all();

        $query = Ask::query()
            ->where('user_id', $user->id)
            ->where(function (Builder $q) use ($helpFlowIds): void {
                $q->whereIn('flow_id', $helpFlowIds)
                    ->orWhereHas('flow', fn (Builder $fq) => $fq->whereIn('code', ['help', 'get_help', 'guidance']))
                    ->orWhereHas('type', fn (Builder $tq) => $tq->whereIn('flow_id', $helpFlowIds)->orWhere('flow_id', fn ($sub) => $sub->select('id')->from('ask_flows')->whereIn('code', ['help', 'get_help', 'guidance'])));
            })
            ->with([
                'flow',
                'type',
                'district',
                'circle',
                'user.city',
                'answers.optionGroup',
                'answers.option',
                'responses.responder',
                'responses.introducedUser',
                'responses.contact',
            ])
            ->withCount(['responses', 'matches'])
            ->orderByDesc('created_at');

        if ($statusFilter !== '' && $statusFilter !== 'all') {
            match ($statusFilter) {
                'closed' => $query->whereIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['closed', 'cancelled', 'expired', 'archived']),
                'fulfilled' => $query->where(function (Builder $sq): void {
                    $sq->whereIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['fulfilled', 'completed'])
                        ->orWhereNotNull('fulfilled_at');
                }),
                'in_progress' => $query->where(function (Builder $sq): void {
                    $sq->whereIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['in_progress', 'review', 'pending'])
                        ->orWhereHas('responses', fn (Builder $rq) => $rq->whereIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['accepted', 'in_progress', 'completed']));
                }),
                'open' => $query->where(function (Builder $sq): void {
                    $sq->whereNotIn(DB::raw('LOWER(COALESCE(status, \'\'))'), ['closed', 'cancelled', 'expired', 'archived', 'fulfilled', 'completed']);
                }),
                default => $query->whereRaw('LOWER(status) = ?', [$statusFilter]),
            };
        }

        if ($categoryId !== null) {
            $isUuid = Str::isUuid($categoryId);
            $query->where(function (Builder $cq) use ($categoryId, $isUuid): void {
                if ($isUuid) {
                    $cq->where('type_id', $categoryId)
                        ->orWhereHas('type', function (Builder $tq) use ($categoryId): void {
                            $tq->where('code', $categoryId)
                                ->orWhere('name', 'ILIKE', '%'.$categoryId.'%');
                        });
                } else {
                    $cq->whereHas('type', function (Builder $tq) use ($categoryId): void {
                        $tq->where('code', $categoryId)
                            ->orWhere('name', 'ILIKE', '%'.$categoryId.'%');
                    });
                }
            });
        }

        if ($search !== null) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function (Builder $sq) use ($like): void {
                $sq->where('title', 'ILIKE', $like)
                    ->orWhereHas('answers', fn (Builder $aq) => $aq->where('value_text', 'ILIKE', $like));
            });
        }

        if ($districtId !== null && Str::isUuid($districtId)) {
            $query->where('visibility_district_id', $districtId);
        }

        if ($circleId !== null && Str::isUuid($circleId)) {
            $query->where('visibility_circle_id', $circleId);
        }

        $asks = $query->get();

        $items = [];
        foreach ($asks as $ask) {
            /** @var Ask $ask */
            $fulfilledBy = null;
            if ($ask->status === Ask::STATUS_FULFILLED || filled($ask->fulfilled_at)) {
                $giverId = $ask->metadata['giver_id'] ?? null;
                $giverUser = null;
                if ($giverId) {
                    $giverUser = User::find($giverId);
                }
                if (! $giverUser) {
                    $acceptedResp = $ask->responses->firstWhere('status', AskResponse::STATUS_ACCEPTED)
                        ?? $ask->responses->firstWhere('status', AskResponse::STATUS_COMPLETED)
                        ?? $ask->responses->first();
                    $giverUser = $acceptedResp?->responder;
                }

                if ($giverUser) {
                    $fulfilledBy = [
                        'id' => (string) $giverUser->id,
                        'display_name' => $giverUser->display_name ?: trim(($giverUser->first_name ?? '').' '.($giverUser->last_name ?? '')),
                        'avatar_url' => $giverUser->profile_photo_file_id
                            ? url('/api/v1/files/'.$giverUser->profile_photo_file_id)
                            : ($giverUser->profile_photo_url ?? null),
                    ];
                }
            }

            $formatted = $this->formatFullAskDetails($ask, 'help', null, $fulfilledBy);

            if ($statusFilter !== '' && $statusFilter !== 'all') {
                if ($formatted['status'] !== $statusFilter && (string) $ask->status !== $statusFilter) {
                    continue;
                }
            }

            $items[] = $formatted;
        }

        $total = count($items);
        $pagedItems = array_slice($items, ($page - 1) * $limit, $limit);
        $hasMore = ($page * $limit) < $total;

        return [
            'items' => $pagedItems,
            'data' => $pagedItems,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'has_more' => $hasMore,
                'total' => $total,
                'last_page' => (int) ceil($total / $limit),
                'authenticated_user_id' => (string) $user->id,
            ],
        ];
    }

    /**
     * 3. Leaderboard for a specific Flow
     *
     * @param  array{limit?: int}  $params
     * @return array{leaderboard: array<int, mixed>}
     */
    public function getLeaderboard(User $user, string $flowCode, array $params = []): array
    {
        $normalizedFlow = $this->normalizeFlowCode($flowCode);
        $limit = max(1, min(100, (int) ($params['limit'] ?? 20)));

        if ($normalizedFlow === 'collaboration') {
            return $this->getCollaborationLeaderboard($limit);
        }

        if ($normalizedFlow === 'referral') {
            return $this->getReferralLeaderboard($limit);
        }

        return $this->getHelpLeaderboard($limit);
    }

    /**
     * Collaboration Leaderboard
     *
     * @return array{leaderboard: array<int, mixed>}
     */
    protected function getCollaborationLeaderboard(int $limit = 20): array
    {
        $collabCounts = Ask::query()
            ->whereHas('flow', fn (Builder $q) => $q->where('code', 'collaboration'))
            ->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->pluck('count', 'user_id')
            ->all();

        $userIds = array_keys($collabCounts);

        $users = User::query()
            ->where('status', 'active')
            ->when(! empty($userIds), fn ($q) => $q->whereIn('id', $userIds))
            ->limit($limit * 2)
            ->get();

        if ($users->isEmpty()) {
            $users = User::query()->where('status', 'active')->limit($limit)->get();
        }

        $leaderboard = [];
        foreach ($users as $u) {
            /** @var User $u */
            $collabCount = (int) ($collabCounts[$u->id] ?? 0);
            $displayName = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
            if ($displayName === '') {
                $displayName = 'Peer Member';
            }
            $avatarUrl = $u->profile_photo_file_id
                ? url('/api/v1/files/'.$u->profile_photo_file_id)
                : ($u->profile_photo_url ?? null);
            $city = $u->city_of_residence ?: (is_string($u->city) ? $u->city : data_get($u, 'city.name', ''));

            $leaderboard[] = [
                'id' => (string) $u->id,
                'user_id' => (string) $u->id,
                'display_name' => $displayName,
                'name' => $displayName,
                'company_name' => (string) ($u->company_name ?? ''),
                'city' => (string) $city,
                'avatar_url' => $avatarUrl,
                'profile_photo_url' => $avatarUrl,
                'collaborations_count' => $collabCount,
                'life_impacted' => (int) ($u->life_impact_points ?? 0),
                'score' => $collabCount,
                'user' => [
                    'id' => (string) $u->id,
                    'display_name' => $displayName,
                    'name' => $displayName,
                    'avatar_url' => $avatarUrl,
                    'company_name' => (string) ($u->company_name ?? ''),
                ],
            ];
        }

        usort($leaderboard, fn ($a, $b) => ($b['collaborations_count'] <=> $a['collaborations_count']) ?: ($b['life_impacted'] <=> $a['life_impacted']));

        $result = [];
        $rank = 1;
        foreach (array_slice($leaderboard, 0, $limit) as $item) {
            $item['rank'] = $rank++;
            $result[] = $item;
        }

        return [
            'leaderboard' => $result,
            'items' => $result,
            'data' => $result,
        ];
    }

    /**
     * Referral Leaderboard
     *
     * @return array{leaderboard: array<int, mixed>, items: array<int, mixed>, data: array<int, mixed>}
     */
    protected function getReferralLeaderboard(int $limit = 20): array
    {
        $givenCounts = Referral::query()
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->select('from_user_id', DB::raw('count(*) as count'))
            ->groupBy('from_user_id')
            ->pluck('count', 'from_user_id')
            ->all();

        $receivedCounts = Referral::query()
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->select('to_user_id', DB::raw('count(*) as count'))
            ->groupBy('to_user_id')
            ->pluck('count', 'to_user_id')
            ->all();

        $dealsByUser = BusinessDeal::query()
            ->where('is_deleted', false)
            ->select('from_user_id', DB::raw('count(*) as deals_count'))
            ->groupBy('from_user_id')
            ->pluck('deals_count', 'from_user_id')
            ->all();

        $userIds = array_unique(array_merge(
            array_keys($givenCounts),
            array_keys($receivedCounts),
            array_keys($dealsByUser)
        ));

        $users = User::query()
            ->where('status', 'active')
            ->when(! empty($userIds), fn ($q) => $q->whereIn('id', $userIds))
            ->limit($limit * 2)
            ->get();

        if ($users->isEmpty()) {
            $users = User::query()->where('status', 'active')->limit($limit)->get();
        }

        $leaderboard = [];
        foreach ($users as $u) {
            /** @var User $u */
            $given = (int) ($givenCounts[$u->id] ?? 0);
            $received = (int) ($receivedCounts[$u->id] ?? 0);
            $deals = (int) ($dealsByUser[$u->id] ?? 0);
            $displayName = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
            if ($displayName === '') {
                $displayName = 'Peer Member';
            }
            $avatarUrl = $u->profile_photo_file_id
                ? url('/api/v1/files/'.$u->profile_photo_file_id)
                : ($u->profile_photo_url ?? null);
            $city = $u->city_of_residence ?: (is_string($u->city) ? $u->city : data_get($u, 'city.name', ''));

            $leaderboard[] = [
                'id' => (string) $u->id,
                'user_id' => (string) $u->id,
                'display_name' => $displayName,
                'name' => $displayName,
                'company_name' => (string) ($u->company_name ?? ''),
                'city' => (string) $city,
                'avatar_url' => $avatarUrl,
                'profile_photo_url' => $avatarUrl,
                'referrals_given' => $given,
                'referrals_received' => $received,
                'deals_closed' => $deals,
                'score' => $given + $deals,
                'user' => [
                    'id' => (string) $u->id,
                    'display_name' => $displayName,
                    'name' => $displayName,
                    'avatar_url' => $avatarUrl,
                    'company_name' => (string) ($u->company_name ?? ''),
                ],
            ];
        }

        usort($leaderboard, fn ($a, $b) => ($b['referrals_given'] <=> $a['referrals_given']) ?: ($b['deals_closed'] <=> $a['deals_closed']));

        $result = [];
        $rank = 1;
        foreach (array_slice($leaderboard, 0, $limit) as $item) {
            $item['rank'] = $rank++;
            $result[] = $item;
        }

        return [
            'leaderboard' => $result,
            'items' => $result,
            'data' => $result,
        ];
    }

    /**
     * Help / Givers Leaderboard
     *
     * @return array{leaderboard: array<int, mixed>, items: array<int, mixed>, data: array<int, mixed>}
     */
    protected function getHelpLeaderboard(int $limit = 20): array
    {
        $helpCounts = AskResponse::query()
            ->whereHas('ask.flow', fn (Builder $q) => $q->where('code', 'help'))
            ->select('responder_user_id', DB::raw('count(*) as count'))
            ->groupBy('responder_user_id')
            ->pluck('count', 'responder_user_id')
            ->all();

        $userIds = array_keys($helpCounts);

        $users = User::query()
            ->where('status', 'active')
            ->when(! empty($userIds), fn ($q) => $q->whereIn('id', $userIds))
            ->limit($limit * 2)
            ->get();

        if ($users->isEmpty()) {
            $users = User::query()->where('status', 'active')->limit($limit)->get();
        }

        $leaderboard = [];
        foreach ($users as $u) {
            /** @var User $u */
            $rendered = (int) ($helpCounts[$u->id] ?? 0);
            $displayName = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
            if ($displayName === '') {
                $displayName = 'Peer Member';
            }
            $avatarUrl = $u->profile_photo_file_id
                ? url('/api/v1/files/'.$u->profile_photo_file_id)
                : ($u->profile_photo_url ?? null);
            $city = $u->city_of_residence ?: (is_string($u->city) ? $u->city : data_get($u, 'city.name', ''));

            $badge = match (true) {
                $rendered >= 20 => 'Top Giver of the Month',
                $rendered >= 10 => 'Community Pillar',
                $rendered >= 5 => 'Master Mentor',
                default => 'Active Helper',
            };

            $leaderboard[] = [
                'id' => (string) $u->id,
                'user_id' => (string) $u->id,
                'display_name' => $displayName,
                'name' => $displayName,
                'company_name' => (string) ($u->company_name ?? ''),
                'city' => (string) $city,
                'avatar_url' => $avatarUrl,
                'profile_photo_url' => $avatarUrl,
                'help_rendered_count' => $rendered,
                'giver_badge' => $badge,
                'score' => $rendered,
                'user' => [
                    'id' => (string) $u->id,
                    'display_name' => $displayName,
                    'name' => $displayName,
                    'avatar_url' => $avatarUrl,
                    'company_name' => (string) ($u->company_name ?? ''),
                ],
            ];
        }

        usort($leaderboard, fn ($a, $b) => $b['help_rendered_count'] <=> $a['help_rendered_count']);

        $result = [];
        $rank = 1;
        foreach (array_slice($leaderboard, 0, $limit) as $item) {
            $item['rank'] = $rank++;
            $result[] = $item;
        }

        return [
            'leaderboard' => $result,
            'items' => $result,
            'data' => $result,
        ];
    }

    /**
     * Get Categories for Asks / Collaboration / Referral / Help
     *
     * @return array<int, array{id: string, code: string, name: string, flow_code: string}>
     */
    public function getCategories(?string $flowCode = null): array
    {
        $query = AskType::query()
            ->where('is_active', true)
            ->with('flow')
            ->orderBy('sort_order');

        if ($flowCode !== null && $flowCode !== '' && $flowCode !== 'all') {
            $normalized = $this->normalizeFlowCode($flowCode);
            $query->whereHas('flow', fn (Builder $q) => $q->where('code', $normalized));
        }

        return $query->get()->map(function (AskType $type): array {
            return [
                'id' => (string) $type->id,
                'code' => (string) $type->code,
                'name' => (string) $type->name,
                'flow_code' => (string) ($type->flow?->code ?? 'collaboration'),
            ];
        })->values()->all();
    }

    /**
     * Normalize flow code string
     */
    public function normalizeFlowCode(string $flow): string
    {
        return match (strtolower(trim($flow))) {
            'collaboration', 'collaborator', 'find_a_collaborator', 'collab' => 'collaboration',
            'referral', 'referrals', 'ask_for_referral' => 'referral',
            'help', 'get_help' => 'help',
            default => strtolower(trim($flow)),
        };
    }

    /**
     * Update the status of an item within a flow (Referral, Ask, or AskResponse).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateFlowItemStatus(User $user, string $flow, string $id, array $data): array
    {
        $normalizedFlow = $this->normalizeFlowCode($flow);

        // 1. Try finding Referral
        $referral = Referral::query()->where('id', $id)->first();
        if ($referral) {
            return $this->updateReferralItem($user, $referral, $data);
        }

        // 2. Try finding Ask
        $ask = Ask::query()->where('id', $id)->first();
        if ($ask) {
            return $this->updateAskItem($user, $ask, $normalizedFlow, $data);
        }

        // 3. Try finding AskResponse
        $response = AskResponse::query()->where('id', $id)->first();
        if ($response) {
            return $this->updateResponseItem($user, $response, $data);
        }

        throw new ModelNotFoundException("Item not found for ID: {$id}");
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateReferralItem(User $user, Referral $referral, array $data): array
    {
        $statusId = $data['status_id'] ?? null;
        $statusLabel = $data['status_label'] ?? $data['status'] ?? null;
        $remarks = $data['remarks'] ?? $data['note'] ?? $data['reason'] ?? null;

        if (! $statusId && $statusLabel) {
            $statusId = $this->mapLabelToReferralStatusId((string) $statusLabel);
        }

        $resolvedStatusId = (int) ($statusId ?: 1);
        $referral->status_id = $resolvedStatusId;
        if ($remarks !== null && (string) $remarks !== '') {
            $referral->remarks = (string) $remarks;
        }
        $referral->save();

        $referral->load(['status', 'fromUser', 'toUser']);
        $statusName = $referral->status ? $referral->status->name : 'Updated';

        // Notify counterparty
        $recipient = null;
        if ($user->id === $referral->from_user_id) {
            $recipient = $referral->toUser;
        } elseif ($user->id === $referral->to_user_id) {
            $recipient = $referral->fromUser;
        }

        if ($recipient) {
            $updaterName = $user->display_name ?? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: 'A member';
            try {
                $this->notifyUserService->notifyUser(
                    $recipient,
                    $user,
                    'activity_referral_status_updated',
                    [
                        'activity_type' => 'referral',
                        'activity_id' => (string) $referral->id,
                        'title' => 'Referral Status Updated',
                        'body' => "{$updaterName} updated the status of the referral to \"{$statusName}\".",
                    ],
                    $referral
                );
            } catch (Throwable $e) {
                Log::warning('Failed sending referral status notification', [
                    'referral_id' => (string) $referral->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $mappedStatus = in_array($resolvedStatusId, [4, 5], true)
            ? 'fulfilled'
            : (in_array($resolvedStatusId, [6, 7], true) ? 'closed' : 'open');

        return [
            'id' => (string) $referral->id,
            'status' => $mappedStatus,
            'status_id' => $resolvedStatusId,
            'status_label' => $statusName,
            'remarks' => $referral->remarks,
            'flow_code' => 'referral',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateAskItem(User $user, Ask $ask, string $flow, array $data): array
    {
        $statusId = $data['status_id'] ?? null;
        $statusLabel = $data['status_label'] ?? null;
        $status = $data['status'] ?? null;
        $remarks = $data['remarks'] ?? $data['note'] ?? $data['reason'] ?? null;
        $outcomeStatus = $data['outcome_status'] ?? null;
        $approxValue = $data['approx_value'] ?? null;

        if ($statusId !== null) {
            $status = match ((string) $statusId) {
                '4', '5' => Ask::STATUS_FULFILLED,
                '6', '7' => Ask::STATUS_CLOSED,
                default => Ask::STATUS_PUBLISHED,
            };
            if ((string) $statusId === '4' && ! $outcomeStatus) {
                $outcomeStatus = 'deal_closed';
            } elseif ((string) $statusId === '5' && ! $outcomeStatus) {
                $outcomeStatus = 'yes_fully';
            }
        } elseif ($statusLabel) {
            $statusIdInt = $this->mapLabelToReferralStatusId((string) $statusLabel);
            $status = match ($statusIdInt) {
                4, 5 => Ask::STATUS_FULFILLED,
                6, 7 => Ask::STATUS_CLOSED,
                default => Ask::STATUS_PUBLISHED,
            };
        }

        $validStatuses = [
            Ask::STATUS_DRAFT,
            Ask::STATUS_PUBLISHED,
            Ask::STATUS_IN_PROGRESS,
            Ask::STATUS_FULFILLED,
            Ask::STATUS_CLOSED,
            Ask::STATUS_CANCELLED,
            Ask::STATUS_EXPIRED,
        ];

        $targetStatus = in_array((string) $status, $validStatuses, true) ? (string) $status : Ask::STATUS_FULFILLED;

        $options = [
            'outcome_status' => $outcomeStatus,
            'approx_value' => $approxValue,
            'remarks' => $remarks,
            'note' => $remarks,
        ];

        $updatedAsk = $this->askService->updateStatus(
            $ask,
            $user,
            $targetStatus,
            $remarks ? (string) $remarks : null,
            $options
        );

        return [
            'id' => (string) $updatedAsk->id,
            'status' => (string) $updatedAsk->status,
            'status_id' => $statusId !== null ? (int) $statusId : null,
            'status_label' => (string) ($statusLabel ?: ucfirst((string) $updatedAsk->status)),
            'outcome_status' => $updatedAsk->outcome_status,
            'flow_code' => $flow,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateResponseItem(User $user, AskResponse $response, array $data): array
    {
        $statusId = $data['status_id'] ?? null;
        $statusLabel = $data['status_label'] ?? $data['status'] ?? null;
        $remarks = $data['remarks'] ?? $data['note'] ?? $data['reason'] ?? null;

        $mappedStatus = match ((string) $statusId) {
            '1' => AskResponse::STATUS_PENDING,
            '2', '3' => 'connected',
            '4' => AskResponse::STATUS_COMPLETED,
            '5' => AskResponse::STATUS_COMPLETED,
            '6', '7' => AskResponse::STATUS_DECLINED,
            default => ($statusLabel ? strtolower(str_replace(' ', '_', (string) $statusLabel)) : AskResponse::STATUS_ACCEPTED),
        };

        $response->update([
            'status' => $mappedStatus,
        ]);

        AskResponseStatusHistory::query()->create([
            'response_id' => $response->id,
            'changed_by_user_id' => $user->id,
            'old_status' => $response->getOriginal('status') ?? AskResponse::STATUS_PENDING,
            'new_status' => $mappedStatus,
            'note' => $remarks ?: ($statusLabel ? 'Status updated to '.$statusLabel : null),
        ]);

        if (in_array((string) $statusId, ['4', '5'], true) && $response->ask) {
            $response->ask->update([
                'status' => Ask::STATUS_FULFILLED,
                'fulfilled_at' => now(),
                'outcome_status' => (string) $statusId === '4' ? 'got_the_business' : 'testimonial_given',
                'outcome_notes' => $remarks,
            ]);
        }

        return [
            'id' => (string) $response->id,
            'status' => $response->status,
            'status_id' => $statusId !== null ? (int) $statusId : null,
            'status_label' => $statusLabel,
            'remarks' => $remarks,
        ];
    }

    /**
     * Map text label to ReferralStatus ID (1-8)
     */
    public function mapLabelToReferralStatusId(string $label): int
    {
        $normalized = strtolower(trim(str_replace(['_', '-'], ' ', $label)));

        return match ($normalized) {
            'not contacted yet', 'not contacted', 'open', 'pending', '1' => 1,
            'contacted', 'in discussion', 'in progress', '2' => 2,
            'no response', '3' => 3,
            'got the business', 'deal closed', 'fulfilled', '4' => 4,
            'got things done', 'completed', '5' => 5,
            'did not get the business', 'declined', 'closed', 'cancelled', 'rejected', '6' => 6,
            'not a good fit', '7' => 7,
            'confidential', '8' => 8,
            default => 1,
        };
    }

    /**
     * Apply visibility scoping to flow feed queries.
     */
    protected function applyVisibilityScope(Builder $query, User $user): void
    {
        $userCircleIds = CircleMember::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->pluck('circle_id')
            ->filter()
            ->all();

        $userDistrictIds = [];
        if (! empty($userCircleIds) && Schema::hasTable('circles') && Schema::hasColumn('circles', 'district_id')) {
            $userDistrictIds = DB::table('circles')
                ->whereIn('id', $userCircleIds)
                ->whereNotNull('district_id')
                ->pluck('district_id')
                ->filter()
                ->all();
        }

        if (Schema::hasColumn('users', 'district_id') && ! empty($user->district_id)) {
            $userDistrictIds[] = (string) $user->district_id;
        }

        if ($user->city_id && Schema::hasTable('cities') && Schema::hasColumn('cities', 'district_id')) {
            $cityDistrictId = DB::table('cities')->where('id', $user->city_id)->value('district_id');
            if ($cityDistrictId) {
                $userDistrictIds[] = (string) $cityDistrictId;
            }
        }

        $cityName = trim((string) ($user->city_of_residence ?: (is_string($user->city) ? $user->city : '')));
        if ($cityName !== '' && Schema::hasTable('districts')) {
            $districtId = District::query()->where('name', 'ILIKE', "%{$cityName}%")->value('id');
            if ($districtId) {
                $userDistrictIds[] = (string) $districtId;
            }
        }

        $userDistrictIds = array_values(array_unique(array_filter($userDistrictIds)));
        $cityId = $user->city_id;

        $query->where(function (Builder $q) use ($user, $userCircleIds, $userDistrictIds, $cityId, $cityName): void {
            // Author can always see their own asks
            $q->where('user_id', $user->id)
                // Global / all peers asks
                ->orWhereNull('visibility_type')
                ->orWhereIn('visibility_type', [Ask::VISIBILITY_ALL_PEERS, 'global', 'all_peers_global'])
                // Circle asks
                ->orWhere(function (Builder $cq) use ($userCircleIds): void {
                    $cq->whereIn('visibility_type', [Ask::VISIBILITY_CIRCLE, 'my_circle']);
                    if (! empty($userCircleIds)) {
                        $cq->where(function (Builder $subCq) use ($userCircleIds): void {
                            $subCq->whereIn('visibility_circle_id', $userCircleIds)
                                ->orWhereIn('user_id', CircleMember::query()
                                    ->whereIn('circle_id', $userCircleIds)
                                    ->whereNull('deleted_at')
                                    ->select('user_id')
                                );
                        });
                    } else {
                        $cq->whereRaw('1 = 0');
                    }
                })
                // District asks
                ->orWhere(function (Builder $dq) use ($userDistrictIds, $cityId, $cityName): void {
                    $dq->whereIn('visibility_type', [Ask::VISIBILITY_DISTRICT, 'my_district']);
                    $dq->where(function (Builder $subDq) use ($userDistrictIds, $cityId, $cityName): void {
                        $hasDistrictCondition = false;
                        if (! empty($userDistrictIds)) {
                            $subDq->whereIn('visibility_district_id', $userDistrictIds);
                            $hasDistrictCondition = true;
                        }
                        if ($cityId) {
                            if ($hasDistrictCondition) {
                                $subDq->orWhereHas('user', fn (Builder $uq) => $uq->where('city_id', $cityId));
                            } else {
                                $subDq->whereHas('user', fn (Builder $uq) => $uq->where('city_id', $cityId));
                                $hasDistrictCondition = true;
                            }
                        }
                        if ($cityName !== '') {
                            if ($hasDistrictCondition) {
                                $subDq->orWhereHas('user', fn (Builder $uq) => $uq->where('city', 'ILIKE', "%{$cityName}%"));
                            } else {
                                $subDq->whereHas('user', fn (Builder $uq) => $uq->where('city', 'ILIKE', "%{$cityName}%"));
                                $hasDistrictCondition = true;
                            }
                        }
                        if (! $hasDistrictCondition) {
                            $subDq->whereRaw('1 = 0');
                        }
                    });
                });
        });
    }
}
