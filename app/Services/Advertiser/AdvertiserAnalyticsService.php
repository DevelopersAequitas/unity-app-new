<?php

declare(strict_types=1);

namespace App\Services\Advertiser;

use App\Models\AdvertiserApplication;
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
                    'unique_views' => $uniqueViews,
                    'total_clicks' => $totalClicks,
                    'unique_clicks' => $uniqueClicks,
                    'ctr_percent' => $ctr,
                ],
            ];
        }

        return [
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
                'unique_views' => $uv,
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
                'unique_views' => $uniqueViews,
                'total_clicks' => $totalClicks,
                'unique_clicks' => $uniqueClicks,
                'website_clicks' => $websiteClicks,
                'call_clicks' => $callClicks,
                'email_clicks' => $emailClicks,
                'whatsapp_clicks' => $whatsappClicks,
                'share_clicks' => $shareClicks,
                'coupon_redemptions' => $couponRedemptions,
                'ctr_percent' => $ctr,
                'conversion_rate_percent' => $conversionRate,
            ],
            'daily' => $dailyData,
        ];
    }
}
