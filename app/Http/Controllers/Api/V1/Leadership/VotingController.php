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
        // Support direct website voting payload (without requiring pre-generated OTP token)
        if ($request->filled('voter_name') || $request->filled('voter_email') || ! $request->filled('voting_token')) {
            $validated = $request->validate([
                'campaign_id' => 'nullable|string',
                'nomination_id' => 'nullable|string',
                'candidate_id' => 'nullable|string',
                'candidate_name' => 'nullable|string',
                'candidate_email' => 'nullable|string',
                'voter_name' => 'required|string|max:255',
                'voter_email' => 'required|string|email|max:255',
                'voter_phone' => 'nullable|string|max:50',
                'voter_city' => 'nullable|string|max:100',
                'voter_company' => 'nullable|string|max:255',
                'voter_designation' => 'nullable|string|max:255',
                'support_reason' => 'nullable|string',
                'vote_reference' => 'nullable|string|max:100',
            ]);

            try {
                $ref = $validated['vote_reference'] ?? ('PG-VOTE-'.date('Y').'-'.strtoupper(\Illuminate\Support\Str::random(6)));
                $nominationId = $validated['nomination_id'] ?? ($validated['candidate_id'] ?? null);

                $webVote = \App\Models\Web\WebVote::updateOrCreate(
                    ['vote_reference' => $ref],
                    [
                        'campaign_id' => $validated['campaign_id'] ?? null,
                        'nomination_id' => $nominationId,
                        'candidate_name' => $validated['candidate_name'] ?? null,
                        'candidate_email' => $validated['candidate_email'] ?? null,
                        'voter_name' => $validated['voter_name'],
                        'voter_email' => strtolower($validated['voter_email']),
                        'voter_phone' => $validated['voter_phone'] ?? null,
                        'voter_city' => $validated['voter_city'] ?? null,
                        'voter_company' => $validated['voter_company'] ?? null,
                        'voter_designation' => $validated['voter_designation'] ?? null,
                        'support_reason' => $validated['support_reason'] ?? null,
                        'created_at' => now(),
                    ]
                );

                return $this->success([
                    'vote_reference' => $webVote->vote_reference,
                    'created_at' => $webVote->created_at,
                ], 'Your vote has been recorded.');
            } catch (\Throwable $e) {
                return $this->error($e->getMessage(), 400);
            }
        }

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

    /**
     * Get detailed voter list ("who voted whom").
     */
    public function detailedVotes(Request $request, ?string $id = null): JsonResponse
    {
        $campaignId = $id ?: (string) $request->query('campaign_id');
        if (! $campaignId) {
            return $this->error('Campaign ID is required.', 400);
        }

        $nominationId = $request->query('nomination_id');
        $search = $request->query('search');
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 25);

        $results = $this->votingService->getDetailedVotes(
            $campaignId,
            $nominationId ? (string) $nominationId : null,
            $search ? (string) $search : null,
            $page,
            $perPage
        );

        return $this->success($results, 'Detailed voter records fetched successfully.');
    }

    /**
     * Export votes for campaign as CSV.
     */
    public function exportVotes(string $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->votingService->exportVotesCsv($id);
    }
}
