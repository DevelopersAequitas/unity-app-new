<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\Leadership\LeadershipCampaign;
use App\Models\Leadership\LeadershipCandidateResultToken;
use App\Models\Leadership\LeadershipNomination;
use App\Models\Leadership\LeadershipVote;
use App\Models\Leadership\LeadershipVoterVerification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class VotingService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Check voting availability (G1).
     *
     * @return array{campaign_id: string, voting_status: string, starts_at: ?string, ends_at: ?string, eligible_candidates_count: int}
     */
    public function getVotingStatus(string $campaignId): array
    {
        $campaign = LeadershipCampaign::findOrFail($campaignId);
        $now = Carbon::now();

        $status = 'closed';
        if ($campaign->status === 'active') {
            if ($campaign->voting_starts_at && $now->lt($campaign->voting_starts_at)) {
                $status = 'upcoming';
            } elseif ($campaign->voting_ends_at && $now->gt($campaign->voting_ends_at)) {
                $status = 'closed';
            } else {
                $status = 'open';
            }
        }

        $candidatesCount = LeadershipNomination::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('status', ['shortlisted', 'approved'])
            ->count();

        return [
            'campaign_id' => $campaign->id,
            'voting_status' => $status,
            'starts_at' => $campaign->voting_starts_at?->toIso8601String(),
            'ends_at' => $campaign->voting_ends_at?->toIso8601String(),
            'eligible_candidates_count' => $candidatesCount,
        ];
    }

    /**
     * Get candidate public voting profile (G2).
     */
    public function getCandidateVotingProfile(string $campaignId, string $nominationId): LeadershipNomination
    {
        /** @var LeadershipNomination $candidate */
        $candidate = LeadershipNomination::query()
            ->with(['campaign.role', 'scope'])
            ->where('campaign_id', $campaignId)
            ->whereIn('status', ['shortlisted', 'approved'])
            ->findOrFail($nominationId);

        return $candidate;
    }

    /**
     * Cast vote (G3) - High integrity & duplicate protected.
     *
     * @return array{vote_reference: string, cast_at: string, status: string}
     */
    public function castVote(string $campaignId, ?string $scopeId, string $nominationId, string $votingToken): array
    {
        $tokenData = Cache::get('voting_token:'.$votingToken);
        if (! $tokenData || $tokenData['campaign_id'] !== $campaignId) {
            throw new RuntimeException('Invalid or expired voting verification token.');
        }

        return DB::transaction(function () use ($campaignId, $scopeId, $nominationId, $tokenData, $votingToken): array {
            // Check campaign voting period inside transaction
            $campaign = LeadershipCampaign::where('id', $campaignId)->lockForUpdate()->firstOrFail();
            if ($campaign->status !== 'active') {
                throw new RuntimeException('Voting is closed for this campaign.');
            }

            $now = Carbon::now();
            if ($campaign->voting_starts_at && $now->lt($campaign->voting_starts_at)) {
                throw new RuntimeException('Voting has not yet started.');
            }
            if ($campaign->voting_ends_at && $now->gt($campaign->voting_ends_at)) {
                throw new RuntimeException('Voting has ended.');
            }

            // Check candidate eligibility
            $candidate = LeadershipNomination::where('id', $nominationId)
                ->where('campaign_id', $campaignId)
                ->whereIn('status', ['shortlisted', 'approved'])
                ->firstOrFail();

            // Lock verification record to prevent concurrent double-spend
            $verification = LeadershipVoterVerification::where('id', $tokenData['verification_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($verification->status === 'consumed') {
                throw new RuntimeException('This verification code has already been used to cast a vote.');
            }

            // Check duplicate voting by contact hash in this campaign
            $existingVote = LeadershipVote::where('campaign_id', $campaignId)
                ->where('voter_contact_hash', $tokenData['contact_hash'])
                ->exists();

            if ($existingVote) {
                $verification->update(['status' => 'consumed', 'consumed_at' => $now]);
                throw new RuntimeException('A vote has already been cast using this contact.');
            }

            $voteReference = (string) Str::uuid();

            /** @var LeadershipVote $vote */
            $vote = LeadershipVote::create([
                'campaign_id' => $campaign->id,
                'scope_id' => $scopeId ?? $candidate->scope_id,
                'nomination_id' => $candidate->id,
                'voter_user_id' => $verification->user_id,
                'voter_contact_hash' => $tokenData['contact_hash'],
                'verification_id' => $verification->id,
                'vote_reference' => $voteReference,
                'cast_at' => $now,
            ]);

            // Consume verification
            $verification->update([
                'status' => 'consumed',
                'consumed_at' => $now,
            ]);

            // Invalidate the cache token
            Cache::forget('voting_token:'.$votingToken);

            return [
                'vote_reference' => $vote->vote_reference,
                'cast_at' => $vote->cast_at?->toIso8601String() ?? $now->toIso8601String(),
                'status' => 'recorded',
            ];
        });
    }

    /**
     * Verify vote submission receipt (G4).
     *
     * @return array{vote_reference: string, recorded: bool, cast_at: ?string}
     */
    public function verifyVoteReceipt(string $voteReference): array
    {
        $vote = LeadershipVote::where('vote_reference', $voteReference)->first();

        if (! $vote) {
            return [
                'vote_reference' => $voteReference,
                'recorded' => false,
                'cast_at' => null,
            ];
        }

        return [
            'vote_reference' => $vote->vote_reference,
            'recorded' => true,
            'cast_at' => $vote->cast_at?->toIso8601String(),
        ];
    }

    /**
     * Open voting officially (G5).
     */
    public function openVoting(string $campaignId, string $remarks = '', ?string $userId = null): LeadershipCampaign
    {
        /** @var LeadershipCampaign $campaign */
        $campaign = LeadershipCampaign::findOrFail($campaignId);

        $eligibleCount = LeadershipNomination::where('campaign_id', $campaign->id)
            ->whereIn('status', ['shortlisted', 'approved'])
            ->count();

        if ($eligibleCount === 0) {
            throw new RuntimeException('Cannot open voting: no eligible candidates found.');
        }

        $campaign->update([
            'voting_starts_at' => Carbon::now(),
        ]);

        $this->auditService->log(
            action: 'campaign.voting.opened',
            entityType: 'LeadershipCampaign',
            entityId: $campaign->id,
            campaignId: $campaign->id,
            remarks: $remarks ?: 'Voting phase opened'
        );

        return $campaign->fresh();
    }

    /**
     * Close voting officially (G6).
     */
    public function closeVoting(string $campaignId, string $remarks = '', ?string $userId = null): LeadershipCampaign
    {
        /** @var LeadershipCampaign $campaign */
        $campaign = LeadershipCampaign::findOrFail($campaignId);

        $campaign->update([
            'voting_ends_at' => Carbon::now(),
        ]);

        $this->auditService->log(
            action: 'campaign.voting.closed',
            entityType: 'LeadershipCampaign',
            entityId: $campaign->id,
            campaignId: $campaign->id,
            remarks: $remarks ?: 'Voting phase closed'
        );

        return $campaign->fresh();
    }

    /**
     * Get admin voting results report (G7).
     *
     * @return array{campaign_id: string, total_votes: int, candidates: array<int, array<string, mixed>>}
     */
    public function getAdminVotingResults(
        string $campaignId,
        ?string $scopeId = null,
        string $sortBy = 'vote_count',
        string $sortOrder = 'desc'
    ): array {
        $totalVotesQuery = LeadershipVote::where('campaign_id', $campaignId);
        if ($scopeId) {
            $totalVotesQuery->where('scope_id', $scopeId);
        }
        $totalVotes = $totalVotesQuery->count();

        $candidatesQuery = LeadershipNomination::query()
            ->with(['scope', 'campaign.role'])
            ->where('campaign_id', $campaignId)
            ->whereIn('status', ['shortlisted', 'approved', 'winner', 'not_selected'])
            ->withCount([
                'votes' => function ($q) use ($scopeId): void {
                    if ($scopeId) {
                        $q->where('scope_id', $scopeId);
                    }
                },
            ]);

        if ($scopeId) {
            $candidatesQuery->where('scope_id', $scopeId);
        }

        $order = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
        if ($sortBy === 'vote_count') {
            $candidatesQuery->orderBy('votes_count', $order);
        } else {
            $candidatesQuery->orderBy('full_name', $order);
        }

        $candidates = $candidatesQuery->get();

        $candidateResults = [];
        foreach ($candidates as $cand) {
            $vCount = (int) $cand->votes_count;
            $percentage = $totalVotes > 0 ? round(($vCount / $totalVotes) * 100, 2) : 0.0;

            $candidateResults[] = [
                'nomination_id' => $cand->id,
                'candidate_name' => $cand->full_name,
                'role_name' => $cand->campaign?->role?->name,
                'scope_name' => $cand->scope?->scope_name,
                'vote_count' => $vCount,
                'percentage' => $percentage,
            ];
        }

        return [
            'campaign_id' => $campaignId,
            'total_votes' => $totalVotes,
            'candidates' => $candidateResults,
        ];
    }

    /**
     * Generate secure candidate private results link (G8).
     *
     * @return array{result_link: string, raw_token: string, expires_at: string}
     */
    public function generateCandidateResultLink(string $nominationId, int $expiresInHours = 72): array
    {
        $nomination = LeadershipNomination::findOrFail($nominationId);

        $rawToken = Str::random(48);
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = Carbon::now()->addHours($expiresInHours);

        LeadershipCandidateResultToken::create([
            'nomination_id' => $nomination->id,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);

        $baseUrl = config('app.url') ?? 'https://peersglobal.com';
        $resultLink = "{$baseUrl}/leadership/private-results/{$rawToken}";

        $this->auditService->log(
            action: 'candidate.result_link.generated',
            entityType: 'LeadershipNomination',
            entityId: $nomination->id,
            campaignId: $nomination->campaign_id,
            remarks: "Result link generated valid until {$expiresAt->toIso8601String()}"
        );

        return [
            'result_link' => $resultLink,
            'raw_token' => $rawToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Candidate's private result retrieval (G9).
     *
     * @return array{candidate: array<string, mixed>, results: array<string, mixed>}
     */
    public function getCandidatePrivateResults(string $resultAccessToken): array
    {
        $data = Cache::get('result_access_token:'.$resultAccessToken);
        if (! $data) {
            throw new RuntimeException('Invalid or expired result access token.');
        }

        $nomination = LeadershipNomination::with(['campaign.role', 'scope'])->findOrFail($data['nomination_id']);
        $voteCount = LeadershipVote::where('nomination_id', $nomination->id)->count();

        return [
            'candidate' => [
                'name' => $nomination->full_name,
                'role' => $nomination->campaign?->role?->name,
                'scope' => $nomination->scope?->scope_name,
            ],
            'results' => [
                'total_votes' => $voteCount,
                'result_status' => $nomination->status,
            ],
        ];
    }
}
