<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\Leadership\LeadershipCampaign;
use App\Models\Leadership\LeadershipCandidateResultToken;
use App\Models\Leadership\LeadershipNomination;
use App\Models\Leadership\LeadershipVote;
use App\Models\Leadership\LeadershipVoterVerification;
use App\Models\Web\WebVote;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    /**
     * Get admin voting results report (G7).
     *
     * @return array{campaign_id: string, total_votes: int, unique_voters: int, candidates: array<int, array<string, mixed>>}
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
        $totalLeadershipVotes = $totalVotesQuery->count();

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

        $candidates = $candidatesQuery->get();

        $hasWebVotes = Schema::hasTable('web_votes');
        $candidateResults = [];
        $totalVotesCalculated = 0;

        foreach ($candidates as $cand) {
            $vCount = (int) $cand->votes_count;

            if ($hasWebVotes) {
                $webVotesCount = WebVote::query()
                    ->where(function ($q) use ($cand) {
                        $q->where('nomination_id', $cand->id)
                            ->orWhere('candidate_name', $cand->full_name);
                        if (! empty($cand->email)) {
                            $q->orWhere('candidate_email', strtolower((string) $cand->email));
                        }
                    })
                    ->count();
                $vCount += $webVotesCount;
            }

            $totalVotesCalculated += $vCount;

            $candidateResults[] = [
                'nomination_id' => $cand->id,
                'candidate_name' => $cand->full_name,
                'application_number' => $cand->application_number ?? '-',
                'role_name' => $cand->campaign?->role?->name,
                'scope_name' => $cand->scope?->scope_name,
                'vote_count' => $vCount,
                'percentage' => 0.0,
            ];
        }

        $finalTotalVotes = max($totalLeadershipVotes, $totalVotesCalculated);

        // Calculate unique voters
        $uniqueVotersCount = $finalTotalVotes;
        if ($hasWebVotes) {
            $candIds = $candidates->pluck('id')->toArray();
            $candNames = $candidates->pluck('full_name')->toArray();
            $uniqueWeb = WebVote::query()
                ->where(function ($q) use ($campaignId, $candIds, $candNames) {
                    $q->where('campaign_id', $campaignId)
                        ->orWhereIn('nomination_id', $candIds)
                        ->orWhereIn('candidate_name', $candNames);
                })
                ->distinct('voter_email')
                ->count('voter_email');
            if ($uniqueWeb > 0) {
                $uniqueVotersCount = max($uniqueWeb, $totalLeadershipVotes);
            }
        }

        // Sort candidates
        $order = strtolower($sortOrder) === 'asc' ? 1 : -1;
        usort($candidateResults, function ($a, $b) use ($sortBy, $order) {
            if ($sortBy === 'vote_count') {
                return ($a['vote_count'] <=> $b['vote_count']) * $order;
            }

            return strcmp((string) $a['candidate_name'], (string) $b['candidate_name']) * $order;
        });

        // Recalculate percentage with final total
        foreach ($candidateResults as &$res) {
            $res['percentage'] = $finalTotalVotes > 0 ? round(($res['vote_count'] / $finalTotalVotes) * 100, 1) : 0.0;
        }
        unset($res);

        return [
            'campaign_id' => $campaignId,
            'total_votes' => $finalTotalVotes,
            'unique_voters' => $uniqueVotersCount,
            'candidates' => $candidateResults,
        ];
    }

    /**
     * Get detailed voter records ("who voted whom") for admin monitoring.
     *
     * @return array<string, mixed>
     */
    public function getDetailedVotes(
        string $campaignId,
        ?string $nominationId = null,
        ?string $search = null,
        int $page = 1,
        int $perPage = 25
    ): array {
        $hasWebVotes = Schema::hasTable('web_votes');
        $items = [];
        $total = 0;

        if ($hasWebVotes) {
            $query = WebVote::query();

            // Match campaign or candidate nominations
            $candidates = LeadershipNomination::where('campaign_id', $campaignId)->get();
            $candIds = $candidates->pluck('id')->toArray();
            $candNames = $candidates->pluck('full_name')->toArray();

            $query->where(function ($q) use ($campaignId, $candIds, $candNames) {
                $q->where('campaign_id', $campaignId)
                    ->orWhereIn('nomination_id', $candIds)
                    ->orWhereIn('candidate_name', $candNames);
            });

            if (! empty($nominationId)) {
                $targetCand = $candidates->firstWhere('id', $nominationId);
                $query->where(function ($q) use ($nominationId, $targetCand) {
                    $q->where('nomination_id', $nominationId);
                    if ($targetCand) {
                        $q->orWhere('candidate_name', $targetCand->full_name);
                        if (! empty($targetCand->email)) {
                            $q->orWhere('candidate_email', strtolower((string) $targetCand->email));
                        }
                    }
                });
            }

            if (! empty($search)) {
                $s = '%'.trim($search).'%';
                $query->where(function ($q) use ($s) {
                    $q->where('voter_name', 'ilike', $s)
                        ->orWhere('voter_email', 'ilike', $s)
                        ->orWhere('voter_phone', 'ilike', $s)
                        ->orWhere('candidate_name', 'ilike', $s)
                        ->orWhere('voter_city', 'ilike', $s)
                        ->orWhere('voter_company', 'ilike', $s)
                        ->orWhere('vote_reference', 'ilike', $s);
                });
            }

            $total = (clone $query)->count();

            $records = $query->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            foreach ($records as $r) {
                $items[] = [
                    'id' => (string) $r->id,
                    'vote_reference' => $r->vote_reference,
                    'campaign_id' => $r->campaign_id,
                    'nomination_id' => $r->nomination_id,
                    'candidate_name' => $r->candidate_name ?? 'Nominee',
                    'candidate_email' => $r->candidate_email,
                    'voter_name' => $r->voter_name,
                    'voter_email' => $r->voter_email,
                    'voter_phone' => $r->voter_phone,
                    'voter_company' => $r->voter_company,
                    'voter_designation' => $r->voter_designation,
                    'voter_city' => $r->voter_city,
                    'support_reason' => $r->support_reason,
                    'created_at' => $r->created_at ? $r->created_at->toIso8601String() : null,
                    'created_at_human' => $r->created_at ? $r->created_at->format('d M Y, h:i A') : '-',
                    'channel' => 'Website',
                ];
            }
        }

        // Also check if any leadership_votes exist and should be included
        if (empty($items)) {
            $lVotesQuery = LeadershipVote::with(['nomination', 'voterUser'])
                ->where('campaign_id', $campaignId);

            if (! empty($nominationId)) {
                $lVotesQuery->where('nomination_id', $nominationId);
            }

            $total = (clone $lVotesQuery)->count();
            $lRecords = $lVotesQuery->orderBy('cast_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            foreach ($lRecords as $lv) {
                $items[] = [
                    'id' => (string) $lv->id,
                    'vote_reference' => $lv->vote_reference,
                    'campaign_id' => $lv->campaign_id,
                    'nomination_id' => $lv->nomination_id,
                    'candidate_name' => $lv->nomination?->full_name ?? 'Nominee',
                    'candidate_email' => $lv->nomination?->email,
                    'voter_name' => $lv->voterUser?->name ?? 'Verified Member',
                    'voter_email' => $lv->voterUser?->email ?? '-',
                    'voter_phone' => $lv->voterUser?->mobile ?? '-',
                    'voter_company' => $lv->voterUser?->company_name,
                    'voter_designation' => null,
                    'voter_city' => $lv->voterUser?->city?->name ?? null,
                    'support_reason' => null,
                    'created_at' => $lv->cast_at ? $lv->cast_at->toIso8601String() : null,
                    'created_at_human' => $lv->cast_at ? $lv->cast_at->format('d M Y, h:i A') : '-',
                    'channel' => 'App',
                ];
            }
        }

        $lastPage = (int) ceil(max($total, 1) / $perPage);

        return [
            'data' => $items,
            'total' => $total,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
        ];
    }

    /**
     * Export all vote records for a campaign as CSV.
     */
    public function exportVotesCsv(string $campaignId): StreamedResponse
    {
        $campaign = LeadershipCampaign::findOrFail($campaignId);
        $candidates = LeadershipNomination::where('campaign_id', $campaignId)->get();
        $candIds = $candidates->pluck('id')->toArray();
        $candNames = $candidates->pluck('full_name')->toArray();

        $fileName = 'vote_audit_'.Str::slug($campaign->name).'_'.date('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($campaignId, $candIds, $candNames): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Vote Reference',
                'Candidate Voted For',
                'Candidate Email',
                'Voter Name',
                'Voter Email',
                'Voter Phone',
                'Voter City',
                'Voter Company',
                'Voter Designation',
                'Support Reason',
                'Channel',
                'Voted At',
            ]);

            if (Schema::hasTable('web_votes')) {
                WebVote::query()
                    ->where(function ($q) use ($campaignId, $candIds, $candNames) {
                        $q->where('campaign_id', $campaignId)
                            ->orWhereIn('nomination_id', $candIds)
                            ->orWhereIn('candidate_name', $candNames);
                    })
                    ->orderBy('created_at', 'desc')
                    ->chunk(200, function ($votes) use ($handle): void {
                        foreach ($votes as $v) {
                            fputcsv($handle, [
                                $v->vote_reference,
                                $v->candidate_name,
                                $v->candidate_email,
                                $v->voter_name,
                                $v->voter_email,
                                $v->voter_phone,
                                $v->voter_city,
                                $v->voter_company,
                                $v->voter_designation,
                                $v->support_reason,
                                'Website',
                                $v->created_at ? $v->created_at->format('Y-m-d H:i:s') : '',
                            ]);
                        }
                    });
            }

            fclose($handle);
        }, 200, $headers);
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
