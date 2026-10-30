@extends('admin.layouts.app')

@section('title', 'Advertiser Analytics & Performance')

@include('admin.partials.grid-head')

@section('content')
    <div class="space-y-4">
        {{-- Header Bar with Date Range and Export --}}
        <div class="light rounded-xl border bs p-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold t1 flex items-center gap-2">
                    <i class="bi bi-graph-up-arrow text-indigo-600"></i>
                    Advertiser Analytics & Intelligence
                </h1>
                <p class="text-xs t3 mt-0.5">Track commercial advertising revenue, application conversion funnel, and feed ad performance.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" action="{{ route('admin.advertiser.analytics.index') }}" class="flex items-center gap-2">
                    <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="px-2.5 py-1 text-xs rounded-lg border border-slate-200 bg-white">
                    <span class="text-xs text-slate-400">to</span>
                    <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="px-2.5 py-1 text-xs rounded-lg border border-slate-200 bg-white">
                    <button type="submit" class="px-3 py-1 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 transition">
                        Filter
                    </button>
                </form>

                <a href="{{ route('admin.advertiser.analytics.export', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 text-xs font-semibold shadow-xs transition flex items-center gap-1.5 no-underline">
                    <i class="bi bi-file-earmark-arrow-down text-emerald-600"></i> Export CSV
                </a>
            </div>
        </div>

        {{-- Top KPI Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            {{-- Gross Revenue --}}
            <div class="light rounded-xl border bs p-4 bg-gradient-to-br from-emerald-50/60 to-white">
                <div class="flex items-center justify-between text-xs text-slate-500 font-bold uppercase tracking-wider">
                    <span>Captured Revenue</span>
                    <i class="bi bi-currency-rupee text-emerald-600 text-base"></i>
                </div>
                <div class="text-2xl font-black text-slate-900 mt-2">
                    ₹{{ number_format((float)$metrics['payments']['captured_amount'], 2) }}
                </div>
                <div class="text-[11px] text-emerald-700 font-semibold mt-1">
                    {{ $metrics['payments']['captured_count'] }} Verified Payments
                </div>
            </div>

            {{-- Active Subscriptions --}}
            <div class="light rounded-xl border bs p-4 bg-gradient-to-br from-indigo-50/60 to-white">
                <div class="flex items-center justify-between text-xs text-slate-500 font-bold uppercase tracking-wider">
                    <span>Active Subscriptions</span>
                    <i class="bi bi-card-checklist text-indigo-600 text-base"></i>
                </div>
                <div class="text-2xl font-black text-indigo-900 mt-2">
                    {{ $metrics['subscriptions']['active'] }}
                </div>
                <div class="text-[11px] text-slate-500 mt-1">
                    Total: {{ $metrics['subscriptions']['total'] }} | Expired: {{ $metrics['subscriptions']['expired'] }}
                </div>
            </div>

            {{-- Impressions / Views --}}
            <div class="light rounded-xl border bs p-4 bg-gradient-to-br from-sky-50/60 to-white">
                <div class="flex items-center justify-between text-xs text-slate-500 font-bold uppercase tracking-wider">
                    <span>Total Ad Views</span>
                    <i class="bi bi-eye-fill text-sky-600 text-base"></i>
                </div>
                <div class="text-2xl font-black text-sky-900 mt-2">
                    {{ number_format($metrics['ads']['total_views']) }}
                </div>
                <div class="text-[11px] text-slate-500 mt-1">
                    Unique Reach: {{ number_format($metrics['ads']['unique_views']) }}
                </div>
            </div>

            {{-- Clicks & CTR --}}
            <div class="light rounded-xl border bs p-4 bg-gradient-to-br from-purple-50/60 to-white">
                <div class="flex items-center justify-between text-xs text-slate-500 font-bold uppercase tracking-wider">
                    <span>Total Clicks & CTR</span>
                    <i class="bi bi-cursor-fill text-purple-600 text-base"></i>
                </div>
                <div class="text-2xl font-black text-purple-900 mt-2">
                    {{ number_format($metrics['ads']['total_clicks']) }}
                    <span class="text-sm font-semibold text-purple-600">({{ $metrics['ads']['ctr_percent'] }}% CTR)</span>
                </div>
                <div class="text-[11px] text-slate-500 mt-1">
                    Unique Clicks: {{ number_format($metrics['ads']['unique_clicks']) }}
                </div>
            </div>
        </div>

        {{-- Funnel & Campaign Distribution Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Application Funnel --}}
            <div class="light rounded-xl border bs p-4 space-y-3">
                <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <i class="bi bi-funnel text-indigo-600"></i> Application Funnel & Status Breakdown
                    </span>
                    <span class="text-xs font-semibold text-slate-500">{{ $metrics['applications']['total'] }} Total</span>
                </h2>
                <div class="space-y-2 text-xs">
                    <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50">
                        <span class="font-medium text-slate-700">Submitted (New)</span>
                        <span class="font-bold text-slate-900">{{ $metrics['applications']['submitted'] }}</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-amber-50/60 text-amber-900">
                        <span class="font-medium">Under Review</span>
                        <span class="font-bold">{{ $metrics['applications']['under_review'] }}</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-sky-50/60 text-sky-900">
                        <span class="font-medium">Corrections Requested</span>
                        <span class="font-bold">{{ $metrics['applications']['changes_requested'] }}</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-emerald-50/60 text-emerald-900">
                        <span class="font-medium">Approved for Payment</span>
                        <span class="font-bold">{{ $metrics['applications']['approved'] }}</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-rose-50/60 text-rose-900">
                        <span class="font-medium">Rejected</span>
                        <span class="font-bold">{{ $metrics['applications']['rejected'] }}</span>
                    </div>
                </div>
            </div>

            {{-- Campaigns Status Distribution --}}
            <div class="light rounded-xl border bs p-4 space-y-3">
                <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <i class="bi bi-pie-chart text-indigo-600"></i> Campaigns Status Overview
                    </span>
                    <span class="text-xs font-semibold text-slate-500">{{ $metrics['campaigns']['total'] }} Total</span>
                </h2>
                <div class="space-y-2 text-xs">
                    <div class="flex items-center justify-between p-2 rounded-lg bg-emerald-50 text-emerald-900">
                        <span class="font-medium">Active (Delivering Live in Feed)</span>
                        <span class="font-bold">{{ $metrics['campaigns']['active'] }}</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-amber-50 text-amber-900">
                        <span class="font-medium">Pending Review Queue</span>
                        <span class="font-bold">{{ $metrics['campaigns']['pending_review'] }}</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-slate-50 text-slate-700">
                        <span class="font-medium">Paused</span>
                        <span class="font-bold">{{ $metrics['campaigns']['paused'] }}</span>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded-lg bg-indigo-50 text-indigo-900">
                        <span class="font-medium">Completed / Schedule Ended</span>
                        <span class="font-bold">{{ $metrics['campaigns']['completed'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Campaigns Table --}}
        <div class="light rounded-xl border bs p-4 space-y-3">
            <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <i class="bi bi-clock-history text-indigo-600"></i> Recent Campaigns
                </span>
                <a href="{{ route('admin.advertiser.campaigns.index') }}" class="text-xs text-indigo-600 hover:underline">
                    View All Campaigns &rarr;
                </a>
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 text-[11px] font-bold uppercase text-slate-500">
                        <tr>
                            <th class="py-2.5 px-3">Campaign</th>
                            <th class="py-2.5 px-3">Company</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3">Linked Ad Placements</th>
                            <th class="py-2.5 px-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentCampaigns as $camp)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-slate-900">{{ $camp->name }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ $camp->campaign_number }}</div>
                                </td>
                                <td class="py-2.5 px-3 font-semibold text-slate-800">
                                    {{ $camp->application?->company_name ?? 'N/A' }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 capitalize">
                                        {{ str_replace('_', ' ', $camp->status) }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-slate-600">
                                    {{ $camp->campaignAds->count() }} ad(s)
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <a href="{{ route('admin.advertiser.campaigns.show', $camp->id) }}" class="text-xs text-indigo-600 hover:underline font-semibold">
                                        Details &rarr;
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-slate-400">No campaigns recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
