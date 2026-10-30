<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Advertiser;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\AdvertiserApplication;
use App\Models\AdvertiserCampaign;
use App\Models\AdvertiserPayment;
use App\Models\AdvertiserSubscription;
use App\Services\Advertiser\AdvertiserAnalyticsService;
use App\Services\Advertiser\AdvertiserApplicationService;
use App\Services\Advertiser\AdvertiserCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdvertiserMemberApiController extends BaseApiController
{
    public function __construct(
        protected AdvertiserApplicationService $applicationService,
        protected AdvertiserAnalyticsService $analyticsService,
        protected AdvertiserCampaignService $campaignService
    ) {}

    /**
     * API 9: Get my applications
     * GET /api/v1/advertiser/my-applications
     */
    public function myApplications(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AdvertiserApplication::where('user_id', $user->id)
            ->with(['package', 'assets', 'subscriptions.payments'])
            ->orderByDesc('created_at');

        if ($request->has('status') && !empty($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        $perPage = (int) $request->query('per_page', 20);
        $applications = $query->paginate($perPage);

        $transformed = $applications->through(function ($app) {
            $sub = $app->subscriptions->first();
            $latestPayment = $sub?->payments->first();

            $isPaid = false;
            if (
                $app->status === AdvertiserApplication::STATUS_ACTIVE ||
                $sub?->status === AdvertiserSubscription::STATUS_ACTIVE ||
                $latestPayment?->status === AdvertiserPayment::STATUS_CAPTURED
            ) {
                $isPaid = true;
            }

            $effectiveStatus = $isPaid ? AdvertiserApplication::STATUS_ACTIVE : $app->status;

            $paymentStatus = $latestPayment?->status ?? (
                $isPaid
                    ? AdvertiserPayment::STATUS_CAPTURED
                    : ($effectiveStatus === AdvertiserApplication::STATUS_APPROVED ? 'pending' : null)
            );

            $nextAction = match ($effectiveStatus) {
                AdvertiserApplication::STATUS_PENDING_EMAIL_VERIFICATION => 'verify_email',
                AdvertiserApplication::STATUS_CHANGES_REQUESTED => 'update_corrections',
                AdvertiserApplication::STATUS_APPROVED => 'complete_payment',
                AdvertiserApplication::STATUS_ACTIVE => null,
                default => 'await_review',
            };

            return [
                'application_id' => $app->id,
                'application_number' => $app->application_number,
                'company_name' => $app->company_name,
                'package_name' => $app->requested_package_name,
                'package_price' => $app->requested_package_price,
                'currency' => $app->requested_currency,
                'status' => $effectiveStatus,
                'payment_status' => $paymentStatus,
                'is_paid' => $isPaid,
                'subscription_id' => $sub?->id,
                'valid_from' => $sub?->starts_at?->toIso8601String(),
                'valid_to' => $sub?->expires_at?->toIso8601String(),
                'submitted_at' => $app->submitted_at?->toIso8601String(),
                'next_action' => $nextAction,
            ];
        });

        return $this->success($transformed, 'My applications retrieved successfully.');
    }

    /**
     * API 8: Get application details
     * GET /api/v1/advertiser/applications/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $application = AdvertiserApplication::with([
            'package',
            'assets',
            'statusHistory',
            'subscriptions.payments',
        ])->find($id);

        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        // Ownership check
        if ($application->user_id !== $user->id && strtolower((string)$application->applicant_email) !== strtolower((string)$user->email)) {
            return $this->error('You are not authorized to view this application.', 403);
        }

        $sub = $application->subscriptions->first();
        $latestPayment = $sub?->payments->first();

        $isPaid = false;
        if (
            $application->status === AdvertiserApplication::STATUS_ACTIVE ||
            $sub?->status === AdvertiserSubscription::STATUS_ACTIVE ||
            $latestPayment?->status === AdvertiserPayment::STATUS_CAPTURED
        ) {
            $isPaid = true;
        }

        $effectiveStatus = $isPaid ? AdvertiserApplication::STATUS_ACTIVE : $application->status;

        $paymentStatus = $latestPayment?->status ?? (
            $isPaid
                ? AdvertiserPayment::STATUS_CAPTURED
                : ($effectiveStatus === AdvertiserApplication::STATUS_APPROVED ? 'pending' : null)
        );

        $nextAction = match ($effectiveStatus) {
            AdvertiserApplication::STATUS_PENDING_EMAIL_VERIFICATION => 'verify_email',
            AdvertiserApplication::STATUS_CHANGES_REQUESTED => 'update_corrections',
            AdvertiserApplication::STATUS_APPROVED => 'complete_payment',
            AdvertiserApplication::STATUS_ACTIVE => null,
            default => 'await_review',
        };

        return $this->success([
            'application_id' => $application->id,
            'application_number' => $application->application_number,
            'status' => $effectiveStatus,
            'payment_status' => $paymentStatus,
            'is_paid' => $isPaid,
            'subscription_id' => $sub?->id,
            'valid_from' => $sub?->starts_at?->toIso8601String(),
            'valid_to' => $sub?->expires_at?->toIso8601String(),
            'source' => $application->source,
            'applicant' => [
                'full_name' => $application->applicant_name,
                'email' => $application->applicant_email,
                'mobile_number' => $application->applicant_phone,
                'email_verified' => (bool) $application->applicant_email_verified,
            ],
            'company' => [
                'company_name' => $application->company_name,
                'legal_business_name' => $application->company_legal_name,
                'business_type' => $application->company_type,
                'industry' => $application->industry,
                'website' => $application->company_website,
                'official_email' => $application->company_email,
                'phone' => $application->company_phone,
                'whatsapp' => $application->whatsapp_number,
                'address' => $application->address_line,
                'city' => $application->city,
                'state' => $application->state,
                'country' => $application->country,
                'postal_code' => $application->postal_code,
                'contact_person_name' => $application->contact_person_name,
                'contact_person_designation' => $application->contact_person_designation,
                'description' => $application->brand_description,
            ],
            'campaign' => [
                'advertising_objective' => $application->advertising_objective,
                'target_audience' => $application->target_audience,
                'target_locations' => $application->target_locations,
                'preferred_start_date' => $application->requested_start_date?->format('Y-m-d'),
                'additional_requirements' => $application->additional_requirements,
            ],
            'package' => [
                'id' => $application->package_id,
                'name' => $application->requested_package_name,
                'duration_months' => $application->requested_duration_months,
                'price' => $application->requested_package_price,
                'currency' => $application->requested_currency,
            ],
            'assets' => $application->assets->map(fn ($a) => [
                'asset_id' => $a->id,
                'asset_type' => $a->asset_type,
                'url' => $a->url,
                'original_filename' => $a->original_filename,
                'review_status' => $a->review_status,
                'review_notes' => $a->review_notes,
            ]),
            'feedback' => $application->admin_notes,
            'applicant_response' => $application->applicant_response,
            'status_history' => $application->statusHistory->map(fn ($h) => [
                'status' => $h->new_status,
                'notes' => $h->notes,
                'created_at' => $h->created_at->toIso8601String(),
            ]),
            'next_action' => $nextAction,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
        ], 'Application details retrieved successfully.');
    }

    /**
     * API 10: Update application details or submit corrections
     * PATCH /api/v1/advertiser/applications/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $application = AdvertiserApplication::find($id);

        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        if ($application->user_id !== $user->id) {
            return $this->error('Unauthorized access.', 403);
        }

        $validated = $request->validate([
            'company.company_name' => ['nullable', 'string', 'max:200'],
            'company.website' => ['nullable', 'url', 'max:2048'],
            'company.description' => ['nullable', 'string'],
            'company.phone' => ['nullable', 'string', 'max:30'],
            'company.whatsapp' => ['nullable', 'string', 'max:30'],
            'campaign.advertising_objective' => ['nullable', 'string', 'max:100'],
            'campaign.additional_requirements' => ['nullable', 'string'],
            'applicant_response' => ['nullable', 'string'],
            'resubmit' => ['nullable', 'boolean'],
        ]);

        $resubmit = (bool) ($validated['resubmit'] ?? false);

        $updated = $this->applicationService->updateApplicationCorrections(
            application: $application,
            data: $validated,
            resubmit: $resubmit
        );

        return $this->success([
            'application_id' => $updated->id,
            'status' => $updated->status,
            'next_action' => ($updated->status === AdvertiserApplication::STATUS_SUBMITTED) ? 'await_review' : 'update_corrections',
        ], 'Application updated successfully.');
    }

    /**
     * API 11: Withdraw application
     * POST /api/v1/advertiser/applications/{id}/withdraw
     */
    public function withdraw(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $application = AdvertiserApplication::find($id);

        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        if ($application->user_id !== $user->id) {
            return $this->error('Unauthorized access.', 403);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $withdrawn = $this->applicationService->withdrawApplication($application, $validated['reason'] ?? null);

        return $this->success([
            'application_id' => $withdrawn->id,
            'status' => $withdrawn->status,
        ], 'Application withdrawn successfully.');
    }

    /**
     * API 35: Link application to authenticated Peer
     * POST /api/v1/advertiser/applications/{id}/link-account
     */
    public function linkAccount(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $application = AdvertiserApplication::find($id);

        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        $linked = $this->applicationService->linkApplicationToUser($application, $user);

        return $this->success([
            'application_id' => $linked->id,
            'status' => $linked->status,
            'linked' => true,
        ], 'Application linked successfully to your Peer account.');
    }

    /**
     * API 12: Advertiser dashboard summary
     * GET /api/v1/advertiser/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $summary = $this->analyticsService->getAdvertiserDashboard($user);

        return $this->success($summary, 'Advertiser dashboard retrieved successfully.');
    }

    /**
     * API 13: Advertiser detailed analytics
     * GET /api/v1/advertiser/analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = $request->user();
        $partnerId = $request->query('brand_partner_id');

        if (!$partnerId) {
            // Find first partner linked to this user
            $sub = AdvertiserSubscription::where('user_id', $user->id)
                ->whereNotNull('brand_partner_id')
                ->first();
            $partnerId = $sub?->brand_partner_id;
        }

        if (!$partnerId) {
            return $this->error('No brand partner is currently associated with your account.', 404);
        }

        // Verify ownership
        $isOwner = AdvertiserSubscription::where('user_id', $user->id)
            ->where('brand_partner_id', $partnerId)
            ->exists() || AdvertiserApplication::where('user_id', $user->id)
            ->where('brand_partner_id', $partnerId)
            ->exists();

        if (!$isOwner) {
            return $this->error('Unauthorized access to this brand partner metrics.', 403);
        }

        $from = $request->query('from');
        $to = $request->query('to');
        $groupBy = (string) $request->query('group_by', 'day');

        $analytics = $this->analyticsService->getPartnerAnalytics($partnerId, $from, $to, $groupBy);

        return $this->success($analytics, 'Brand partner analytics retrieved successfully.');
    }

    /**
     * API 14: Get advertiser subscriptions
     * GET /api/v1/advertiser/subscriptions
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AdvertiserSubscription::where('user_id', $user->id)
            ->with(['package', 'brandPartner'])
            ->orderByDesc('created_at');

        if ($request->has('status') && !empty($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        $perPage = (int) $request->query('per_page', 20);
        $subscriptions = $query->paginate($perPage);

        $transformed = $subscriptions->through(fn ($sub) => [
            'subscription_id' => $sub->id,
            'application_id' => $sub->application_id,
            'brand_partner_id' => $sub->brand_partner_id,
            'package' => [
                'name' => $sub->package_name_snapshot,
                'duration_months' => $sub->duration_months,
                'price' => $sub->amount,
                'currency' => $sub->currency,
            ],
            'status' => $sub->status,
            'valid_from' => $sub->starts_at?->toIso8601String(),
            'valid_to' => $sub->expires_at?->toIso8601String(),
            'renewal_allowed' => ($sub->status === AdvertiserSubscription::STATUS_ACTIVE || $sub->status === AdvertiserSubscription::STATUS_EXPIRED),
        ]);

        return $this->success($transformed, 'Subscriptions retrieved successfully.');
    }

    /**
     * API 15: Get subscription details
     * GET /api/v1/advertiser/subscriptions/{id}
     */
    public function subscriptionDetails(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $subscription = AdvertiserSubscription::with(['payments', 'brandPartner'])->find($id);

        if (!$subscription) {
            return $this->error('Subscription not found.', 404);
        }

        if ($subscription->user_id !== $user->id) {
            return $this->error('Unauthorized access.', 403);
        }

        return $this->success([
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
            'package_snapshot' => [
                'name' => $subscription->package_name_snapshot,
                'duration_months' => $subscription->duration_months,
                'price' => $subscription->amount,
                'currency' => $subscription->currency,
            ],
            'valid_from' => $subscription->starts_at?->toIso8601String(),
            'valid_to' => $subscription->expires_at?->toIso8601String(),
            'payments' => $subscription->payments->map(fn ($p) => [
                'payment_id' => $p->id,
                'status' => $p->status,
                'amount' => $p->amount,
                'currency' => $p->currency,
                'captured_at' => $p->captured_at?->toIso8601String(),
            ]),
        ], 'Subscription details retrieved successfully.');
    }

    /**
     * List authenticated advertiser's campaigns
     * GET /api/v1/advertiser/campaigns
     */
    public function campaigns(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AdvertiserCampaign::where('user_id', $user->id)
            ->with(['subscription', 'campaignAds.ad'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $perPage = (int) $request->query('per_page', 20);
        $campaigns = $query->paginate($perPage);

        $transformed = $campaigns->through(fn ($c) => [
            'id' => $c->id,
            'campaign_number' => $c->campaign_number,
            'name' => $c->name,
            'objective' => $c->objective,
            'status' => $c->status,
            'cta_text' => $c->cta_text,
            'destination_url' => $c->destination_url,
            'requested_starts_at' => $c->requested_starts_at?->toIso8601String(),
            'requested_ends_at' => $c->requested_ends_at?->toIso8601String(),
            'published_at' => $c->published_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
        ]);

        return $this->success($transformed, 'Campaigns retrieved successfully.');
    }

    /**
     * Create campaign draft
     * POST /api/v1/advertiser/campaigns
     */
    public function createCampaign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'objective' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'placement_ids' => ['nullable', 'array'],
            'requested_placements' => ['nullable', 'array'],
            'asset_ids' => ['nullable', 'array'],
            'cta_text' => ['nullable', 'string', 'max:100'],
            'destination_url' => ['nullable', 'url', 'max:2048'],
            'target_audience' => ['nullable', 'string'],
            'target_locations' => ['nullable', 'array'],
            'requested_starts_at' => ['nullable', 'date'],
            'requested_ends_at' => ['nullable', 'date'],
            'subscription_id' => ['nullable', 'uuid'],
        ]);

        $campaign = $this->campaignService->createDraft($request->user(), $validated);

        return $this->success([
            'id' => $campaign->id,
            'campaign_number' => $campaign->campaign_number,
            'status' => $campaign->status,
            'name' => $campaign->name,
            'created_at' => $campaign->created_at?->toIso8601String(),
        ], 'Campaign draft created successfully.', 201);
    }

    /**
     * Get campaign details
     * GET /api/v1/advertiser/campaigns/{id}
     */
    public function campaignDetails(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $campaign = AdvertiserCampaign::with(['subscription', 'campaignAds.ad', 'application.assets'])
            ->find($id);

        if (!$campaign) {
            return $this->error('Campaign not found.', 404);
        }

        if ($campaign->user_id !== $user->id) {
            return $this->error('Unauthorized access.', 403);
        }

        return $this->success([
            'id' => $campaign->id,
            'campaign_number' => $campaign->campaign_number,
            'name' => $campaign->name,
            'objective' => $campaign->objective,
            'description' => $campaign->description,
            'status' => $campaign->status,
            'cta_text' => $campaign->cta_text,
            'destination_url' => $campaign->destination_url,
            'admin_notes' => $campaign->admin_notes,
            'applicant_feedback' => $campaign->applicant_feedback,
            'requested_placements' => $campaign->requested_placements,
            'target_audience' => $campaign->target_audience,
            'target_locations' => $campaign->target_locations,
            'requested_starts_at' => $campaign->requested_starts_at?->toIso8601String(),
            'requested_ends_at' => $campaign->requested_ends_at?->toIso8601String(),
            'approved_at' => $campaign->approved_at?->toIso8601String(),
            'published_at' => $campaign->published_at?->toIso8601String(),
            'subscription' => $campaign->subscription ? [
                'id' => $campaign->subscription->id,
                'package_name' => $campaign->subscription->package_name_snapshot,
                'status' => $campaign->subscription->status,
                'expires_at' => $campaign->subscription->expires_at?->toIso8601String(),
            ] : null,
            'ads' => $campaign->campaignAds->map(fn ($link) => [
                'ad_id' => $link->ad_id,
                'title' => $link->ad?->title,
                'placement' => $link->ad?->placement,
                'is_active' => $link->ad?->is_active ?? false,
                'status' => $link->status,
            ]),
        ], 'Campaign details retrieved successfully.');
    }

    /**
     * Update campaign draft
     * PATCH /api/v1/advertiser/campaigns/{id}
     */
    public function updateCampaign(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $campaign = AdvertiserCampaign::find($id);

        if (!$campaign) {
            return $this->error('Campaign not found.', 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'objective' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'placement_ids' => ['nullable', 'array'],
            'requested_placements' => ['nullable', 'array'],
            'asset_ids' => ['nullable', 'array'],
            'cta_text' => ['nullable', 'string', 'max:100'],
            'destination_url' => ['nullable', 'url', 'max:2048'],
            'target_audience' => ['nullable', 'string'],
            'target_locations' => ['nullable', 'array'],
            'requested_starts_at' => ['nullable', 'date'],
            'requested_ends_at' => ['nullable', 'date'],
        ]);

        $updated = $this->campaignService->updateDraft($campaign, $validated, $user);

        return $this->success([
            'id' => $updated->id,
            'status' => $updated->status,
            'updated_at' => $updated->updated_at?->toIso8601String(),
        ], 'Campaign draft updated successfully.');
    }

    /**
     * Submit campaign for review
     * POST /api/v1/advertiser/campaigns/{id}/submit
     */
    public function submitCampaign(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $campaign = AdvertiserCampaign::find($id);

        if (!$campaign) {
            return $this->error('Campaign not found.', 404);
        }

        $validated = $request->validate([
            'submission_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $submitted = $this->campaignService->submitForReview($campaign, $validated['submission_note'] ?? null, $user);

        return $this->success([
            'id' => $submitted->id,
            'status' => $submitted->status,
        ], 'Campaign submitted for review successfully.');
    }

    /**
     * Cancel campaign
     * POST /api/v1/advertiser/campaigns/{id}/cancel
     */
    public function cancelCampaign(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $campaign = AdvertiserCampaign::find($id);

        if (!$campaign) {
            return $this->error('Campaign not found.', 404);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $cancelled = $this->campaignService->cancelCampaign($campaign, $validated['reason'] ?? null, $user);

        return $this->success([
            'id' => $cancelled->id,
            'status' => $cancelled->status,
        ], 'Campaign cancelled successfully.');
    }

    /**
     * Get campaign performance analytics
     * GET /api/v1/advertiser/campaigns/{id}/analytics
     */
    public function campaignAnalytics(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $campaign = AdvertiserCampaign::find($id);

        if (!$campaign) {
            return $this->error('Campaign not found.', 404);
        }

        if ($campaign->user_id !== $user->id) {
            return $this->error('Unauthorized access.', 403);
        }

        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : null;
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : null;

        $stats = $this->analyticsService->getCampaignAnalytics($campaign, $from, $to);

        return $this->success($stats, 'Campaign analytics retrieved successfully.');
    }

    /**
     * Get advertiser-level timeseries analytics
     * GET /api/v1/advertiser/analytics/timeseries
     */
    public function analyticsTimeseries(Request $request): JsonResponse
    {
        $user = $request->user();
        $metric = (string) $request->query('metric', 'views');
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : now()->subDays(30);
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : now();
        $interval = (string) $request->query('interval', 'day');

        $timeseries = $this->analyticsService->getAdvertiserAnalyticsTimeseries($user, $metric, $from, $to, $interval);

        return $this->success($timeseries, 'Analytics timeseries retrieved successfully.');
    }
}
