@extends('admin.layouts.app')

@section('title', 'Ads Analytics & Reports')

@include('admin.partials.grid-head')

@push('styles')
<style>
  .kpi-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 16px; transition: all .2s ease; display: block; width: 100%; text-align: left; }
  .kpi-card:hover { border-color: var(--accent); }
</style>
@endpush

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card mb-4">

    <!-- Header & Quick Actions -->
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4 pb-3 border-b bs">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Ads Analytics &amp; Reports</h2>
            <p class="text-xs t3 m-0 mt-0.5">Comprehensive performance analytics, click-through rates, and historical overview.</p>
        </div>
        <div class="flex items-center flex-wrap gap-2">
            <a href="{{ route('admin.ads.dashboard') }}" class="px-3 py-1.5 rounded-lg border bs text-[12px] t2 hover:t1 hover:surface-2 transition font-medium focus-ring no-underline flex items-center gap-1.5">
                <i class="bi bi-speedometer2 text-indigo-400"></i> Dashboard
            </a>
            <a href="{{ route('admin.ads.index') }}" class="px-3 py-1.5 rounded-lg border bs text-[12px] t2 hover:t1 hover:surface-2 transition font-medium focus-ring no-underline flex items-center gap-1.5">
                <i class="bi bi-megaphone text-indigo-400"></i> All Ads
            </a>
            <a href="{{ route('admin.ads.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-[12px] font-semibold transition focus-ring no-underline flex items-center gap-1">
                <i class="bi bi-plus-lg"></i> Add Ad
            </a>
        </div>
    </div>

    <!-- Top Summary Widgets -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div class="kpi-card text-center">
            <h4 class="text-xs font-semibold text-indigo-400 uppercase tracking-wider mb-1">Overall Click-Through Rate (CTR)</h4>
            <div class="font-display font-extrabold text-4xl text-amber-500 my-2">{{ $stats['ctr'] }}%</div>
            <p class="text-xs t3 m-0">Percentage of unique ad impressions that resulted in unique clicks.</p>
        </div>
        <div class="kpi-card text-center">
            <h4 class="text-xs font-semibold text-indigo-400 uppercase tracking-wider mb-1">Total Interactions</h4>
            <div class="font-display font-extrabold text-4xl text-emerald-500 my-2">{{ number_format($stats['total_views'] + $stats['total_clicks']) }}</div>
            <p class="text-xs t3 m-0">Combined total of views ({{ number_format($stats['total_views']) }}) and clicks ({{ number_format($stats['total_clicks']) }}).</p>
        </div>
    </div>

    <!-- Analytics Breakdown Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Placement Distribution / Top Ads -->
        <div class="surface border bs rounded-xl p-4">
            @if(!empty($charts['has_placement']))
                <h3 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0 mb-3">Placements Distribution</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left align-middle border-collapse">
                        <thead>
                            <tr class="border-b bs text-xs t3">
                                <th class="py-2 px-1">Placement</th>
                                <th class="py-2 text-center">Ads Count</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y bs">
                            @foreach($charts['placements'] as $place)
                                <tr>
                                    <td class="py-2 px-1 font-semibold t1">{{ $place['name'] }}</td>
                                    <td class="py-2 text-center text-indigo-500 font-bold">{{ number_format($place['count']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <h3 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0 mb-3">Top Ads Engagement</h3>
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
                                            {{ Str::limit($ad['title'], 22) }}
                                        </a>
                                    </td>
                                    <td class="py-2 text-center text-sky-500 font-bold">{{ number_format($ad['views']) }}</td>
                                    <td class="py-2 text-center text-indigo-500 font-bold">{{ number_format($ad['clicks']) }}</td>
                                    <td class="py-2 text-right text-amber-500 font-bold">{{ $ad['ctr'] }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center t3 py-4">No engagement data yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Last 6 Months Metrics -->
        <div class="surface border bs rounded-xl p-4">
            <h3 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0 mb-3">Historical Overview</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left align-middle border-collapse">
                    <thead>
                        <tr class="border-b bs text-xs t3">
                            <th class="py-2 px-1">Month</th>
                            <th class="py-2 text-center">Views</th>
                            <th class="py-2 text-center">Clicks</th>
                            <th class="py-2 text-right">CTR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y bs">
                        @foreach($charts['monthly_performance'] as $month)
                            @php
                                $mCtr = $month['views'] > 0 ? round(($month['clicks'] / $month['views']) * 100, 2) : 0;
                            @endphp
                            <tr>
                                <td class="py-2 px-1 font-semibold t1">{{ $month['month'] }}</td>
                                <td class="py-2 text-center text-sky-500 font-semibold">{{ number_format($month['views']) }}</td>
                                <td class="py-2 text-center text-indigo-500 font-semibold">{{ number_format($month['clicks']) }}</td>
                                <td class="py-2 text-right text-amber-500 font-bold">{{ $mCtr }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
