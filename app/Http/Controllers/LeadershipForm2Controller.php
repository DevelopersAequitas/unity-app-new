<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Leadership\LeadershipNomination;
use App\Services\Leadership\AdminNominationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LeadershipForm2Controller extends Controller
{
    public function __construct(
        protected AdminNominationService $nominationService
    ) {}

    /**
     * Show public Form 2 (Jury Evaluation Data Form).
     */
    public function show(string $id): View
    {
        /** @var LeadershipNomination $nomination */
        $nomination = LeadershipNomination::with(['campaign.role', 'scope'])->findOrFail($id);

        $roleName = $nomination->campaign?->role?->name ?? 'Leadership Candidate';
        $campaignName = $nomination->campaign?->name ?? 'Peers Global Leadership Selection 2026';
        $candidateName = $nomination->candidate_name ?: 'Candidate';
        $email = $nomination->email;
        $status = $nomination->form_2_status;
        $isSubmitted = $status === 'submitted';

        // Bench leader role name based on applied role
        $benchRequirement = $this->resolveBenchRequirement($roleName);

        return view('leadership.form2', [
            'nomination' => $nomination,
            'roleName' => $roleName,
            'campaignName' => $campaignName,
            'candidateName' => $candidateName,
            'email' => $email,
            'isSubmitted' => $isSubmitted,
            'benchRequirement' => $benchRequirement,
            'existingData' => $nomination->form_2_data,
        ]);
    }

    /**
     * Public API endpoint to get candidate & campaign details for Form 2 on peersglobal.com.
     */
    public function apiDetails(string $id): JsonResponse
    {
        /** @var LeadershipNomination $nomination */
        $nomination = LeadershipNomination::with(['campaign.role', 'scope'])->findOrFail($id);

        $roleName = $nomination->campaign?->role?->name ?? 'Leadership Candidate';
        $campaignName = $nomination->campaign?->name ?? 'Peers Global Leadership Selection 2026';
        $benchRequirement = $this->resolveBenchRequirement($roleName);

        return response()->json([
            'success' => true,
            'data' => [
                'nomination_id' => $nomination->id,
                'application_number' => $nomination->application_number,
                'candidate_name' => $nomination->candidate_name,
                'campaign_id' => $nomination->campaign_id,
                'campaign_name' => $campaignName,
                'role_name' => $roleName,
                'masked_email' => $this->maskEmail((string) $nomination->email),
                'status' => $nomination->status,
                'form_2_status' => $nomination->form_2_status,
                'is_submitted' => $nomination->form_2_status === 'submitted',
                'bench_requirement' => $benchRequirement,
                'existing_data' => $nomination->form_2_data,
            ],
        ]);
    }

    protected function maskEmail(string $email): string
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '****@****.com';
        }
        [$name, $domain] = explode('@', $email, 2);
        $maskedName = substr($name, 0, 2).str_repeat('*', max(1, strlen($name) - 3)).substr($name, -1);

        return $maskedName.'@'.$domain;
    }

    /**
     * Verify email address to prevent mismatch.
     */
    public function verifyEmail(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        /** @var LeadershipNomination $nomination */
        $nomination = LeadershipNomination::with('campaign.role')->findOrFail($id);

        $inputEmail = strtolower(trim((string) $request->input('email')));
        $nomineeEmail = strtolower(trim((string) $nomination->email));

        if ($inputEmail !== $nomineeEmail) {
            return response()->json([
                'success' => false,
                'message' => 'Email mismatch: The entered email address does not match this nomination application. Please enter the email address used during your initial nomination.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully. Form 2 unlocked.',
            'data' => [
                'verified' => true,
                'candidate_name' => $nomination->candidate_name,
                'role_name' => $nomination->campaign?->role?->name,
                'application_number' => $nomination->application_number,
            ],
        ]);
    }

    /**
     * Upload an attachment for Form 2.
     */
    public function upload(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,ppt,pptx',
            'type' => 'nullable|string',
        ]);

        $file = $request->file('file');
        if (! $file) {
            return response()->json(['success' => false, 'message' => 'No file uploaded.'], 400);
        }

        $path = $file->store("leadership/form2/{$id}", 'public');
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'success' => true,
            'url' => $url,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Submit Form 2 data.
     */
    public function submit(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $payload = $request->all();

        // Handle direct file uploads if present in multipart form
        if ($request->hasFile('business_proof_file')) {
            $path = $request->file('business_proof_file')->store("leadership/form2/{$id}", 'public');
            $payload['business_proof_url'] = Storage::disk('public')->url($path);
        }
        if ($request->hasFile('identity_proof_file')) {
            $path = $request->file('identity_proof_file')->store("leadership/form2/{$id}", 'public');
            $payload['identity_proof_url'] = Storage::disk('public')->url($path);
        }
        if ($request->hasFile('deck_file')) {
            $path = $request->file('deck_file')->store("leadership/form2/{$id}", 'public');
            $payload['deck_url'] = Storage::disk('public')->url($path);
        }

        try {
            $subNomination = $this->nominationService->submitForm2($id, $payload);

            if ($request->expectsJson() || $request->isJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Form 2 (Jury Evaluation Data) submitted successfully.',
                    'data' => [
                        'sub_nomination_id' => $subNomination->id,
                        'application_number' => $subNomination->application_number,
                    ],
                ]);
            }

            return redirect()->route('leadership.form2.show', ['id' => $id, 'submitted' => 1])
                ->with('success', 'Form 2 submitted successfully! Thank you for completing your evaluation details.');
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson() || $request->isJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->withErrors(['email' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            if ($request->expectsJson() || $request->isJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }

            return redirect()->back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    /**
     * Resolve Bench section role requirements per guidelines.
     *
     * @return array{target_role: string, min_count: int, description: string}
     */
    protected function resolveBenchRequirement(string $roleName): array
    {
        $lower = strtolower($roleName);

        if (str_contains($lower, 'chief executive') || str_contains($lower, 'ced')) {
            return [
                'target_role' => 'Senior Executive Directors (SED)',
                'min_count' => 3,
                'description' => 'You must name at least 3 prospective SED candidates who will lead states/zones under you.',
            ];
        }

        if (str_contains($lower, 'senior executive') || str_contains($lower, 'sed')) {
            return [
                'target_role' => 'District Executive Directors (DED)',
                'min_count' => 5,
                'description' => 'You must name at least 5 prospective DED candidates who will lead districts under you.',
            ];
        }

        if (str_contains($lower, 'district executive') || str_contains($lower, 'ded')) {
            return [
                'target_role' => 'Industry Directors (ID)',
                'min_count' => 7,
                'description' => 'You must name at least 7 prospective Industry Directors across key industry clusters.',
            ];
        }

        if (str_contains($lower, 'executive director') || str_contains($lower, 'ed')) {
            return [
                'target_role' => 'Industry Directors for area',
                'min_count' => 3,
                'description' => 'You must name at least 3 prospective Industry Directors for your designated area.',
            ];
        }

        if (str_contains($lower, 'industry director') || str_contains($lower, 'id')) {
            return [
                'target_role' => 'Circle Founders',
                'min_count' => 3,
                'description' => 'You must name at least 3 prospective Circle Founders.',
            ];
        }

        if (str_contains($lower, 'circle chair') || str_contains($lower, 'chair')) {
            return [
                'target_role' => 'New Peers & Leadership Team Members',
                'min_count' => 5,
                'description' => 'You must name at least 5 prospective Core Circle Leadership Team members.',
            ];
        }

        return [
            'target_role' => 'Leadership Bench candidates',
            'min_count' => 3,
            'description' => 'You must name at least 3 prospective leaders one level below your applied role.',
        ];
    }
}
