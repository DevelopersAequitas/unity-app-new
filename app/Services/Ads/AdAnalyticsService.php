<?php

namespace App\Services\Ads;

use App\Models\Ad;
use App\Models\AdClick;
use App\Models\AdView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AdAnalyticsService
{
    private function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getDashboardStats(): array
    {
        $now = Carbon::now();

        $hasIsActive = $this->hasColumn('ads', 'is_active');
        $hasStartsAt = $this->hasColumn('ads', 'starts_at');
        $hasEndsAt = $this->hasColumn('ads', 'ends_at');

        // Cards statistics
        $totalAds = Ad::count();

        if ($hasIsActive) {
            if ($hasStartsAt || $hasEndsAt) {
                $activeAds = Ad::currentlyVisible()->count();
            } else {
                $activeAds = Ad::where('is_active', true)->count();
            }
            $inactiveAds = Ad::where('is_active', false)->count();
            $scheduledAds = $hasStartsAt ? Ad::where('is_active', true)->where('starts_at', '>', $now)->count() : 0;
        } else {
            $activeAds = $totalAds;
            $inactiveAds = 0;
            $scheduledAds = 0;
        }

        $expiredAds = $hasEndsAt ? Ad::whereNotNull('ends_at')->where('ends_at', '<', $now)->count() : 0;

        // Views tracking
        $totalViews = AdView::count();
        $uniqueViewsRaw = AdView::selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
            ->first();
        $uniqueViews = (int) ($uniqueViewsRaw->count ?? 0);

        // Clicks tracking
        $totalClicks = AdClick::count();
        $uniqueClicksRaw = AdClick::selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
            ->first();
        $uniqueClicks = (int) ($uniqueClicksRaw->count ?? 0);

        // Force unique clicks to be <= unique views for mathematical sanity
        if ($uniqueClicks > $uniqueViews) {
            $uniqueClicks = $uniqueViews;
        }

        // CTR Safety Validation
        $ctr = 0;
        if ($uniqueViews > 0) {
            $ctr = ($uniqueClicks / $uniqueViews) * 100;
        }
        if ($ctr > 100) {
            Log::warning("Ads CTR calculation exceeded 100%: {$ctr}%. Capping to 100%. Unique Clicks: {$uniqueClicks}, Unique Views: {$uniqueViews}");
            $ctr = 100;
        }
        $ctr = round($ctr, 2);

        // Top Performing Ad (Most Clicked)
        $topAdRow = AdClick::select('ad_id', DB::raw('COUNT(*) as click_count'))
            ->groupBy('ad_id')
            ->orderByDesc('click_count')
            ->first();
        $topPerformingAd = $topAdRow ? Ad::find($topAdRow->ad_id) : null;

        // Most Viewed Ad
        $mostViewedRow = AdView::select('ad_id', DB::raw('COUNT(*) as view_count'))
            ->groupBy('ad_id')
            ->orderByDesc('view_count')
            ->first();
        $mostViewedAd = $mostViewedRow ? Ad::find($mostViewedRow->ad_id) : null;

        return [
            'total_ads' => $totalAds,
            'active_ads' => $activeAds,
            'inactive_ads' => $inactiveAds,
            'scheduled_ads' => $scheduledAds,
            'expired_ads' => $expiredAds,
            'total_views' => $totalViews,
            'unique_views' => $uniqueViews,
            'total_clicks' => $totalClicks,
            'unique_clicks' => $uniqueClicks,
            'ctr' => $ctr,
            'top_performing_ad' => $topPerformingAd,
            'top_performing_clicks' => $topAdRow->click_count ?? 0,
            'most_viewed_ad' => $mostViewedAd,
            'most_viewed_count' => $mostViewedRow->view_count ?? 0,
        ];
    }

    public function getDashboardCharts(): array
    {
        $days30Ago = Carbon::now()->subDays(30)->startOfDay();
        $months6Ago = Carbon::now()->subMonths(6)->startOfMonth();

        // Daily traffic (views and clicks for the last 30 days)
        $dailyViews = AdView::selectRaw('DATE(viewed_at) as date, COUNT(*) as count')
            ->where('viewed_at', '>=', $days30Ago)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        $dailyClicks = AdClick::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $days30Ago)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Fill in missing dates for the last 30 days
        $trafficChart = [];
        for ($i = 30; $i >= 0; $i--) {
            $dateStr = Carbon::now()->subDays($i)->format('Y-m-d');
            $trafficChart[] = [
                'date' => $dateStr,
                'views' => (int) ($dailyViews[$dateStr] ?? 0),
                'clicks' => (int) ($dailyClicks[$dateStr] ?? 0),
            ];
        }

        // Placement breakdown (only if column exists)
        $hasPlacement = $this->hasColumn('ads', 'placement');
        $placements = [];

        if ($hasPlacement) {
            $placements = Ad::selectRaw("COALESCE(placement, 'unassigned') as placement_name, COUNT(*) as count")
                ->groupBy('placement_name')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($item) => [
                    'name' => ucfirst($item->placement_name),
                    'count' => (int) $item->count,
                ])
                ->toArray();
        }

        // Top ads by engagement (used as fallback or secondary view)
        $topAdsByEngagement = Ad::query()
            ->withCount(['views', 'clicks'])
            ->get()
            ->map(function ($ad) {
                $views = (int) ($ad->views_count ?? 0);
                $clicks = (int) ($ad->clicks_count ?? 0);
                $ctr = $views > 0 ? round(($clicks / $views) * 100, 2) : 0;

                return [
                    'id' => $ad->id,
                    'title' => $ad->title ?? 'Untitled Ad',
                    'views' => $views,
                    'clicks' => $clicks,
                    'ctr' => $ctr,
                ];
            })
            ->sortByDesc('clicks')
            ->take(5)
            ->values()
            ->toArray();

        // Monthly performance (last 6 months)
        $isSqlite = DB::getDriverName() === 'sqlite';
        $viewMonthExpr = $isSqlite ? "strftime('%Y-%m', viewed_at)" : "TO_CHAR(viewed_at, 'YYYY-MM')";
        $clickMonthExpr = $isSqlite ? "strftime('%Y-%m', created_at)" : "TO_CHAR(created_at, 'YYYY-MM')";

        $monthlyViews = AdView::selectRaw("{$viewMonthExpr} as month, COUNT(*) as count")
            ->where('viewed_at', '>=', $months6Ago)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month')
            ->toArray();

        $monthlyClicks = AdClick::selectRaw("{$clickMonthExpr} as month, COUNT(*) as count")
            ->where('created_at', '>=', $months6Ago)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month')
            ->toArray();

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
            'has_placement' => $hasPlacement && count($placements) > 0,
            'placements' => $placements,
            'top_ads_by_engagement' => $topAdsByEngagement,
            'monthly_performance' => $monthlyPerformance,
        ];
    }

    public function getAdAnalytics(string $adId): array
    {
        $views = AdView::where('ad_id', $adId)->count();
        $uniqueViewsRaw = AdView::where('ad_id', $adId)
            ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
            ->first();
        $uniqueViews = (int) ($uniqueViewsRaw->count ?? 0);

        $clicksQuery = AdClick::where('ad_id', $adId);
        $totalClicks = (clone $clicksQuery)->count();
        $uniqueClicksRaw = (clone $clicksQuery)
            ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
            ->first();
        $uniqueClicks = (int) ($uniqueClicksRaw->count ?? 0);

        // Force unique clicks to be <= unique views for mathematical sanity
        if ($uniqueClicks > $uniqueViews) {
            $uniqueClicks = $uniqueViews;
        }

        // Unique CTR Safety Validation
        $ctr = 0;
        if ($uniqueViews > 0) {
            $ctr = ($uniqueClicks / $uniqueViews) * 100;
        }
        if ($ctr > 100) {
            Log::warning("Ad {$adId} CTR calculation exceeded 100%: {$ctr}%. Capping to 100%.");
            $ctr = 100;
        }
        $ctr = round($ctr, 2);

        return [
            'views' => $views,
            'unique_views' => $uniqueViews,
            'clicks' => $totalClicks,
            'unique_clicks' => $uniqueClicks,
            'ctr' => $ctr,
        ];
    }
}
