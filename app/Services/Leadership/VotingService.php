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
            ->with(['campaign.role', 'scope', 'user', 'documents'])
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
        $hasLeadershipVotes = Schema::hasTable('web_leadership_votes');

        $candidateResults = [];
        $campaignAllVoteRefs = [];
        $campaignVoterEmails = [];

        foreach ($candidates as $cand) {
            $candidateVoteRefs = [];

            // 1. Check web_leadership_votes
            if ($hasLeadershipVotes) {
                $lQuery = LeadershipVote::query();
                if ($scopeId) {
                    $lQuery->where('scope_id', $scopeId);
                }
                $candIds = array_values(array_filter([$cand->id, $cand->user_id, $cand->application_number]));
                $lQuery->where(function ($q) use ($candIds, $cand): void {
                    $q->whereIn('nomination_id', $candIds);
                    if (! empty($cand->full_name)) {
                        $name = strtolower(trim((string) $cand->full_name));
                        $q->orWhereRaw("LOWER(metadata->>'candidate_name') = ?", [$name])
                            ->orWhereRaw("LOWER(metadata->>'candidate_name') LIKE ?", ['%'.$name.'%']);
                    }
                });
                $lVotes = $lQuery->get();
                foreach ($lVotes as $lv) {
                    $ref = $lv->vote_reference ?: (string) $lv->id;
                    $candidateVoteRefs[$ref] = true;
                    $campaignAllVoteRefs[$ref] = true;
                    $email = $lv->voterUser?->email ?? ($lv->metadata['voter_email'] ?? null);
                    if ($email) {
                        $campaignVoterEmails[strtolower(trim((string) $email))] = true;
                    } elseif ($lv->voter_contact_hash) {
                        $campaignVoterEmails[$lv->voter_contact_hash] = true;
                    }
                }
            }

            // 2. Check web_votes
            if ($hasWebVotes) {
                $wQuery = WebVote::query();
                $candIds = array_values(array_filter([$cand->id, $cand->user_id, $cand->application_number]));
                $wQuery->where(function ($q) use ($candIds, $cand): void {
                    $q->whereIn('nomination_id', $candIds);
                    if (! empty($cand->full_name)) {
                        $name = strtolower(trim((string) $cand->full_name));
                        $q->orWhereRaw('LOWER(candidate_name) = ?', [$name])
                            ->orWhereRaw('LOWER(candidate_name) LIKE ?', ['%'.$name.'%']);
                    }
                    if (! empty($cand->email)) {
                        $email = strtolower(trim((string) $cand->email));
                        $q->orWhereRaw('LOWER(candidate_email) = ?', [$email]);
                    }
                });
                $wVotes = $wQuery->get();
                foreach ($wVotes as $wv) {
                    $ref = $wv->vote_reference ?: ('WV-'.$wv->id);
                    $candidateVoteRefs[$ref] = true;
                    $campaignAllVoteRefs[$ref] = true;
                    if (! empty($wv->voter_email)) {
                        $campaignVoterEmails[strtolower(trim((string) $wv->voter_email))] = true;
                    }
                }
            }

            $vCount = count($candidateVoteRefs);
            if ($vCount === 0 && (int) $cand->votes_count > 0) {
                $vCount = (int) $cand->votes_count;
            }

            // Retrieve candidate result token if active
            $resultToken = null;
            if (Schema::hasTable('web_leadership_candidate_result_tokens')) {
                $tokenModel = LeadershipCandidateResultToken::where('nomination_id', $cand->id)
                    ->where(function ($q): void {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->latest()
                    ->first();
                $resultToken = $tokenModel?->token;
            }

            $candidateResults[] = [
                'nomination_id' => $cand->id,
                'candidate_name' => $cand->full_name,
                'application_number' => $cand->application_number ?? '-',
                'role_name' => $cand->campaign?->role?->name,
                'scope_name' => $cand->scope?->scope_name,
                'vote_count' => $vCount,
                'percentage' => 0.0,
                'result_token' => $resultToken,
            ];
        }

        $finalTotalVotes = max(count($campaignAllVoteRefs), array_sum(array_column($candidateResults, 'vote_count')));
        $uniqueVotersCount = max(count($campaignVoterEmails), $finalTotalVotes > 0 ? min(count($campaignVoterEmails) ?: $finalTotalVotes, $finalTotalVotes) : 0);
        if ($uniqueVotersCount === 0 && $finalTotalVotes > 0) {
            $uniqueVotersCount = $finalTotalVotes;
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
        $candidates = LeadershipNomination::where('campaign_id', $campaignId)->get();
        $candIds = $candidates->pluck('id')->toArray();
        $candUserIds = array_filter($candidates->pluck('user_id')->toArray());
        $candAppNos = array_filter($candidates->pluck('application_number')->toArray());
        $allCandIdentifiers = array_unique(array_merge($candIds, $candUserIds, $candAppNos));
        $candNames = array_map('strtolower', array_map('trim', $candidates->pluck('full_name')->toArray()));

        $targetCand = null;
        $targetIds = [];
        if (! empty($nominationId)) {
            $targetCand = $candidates->first(fn ($c) => $c->id === $nominationId || $c->user_id === $nominationId || $c->application_number === $nominationId);
            if ($targetCand) {
                $targetIds = array_values(array_filter([$targetCand->id, $targetCand->user_id, $targetCand->application_number]));
            } else {
                $targetIds = [$nominationId];
            }
        }

        $mergedVotes = [];

        // 1. Fetch from web_votes if exists
        if (Schema::hasTable('web_votes')) {
            $wQuery = WebVote::query();
            $wQuery->where(function ($q) use ($campaignId, $allCandIdentifiers, $candNames): void {
                $q->where('campaign_id', $campaignId)
                    ->orWhereIn('nomination_id', $allCandIdentifiers);
                foreach ($candNames as $name) {
                    if (! empty($name)) {
                        $q->orWhereRaw('LOWER(candidate_name) = ?', [$name])
                            ->orWhereRaw('LOWER(candidate_name) LIKE ?', ['%'.$name.'%']);
                    }
                }
            });

            if (! empty($targetIds)) {
                $wQuery->where(function ($q) use ($targetIds, $targetCand): void {
                    $q->whereIn('nomination_id', $targetIds);
                    if ($targetCand) {
                        $name = strtolower(trim((string) $targetCand->full_name));
                        $q->orWhereRaw('LOWER(candidate_name) = ?', [$name])
                            ->orWhereRaw('LOWER(candidate_name) LIKE ?', ['%'.$name.'%']);
                        if (! empty($targetCand->email)) {
                            $q->orWhereRaw('LOWER(candidate_email) = ?', [strtolower(trim((string) $targetCand->email))]);
                        }
                    }
                });
            }

            foreach ($wQuery->get() as $r) {
                $matched = $candidates->first(function ($c) use ($r) {
                    return $c->id === $r->nomination_id
                        || $c->user_id === $r->nomination_id
                        || $c->application_number === $r->nomination_id
                        || (! empty($r->candidate_name) && strtolower(trim((string) $c->full_name)) === strtolower(trim((string) $r->candidate_name)));
                });

                $ref = $r->vote_reference ?: ('WV-'.$r->id);
                $mergedVotes[$ref] = [
                    'id' => (string) $r->id,
                    'vote_reference' => $ref,
                    'campaign_id' => $r->campaign_id ?: $campaignId,
                    'nomination_id' => $matched?->id ?? $r->nomination_id,
                    'candidate_name' => $matched?->full_name ?? $r->candidate_name ?? 'Nominee',
                    'candidate_email' => $matched?->email ?? $r->candidate_email,
                    'voter_name' => $r->voter_name ?: 'Voter',
                    'voter_email' => $r->voter_email ?: '-',
                    'voter_phone' => $r->voter_phone,
                    'voter_company' => $r->voter_company,
                    'voter_designation' => $r->voter_designation,
                    'voter_city' => $r->voter_city,
                    'support_reason' => $r->support_reason,
                    'created_at' => $r->created_at ? $r->created_at->toIso8601String() : null,
                    'created_at_human' => $r->created_at ? $r->created_at->format('d M Y, h:i A') : '-',
                    'timestamp' => $r->created_at ? $r->created_at->timestamp : 0,
                    'channel' => 'Website',
                ];
            }
        }

        // 2. Fetch from web_leadership_votes if exists
        if (Schema::hasTable('web_leadership_votes')) {
            $lQuery = LeadershipVote::with(['nomination', 'voterUser']);
            $lQuery->where(function ($q) use ($campaignId, $allCandIdentifiers): void {
                $q->where('campaign_id', $campaignId)
                    ->orWhereIn('nomination_id', $allCandIdentifiers);
            });

            if (! empty($targetIds)) {
                $lQuery->whereIn('nomination_id', $targetIds);
            }

            foreach ($lQuery->get() as $lv) {
                $ref = $lv->vote_reference ?: (string) $lv->id;

                $matched = $lv->nomination ?: $candidates->first(function ($c) use ($lv) {
                    return $c->id === $lv->nomination_id
                        || $c->user_id === $lv->nomination_id
                        || $c->application_number === $lv->nomination_id;
                });

                $meta = is_array($lv->metadata) ? $lv->metadata : [];
                $vName = $lv->voterUser?->name ?? ($meta['voter_name'] ?? null) ?? 'Verified Member';
                $vEmail = $lv->voterUser?->email ?? ($meta['voter_email'] ?? null) ?? '-';
                $vPhone = $lv->voterUser?->mobile ?? ($meta['voter_phone'] ?? null) ?? '-';
                $vCompany = $lv->voterUser?->company_name ?? ($meta['voter_company'] ?? null);
                $vCity = $lv->voterUser?->city?->name ?? ($meta['voter_city'] ?? null);
                $reason = $meta['support_reason'] ?? null;
                $channel = $meta['channel'] ?? ($lv->voterUser ? 'App' : 'Website');

                if (isset($mergedVotes[$ref])) {
                    if (empty($mergedVotes[$ref]['candidate_name']) || $mergedVotes[$ref]['candidate_name'] === 'Nominee') {
                        $mergedVotes[$ref]['candidate_name'] = $matched?->full_name ?? $mergedVotes[$ref]['candidate_name'];
                    }
                    if (empty($mergedVotes[$ref]['candidate_email'])) {
                        $mergedVotes[$ref]['candidate_email'] = $matched?->email ?? $mergedVotes[$ref]['candidate_email'];
                    }
                } else {
                    $createdAt = $lv->cast_at ?? $lv->created_at;
                    $mergedVotes[$ref] = [
                        'id' => (string) $lv->id,
                        'vote_reference' => $ref,
                        'campaign_id' => $lv->campaign_id ?: $campaignId,
                        'nomination_id' => $matched?->id ?? $lv->nomination_id,
                        'candidate_name' => $matched?->full_name ?? ($meta['candidate_name'] ?? 'Nominee'),
                        'candidate_email' => $matched?->email,
                        'voter_name' => $vName,
                        'voter_email' => $vEmail,
                        'voter_phone' => $vPhone,
                        'voter_company' => $vCompany,
                        'voter_designation' => $meta['voter_designation'] ?? null,
                        'voter_city' => $vCity,
                        'support_reason' => $reason,
                        'created_at' => $createdAt ? $createdAt->toIso8601String() : null,
                        'created_at_human' => $createdAt ? $createdAt->format('d M Y, h:i A') : '-',
                        'timestamp' => $createdAt ? $createdAt->timestamp : 0,
                        'channel' => $channel,
                    ];
                }
            }
        }

        // 3. Search filtering
        $filtered = array_values($mergedVotes);
        if (! empty($search)) {
            $s = strtolower(trim($search));
            $filtered = array_filter($filtered, function ($item) use ($s): bool {
                return str_contains(strtolower((string) $item['voter_name']), $s)
                    || str_contains(strtolower((string) $item['voter_email']), $s)
                    || str_contains(strtolower((string) ($item['voter_phone'] ?? '')), $s)
                    || str_contains(strtolower((string) $item['candidate_name']), $s)
                    || str_contains(strtolower((string) ($item['voter_city'] ?? '')), $s)
                    || str_contains(strtolower((string) ($item['voter_company'] ?? '')), $s)
                    || str_contains(strtolower((string) $item['vote_reference']), $s);
            });
            $filtered = array_values($filtered);
        }

        // Sort descending by timestamp
        usort($filtered, fn ($a, $b) => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));

        $total = count($filtered);
        $lastPage = (int) ceil(max($total, 1) / $perPage);
        $paginated = array_slice($filtered, ($page - 1) * $perPage, $perPage);

        return [
            'data' => $paginated,
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
        $fileName = 'vote_audit_'.Str::slug($campaign->name).'_'.date('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($campaignId): void {
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

            $detailed = $this->getDetailedVotes($campaignId, null, null, 1, 10000);
            foreach ($detailed['data'] as $v) {
                fputcsv($handle, [
                    $v['vote_reference'] ?? '',
                    $v['candidate_name'] ?? '',
                    $v['candidate_email'] ?? '',
                    $v['voter_name'] ?? '',
                    $v['voter_email'] ?? '',
                    $v['voter_phone'] ?? '',
                    $v['voter_city'] ?? '',
                    $v['voter_company'] ?? '',
                    $v['voter_designation'] ?? '',
                    $v['support_reason'] ?? '',
                    $v['channel'] ?? 'Website',
                    $v['created_at_human'] ?? '',
                ]);
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
