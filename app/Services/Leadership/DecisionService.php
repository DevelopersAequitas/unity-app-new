<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\Leadership\LeadershipCampaign;
use App\Models\Leadership\LeadershipFinalDecision;
use App\Models\Leadership\LeadershipNomination;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DecisionService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get candidates eligible for final decision (J1).
     *
     * @return Collection<int, LeadershipNomination>
     */
    public function getEligibleDecisionCandidates(string $campaignId): Collection
    {
        return LeadershipNomination::query()
            ->with(['scope', 'campaign.role', 'finalDecision'])
            ->withCount(['votes', 'juryAssignments', 'juryAssignments as jury_completed_count' => fn ($q) => $q->where('status', 'completed')])
            ->where('campaign_id', $campaignId)
            ->whereIn('status', ['shortlisted', 'approved', 'winner', 'not_selected'])
            ->get();
    }

    /**
     * Consolidated voting and jury summary (J2).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getConsolidatedDecisionSummary(string $campaignId): array
    {
        $candidates = LeadershipNomination::query()
            ->with(['scope', 'campaign.role', 'juryAssignments.report', 'juryAssignments.scores.criterion', 'documents'])
            ->withCount(['votes'])
            ->where('campaign_id', $campaignId)
            ->whereIn('status', ['shortlisted', 'approved', 'winner', 'not_selected'])
            ->get();

        $totalCampaignVotes = (int) DB::table('leadership_votes')->where('campaign_id', $campaignId)->count();

        $summary = [];
        foreach ($candidates as $cand) {
            $votesCount = (int) $cand->votes_count;
            $percentage = $totalCampaignVotes > 0 ? round(($votesCount / $totalCampaignVotes) * 100, 2) : 0.0;

            $assigned = $cand->juryAssignments->count();
            $completed = $cand->juryAssignments->where('status', 'completed')->count();

            $totalWeighted = 0.0;
            $totalWeight = 0.0;

            $recs = [
                'strongly_recommend' => 0,
                'recommend' => 0,
                'recommend_with_reservations' => 0,
                'do_not_recommend' => 0,
                'abstain' => 0,
            ];

            foreach ($cand->juryAssignments as $asgn) {
                if ($asgn->report) {
                    $recKey = $asgn->report->overall_recommendation;
                    if (isset($recs[$recKey])) {
                        $recs[$recKey]++;
                    }
                }
                foreach ($asgn->scores as $sc) {
                    if ($sc->criterion) {
                        $totalWeighted += ($sc->score * $sc->criterion->weight);
                        $totalWeight += $sc->criterion->weight;
                    }
                }
            }

            $avgScore = $totalWeight > 0 ? round($totalWeighted / $totalWeight, 2) : 0.0;

            $docsVerified = $cand->documents->where('verification_status', 'verified')->count();
            $docsPending = $cand->documents->where('verification_status', 'pending')->count();
            $docsRejected = $cand->documents->where('verification_status', 'rejected')->count();

            $summary[] = [
                'nomination_id' => $cand->id,
                'candidate_name' => $cand->full_name,
                'role_name' => $cand->campaign?->role?->name,
                'scope_name' => $cand->scope?->scope_name,
                'voting' => [
                    'total_votes' => $votesCount,
                    'percentage' => $percentage,
                ],
                'jury' => [
                    'assigned' => $assigned,
                    'completed' => $completed,
                    'average_weighted_score' => $avgScore,
                    'recommendations' => $recs,
                ],
                'documents' => [
                    'verified' => $docsVerified,
                    'pending' => $docsPending,
                    'rejected' => $docsRejected,
                ],
            ];
        }

        return $summary;
    }

    /**
     * Record final decisions (J3).
     *
     * @param  array<int, array{nomination_id: string, decision: string, is_winner: bool, decision_reason: string, internal_remarks?: ?string}>  $decisions
     * @return Collection<int, LeadershipFinalDecision>
     */
    public function recordFinalDecisions(string $campaignId, array $decisions, ?string $userId = null): Collection
    {
        $campaign = LeadershipCampaign::findOrFail($campaignId);

        return DB::transaction(function () use ($campaign, $decisions, $userId): Collection {
            $created = new Collection;
            $winnerCount = 0;

            foreach ($decisions as $item) {
                $nomination = LeadershipNomination::where('campaign_id', $campaign->id)
                    ->findOrFail($item['nomination_id']);

                $isWinner = (bool) ($item['is_winner'] ?? false);
                if ($isWinner) {
                    $winnerCount++;
                }

                if ($isWinner && ! $campaign->allow_multiple_winners && $winnerCount > 1) {
                    throw new RuntimeException('This campaign does not allow multiple winners.');
                }

                /** @var LeadershipFinalDecision $decision */
                $decision = LeadershipFinalDecision::updateOrCreate(
                    [
                        'campaign_id' => $campaign->id,
                        'nomination_id' => $nomination->id,
                    ],
                    [
                        'decision' => $item['decision'],
                        'is_winner' => $isWinner,
                        'decision_reason' => $item['decision_reason'],
                        'internal_remarks' => $item['internal_remarks'] ?? null,
                        'decided_by' => ($userId && \App\Models\User::where('id', $userId)->exists()) ? (string) $userId : null,
                        'decided_at' => Carbon::now(),
                        'publication_status' => 'pending',
                    ]
                );

                // Update nomination status accordingly
                $newNominationStatus = $isWinner ? 'winner' : ($item['decision'] === 'selected' ? 'winner' : 'not_selected');
                $nomination->update(['status' => $newNominationStatus]);

                $this->auditService->log(
                    action: 'decision.recorded',
                    entityType: 'LeadershipFinalDecision',
                    entityId: $decision->id,
                    campaignId: $campaign->id,
                    afterData: $decision->toArray(),
                    remarks: "Decision for {$nomination->full_name}: {$item['decision']}"
                );

                $created->push($decision);
            }

            return $created;
        });
    }

    /**
     * Update an unpublished decision (J4).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDecision(string $decisionId, array $data, ?string $userId = null): LeadershipFinalDecision
    {
        /** @var LeadershipFinalDecision $decision */
        $decision = LeadershipFinalDecision::findOrFail($decisionId);

        if ($decision->publication_status === 'published') {
            throw new RuntimeException('Published decisions cannot be edited. Please unpublish first.');
        }

        $before = $decision->toArray();
        $decision->update($data);

        $this->auditService->log(
            action: 'decision.updated',
            entityType: 'LeadershipFinalDecision',
            entityId: $decision->id,
            campaignId: $decision->campaign_id,
            beforeData: $before,
            afterData: $decision->fresh()->toArray()
        );

        return $decision->fresh();
    }

    /**
     * List all final decisions for campaign (J5).
     *
     * @param  array<string, mixed>  $filters
     */
    public function listDecisions(string $campaignId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = LeadershipFinalDecision::query()
            ->with(['nomination.scope', 'nomination.campaign.role', 'decider'])
            ->where('campaign_id', $campaignId);

        if (! empty($filters['decision'])) {
            $query->where('decision', $filters['decision']);
        }

        if (isset($filters['is_winner'])) {
            $query->where('is_winner', filter_var($filters['is_winner'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['publication_status'])) {
            $query->where('publication_status', $filters['publication_status']);
        }

        return $query->orderBy('decided_at', 'desc')->paginate($perPage);
    }

    /**
     * Publish winner decision (J6).
     */
    public function publishWinner(string $decisionId, string $remarks = '', ?string $userId = null): LeadershipFinalDecision
    {
        /** @var LeadershipFinalDecision $decision */
        $decision = LeadershipFinalDecision::findOrFail($decisionId);

        if (! $decision->is_winner || $decision->decision !== 'selected') {
            throw new RuntimeException('Only selected winner decisions can be published.');
        }

        $before = $decision->toArray();

        $decision->update([
            'publication_status' => 'published',
        ]);

        $this->auditService->log(
            action: 'winner.published',
            entityType: 'LeadershipFinalDecision',
            entityId: $decision->id,
            campaignId: $decision->campaign_id,
            beforeData: $before,
            afterData: $decision->fresh()->toArray(),
            remarks: $remarks ?: 'Winner decision published to public website'
        );

        return $decision->fresh(['nomination']);
    }

    /**
     * Remove winner from public display (J7).
     */
    public function unpublishWinner(string $decisionId, string $remarks = '', ?string $userId = null): LeadershipFinalDecision
    {
        /** @var LeadershipFinalDecision $decision */
        $decision = LeadershipFinalDecision::findOrFail($decisionId);
        $before = $decision->toArray();

        $decision->update([
            'publication_status' => 'unpublished',
        ]);

        $this->auditService->log(
            action: 'winner.unpublished',
            entityType: 'LeadershipFinalDecision',
            entityId: $decision->id,
            campaignId: $decision->campaign_id,
            beforeData: $before,
            afterData: $decision->fresh()->toArray(),
            remarks: $remarks ?: 'Winner decision unpublished'
        );

        return $decision->fresh(['nomination']);
    }

    /**
     * List winner records (J8).
     *
     * @return Collection<int, LeadershipFinalDecision>
     */
    public function listWinners(string $campaignId): Collection
    {
        return LeadershipFinalDecision::query()
            ->with(['nomination.scope', 'nomination.campaign.role', 'creatives'])
            ->where('campaign_id', $campaignId)
            ->where('is_winner', true)
            ->get();
    }
}
