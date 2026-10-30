<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Advertiser\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\AdvertiserApplication;
use App\Models\AdvertiserApplicationAsset;
use App\Models\AdvertiserCampaign;
use App\Models\AdvertiserPackage;
use App\Models\AdvertiserPayment;
use App\Models\AdvertiserSubscription;
use App\Models\BrandPartnerClick;
use App\Models\BrandPartnerView;
use App\Services\Advertiser\AdvertiserAnalyticsService;
use App\Services\Advertiser\AdvertiserApplicationService;
use App\Services\Advertiser\AdvertiserCampaignService;
use App\Services\Advertiser\AdvertiserPaymentService;
use App\Services\Advertiser\AdvertiserPublicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AdvertiserAdminApiController extends BaseApiController
{
    public function __construct(
        protected AdvertiserApplicationService $applicationService,
        protected AdvertiserPaymentService $paymentService,
        protected AdvertiserCampaignService $campaignService,
        protected AdvertiserPublicationService $publicationService,
        protected AdvertiserAnalyticsService $analyticsService
    ) {}

    // ==========================================
    // Package Management (APIs 20–25)
    // ==========================================

    public function packages(Request $request): JsonResponse
    {
        $query = AdvertiserPackage::ordered();

        if ($request->has('status') && !empty($request->query('status'))) {
            $query->where('is_active', $request->query('status') === 'active');
        }

        if ($request->has('q') && !empty($request->query('q'))) {
            $q = trim((string) $request->query('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'ILIKE', "%{$q}%")
                    ->orWhere('description', 'ILIKE', "%{$q}%");
            });
        }

        $perPage = (int) $request->query('per_page', 20);
        $packages = $query->paginate($perPage);

        return $this->success($packages, 'Packages retrieved successfully.');
    }

    public function createPackage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'duration_months' => ['required', 'integer', 'in:1,6,12'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'features' => ['nullable', 'array'],
            'banner_placement' => ['nullable', 'string', 'max:100'],
            'max_banner_assets' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $counter = 1;
        while (AdvertiserPackage::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-" . $counter;
            $counter++;
        }

        $package = AdvertiserPackage::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'duration_months' => (int) $validated['duration_months'],
            'price' => (float) $validated['price'],
            'currency' => strtoupper((string) ($validated['currency'] ?? 'INR')),
            'features' => $validated['features'] ?? [],
            'banner_placement' => $validated['banner_placement'] ?? 'brand_partner_listing',
            'max_banner_assets' => (int) ($validated['max_banner_assets'] ?? 5),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return $this->success($package, 'Package created successfully.', 201);
    }

    public function showPackage(string $id): JsonResponse
    {
        $package = AdvertiserPackage::find($id);
        if (!$package) {
            return $this->error('Package not found.', 404);
        }

        return $this->success($package, 'Package details retrieved.');
    }

    public function updatePackage(Request $request, string $id): JsonResponse
    {
        $package = AdvertiserPackage::find($id);
        if (!$package) {
            return $this->error('Package not found.', 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'duration_months' => ['sometimes', 'integer', 'in:1,6,12'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'features' => ['nullable', 'array'],
            'banner_placement' => ['nullable', 'string', 'max:100'],
            'max_banner_assets' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        if (isset($validated['name']) && $validated['name'] !== $package->name) {
            $package->name = $validated['name'];
        }
        if (array_key_exists('description', $validated)) {
            $package->description = $validated['description'];
        }
        if (isset($validated['duration_months'])) {
            $package->duration_months = (int) $validated['duration_months'];
        }
        if (isset($validated['price'])) {
            $package->price = (float) $validated['price'];
        }
        if (isset($validated['currency'])) {
            $package->currency = strtoupper((string) $validated['currency']);
        }
        if (isset($validated['features'])) {
            $package->features = $validated['features'];
        }
        if (isset($validated['banner_placement'])) {
            $package->banner_placement = $validated['banner_placement'];
        }
        if (isset($validated['max_banner_assets'])) {
            $package->max_banner_assets = (int) $validated['max_banner_assets'];
        }
        if (isset($validated['is_active'])) {
            $package->is_active = (bool) $validated['is_active'];
        }
        if (isset($validated['sort_order'])) {
            $package->sort_order = (int) $validated['sort_order'];
        }

        $package->updated_by = auth()->id();
        $package->save();

        return $this->success($package, 'Package updated successfully.');
    }

    public function togglePackageStatus(Request $request, string $id): JsonResponse
    {
        $package = AdvertiserPackage::find($id);
        if (!$package) {
            return $this->error('Package not found.', 404);
        }

        $status = $request->input('status');
        if ($status !== null) {
            $package->is_active = ($status === 'active' || (bool) $status);
        } else {
            $package->is_active = !$package->is_active;
        }

        $package->updated_by = auth()->id();
        $package->save();

        return $this->success([
            'id' => $package->id,
            'status' => $package->is_active ? 'active' : 'inactive',
        ], 'Package status updated successfully.');
    }

    public function reorderPackages(Request $request): JsonResponse
    {
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'uuid', 'exists:advertiser_packages,id'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        foreach ($request->input('items') as $item) {
            AdvertiserPackage::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return $this->success(null, 'Package order updated successfully.');
    }

    // ==========================================
    // Application Management (APIs 26–33, 44)
    // ==========================================

    public function applications(Request $request): JsonResponse
    {
        $query = AdvertiserApplication::with(['package', 'user'])->orderByDesc('created_at');

        if ($request->has('q') && !empty($request->query('q'))) {
            $q = trim((string) $request->query('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('application_number', 'ILIKE', "%{$q}%")
                    ->orWhere('company_name', 'ILIKE', "%{$q}%")
                    ->orWhere('applicant_name', 'ILIKE', "%{$q}%")
                    ->orWhere('applicant_email', 'ILIKE', "%{$q}%");
            });
        }

        if ($request->has('status') && !empty($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('package_id') && !empty($request->query('package_id'))) {
            $query->where('package_id', $request->query('package_id'));
        }

        if ($request->has('source') && !empty($request->query('source'))) {
            $query->where('source', $request->query('source'));
        }

        if ($request->has('from') && !empty($request->query('from'))) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }
        if ($request->has('to') && !empty($request->query('to'))) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $perPage = (int) $request->query('per_page', 20);
        $apps = $query->paginate($perPage);

        return $this->success($apps, 'Applications retrieved successfully.');
    }

    public function showApplication(string $id): JsonResponse
    {
        $application = AdvertiserApplication::with([
            'package',
            'assets',
            'statusHistory.user',
            'subscriptions.payments',
            'brandPartner',
            'reviewer',
        ])->find($id);

        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        return $this->success($application, 'Application details retrieved.');
    }

    public function startReview(Request $request, string $id): JsonResponse
    {
        $application = AdvertiserApplication::find($id);
        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        $note = (string) $request->input('admin_note', 'Review started by administrator.');
        $updated = $this->applicationService->transitionStatus(
            application: $application,
            newStatus: AdvertiserApplication::STATUS_UNDER_REVIEW,
            actorType: 'admin',
            actorId: auth()->id(),
            notes: $note
        );

        return $this->success([
            'application_id' => $updated->id,
            'status' => $updated->status,
        ], 'Application moved to under review.');
    }

    public function requestCorrections(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'feedback' => ['required', 'string'],
            'required_corrections' => ['nullable', 'array'],
        ]);

        $application = AdvertiserApplication::find($id);
        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        $feedback = (string) $request->input('feedback');
        $updated = $this->applicationService->transitionStatus(
            application: $application,
            newStatus: AdvertiserApplication::STATUS_CHANGES_REQUESTED,
            actorType: 'admin',
            actorId: auth()->id(),
            notes: $feedback,
            metadata: ['required_corrections' => $request->input('required_corrections')]
        );

        return $this->success([
            'application_id' => $updated->id,
            'status' => $updated->status,
        ], 'Correction request sent to applicant.');
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $application = AdvertiserApplication::find($id);
        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        $note = (string) $request->input('admin_note', 'Company details and creatives approved.');
        $applicantMessage = $request->input('applicant_message');

        $updated = $this->applicationService->transitionStatus(
            application: $application,
            newStatus: AdvertiserApplication::STATUS_APPROVED,
            actorType: 'admin',
            actorId: auth()->id(),
            notes: $note,
            metadata: ['applicant_message' => $applicantMessage]
        );

        // Ensure a pending subscription record is initialized
        $subscription = AdvertiserSubscription::firstOrCreate(
            ['application_id' => $updated->id],
            [
                'user_id' => $updated->user_id,
                'brand_partner_id' => $updated->brand_partner_id,
                'package_id' => $updated->package_id,
                'package_name_snapshot' => $updated->requested_package_name ?? 'Standard Package',
                'duration_months' => $updated->requested_duration_months ?: 1,
                'amount' => $updated->requested_package_price ?? 0.0,
                'currency' => $updated->requested_currency ?? 'INR',
                'status' => AdvertiserSubscription::STATUS_PENDING_PAYMENT,
            ]
        );

        $paymentRequired = ((float) $subscription->amount > 0);

        return $this->success([
            'application_id' => $updated->id,
            'status' => $updated->status,
            'subscription_id' => $subscription->id,
            'payment_required' => $paymentRequired,
            'next_action' => $paymentRequired ? 'complete_payment' : 'publish_free_campaign',
        ], 'Application approved successfully.');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string'],
            'applicant_message' => ['nullable', 'string'],
        ]);

        $application = AdvertiserApplication::find($id);
        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        $reason = (string) $request->input('reason');
        $applicantMessage = $request->input('applicant_message');

        $updated = $this->applicationService->transitionStatus(
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

        return $this->success([
            'application_id' => $updated->id,
            'status' => $updated->status,
        ], 'Application rejected.');
    }

    public function reviewAsset(Request $request, string $applicationId, string $assetId): JsonResponse
    {
        $validated = $request->validate([
            'review_status' => ['required', 'string', 'in:pending,approved,rejected'],
            'feedback' => ['nullable', 'string', 'max:500'],
        ]);

        $asset = AdvertiserApplicationAsset::where('application_id', $applicationId)
            ->where('id', $assetId)
            ->first();

        if (!$asset) {
            return $this->error('Asset not found.', 404);
        }

        $asset->review_status = $validated['review_status'];
        $asset->review_notes = $validated['feedback'] ?? null;
        $asset->save();

        return $this->success([
            'asset_id' => $asset->id,
            'review_status' => $asset->review_status,
            'feedback' => $asset->review_notes,
        ], 'Asset review updated.');
    }

    public function history(string $id): JsonResponse
    {
        $application = AdvertiserApplication::with('statusHistory.user')->find($id);
        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        return $this->success($application->statusHistory, 'Application status history retrieved.');
    }

    public function advertiserDetails(string $id): JsonResponse
    {
        $application = AdvertiserApplication::with([
            'package',
            'assets',
            'user',
            'brandPartner',
            'subscriptions.payments',
        ])->find($id);

        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        return $this->success($application, 'Advertiser details retrieved.');
    }

    // ==========================================
    // Performance & Subscriptions & Payments (APIs 38–43)
    // ==========================================

    public function dashboard(): JsonResponse
    {
        $appStats = [
            'total' => AdvertiserApplication::count(),
            'submitted' => AdvertiserApplication::where('status', AdvertiserApplication::STATUS_SUBMITTED)->count(),
            'under_review' => AdvertiserApplication::where('status', AdvertiserApplication::STATUS_UNDER_REVIEW)->count(),
            'approved' => AdvertiserApplication::where('status', AdvertiserApplication::STATUS_APPROVED)->count(),
            'rejected' => AdvertiserApplication::where('status', AdvertiserApplication::STATUS_REJECTED)->count(),
        ];

        $subStats = [
            'active' => AdvertiserSubscription::where('status', AdvertiserSubscription::STATUS_ACTIVE)->count(),
            'expired' => AdvertiserSubscription::where('status', AdvertiserSubscription::STATUS_EXPIRED)->count(),
            'pending_payment' => AdvertiserSubscription::where('status', AdvertiserSubscription::STATUS_PENDING_PAYMENT)->count(),
        ];

        $paymentStats = [
            'captured_count' => AdvertiserPayment::where('status', AdvertiserPayment::STATUS_CAPTURED)->count(),
            'captured_amount' => (string) AdvertiserPayment::where('status', AdvertiserPayment::STATUS_CAPTURED)->sum('amount'),
            'failed_count' => AdvertiserPayment::where('status', AdvertiserPayment::STATUS_FAILED)->count(),
            'pending_count' => AdvertiserPayment::whereIn('status', [AdvertiserPayment::STATUS_CREATED, AdvertiserPayment::STATUS_PENDING])->count(),
            'currency' => 'INR',
        ];

        $totalViews = BrandPartnerView::count();
        $uvRow = BrandPartnerView::selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')->first();
        $uniqueViews = (int) ($uvRow->count ?? 0);

        $totalClicks = BrandPartnerClick::count();
        $ucRow = BrandPartnerClick::selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')->first();
        $uniqueClicks = (int) ($ucRow->count ?? 0);

        return $this->success([
            'applications' => $appStats,
            'subscriptions' => $subStats,
            'payments' => $paymentStats,
            'performance' => [
                'total_views' => $totalViews,
                'unique_views' => $uniqueViews,
                'total_clicks' => $totalClicks,
                'unique_clicks' => $uniqueClicks,
            ],
        ], 'Advertiser admin dashboard retrieved.');
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $query = AdvertiserSubscription::with(['application', 'brandPartner', 'package'])->orderByDesc('created_at');

        if ($request->has('status') && !empty($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        $perPage = (int) $request->query('per_page', 20);
        $subs = $query->paginate($perPage);

        return $this->success($subs, 'Subscriptions retrieved.');
    }

    public function payments(Request $request): JsonResponse
    {
        $query = AdvertiserPayment::with('subscription.application')->orderByDesc('created_at');

        if ($request->has('status') && !empty($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        $perPage = (int) $request->query('per_page', 20);
        $payments = $query->paginate($perPage);

        return $this->success($payments, 'Payments retrieved.');
    }

    public function subscriptionDetails(string $id): JsonResponse
    {
        $sub = AdvertiserSubscription::with(['application.assets', 'brandPartner', 'payments', 'package'])->find($id);
        if (!$sub) {
            return $this->error('Subscription not found.', 404);
        }

        return $this->success($sub, 'Subscription details retrieved.');
    }

    public function paymentDetails(string $id): JsonResponse
    {
        $payment = AdvertiserPayment::with('subscription.application')->find($id);
        if (!$payment) {
            return $this->error('Payment not found.', 404);
        }

        return $this->success($payment, 'Payment details retrieved.');
    }

    public function reconcilePayment(Request $request, string $id): JsonResponse
    {
        $payment = AdvertiserPayment::find($id);
        if (!$payment) {
            return $this->error('Payment not found.', 404);
        }

        $reconciled = $this->paymentService->reconcilePayment($payment);

        return $this->success([
            'payment_id' => $reconciled->id,
            'status' => $reconciled->status,
        ], 'Payment reconciliation completed.');
    }

    public function bannerConfig(): JsonResponse
    {
        return $this->success([
            'enabled' => true,
            'title' => 'Become a Brand Partner',
            'description' => 'Grow your business with Peers Global Unity.',
            'cta_text' => 'Apply Now',
            'destination' => 'advertiser_packages',
        ], 'Banner settings retrieved.');
    }

    public function updateBannerConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'cta_text' => ['required', 'string', 'max:50'],
            'destination' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->success($validated, 'Banner configuration saved successfully.');
    }

    // ==========================================
    // Admin Campaign Management
    // ==========================================

    public function campaigns(Request $request): JsonResponse
    {
        $query = AdvertiserCampaign::with(['application', 'subscription', 'user', 'campaignAds.ad'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->query('q'));
            $query->where(function ($b) use ($q) {
                $b->where('name', 'ILIKE', "%{$q}%")
                    ->orWhere('campaign_number', 'ILIKE', "%{$q}%")
                    ->orWhereHas('application', fn ($sub) => $sub->where('company_name', 'ILIKE', "%{$q}%"));
            });
        }

        $perPage = (int) $request->query('per_page', 20);
        $campaigns = $query->paginate($perPage);

        return $this->success($campaigns, 'Campaigns retrieved successfully.');
    }

    public function showCampaign(string $id): JsonResponse
    {
        $campaign = AdvertiserCampaign::with([
            'application.assets',
            'subscription.package',
            'user',
            'campaignAds.ad',
            'statusHistory.user',
            'reviewer',
        ])->find($id);

        if (!$campaign) {
            return $this->error('Campaign not found.', 404);
        }

        $analytics = $this->analyticsService->getCampaignAnalytics($campaign);

        return $this->success([
            'campaign' => $campaign,
            'analytics' => $analytics,
        ], 'Campaign details retrieved successfully.');
    }

    public function reviewCampaign(Request $request, string $id): JsonResponse
    {
        $campaign = AdvertiserCampaign::find($id);
        if (!$campaign) {
            return $this->error('Campaign not found.', 404);
        }

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approved,changes_requested,rejected'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $this->campaignService->reviewCampaign(
            $campaign,
            $validated['decision'],
            $validated['notes'] ?? null,
            $request->user()
        );

        return $this->success([
            'id' => $updated->id,
            'status' => $updated->status,
            'reviewed_at' => $updated->reviewed_at?->toIso8601String(),
        ], "Campaign {$validated['decision']} successfully.");
    }

    public function publishCampaign(Request $request, string $id): JsonResponse
    {
        $campaign = AdvertiserCampaign::find($id);
        if (!$campaign) {
            return $this->error('Campaign not found.', 404);
        }

        $ad = $this->publicationService->publishCampaignAd($campaign, $request->user());

        return $this->success([
            'campaign_id' => $campaign->id,
            'ad_id' => $ad->id,
            'status' => $campaign->status,
        ], 'Campaign published to feed successfully.');
    }

    public function pauseCampaign(Request $request, string $id): JsonResponse
    {
        $campaign = AdvertiserCampaign::find($id);
        if (!$campaign) {
            return $this->error('Campaign not found.', 404);
        }

        $reason = $request->input('reason', 'Paused by admin');
        $this->campaignService->pauseCampaign($campaign, $reason, $request->user());

        return $this->success([
            'campaign_id' => $campaign->id,
            'status' => AdvertiserCampaign::STATUS_PAUSED,
        ], 'Campaign paused successfully.');
    }

    public function resumeCampaign(Request $request, string $id): JsonResponse
    {
        $campaign = AdvertiserCampaign::find($id);
        if (!$campaign) {
            return $this->error('Campaign not found.', 404);
        }

        $this->campaignService->resumeCampaign($campaign, $request->user());

        return $this->success([
            'campaign_id' => $campaign->id,
            'status' => AdvertiserCampaign::STATUS_ACTIVE,
        ], 'Campaign resumed successfully.');
    }

    public function analyticsSummary(Request $request): JsonResponse
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : null;
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : null;

        $summary = $this->analyticsService->getAdminAdvertiserSummary($from, $to);

        return $this->success($summary, 'Admin advertiser analytics summary retrieved.');
    }
}
