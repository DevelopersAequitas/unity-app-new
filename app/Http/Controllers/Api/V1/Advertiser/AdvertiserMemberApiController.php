<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Advertiser;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\AdvertiserApplication;
use App\Models\AdvertiserSubscription;
use App\Services\Advertiser\AdvertiserAnalyticsService;
use App\Services\Advertiser\AdvertiserApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertiserMemberApiController extends BaseApiController
{
    public function __construct(
        protected AdvertiserApplicationService $applicationService,
        protected AdvertiserAnalyticsService $analyticsService
    ) {}

    /**
     * API 9: Get my applications
     * GET /api/v1/advertiser/my-applications
     */
    public function myApplications(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AdvertiserApplication::where('user_id', $user->id)
            ->with(['package', 'assets'])
            ->orderByDesc('created_at');

        if ($request->has('status') && !empty($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        $perPage = (int) $request->query('per_page', 20);
        $applications = $query->paginate($perPage);

        $transformed = $applications->through(function ($app) {
            $nextAction = match ($app->status) {
                AdvertiserApplication::STATUS_PENDING_EMAIL_VERIFICATION => 'verify_email',
                AdvertiserApplication::STATUS_CHANGES_REQUESTED => 'update_corrections',
                AdvertiserApplication::STATUS_APPROVED => 'complete_payment',
                default => 'await_review',
            };

            return [
                'application_id' => $app->id,
                'application_number' => $app->application_number,
                'company_name' => $app->company_name,
                'package_name' => $app->requested_package_name,
                'package_price' => $app->requested_package_price,
                'currency' => $app->requested_currency,
                'status' => $app->status,
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
        $application = AdvertiserApplication::with(['package', 'assets', 'statusHistory', 'subscriptions.payments'])->find($id);

        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        // Ownership check
        if ($application->user_id !== $user->id && strtolower((string)$application->applicant_email) !== strtolower((string)$user->email)) {
            return $this->error('You are not authorized to view this application.', 403);
        }

        $nextAction = match ($application->status) {
            AdvertiserApplication::STATUS_PENDING_EMAIL_VERIFICATION => 'verify_email',
            AdvertiserApplication::STATUS_CHANGES_REQUESTED => 'update_corrections',
            AdvertiserApplication::STATUS_APPROVED => 'complete_payment',
            default => 'await_review',
        };

        return $this->success([
            'application_id' => $application->id,
            'application_number' => $application->application_number,
            'status' => $application->status,
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
}
