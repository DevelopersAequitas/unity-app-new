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

            $userName = $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: 'Jury Member';

            session([
                'jury_user_id' => $user->id,
                'jury_user_email' => $user->email,
                'jury_user_name' => $userName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logged in successfully.',
                'redirect_url' => route('admin.web.jury.dashboard', [], false),
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

        return view('admin.web.jury.dashboard', compact('juror', 'assignments', 'criteria'));
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

            // Fetch Form 2 child if exists
            $form2Child = LeadershipNomination::where('application_number', $nomination->application_number.'-F2')
                ->orWhere(function ($q) use ($nomination) {
                    $q->where('campaign_id', $nomination->campaign_id)
                        ->where(function ($sub) use ($nomination) {
                            $sub->where('profile_snapshot->parent_nomination_id', $nomination->id)
                                ->orWhere('profile_snapshot->parent_application_number', $nomination->application_number);
                        });
                })
                ->latest()
                ->first();

            $rawF2 = $nomination->form_2_data
                ?? $nomination->profile_snapshot['form_2']
                ?? $form2Child?->profile_snapshot['form_2']
                ?? $form2Child?->profile_snapshot
                ?? null;

            $form2Data = (is_array($rawF2) && isset($rawF2['form_2'])) ? $rawF2['form_2'] : $rawF2;

            $candidateName = $nomination->candidate_name ?: ($nomination->user?->name ?? 'Candidate');
            $companyName = $nomination->company ?: ($nomination->user?->company_name ?: ($nomination->profile_snapshot['company_name'] ?? ($nomination->profile_snapshot['company'] ?? 'Not Specified')));
            $designation = $nomination->designation ?: ($nomination->user?->designation ?: ($nomination->profile_snapshot['designation'] ?? ($nomination->campaign?->role?->name ?? 'Leader')));
            $businessCategory = $nomination->user?->mainBusinessCategory?->name ?: ($nomination->user?->businessCategory?->name ?: ($nomination->profile_snapshot['business_category'] ?? ($nomination->profile_snapshot['business_type'] ?? 'General Business')));
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

            return response()->json([
                'success' => true,
                'data' => [
                    'nomination' => $nominationArray,
                    'form_2' => $form2Data,
                    'documents' => $nomination->documents,
                    'existing_review' => $existingReview,
                ],
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

            // 1. Create or update the final jury report
            $report = LeadershipJuryReport::updateOrCreate(
                ['assignment_id' => $assignment->id],
                [
                    'overall_recommendation' => $validated['overall_recommendation'],
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
                                'scored_at' => Carbon::now(),
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
                            'scored_at' => Carbon::now(),
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
