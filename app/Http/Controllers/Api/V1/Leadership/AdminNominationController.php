<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Services\Leadership\AdminNominationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminNominationController extends LeadershipBaseController
{
    public function __construct(
        protected AdminNominationService $nominationService
    ) {}

    /**
     * F1. List and filter nominations.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $nominations = $this->nominationService->listNominations($request->all(), $perPage);

        return $this->paginate($nominations, 'Nominations fetched successfully.');
    }

    /**
     * F2. Full application details.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $nomination = $this->nominationService->getNominationDetails($id);

            return $this->success($nomination, 'Nomination details fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * F3. List candidate documents with download URLs.
     */
    public function documents(string $id): JsonResponse
    {
        $documents = $this->nominationService->getDocuments($id);

        return $this->success($documents, 'Documents fetched successfully.');
    }

    /**
     * F4. Verify or reject a document.
     */
    public function verifyDocument(Request $request, string $id, string $documentId): JsonResponse
    {
        $validated = $request->validate([
            'decision' => 'required|in:verified,rejected',
            'remarks' => 'required|string',
        ]);

        try {
            $doc = $this->nominationService->verifyDocument(
                $id,
                $documentId,
                $validated['decision'],
                $validated['remarks'],
                Auth::id()
            );

            return $this->success($doc, 'Document verification updated successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * F5. Request corrections.
     */
    public function requestChanges(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => 'required|string',
            'required_changes' => 'nullable|array',
        ]);

        try {
            $nomination = $this->nominationService->requestChanges(
                $id,
                $validated['remarks'],
                $validated['required_changes'] ?? [],
                Auth::id()
            );

            return $this->success($nomination, 'Changes requested successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * F6. Approve nomination.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $remarks = (string) $request->input('remarks', '');

        try {
            $nomination = $this->nominationService->approveNomination($id, $remarks, Auth::id());

            return $this->success($nomination, 'Nomination approved successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * F7. Reject nomination.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string',
            'remarks' => 'nullable|string',
        ]);

        try {
            $nomination = $this->nominationService->rejectNomination(
                $id,
                $validated['reason'],
                $validated['remarks'] ?? '',
                Auth::id()
            );

            return $this->success($nomination, 'Nomination rejected successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * F8. Shortlist candidate.
     */
    public function shortlist(Request $request, string $id): JsonResponse
    {
        $remarks = (string) $request->input('remarks', '');
        $enablePublicVoting = (bool) $request->input('enable_public_voting', true);
        $enableJuryEvaluation = (bool) $request->input('enable_jury_evaluation', true);

        try {
            $nomination = $this->nominationService->shortlistCandidate(
                $id,
                $remarks,
                $enablePublicVoting,
                $enableJuryEvaluation,
                Auth::id()
            );

            return $this->success($nomination, 'Candidate shortlisted successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * F9. View full status history.
     */
    public function history(string $id): JsonResponse
    {
        $history = $this->nominationService->getHistory($id);

        return $this->success($history, 'History fetched successfully.');
    }
}
