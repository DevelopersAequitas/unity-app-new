<?php

declare(strict_types=1);

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
    private function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

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

        $hasAdsTable = $this->hasTable('ads');
        $hasViewsTable = $this->hasTable('ad_views');
        $hasClicksTable = $this->hasTable('ad_clicks');

        $hasIsActive = $hasAdsTable && $this->hasColumn('ads', 'is_active');
        $hasStartsAt = $hasAdsTable && $this->hasColumn('ads', 'starts_at');
        $hasEndsAt = $hasAdsTable && $this->hasColumn('ads', 'ends_at');

        // Cards statistics
        $totalAds = $hasAdsTable ? Ad::count() : 0;

        if ($hasAdsTable && $hasIsActive) {
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

        $expiredAds = ($hasAdsTable && $hasEndsAt) ? Ad::whereNotNull('ends_at')->where('ends_at', '<', $now)->count() : 0;

        // Views tracking
        $totalViews = $hasViewsTable ? AdView::count() : 0;
        $uniqueViews = 0;
        if ($hasViewsTable) {
            $uniqueViewsRaw = AdView::selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')->first();
            $uniqueViews = (int) ($uniqueViewsRaw->count ?? 0);
        }

        // Clicks tracking
        $totalClicks = $hasClicksTable ? AdClick::count() : 0;
        $uniqueClicks = 0;
        if ($hasClicksTable) {
            $uniqueClicksRaw = AdClick::selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')->first();
            $uniqueClicks = (int) ($uniqueClicksRaw->count ?? 0);
        }

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
        $topAdRow = null;
        $topPerformingAd = null;
        if ($hasClicksTable && $hasAdsTable) {
            $topAdRow = AdClick::select('ad_id', DB::raw('COUNT(*) as click_count'))
                ->groupBy('ad_id')
                ->orderByDesc('click_count')
                ->first();
            $topPerformingAd = $topAdRow ? Ad::find($topAdRow->ad_id) : null;
        }

        // Most Viewed Ad
        $mostViewedRow = null;
        $mostViewedAd = null;
        if ($hasViewsTable && $hasAdsTable) {
            $mostViewedRow = AdView::select('ad_id', DB::raw('COUNT(*) as view_count'))
                ->groupBy('ad_id')
                ->orderByDesc('view_count')
                ->first();
            $mostViewedAd = $mostViewedRow ? Ad::find($mostViewedRow->ad_id) : null;
        }

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

        $hasAdsTable = $this->hasTable('ads');
        $hasViewsTable = $this->hasTable('ad_views');
        $hasClicksTable = $this->hasTable('ad_clicks');

        // Daily traffic (views and clicks for the last 30 days)
        $dailyViews = $hasViewsTable ? AdView::selectRaw('DATE(viewed_at) as date, COUNT(*) as count')
            ->where('viewed_at', '>=', $days30Ago)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray() : [];

        $dailyClicks = $hasClicksTable ? AdClick::selectRaw('DATE(created_at) as date, COUNT(*) as count')
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

        // Placement breakdown (only if column exists)
        $hasPlacement = $hasAdsTable && $this->hasColumn('ads', 'placement');
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

        // Top ads by engagement
        $topAdsByEngagement = $hasAdsTable ? Ad::query()
            ->withCount([
                'views' => function ($q) use ($hasViewsTable) {
                    if (! $hasViewsTable) {
                        $q->whereRaw('1 = 0');
                    }
                },
                'clicks' => function ($q) use ($hasClicksTable) {
                    if (! $hasClicksTable) {
                        $q->whereRaw('1 = 0');
                    }
                },
            ])
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
            ->toArray() : [];

        // Monthly performance (last 6 months)
        $isSqlite = DB::getDriverName() === 'sqlite';
        $viewMonthExpr = $isSqlite ? "strftime('%Y-%m', viewed_at)" : "TO_CHAR(viewed_at, 'YYYY-MM')";
        $clickMonthExpr = $isSqlite ? "strftime('%Y-%m', created_at)" : "TO_CHAR(created_at, 'YYYY-MM')";

        $monthlyViews = $hasViewsTable ? AdView::selectRaw("{$viewMonthExpr} as month, COUNT(*) as count")
            ->where('viewed_at', '>=', $months6Ago)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month')
            ->toArray() : [];

        $monthlyClicks = $hasClicksTable ? AdClick::selectRaw("{$clickMonthExpr} as month, COUNT(*) as count")
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
            'has_placement' => $hasPlacement && count($placements) > 0,
            'placements' => $placements,
            'top_ads_by_engagement' => $topAdsByEngagement,
            'monthly_performance' => $monthlyPerformance,
        ];
    }

    public function getAdAnalytics(string $adId): array
    {
        $hasViewsTable = $this->hasTable('ad_views');
        $hasClicksTable = $this->hasTable('ad_clicks');

        $views = $hasViewsTable ? AdView::where('ad_id', $adId)->count() : 0;
        $uniqueViews = 0;
        if ($hasViewsTable) {
            $uniqueViewsRaw = AdView::where('ad_id', $adId)
                ->selectRaw('COUNT(DISTINCT COALESCE(CAST(user_id AS VARCHAR), ip_address, session_id)) as count')
                ->first();
            $uniqueViews = (int) ($uniqueViewsRaw->count ?? 0);
        }

        $totalClicks = 0;
        $uniqueClicks = 0;
        if ($hasClicksTable) {
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
