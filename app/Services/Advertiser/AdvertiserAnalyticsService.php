<?php

declare(strict_types=1);

namespace App\Services\Advertiser;

use App\Models\Ad;
use App\Models\AdClick;
use App\Models\AdView;
use App\Models\AdvertiserApplication;
use App\Models\AdvertiserCampaign;
use App\Models\AdvertiserCampaignAd;
use App\Models\AdvertiserPayment;
use App\Models\AdvertiserSubscription;
use App\Models\BrandPartner;
use App\Models\BrandPartnerClick;
use App\Models\BrandPartnerSaved;
use App\Models\BrandPartnerView;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdvertiserAnalyticsService
{
    /**
     * Get dashboard summary for authenticated advertiser user.
     */
    public function getAdvertiserDashboard(User $user): array
    {
        $userId = $user->id;

        // Applications summary
        $userApplications = AdvertiserApplication::where('user_id', $userId)->get();

        $appSummary = [
            'total_applications' => $userApplications->count(),
            'pending_review' => $userApplications->whereIn('status', [AdvertiserApplication::STATUS_SUBMITTED, AdvertiserApplication::STATUS_UNDER_REVIEW])->count(),
            'changes_requested' => $userApplications->where('status', AdvertiserApplication::STATUS_CHANGES_REQUESTED)->count(),
            'approved' => $userApplications->where('status', AdvertiserApplication::STATUS_APPROVED)->count(),
            'rejected' => $userApplications->where('status', AdvertiserApplication::STATUS_REJECTED)->count(),
        ];

        // Subscriptions
        $subscriptions = AdvertiserSubscription::where('user_id', $userId)->with(['application', 'brandPartner'])->get();
        $activeSubscriptions = $subscriptions->where('status', AdvertiserSubscription::STATUS_ACTIVE)->count();
        $pendingPayments = $subscriptions->where('status', AdvertiserSubscription::STATUS_PENDING_PAYMENT)->count();

        // Linked Brand Partners
        $partners = [];
        $activePartnersCount = 0;

        foreach ($subscriptions as $sub) {
            $partner = $sub->brandPartner;
            if (!$partner) {
                continue;
            }

            if ($partner->is_active) {
                $activePartnersCount++;
            }

            // Calculate metrics
            $totalViews = BrandPartnerView::where('brand_partner_id', $partner->id)->count();
            $uvRow = BrandPartnerView::where('brand_partner_id', $partner->id)
                ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
                ->first();
            $uniqueViews = (int) ($uvRow->count ?? 0);

            $totalClicks = BrandPartnerClick::where('brand_partner_id', $partner->id)->count();
            $ucRow = BrandPartnerClick::where('brand_partner_id', $partner->id)
                ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
                ->first();
            $uniqueClicks = (int) ($ucRow->count ?? 0);

            if ($uniqueClicks > $uniqueViews) {
                $uniqueClicks = $uniqueViews;
            }

            $ctr = ($uniqueViews > 0) ? min(100, round(($uniqueClicks / $uniqueViews) * 100, 2)) : 0.0;

            $publicationStatus = $partner->is_active ? 'published' : 'unpublished';

            $partners[] = [
                'brand_partner_id' => $partner->id,
                'brand_name' => $partner->name,
                'application_id' => $sub->application_id,
                'application_status' => $sub->application?->status ?? 'approved',
                'subscription_status' => $sub->status,
                'publication_status' => $publicationStatus,
                'valid_from' => $sub->starts_at?->toIso8601String(),
                'valid_to' => $sub->expires_at?->toIso8601String(),
                'metrics' => [
                    'total_views' => $totalViews,
                    'impressions' => $totalViews,
                    'unique_views' => $uniqueViews,
                    'unique_reach' => $uniqueViews,
                    'total_clicks' => $totalClicks,
                    'clicks' => $totalClicks,
                    'unique_clicks' => $uniqueClicks,
                    'ctr_percent' => $ctr,
                    'ctr_percentage' => $ctr,
                ],
            ];
        }

        $primaryPartner = collect($partners)->firstWhere('publication_status', 'published') ?? ($partners[0] ?? null);
        $currentCampaign = null;
        $currentMetrics = null;

        if ($primaryPartner) {
            $sub = $subscriptions->firstWhere('brand_partner_id', $primaryPartner['brand_partner_id']);
            $app = $sub?->application ?? $userApplications->firstWhere('brand_partner_id', $primaryPartner['brand_partner_id']);

            $currentCampaign = [
                'company_name' => $app?->company_name ?? $primaryPartner['brand_name'],
                'brand_name' => $primaryPartner['brand_name'],
                'publication_status' => $primaryPartner['publication_status'],
                'valid_from' => $primaryPartner['valid_from'] ? Carbon::parse($primaryPartner['valid_from'])->format('Y-m-d') : null,
                'valid_to' => $primaryPartner['valid_to'] ? Carbon::parse($primaryPartner['valid_to'])->format('Y-m-d') : null,
            ];
            $currentMetrics = $primaryPartner['metrics'];
        } else {
            $currentMetrics = [
                'impressions' => 0,
                'total_views' => 0,
                'clicks' => 0,
                'total_clicks' => 0,
                'unique_reach' => 0,
                'unique_views' => 0,
                'unique_clicks' => 0,
                'ctr_percentage' => 0.0,
                'ctr_percent' => 0.0,
            ];
        }

        return [
            'current_campaign' => $currentCampaign,
            'metrics' => $currentMetrics,
            'application_summary' => $appSummary,
            'active_partners' => $activePartnersCount,
            'pending_payments' => $pendingPayments,
            'active_subscriptions' => $activeSubscriptions,
            'partners' => $partners,
        ];
    }

    /**
     * Get detailed analytics for a brand partner.
     */
    public function getPartnerAnalytics(
        string $partnerId,
        ?string $fromDate = null,
        ?string $toDate = null,
        string $groupBy = 'day'
    ): array {
        $tz = config('app.timezone', 'Asia/Kolkata');

        $start = $fromDate ? Carbon::parse($fromDate, $tz)->startOfDay() : now($tz)->subDays(30)->startOfDay();
        $end = $toDate ? Carbon::parse($toDate, $tz)->endOfDay() : now($tz)->endOfDay();

        // 1. Views
        $viewsQuery = BrandPartnerView::where('brand_partner_id', $partnerId)
            ->whereBetween('viewed_at', [$start->utc(), $end->utc()]);

        $totalViews = (clone $viewsQuery)->count();
        $uvRow = (clone $viewsQuery)
            ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
            ->first();
        $uniqueViews = (int) ($uvRow->count ?? 0);

        // 2. Clicks
        $clicksQuery = BrandPartnerClick::where('brand_partner_id', $partnerId)
            ->whereBetween('created_at', [$start->utc(), $end->utc()]);

        $totalClicks = (clone $clicksQuery)->count();
        $ucRow = (clone $clicksQuery)
            ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
            ->first();
        $uniqueClicks = (int) ($ucRow->count ?? 0);

        if ($uniqueClicks > $uniqueViews) {
            $uniqueClicks = $uniqueViews;
        }

        $websiteClicks = (clone $clicksQuery)->where('click_type', 'website')->count();
        $callClicks = (clone $clicksQuery)->where('click_type', 'call')->count();
        $emailClicks = (clone $clicksQuery)->where('click_type', 'email')->count();
        $whatsappClicks = (clone $clicksQuery)->where('click_type', 'whatsapp')->count();
        $shareClicks = (clone $clicksQuery)->where('click_type', 'share')->count();
        $couponRedemptions = (clone $clicksQuery)->where('click_type', 'redeem')->count();

        // Calculations
        $ctr = ($uniqueViews > 0) ? min(100, round(($uniqueClicks / $uniqueViews) * 100, 2)) : 0.0;
        $conversionRate = ($uniqueClicks > 0) ? min(100, round(($couponRedemptions / $uniqueClicks) * 100, 2)) : 0.0;

        // 3. Daily Breakdown
        $dailyData = [];
        $isSqlite = DB::getDriverName() === 'sqlite';
        $viewDateExpr = $isSqlite ? "date(viewed_at)" : "TO_CHAR(viewed_at AT TIME ZONE '{$tz}', 'YYYY-MM-DD')";
        $clickDateExpr = $isSqlite ? "date(created_at)" : "TO_CHAR(created_at AT TIME ZONE '{$tz}', 'YYYY-MM-DD')";

        $dailyViews = (clone $viewsQuery)
            ->selectRaw("{$viewDateExpr} as date, COUNT(*) as count")
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $dailyUniqueViews = (clone $viewsQuery)
            ->selectRaw("{$viewDateExpr} as date, COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count")
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $dailyClicks = (clone $clicksQuery)
            ->selectRaw("{$clickDateExpr} as date, COUNT(*) as count")
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $dailyUniqueClicks = (clone $clicksQuery)
            ->selectRaw("{$clickDateExpr} as date, COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count")
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dateStr = $cursor->format('Y-m-d');
            $v = (int) ($dailyViews[$dateStr] ?? 0);
            $uv = (int) ($dailyUniqueViews[$dateStr] ?? 0);
            $c = (int) ($dailyClicks[$dateStr] ?? 0);
            $uc = (int) ($dailyUniqueClicks[$dateStr] ?? 0);

            if ($uc > $uv) {
                $uc = $uv;
            }

            $dailyData[] = [
                'date' => $dateStr,
                'views' => $v,
                'impressions' => $v,
                'unique_views' => $uv,
                'unique_reach' => $uv,
                'clicks' => $c,
                'unique_clicks' => $uc,
            ];

            $cursor->addDay();
        }

        return [
            'brand_partner_id' => $partnerId,
            'period' => [
                'from' => $start->format('Y-m-d'),
                'to' => $end->format('Y-m-d'),
                'timezone' => $tz,
            ],
            'summary' => [
                'total_views' => $totalViews,
                'impressions' => $totalViews,
                'unique_views' => $uniqueViews,
                'unique_reach' => $uniqueViews,
                'total_clicks' => $totalClicks,
                'clicks' => $totalClicks,
                'unique_clicks' => $uniqueClicks,
                'website_clicks' => $websiteClicks,
                'call_clicks' => $callClicks,
                'email_clicks' => $emailClicks,
                'whatsapp_clicks' => $whatsappClicks,
                'share_clicks' => $shareClicks,
                'coupon_redemptions' => $couponRedemptions,
                'ctr_percent' => $ctr,
                'ctr_percentage' => $ctr,
                'conversion_rate_percent' => $conversionRate,
            ],
            'daily' => $dailyData,
        ];
    }

    /**
     * Get analytics for an individual campaign scoped strictly to its linked ads.
     */
    public function getCampaignAnalytics(AdvertiserCampaign $campaign, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $start = $from ? $from->copy()->startOfDay() : now()->subDays(30)->startOfDay();
        $end = $to ? $to->copy()->endOfDay() : now()->endOfDay();

        $adIds = AdvertiserCampaignAd::where('campaign_id', $campaign->id)->pluck('ad_id')->all();

        if (empty($adIds)) {
            return [
                'campaign_id' => $campaign->id,
                'period' => [
                    'from' => $start->format('Y-m-d'),
                    'to' => $end->format('Y-m-d'),
                ],
                'views' => 0,
                'unique_views' => 0,
                'clicks' => 0,
                'unique_clicks' => 0,
                'ctr_percent' => 0.0,
                'daily' => [],
            ];
        }

        // Total Views
        $viewsQuery = AdView::whereIn('ad_id', $adIds)
            ->whereBetween('viewed_at', [$start, $end]);
        $totalViews = $viewsQuery->count();

        // Unique Views
        $uvRow = AdView::whereIn('ad_id', $adIds)
            ->whereBetween('viewed_at', [$start, $end])
            ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
            ->first();
        $uniqueViews = (int) ($uvRow->count ?? 0);

        // Total Clicks
        $clicksQuery = AdClick::whereIn('ad_id', $adIds)
            ->whereBetween('created_at', [$start, $end]);
        $totalClicks = $clicksQuery->count();

        // Unique Clicks
        $ucRow = AdClick::whereIn('ad_id', $adIds)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
            ->first();
        $uniqueClicks = (int) ($ucRow->count ?? 0);

        if ($uniqueClicks > $uniqueViews) {
            $uniqueClicks = $uniqueViews;
        }

        $ctr = ($uniqueViews > 0) ? min(100.0, round(($uniqueClicks / $uniqueViews) * 100, 2)) : 0.0;

        // Daily breakdown
        $daily = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();

            $dayViews = AdView::whereIn('ad_id', $adIds)->whereBetween('viewed_at', [$dayStart, $dayEnd])->count();
            $dayClicks = AdClick::whereIn('ad_id', $adIds)->whereBetween('created_at', [$dayStart, $dayEnd])->count();

            $daily[] = [
                'date' => $cursor->format('Y-m-d'),
                'views' => $dayViews,
                'clicks' => $dayClicks,
            ];

            $cursor->addDay();
        }

        return [
            'campaign_id' => $campaign->id,
            'campaign_number' => $campaign->campaign_number,
            'campaign_name' => $campaign->name,
            'status' => $campaign->status,
            'period' => [
                'from' => $start->format('Y-m-d'),
                'to' => $end->format('Y-m-d'),
            ],
            'views' => $totalViews,
            'unique_views' => $uniqueViews,
            'clicks' => $totalClicks,
            'unique_clicks' => $uniqueClicks,
            'ctr_percent' => $ctr,
            'daily' => $daily,
        ];
    }

    /**
     * Get timeseries data for an advertiser across their owned campaigns.
     */
    public function getAdvertiserAnalyticsTimeseries(User $user, string $metric, Carbon $from, Carbon $to, string $interval = 'day'): array
    {
        $campaignIds = AdvertiserCampaign::where('user_id', $user->id)->pluck('id')->all();
        $adIds = AdvertiserCampaignAd::whereIn('campaign_id', $campaignIds)->pluck('ad_id')->all();

        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $points = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();

            $value = 0;
            if (!empty($adIds)) {
                if ($metric === 'views' || $metric === 'impressions') {
                    $value = AdView::whereIn('ad_id', $adIds)->whereBetween('viewed_at', [$dayStart, $dayEnd])->count();
                } else {
                    $value = AdClick::whereIn('ad_id', $adIds)->whereBetween('created_at', [$dayStart, $dayEnd])->count();
                }
            }

            $points[] = [
                'date' => $cursor->format('Y-m-d'),
                'value' => $value,
            ];

            $cursor->addDay();
        }

        return [
            'metric' => $metric,
            'interval' => $interval,
            'period' => [
                'from' => $start->format('Y-m-d'),
                'to' => $end->format('Y-m-d'),
            ],
            'points' => $points,
        ];
    }

    /**
     * Get system-wide advertiser summary for admin panel.
     */
    public function getAdminAdvertiserSummary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $appQuery = AdvertiserApplication::query();
        $paymentQuery = AdvertiserPayment::where('status', AdvertiserPayment::STATUS_CAPTURED);
        $campaignQuery = AdvertiserCampaign::query();

        if ($from && $to) {
            $appQuery->whereBetween('created_at', [$from, $to]);
            $paymentQuery->whereBetween('created_at', [$from, $to]);
            $campaignQuery->whereBetween('created_at', [$from, $to]);
        }

        $apps = $appQuery->get();
        $totalRevenue = (float) $paymentQuery->sum('amount');
        $capturedPaymentsCount = $paymentQuery->count();

        // Linked Ads aggregate
        $allCampaignAdIds = AdvertiserCampaignAd::pluck('ad_id')->unique()->all();

        $totalViews = 0;
        $uniqueViews = 0;
        $totalClicks = 0;
        $uniqueClicks = 0;

        if (!empty($allCampaignAdIds)) {
            $vQuery = AdView::whereIn('ad_id', $allCampaignAdIds);
            $cQuery = AdClick::whereIn('ad_id', $allCampaignAdIds);
            if ($from && $to) {
                $vQuery->whereBetween('viewed_at', [$from, $to]);
                $cQuery->whereBetween('created_at', [$from, $to]);
            }
            $totalViews = $vQuery->count();
            $totalClicks = $cQuery->count();

            $uvRow = (clone $vQuery)->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')->first();
            $uniqueViews = (int) ($uvRow->count ?? 0);

            $ucRow = (clone $cQuery)->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')->first();
            $uniqueClicks = (int) ($ucRow->count ?? 0);
        }

        $ctr = ($uniqueViews > 0) ? min(100.0, round(($uniqueClicks / $uniqueViews) * 100, 2)) : 0.0;

        return [
            'applications' => [
                'total' => $apps->count(),
                'submitted' => $apps->where('status', AdvertiserApplication::STATUS_SUBMITTED)->count(),
                'under_review' => $apps->where('status', AdvertiserApplication::STATUS_UNDER_REVIEW)->count(),
                'changes_requested' => $apps->where('status', AdvertiserApplication::STATUS_CHANGES_REQUESTED)->count(),
                'approved' => $apps->where('status', AdvertiserApplication::STATUS_APPROVED)->count(),
                'rejected' => $apps->where('status', AdvertiserApplication::STATUS_REJECTED)->count(),
            ],
            'subscriptions' => [
                'total' => AdvertiserSubscription::count(),
                'active' => AdvertiserSubscription::where('status', AdvertiserSubscription::STATUS_ACTIVE)->count(),
                'pending_payment' => AdvertiserSubscription::where('status', AdvertiserSubscription::STATUS_PENDING_PAYMENT)->count(),
                'expired' => AdvertiserSubscription::where('status', AdvertiserSubscription::STATUS_EXPIRED)->count(),
            ],
            'payments' => [
                'captured_amount' => number_format($totalRevenue, 2, '.', ''),
                'captured_count' => $capturedPaymentsCount,
                'currency' => 'INR',
            ],
            'campaigns' => [
                'total' => $campaignQuery->count(),
                'active' => (clone $campaignQuery)->where('status', AdvertiserCampaign::STATUS_ACTIVE)->count(),
                'pending_review' => (clone $campaignQuery)->where('status', AdvertiserCampaign::STATUS_PENDING_REVIEW)->count(),
                'paused' => (clone $campaignQuery)->where('status', AdvertiserCampaign::STATUS_PAUSED)->count(),
                'completed' => (clone $campaignQuery)->where('status', AdvertiserCampaign::STATUS_COMPLETED)->count(),
            ],
            'ads' => [
                'total_views' => $totalViews,
                'unique_views' => $uniqueViews,
                'total_clicks' => $totalClicks,
                'unique_clicks' => $uniqueClicks,
                'ctr_percent' => $ctr,
            ],
        ];
    }
}
