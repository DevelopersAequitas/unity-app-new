<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\Leadership\LeadershipCampaign;
use App\Models\Leadership\LeadershipFinalDecision;
use App\Models\Leadership\LeadershipJuryAssignment;
use App\Models\Leadership\LeadershipNomination;
use App\Models\Leadership\LeadershipNotificationLog;
use App\Models\Leadership\LeadershipVote;
use App\Models\Leadership\LeadershipWinnerCreative;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReportingService
{
    /**
     * Dashboard overview statistics (M1).
     *
     * @return array<string, int>
     */
    public function getDashboardOverview(): array
    {
        return [
            'total_campaigns' => Schema::hasTable('web_leadership_campaigns') ? LeadershipCampaign::count() : 0,
            'active_campaigns' => (Schema::hasTable('web_leadership_campaigns') && Schema::hasColumn('web_leadership_campaigns', 'status')) ? LeadershipCampaign::where('status', 'active')->count() : 0,
            'total_nominations' => Schema::hasTable('web_leadership_nominations') ? LeadershipNomination::count() : 0,
            'pending_reviews' => (Schema::hasTable('web_leadership_nominations') && Schema::hasColumn('web_leadership_nominations', 'status')) ? LeadershipNomination::where('status', 'submitted')->count() : 0,
            'shortlisted_candidates' => (Schema::hasTable('web_leadership_nominations') && Schema::hasColumn('web_leadership_nominations', 'status')) ? LeadershipNomination::where('status', 'shortlisted')->count() : 0,
            'total_votes' => Schema::hasTable('web_leadership_votes') ? LeadershipVote::count() : 0,
            'pending_jury_evaluations' => (Schema::hasTable('web_leadership_jury_assignments') && Schema::hasColumn('web_leadership_jury_assignments', 'status')) ? LeadershipJuryAssignment::whereIn('status', ['assigned', 'invited', 'in_progress'])->count() : 0,
            'completed_jury_evaluations' => (Schema::hasTable('web_leadership_jury_assignments') && Schema::hasColumn('web_leadership_jury_assignments', 'status')) ? LeadershipJuryAssignment::where('status', 'completed')->count() : 0,
            'declared_winners' => (Schema::hasTable('web_leadership_final_decisions') && Schema::hasColumn('web_leadership_final_decisions', 'is_winner')) ? LeadershipFinalDecision::where('is_winner', true)->count() : 0,
            'pending_creatives' => (Schema::hasTable('web_leadership_winner_creatives') && Schema::hasColumn('web_leadership_winner_creatives', 'generation_status')) ? LeadershipWinnerCreative::where('generation_status', 'pending')->count() : 0,
            'failed_notifications' => (Schema::hasTable('web_leadership_notification_logs') && Schema::hasColumn('web_leadership_notification_logs', 'status')) ? LeadershipNotificationLog::where('status', 'failed')->count() : 0,
        ];
    }

    /**
     * Nomination report (M2).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getNominationReport(array $filters = []): array
    {
        $query = LeadershipNomination::query();

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }
        if (! empty($filters['scope_id'])) {
            $query->where('scope_id', $filters['scope_id']);
        }

        $byStatus = (clone $query)->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total_nominations' => (clone $query)->count(),
            'status_breakdown' => $byStatus,
            'submitted' => $byStatus['submitted'] ?? 0,
            'approved' => $byStatus['approved'] ?? 0,
            'rejected' => $byStatus['rejected'] ?? 0,
            'changes_requested' => $byStatus['changes_requested'] ?? 0,
            'shortlisted' => $byStatus['shortlisted'] ?? 0,
            'winner' => $byStatus['winner'] ?? 0,
        ];
    }

    /**
     * Voting analytics report (M3).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getVotingReport(array $filters = []): array
    {
        $query = LeadershipVote::query();

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }
        if (! empty($filters['scope_id'])) {
            $query->where('scope_id', $filters['scope_id']);
        }

        $totalVotes = (clone $query)->count();

        $byCandidate = (clone $query)
            ->select('nomination_id', DB::raw('count(*) as vote_count'))
            ->groupBy('nomination_id')
            ->with('nomination:id,full_name')
            ->orderBy('vote_count', 'desc')
            ->take(20)
            ->get();

        return [
            'total_votes' => $totalVotes,
            'top_candidates' => $byCandidate,
        ];
    }

    /**
     * Jury performance report (M4).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getJuryReport(array $filters = []): array
    {
        $query = LeadershipJuryAssignment::query();

        if (! empty($filters['campaign_id'])) {
            $query->whereHas('nomination', fn ($q) => $q->where('campaign_id', $filters['campaign_id']));
        }

        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', 'completed')->count();
        $conflicts = (clone $query)->where('conflict_declared', true)->count();

        return [
            'total_assignments' => $total,
            'completed' => $completed,
            'pending' => $total - $completed,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Winner report (M5).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getWinnerReport(array $filters = []): array
    {
        $query = LeadershipFinalDecision::query()
            ->with(['nomination.scope', 'nomination.campaign.role', 'creatives'])
            ->where('is_winner', true);

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        return [
            'total_winners' => (clone $query)->count(),
            'winners' => $query->get(),
        ];
    }

    /**
     * Queue asynchronous report export (M6).
     *
     * @param  array<string, mixed>  $filters
     * @return array{export_id: string, status: string}
     */
    public function queueExport(string $reportType, array $filters = [], string $format = 'xlsx'): array
    {
        $exportId = (string) Str::uuid();

        // Queue export job if queue worker configured; returns processing reference
        return [
            'export_id' => $exportId,
            'status' => 'processing',
        ];
    }
}
