<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\City;
use App\Models\Leadership\LeadershipCampaign;
use App\Models\Leadership\LeadershipFinalDecision;
use App\Models\Leadership\LeadershipNomination;
use App\Services\Leadership\DecisionService;
use App\Services\Leadership\JuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinalDecisionController extends LeadershipBaseController
{
    public function __construct(
        protected DecisionService $decisionService,
        protected JuryService $juryService
    ) {}

    /**
     * Get candidate's jury evaluation breakdown and scores.
     */
    public function evaluations(string $id): JsonResponse
    {
        try {
            $nomination = LeadershipNomination::findOrFail($id);
            $summary = $this->juryService->getAdminJurySummary($nomination->id);

            return $this->success($summary, 'Candidate jury evaluations fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * List circles for winner assignment dropdown.
     */
    public function circles(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $query = Circle::query()
            ->select(['id', 'name', 'slug', 'city_id', 'type', 'status', 'meeting_mode'])
            ->with(['city:id,name,state'])
            ->whereNull('deleted_at');

        if ($search !== '') {
            $query->where('name', 'ILIKE', "%{$search}%");
        }

        $circles = $query->orderBy('name')->limit(200)->get()->map(function (Circle $c): array {
            return [
                'id' => (string) $c->id,
                'name' => (string) $c->name,
                'slug' => (string) $c->slug,
                'city_id' => $c->city_id ? (string) $c->city_id : null,
                'city' => $c->city?->name ?? '',
                'type' => (string) ($c->type ?? 'public'),
                'status' => (string) ($c->status ?? 'active'),
                'meeting_mode' => (string) ($c->meeting_mode ?? 'hybrid'),
            ];
        });

        return $this->success($circles, 'Circles fetched successfully.');
    }

    /**
     * J1. Candidates eligible for final decision.
     */
    public function candidates(string $id): JsonResponse
    {
        $candidates = $this->decisionService->getEligibleDecisionCandidates($id);

        return $this->success($candidates, 'Decision candidates fetched successfully.');
    }

    /**
     * J2. Consolidated voting and jury summary.
     */
    public function summary(string $id): JsonResponse
    {
        $summary = $this->decisionService->getConsolidatedDecisionSummary($id);

        return $this->success($summary, 'Consolidated decision summary fetched successfully.');
    }

    /**
     * J3. Record final decisions.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'decisions' => 'required|array',
            'decisions.*.nomination_id' => 'required|uuid',
            'decisions.*.decision' => 'required|in:selected,not_selected,deferred,withdrawn',
            'decisions.*.is_winner' => 'required|boolean',
            'decisions.*.decision_reason' => 'required|string',
            'decisions.*.internal_remarks' => 'nullable|string',
        ]);

        try {
            $decisions = $this->decisionService->recordFinalDecisions($id, $validated['decisions'], Auth::id());

            return $this->success($decisions, 'Final decisions recorded successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * J4. Update an unpublished decision.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $decision = $this->decisionService->updateDecision($id, $request->all(), Auth::id());

            return $this->success($decision, 'Decision updated successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * J5. List all final decisions for campaign.
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $decisions = $this->decisionService->listDecisions($id, $request->all(), $perPage);

        return $this->paginate($decisions, 'Final decisions fetched successfully.');
    }

    /**
     * J6. Publish winner decision.
     */
    public function publish(Request $request, string $id): JsonResponse
    {
        $remarks = (string) $request->input('remarks', '');

        try {
            $decision = $this->decisionService->publishWinner($id, $remarks, Auth::id());

            return $this->success($decision, 'Winner decision published successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * J7. Remove winner from public display.
     */
    public function unpublish(Request $request, string $id): JsonResponse
    {
        $remarks = (string) $request->input('remarks', '');

        try {
            $decision = $this->decisionService->unpublishWinner($id, $remarks, Auth::id());

            return $this->success($decision, 'Winner unpublished successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * J8. List winner records.
     */
    public function winners(string $id): JsonResponse
    {
        $winners = $this->decisionService->listWinners($id);

        return $this->success($winners, 'Winners fetched successfully.');
    }

    /**
     * Submit or record a single candidate selection decision.
     */
    public function submitDecision(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => 'required|uuid',
            'nomination_id' => 'required|uuid',
            'decision' => 'required|string',
            'rank' => 'nullable|integer',
            'remarks' => 'nullable|string',
            'decision_reason' => 'nullable|string',
            'is_winner' => 'nullable|boolean',
            'circle_action' => 'nullable|string|in:none,existing,new',
            'circle_id' => 'nullable|uuid',
            'new_circle_name' => 'nullable|string|max:200',
            'new_circle_description' => 'nullable|string',
            'new_circle_purpose' => 'nullable|string',
            'new_circle_city_id' => 'nullable|uuid',
            'new_circle_type' => 'nullable|string|in:public,private',
            'new_circle_meeting_mode' => 'nullable|string|in:online,offline,hybrid',
        ]);

        try {
            $campaign = LeadershipCampaign::findOrFail($validated['campaign_id']);
            $nomination = LeadershipNomination::findOrFail($validated['nomination_id']);

            $decisionKey = strtolower(trim((string) $validated['decision']));
            $isWinner = $validated['is_winner'] ?? in_array($decisionKey, ['selected', 'winner'], true);
            $reason = $validated['remarks'] ?? ($validated['decision_reason'] ?? 'Recorded by leadership selection committee');

            $assignedCircle = null;
            $circleAction = (string) ($validated['circle_action'] ?? 'none');

            // Process Circle Assignment if winner or circle specified
            if ($isWinner && $circleAction === 'existing' && ! empty($validated['circle_id'])) {
                /** @var Circle|null $existingCircle */
                $existingCircle = Circle::find($validated['circle_id']);
                if ($existingCircle) {
                    $roleName = strtolower((string) ($nomination->applied_role_name ?? $nomination->applied_role_title ?? ''));
                    if (str_contains($roleName, 'vice')) {
                        $existingCircle->vice_chair_user_id = $nomination->user_id;
                    } elseif (str_contains($roleName, 'ded') || str_contains($roleName, 'district')) {
                        $existingCircle->ded_user_id = $nomination->user_id;
                    } else {
                        $existingCircle->chair_user_id = $nomination->user_id;
                    }

                    if (empty($existingCircle->circle_founder_user_id) && ! empty($nomination->user_id)) {
                        $existingCircle->circle_founder_user_id = $nomination->user_id;
                    }
                    $existingCircle->save();

                    if (! empty($nomination->user_id)) {
                        CircleMember::updateOrCreate(
                            [
                                'circle_id' => $existingCircle->id,
                                'user_id' => $nomination->user_id,
                            ],
                            [
                                'role' => str_contains($roleName, 'vice') ? 'vice_chair' : 'chair',
                                'status' => 'active',
                            ]
                        );
                    }
                    $assignedCircle = $existingCircle;
                }
            } elseif ($isWinner && $circleAction === 'new' && ! empty($validated['new_circle_name'])) {
                $cityName = null;
                if (! empty($validated['new_circle_city_id'])) {
                    $cityName = City::where('id', $validated['new_circle_city_id'])->value('name');
                }

                $newCircle = Circle::create([
                    'name' => trim((string) $validated['new_circle_name']),
                    'description' => $validated['new_circle_description'] ?? null,
                    'purpose' => $validated['new_circle_purpose'] ?? $validated['new_circle_description'] ?? null,
                    'city_id' => $validated['new_circle_city_id'] ?? null,
                    'status' => 'active',
                    'type' => $validated['new_circle_type'] ?? 'public',
                    'country' => 'India',
                    'meeting_mode' => $validated['new_circle_meeting_mode'] ?? 'hybrid',
                    'circle_founder_user_id' => $nomination->user_id,
                    'chair_user_id' => $nomination->user_id,
                ]);

                if (! empty($nomination->user_id)) {
                    CircleMember::updateOrCreate(
                        [
                            'circle_id' => $newCircle->id,
                            'user_id' => $nomination->user_id,
                        ],
                        [
                            'role' => 'circle_founder',
                            'status' => 'active',
                        ]
                    );
                }
                $assignedCircle = $newCircle;
            }

            /** @var LeadershipFinalDecision $decision */
            $decision = LeadershipFinalDecision::updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'nomination_id' => $nomination->id,
                ],
                [
                    'decision' => $decisionKey,
                    'is_winner' => $isWinner,
                    'decision_reason' => $reason,
                    'internal_remarks' => $validated['remarks'] ?? null,
                    'decided_by' => Auth::id() ? (string) Auth::id() : null,
                    'decided_at' => now(),
                    'publication_status' => 'pending',
                ]
            );

            // Update nomination status
            $newStatus = $isWinner ? 'winner' : ($decisionKey === 'rejected' || $decisionKey === 'not_selected' ? 'not_selected' : 'approved');
            $nomination->update(['status' => $newStatus]);

            // Save rank, remarks, & assigned circle in profile_snapshot
            $snapshot = (array) ($nomination->profile_snapshot ?? []);
            if (isset($validated['rank'])) {
                $snapshot['decision_rank'] = (int) $validated['rank'];
            }
            if (! empty($validated['remarks'])) {
                $snapshot['decision_remarks'] = $validated['remarks'];
            }
            if ($assignedCircle) {
                $snapshot['assigned_circle_id'] = $assignedCircle->id;
                $snapshot['assigned_circle_name'] = $assignedCircle->name;
            }
            $nomination->update(['profile_snapshot' => $snapshot]);

            $result = $decision->toArray();
            if ($assignedCircle) {
                $result['assigned_circle'] = [
                    'id' => $assignedCircle->id,
                    'name' => $assignedCircle->name,
                    'slug' => $assignedCircle->slug,
                ];
            }

            return $this->success($result, 'Final selection decision recorded successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Get all decisions for a campaign.
     */
    public function campaignDecisions(Request $request, string $id): JsonResponse
    {
        $decisions = \App\Models\Leadership\LeadershipFinalDecision::with(['nomination'])
            ->where('campaign_id', $id)
            ->get();

        return $this->success($decisions, 'Final decisions fetched successfully.');
    }

    /**
     * Batch publish all winners for a campaign.
     */
    public function publishBatch(Request $request, string $id): JsonResponse
    {
        $remarks = (string) $request->input('remarks', 'Winners officially published');

        try {
            $decisions = \App\Models\Leadership\LeadershipFinalDecision::where('campaign_id', $id)
                ->where('is_winner', true)
                ->get();

            foreach ($decisions as $dec) {
                $dec->update([
                    'publication_status' => 'published',
                    'published_at' => now(),
                    'published_by' => Auth::id() ? (string) Auth::id() : null,
                ]);
            }

            return $this->success($decisions, 'All selected winners published successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
