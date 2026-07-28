<?php

declare(strict_types=1);

namespace App\Services\BrandPartners;

use App\Models\BrandPartner;
use App\Models\BrandPartnerCategory;
use App\Models\BrandPartnerClick;
use App\Models\BrandPartnerSaved;
use App\Models\BrandPartnerView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BrandPartnerAnalyticsService
{
    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getDashboardStats(): array
    {
        $now = Carbon::now();

        $hasPartnersTable = $this->hasTable('brand_partners');
        $hasViewsTable = $this->hasTable('brand_partner_views');
        $hasClicksTable = $this->hasTable('brand_partner_clicks');
        $hasSavedTable = $this->hasTable('brand_partner_saved');

        // Cards statistics
        $totalPartners = $hasPartnersTable ? BrandPartner::count() : 0;
        $featuredPartners = $hasPartnersTable ? BrandPartner::where('is_featured', true)->count() : 0;
        $sponsoredPartners = $hasPartnersTable ? BrandPartner::where('is_sponsored', true)->count() : 0;

        $activeOffers = $hasPartnersTable ? BrandPartner::where('is_active', true)
            ->whereNotNull('offer_title')
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', $now);
            })
            ->count() : 0;

        $expiredOffers = $hasPartnersTable ? BrandPartner::whereNotNull('offer_title')
            ->where('valid_to', '<', $now)
            ->count() : 0;

        $inactivePartners = $hasPartnersTable ? BrandPartner::where('is_active', false)->count() : 0;

        // Views tracking
        $totalViews = $hasViewsTable ? BrandPartnerView::count() : 0;
        $uniqueViews = 0;
        if ($hasViewsTable) {
            $uvRow = BrandPartnerView::selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')->first();
            $uniqueViews = (int) ($uvRow->count ?? 0);
        }

        // Clicks tracking
        $totalClicks = $hasClicksTable ? BrandPartnerClick::count() : 0;
        $uniqueClicks = 0;
        if ($hasClicksTable) {
            $ucRow = BrandPartnerClick::selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')->first();
            $uniqueClicks = (int) ($ucRow->count ?? 0);
        }

        // Force unique clicks to be <= unique views for mathematical sanity
        if ($uniqueClicks > $uniqueViews) {
            $uniqueClicks = $uniqueViews;
        }

        $totalWebsiteClicks = $hasClicksTable ? BrandPartnerClick::where('click_type', 'website')->count() : 0;
        $uniqueWebsiteClicks = 0;
        if ($hasClicksTable) {
            $uwcRow = BrandPartnerClick::where('click_type', 'website')
                ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
                ->first();
            $uniqueWebsiteClicks = (int) ($uwcRow->count ?? 0);
        }

        $totalRedemptions = $hasClicksTable ? BrandPartnerClick::where('click_type', 'redeem')->count() : 0;
        $savedPartners = $hasSavedTable ? BrandPartnerSaved::count() : 0;

        // CTR Safety Validation
        $ctr = 0;
        if ($uniqueViews > 0) {
            $ctr = ($uniqueClicks / $uniqueViews) * 100;
        }
        if ($ctr > 100) {
            Log::warning("Brand Partners CTR calculation exceeded 100%: {$ctr}%. Capping to 100%. Unique Clicks: {$uniqueClicks}, Unique Views: {$uniqueViews}");
            $ctr = 100;
        }
        $ctr = round($ctr, 2);

        // Conversion Rate Safety Validation
        $conversionRate = 0;
        if ($uniqueClicks > 0) {
            $conversionRate = ($totalRedemptions / $uniqueClicks) * 100;
        }
        if ($conversionRate > 100) {
            Log::warning("Brand Partners Conversion Rate exceeded 100%: {$conversionRate}%. Capping to 100%. Redemptions: {$totalRedemptions}, Unique Clicks: {$uniqueClicks}");
            $conversionRate = 100;
        }
        $conversionRate = round($conversionRate, 2);

        // Top Performing Partner (Most Clicked)
        $topPartnerRow = null;
        $topPerformingPartner = null;
        if ($hasClicksTable && $hasPartnersTable) {
            $topPartnerRow = BrandPartnerClick::select('brand_partner_id', DB::raw('COUNT(*) as click_count'))
                ->groupBy('brand_partner_id')
                ->orderByDesc('click_count')
                ->first();
            $topPerformingPartner = $topPartnerRow ? BrandPartner::find($topPartnerRow->brand_partner_id) : null;
        }

        // Most Saved Partner
        $mostSavedRow = null;
        $mostSavedPartner = null;
        if ($hasSavedTable && $hasPartnersTable) {
            $mostSavedRow = BrandPartnerSaved::select('brand_partner_id', DB::raw('COUNT(*) as save_count'))
                ->groupBy('brand_partner_id')
                ->orderByDesc('save_count')
                ->first();
            $mostSavedPartner = $mostSavedRow ? BrandPartner::find($mostSavedRow->brand_partner_id) : null;
        }

        return [
            'total_partners' => $totalPartners,
            'featured_partners' => $featuredPartners,
            'sponsored_partners' => $sponsoredPartners,
            'active_offers' => $activeOffers,
            'expired_offers' => $expiredOffers,
            'inactive_partners' => $inactivePartners,
            'total_views' => $totalViews,
            'unique_views' => $uniqueViews,
            'total_clicks' => $totalClicks,
            'unique_clicks' => $uniqueClicks,
            'total_website_clicks' => $totalWebsiteClicks,
            'unique_website_clicks' => $uniqueWebsiteClicks,
            'total_redemptions' => $totalRedemptions,
            'saved_partners' => $savedPartners,
            'ctr' => $ctr,
            'conversion_rate' => $conversionRate,
            'top_performing_partner' => $topPerformingPartner,
            'top_performing_clicks' => $topPartnerRow->click_count ?? 0,
            'most_saved_partner' => $mostSavedPartner,
            'most_saved_count' => $mostSavedRow->save_count ?? 0,
        ];
    }

    public function getDashboardCharts(): array
    {
        $days30Ago = Carbon::now()->subDays(30)->startOfDay();
        $months6Ago = Carbon::now()->subMonths(6)->startOfMonth();

        $hasViewsTable = $this->hasTable('brand_partner_views');
        $hasClicksTable = $this->hasTable('brand_partner_clicks');
        $hasCategoriesTable = $this->hasTable('brand_partner_categories');
        $hasPartnersTable = $this->hasTable('brand_partners');

        // Daily traffic (views and clicks for the last 30 days)
        $dailyViews = $hasViewsTable ? BrandPartnerView::selectRaw('DATE(viewed_at) as date, COUNT(*) as count')
            ->where('viewed_at', '>=', $days30Ago)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray() : [];

        $dailyClicks = $hasClicksTable ? BrandPartnerClick::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $days30Ago)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray() : [];

        // Fill in missing dates for the last 30 days
        $trafficChart = [];
        for ($i = 30; $i >= 0; $i--) {
            $dt = Carbon::now()->subDays($i);
            $dateKey = $dt->format('Y-m-d');
            $trafficChart[] = [
                'date' => $dt->format('d M'),
                'views' => (int) ($dailyViews[$dateKey] ?? 0),
                'clicks' => (int) ($dailyClicks[$dateKey] ?? 0),
            ];
        }

        // Top Categories by partners count
        $topCategories = ($hasCategoriesTable && $hasPartnersTable) ? BrandPartnerCategory::withCount('brandPartners')
            ->orderByDesc('brand_partners_count')
            ->limit(5)
            ->get()
            ->map(fn ($cat) => [
                'name' => $cat->name,
                'count' => $cat->brand_partners_count,
            ])
            ->toArray() : [];

        // Monthly performance (last 6 months)
        $isSqlite = DB::getDriverName() === 'sqlite';
        $viewMonthExpr = $isSqlite ? "strftime('%Y-%m', viewed_at)" : "TO_CHAR(viewed_at, 'YYYY-MM')";
        $clickMonthExpr = $isSqlite ? "strftime('%Y-%m', created_at)" : "TO_CHAR(created_at, 'YYYY-MM')";

        $monthlyViews = $hasViewsTable ? BrandPartnerView::selectRaw("{$viewMonthExpr} as month, COUNT(*) as count")
            ->where('viewed_at', '>=', $months6Ago)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month')
            ->toArray() : [];

        $monthlyClicks = $hasClicksTable ? BrandPartnerClick::selectRaw("{$clickMonthExpr} as month, COUNT(*) as count")
            ->where('created_at', '>=', $months6Ago)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month')
            ->toArray() : [];

        $monthlyPerformance = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStr = Carbon::now()->subMonths($i)->format('Y-m');
            $monthLabel = Carbon::now()->subMonths($i)->format('M Y');
            $monthlyPerformance[] = [
                'month' => $monthLabel,
                'views' => (int) ($monthlyViews[$monthStr] ?? 0),
                'clicks' => (int) ($monthlyClicks[$monthStr] ?? 0),
            ];
        }

        return [
            'traffic_chart' => $trafficChart,
            'top_categories' => $topCategories,
            'monthly_performance' => $monthlyPerformance,
        ];
    }

    public function getPartnerAnalytics(string $partnerId): array
    {
        $hasViewsTable = $this->hasTable('brand_partner_views');
        $hasClicksTable = $this->hasTable('brand_partner_clicks');
        $hasSavedTable = $this->hasTable('brand_partner_saved');

        $views = $hasViewsTable ? BrandPartnerView::where('brand_partner_id', $partnerId)->count() : 0;
        $uniqueViews = 0;
        if ($hasViewsTable) {
            $uvRow = BrandPartnerView::where('brand_partner_id', $partnerId)
                ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
                ->first();
            $uniqueViews = (int) ($uvRow->count ?? 0);
        }

        $totalClicks = 0;
        $uniqueClicks = 0;
        $websiteClicks = 0;
        $redeems = 0;
        $shares = 0;
        $calls = 0;
        $emails = 0;

        if ($hasClicksTable) {
            $clicksQuery = BrandPartnerClick::where('brand_partner_id', $partnerId);
            $totalClicks = (clone $clicksQuery)->count();
            $ucRow = (clone $clicksQuery)
                ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
                ->first();
            $uniqueClicks = (int) ($ucRow->count ?? 0);

            // Force unique clicks to be <= unique views for mathematical sanity
            if ($uniqueClicks > $uniqueViews) {
                $uniqueClicks = $uniqueViews;
            }

            $websiteClicks = (clone $clicksQuery)->where('click_type', 'website')->count();
            $redeems = (clone $clicksQuery)->where('click_type', 'redeem')->count();
            $shares = (clone $clicksQuery)->where('click_type', 'share')->count();
            $calls = (clone $clicksQuery)->where('click_type', 'call')->count();
            $emails = (clone $clicksQuery)->where('click_type', 'email')->count();
        }

        $saves = $hasSavedTable ? BrandPartnerSaved::where('brand_partner_id', $partnerId)->count() : 0;

        // Unique CTR Safety Validation
        $ctr = 0;
        if ($uniqueViews > 0) {
            $ctr = ($uniqueClicks / $uniqueViews) * 100;
        }
        if ($ctr > 100) {
            Log::warning("Brand Partner {$partnerId} CTR calculation exceeded 100%: {$ctr}%. Capping to 100%.");
            $ctr = 100;
        }
        $ctr = round($ctr, 2);

        // Conversion Rate Safety Validation
        $conversionRate = 0;
        if ($uniqueClicks > 0) {
            $conversionRate = ($redeems / $uniqueClicks) * 100;
        }
        if ($conversionRate > 100) {
            Log::warning("Brand Partner {$partnerId} Conversion Rate exceeded 100%: {$conversionRate}%. Capping to 100%.");
            $conversionRate = 100;
        }
        $conversionRate = round($conversionRate, 2);

        return [
            'views' => $views,
            'unique_views' => $uniqueViews,
            'clicks' => $totalClicks,
            'unique_clicks' => $uniqueClicks,
            'website_clicks' => $websiteClicks,
            'redeem_clicks' => $redeems,
            'shares' => $shares,
            'calls' => $calls,
            'emails' => $emails,
            'saves' => $saves,
            'ctr' => $ctr,
            'conversion_rate' => $conversionRate,
        ];
    }
}
