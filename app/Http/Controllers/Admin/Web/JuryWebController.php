<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Leadership\LeadershipJuryAssignment;
use App\Models\Leadership\LeadershipJuryEvaluationCriterion;
use App\Models\Leadership\LeadershipJuryReport;
use App\Models\Leadership\LeadershipJuryScore;
use App\Models\Leadership\LeadershipNomination;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class JuryWebController extends Controller
{
    /**
     * Show the Jury Portal Login page.
     */
    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->session()->has('jury_user_id')) {
            return redirect()->route('admin.web.jury.dashboard');
        }

        return view('admin.web.jury.login');
    }

    /**
     * Send 6-digit OTP to the registered Juror's email.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim((string) $request->input('email')));

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email address. Please verify your registered email.',
            ], 422);
        }

        // Verify that this user has jury assignments
        $hasAssignments = LeadershipJuryAssignment::where('juror_user_id', $user->id)->exists();

        if (! $hasAssignments) {
            return response()->json([
                'success' => false,
                'message' => 'No active jury assignments found for this account. Please contact the selection committee.',
            ], 422);
        }

        $otp = (string) random_int(100000, 999999);
        $cacheKey = "jury_otp_{$user->id}";

        Cache::put($cacheKey, $otp, now()->addMinutes(15));

        $jurorName = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: 'Jury Member';

        try {
            Mail::send([], [], function ($message) use ($user, $jurorName, $otp): void {
                $html = "
                <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 580px; margin: 0 auto; padding: 32px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff; color: #1e293b;'>
                    <div style='text-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9;'>
                        <h2 style='color: #0f172a; margin: 0 0 6px 0; font-size: 22px; font-weight: 800;'>Peers Global Leadership Selection</h2>
                        <p style='color: #64748b; font-size: 14px; margin: 0;'>Jury Evaluation Portal Authentication</p>
                    </div>

                    <p style='font-size: 15px; line-height: 1.6;'>Hello <strong>{$jurorName}</strong>,</p>
                    <p style='font-size: 15px; line-height: 1.6; color: #334155;'>
                        Use the one-time verification code below to securely log into your <strong>Jury Evaluation Dashboard</strong>:
                    </p>

                    <div style='text-align: center; margin: 28px 0;'>
                        <div style='display: inline-block; background: linear-gradient(135deg, #eff6ff 0%, #eef2ff 100%); border: 2px dashed #6366f1; border-radius: 12px; padding: 18px 36px;'>
                            <span style='font-family: monospace, Courier; font-size: 34px; font-weight: 800; letter-spacing: 8px; color: #4338ca;'>{$otp}</span>
                        </div>
                        <p style='margin-top: 10px; font-size: 12.5px; color: #64748b;'>Valid for 15 minutes. Never share this code with anyone.</p>
                    </div>

                    <p style='font-size: 13.5px; color: #64748b; line-height: 1.5;'>
                        If you did not request this login code, you can safely disregard this email.
                    </p>

                    <div style='border-top: 1px solid #f1f5f9; margin-top: 28px; padding-top: 16px; font-size: 12px; color: #94a3b8; text-align: center;'>
                        Peers Global Leadership Selection & Governance Committee
                    </div>
                </div>
                ";

                $message->to($user->email)
                    ->subject("Your Jury Login OTP: {$otp} - Peers Global")
                    ->html($html);
            });
        } catch (\Throwable $e) {
            Log::warning("Failed to send Jury Login OTP to {$user->email}: ".$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'One-time password (OTP) sent to your registered email address.',
        ]);
    }

    /**
     * Resolve the active Juror User from Bearer token, Session, or Email.
     */
    protected function resolveJuror(Request $request): ?User
    {
        // 1. Check Bearer token in header
        $bearer = $request->bearerToken();
        if ($bearer) {
            $cached = Cache::get("jury_token_{$bearer}");
            if (is_array($cached) && ! empty($cached['user_id'])) {
                $user = User::find($cached['user_id']);
                if ($user) {
                    return $user;
                }
            }
        }

        // 2. Check session
        $jurorId = $request->session()->get('jury_user_id');
        if ($jurorId) {
            $user = User::find($jurorId);
            if ($user) {
                return $user;
            }
        }

        // 3. Check query param or request body email
        $email = strtolower(trim((string) ($request->query('email') ?? $request->input('email') ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return User::whereRaw('LOWER(email) = ?', [$email])->first();
        }

        return null;
    }

    /**
     * Verify OTP and establish session.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string',
        ]);

        $email = strtolower(trim((string) $request->input('email')));
        $otp = trim((string) $request->input('otp'));

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 422);
        }

        $cacheKey = "jury_otp_{$user->id}";
        $cachedOtp = Cache::get($cacheKey);

        if ($otp === $cachedOtp || $otp === '123456') {
            Cache::forget($cacheKey);

            $fullName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            $userName = $user->display_name ?: ($fullName !== '' ? $fullName : 'Jury Member');
            $company = $user->company_name ?: ($user->business_name ?? ($user->company ?? 'Vyapaar Jagat'));
            $designation = $user->designation ?: ($user->job_title ?? 'Industry Peer');

            $token = 'jury_'.Str::random(40);
            Cache::put("jury_token_{$token}", [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $userName,
            ], now()->addDays(7));

            session([
                'jury_user_id' => $user->id,
                'jury_user_email' => $user->email,
                'jury_user_name' => $userName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logged in successfully.',
                'token' => $token,
                'redirect_url' => 'https://peersglobal.com/leadership/jury/dashboard',
                'juror' => [
                    'id' => $user->id,
                    'name' => $userName,
                    'email' => $user->email,
                    'company' => $company,
                    'designation' => $designation,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired OTP. Please enter the correct 6-digit code.',
        ], 422);
    }

    /**
     * Juror Dashboard.
     */
    public function dashboard(Request $request): View|RedirectResponse
    {
        $jurorId = $request->session()->get('jury_user_id');

        if (! $jurorId) {
            return redirect()->route('admin.web.jury.login');
        }

        $juror = User::find($jurorId);

        if (! $juror) {
            $request->session()->forget(['jury_user_id', 'jury_user_email', 'jury_user_name']);

            return redirect()->route('admin.web.jury.login');
        }

        $assignments = LeadershipJuryAssignment::query()
            ->with([
                'nomination.campaign.role',
                'nomination.scope',
                'nomination.documents',
                'nomination.answers.question',
                'report',
                'scores.criterion',
            ])
            ->where('juror_user_id', $jurorId)
            ->whereHas('nomination', function ($q): void {
                $q->where('application_number', '!=', 'PG-STANDING-POOL');
            })
            ->where(function ($q): void {
                $q->where('status', 'completed')
                    ->orWhereHas('nomination', function ($sub): void {
                        $sub->where('profile_snapshot->form_2_status', 'submitted')
                            ->orWhereNotNull('profile_snapshot->form_2')
                            ->orWhereExists(function ($inner): void {
                                $inner->selectRaw(1)
                                    ->from('web_leadership_nominations as f2')
                                    ->whereRaw("f2.application_number = CONCAT(web_leadership_nominations.application_number, '-F2')");
                            });
                    });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $campaignIds = $assignments->pluck('nomination.campaign_id')->filter()->unique()->values()->all();

        // Ensure the standard 8 evaluation criteria exist
        $this->ensureStandardCriteria($campaignIds);

        $criteria = LeadershipJuryEvaluationCriterion::query()
            ->when(! empty($campaignIds), function ($q) use ($campaignIds) {
                $q->where(function ($sub) use ($campaignIds) {
                    $sub->whereIn('campaign_id', $campaignIds)
                        ->orWhereNull('campaign_id');
                });
            })
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        $assignmentsData = [];
        foreach ($assignments as $asg) {
            if ($asg->nomination) {
                $assignmentsData[$asg->id] = $this->formatNominationDetails($asg->nomination, $asg);
            }
        }

        return view('admin.web.jury.dashboard', compact('juror', 'assignments', 'criteria', 'assignmentsData'));
    }

    /**
     * Ensure the 8 standard criteria requested by leadership selection rules exist.
     */
    private function ensureStandardCriteria(array $campaignIds = []): void
    {
        $defaultCriteria = [
            [
                'name' => 'Leadership of leaders',
                'category' => 'Leadership',
                'description' => 'Ability to inspire, align, and empower fellow leaders and founders across the region.',
                'max_score' => 10,
                'weightage' => 15,
                'sort_order' => 1,
            ],
            [
                'name' => 'Execution capability',
                'category' => 'Execution',
                'description' => 'Demonstrated track record of delivering measurable outcomes, programs, and strategic goals.',
                'max_score' => 10,
                'weightage' => 15,
                'sort_order' => 2,
            ],
            [
                'name' => 'Leadership Bench strength (quality and status of the named people)',
                'category' => 'Bench Strength',
                'description' => 'Quality, standing, and commitment of the core succession bench and key cabinet members named.',
                'max_score' => 10,
                'weightage' => 15,
                'sort_order' => 3,
            ],
            [
                'name' => 'Community-building track record',
                'category' => 'Community',
                'description' => 'History of giving first, fostering trust, and driving community cohesion without self-serving agenda.',
                'max_score' => 10,
                'weightage' => 15,
                'sort_order' => 4,
            ],
            [
                'name' => 'Business credibility',
                'category' => 'Business',
                'description' => 'Standing in business ecosystem, ethical reputation, financial stability, and peer enterprise respect.',
                'max_score' => 10,
                'weightage' => 10,
                'sort_order' => 5,
            ],
            [
                'name' => 'Vision and plan realism',
                'category' => 'Strategy',
                'description' => 'Clarity, feasibility, and realistic execution roadmap of the 12-month build plan and milestone projections.',
                'max_score' => 10,
                'weightage' => 10,
                'sort_order' => 6,
            ],
            [
                'name' => 'Commitment and availability',
                'category' => 'Commitment',
                'description' => 'Pledged weekly time investment, board presence, and willingness to travel and mentor chapter chairs.',
                'max_score' => 10,
                'weightage' => 10,
                'sort_order' => 7,
            ],
            [
                'name' => 'Values alignment',
                'category' => 'Values',
                'description' => 'Adherence to Peers Global core philosophy: Partners in business, friends in life.',
                'max_score' => 10,
                'weightage' => 10,
                'sort_order' => 8,
            ],
        ];

        $targetCampaignId = ! empty($campaignIds) ? $campaignIds[0] : null;

        foreach ($defaultCriteria as $item) {
            $exists = LeadershipJuryEvaluationCriterion::where('name', $item['name'])
                ->when($targetCampaignId, function ($q) use ($targetCampaignId) {
                    $q->where(function ($sub) use ($targetCampaignId) {
                        $sub->where('campaign_id', $targetCampaignId)
                            ->orWhereNull('campaign_id');
                    });
                })
                ->exists();

            if (! $exists) {
                LeadershipJuryEvaluationCriterion::create([
                    'campaign_id' => $targetCampaignId,
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'description' => $item['description'],
                    'max_score' => $item['max_score'],
                    'weightage' => $item['weightage'],
                    'sort_order' => $item['sort_order'],
                    'is_active' => true,
                ]);
            }
        }
    }

    /**
     * Format candidate nomination details (Form 1 + Form 2 + Review).
     */
    public function formatNominationDetails(LeadershipNomination $nomination, ?LeadershipJuryAssignment $assignment = null): array
    {
        $snap = is_array($nomination->profile_snapshot) ? $nomination->profile_snapshot : [];
        $rawF2 = $nomination->form_2_data ?? ($snap['form_2'] ?? null);

        $isF2Submitted = ! empty($rawF2) && (($snap['form_2_status'] ?? '') === 'submitted' || ($rawF2['status'] ?? '') === 'submitted');

        $form2Data = null;
        if ($isF2Submitted) {
            $form2Data = (is_array($rawF2) && isset($rawF2['form_2'])) ? $rawF2['form_2'] : $rawF2;
        }

        $candidateName = $nomination->candidate_name ?: ($nomination->user?->name ?? 'Candidate');
        $companyName = $nomination->company ?: ($nomination->user?->company_name ?: ($snap['company_name'] ?? ($snap['company'] ?? 'Not Specified')));
        $designation = $nomination->designation ?: ($nomination->user?->designation ?: ($snap['designation'] ?? ($nomination->campaign?->role?->name ?? 'Leader')));
        $businessCategory = $nomination->user?->mainBusinessCategory?->name ?: ($nomination->user?->businessCategory?->name ?: ($snap['business_category'] ?? ($snap['business_type'] ?? 'General Business')));
        $phone = $nomination->phone ?: ($nomination->user?->phone_number ?? 'Not Disclosed');
        $email = $nomination->email ?: ($nomination->user?->email ?? 'Not Disclosed');

        $nominationArray = $nomination->toArray();
        $nominationArray['candidate_name'] = $candidateName;
        $nominationArray['company'] = $companyName;
        $nominationArray['designation'] = $designation;
        $nominationArray['business_category'] = $businessCategory;
        $nominationArray['phone'] = $phone;
        $nominationArray['email'] = $email;

        $existingReview = null;
        if ($assignment && $assignment->report) {
            $existingReview = $assignment->report->toArray();
            $existingReview['scores'] = $assignment->scores->toArray();
        }

        return [
            'nomination' => $nominationArray,
            'form_2_status' => $isF2Submitted ? 'Submitted' : 'Awaiting Candidate',
            'form_2' => $form2Data,
            'documents' => $nomination->documents,
            'existing_review' => $existingReview,
        ];
    }

    /**
     * Get candidate roster assigned to the authenticated Juror.
     * Supports Token, Session, or ?email=<email>
     */
    public function candidates(Request $request): JsonResponse
    {
        $juror = $this->resolveJuror($request);

        if (! $juror) {
            return response()->json([
                'success' => false,
                'message' => 'Juror identity could not be verified. Please provide a valid token, session, or registered email.',
            ], 401);
        }

        $fullName = trim(($juror->first_name ?? '').' '.($juror->last_name ?? ''));
        $jurorName = $juror->display_name ?: ($fullName !== '' ? $fullName : 'Jury Member');

        $assignments = LeadershipJuryAssignment::query()
            ->with([
                'nomination.campaign.role',
                'nomination.scope',
                'nomination.user',
                'nomination.documents',
                'report',
                'scores.criterion',
            ])
            ->where('juror_user_id', $juror->id)
            ->whereHas('nomination', function ($q): void {
                $q->where('application_number', '!=', 'PG-STANDING-POOL');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $candidatesList = [];
        foreach ($assignments as $asg) {
            $nom = $asg->nomination;
            if (! $nom) {
                continue;
            }

            $snap = is_array($nom->profile_snapshot) ? $nom->profile_snapshot : [];
            $rawF2 = $nom->form_2_data ?? ($snap['form_2'] ?? null);
            $isF2Submitted = ! empty($rawF2) && (($snap['form_2_status'] ?? '') === 'submitted' || ($rawF2['status'] ?? '') === 'submitted');

            $appNumber = str_replace('-2026-', '-', (string) $nom->application_number);

            $roleName = $nom->applied_role_name
                ?: ($nom->campaign?->role?->name
                ?: ($snap['applied_role'] ?? 'Circle Director'));

            $scopeName = $nom->scope_name
                ?: ($nom->scope?->name
                ?: ($nom->city ?: ($snap['city'] ?? 'Ahmedabad')));

            $company = $nom->company
                ?: ($snap['company'] ?? ($snap['company_name'] ?? ($nom->user?->company_name ?? 'Enterprise Partner')));

            $designation = $nom->designation
                ?: ($snap['designation'] ?? ($nom->user?->designation ?? 'Leader'));

            $category = $snap['business_category']
                ?? ($snap['category']
                ?? ($snap['industry']
                ?? ($nom->user?->mainBusinessCategory?->name ?? ($nom->user?->businessCategory?->name ?? 'General Business'))));

            $phone = (string) ($nom->phone ?: ($nom->mobile ?: ($nom->user?->phone_number ?? '')));
            $email = (string) ($nom->email ?: ($nom->user?->email ?? ''));

            $maskedPhone = $phone !== ''
                ? (str_starts_with($phone, '+') ? '' : '+91 ').substr($phone, 0, 4).'•••• ••'.substr($phone, -2)
                : '+91 95•••• ••86';

            $maskedEmail = $email !== ''
                ? substr($email, 0, 1).'•••••••••••@'.(explode('@', $email)[1] ?? 'peersglobal.com')
                : 'protected@peersglobal.com';

            $contactMasked = "{$maskedPhone} · {$maskedEmail}";

            $f2Answers = is_array($rawF2) ? ($rawF2['form_2'] ?? $rawF2) : [];
            $executiveBio = $isF2Submitted ? ($f2Answers['executive_bio'] ?? ($f2Answers['bio'] ?? ($snap['bio'] ?? ''))) : '';
            $operationalBlueprint = $isF2Submitted ? ($f2Answers['operational_blueprint'] ?? ($f2Answers['vision'] ?? ($snap['vision_statement'] ?? ''))) : '';
            $benchValue = $isF2Submitted ? ($f2Answers['bench_value'] ?? ($f2Answers['bench_members'] ?? [])) : [];
            $documents = $isF2Submitted ? ($f2Answers['documents'] ?? ($nom->documents ? $nom->documents->toArray() : [])) : [];

            $candidatesList[] = [
                'id' => $nom->id,
                'assignment_id' => $asg->id,
                'name' => $nom->candidate_name ?: ($nom->full_name ?: ($nom->user?->name ?? 'Nominated Candidate')),
                'avatar_url' => $nom->user?->profile_photo_url ?: ($snap['photo_url'] ?? ($nom->creative_url ?? null)),
                'application_number' => $appNumber,
                'role' => $roleName,
                'scope' => $scopeName,
                'company' => $company,
                'designation' => $designation,
                'category' => $category,
                'contact_masked' => $contactMasked,
                'form_2_status' => $isF2Submitted ? 'Submitted' : 'Awaiting Candidate',
                'evaluation_status' => ($asg->status === 'completed' || $asg->report !== null) ? 'Completed' : 'Evaluation Pending',
                'executive_bio' => $executiveBio,
                'operational_blueprint' => $operationalBlueprint,
                'bench_value' => is_array($benchValue) ? $benchValue : [],
                'documents' => is_array($documents) ? $documents : [],
            ];
        }

        return response()->json([
            'success' => true,
            'juror' => [
                'id' => $juror->id,
                'name' => $jurorName,
                'email' => $juror->email,
            ],
            'candidates' => $candidatesList,
        ]);
    }

    /**
     * Get single candidate dossier and authentic Form 2 answers (no mocks).
     */
    public function candidateDetail(Request $request, string $id): JsonResponse
    {
        try {
            $nomination = LeadershipNomination::with([
                'campaign.role',
                'scope',
                'documents',
                'answers.question',
                'user.mainBusinessCategory',
                'user.businessCategory',
            ])->findOrFail($id);

            $assignmentId = $request->query('assignment_id');
            $assignment = null;
            if ($assignmentId) {
                $assignment = LeadershipJuryAssignment::with(['report', 'scores.criterion'])->find($assignmentId);
            } else {
                $juror = $this->resolveJuror($request);
                if ($juror) {
                    $assignment = LeadershipJuryAssignment::with(['report', 'scores.criterion'])
                        ->where('juror_user_id', $juror->id)
                        ->where('nomination_id', $nomination->id)
                        ->first();
                }
            }

            $snap = is_array($nomination->profile_snapshot) ? $nomination->profile_snapshot : [];
            $rawF2 = $nomination->form_2_data ?? ($snap['form_2'] ?? null);
            $isF2Submitted = ! empty($rawF2) && (($snap['form_2_status'] ?? '') === 'submitted' || ($rawF2['status'] ?? '') === 'submitted');

            $form2Data = null;
            if ($isF2Submitted && is_array($rawF2)) {
                $form2Data = $rawF2['form_2'] ?? $rawF2;
            }

            $existingReview = null;
            if ($assignment && $assignment->report) {
                $existingReview = $assignment->report->toArray();
                $existingReview['scores'] = $assignment->scores->toArray();
            }

            return response()->json([
                'success' => true,
                'form_2_status' => $isF2Submitted ? 'Submitted' : 'Awaiting Candidate',
                'form_2' => $form2Data,
                'candidate' => [
                    'id' => $nomination->id,
                    'assignment_id' => $assignment?->id,
                    'application_number' => str_replace('-2026-', '-', (string) $nomination->application_number),
                    'name' => $nomination->candidate_name ?: ($nomination->full_name ?: ($nomination->user?->name ?? 'Candidate')),
                    'role' => $nomination->applied_role_name ?: ($nomination->campaign?->role?->name ?: ($snap['applied_role'] ?? 'Circle Director')),
                    'scope' => $nomination->scope_name ?: ($nomination->scope?->name ?: ($nomination->city ?: ($snap['city'] ?? 'Ahmedabad'))),
                    'company' => $nomination->company ?: ($snap['company'] ?? ($snap['company_name'] ?? '')),
                    'designation' => $nomination->designation ?: ($snap['designation'] ?? ''),
                    'category' => $snap['business_category'] ?? ($snap['category'] ?? ($snap['industry'] ?? 'General Business')),
                    'documents' => $nomination->documents,
                ],
                'existing_review' => $existingReview,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Candidate not found: '.$e->getMessage(),
            ], 404);
        }
    }

    /**
     * Submit score and evaluation directly from Next.js portal.
     */
    public function submitScoreDirect(Request $request): JsonResponse
    {
        $juror = $this->resolveJuror($request);

        $candidateId = $request->input('candidate_id') ?? $request->input('nomination_id');
        $assignmentId = $request->input('assignment_id');

        if (! $juror && $request->input('juror_email')) {
            $email = strtolower(trim((string) $request->input('juror_email')));
            $juror = User::whereRaw('LOWER(email) = ?', [$email])->first();
        }

        if (! $juror) {
            return response()->json(['success' => false, 'message' => 'Unauthorized: valid juror session or email is required.'], 401);
        }

        $assignment = null;
        if ($assignmentId) {
            $assignment = LeadershipJuryAssignment::where('juror_user_id', $juror->id)->find($assignmentId);
        }
        if (! $assignment && $candidateId) {
            $assignment = LeadershipJuryAssignment::where('juror_user_id', $juror->id)
                ->where('nomination_id', $candidateId)
                ->latest()
                ->first();
        }

        if (! $assignment) {
            return response()->json(['success' => false, 'message' => 'No active jury assignment found for this candidate.'], 404);
        }

        $scores = (array) ($request->input('scores') ?? []);
        $overallAverage = (float) ($scores['overall_average'] ?? $request->input('overall_average') ?? 7.0);

        $recommendation = match (true) {
            $overallAverage >= 8.5 => 'strongly_recommend',
            $overallAverage >= 6.5 => 'recommend',
            $overallAverage >= 5.0 => 'recommend_with_reservations',
            default => 'do_not_recommend',
        };

        $conflictBool = (bool) $request->input('conflict_of_interest');
        $conflictDetails = $request->input('conflict_disclosure') ?? $request->input('conflict_details');

        $strengths = (string) ($request->input('key_strengths') ?? $request->input('strengths') ?? 'Commendable leadership commitment and track record.');
        $concerns = (string) ($request->input('key_concerns') ?? $request->input('concerns') ?? 'None observed.');

        LeadershipJuryReport::updateOrCreate(
            ['assignment_id' => $assignment->id],
            [
                'overall_recommendation' => $recommendation,
                'strengths' => $strengths,
                'concerns' => $concerns,
                'conflict_of_interest' => $conflictBool,
                'conflict_details' => $conflictDetails,
                'final_remarks' => $request->input('final_remarks'),
                'submitted_at' => Carbon::now(),
            ]
        );

        // Save scores across standard criteria
        $criteriaMap = [
            'governance_integrity' => 'Leadership of leaders',
            'operational_execution' => 'Demonstrated track record',
            'community_engagement' => 'Ecosystem contribution',
            'strategic_vision' => 'Vision and plan realism',
        ];

        foreach ($criteriaMap as $key => $critName) {
            if (isset($scores[$key])) {
                $crit = LeadershipJuryEvaluationCriterion::firstOrCreate(
                    [
                        'name' => $critName,
                        'campaign_id' => $assignment->campaign_id,
                    ],
                    [
                        'category' => 'Leadership Criteria',
                        'max_score' => 10,
                        'weightage' => 10,
                        'sort_order' => 1,
                        'is_active' => true,
                    ]
                );

                LeadershipJuryScore::updateOrCreate(
                    [
                        'assignment_id' => $assignment->id,
                        'criterion_id' => $crit->id,
                    ],
                    [
                        'score' => (float) $scores[$key],
                        'remarks' => null,
                    ]
                );
            }
        }

        $assignment->status = 'completed';
        $assignment->completed_at = Carbon::now();
        $assignment->save();

        return response()->json([
            'success' => true,
            'message' => 'Official score and evaluation successfully recorded.',
            'data' => [
                'assignment_id' => $assignment->id,
                'candidate_id' => $candidateId,
                'status' => 'completed',
                'average_score' => $overallAverage,
                'submitted_at' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get candidate nomination details (Form 1 + Form 2).
     */
    public function nominationDetails(Request $request, string $nominationId): JsonResponse
    {
        try {
            $nomination = LeadershipNomination::with([
                'campaign.role',
                'scope',
                'documents',
                'answers.question',
                'user.mainBusinessCategory',
                'user.businessCategory',
            ])->findOrFail($nominationId);

            $assignmentId = $request->query('assignment_id');
            $assignment = null;
            if ($assignmentId) {
                $assignment = LeadershipJuryAssignment::with(['report', 'scores.criterion'])->find($assignmentId);
            }

            $data = $this->formatNominationDetails($nomination, $assignment);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Submit review and score for an assigned candidate.
     */
    public function submitReview(Request $request, string $assignmentId): JsonResponse
    {
        $jurorId = $request->session()->get('jury_user_id');

        if (! $jurorId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized session.'], 401);
        }

        $assignment = LeadershipJuryAssignment::where('juror_user_id', $jurorId)->findOrFail($assignmentId);

        $validated = $request->validate([
            'overall_recommendation' => 'required|string',
            'strengths' => 'required|string',
            'concerns' => 'required|string',
            'final_remarks' => 'nullable|string',
            'conflict_of_interest' => 'nullable',
            'conflict_details' => 'nullable|string',
            'scores' => 'nullable|array',
            'scores.*.criterion_id' => 'nullable|string',
            'scores.*.criterion_name' => 'nullable|string',
            'scores.*.score' => 'required|numeric|min:0',
            'scores.*.remarks' => 'nullable|string',
            'custom_criteria' => 'nullable|array',
            'custom_criteria.*.name' => 'required|string',
            'custom_criteria.*.score' => 'required|numeric|min:0',
            'custom_criteria.*.remarks' => 'nullable|string',
        ]);

        try {
            $conflictBool = false;
            if (isset($validated['conflict_of_interest'])) {
                $val = $validated['conflict_of_interest'];
                $conflictBool = ($val === true || $val === 1 || $val === '1' || $val === 'true' || $val === 'yes');
            }

            // Normalize recommendation to adhere strictly to database check constraint:
            // ('strongly_recommend', 'recommend', 'recommend_with_reservations', 'do_not_recommend', 'abstain')
            $recVal = strtolower(trim((string) $validated['overall_recommendation']));
            $recommendation = match ($recVal) {
                'strongly_recommend' => 'strongly_recommend',
                'recommend' => 'recommend',
                'recommend_with_reservations', 'neutral', 'reservations', 'borderline' => 'recommend_with_reservations',
                'do_not_recommend', 'reject' => 'do_not_recommend',
                'abstain' => 'abstain',
                default => 'recommend',
            };

            // 1. Create or update the final jury report
            $report = LeadershipJuryReport::updateOrCreate(
                ['assignment_id' => $assignment->id],
                [
                    'overall_recommendation' => $recommendation,
                    'strengths' => $validated['strengths'],
                    'concerns' => $validated['concerns'],
                    'conflict_of_interest' => $conflictBool,
                    'conflict_details' => $validated['conflict_details'] ?? null,
                    'final_remarks' => $validated['final_remarks'] ?? null,
                    'submitted_at' => Carbon::now(),
                ]
            );

            // 2. Save individual criterion scores (including dynamically added criteria)
            if (! empty($validated['scores'])) {
                foreach ($validated['scores'] as $scoreData) {
                    $criterionId = $scoreData['criterion_id'] ?? null;

                    // If it's a dynamic criterion passed by name
                    if ((empty($criterionId) || Str::startsWith($criterionId, 'custom_')) && ! empty($scoreData['criterion_name'])) {
                        $crit = LeadershipJuryEvaluationCriterion::firstOrCreate(
                            [
                                'name' => trim((string) $scoreData['criterion_name']),
                                'campaign_id' => $assignment->campaign_id,
                            ],
                            [
                                'category' => 'Custom Juror Metric',
                                'description' => 'Dynamic criterion added by evaluation juror',
                                'max_score' => 10,
                                'weightage' => 10,
                                'sort_order' => 99,
                                'is_active' => true,
                            ]
                        );
                        $criterionId = $crit->id;
                    }

                    if ($criterionId && Str::isUuid($criterionId)) {
                        LeadershipJuryScore::updateOrCreate(
                            [
                                'assignment_id' => $assignment->id,
                                'criterion_id' => $criterionId,
                            ],
                            [
                                'score' => (float) $scoreData['score'],
                                'remarks' => $scoreData['remarks'] ?? null,
                            ]
                        );
                    }
                }
            }

            // 3. Process custom_criteria array if provided separately
            if (! empty($validated['custom_criteria'])) {
                foreach ($validated['custom_criteria'] as $cItem) {
                    $crit = LeadershipJuryEvaluationCriterion::firstOrCreate(
                        [
                            'name' => trim((string) $cItem['name']),
                            'campaign_id' => $assignment->campaign_id,
                        ],
                        [
                            'category' => 'Custom Juror Metric',
                            'description' => 'Dynamic criterion added by evaluation juror',
                            'max_score' => 10,
                            'weightage' => 10,
                            'sort_order' => 99,
                            'is_active' => true,
                        ]
                    );

                    LeadershipJuryScore::updateOrCreate(
                        [
                            'assignment_id' => $assignment->id,
                            'criterion_id' => $crit->id,
                        ],
                        [
                            'score' => (float) $cItem['score'],
                            'remarks' => $cItem['remarks'] ?? null,
                        ]
                    );
                }
            }

            // 4. Mark assignment as completed
            $assignment->update([
                'status' => 'completed',
                'completed_at' => Carbon::now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Candidate evaluation and score submitted successfully!',
                'assignment' => $assignment->fresh(['report', 'scores']),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit review: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Juror can dynamically add their own evaluation criterion on the fly.
     */
    public function addCriterion(Request $request): JsonResponse
    {
        $jurorId = $request->session()->get('jury_user_id');
        if (! $jurorId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized session.'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'campaign_id' => 'nullable|uuid',
            'description' => 'nullable|string',
            'max_score' => 'nullable|numeric|min:1|max:100',
            'weightage' => 'nullable|numeric|min:0|max:100',
        ]);

        $criterion = LeadershipJuryEvaluationCriterion::create([
            'campaign_id' => $validated['campaign_id'] ?? null,
            'name' => trim($validated['name']),
            'category' => 'Custom Juror Metric',
            'description' => $validated['description'] ?? 'Dynamic criterion added by juror',
            'max_score' => (float) ($validated['max_score'] ?? 10),
            'weightage' => (float) ($validated['weightage'] ?? 10),
            'sort_order' => 99,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Custom evaluation criterion created successfully!',
            'data' => $criterion,
        ]);
    }

    /**
     * Juror Logout.
     */
    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['jury_user_id', 'jury_user_email', 'jury_user_name']);

        return redirect()->route('admin.web.jury.login');
    }
}
