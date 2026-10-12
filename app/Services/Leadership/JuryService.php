<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\Leadership\LeadershipFormTemplate;
use App\Models\Leadership\LeadershipJuryAssignment;
use App\Models\Leadership\LeadershipJuryEvaluationCriterion;
use App\Models\Leadership\LeadershipJuryFormAnswer;
use App\Models\Leadership\LeadershipJuryFormSubmission;
use App\Models\Leadership\LeadershipJuryReport;
use App\Models\Leadership\LeadershipJuryScore;
use App\Models\Leadership\LeadershipNomination;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class JuryService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List eligible jury members (H1).
     *
     * @param  array<string, mixed>  $filters
     */
    public function listEligibleJurors(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = User::query();

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(fn ($q) => $q->where('display_name', 'ilike', $term)
                ->orWhere('email', 'ilike', $term)
                ->orWhere('first_name', 'ilike', $term)
                ->orWhere('last_name', 'ilike', $term));
        }

        return $query->paginate($perPage);
    }

    /**
     * Assign jurors to candidate (H2).
     *
     * @param  array<string>  $jurorUserIds
     * @return array{assignments_created: int, assignments: Collection<int, LeadershipJuryAssignment>}
     */
    public function assignJurors(
        string $nominationId,
        array $jurorUserIds,
        ?string $dueAt = null,
        string $remarks = '',
        ?string $assignedBy = null
    ): array {
        $nomination = LeadershipNomination::findOrFail($nominationId);

        return DB::transaction(function () use ($nomination, $jurorUserIds, $dueAt, $remarks, $assignedBy): array {
            $created = new Collection;

            foreach ($jurorUserIds as $jurorId) {
                // Check if user exists
                $user = User::findOrFail($jurorId);

                // Prevent candidate self-assignment
                if ($nomination->user_id && $nomination->user_id === $user->id) {
                    continue;
                }

                $assignment = LeadershipJuryAssignment::firstOrCreate(
                    [
                        'nomination_id' => $nomination->id,
                        'juror_user_id' => $user->id,
                    ],
                    [
                        'status' => 'assigned',
                        'due_at' => $dueAt ? Carbon::parse($dueAt) : null,
                        'assignment_remarks' => $remarks,
                        'assigned_by' => ($assignedBy && \App\Models\User::where('id', $assignedBy)->exists()) ? (string) $assignedBy : null,
                    ]
                );

                $created->push($assignment);
            }

            $this->auditService->log(
                action: 'jury.assigned',
                entityType: 'LeadershipNomination',
                entityId: $nomination->id,
                campaignId: $nomination->campaign_id,
                remarks: "Assigned {$created->count()} jurors to nomination {$nomination->application_number}"
            );

            return [
                'assignments_created' => $created->count(),
                'assignments' => $created->load('juror'),
            ];
        });
    }

    /**
     * View all jury assignments for a candidate (H3).
     *
     * @return Collection<int, LeadershipJuryAssignment>
     */
    public function getAssignmentsForCandidate(string $nominationId): Collection
    {
        return LeadershipJuryAssignment::query()
            ->with(['juror', 'report', 'scores.criterion'])
            ->where('nomination_id', $nominationId)
            ->get();
    }

    /**
     * Update assignment details (H4).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAssignment(string $assignmentId, array $data): LeadershipJuryAssignment
    {
        /** @var LeadershipJuryAssignment $assignment */
        $assignment = LeadershipJuryAssignment::findOrFail($assignmentId);
        $before = $assignment->toArray();

        $assignment->update($data);

        $this->auditService->log(
            action: 'jury.assignment.updated',
            entityType: 'LeadershipJuryAssignment',
            entityId: $assignment->id,
            campaignId: $assignment->nomination->campaign_id,
            beforeData: $before,
            afterData: $assignment->fresh()->toArray()
        );

        return $assignment->fresh(['juror']);
    }

    /**
     * Send jury invitation (H5).
     *
     * @return array{assignment_id: string, notification_status: string}
     */
    public function sendInvitation(string $assignmentId, bool $sendEmail, bool $sendWhatsapp, string $templateKey): array
    {
        /** @var LeadershipJuryAssignment $assignment */
        $assignment = LeadershipJuryAssignment::with('juror')->findOrFail($assignmentId);

        $assignment->update([
            'status' => 'invited',
            'invitation_sent_at' => Carbon::now(),
        ]);

        return [
            'assignment_id' => $assignment->id,
            'notification_status' => 'queued',
        ];
    }

    /**
     * Juror dashboard assignments list (H6).
     */
    public function getJurorAssignments(string $jurorUserId, ?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = LeadershipJuryAssignment::query()
            ->with(['nomination.campaign.role', 'nomination.scope'])
            ->where('juror_user_id', $jurorUserId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('due_at', 'asc')->paginate($perPage);
    }

    /**
     * View assigned candidate details for juror (H7).
     */
    public function getJurorAssignmentDetails(string $assignmentId, string $jurorUserId): LeadershipJuryAssignment
    {
        /** @var LeadershipJuryAssignment $assignment */
        $assignment = LeadershipJuryAssignment::query()
            ->with([
                'nomination.campaign.role',
                'nomination.scope',
                'nomination.answers.question',
                'nomination.documents',
                'report',
                'scores',
            ])
            ->where('juror_user_id', $jurorUserId)
            ->findOrFail($assignmentId);

        return $assignment;
    }

    /**
     * Declare conflict of interest (H8).
     */
    public function declareConflict(string $assignmentId, string $jurorUserId, bool $hasConflict, ?string $details = null): LeadershipJuryAssignment
    {
        /** @var LeadershipJuryAssignment $assignment */
        $assignment = LeadershipJuryAssignment::where('juror_user_id', $jurorUserId)->findOrFail($assignmentId);

        $assignment->update([
            'conflict_declared' => $hasConflict,
            'conflict_details' => $details,
            'status' => $hasConflict ? 'declined' : $assignment->status,
        ]);

        $this->auditService->log(
            action: 'jury.conflict_declared',
            entityType: 'LeadershipJuryAssignment',
            entityId: $assignment->id,
            campaignId: $assignment->nomination->campaign_id,
            remarks: $hasConflict ? "Conflict declared: {$details}" : 'No conflict declared'
        );

        return $assignment->fresh();
    }

    /**
     * Get candidate's Form 2 for evaluation (I1).
     */
    public function getEvaluationForm(string $assignmentId, string $jurorUserId): array
    {
        $assignment = LeadershipJuryAssignment::with('nomination')->where('juror_user_id', $jurorUserId)->findOrFail($assignmentId);

        $formTemplate = LeadershipFormTemplate::query()
            ->with(['sections.questions.options'])
            ->where('campaign_id', $assignment->nomination->campaign_id)
            ->where('form_type', 'jury_evaluation')
            ->where('status', 'published')
            ->first();

        $submission = LeadershipJuryFormSubmission::with('answers')
            ->where('nomination_id', $assignment->nomination_id)
            ->first();

        return [
            'assignment' => $assignment,
            'form_template' => $formTemplate,
            'submission' => $submission,
        ];
    }

    /**
     * Save evaluation draft (I2 & I3).
     *
     * @param  array<string, mixed>  $answers
     */
    public function saveEvaluationDraft(string $assignmentId, string $jurorUserId, array $answers): LeadershipJuryFormSubmission
    {
        $assignment = LeadershipJuryAssignment::with('nomination')->where('juror_user_id', $jurorUserId)->findOrFail($assignmentId);

        $template = LeadershipFormTemplate::where('campaign_id', $assignment->nomination->campaign_id)
            ->where('form_type', 'jury_evaluation')
            ->where('status', 'published')
            ->first();

        return DB::transaction(function () use ($assignment, $template, $answers, $jurorUserId): LeadershipJuryFormSubmission {
            /** @var LeadershipJuryFormSubmission $submission */
            $submission = LeadershipJuryFormSubmission::firstOrCreate(
                ['nomination_id' => $assignment->nomination_id, 'version' => 1],
                [
                    'form_template_id' => $template?->id,
                    'submitted_by' => $jurorUserId,
                    'status' => 'draft',
                ]
            );

            foreach ($answers as $key => $val) {
                LeadershipJuryFormAnswer::updateOrCreate(
                    ['submission_id' => $submission->id, 'question_key' => $key],
                    ['answer' => is_array($val) ? $val : ['value' => $val]]
                );
            }

            return $submission->load('answers');
        });
    }

    /**
     * Submit completed Form 2 (I4).
     */
    public function submitEvaluationForm(string $assignmentId, string $jurorUserId): LeadershipJuryFormSubmission
    {
        $assignment = LeadershipJuryAssignment::with('nomination')->where('juror_user_id', $jurorUserId)->findOrFail($assignmentId);

        /** @var LeadershipJuryFormSubmission $submission */
        $submission = LeadershipJuryFormSubmission::where('nomination_id', $assignment->nomination_id)->firstOrFail();

        $submission->update([
            'status' => 'submitted',
            'submitted_at' => Carbon::now(),
        ]);

        return $submission;
    }

    /**
     * Get scoring criteria for campaign (I5).
     *
     * @return Collection<int, LeadershipJuryEvaluationCriterion>
     */
    public function getScoringCriteria(string $assignmentId, string $jurorUserId): Collection
    {
        $assignment = LeadershipJuryAssignment::with('nomination')->where('juror_user_id', $jurorUserId)->findOrFail($assignmentId);

        return LeadershipJuryEvaluationCriterion::query()
            ->where('campaign_id', $assignment->nomination->campaign_id)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();
    }

    /**
     * Save scores (I6).
     *
     * @param  array<int, array{criterion_id: string, score: float, remarks?: ?string}>  $scores
     * @return Collection<int, LeadershipJuryScore>
     */
    public function saveScores(string $assignmentId, string $jurorUserId, array $scores): Collection
    {
        $assignment = LeadershipJuryAssignment::where('juror_user_id', $jurorUserId)->findOrFail($assignmentId);

        if ($assignment->conflict_declared) {
            throw new RuntimeException('Cannot score a candidate after declaring a conflict of interest.');
        }

        return DB::transaction(function () use ($assignment, $scores): Collection {
            $saved = new Collection;

            foreach ($scores as $s) {
                $criterion = LeadershipJuryEvaluationCriterion::findOrFail($s['criterion_id']);

                if ($s['score'] < 0 || $s['score'] > $criterion->max_score) {
                    throw new RuntimeException("Score for '{$criterion->name}' must be between 0 and {$criterion->max_score}.");
                }

                $record = LeadershipJuryScore::updateOrCreate(
                    [
                        'assignment_id' => $assignment->id,
                        'criterion_id' => $criterion->id,
                    ],
                    [
                        'score' => $s['score'],
                        'remarks' => $s['remarks'] ?? null,
                        'scored_at' => Carbon::now(),
                    ]
                );

                $saved->push($record);
            }

            return $saved;
        });
    }

    /**
     * Submit final jury report (I7).
     *
     * @param  array<string, mixed>  $reportData
     */
    public function submitFinalReport(string $assignmentId, string $jurorUserId, array $reportData): LeadershipJuryReport
    {
        $assignment = LeadershipJuryAssignment::where('juror_user_id', $jurorUserId)->findOrFail($assignmentId);

        if ($assignment->conflict_declared) {
            throw new RuntimeException('Cannot submit report after declaring a conflict of interest.');
        }

        if (empty($reportData['strengths']) || empty($reportData['concerns'])) {
            throw new RuntimeException('Strengths and concerns are mandatory.');
        }

        return DB::transaction(function () use ($assignment, $reportData): LeadershipJuryReport {
            /** @var LeadershipJuryReport $report */
            $report = LeadershipJuryReport::updateOrCreate(
                ['assignment_id' => $assignment->id],
                [
                    'overall_recommendation' => $reportData['overall_recommendation'],
                    'strengths' => $reportData['strengths'],
                    'concerns' => $reportData['concerns'],
                    'verification_summary' => $reportData['verification_summary'] ?? null,
                    'final_remarks' => $reportData['final_remarks'] ?? null,
                    'conflict_of_interest' => $reportData['conflict_of_interest'] ?? false,
                    'conflict_details' => $reportData['conflict_details'] ?? null,
                    'submitted_at' => Carbon::now(),
                ]
            );

            $assignment->update([
                'status' => 'completed',
                'completed_at' => Carbon::now(),
            ]);

            return $report;
        });
    }

    /**
     * Admin view of all jury evaluations for a candidate (I8).
     *
     * @return array<string, mixed>
     */
    public function getAdminJurySummary(string $nominationId): array
    {
        $nomination = LeadershipNomination::findOrFail($nominationId);

        $assignments = LeadershipJuryAssignment::query()
            ->with(['juror', 'report', 'scores.criterion'])
            ->where('nomination_id', $nomination->id)
            ->get();

        $totalAssigned = $assignments->count();
        $completed = $assignments->where('status', 'completed')->count();
        $conflicted = $assignments->where('conflict_declared', true)->count();
        $pending = $totalAssigned - $completed;

        // Compute aggregate weighted score
        $totalWeightedScore = 0.0;
        $totalWeight = 0.0;

        foreach ($assignments as $asgn) {
            foreach ($asgn->scores as $sc) {
                if ($sc->criterion) {
                    $totalWeightedScore += ($sc->score * $sc->criterion->weight);
                    $totalWeight += $sc->criterion->weight;
                }
            }
        }

        $avgWeightedScore = $totalWeight > 0 ? round($totalWeightedScore / $totalWeight, 2) : 0.0;

        return [
            'nomination_id' => $nomination->id,
            'candidate_name' => $nomination->full_name,
            'total_assigned' => $totalAssigned,
            'completed' => $completed,
            'pending' => $pending,
            'conflicted' => $conflicted,
            'average_weighted_score' => $avgWeightedScore,
            'assignments' => $assignments,
        ];
    }
}
