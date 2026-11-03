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
        $raw = $request->all();

        // Support direct website voting payload (without requiring pre-generated OTP token)
        $hasToken = ! empty($raw['voting_token']) || ! empty($raw['votingToken']) || ! empty($raw['token']);
        $hasDirectVoter = ! empty($raw['voter_name']) || ! empty($raw['voterName']) || ! empty($raw['name']) || ! empty($raw['full_name'])
            || ! empty($raw['voter_email']) || ! empty($raw['voterEmail']) || ! empty($raw['email']);

        if (! $hasToken || $hasDirectVoter) {
            $vEmail = strtolower(trim((string) ($raw['voter_email'] ?? $raw['voterEmail'] ?? $raw['email'] ?? '')));
            if (empty($vEmail) || ! filter_var($vEmail, FILTER_VALIDATE_EMAIL)) {
                return $this->error('A valid voter email is required to record your vote.', 422);
            }

            $vName = trim((string) ($raw['voter_name'] ?? $raw['voterName'] ?? $raw['name'] ?? $raw['full_name'] ?? 'Website Supporter'));
            $vPhone = $raw['voter_phone'] ?? $raw['voterPhone'] ?? $raw['phone'] ?? $raw['mobile'] ?? null;
            $vCity = $raw['voter_city'] ?? $raw['voterCity'] ?? $raw['city'] ?? null;
            $vCompany = $raw['voter_company'] ?? $raw['voterCompany'] ?? $raw['company'] ?? null;
            $vDesignation = $raw['voter_designation'] ?? $raw['voterDesignation'] ?? $raw['designation'] ?? null;
            $supportReason = $raw['support_reason'] ?? $raw['supportReason'] ?? $raw['reason'] ?? $raw['comment'] ?? null;
            $rawCandId = $raw['nomination_id'] ?? $raw['nominee_id'] ?? $raw['candidate_id'] ?? $raw['candidateId'] ?? $raw['nominationId'] ?? $raw['nomineeId'] ?? $raw['application_number'] ?? $raw['applicationNumber'] ?? null;
            $candidateName = $raw['candidate_name'] ?? $raw['nominee_name'] ?? $raw['candidateName'] ?? $raw['nomineeName'] ?? null;
            $candidateEmail = $raw['candidate_email'] ?? $raw['nominee_email'] ?? $raw['candidateEmail'] ?? $raw['nomineeEmail'] ?? null;
            $campaignId = $raw['campaign_id'] ?? $raw['campaignId'] ?? null;
            $voteReference = $raw['vote_reference'] ?? $raw['voteReference'] ?? null;

            try {
                $ref = $voteReference ?: ('PG-VOTE-'.date('Y').'-'.strtoupper(\Illuminate\Support\Str::random(6)));

                // Safe candidate resolution without PostgreSQL UUID type crash
                $nomination = null;
                if (! empty($rawCandId)) {
                    $candStr = trim((string) $rawCandId);
                    $q = \App\Models\Leadership\LeadershipNomination::query();
                    if (\Illuminate\Support\Str::isUuid($candStr)) {
                        $q->where(function ($sub) use ($candStr): void {
                            $sub->where('id', $candStr)->orWhere('user_id', $candStr);
                        });
                    } else {
                        $q->where(function ($sub) use ($candStr): void {
                            $sub->where('application_number', $candStr)
                                ->orWhereRaw('LOWER(application_number) = ?', [strtolower($candStr)]);
                        });
                    }
                    $nomination = $q->first();
                }

                if (! $nomination && ! empty($candidateName)) {
                    $cName = trim((string) $candidateName);
                    $nomination = \App\Models\Leadership\LeadershipNomination::query()
                        ->where(function ($q) use ($cName): void {
                            $q->whereRaw('LOWER(full_name) = ?', [strtolower($cName)])
                                ->orWhereRaw('LOWER(full_name) LIKE ?', ['%'.strtolower($cName).'%']);
                        })
                        ->first();
                }

                if (! $nomination && ! empty($candidateEmail)) {
                    $cEmail = strtolower(trim((string) $candidateEmail));
                    $nomination = \App\Models\Leadership\LeadershipNomination::query()
                        ->whereRaw('LOWER(email) = ?', [$cEmail])
                        ->first();
                }

                $leadershipNominationId = $nomination ? (string) $nomination->id : (($rawCandId && \Illuminate\Support\Str::isUuid((string) $rawCandId)) ? (string) $rawCandId : null);
                $campaignId = $nomination?->campaign_id ?? $campaignId;
                $candidateName = $nomination?->full_name ?? $candidateName;
                $candidateEmail = $nomination?->email ?? $candidateEmail;
                $appNumber = $nomination?->application_number ?? (is_string($rawCandId) ? $rawCandId : null);
                $scopeId = $nomination?->scope_id;

                // 1. Record into web_votes if table exists
                if (\Illuminate\Support\Facades\Schema::hasTable('web_votes')) {
                    try {
                        \App\Models\Web\WebVote::updateOrCreate(
                            ['vote_reference' => $ref],
                            [
                                'campaign_id' => $campaignId,
                                'nomination_id' => $leadershipNominationId ?? $rawCandId,
                                'candidate_name' => $candidateName,
                                'candidate_email' => $candidateEmail,
                                'voter_name' => $vName,
                                'voter_email' => $vEmail,
                                'voter_phone' => $vPhone,
                                'voter_city' => $vCity,
                                'voter_company' => $vCompany,
                                'voter_designation' => $vDesignation,
                                'support_reason' => $supportReason,
                                'created_at' => now(),
                            ]
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Could not record into web_votes: '.$e->getMessage());
                    }
                }

                // 2. Also record into web_leadership_votes if table exists
                if (\Illuminate\Support\Facades\Schema::hasTable('web_leadership_votes')) {
                    try {
                        $contactHash = hash('sha256', $vEmail);
                        \App\Models\Leadership\LeadershipVote::updateOrCreate(
                            ['vote_reference' => $ref],
                            [
                                'id' => (string) \Illuminate\Support\Str::uuid(),
                                'campaign_id' => $campaignId,
                                'scope_id' => $scopeId,
                                'nomination_id' => $leadershipNominationId,
                                'voter_user_id' => null,
                                'voter_contact_hash' => $contactHash,
                                'verification_id' => null,
                                'vote_reference' => $ref,
                                'cast_at' => now(),
                                'metadata' => [
                                    'voter_name' => $vName,
                                    'voter_email' => $vEmail,
                                    'voter_phone' => $vPhone,
                                    'voter_city' => $vCity,
                                    'voter_company' => $vCompany,
                                    'voter_designation' => $vDesignation,
                                    'support_reason' => $supportReason,
                                    'candidate_name' => $candidateName,
                                    'candidate_email' => $candidateEmail,
                                    'application_number' => $appNumber,
                                    'nomination_reference' => $rawCandId,
                                    'channel' => 'Website',
                                ],
                            ]
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Could not record into web_leadership_votes: '.$e->getMessage());
                    }
                }

                return $this->success([
                    'vote_reference' => $ref,
                    'created_at' => now()->toIso8601String(),
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
