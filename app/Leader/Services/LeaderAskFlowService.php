<?php

declare(strict_types=1);

namespace App\Leader\Services;

use App\Models\Ask\Ask;
use App\Models\Ask\AskFlow;
use App\Models\Ask\AskMatch;
use App\Models\Ask\AskOption;
use App\Models\Ask\AskOptionGroup;
use App\Models\Ask\AskResponse;
use App\Models\Ask\AskType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class LeaderAskFlowService
{
    /**
     * Map fallback flow codes to option groups.
     *
     * @var array<string, array<string>>
     */
    protected array $groupFlowMap = [
        'collaboration_bring' => ['collaboration'],
        'collaboration_need' => ['collaboration'],
        'industry' => ['collaboration', 'referral', 'help'],
        'geography' => ['collaboration', 'referral', 'help'],
        'business_stage' => ['collaboration'],
        'timeline' => ['collaboration'],
        'expected_outcome' => ['collaboration'],
        'referral_industry' => ['referral'],
        'referral_geography' => ['referral'],
        'who_to_meet' => ['referral'],
        'ideal_profile' => ['referral'],
        'referral_reason' => ['referral'],
        'what_i_offer' => ['referral'],
        'mentorship_subtype' => ['help'],
        'help_topic' => ['help'],
        'help_duration' => ['help'],
        'help_timing' => ['help'],
        'what_needed' => ['help'],
        'done_definition' => ['help'],
    ];

    /**
     * Section layout definitions per flow.
     *
     * @var array<string, array{details: array<string>, filters: array<string>}>
     */
    protected array $flowSections = [
        'collaboration' => [
            'details' => ['goal', 'collaboration_bring', 'collaboration_need'],
            'filters' => ['industry', 'geography', 'business_stage', 'timeline', 'expected_outcome'],
        ],
        'referral' => [
            'details' => ['who_to_meet', 'ideal_profile', 'referral_reason', 'what_i_offer'],
            'filters' => ['referral_industry', 'referral_geography', 'timeline'],
        ],
        'help' => [
            'details' => ['help_topic', 'mentorship_subtype', 'what_needed', 'done_definition'],
            'filters' => ['industry', 'geography', 'help_duration', 'help_timing'],
        ],
    ];

    /**
     * Get all 3 primary Ask Flows with sub-types, option counts, and metrics for Leader App.
     *
     * @return array<string, mixed>
     */
    public function getFlows(User $user, array $filters = []): array
    {
        $includeInactive = ! empty($filters['include_inactive']);

        $flows = AskFlow::query()
            ->when(! $includeInactive, fn (Builder $q) => $q->where('is_active', true))
            ->with(['types' => function ($q) use ($includeInactive): void {
                $q->when(! $includeInactive, fn (Builder $sq) => $sq->where('is_active', true))
                    ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        $flowsData = $flows->map(function (AskFlow $flow): array {
            $stats = $this->getFlowStatistics($flow->id);

            $types = $flow->types->map(function (AskType $type): array {
                $optionsCount = AskOption::query()
                    ->where('ask_type_id', $type->id)
                    ->where('is_active', true)
                    ->count();

                return [
                    'id' => $type->id,
                    'flow_id' => $type->flow_id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'description' => $type->description,
                    'level' => $type->level,
                    'sort_order' => $type->sort_order,
                    'is_active' => (bool) $type->is_active,
                    'options_count' => $optionsCount,
                    'metadata' => $type->metadata ?? [],
                ];
            });

            return [
                'id' => $flow->id,
                'code' => $flow->code,
                'name' => $flow->name,
                'description' => $flow->description,
                'icon' => $flow->icon,
                'sort_order' => $flow->sort_order,
                'is_active' => (bool) $flow->is_active,
                'types_count' => $types->count(),
                'types' => $types->values()->all(),
                'sections' => $this->flowSections[$flow->code] ?? [
                    'details' => [],
                    'filters' => [],
                ],
                'stats' => $stats,
                'metadata' => $flow->metadata ?? [],
            ];
        });

        return [
            'total_flows' => $flowsData->count(),
            'flows' => $flowsData->values()->all(),
        ];
    }

    /**
     * Get complete details, sub-types, sections, and options for a specific flow.
     *
     * @return array<string, mixed>
     */
    public function getFlowDetails(User $user, string $flowParam, array $filters = []): array
    {
        $includeInactive = ! empty($filters['include_inactive']);

        /** @var AskFlow|null $flow */
        $flow = AskFlow::query()
            ->when(! $includeInactive, fn (Builder $q) => $q->where('is_active', true))
            ->where(function (Builder $q) use ($flowParam): void {
                $q->where('code', $flowParam);
                if (Str::isUuid($flowParam)) {
                    $q->orWhere('id', $flowParam);
                }
            })
            ->with(['types' => function ($q) use ($includeInactive): void {
                $q->when(! $includeInactive, fn (Builder $sq) => $sq->where('is_active', true))
                    ->orderBy('sort_order');
            }])
            ->first();

        if (! $flow) {
            return [
                'found' => false,
                'message' => "Flow '{$flowParam}' not found.",
            ];
        }

        // Fetch relevant option groups for this flow
        $groups = AskOptionGroup::query()
            ->when(! $includeInactive, fn (Builder $q) => $q->where('is_active', true))
            ->with(['options' => function ($q) use ($flow, $includeInactive): void {
                $q->when(! $includeInactive, fn (Builder $oq) => $oq->where('is_active', true))
                    ->where(function (Builder $sub) use ($flow): void {
                        $sub->whereNull('flow_id')
                            ->orWhere('flow_id', $flow->id);
                    })
                    ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get()
            ->filter(function (AskOptionGroup $g) use ($flow): bool {
                $flows = array_unique(array_merge($g->metadata['flows'] ?? [], $this->groupFlowMap[$g->code] ?? []));

                return in_array($flow->code, $flows, true) || in_array('all', $flows, true);
            });

        $sectionsData = $groups->map(function (AskOptionGroup $g): array {
            $options = $g->options->map(fn (AskOption $o): array => [
                'id' => $o->id,
                'code' => $o->code,
                'label' => $o->label,
                'ask_type_id' => $o->ask_type_id,
                'flow_id' => $o->flow_id,
                'sort_order' => $o->sort_order,
                'is_active' => (bool) $o->is_active,
            ]);

            return [
                'id' => $g->id,
                'code' => $g->code,
                'name' => $g->name,
                'description' => $g->description,
                'input_type' => $g->input_type,
                'is_multi_select' => (bool) $g->is_multi_select,
                'is_required' => (bool) $g->is_required,
                'sort_order' => $g->sort_order,
                'options_count' => $options->count(),
                'options' => $options->values()->all(),
            ];
        });

        $typesData = $flow->types->map(function (AskType $t): array {
            $typeOptions = AskOption::query()
                ->where('ask_type_id', $t->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'code', 'label', 'option_group_id', 'sort_order']);

            return [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'description' => $t->description,
                'level' => $t->level,
                'sort_order' => $t->sort_order,
                'is_active' => (bool) $t->is_active,
                'options_count' => $typeOptions->count(),
                'options' => $typeOptions->toArray(),
            ];
        });

        return [
            'found' => true,
            'flow' => [
                'id' => $flow->id,
                'code' => $flow->code,
                'name' => $flow->name,
                'description' => $flow->description,
                'icon' => $flow->icon,
                'sort_order' => $flow->sort_order,
                'is_active' => (bool) $flow->is_active,
                'sections_layout' => $this->flowSections[$flow->code] ?? [],
                'stats' => $this->getFlowStatistics($flow->id),
            ],
            'types' => $typesData->values()->all(),
            'option_groups' => $sectionsData->values()->all(),
        ];
    }

    /**
     * Get dynamic form configuration for a specific flow and category.
     *
     * @return array<string, mixed>
     */
    public function getFormConfig(User $user, string $flowParam, ?string $typeParam = null, bool $includeInactive = false): array
    {
        /** @var AskFlow|null $flow */
        $flow = AskFlow::query()
            ->when(! $includeInactive, fn (Builder $q) => $q->where('is_active', true))
            ->where(function (Builder $q) use ($flowParam): void {
                $q->where('code', $flowParam);
                if (Str::isUuid($flowParam)) {
                    $q->orWhere('id', $flowParam);
                }
            })
            ->first();

        if (! $flow) {
            return [
                'found' => false,
                'message' => "Flow '{$flowParam}' not found.",
            ];
        }

        /** @var AskType|null $type */
        $type = null;
        if (! empty($typeParam)) {
            $type = AskType::query()
                ->where('flow_id', $flow->id)
                ->when(! $includeInactive, fn (Builder $q) => $q->where('is_active', true))
                ->where(function (Builder $q) use ($typeParam): void {
                    $q->where('code', $typeParam);
                    if (Str::isUuid($typeParam)) {
                        $q->orWhere('id', $typeParam);
                    }
                })
                ->first();
        }

        // Fetch groups filtered for this flow
        $groups = AskOptionGroup::query()
            ->when(! $includeInactive, fn (Builder $q) => $q->where('is_active', true))
            ->with(['options' => function ($q) use ($flow, $type, $includeInactive): void {
                $q->when(! $includeInactive, fn (Builder $oq) => $oq->where('is_active', true))
                    ->where(function (Builder $oq) use ($flow, $type): void {
                        if ($type) {
                            $oq->where('ask_type_id', $type->id)
                                ->orWhere(function (Builder $sq) use ($flow, $type): void {
                                    $sq->whereNull('ask_type_id')
                                        ->where(function (Builder $sub) use ($flow): void {
                                            $sub->whereNull('flow_id')->orWhere('flow_id', $flow->id);
                                        })
                                        ->whereNotExists(function ($subQ) use ($type): void {
                                            $subQ->selectRaw(1)
                                                ->from('ask_options as sub_opts')
                                                ->whereColumn('sub_opts.option_group_id', 'ask_options.option_group_id')
                                                ->where('sub_opts.ask_type_id', $type->id)
                                                ->whereNull('sub_opts.deleted_at');
                                        });
                                });
                        } else {
                            $oq->whereNull('ask_type_id')
                                ->where(function (Builder $sq) use ($flow): void {
                                    $sq->whereNull('flow_id')->orWhere('flow_id', $flow->id);
                                });
                        }
                    })
                    ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get()
            ->filter(function (AskOptionGroup $g) use ($flow): bool {
                $flows = array_unique(array_merge($g->metadata['flows'] ?? [], $this->groupFlowMap[$g->code] ?? []));

                return in_array($flow->code, $flows, true) || in_array('all', $flows, true);
            });

        return [
            'found' => true,
            'flow' => [
                'id' => $flow->id,
                'code' => $flow->code,
                'name' => $flow->name,
                'description' => $flow->description,
                'icon' => $flow->icon,
                'sort_order' => $flow->sort_order,
                'is_active' => (bool) $flow->is_active,
                'metadata' => $flow->metadata ?? [],
            ],
            'type' => $type ? [
                'id' => $type->id,
                'flow_id' => $type->flow_id,
                'parent_id' => $type->parent_id,
                'code' => $type->code,
                'name' => $type->name,
                'description' => $type->description,
                'level' => $type->level,
                'sort_order' => $type->sort_order,
                'is_active' => (bool) $type->is_active,
                'metadata' => $type->metadata ?? [],
            ] : null,
            'sections' => $this->flowSections[$flow->code] ?? [
                'details' => [],
                'filters' => [],
            ],
            'groups' => $groups->map(fn (AskOptionGroup $g): array => [
                'id' => $g->id,
                'code' => $g->code,
                'name' => $g->name,
                'description' => $g->description,
                'input_type' => $g->input_type,
                'is_multi_select' => (bool) $g->is_multi_select,
                'is_required' => (bool) $g->is_required,
                'sort_order' => $g->sort_order,
                'is_active' => (bool) $g->is_active,
                'options' => $g->options->map(fn (AskOption $o): array => [
                    'id' => $o->id,
                    'option_group_id' => $o->option_group_id,
                    'flow_id' => $o->flow_id,
                    'ask_type_id' => $o->ask_type_id,
                    'parent_option_id' => $o->parent_option_id,
                    'code' => $o->code,
                    'label' => $o->label,
                    'description' => $o->description,
                    'sort_order' => $o->sort_order,
                    'is_active' => (bool) $o->is_active,
                    'metadata' => $o->metadata ?? [],
                ])->values()->all(),
                'metadata' => $g->metadata ?? [],
            ])->values()->all(),
        ];
    }

    /**
     * Get complete Master Data across ALL 3 Flows for mobile client caching.
     *
     * @return array<string, mixed>
     */
    public function getAllData(User $user): array
    {
        $flowsData = $this->getFlows($user);
        $summary = $this->getSummary($user);

        return [
            'summary' => $summary,
            'flows' => $flowsData['flows'],
            'version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get KPI Summary & Flow Breakdown for the Leader App Dashboard.
     *
     * @return array<string, mixed>
     */
    public function getSummary(User $user): array
    {
        $totalAsks = Ask::query()->count();
        $publishedAsks = Ask::query()->where('status', Ask::STATUS_PUBLISHED)->count();
        $inProgressAsks = Ask::query()->where('status', Ask::STATUS_IN_PROGRESS)->count();
        $fulfilledAsks = Ask::query()->where('status', Ask::STATUS_FULFILLED)->count();
        $totalMatches = AskMatch::query()->count();
        $totalResponses = AskResponse::query()->count();

        $flowBreakdown = [];
        $flows = AskFlow::query()->where('is_active', true)->orderBy('sort_order')->get();
        foreach ($flows as $f) {
            $flowBreakdown[$f->code] = $this->getFlowStatistics($f->id);
            $flowBreakdown[$f->code]['name'] = $f->name;
        }

        // Recent 5 active asks across any flow
        $recentAsks = Ask::query()
            ->with(['flow:id,code,name', 'type:id,code,name', 'user:id,name,first_name,last_name,avatar,city'])
            ->whereIn('status', [Ask::STATUS_PUBLISHED, Ask::STATUS_IN_PROGRESS])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Ask $a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'status' => $a->status,
                'flow' => $a->flow?->name,
                'flow_code' => $a->flow?->code,
                'type' => $a->type?->name,
                'creator' => $a->user ? trim(($a->user->first_name ?? '').' '.($a->user->last_name ?? '')) ?: $a->user->name : 'Peer Member',
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        return [
            'total_requirements' => $totalAsks,
            'active_requirements' => $publishedAsks + $inProgressAsks,
            'fulfilled_requirements' => $fulfilledAsks,
            'total_peer_matches' => $totalMatches,
            'total_peer_responses' => $totalResponses,
            'flows_breakdown' => $flowBreakdown,
            'recent_requirements' => $recentAsks->values()->all(),
        ];
    }

    /**
     * Compute statistics for a specific AskFlow ID.
     *
     * @return array<string, int>
     */
    protected function getFlowStatistics(string $flowId): array
    {
        $askQuery = Ask::query()->where('flow_id', $flowId);

        $total = (clone $askQuery)->count();
        $active = (clone $askQuery)->whereIn('status', [Ask::STATUS_PUBLISHED, Ask::STATUS_IN_PROGRESS])->count();
        $fulfilled = (clone $askQuery)->where('status', Ask::STATUS_FULFILLED)->count();

        $matches = AskMatch::query()
            ->whereHas('ask', fn (Builder $q) => $q->where('flow_id', $flowId))
            ->count();

        $responses = AskResponse::query()
            ->whereHas('ask', fn (Builder $q) => $q->where('flow_id', $flowId))
            ->count();

        return [
            'total_asks' => $total,
            'active_asks' => $active,
            'fulfilled_asks' => $fulfilled,
            'matches_count' => $matches,
            'responses_count' => $responses,
        ];
    }
}
