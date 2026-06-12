<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\EntrepreneurCertificationApprovedMail;
use App\Mail\LeadershipCertificationApprovedMail;
use App\Models\EntrepreneurCertificationSubmission;
use App\Models\LeadershipCertificationSubmission;
use App\Services\Certificates\EntrepreneurCertificatePdf;
use App\Services\Certificates\LeadershipCertificatePdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CertificationRequestController extends Controller
{
    public function entrepreneurIndex(Request $request): View
    {
        return $this->index($request, 'entrepreneur');
    }

    public function entrepreneurShow(string $id): View
    {
        return $this->show('entrepreneur', $id);
    }

    public function entrepreneurApprove(string $id): RedirectResponse
    {
        return $this->updateStatus('entrepreneur', $id, 'approved');
    }

    public function entrepreneurReject(string $id): RedirectResponse
    {
        return $this->updateStatus('entrepreneur', $id, 'rejected');
    }

    public function entrepreneurCertificate(string $id)
    {
        $certificationRequest = EntrepreneurCertificationSubmission::query()->findOrFail($id);

        if ((string) $certificationRequest->status !== 'approved') {
            return redirect()
                ->back()
                ->with('error', 'Certificate PDF is available only for approved entrepreneur certification requests.');
        }

        return $this->streamEntrepreneurCertificate($certificationRequest);
    }

    public function leadershipIndex(Request $request): View
    {
        return $this->index($request, 'leadership');
    }

    public function leadershipShow(string $id): View
    {
        return $this->show('leadership', $id);
    }

    public function leadershipCertificate(string $id)
    {
        $certificationRequest = LeadershipCertificationSubmission::query()->findOrFail($id);

        if ((string) $certificationRequest->status !== 'approved') {
            return redirect()
                ->back()
                ->with('error', 'Certificate PDF is available only for approved leadership certification requests.');
        }

        return $this->streamLeadershipCertificate($certificationRequest);
    }

    public function leadershipApprove(string $id): RedirectResponse
    {
        return $this->updateStatus('leadership', $id, 'approved');
    }

    public function leadershipReject(string $id): RedirectResponse
    {
        return $this->updateStatus('leadership', $id, 'rejected');
    }

    private function index(Request $request, string $type): View
    {
        $resource = $this->resourceConfig($type);
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => strtolower(trim((string) $request->query('status', 'all'))),
            'from_date' => trim((string) $request->query('from_date', '')),
            'to_date' => trim((string) $request->query('to_date', '')),
        ];

        /** @var Builder $query */
        $query = $resource['model']::query()->orderByDesc('created_at');
        $this->applyFilters($query, $resource, $filters);

        /** @var LengthAwarePaginator $requests */
        $requests = $query->paginate(25)->appends($request->query());

        $summaryBase = $resource['model']::query();
        $summary = [
            'pending' => (clone $summaryBase)->whereIn('status', ['new', 'pending'])->count(),
            'approved' => (clone $summaryBase)->where('status', 'approved')->count(),
            'rejected' => (clone $summaryBase)->where('status', 'rejected')->count(),
            'total' => (clone $summaryBase)->count(),
        ];

        return view('admin.certification_requests.index', compact('resource', 'requests', 'summary', 'filters'));
    }

    private function show(string $type, string $id): View
    {
        $resource = $this->resourceConfig($type);

        /** @var Model $certificationRequest */
        $certificationRequest = $resource['model']::query()->findOrFail($id);
        $answerEvaluations = $this->buildAnswerEvaluations($certificationRequest, $resource);

        return view('admin.certification_requests.show', compact('resource', 'certificationRequest', 'answerEvaluations'));
    }

    private function buildAnswerEvaluations(Model $certificationRequest, array $resource): array
    {
        $modelClass = $resource['model'];
        $quizFields = defined($modelClass.'::QUIZ_FIELDS') ? $modelClass::QUIZ_FIELDS : [];
        $correctAnswers = defined($modelClass.'::CORRECT_ANSWERS') ? $modelClass::CORRECT_ANSWERS : [];

        return collect($quizFields)
            ->map(function (string $field) use ($certificationRequest, $correctAnswers): array {
                $submittedAnswer = data_get($certificationRequest, $field);
                $hasCorrectAnswer = array_key_exists($field, $correctAnswers);
                $correctAnswer = $hasCorrectAnswer ? $correctAnswers[$field] : null;

                return [
                    'field' => $field,
                    'question_label' => Str::headline($field),
                    'submitted_answer' => $submittedAnswer,
                    'has_correct_answer' => $hasCorrectAnswer,
                    'correct_answer' => $correctAnswer,
                    'is_correct' => $hasCorrectAnswer ? $submittedAnswer === $correctAnswer : null,
                ];
            })
            ->all();
    }

    private function updateStatus(string $type, string $id, string $status): RedirectResponse
    {
        abort_unless(in_array($status, ['approved', 'rejected'], true), 422);

        $resource = $this->resourceConfig($type);

        /** @var Model $certificationRequest */
        $certificationRequest = $resource['model']::query()->findOrFail($id);

        $previousStatus = (string) $certificationRequest->status;

        if ($previousStatus !== 'new') {
            return redirect()
                ->back()
                ->with('error', 'Only new certification requests can be approved or rejected.');
        }

        $certificationRequest->forceFill(['status' => $status])->save();

        if ($status === 'approved' && $previousStatus !== 'approved') {
            $this->sendApprovalCertificateEmail($certificationRequest, $resource);
        }

        return redirect()
            ->route($resource['index_route'])
            ->with('success', $resource['singular_title'].' '.($status === 'approved' ? 'approved' : 'rejected').' successfully.');
    }

    private function streamEntrepreneurCertificate(EntrepreneurCertificationSubmission $certificationRequest)
    {
        $certificatePdf = app(EntrepreneurCertificatePdf::class);
        $filename = $certificatePdf->filename($certificationRequest);

        return response($certificatePdf->generate($certificationRequest), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    private function streamLeadershipCertificate(LeadershipCertificationSubmission $certificationRequest)
    {
        $certificatePdf = app(LeadershipCertificatePdf::class);
        $filename = $certificatePdf->filename($certificationRequest);

        return response($certificatePdf->generate($certificationRequest), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    private function sendApprovalCertificateEmail(Model $certificationRequest, array $resource): void
    {
        $email = trim((string) data_get($certificationRequest, 'email', ''));

        if ($email === '') {
            Log::warning('Certification approval email skipped: missing recipient email', [
                'request_id' => (string) $certificationRequest->getKey(),
                'certification_type' => $resource['title'] ?? null,
            ]);

            return;
        }

        try {
            if ($resource['type'] === 'entrepreneur' && $certificationRequest instanceof EntrepreneurCertificationSubmission) {
                $mailable = new EntrepreneurCertificationApprovedMail($certificationRequest, now());
            } elseif ($resource['type'] === 'leadership' && $certificationRequest instanceof LeadershipCertificationSubmission) {
                $mailable = new LeadershipCertificationApprovedMail($certificationRequest, now());
            } else {
                Log::warning('Certification approval email skipped: unsupported certification type', [
                    'request_id' => (string) $certificationRequest->getKey(),
                    'certification_type' => $resource['title'] ?? null,
                ]);

                return;
            }

            Mail::to($email)->send($mailable);
        } catch (\Throwable $exception) {
            Log::warning('Certification approval email send failed', [
                'request_id' => (string) $certificationRequest->getKey(),
                'recipient' => $email,
                'certification_type' => $resource['title'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function applyFilters(Builder $query, array $resource, array $filters): void
    {
        if ($filters['search'] !== '') {
            $like = '%'.$filters['search'].'%';
            $query->where(function (Builder $builder) use ($resource, $like) {
                foreach ($resource['search_columns'] as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $builder->{$method}($column, 'ILIKE', $like);
                }
            });
        }

        $status = strtolower((string) ($filters['status'] ?? 'all'));

        if (in_array($status, ['new', 'pending', 'pending_new'], true)) {
            $query->whereIn('status', ['new', 'pending']);
        } elseif (in_array($status, ['approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        if ($filters['from_date'] !== '') {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if ($filters['to_date'] !== '') {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }
    }

    private function resourceConfig(string $type): array
    {
        $resources = [
            'entrepreneur' => [
                'type' => 'entrepreneur',
                'title' => 'Entrepreneur Certification Requests',
                'singular_title' => 'Entrepreneur certification request',
                'description' => 'Review and approve entrepreneur certification submissions.',
                'model' => EntrepreneurCertificationSubmission::class,
                'index_route' => 'admin.entrepreneur-certification-requests.index',
                'show_route' => 'admin.entrepreneur-certification-requests.show',
                'approve_route' => 'admin.entrepreneur-certification-requests.approve',
                'reject_route' => 'admin.entrepreneur-certification-requests.reject',
                'certificate_route' => 'admin.entrepreneur-certification-requests.certificate',
                'tier_column' => 'certification_tier',
                'tier_label' => 'Certification Tier',
                'search_columns' => ['full_name', 'business_name', 'email', 'contact_no', 'certification_tier'],
                'detail_columns' => [
                    'id',
                    'full_name',
                    'business_name',
                    'email',
                    'contact_no',
                    'total_score',
                    'percentage',
                    'certification_tier',
                    'status',
                    'notes',
                    'business_start_reason',
                    'business_failure_reaction',
                    'successful_entrepreneur_definition',
                    'business_purpose_frequency',
                    'business_challenge_approach',
                    'finance_tracking_frequency',
                    'pricing_decision_method',
                    'business_systems_status',
                    'unhappy_customer_response',
                    'money_separation_status',
                    'failure_recovery_action',
                    'major_decision_method',
                    'competitor_growth_response',
                    'new_idea_action',
                    'risk_approach',
                    'networking_belief',
                    'conflict_handling',
                    'team_motivation_method',
                    'business_meet_frequency',
                    'community_growth_belief',
                    'five_year_business_vision',
                    'success_meaning',
                    'work_life_balance_method',
                    'society_value_belief',
                    'future_mentorship_belief',
                    'created_at',
                    'updated_at',
                ],
            ],
            'leadership' => [
                'type' => 'leadership',
                'title' => 'Leadership Certification Requests',
                'singular_title' => 'Leadership certification request',
                'description' => 'Review and approve leadership certification submissions.',
                'model' => LeadershipCertificationSubmission::class,
                'index_route' => 'admin.leadership-certification-requests.index',
                'show_route' => 'admin.leadership-certification-requests.show',
                'approve_route' => 'admin.leadership-certification-requests.approve',
                'reject_route' => 'admin.leadership-certification-requests.reject',
                'certificate_route' => 'admin.leadership-certification-requests.certificate',
                'tier_column' => 'certification_level',
                'tier_label' => 'Certification Level',
                'search_columns' => ['full_name', 'business_name', 'email', 'contact_no', 'certification_level'],
                'detail_columns' => [
                    'id',
                    'full_name',
                    'business_name',
                    'email',
                    'contact_no',
                    'total_score',
                    'percentage',
                    'certification_level',
                    'status',
                    'notes',
                    'team_struggling_action',
                    'leader_definition',
                    'junior_challenged_idea',
                    'leader_when_wrong',
                    'team_motivation',
                    'leadership_meaning',
                    'different_background_team_first_step',
                    'group_task_approach',
                    'team_conflict_action',
                    'leader_makes_others_feel',
                    'team_big_achievement_action',
                    'guide_new_entrepreneurs',
                    'local_business_group_thought',
                    'silent_team_meeting_action',
                    'leadership_starts_with',
                    'business_community_approach',
                    'low_confidence_person_action',
                    'support_most_in_team',
                    'good_leadership_means',
                    'feedback_frequency',
                    'unhappy_customer_action',
                    'new_network_person_action',
                    'local_event_speaking_action',
                    'leadership_role_offer_action',
                    'great_leader_opinion',
                    'created_at',
                    'updated_at',
                ],
            ],
        ];

        abort_unless(isset($resources[$type]), 404);

        return $resources[$type];
    }
}
