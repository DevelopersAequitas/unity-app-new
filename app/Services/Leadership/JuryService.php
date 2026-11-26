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
                'profile_photo_file_id',
                'profile_photo_id',
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
            $photoUrl = $u->profile_photo_url;
            $u->setAttribute('profile_photo_url', $photoUrl);
            $u->setAttribute('photo_url', $photoUrl);
            $u->setAttribute('avatar_url', $photoUrl);

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
                        'campaign_id' => $nomination->campaign_id,
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
            $isStanding = ($nomination->application_number === 'PG-STANDING-POOL');
            $jurorName = $juror->display_name ?: trim(($juror->first_name ?? '').' '.($juror->last_name ?? '')) ?: 'Jury Member';
            $campaignName = $nomination->campaign?->name ?? 'Peers Global Leadership Selection';
            $candidateName = $isStanding ? 'All Current & Incoming Candidates' : ($nomination->candidate_name ?? $nomination->full_name);
            $appNumber = $isStanding ? 'Standing Appointment' : $nomination->application_number;
            $roleName = $isStanding ? 'National & Circle Leadership Roles' : ($nomination->campaign?->role?->name ?? 'Leadership Role');
            $deadline = $dueAt ? Carbon::parse($dueAt)->format('d M Y, h:i A') : '7 Days from Assignment';
            $portalUrl = 'https://peersglobal.com/leadership/jury';

            \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($juror, $jurorName, $campaignName, $candidateName, $appNumber, $roleName, $deadline, $portalUrl, $isStanding): void {
                $greetingParagraph = $isStanding
                    ? "You have been officially appointed as a distinguished <strong>Jury Member</strong> for the <strong>{$campaignName}</strong>. You have been appointed to evaluate and score all current and incoming candidate leadership applications submitted through the portal."
                    : "You have been officially appointed as a distinguished <strong>Jury Member</strong> for the <strong>{$campaignName}</strong>. You have been assigned to evaluate and score the candidacy of <strong>{$candidateName}</strong> for the position of <strong>{$roleName}</strong>.";

                $html = "
                <!DOCTYPE html>
                <html lang='en' xmlns='http://www.w3.org/1999/xhtml'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <meta http-equiv='X-UA-Compatible' content='IE=edge'>
                    <title>Appointment as Jury Member - {$campaignName}</title>
                    <style>
                        * { box-sizing: border-box; }
                        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
                        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
                        @media only screen and (max-width: 600px) {
                            .email-wrapper { padding: 12px 6px !important; }
                            .email-container { width: 100% !important; max-width: 100% !important; border-radius: 12px !important; }
                            .email-body { padding: 22px 16px !important; }
                            .email-header { padding: 20px 16px !important; }
                            .mobile-btn { width: 100% !important; display: block !important; padding: 14px 16px !important; text-align: center !important; }
                            .detail-table td { display: block !important; width: 100% !important; padding: 3px 0 !important; }
                            .detail-table td.label-td { padding-top: 8px !important; }
                        }
                    </style>
                </head>
                <body style='margin:0; padding:0; background-color:#0b0f19; font-family:-apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing:antialiased; color:#e2e8f0;'>
                    <table role='presentation' cellpadding='0' cellspacing='0' width='100%' class='email-wrapper' style='width:100%; margin:0; padding:24px 12px; background-color:#0b0f19; border-collapse:collapse;'>
                        <tr>
                            <td align='center'>
                                <table role='presentation' cellpadding='0' cellspacing='0' width='100%' class='email-container' style='max-width:580px; width:100%; margin:0 auto; background-color:#121824; border-radius:16px; overflow:hidden; border:1px solid #1e293b; box-shadow:0 10px 30px rgba(0,0,0,0.5); border-collapse:collapse;'>
                                    <!-- Header -->
                                    <tr>
                                        <td align='center' class='email-header' style='background:linear-gradient(135deg, #1e085a 0%, #2e1065 50%, #4338ca 100%); padding:28px 24px; text-align:center; border-bottom:1px solid rgba(255,255,255,0.1);'>
                                            <div style='display:inline-block; background:rgba(255,255,255,0.15); color:#ffffff; font-size:11.5px; font-weight:700; padding:5px 14px; border-radius:9999px; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:10px;'>Official Appointment</div>
                                            <h1 style='color:#ffffff; font-size:22px; font-weight:800; margin:0 0 6px 0; letter-spacing:-0.02em;'>Jury Evaluation Panel</h1>
                                            <p style='color:#c7d2fe; font-size:13.5px; margin:0;'>{$campaignName}</p>
                                        </td>
                                    </tr>

                                    <!-- Body -->
                                    <tr>
                                        <td class='email-body' style='padding:30px 24px; color:#cbd5e1; font-size:15px; line-height:1.6;'>
                                            <p style='font-size:16px; font-weight:600; color:#f1f5f9; margin-bottom:14px;'>Dear <strong>{$jurorName}</strong>,</p>

                                            <p style='font-size:14.5px; line-height:1.6; color:#cbd5e1; margin-bottom:20px;'>
                                                {$greetingParagraph}
                                            </p>

                                            <!-- Assignment Details Card -->
                                            <div style='background-color:#182234; border:1px solid #283548; border-radius:12px; padding:18px 20px; margin:22px 0;'>
                                                <div style='font-size:12px; font-weight:700; color:#38bdf8; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:12px;'>Assignment Details</div>
                                                <table class='detail-table' role='presentation' cellpadding='0' cellspacing='0' width='100%' style='border-collapse:collapse; font-size:14px;'>
                                                    <tr>
                                                        <td class='label-td' style='padding:5px 0; color:#94a3b8; width:130px;'>Selection Scope:</td>
                                                        <td style='padding:5px 0; color:#ffffff; font-weight:600;'>{$campaignName}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class='label-td' style='padding:5px 0; color:#94a3b8;'>Candidate / Scope:</td>
                                                        <td style='padding:5px 0; color:#ffffff; font-weight:600;'>{$candidateName} ({$appNumber})</td>
                                                    </tr>
                                                    <tr>
                                                        <td class='label-td' style='padding:5px 0; color:#94a3b8;'>Target Roles:</td>
                                                        <td style='padding:5px 0; color:#ffffff; font-weight:600;'>{$roleName}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class='label-td' style='padding:5px 0; color:#94a3b8;'>Evaluation Due:</td>
                                                        <td style='padding:5px 0; color:#f87171; font-weight:700;'>{$deadline}</td>
                                                    </tr>
                                                </table>
                                            </div>

                                            <!-- CTA Access Dashboard -->
                                            <div style='background:linear-gradient(135deg, #1e1b4b 0%, #2e1065 100%); border:1px solid #4338ca; border-radius:12px; padding:22px 20px; margin:24px 0; text-align:center;'>
                                                <h3 style='margin:0 0 8px 0; color:#ffffff; font-size:17px; font-weight:700;'>Access Your Jury Dashboard</h3>
                                                <p style='color:#c7d2fe; font-size:13.5px; margin-bottom:18px; line-height:1.5;'>
                                                    Log into the Jury Portal with your registered email (<strong>{$juror->email}</strong>) and a secure one-time password (OTP).
                                                </p>
                                                <a href='{$portalUrl}' target='_blank' class='mobile-btn' style='display:inline-block; box-sizing:border-box; background:linear-gradient(135deg, #4f46e5, #6366f1); color:#ffffff; text-decoration:none; padding:13px 28px; border-radius:10px; font-weight:700; font-size:14.5px; box-shadow:0 6px 16px rgba(79, 70, 229, 0.4); letter-spacing:0.02em;'>
                                                    Open Jury Evaluation Portal &rarr;
                                                </a>
                                                <p style='margin-top:14px; font-size:12px; color:#94a3b8; word-break:break-all;'>
                                                    Or access via: <a href='{$portalUrl}' style='color:#818cf8;'>{$portalUrl}</a>
                                                </p>
                                            </div>

                                            <p style='font-size:13px; color:#94a3b8; line-height:1.5;'>
                                                In your dashboard, you will find complete Form 1 credentials, verified achievements, and Form 2 operational dossiers to record your scores and recommendations.
                                            </p>

                                            <div style='border-top:1px solid #1e293b; margin-top:24px; padding-top:16px; font-size:11.5px; color:#64748b; text-align:center; line-height:1.5;'>
                                                Peers Global Leadership Selection &amp; Governance Committee<br/>
                                                <span style='color:#475569;'>Automated notification from Peers Unity Platform.</span>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>
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
        $assignment = LeadershipJuryAssignment::with(['juror', 'nomination.campaign.role'])->findOrFail($assignmentId);

        $assignment->update([
            'status' => 'invited',
            'invitation_sent_at' => Carbon::now(),
        ]);

        if ($sendEmail && $assignment->juror && $assignment->nomination) {
            $this->sendJurorAppointmentEmail($assignment->juror, $assignment->nomination, $assignment->due_at ? $assignment->due_at->toDateTimeString() : null);
        }

        return [
            'assignment_id' => $assignment->id,
            'notification_status' => 'sent',
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
            // Strictly normalize recommendation to adhere to Postgres check constraint:
            // ('strongly_recommend', 'recommend', 'recommend_with_reservations', 'do_not_recommend', 'abstain')
            $recVal = strtolower(trim((string) ($reportData['overall_recommendation'] ?? 'recommend')));
            $recommendation = match ($recVal) {
                'strongly_recommend' => 'strongly_recommend',
                'recommend' => 'recommend',
                'recommend_with_reservations', 'neutral', 'reservations', 'borderline' => 'recommend_with_reservations',
                'do_not_recommend', 'reject' => 'do_not_recommend',
                'abstain' => 'abstain',
                default => 'recommend',
            };

            /** @var LeadershipJuryReport $report */
            $report = LeadershipJuryReport::updateOrCreate(
                ['assignment_id' => $assignment->id],
                [
                    'overall_recommendation' => $recommendation,
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
