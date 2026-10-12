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
}
