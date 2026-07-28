@extends('admin.layouts.app')

@section('title', 'Ads Dashboard')

@include('admin.partials.grid-head')

@push('styles')
<style>
  .kpi-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 14px 16px; transition: all .2s ease; display: block; width: 100%; text-align: left; text-decoration: none !important; }
  .kpi-card:hover { border-color: var(--accent); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
  .kpi-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
  .kpi-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex: none; font-size: 1.15rem; }
  .kpi-num { font-family: 'Lexend', sans-serif; font-weight: 700; font-size: 1.45rem; line-height: 1.1; color: var(--text-1); font-variant-numeric: tabular-nums; }
  .kpi-title { font-size: 11px; font-weight: 600; color: var(--text-2); margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
  .kpi-sub { font-size: 11px; color: var(--text-3); margin-top: 2px; }

  .mini-stat-card { background: var(--surface-2); border: 1px solid var(--border-soft); border-radius: 10px; padding: 12px 14px; text-align: center; }
  .mini-stat-label { font-size: 10.5px; font-weight: 600; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.5px; }
  .mini-stat-val { font-family: 'Lexend', sans-serif; font-weight: 700; font-size: 1.25rem; color: var(--text-1); margin-top: 4px; }

  .highlight-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 16px; transition: all .2s ease; }
</style>
@endpush

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card mb-4">

    <!-- Top Command Center Header & Quick Actions -->
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4 pb-3 border-b bs">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Advertisement Command Center</h2>
            <p class="text-xs t3 m-0 mt-0.5">Real-time ad metrics, placement performance, views tracking, and CTR analytics.</p>
        </div>
        <div class="flex items-center flex-wrap gap-2">
            <a href="{{ route('admin.ads.index') }}" class="px-3 py-1.5 rounded-lg border bs text-[12px] t2 hover:t1 hover:surface-2 transition font-medium focus-ring no-underline flex items-center gap-1.5">
                <i class="bi bi-megaphone text-indigo-400"></i> All Ads
            </a>
            <a href="{{ route('admin.ads.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-[12px] font-semibold transition focus-ring no-underline flex items-center gap-1">
                <i class="bi bi-plus-lg"></i> Add Ad
            </a>
            <a href="{{ route('admin.ads.analytics') }}" class="px-3 py-1.5 rounded-lg border bs text-[12px] t2 hover:t1 hover:surface-2 transition font-medium focus-ring no-underline flex items-center gap-1.5">
                <i class="bi bi-graph-up text-sky-400"></i> Analytics Report
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb-4 rounded-xl shadow-sm border-0">{{ session('success') }}</div>
    @endif

    @if($stats['unique_views'] == 0)
        <div class="surface-2 border bs rounded-xl p-3.5 mb-4 flex items-center gap-3">
            <i class="bi bi-info-circle-fill text-indigo-400 text-xl"></i>
            <div>
                <div class="font-semibold text-xs t1">Analytics Collection Active</div>
                <span class="text-xs t3">Ad views, clicks, unique tracking metrics and CTR will populate automatically as peers interact with advertisements.</span>
            </div>
        </div>
    @endif

    <!-- Section 1: Primary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 mb-4">
        <!-- Total Ads -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-title">Total Ads</div>
                <div class="kpi-icon bg-indigo-500/10 text-indigo-500">
                    <i class="bi bi-megaphone-fill"></i>
                </div>
            </div>
            <div class="kpi-num">{{ number_format($stats['total_ads']) }}</div>
            <div class="kpi-sub">Campaign Ads</div>
        </div>

        <!-- Active Ads -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-title">Active Ads</div>
                <div class="kpi-icon bg-emerald-500/10 text-emerald-500">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
            </div>
            <div class="kpi-num text-emerald-500">{{ number_format($stats['active_ads']) }}</div>
            <div class="kpi-sub">Live Campaigns</div>
        </div>

        <!-- Total Views -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-title">Total Views</div>
                <div class="kpi-icon bg-sky-500/10 text-sky-500">
                    <i class="bi bi-eye-fill"></i>
                </div>
            </div>
            <div class="kpi-num text-sky-500">{{ number_format($stats['total_views']) }}</div>
            <div class="kpi-sub">
                @if($stats['unique_views'] > 0)
                    Unique: {{ number_format($stats['unique_views']) }}
                @else
                    Impressions
                @endif
            </div>
        </div>

        <!-- Total Clicks -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-title">Total Clicks</div>
                <div class="kpi-icon bg-violet-500/10 text-violet-500">
                    <i class="bi bi-cursor-fill"></i>
                </div>
            </div>
            <div class="kpi-num text-violet-500">{{ number_format($stats['total_clicks']) }}</div>
            <div class="kpi-sub">
                @if($stats['unique_views'] > 0)
                    Unique: {{ number_format($stats['unique_clicks']) }}
                @else
                    Banner Clicks
                @endif
            </div>
        </div>

        <!-- Unique CTR -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-title">Unique CTR</div>
                <div class="kpi-icon bg-amber-500/10 text-amber-500">
                    <i class="bi bi-percent"></i>
                </div>
            </div>
            <div class="kpi-num text-amber-500">{{ $stats['ctr'] }}%</div>
            <div class="kpi-sub">Click-Through Rate</div>
        </div>
    </div>

    <!-- Section 2: Secondary Statistics Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-4">
        <div class="mini-stat-card">
            <div class="mini-stat-label">Unique Views</div>
            <div class="mini-stat-val">{{ number_format($stats['unique_views']) }}</div>
        </div>
        <div class="mini-stat-card">
            <div class="mini-stat-label">Unique Clicks</div>
            <div class="mini-stat-val">{{ number_format($stats['unique_clicks']) }}</div>
        </div>
        <div class="mini-stat-card">
            <div class="mini-stat-label">Scheduled</div>
            <div class="mini-stat-val text-sky-500">{{ number_format($stats['scheduled_ads']) }}</div>
        </div>
        <div class="mini-stat-card">
            <div class="mini-stat-label">Expired</div>
            <div class="mini-stat-val text-rose-500">{{ number_format($stats['expired_ads']) }}</div>
        </div>
        <div class="mini-stat-card">
            <div class="mini-stat-label">Inactive</div>
            <div class="mini-stat-val text-slate-400">{{ number_format($stats['inactive_ads']) }}</div>
        </div>
    </div>

    <!-- Section 3: Performance Highlights -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <!-- Top Performing Ad -->
        <div class="highlight-card flex flex-wrap justify-between items-center gap-3">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-xl flex-none">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div>
                    <span class="text-[11px] font-semibold t3 uppercase tracking-wider">Top Performing Ad</span>
                    <h4 class="font-display font-bold text-sm t1 m-0 mt-0.5">
                        {{ $stats['top_performing_ad']?->title ?? 'None Yet' }}
                    </h4>
                    <p class="text-xs t3 m-0 mt-1">Highest engagement with {{ number_format($stats['top_performing_clicks']) }} clicks.</p>
                </div>
            </div>
            @if($stats['top_performing_ad'])
                <a href="{{ route('admin.ads.show', $stats['top_performing_ad']) }}" class="px-3 py-1.5 rounded-lg border bs text-xs t2 hover:t1 hover:surface-2 transition font-medium no-underline">View Details</a>
            @endif
        </div>

        <!-- Most Viewed Ad -->
        <div class="highlight-card flex flex-wrap justify-between items-center gap-3">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center text-xl flex-none">
                    <i class="bi bi-eye-fill"></i>
                </div>
                <div>
                    <span class="text-[11px] font-semibold t3 uppercase tracking-wider">Most Viewed Ad</span>
                    <h4 class="font-display font-bold text-sm t1 m-0 mt-0.5">
                        {{ $stats['most_viewed_ad']?->title ?? 'None Yet' }}
                    </h4>
                    <p class="text-xs t3 m-0 mt-1">Highest visibility with {{ number_format($stats['most_viewed_count']) }} views.</p>
                </div>
            </div>
            @if($stats['most_viewed_ad'])
                <a href="{{ route('admin.ads.show', $stats['most_viewed_ad']) }}" class="px-3 py-1.5 rounded-lg border bs text-xs t2 hover:t1 hover:surface-2 transition font-medium no-underline">View Details</a>
            @endif
        </div>
    </div>

    <!-- Section 4: Analytics Charts & Placements -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <!-- Daily Traffic Chart (Line) -->
        <div class="lg:col-span-8 surface border bs rounded-xl p-4">
            <h3 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0 mb-3">Daily Traffic (Last 30 Days)</h3>
            <div style="height: 300px; position: relative;">
                <canvas id="trafficChartCanvas"></canvas>
            </div>
        </div>

        @if(!empty($charts['has_placement']) && !empty($charts['placements']))
            <!-- Placements Breakdown (Doughnut) -->
            <div class="lg:col-span-4 surface border bs rounded-xl p-4">
                <h3 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0 mb-3">Placement Performance</h3>
                <div style="height: 250px; position: relative;" class="flex items-center justify-center">
                    <canvas id="placementsChartCanvas"></canvas>
                </div>
            </div>
        @else
            <!-- Top Ads by Engagement Table -->
            <div class="lg:col-span-4 surface border bs rounded-xl p-4">
                <h3 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0 mb-3">Top Ads by Engagement</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left align-middle border-collapse">
                        <thead>
                            <tr class="border-b bs text-xs t3">
                                <th class="py-2 px-1">Ad</th>
                                <th class="py-2 text-center">Views</th>
                                <th class="py-2 text-center">Clicks</th>
                                <th class="py-2 text-right">CTR</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y bs">
                            @forelse($charts['top_ads_by_engagement'] as $ad)
                                <tr>
                                    <td class="py-2 px-1 font-medium">
                                        <a href="{{ route('admin.ads.show', $ad['id']) }}" class="no-underline t1 hover:text-indigo-400">
                                            {{ Str::limit($ad['title'], 18) }}
                                        </a>
                                    </td>
                                    <td class="py-2 text-center text-sky-500 font-semibold">{{ number_format($ad['views']) }}</td>
                                    <td class="py-2 text-center text-indigo-500 font-semibold">{{ number_format($ad['clicks']) }}</td>
                                    <td class="py-2 text-right text-amber-500 font-semibold">{{ $ad['ctr'] }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center t3 py-4">No engagement data yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- ChartJS Integration -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chartsData = @json($charts);

        // 1. Traffic Chart (Line)
        const trafficDates = chartsData.traffic_chart.map(item => item.date);
        const trafficViews = chartsData.traffic_chart.map(item => item.views);
        const trafficClicks = chartsData.traffic_chart.map(item => item.clicks);

        new Chart(document.getElementById('trafficChartCanvas'), {
            type: 'line',
            data: {
                labels: trafficDates,
                datasets: [
                    {
                        label: 'Views',
                        data: trafficViews,
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14, 165, 233, 0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Clicks',
                        data: trafficClicks,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, usePointStyle: true, font: { family: 'Inter', size: 12 } }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: 8,
                            maxRotation: 0,
                            minRotation: 0,
                            font: { family: 'Inter', size: 11 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { family: 'Inter', size: 11 } }
                    }
                }
            }
        });

        // 2. Placements Chart (Doughnut - if enabled)
        const placementsCanvas = document.getElementById('placementsChartCanvas');
        if (placementsCanvas && chartsData.has_placement && chartsData.placements && chartsData.placements.length > 0) {
            const placeLabels = chartsData.placements.map(item => item.name);
            const placeCounts = chartsData.placements.map(item => item.count);

            new Chart(placementsCanvas, {
                type: 'doughnut',
                data: {
                    labels: placeLabels,
                    datasets: [{
                        data: placeCounts,
                        backgroundColor: ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, font: { family: 'Inter', size: 11 } } }
                    }
                }
            });
        }
    });
</script>
@endsection
