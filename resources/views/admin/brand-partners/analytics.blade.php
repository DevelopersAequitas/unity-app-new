@extends('admin.layouts.app')

@section('title', 'Brand Partners Analytics')

@push('styles')
<style>
/* ── Premium Brand Partners Analytics Design System ── */
:root {
    --bp-indigo-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    --bp-emerald-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
    --bp-amber-gradient: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
    --bp-sky-gradient: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    --bp-purple-gradient: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
}

.analytics-hero-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    padding: 18px 20px;
    position: relative;
    overflow: hidden;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03), 0 1px 2px rgba(15, 23, 42, 0.02);
}

.analytics-hero-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: transparent;
    transition: all 0.25s ease;
}

.analytics-hero-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 30px -8px rgba(15, 23, 42, 0.1), 0 4px 12px -2px rgba(15, 23, 42, 0.05);
}

/* Card Themes */
.card-theme-indigo { border-top: 3px solid #6366f1; background: linear-gradient(180deg, rgba(99, 102, 241, 0.03) 0%, #ffffff 100%); }
.card-theme-indigo:hover { border-color: #6366f1; }

.card-theme-emerald { border-top: 3px solid #10b981; background: linear-gradient(180deg, rgba(16, 185, 129, 0.03) 0%, #ffffff 100%); }
.card-theme-emerald:hover { border-color: #10b981; }

.card-theme-amber { border-top: 3px solid #f59e0b; background: linear-gradient(180deg, rgba(245, 158, 11, 0.03) 0%, #ffffff 100%); }
.card-theme-amber:hover { border-color: #f59e0b; }

.card-theme-sky { border-top: 3px solid #0ea5e9; background: linear-gradient(180deg, rgba(14, 165, 233, 0.03) 0%, #ffffff 100%); }
.card-theme-sky:hover { border-color: #0ea5e9; }

.card-theme-purple { border-top: 3px solid #a855f7; background: linear-gradient(180deg, rgba(168, 85, 247, 0.03) 0%, #ffffff 100%); }
.card-theme-purple:hover { border-color: #a855f7; }

/* Icon Bubbles with Modern Gradients */
.stat-glow-icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: #ffffff;
    flex-shrink: 0;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.analytics-hero-card:hover .stat-glow-icon {
    transform: scale(1.08) rotate(3deg);
}

.icon-indigo { background: var(--bp-indigo-gradient); box-shadow: 0 6px 16px rgba(99, 102, 241, 0.35); }
.icon-emerald { background: var(--bp-emerald-gradient); box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35); }
.icon-amber { background: var(--bp-amber-gradient); box-shadow: 0 6px 16px rgba(245, 158, 11, 0.35); }
.icon-sky { background: var(--bp-sky-gradient); box-shadow: 0 6px 16px rgba(14, 165, 233, 0.35); }
.icon-purple { background: var(--bp-purple-gradient); box-shadow: 0 6px 16px rgba(168, 85, 247, 0.35); }

/* Rate Cards & Conversion Funnel */
.funnel-rate-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    padding: 22px 20px;
    position: relative;
    overflow: hidden;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
}

.funnel-rate-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 30px -6px rgba(15, 23, 42, 0.1);
}

.funnel-rate-value {
    font-size: 2.6rem;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -0.03em;
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
}

/* Explicit Custom Badges (avoiding framework text-color invisibility) */
.custom-pill-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
    line-height: 1.2;
}

.badge-indigo-glow {
    background: #eef2ff !important;
    color: #4338ca !important;
    border: 1px solid #c7d2fe !important;
}

.badge-emerald-glow {
    background: #ecfdf5 !important;
    color: #047857 !important;
    border: 1px solid #a7f3d0 !important;
}

.badge-sky-glow {
    background: #f0f9ff !important;
    color: #0369a1 !important;
    border: 1px solid #bae6fd !important;
}

.badge-amber-glow {
    background: #fffbeb !important;
    color: #b45309 !important;
    border: 1px solid #fde68a !important;
}

.badge-purple-glow {
    background: #faf5ff !important;
    color: #7e22ce !important;
    border: 1px solid #e9d5ff !important;
}

/* Dual-Tone Animated Progress Bars */
.funnel-progress-track {
    width: 100%;
    height: 8px;
    background: #f1f5f9;
    border-radius: 9999px;
    overflow: hidden;
    position: relative;
    border: 1px solid #e2e8f0;
}

.funnel-progress-fill {
    height: 100%;
    border-radius: 9999px;
    transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Tables Section Styling */
.table-card-container {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.table-card-header {
    padding: 16px 20px;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
    border-bottom: 1px solid #edf2f7;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.rank-badge {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
}

.rank-gold { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.rank-silver { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.rank-bronze { background: #ffedd5; color: #9a3412; border: 1px solid #fed7aa; }
.rank-default { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }

.live-pulse-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #10b981;
    display: inline-block;
    position: relative;
}

.live-pulse-dot::after {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    border-radius: 50%;
    border: 2px solid #10b981;
    animation: pulseGlow 1.8s infinite;
}

@keyframes pulseGlow {
    0% { transform: scale(1); opacity: 0.8; }
    100% { transform: scale(2.2); opacity: 0; }
}
</style>
@endpush

@section('content')
@php
    $conversionRate = $stats['total_website_clicks'] > 0 ? round(($stats['total_redemptions'] / $stats['total_website_clicks']) * 100, 2) : 0;
    $websiteCtr = $stats['total_views'] > 0 ? round(($stats['total_website_clicks'] / $stats['total_views']) * 100, 2) : 0;
    $redeemCtr = $stats['total_views'] > 0 ? round(($stats['total_redemptions'] / $stats['total_views']) * 100, 2) : 0;
@endphp

<div class="space-y-4">
    {{-- Page Header with Modern Action Bar --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 p-3 bg-white rounded-2xl border border-slate-200 shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <div style="width: 44px; height: 44px; border-radius: 14px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 22px; box-shadow: 0 6px 16px rgba(99, 102, 241, 0.35);">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h4 class="fw-bold mb-0 text-slate-900" style="font-size: 1.3rem; font-family: 'Outfit', sans-serif;">Brand Partners Analytics</h4>
                    <span class="custom-pill-badge badge-emerald-glow">
                        <span class="live-pulse-dot"></span> Live Data
                    </span>
                </div>
                <p class="text-slate-500 mb-0 text-xs font-medium mt-0.5">Comprehensive real-time conversion funnels, partner engagement, and CTR tracking</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.brand-partners.dashboard') }}" class="btn btn-sm d-inline-flex align-items-center gap-2 px-3.5 py-2 fw-semibold text-white shadow-sm transition-all" style="border-radius: 12px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none;">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="{{ route('admin.brand-partners.index') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-2 px-3.5 py-2 fw-semibold bg-white text-slate-700 shadow-sm hover:bg-slate-50 transition-all" style="border-radius: 12px; border: 1px solid #cbd5e1;">
                <i class="bi bi-people-fill text-indigo-600"></i> All Partners
            </a>
        </div>
    </div>

    {{-- Top 4 KPI Metrics Strip with Rich Gradient Theme --}}
    <div class="row g-3">
        {{-- 1. Total Views --}}
        <div class="col-6 col-lg-3">
            <div class="analytics-hero-card card-theme-indigo h-100 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider d-block">Total Impressions</span>
                        <div class="fw-extrabold text-slate-900 text-2xl mt-1 tracking-tight" style="font-family: 'Outfit', sans-serif;">
                            {{ number_format($stats['total_views'] ?? 0) }}
                        </div>
                    </div>
                    <div class="stat-glow-icon icon-indigo">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-t border-slate-100">
                    <span class="custom-pill-badge badge-indigo-glow">
                        <i class="bi bi-person-check-fill"></i> {{ number_format($stats['unique_views'] ?? 0) }} Unique
                    </span>
                    <span class="text-[11px] text-slate-400 font-semibold">User Views</span>
                </div>
            </div>
        </div>

        {{-- 2. Website Clicks --}}
        <div class="col-6 col-lg-3">
            <div class="analytics-hero-card card-theme-emerald h-100 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider d-block">Website Clicks</span>
                        <div class="fw-extrabold text-slate-900 text-2xl mt-1 tracking-tight" style="font-family: 'Outfit', sans-serif;">
                            {{ number_format($stats['total_website_clicks'] ?? 0) }}
                        </div>
                    </div>
                    <div class="stat-glow-icon icon-emerald">
                        <i class="bi bi-cursor-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-t border-slate-100">
                    <span class="custom-pill-badge badge-emerald-glow">
                        <i class="bi bi-fingerprint"></i> {{ number_format($stats['unique_website_clicks'] ?? 0) }} Unique
                    </span>
                    <span class="text-[11px] text-slate-400 font-semibold">Web Traffic</span>
                </div>
            </div>
        </div>

        {{-- 3. Redemptions --}}
        <div class="col-6 col-lg-3">
            <div class="analytics-hero-card card-theme-amber h-100 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider d-block">Coupons Claimed</span>
                        <div class="fw-extrabold text-slate-900 text-2xl mt-1 tracking-tight" style="font-family: 'Outfit', sans-serif;">
                            {{ number_format($stats['total_redemptions'] ?? 0) }}
                        </div>
                    </div>
                    <div class="stat-glow-icon icon-amber">
                        <i class="bi bi-ticket-perforated-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-t border-slate-100">
                    <span class="custom-pill-badge badge-amber-glow">
                        <i class="bi bi-check-circle-fill"></i> Redeemed
                    </span>
                    <span class="text-[11px] text-slate-400 font-semibold">Brand Offers</span>
                </div>
            </div>
        </div>

        {{-- 4. Active Partners --}}
        <div class="col-6 col-lg-3">
            <div class="analytics-hero-card card-theme-sky h-100 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div>
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider d-block">Active Partners</span>
                        <div class="fw-extrabold text-slate-900 text-2xl mt-1 tracking-tight" style="font-family: 'Outfit', sans-serif;">
                            {{ number_format($stats['total_partners'] ?? 0) }}
                        </div>
                    </div>
                    <div class="stat-glow-icon icon-sky">
                        <i class="bi bi-shop"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-t border-slate-100">
                    <span class="custom-pill-badge badge-sky-glow">
                        <i class="bi bi-tag-fill"></i> {{ number_format($stats['active_offers'] ?? 0) }} Active
                    </span>
                    <span class="text-[11px] text-slate-400 font-semibold">Live Offers</span>
                </div>
            </div>
        </div>
    </div>

    {{-- 3 Refined Core Conversion Funnel Cards --}}
    <div class="row g-3">
        <!-- 1. Redeem Conversion Rate -->
        <div class="col-12 col-md-4">
            <div class="funnel-rate-card card-theme-indigo h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-wider d-flex align-items-center gap-1.5">
                            <i class="bi bi-funnel-fill text-indigo-600"></i> Redeem Conversion Rate
                        </span>
                        <span class="custom-pill-badge badge-indigo-glow">
                            <i class="bi bi-arrow-right-short"></i> Clicks → Redeem
                        </span>
                    </div>
                    <div class="d-flex align-items-baseline gap-2 my-2">
                        <span class="funnel-rate-value text-indigo-600">{{ $conversionRate }}%</span>
                        <span class="text-xs text-slate-500 font-bold uppercase tracking-wider">conversion</span>
                    </div>
                    <p class="text-slate-600 text-xs mb-3 leading-relaxed">
                        Percentage of users who clicked the website link and subsequently redeemed a coupon code.
                    </p>
                </div>
                <div>
                    <div class="d-flex justify-content-between align-items-center text-[11px] font-semibold text-slate-500 mb-1.5">
                        <span>Funnel Efficiency</span>
                        <span class="text-indigo-600 font-bold">{{ $conversionRate }}%</span>
                    </div>
                    <div class="funnel-progress-track">
                        <div class="funnel-progress-fill" style="width: {{ min(max($conversionRate, 3), 100) }}%; background: linear-gradient(90deg, #6366f1, #4f46e5); box-shadow: 0 0 10px rgba(99, 102, 241, 0.5);"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Website CTR -->
        <div class="col-12 col-md-4">
            <div class="funnel-rate-card card-theme-emerald h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-wider d-flex align-items-center gap-1.5">
                            <i class="bi bi-mouse-fill text-emerald-600"></i> Website CTR
                        </span>
                        <span class="custom-pill-badge badge-emerald-glow">
                            <i class="bi bi-arrow-right-short"></i> Views → Clicks
                        </span>
                    </div>
                    <div class="d-flex align-items-baseline gap-2 my-2">
                        <span class="funnel-rate-value text-emerald-600">{{ $websiteCtr }}%</span>
                        <span class="text-xs text-slate-500 font-bold uppercase tracking-wider">click-through</span>
                    </div>
                    <p class="text-slate-600 text-xs mb-3 leading-relaxed">
                        Percentage of brand views that converted into external website visits and user clicks.
                    </p>
                </div>
                <div>
                    <div class="d-flex justify-content-between align-items-center text-[11px] font-semibold text-slate-500 mb-1.5">
                        <span>Click-through Ratio</span>
                        <span class="text-emerald-600 font-bold">{{ $websiteCtr }}%</span>
                    </div>
                    <div class="funnel-progress-track">
                        <div class="funnel-progress-fill" style="width: {{ min(max($websiteCtr, 3), 100) }}%; background: linear-gradient(90deg, #10b981, #059669); box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Redeem CTR -->
        <div class="col-12 col-md-4">
            <div class="funnel-rate-card card-theme-sky h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-wider d-flex align-items-center gap-1.5">
                            <i class="bi bi-percent text-sky-600"></i> Redeem CTR
                        </span>
                        <span class="custom-pill-badge badge-sky-glow">
                            <i class="bi bi-arrow-right-short"></i> Views → Redeem
                        </span>
                    </div>
                    <div class="d-flex align-items-baseline gap-2 my-2">
                        <span class="funnel-rate-value text-sky-600">{{ $redeemCtr }}%</span>
                        <span class="text-xs text-slate-500 font-bold uppercase tracking-wider">redemptions</span>
                    </div>
                    <p class="text-slate-600 text-xs mb-3 leading-relaxed">
                        Percentage of overall brand impressions that directly resulted in a coupon redemption.
                    </p>
                </div>
                <div>
                    <div class="d-flex justify-content-between align-items-center text-[11px] font-semibold text-slate-500 mb-1.5">
                        <span>Impression-to-Claim</span>
                        <span class="text-sky-600 font-bold">{{ $redeemCtr }}%</span>
                    </div>
                    <div class="funnel-progress-track">
                        <div class="funnel-progress-fill" style="width: {{ min(max($redeemCtr, 3), 100) }}%; background: linear-gradient(90deg, #0ea5e9, #0284c7); box-shadow: 0 0 10px rgba(14, 165, 233, 0.5);"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom Analytics Tables with High-Contrast Glassmorphism Design --}}
    <div class="row g-3">
        <!-- Top Performing Categories -->
        <div class="col-12 col-lg-6">
            <div class="table-card-container">
                <div class="table-card-header">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 32px; height: 32px; border-radius: 10px; background: #eef2ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; font-size: 15px;">
                            <i class="bi bi-tags-fill"></i>
                        </div>
                        <span class="fw-bold text-slate-800 text-sm" style="font-family: 'Outfit', sans-serif;">Top Performing Categories</span>
                    </div>
                    <span class="custom-pill-badge badge-indigo-glow">
                        <i class="bi bi-grid-fill"></i> {{ count($charts['top_categories'] ?? []) }} Categories
                    </span>
                </div>
                <div class="p-0 flex-grow-1">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-[13px]">
                            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-200">
                                <tr>
                                    <th class="px-3 py-3 text-center" style="width: 55px;">Rank</th>
                                    <th class="px-3 py-3 text-left">Category Name</th>
                                    <th class="px-3 py-3 text-end">Partners Count</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse(($charts['top_categories'] ?? []) as $index => $cat)
                                    @php
                                        $rankClass = match($index) {
                                            0 => 'rank-gold',
                                            1 => 'rank-silver',
                                            2 => 'rank-bronze',
                                            default => 'rank-default'
                                        };
                                        $rankIcon = match($index) {
                                            0 => '🥇',
                                            1 => '🥈',
                                            2 => '🥉',
                                            default => '#' . ($index + 1)
                                        };
                                    @endphp
                                    <tr class="hover:bg-slate-50/90 transition-all">
                                        <td class="px-3 py-3 text-center">
                                            <span class="rank-badge {{ $rankClass }}">{{ $rankIcon }}</span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="fw-bold text-slate-800 text-xs">{{ $cat['name'] }}</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-end">
                                            <span class="custom-pill-badge badge-indigo-glow">
                                                <i class="bi bi-shop"></i> {{ $cat['count'] }} Partners
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-8">
                                            <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                                                <div style="width: 44px; height: 44px; border-radius: 50%; background: #f8fafc; color: #94a3b8; display: flex; align-items: center; justify-content: center; font-size: 20px; border: 1px dashed #cbd5e1;">
                                                    <i class="bi bi-tag"></i>
                                                </div>
                                                <span class="text-xs font-semibold text-slate-500">No category performance data found yet.</span>
                                                <span class="text-[11px] text-slate-400">Categories will rank automatically as partners join.</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historical Overview (Last 6 Months) -->
        <div class="col-12 col-lg-6">
            <div class="table-card-container">
                <div class="table-card-header">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 32px; height: 32px; border-radius: 10px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 15px;">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <span class="fw-bold text-slate-800 text-sm" style="font-family: 'Outfit', sans-serif;">Historical Overview</span>
                    </div>
                    <span class="custom-pill-badge badge-sky-glow">
                        <i class="bi bi-calendar3"></i> Last 6 Months
                    </span>
                </div>
                <div class="p-0 flex-grow-1">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-[13px]">
                            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-200">
                                <tr>
                                    <th class="px-3 py-3 text-left">Month</th>
                                    <th class="px-3 py-3 text-center">Views</th>
                                    <th class="px-3 py-3 text-center">Clicks</th>
                                    <th class="px-3 py-3 text-end">CTR</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse(($charts['monthly_performance'] ?? []) as $month)
                                    @php
                                        $monthCtr = $month['views'] > 0 ? round(($month['clicks'] / $month['views']) * 100, 2) : 0;
                                    @endphp
                                    <tr class="hover:bg-slate-50/90 transition-all">
                                        <td class="px-3 py-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-calendar-event text-slate-400 text-xs"></i>
                                                <span class="fw-bold text-slate-800 text-xs">{{ $month['month'] }}</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-center">
                                            <span class="badge bg-slate-100 text-slate-700 border border-slate-200 text-xs px-2.5 py-1 font-mono font-bold">
                                                {{ number_format($month['views']) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-center">
                                            <span class="badge bg-slate-100 text-slate-700 border border-slate-200 text-xs px-2.5 py-1 font-mono font-bold">
                                                {{ number_format($month['clicks']) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-end">
                                            <span class="custom-pill-badge {{ $monthCtr > 0 ? 'badge-emerald-glow' : 'badge-indigo-glow' }} font-mono">
                                                {{ $monthCtr }}%
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-8">
                                            <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                                                <div style="width: 44px; height: 44px; border-radius: 50%; background: #f8fafc; color: #94a3b8; display: flex; align-items: center; justify-content: center; font-size: 20px; border: 1px dashed #cbd5e1;">
                                                    <i class="bi bi-calendar-x"></i>
                                                </div>
                                                <span class="text-xs font-semibold text-slate-500">No monthly historical data recorded yet.</span>
                                                <span class="text-[11px] text-slate-400">Monthly breakdown will populate as interactions occur.</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
