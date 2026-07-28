@extends('admin.layouts.app')

@section('title', 'Brand Partners Dashboard')

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
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Brand Partners Command Center</h2>
            <p class="text-xs t3 m-0 mt-0.5">Comprehensive overview of partner performance, engagement analytics, active offers, and CTR metrics.</p>
        </div>
        <div class="flex items-center flex-wrap gap-2">
            <a href="{{ route('admin.brand-partners.index') }}" class="px-3 py-1.5 rounded-lg border bs text-[12px] t2 hover:t1 hover:surface-2 transition font-medium focus-ring no-underline flex items-center gap-1.5">
                <i class="bi bi-people text-indigo-400"></i> All Partners
            </a>
            <a href="{{ route('admin.brand-partners.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-[12px] font-semibold transition focus-ring no-underline flex items-center gap-1">
                <i class="bi bi-plus-lg"></i> Add Partner
            </a>
            <a href="{{ route('admin.brand-partners.categories.index') }}" class="px-3 py-1.5 rounded-lg border bs text-[12px] t2 hover:t1 hover:surface-2 transition font-medium focus-ring no-underline flex items-center gap-1.5">
                <i class="bi bi-tags"></i> Categories
            </a>
            <a href="{{ route('admin.brand-partners.offers') }}" class="px-3 py-1.5 rounded-lg border bs text-[12px] t2 hover:t1 hover:surface-2 transition font-medium focus-ring no-underline flex items-center gap-1.5">
                <i class="bi bi-gift"></i> Offers
            </a>
            <a href="{{ route('admin.brand-partners.export', ['format' => 'csv']) }}" class="px-3 py-1.5 rounded-lg border bs text-[12px] t2 hover:t1 hover:surface-2 transition font-medium focus-ring no-underline flex items-center gap-1.5">
                <i class="bi bi-filetype-csv text-emerald-500"></i> CSV
            </a>
            <a href="{{ route('admin.brand-partners.export', ['format' => 'excel']) }}" class="px-3 py-1.5 rounded-lg border bs text-[12px] t2 hover:t1 hover:surface-2 transition font-medium focus-ring no-underline flex items-center gap-1.5">
                <i class="bi bi-file-earmark-excel text-green-600"></i> Excel
            </a>
            <a href="{{ route('admin.brand-partners.settings') }}" class="px-3 py-1.5 rounded-lg border bs text-[12px] t2 hover:t1 hover:surface-2 transition font-medium focus-ring no-underline flex items-center gap-1.5">
                <i class="bi bi-gear"></i> Settings
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
                <span class="text-xs t3">Unique tracking metrics and CTR will populate automatically as peers interact with brand partners.</span>
            </div>
        </div>
    @endif

    <!-- Section 1: Primary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-4">
        <!-- Total Partners -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-title">Total Partners</div>
                <div class="kpi-icon bg-indigo-500/10 text-indigo-500">
                    <i class="bi bi-building"></i>
                </div>
            </div>
            <div class="kpi-num">{{ number_format($stats['total_partners']) }}</div>
            <div class="kpi-sub">Registered Brands</div>
        </div>

        <!-- Active Offers -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-title">Active Offers</div>
                <div class="kpi-icon bg-emerald-500/10 text-emerald-500">
                    <i class="bi bi-gift-fill"></i>
                </div>
            </div>
            <div class="kpi-num text-emerald-500">{{ number_format($stats['active_offers']) }}</div>
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
                    Impression Tracking
                @endif
            </div>
        </div>

        <!-- Total Clicks -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-title">Total Clicks</div>
                <div class="kpi-icon bg-violet-500/10 text-violet-500">
                    <i class="bi bi-link-45deg"></i>
                </div>
            </div>
            <div class="kpi-num text-violet-500">{{ number_format($stats['total_clicks']) }}</div>
            <div class="kpi-sub">
                @if($stats['unique_views'] > 0)
                    Unique: {{ number_format($stats['unique_clicks']) }}
                @else
                    Link Interactions
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

        <!-- Redemptions -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-title">Redemptions</div>
                <div class="kpi-icon bg-teal-500/10 text-teal-500">
                    <i class="bi bi-ticket-perforated-fill"></i>
                </div>
            </div>
            <div class="kpi-num text-teal-500">{{ number_format($stats['total_redemptions']) }}</div>
            <div class="kpi-sub">
                @if($stats['unique_views'] > 0)
                    Conv. Rate: {{ $stats['conversion_rate'] }}%
                @else
                    Claims & Code Uses
                @endif
            </div>
        </div>
    </div>

    <!-- Section 2: Secondary Statistics Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-4">
        <div class="mini-stat-card">
            <div class="mini-stat-label">Featured</div>
            <div class="mini-stat-val">{{ number_format($stats['featured_partners']) }}</div>
        </div>
        <div class="mini-stat-card">
            <div class="mini-stat-label">Sponsored</div>
            <div class="mini-stat-val">{{ number_format($stats['sponsored_partners']) }}</div>
        </div>
        <div class="mini-stat-card">
            <div class="mini-stat-label">Expired Offers</div>
            <div class="mini-stat-val text-rose-500">{{ number_format($stats['expired_offers']) }}</div>
        </div>
        <div class="mini-stat-card">
            <div class="mini-stat-label">Inactive</div>
            <div class="mini-stat-val text-slate-400">{{ number_format($stats['inactive_partners']) }}</div>
        </div>
        <div class="mini-stat-card">
            <div class="mini-stat-label">Bookmarks</div>
            <div class="mini-stat-val text-sky-500">{{ number_format($stats['saved_partners']) }}</div>
        </div>
    </div>

    <!-- Section 3: Performance Highlights -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <!-- Top Performing Partner -->
        <div class="highlight-card flex flex-wrap justify-between items-center gap-3">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-xl flex-none">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div>
                    <span class="text-[11px] font-semibold t3 uppercase tracking-wider">Top Performing Partner</span>
                    <h4 class="font-display font-bold text-sm t1 m-0 mt-0.5">
                        {{ $stats['top_performing_partner']?->name ?? 'None Yet' }}
                    </h4>
                    <p class="text-xs t3 m-0 mt-1">Most active engagement with {{ number_format($stats['top_performing_clicks']) }} clicks.</p>
                </div>
            </div>
            @if($stats['top_performing_partner'])
                <a href="{{ route('admin.brand-partners.show', $stats['top_performing_partner']) }}" class="px-3 py-1.5 rounded-lg border bs text-xs t2 hover:t1 hover:surface-2 transition font-medium no-underline">View Details</a>
            @endif
        </div>

        <!-- Most Saved Partner -->
        <div class="highlight-card flex flex-wrap justify-between items-center gap-3">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-rose-500/10 text-rose-500 flex items-center justify-center text-xl flex-none">
                    <i class="bi bi-bookmark-heart-fill"></i>
                </div>
                <div>
                    <span class="text-[11px] font-semibold t3 uppercase tracking-wider">Most Bookmarked Partner</span>
                    <h4 class="font-display font-bold text-sm t1 m-0 mt-0.5">
                        {{ $stats['most_saved_partner']?->name ?? 'None Yet' }}
                    </h4>
                    <p class="text-xs t3 m-0 mt-1">Saved in favorites by {{ number_format($stats['most_saved_count']) }} peers.</p>
                </div>
            </div>
            @if($stats['most_saved_partner'])
                <a href="{{ route('admin.brand-partners.show', $stats['most_saved_partner']) }}" class="px-3 py-1.5 rounded-lg border bs text-xs t2 hover:t1 hover:surface-2 transition font-medium no-underline">View Details</a>
            @endif
        </div>
    </div>

    <!-- Section 4: Analytics Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <!-- Daily Traffic Chart (Line) -->
        <div class="lg:col-span-8 surface border bs rounded-xl p-4">
            <h3 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0 mb-3">Daily Traffic (Last 30 Days)</h3>
            <div style="height: 300px; position: relative;">
                <canvas id="trafficChartCanvas"></canvas>
            </div>
        </div>

        <!-- Top Categories (Doughnut) -->
        <div class="lg:col-span-4 surface border bs rounded-xl p-4 flex flex-col">
            <h3 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0 mb-3">Top Categories</h3>
            @if(!empty($charts['top_categories']) && count($charts['top_categories']) > 0)
                <div style="height: 250px; position: relative;" class="flex items-center justify-center my-auto">
                    <canvas id="categoriesChartCanvas"></canvas>
                </div>
            @else
                <div class="flex flex-col items-center justify-center my-auto py-12 text-center">
                    <i class="bi bi-pie-chart text-4xl text-indigo-400 opacity-40 mb-2"></i>
                    <span class="text-xs font-medium t3">No category data recorded yet</span>
                </div>
            @endif
        </div>
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

        // 2. Categories Chart (Doughnut)
        const categoriesCanvas = document.getElementById('categoriesChartCanvas');
        if (categoriesCanvas && chartsData.top_categories && chartsData.top_categories.length > 0) {
            const catLabels = chartsData.top_categories.map(item => item.name);
            const catCounts = chartsData.top_categories.map(item => item.count);

            new Chart(categoriesCanvas, {
                type: 'doughnut',
                data: {
                    labels: catLabels,
                    datasets: [{
                        data: catCounts,
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
