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
}
