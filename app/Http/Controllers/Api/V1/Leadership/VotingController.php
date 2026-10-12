<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Services\Leadership\VotingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VotingController extends LeadershipBaseController
{
    public function __construct(
        protected VotingService $votingService
    ) {}

    /**
     * G1. Check voting availability.
     */
    public function status(string $id): JsonResponse
    {
        try {
            $status = $this->votingService->getVotingStatus($id);

            return $this->success($status, 'Voting status fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * G2. Get candidate voting profile.
     */
    public function candidateProfile(string $id, string $nominationId): JsonResponse
    {
        try {
            $candidate = $this->votingService->getCandidateVotingProfile($id, $nominationId);

            return $this->success($candidate, 'Candidate voting profile fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * G3. Cast a vote.
     */
    public function castVote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => 'required|uuid',
            'scope_id' => 'nullable|uuid',
            'nomination_id' => 'required|uuid',
            'voting_token' => 'required|string',
        ]);

        try {
            $receipt = $this->votingService->castVote(
                $validated['campaign_id'],
                $validated['scope_id'] ?? null,
                $validated['nomination_id'],
                $validated['voting_token']
            );

            return $this->success($receipt, 'Your vote has been recorded.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * G4. Verify vote submission receipt.
     */
    public function verifyReceipt(string $reference): JsonResponse
    {
        $receipt = $this->votingService->verifyVoteReceipt($reference);

        return $this->success($receipt, 'Receipt verification fetched.');
    }

    /**
     * G5. Open voting.
     */
    public function openVoting(Request $request, string $id): JsonResponse
    {
        $remarks = (string) $request->input('remarks', '');

        try {
            $campaign = $this->votingService->openVoting($id, $remarks, Auth::id());

            return $this->success($campaign, 'Voting phase opened successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * G6. Close voting.
     */
    public function closeVoting(Request $request, string $id): JsonResponse
    {
        $remarks = (string) $request->input('remarks', '');

        try {
            $campaign = $this->votingService->closeVoting($id, $remarks, Auth::id());

            return $this->success($campaign, 'Voting phase closed successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * G7. Admin voting report.
     */
    public function results(Request $request, string $id): JsonResponse
    {
        $scopeId = $request->query('scope_id');
        $sortBy = (string) $request->query('sort_by', 'vote_count');
        $sortOrder = (string) $request->query('sort_order', 'desc');

        $report = $this->votingService->getAdminVotingResults(
            $id,
            $scopeId ? (string) $scopeId : null,
            $sortBy,
            $sortOrder
        );

        return $this->success($report, 'Voting results fetched successfully.');
    }

    /**
     * G8. Generate candidate result link.
     */
    public function generateResultLink(Request $request, string $id): JsonResponse
    {
        $hours = (int) $request->input('expires_in_hours', 72);

        try {
            $link = $this->votingService->generateCandidateResultLink($id, $hours);

            return $this->success($link, 'Candidate result link generated successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * G9. Candidate's private result page.
     */
    public function privateResults(Request $request): JsonResponse
    {
        $token = (string) ($request->query('result_access_token') ?: $request->header('X-Result-Access-Token'));
        if (! $token) {
            return $this->error('Result access token is required.', 401);
        }

        try {
            $results = $this->votingService->getCandidatePrivateResults($token);

            return $this->success($results, 'Candidate private results fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 403);
        }
    }
}
