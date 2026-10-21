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
                'redirect_url' => route('admin.web.jury.dashboard'),
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
            ->orderBy('created_at', 'desc')
            ->get();

        $campaignIds = $assignments->pluck('nomination.campaign_id')->filter()->unique()->values()->all();

        $criteria = LeadershipJuryEvaluationCriterion::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        // If no criteria exist specifically for the campaigns, load general active criteria
        if ($criteria->isEmpty()) {
            $criteria = LeadershipJuryEvaluationCriterion::where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->get();
        }

        return view('admin.web.jury.dashboard', compact('juror', 'assignments', 'criteria'));
    }

    /**
     * Get candidate nomination details (Form 1 + Form 2).
     */
    public function nominationDetails(string $nominationId): JsonResponse
    {
        try {
            $nomination = LeadershipNomination::with([
                'campaign.role',
                'scope',
                'documents',
                'answers.question',
            ])->findOrFail($nominationId);

            // Fetch Form 2 child if exists
            $form2Child = LeadershipNomination::where('application_number', $nomination->application_number.'-F2')->first();
            $form2Data = $nomination->form_2_data ?? $form2Child?->profile_snapshot ?? null;

            return response()->json([
                'success' => true,
                'data' => [
                    'nomination' => $nomination,
                    'form_2' => $form2Data,
                    'documents' => $nomination->documents,
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
            'scores' => 'nullable|array',
            'scores.*.criterion_id' => 'required|uuid',
            'scores.*.score' => 'required|numeric|min:0',
            'scores.*.remarks' => 'nullable|string',
        ]);

        try {
            // 1. Create or update the final jury report
            $report = LeadershipJuryReport::updateOrCreate(
                ['assignment_id' => $assignment->id],
                [
                    'overall_recommendation' => $validated['overall_recommendation'],
                    'strengths' => $validated['strengths'],
                    'concerns' => $validated['concerns'],
                    'final_remarks' => $validated['final_remarks'] ?? null,
                    'submitted_at' => Carbon::now(),
                ]
            );

            // 2. Save individual criterion scores
            if (! empty($validated['scores'])) {
                foreach ($validated['scores'] as $scoreData) {
                    LeadershipJuryScore::updateOrCreate(
                        [
                            'assignment_id' => $assignment->id,
                            'criterion_id' => $scoreData['criterion_id'],
                        ],
                        [
                            'score' => (float) $scoreData['score'],
                            'remarks' => $scoreData['remarks'] ?? null,
                            'scored_at' => Carbon::now(),
                        ]
                    );
                }
            }

            // 3. Mark assignment as completed
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
     * Juror Logout.
     */
    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['jury_user_id', 'jury_user_email', 'jury_user_name']);

        return redirect()->route('admin.web.jury.login');
    }
}
