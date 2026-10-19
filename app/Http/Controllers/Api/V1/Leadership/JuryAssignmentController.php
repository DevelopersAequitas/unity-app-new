<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Services\Leadership\JuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JuryAssignmentController extends LeadershipBaseController
{
    public function __construct(
        protected JuryService $juryService
    ) {}

    /**
     * H1. List eligible jury members.
     */
    public function members(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $members = $this->juryService->listEligibleJurors($request->all(), $perPage);

        return $this->paginate($members, 'Eligible jury members fetched successfully.');
    }

    /**
     * H2. Assign juror(s) to a candidate.
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'juror_user_ids' => 'required|array',
            'juror_user_ids.*' => 'uuid',
            'due_at' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        try {
            $result = $this->juryService->assignJurors(
                $id,
                $validated['juror_user_ids'],
                $validated['due_at'] ?? null,
                $validated['remarks'] ?? '',
                Auth::id()
            );

            return $this->success($result, 'Jury members assigned successfully.', 201);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * H3. View all assignments for a candidate.
     */
    public function candidateAssignments(string $id): JsonResponse
    {
        $assignments = $this->juryService->getAssignmentsForCandidate($id);

        return $this->success($assignments, 'Jury assignments fetched successfully.');
    }

    /**
     * H4. Update assignment or due date.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $assignment = $this->juryService->updateAssignment($id, $request->all());

            return $this->success($assignment, 'Jury assignment updated successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * H5. Send email and WhatsApp invitation.
     */
    public function sendInvitation(Request $request, string $id): JsonResponse
    {
        $sendEmail = (bool) $request->input('send_email', true);
        $sendWhatsapp = (bool) $request->input('send_whatsapp', true);
        $template = (string) $request->input('message_template_key', 'jury_evaluation_invitation');

        try {
            $result = $this->juryService->sendInvitation($id, $sendEmail, $sendWhatsapp, $template);

            return $this->success($result, 'Jury invitation queued.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * H6. Juror's assigned candidate list.
     */
    public function jurorAssignments(Request $request): JsonResponse
    {
        $jurorId = (string) Auth::id();
        $status = $request->query('status');
        $perPage = (int) $request->query('per_page', 20);

        $assignments = $this->juryService->getJurorAssignments($jurorId, $status ? (string) $status : null, $perPage);

        return $this->paginate($assignments, 'Juror assignments fetched successfully.');
    }

    /**
     * H7. View assigned candidate details.
     */
    public function jurorAssignmentDetails(string $id): JsonResponse
    {
        $jurorId = (string) Auth::id();

        try {
            $assignment = $this->juryService->getJurorAssignmentDetails($id, $jurorId);

            return $this->success($assignment, 'Assignment details fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 403);
        }
    }

    /**
     * H8. Declare conflict of interest.
     */
    public function declareConflict(Request $request, string $id): JsonResponse
    {
        $jurorId = (string) Auth::id();
        $validated = $request->validate([
            'has_conflict' => 'required|boolean',
            'details' => 'nullable|string',
        ]);

        try {
            $assignment = $this->juryService->declareConflict(
                $id,
                $jurorId,
                $validated['has_conflict'],
                $validated['details'] ?? null
            );

            return $this->success($assignment, 'Conflict declaration recorded.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Admin: List all jury assignments.
     */
    public function adminAssignments(Request $request): JsonResponse
    {
        $query = \App\Models\Leadership\LeadershipJuryAssignment::with(['nomination.campaign', 'juror'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('campaign_id')) {
            $query->whereHas('nomination', function ($q) use ($request): void {
                $q->where('campaign_id', $request->query('campaign_id'));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $assignments = $query->get();

        return $this->success($assignments, 'Jury assignments fetched successfully.');
    }

    /**
     * Admin: Assign a juror to a candidate directly.
     */
    public function adminAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomination_id' => 'required|uuid',
            'jury_user_id' => 'nullable|uuid',
            'juror_user_ids' => 'nullable|array',
            'juror_user_ids.*' => 'uuid',
            'deadline' => 'nullable|date',
            'due_at' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        $nominationId = $validated['nomination_id'];
        $jurorIds = [];
        if (! empty($validated['jury_user_id'])) {
            $jurorIds[] = $validated['jury_user_id'];
        }
        if (! empty($validated['juror_user_ids'])) {
            $jurorIds = array_unique(array_merge($jurorIds, $validated['juror_user_ids']));
        }

        if (empty($jurorIds)) {
            return $this->error('At least one juror user ID is required.', 422);
        }

        $dueAt = $validated['deadline'] ?? $validated['due_at'] ?? null;
        $remarks = $validated['remarks'] ?? '';

        try {
            $result = $this->juryService->assignJurors(
                $nominationId,
                $jurorIds,
                $dueAt ? (string) $dueAt : null,
                $remarks,
                Auth::id()
            );

            return $this->success($result, 'Juror assigned successfully.', 201);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Admin: Delete/Revoke a jury assignment.
     */
    public function adminDeleteAssignment(string $id): JsonResponse
    {
        try {
            $assignment = \App\Models\Leadership\LeadershipJuryAssignment::findOrFail($id);
            $assignment->delete();

            return $this->success(null, 'Jury assignment revoked successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * Admin: List evaluation criteria.
     */
    public function adminCriteria(Request $request): JsonResponse
    {
        $query = \App\Models\Leadership\LeadershipJuryEvaluationCriterion::query()
            ->orderBy('sort_order', 'asc');

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->query('campaign_id'));
        }

        $criteria = $query->get();

        return $this->success($criteria, 'Evaluation criteria fetched successfully.');
    }

    /**
     * Admin: Create an evaluation criterion.
     */
    public function adminCreateCriterion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'campaign_id' => 'nullable|uuid',
            'category' => 'nullable|string',
            'description' => 'nullable|string',
            'max_score' => 'required|numeric|min:1',
            'weightage' => 'nullable|numeric|min:0|max:100',
            'weight' => 'nullable|numeric|min:0|max:100',
            'sort_order' => 'nullable|integer',
        ]);

        try {
            $campaignId = $validated['campaign_id'] ?? null;
            if (! $campaignId) {
                $campaignId = \App\Models\Leadership\LeadershipCampaign::orderBy('created_at', 'desc')->value('id');
            }

            $criterion = \App\Models\Leadership\LeadershipJuryEvaluationCriterion::create([
                'campaign_id' => $campaignId,
                'criterion_key' => $validated['category'] ?? 'General',
                'name' => $validated['name'],
                'description' => $validated['description'] ?? '',
                'max_score' => (float) $validated['max_score'],
                'weight' => (float) ($validated['weightage'] ?? $validated['weight'] ?? 20.0),
                'sort_order' => (int) ($validated['sort_order'] ?? 1),
                'is_active' => true,
                'is_required' => true,
            ]);

            return $this->success($criterion, 'Evaluation criterion created successfully.', 201);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Admin: Update an evaluation criterion.
     */
    public function adminUpdateCriterion(Request $request, string $id): JsonResponse
    {
        try {
            $criterion = \App\Models\Leadership\LeadershipJuryEvaluationCriterion::findOrFail($id);
            $data = $request->only(['name', 'description', 'max_score', 'weightage', 'weight', 'sort_order', 'category', 'is_active']);

            if (isset($data['weightage'])) {
                $data['weight'] = (float) $data['weightage'];
                unset($data['weightage']);
            }
            if (isset($data['category'])) {
                $data['criterion_key'] = $data['category'];
                unset($data['category']);
            }

            $criterion->update($data);

            return $this->success($criterion, 'Evaluation criterion updated successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Admin: Delete an evaluation criterion.
     */
    public function adminDeleteCriterion(string $id): JsonResponse
    {
        try {
            $criterion = \App\Models\Leadership\LeadershipJuryEvaluationCriterion::findOrFail($id);
            $criterion->delete();

            return $this->success(null, 'Evaluation criterion deleted successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * Admin: Get jury evaluation report for a campaign.
     */
    public function adminReport(string $campaignId): JsonResponse
    {
        try {
            $assignments = \App\Models\Leadership\LeadershipJuryAssignment::with(['nomination', 'juror', 'scores.criterion', 'report'])
                ->whereHas('nomination', fn ($q) => $q->where('campaign_id', $campaignId))
                ->get();

            return $this->success([
                'campaign_id' => $campaignId,
                'total_assignments' => $assignments->count(),
                'completed' => $assignments->where('status', 'completed')->count(),
                'assignments' => $assignments,
            ], 'Jury report fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
