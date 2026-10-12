<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Services\Leadership\JuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JuryEvaluationController extends LeadershipBaseController
{
    public function __construct(
        protected JuryService $juryService
    ) {}

    /**
     * I1. Get candidate's Form 2.
     */
    public function form(string $id): JsonResponse
    {
        $jurorId = (string) Auth::id();

        try {
            $data = $this->juryService->getEvaluationForm($id, $jurorId);

            return $this->success($data, 'Jury evaluation form fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 403);
        }
    }

    /**
     * I2. Save evaluation draft.
     */
    public function saveDraft(Request $request, string $id): JsonResponse
    {
        $jurorId = (string) Auth::id();
        $answers = (array) $request->input('answers', []);

        try {
            $submission = $this->juryService->saveEvaluationDraft($id, $jurorId, $answers);

            return $this->success($submission, 'Evaluation draft saved successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * I3. Update evaluation draft.
     */
    public function updateDraft(Request $request, string $id): JsonResponse
    {
        return $this->saveDraft($request, $id);
    }

    /**
     * I4. Submit completed Form 2.
     */
    public function submitForm(Request $request, string $id): JsonResponse
    {
        $jurorId = (string) Auth::id();

        try {
            $submission = $this->juryService->submitEvaluationForm($id, $jurorId);

            return $this->success([
                'submission_id' => $submission->id,
                'status' => $submission->status,
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
            ], 'Jury evaluation form submitted successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * I5. Get scoring criteria.
     */
    public function criteria(string $id): JsonResponse
    {
        $jurorId = (string) Auth::id();

        try {
            $criteria = $this->juryService->getScoringCriteria($id, $jurorId);

            return $this->success($criteria, 'Scoring criteria fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 403);
        }
    }

    /**
     * I6. Save scores.
     */
    public function saveScores(Request $request, string $id): JsonResponse
    {
        $jurorId = (string) Auth::id();
        $validated = $request->validate([
            'scores' => 'required|array',
            'scores.*.criterion_id' => 'required|uuid',
            'scores.*.score' => 'required|numeric',
            'scores.*.remarks' => 'nullable|string',
        ]);

        try {
            $scores = $this->juryService->saveScores($id, $jurorId, $validated['scores']);

            return $this->success($scores, 'Scores saved successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * I7. Submit final jury report.
     */
    public function submitReport(Request $request, string $id): JsonResponse
    {
        $jurorId = (string) Auth::id();
        $validated = $request->validate([
            'overall_recommendation' => 'required|in:strongly_recommend,recommend,recommend_with_reservations,do_not_recommend,abstain',
            'strengths' => 'required|string',
            'concerns' => 'required|string',
            'verification_summary' => 'nullable|string',
            'final_remarks' => 'nullable|string',
            'conflict_of_interest' => 'nullable|boolean',
            'conflict_details' => 'nullable|string',
        ]);

        try {
            $report = $this->juryService->submitFinalReport($id, $jurorId, $validated);

            return $this->success([
                'report_id' => $report->id,
                'assignment_status' => 'completed',
                'submitted_at' => $report->submitted_at?->toIso8601String(),
            ], 'Jury report submitted successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * I8. Admin view of all jury evaluations.
     */
    public function summary(string $id): JsonResponse
    {
        try {
            $summary = $this->juryService->getAdminJurySummary($id);

            return $this->success($summary, 'Jury summary fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 404);
        }
    }
}
