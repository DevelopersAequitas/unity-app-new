<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvertiserApplication;
use App\Models\AdvertiserApplicationAsset;
use App\Models\AdvertiserPackage;
use App\Models\AdvertiserSubscription;
use App\Services\Advertiser\AdvertiserApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvertiserApplicationAdminController extends Controller
{
    public function __construct(
        protected AdvertiserApplicationService $applicationService
    ) {}

    public function index(Request $request): View
    {
        $query = AdvertiserApplication::with(['package', 'user', 'brandPartner'])->orderByDesc('created_at');

        if ($request->filled('q')) {
            $q = trim((string) $request->query('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('application_number', 'ILIKE', "%{$q}%")
                    ->orWhere('company_name', 'ILIKE', "%{$q}%")
                    ->orWhere('applicant_name', 'ILIKE', "%{$q}%")
                    ->orWhere('applicant_email', 'ILIKE', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('package_id')) {
            $query->where('package_id', $request->query('package_id'));
        }

        if ($request->filled('source')) {
            $query->where('source', $request->query('source'));
        }

        $applications = $query->paginate(20)->appends($request->query());
        $packages = AdvertiserPackage::ordered()->get();

        $counts = [
            'total' => AdvertiserApplication::count(),
            'submitted' => AdvertiserApplication::where('status', AdvertiserApplication::STATUS_SUBMITTED)->count(),
            'under_review' => AdvertiserApplication::where('status', AdvertiserApplication::STATUS_UNDER_REVIEW)->count(),
            'changes_requested' => AdvertiserApplication::where('status', AdvertiserApplication::STATUS_CHANGES_REQUESTED)->count(),
            'approved' => AdvertiserApplication::where('status', AdvertiserApplication::STATUS_APPROVED)->count(),
            'rejected' => AdvertiserApplication::where('status', AdvertiserApplication::STATUS_REJECTED)->count(),
        ];

        return view('admin.advertiser.applications.index', compact('applications', 'packages', 'counts'));
    }

    public function show(AdvertiserApplication $application): View
    {
        $application->loadMissing([
            'package',
            'assets',
            'statusHistory.user',
            'subscriptions.payments',
            'brandPartner',
            'reviewer',
        ]);

        return view('admin.advertiser.applications.show', compact('application'));
    }

    public function startReview(Request $request, AdvertiserApplication $application): RedirectResponse
    {
        $note = (string) $request->input('admin_note', 'Review commenced by admin.');
        $this->applicationService->transitionStatus(
            application: $application,
            newStatus: AdvertiserApplication::STATUS_UNDER_REVIEW,
            actorType: 'admin',
            actorId: auth()->id(),
            notes: $note
        );

        return redirect()->back()->with('success', 'Application marked as Under Review.');
    }

    public function requestCorrections(Request $request, AdvertiserApplication $application): RedirectResponse
    {
        $request->validate([
            'feedback' => ['required', 'string'],
        ]);

        $feedback = (string) $request->input('feedback');
        $this->applicationService->transitionStatus(
            application: $application,
            newStatus: AdvertiserApplication::STATUS_CHANGES_REQUESTED,
            actorType: 'admin',
            actorId: auth()->id(),
            notes: $feedback
        );

        return redirect()->back()->with('success', 'Correction request sent to applicant.');
    }

    public function approve(Request $request, AdvertiserApplication $application): RedirectResponse
    {
        $note = (string) $request->input('admin_note', 'Application approved by admin.');
        $applicantMessage = $request->input('applicant_message');

        $this->applicationService->transitionStatus(
            application: $application,
            newStatus: AdvertiserApplication::STATUS_APPROVED,
            actorType: 'admin',
            actorId: auth()->id(),
            notes: $note,
            metadata: ['applicant_message' => $applicantMessage]
        );

        // Ensure pending subscription record is initialized
        AdvertiserSubscription::firstOrCreate(
            ['application_id' => $application->id],
            [
                'user_id' => $application->user_id,
                'brand_partner_id' => $application->brand_partner_id,
                'package_id' => $application->package_id,
                'package_name_snapshot' => $application->requested_package_name ?? 'Standard Package',
                'duration_months' => $application->requested_duration_months ?: 1,
                'amount' => $application->requested_package_price ?? 0.0,
                'currency' => $application->requested_currency ?? 'INR',
                'status' => AdvertiserSubscription::STATUS_PENDING_PAYMENT,
            ]
        );

        return redirect()->back()->with('success', 'Application approved successfully. Next step is applicant payment & publication.');
    }

    public function reject(Request $request, AdvertiserApplication $application): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $reason = (string) $request->input('reason');
        $applicantMessage = $request->input('applicant_message');

        $this->applicationService->transitionStatus(
            application: $application,
            newStatus: AdvertiserApplication::STATUS_REJECTED,
            actorType: 'admin',
            actorId: auth()->id(),
            notes: $reason,
            metadata: [
                'rejection_reason' => $reason,
                'applicant_message' => $applicantMessage,
            ]
        );

        return redirect()->back()->with('success', 'Application rejected.');
    }

    public function reviewAsset(Request $request, AdvertiserApplication $application, AdvertiserApplicationAsset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'review_status' => ['required', 'string', 'in:pending,approved,rejected'],
            'review_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $asset->update([
            'review_status' => $validated['review_status'],
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        return redirect()->back()->with('success', "Creative asset marked as {$validated['review_status']}.");
    }
}
