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
        $query = \App\Models\Leadership\LeadershipJuryAssignment::with([
            'nomination.campaign.role',
            'juror.mainBusinessCategory',
            'juror.businessCategory',
            'report',
            'scores.criterion',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('campaign_id')) {
            $query->whereHas('nomination', function ($q) use ($request): void {
                $q->where('campaign_id', $request->query('campaign_id'));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $assignments = $query->get();

        $assignments->each(function ($asg): void {
            if ($asg->nomination?->application_number === 'PG-STANDING-POOL') {
                $asg->setAttribute('is_standing', true);
                $asg->setAttribute('candidate_name', 'All Incoming Candidates (Standing Pool)');
            }
            if ($asg->juror) {
                $compName = $asg->juror->company_name ?: ($asg->juror->designation ? "{$asg->juror->designation} at Enterprise" : 'Enterprise Services');
                $catName = $asg->juror->mainBusinessCategory?->name ?: ($asg->juror->businessCategory?->name ?: ($asg->juror->business_sub_category ?: 'Industry Peer'));

                $asg->juror->unsetRelation('businessCategory');
                $asg->juror->unsetRelation('mainBusinessCategory');

                $asg->juror->setAttribute('business_name', $compName);
                $asg->juror->setAttribute('business_category', $catName);

                $asg->setAttribute('juror_business_name', $compName);
                $asg->setAttribute('juror_business_category', $catName);
            }
        });

        return $this->success($assignments, 'Jury assignments fetched successfully.');
    }

    /**
     * Admin: Assign a juror to a candidate directly.
     */
    public function adminAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomination_id' => 'required|string',
            'campaign_id' => 'nullable|uuid',
            'jury_user_id' => 'nullable|uuid',
            'juror_user_ids' => 'nullable|array',
            'juror_user_ids.*' => 'uuid',
            'deadline' => 'nullable|date',
            'due_at' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        $nominationId = (string) $validated['nomination_id'];
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
            // Always ensure the standing pool placeholder exists
            $standingNomination = \App\Models\Leadership\LeadershipNomination::firstOrCreate(
                ['application_number' => 'PG-STANDING-POOL'],
                [
                    'candidate_name' => 'Standing Jury Pool',
                    'full_name' => 'Standing Jury Pool',
                    'email' => 'jury-standing@peersglobal.com',
                    'applied_role' => 'general',
                    'status' => 'approved',
                    'form_type' => 'main_form_1',
                ]
            );

            // Register standing assignment for each juror so they are standing evaluators
            $standingRes = $this->juryService->assignJurors(
                $standingNomination->id,
                $jurorIds,
                $dueAt ? (string) $dueAt : null,
                $remarks ?: 'Standing Juror appointment for all candidate applications',
                Auth::id()
            );

            if ($nominationId === 'all') {
                $campaignId = $validated['campaign_id'] ?? $request->input('campaign_id');
                $nomQuery = \App\Models\Leadership\LeadershipNomination::query()
                    ->where('application_number', '!=', 'PG-STANDING-POOL')
                    ->where('application_number', 'not like', '%-F2')
                    ->whereIn('status', ['shortlisted', 'approved', 'submitted']);

                if (! empty($campaignId) && $campaignId !== 'all') {
                    $nomQuery->where('campaign_id', $campaignId);
                }

                $nominations = $nomQuery->get();

                if ($nominations->isEmpty()) {
                    // Fallback to all candidate nominations if none shortlisted/submitted
                    $nominations = \App\Models\Leadership\LeadershipNomination::where('application_number', '!=', 'PG-STANDING-POOL')
                        ->where('application_number', 'not like', '%-F2')
                        ->get();
                }

                $totalCreated = 0;
                $allAssignments = new \Illuminate\Database\Eloquent\Collection;
                $allAssignments = $allAssignments->merge($standingRes['assignments']);

                foreach ($nominations as $nom) {
                    $res = $this->juryService->assignJurors(
                        $nom->id,
                        $jurorIds,
                        $dueAt ? (string) $dueAt : null,
                        $remarks,
                        Auth::id()
                    );
                    $totalCreated += $res['assignments_created'];
                    $allAssignments = $allAssignments->merge($res['assignments']);
                }

                $candidateCount = $nominations->count();
                $msg = $candidateCount > 0
                    ? "Assigned juror(s) to {$candidateCount} candidate application(s) and registered as standing jurors for future nominations."
                    : 'Appointed standing juror(s) successfully. Any future candidate nominations will automatically be assigned to them.';

                return $this->success([
                    'assignments_created' => $totalCreated ?: count($jurorIds),
                    'assignments' => $allAssignments,
                    'candidates_assigned' => $candidateCount,
                    'is_standing' => true,
                ], $msg, 201);
            }

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
     * Admin: Get jury evaluation report for all candidates or a specific campaign.
     */
    public function adminReport(?string $campaignId = null): JsonResponse
    {
        try {
            $query = \App\Models\Leadership\LeadershipJuryAssignment::with(['nomination', 'juror', 'scores.criterion', 'report']);

            if (! empty($campaignId) && $campaignId !== 'all') {
                $query->whereHas('nomination', fn ($q) => $q->where('campaign_id', $campaignId));
            }

            $assignments = $query->get();

            return $this->success([
                'campaign_id' => $campaignId ?: 'all',
                'total_assignments' => $assignments->count(),
                'completed' => $assignments->where('status', 'completed')->count(),
                'assignments' => $assignments,
            ], 'Jury report fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Admin: Resend official appointment email to an assigned juror with portal evaluation link.
     */
    public function resendJurorEmail(Request $request): JsonResponse
    {
        $jurorUserId = $request->input('juror_user_id') ?? $request->input('user_id');
        $assignmentId = $request->input('assignment_id');

        if (! empty($assignmentId)) {
            $assignment = \App\Models\Leadership\LeadershipJuryAssignment::with(['juror', 'nomination'])->findOrFail($assignmentId);
            $this->juryService->sendInvitation($assignment->id, true, false, 'jury_evaluation_invitation');

            return $this->success(null, 'Official appointment email resent successfully.');
        }

        if (! $jurorUserId) {
            return $this->error('Juror User ID or Assignment ID is required.', 422);
        }

        $juror = \App\Models\User::findOrFail($jurorUserId);
        $latestAsg = \App\Models\Leadership\LeadershipJuryAssignment::with('nomination')
            ->where('juror_user_id', $jurorUserId)
            ->latest()
            ->first();

        if ($latestAsg && $latestAsg->nomination) {
            $this->juryService->sendInvitation($latestAsg->id, true, false, 'jury_evaluation_invitation');
        } else {
            $portalUrl = 'https://admin.peersunity.com/web/leadership/jury-evaluations';
            $jurorName = $juror->display_name ?: trim(($juror->first_name ?? '').' '.($juror->last_name ?? '')) ?: 'Jury Member';
            if (! empty($juror->email)) {
                \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($juror, $jurorName, $portalUrl): void {
                    $html = "
                    <div style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 28px; border: 1px solid #e2e8f0; border-radius: 14px;'>
                        <h2 style='color: #1e293b;'>Jury Evaluation Portal Access</h2>
                        <p>Dear <strong>{$jurorName}</strong>,</p>
                        <p>You have been appointed as an official Jury Member for the Peers Global Leadership Selection.</p>
                        <p style='margin: 24px 0;'><a href='{$portalUrl}' style='display: inline-block; background: #2563eb; color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600;'>Access Jury Evaluation Portal</a></p>
                        <p style='font-size: 13px; color: #64748b;'>Login with your email: {$juror->email}</p>
                    </div>";
                    $message->to($juror->email, $jurorName)
                        ->subject('Jury Evaluation Portal Access - Peers Global Leadership')
                        ->html($html);
                });
            }
        }

        return $this->success(null, "Official appointment email resent successfully to {$juror->email}.");
    }
}
