<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Services\Leadership\DecisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinalDecisionController extends LeadershipBaseController
{
    public function __construct(
        protected DecisionService $decisionService
    ) {}

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
        ]);

        try {
            $campaign = \App\Models\Leadership\LeadershipCampaign::findOrFail($validated['campaign_id']);
            $nomination = \App\Models\Leadership\LeadershipNomination::findOrFail($validated['nomination_id']);

            $decisionKey = strtolower(trim((string) $validated['decision']));
            $isWinner = $validated['is_winner'] ?? in_array($decisionKey, ['selected', 'winner'], true);
            $reason = $validated['remarks'] ?? ($validated['decision_reason'] ?? 'Recorded by leadership selection committee');

            /** @var \App\Models\Leadership\LeadershipFinalDecision $decision */
            $decision = \App\Models\Leadership\LeadershipFinalDecision::updateOrCreate(
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

            // Save rank & remarks in profile_snapshot
            $snapshot = (array) ($nomination->profile_snapshot ?? []);
            if (isset($validated['rank'])) {
                $snapshot['decision_rank'] = (int) $validated['rank'];
            }
            if (! empty($validated['remarks'])) {
                $snapshot['decision_remarks'] = $validated['remarks'];
            }
            $nomination->update(['profile_snapshot' => $snapshot]);

            return $this->success($decision, 'Final selection decision recorded successfully.');
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
