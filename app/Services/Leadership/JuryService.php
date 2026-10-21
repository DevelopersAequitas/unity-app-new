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
    public function listEligibleJurors(array $filters = [], int $perPage = 250): LengthAwarePaginator
    {
        $query = User::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'display_name',
                'email',
                'phone',
                'company_name',
                'designation',
                'business_category_id',
                'main_business_category_id',
                'business_sub_category',
            ])
            ->with(['mainBusinessCategory', 'businessCategory']);

        if (! empty($filters['search'])) {
            $term = '%'.strtolower(trim((string) $filters['search'])).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(display_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(company_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(business_sub_category) LIKE ?', [$term]);
            });
        }

        $query->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('display_name', 'asc');

        $paginated = $query->paginate($perPage);

        $paginated->getCollection()->transform(function ($u) {
            $catName = $u->mainBusinessCategory?->name ?: ($u->businessCategory?->name ?: ($u->business_sub_category ?: 'Industry Peer'));
            $compName = $u->company_name ?: ($u->designation ? "{$u->designation} at Enterprise" : 'Enterprise Services');

            $u->unsetRelation('businessCategory');
            $u->unsetRelation('mainBusinessCategory');

            $u->setAttribute('business_name', $compName);
            $u->setAttribute('business_category', $catName);
            $u->setAttribute('business_category_name', $catName);

            return $u;
        });

        return $paginated;
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
        $nomination = LeadershipNomination::with('campaign.role')->findOrFail($nominationId);

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
                        'assigned_by' => ($assignedBy && User::where('id', $assignedBy)->exists()) ? (string) $assignedBy : null,
                    ]
                );

                $created->push($assignment);

                // Send email notification to the assigned juror
                $this->sendJurorAppointmentEmail($user, $nomination, $dueAt);
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
     * Send email to appointed juror with portal login link.
     */
    protected function sendJurorAppointmentEmail(User $juror, LeadershipNomination $nomination, ?string $dueAt = null): void
    {
        if (empty($juror->email)) {
            return;
        }

        try {
            $jurorName = $juror->display_name ?: trim(($juror->first_name ?? '').' '.($juror->last_name ?? '')) ?: 'Jury Member';
            $campaignName = $nomination->campaign?->name ?? 'Leadership Selection Campaign';
            $candidateName = $nomination->candidate_name ?? $nomination->full_name;
            $appNumber = $nomination->application_number;
            $roleName = $nomination->campaign?->role?->name ?? 'Leadership Role';
            $deadline = $dueAt ? Carbon::parse($dueAt)->format('d M Y, h:i A') : '7 Days from Assignment';
            $portalUrl = 'https://peersunity.com/admin/web/jury/login';

            \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($juror, $jurorName, $campaignName, $candidateName, $appNumber, $roleName, $deadline, $portalUrl): void {
                $html = "
                <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 620px; margin: 0 auto; padding: 32px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff; color: #1e293b;'>
                    <div style='text-align: center; margin-bottom: 28px; padding-bottom: 20px; border-bottom: 1px solid #f1f5f9;'>
                        <div style='display: inline-block; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #ffffff; font-size: 13px; font-weight: 700; padding: 6px 14px; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;'>Official Appointment</div>
                        <h1 style='color: #0f172a; font-size: 22px; font-weight: 800; margin: 0 0 6px 0;'>Jury Evaluation Panel</h1>
                        <p style='color: #64748b; font-size: 14px; margin: 0;'>Peers Global Leadership Selection 2026</p>
                    </div>

                    <p style='font-size: 15px; line-height: 1.6; margin-bottom: 16px;'>Dear <strong>{$jurorName}</strong>,</p>

                    <p style='font-size: 15px; line-height: 1.6; color: #334155; margin-bottom: 20px;'>
                        You have been officially appointed as a distinguished <strong>Jury Member</strong> for the campaign: <strong>{$campaignName}</strong>. You have been assigned to evaluate and score the candidacy of <strong>{$candidateName}</strong> for the position of <strong>{$roleName}</strong>.
                    </p>

                    <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 24px 0;'>
                        <div style='font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;'>Assignment Details</div>
                        <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b; width: 140px;'>Campaign:</td>
                                <td style='padding: 6px 0; color: #0f172a; font-weight: 600;'>{$campaignName}</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'>Candidate:</td>
                                <td style='padding: 6px 0; color: #0f172a; font-weight: 600;'>{$candidateName} ({$appNumber})</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'>Applied Role:</td>
                                <td style='padding: 6px 0; color: #0f172a; font-weight: 600;'>{$roleName}</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748b;'>Evaluation Due:</td>
                                <td style='padding: 6px 0; color: #dc2626; font-weight: 700;'>{$deadline}</td>
                            </tr>
                        </table>
                    </div>

                    <div style='background: linear-gradient(135deg, #eff6ff 0%, #eef2ff 100%); border: 1px solid #c7d2fe; border-radius: 12px; padding: 24px; margin: 28px 0; text-align: center;'>
                        <h3 style='margin: 0 0 10px 0; color: #312e81; font-size: 17px; font-weight: 700;'>Access Your Jury Dashboard</h3>
                        <p style='color: #475569; font-size: 14px; margin-bottom: 20px; line-height: 1.5;'>
                            Please log into the Jury Portal using your registered email (<strong>{$juror->email}</strong>) and a one-time password (OTP) sent directly to your inbox.
                        </p>
                        <a href='{$portalUrl}' target='_blank' style='display: inline-block; background: linear-gradient(135deg, #4f46e5, #6366f1); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 700; font-size: 15px; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);'>
                            Open Jury Evaluation Portal &rarr;
                        </a>
                        <p style='margin-top: 14px; font-size: 12px; color: #64748b;'>
                            Or access via: <a href='{$portalUrl}' style='color: #4f46e5; word-break: break-all;'>{$portalUrl}</a>
                        </p>
                    </div>

                    <p style='font-size: 13px; color: #64748b; line-height: 1.5;'>
                        In your dashboard, you will find complete Form 1 credentials, verified achievements, and Form 2 operational dossiers to record your scores, strengths, and recommendations.
                    </p>

                    <div style='border-top: 1px solid #f1f5f9; margin-top: 28px; padding-top: 20px; font-size: 12px; color: #94a3b8; text-align: center;'>
                        Peers Global Leadership Selection & Governance Committee<br/>
                        Automated notification from Peers Unity Platform.
                    </div>
                </div>
                ";

                $message->to($juror->email)
                    ->subject("Appointment as Jury Member - {$campaignName}")
                    ->html($html);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to send juror appointment email to {$juror->email}: ".$e->getMessage());
        }
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
