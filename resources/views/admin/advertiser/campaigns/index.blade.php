@extends('admin.layouts.app')

@section('title', 'Advertiser Campaigns')

@include('admin.partials.grid-head')

@section('content')
    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="bi bi-check-circle-fill text-emerald-600"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="text-emerald-700 hover:text-emerald-900 border-0 bg-transparent cursor-pointer text-sm" onclick="this.parentElement.remove()">&times;</button>
        </div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        {{-- Header Bar --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
                <h1 class="text-xl font-bold t1 flex items-center gap-2">
                    <i class="bi bi-megaphone-fill text-indigo-600"></i>
                    Advertiser Campaigns
                </h1>
                <p class="text-xs t3 mt-0.5">Manage advertiser campaigns, review ad creatives, approve and track ad delivery.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.advertiser.applications.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition no-underline">
                    <i class="bi bi-file-earmark-person me-1"></i> Applications
                </a>
                <a href="{{ route('admin.advertiser.subscriptions.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition no-underline">
                    <i class="bi bi-card-checklist me-1"></i> Subscriptions
                </a>
                <a href="{{ route('admin.advertiser.analytics.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition no-underline">
                    <i class="bi bi-graph-up-arrow me-1"></i> Analytics
                </a>
            </div>
        </div>

        {{-- KPI Filter Cards --}}
        @php
            $currentStatus = request('status');
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
            {{-- All --}}
            <a href="{{ route('admin.advertiser.campaigns.index') }}" 
               class="group p-3 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1 hover:shadow-lg relative overflow-hidden {{ !$currentStatus ? 'bg-gradient-to-br from-indigo-100/90 via-white to-purple-50 border-indigo-500 shadow-md ring-2 ring-indigo-400/30' : 'bg-gradient-to-br from-indigo-50/40 via-white to-slate-50/20 border-slate-200 hover:border-indigo-400' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider {{ !$currentStatus ? 'text-indigo-900' : 'text-slate-600' }}">All Campaigns</span>
                    <div class="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-xs shadow-xs">
                        <i class="bi bi-stack"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-slate-900 mt-1.5">{{ $counts['total'] }}</div>
                <div class="mt-1 text-[10px] font-semibold text-slate-400">Total Created</div>
            </a>

            {{-- Pending Review --}}
            <a href="{{ route('admin.advertiser.campaigns.index', ['status' => 'pending_review']) }}" 
               class="group p-3 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1 hover:shadow-lg relative overflow-hidden {{ $currentStatus === 'pending_review' ? 'bg-gradient-to-br from-amber-100/90 via-white to-amber-50 border-amber-500 shadow-md ring-2 ring-amber-400/30' : 'bg-gradient-to-br from-amber-50/40 via-white to-slate-50/20 border-slate-200 hover:border-amber-400' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-800">Review Queue</span>
                    <div class="w-7 h-7 rounded-lg bg-amber-500 text-white flex items-center justify-center text-xs shadow-xs">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-amber-700 mt-1.5">{{ $counts['pending_review'] }}</div>
                <div class="mt-1 text-[10px] font-semibold text-amber-600">Pending Review</div>
            </a>

            {{-- Active --}}
            <a href="{{ route('admin.advertiser.campaigns.index', ['status' => 'active']) }}" 
               class="group p-3 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1 hover:shadow-lg relative overflow-hidden {{ $currentStatus === 'active' ? 'bg-gradient-to-br from-emerald-100/90 via-white to-emerald-50 border-emerald-500 shadow-md ring-2 ring-emerald-400/30' : 'bg-gradient-to-br from-emerald-50/40 via-white to-slate-50/20 border-slate-200 hover:border-emerald-400' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-800">Active</span>
                    <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-xs shadow-xs">
                        <i class="bi bi-play-circle-fill"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-emerald-700 mt-1.5">{{ $counts['active'] }}</div>
                <div class="mt-1 text-[10px] font-semibold text-emerald-600">Live in Feed</div>
            </a>

            {{-- Paused --}}
            <a href="{{ route('admin.advertiser.campaigns.index', ['status' => 'paused']) }}" 
               class="group p-3 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1 hover:shadow-lg relative overflow-hidden {{ $currentStatus === 'paused' ? 'bg-gradient-to-br from-slate-200 via-white to-slate-100 border-slate-500 shadow-md' : 'bg-white border-slate-200 hover:border-slate-400' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-700">Paused</span>
                    <div class="w-7 h-7 rounded-lg bg-slate-600 text-white flex items-center justify-center text-xs shadow-xs">
                        <i class="bi bi-pause-circle-fill"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-slate-700 mt-1.5">{{ $counts['paused'] }}</div>
                <div class="mt-1 text-[10px] font-semibold text-slate-500">Temporarily Off</div>
            </a>

            {{-- Changes Requested --}}
            <a href="{{ route('admin.advertiser.campaigns.index', ['status' => 'changes_requested']) }}" 
               class="group p-3 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1 hover:shadow-lg relative overflow-hidden {{ $currentStatus === 'changes_requested' ? 'bg-gradient-to-br from-sky-100/90 via-white to-sky-50 border-sky-500 shadow-md' : 'bg-white border-slate-200 hover:border-sky-400' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-sky-800">Revisions</span>
                    <div class="w-7 h-7 rounded-lg bg-sky-600 text-white flex items-center justify-center text-xs shadow-xs">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-sky-700 mt-1.5">{{ $counts['changes_requested'] }}</div>
                <div class="mt-1 text-[10px] font-semibold text-sky-600">Revisions Sent</div>
            </a>

            {{-- Rejected --}}
            <a href="{{ route('admin.advertiser.campaigns.index', ['status' => 'rejected']) }}" 
               class="group p-3 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1 hover:shadow-lg relative overflow-hidden {{ $currentStatus === 'rejected' ? 'bg-gradient-to-br from-rose-100/90 via-white to-rose-50 border-rose-500 shadow-md' : 'bg-white border-slate-200 hover:border-rose-400' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-rose-800">Rejected</span>
                    <div class="w-7 h-7 rounded-lg bg-rose-600 text-white flex items-center justify-center text-xs shadow-xs">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-rose-700 mt-1.5">{{ $counts['rejected'] }}</div>
                <div class="mt-1 text-[10px] font-semibold text-rose-600">Disapproved</div>
            </a>
        </div>

        {{-- Search and Filters --}}
        <form method="GET" action="{{ route('admin.advertiser.campaigns.index') }}" class="flex flex-col sm:flex-row gap-2 pt-2">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <div class="relative flex-1">
                <i class="bi bi-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by campaign number, name or company..." class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-indigo-500 bg-white">
            </div>
            <button type="submit" class="px-4 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 transition">
                Search
            </button>
            @if(request('q') || request('status'))
                <a href="{{ route('admin.advertiser.campaigns.index') }}" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-50 transition no-underline flex items-center justify-center">
                    Clear
                </a>
            @endif
        </form>

        {{-- Campaigns Table --}}
        <div class="overflow-x-auto rounded-xl border border-slate-100">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="bg-slate-50 text-[11px] font-bold uppercase text-slate-500 border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-3">Campaign</th>
                        <th class="py-3 px-3">Advertiser / Company</th>
                        <th class="py-3 px-3">Objective & CTA</th>
                        <th class="py-3 px-3">Schedule</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($campaigns as $camp)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900">{{ $camp->name }}</div>
                                <div class="text-[10px] text-indigo-600 font-mono">{{ $camp->campaign_number }}</div>
                            </td>
                            <td class="py-3 px-3">
                                <div class="font-semibold text-slate-800">{{ $camp->application?->company_name ?? 'N/A' }}</div>
                                <div class="text-[11px] text-slate-400">{{ $camp->user?->name ?? 'User' }}</div>
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700">
                                    {{ ucfirst(str_replace('_', ' ', $camp->objective ?? 'General')) }}
                                </span>
                                <div class="text-[10px] text-slate-500 mt-0.5">CTA: {{ $camp->cta_text ?: 'Learn More' }}</div>
                            </td>
                            <td class="py-3 px-3 text-[11px]">
                                @if($camp->requested_starts_at)
                                    <div>From: {{ $camp->requested_starts_at->format('d M Y') }}</div>
                                    <div class="text-slate-400">To: {{ $camp->requested_ends_at ? $camp->requested_ends_at->format('d M Y') : 'Ongoing' }}</div>
                                @else
                                    <span class="text-slate-400">Immediate</span>
                                @endif
                            </td>
                            <td class="py-3 px-3">
                                @php
                                    $badgeClasses = match($camp->status) {
                                        'active' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                        'pending_review' => 'bg-amber-100 text-amber-800 border-amber-200 animate-pulse',
                                        'paused' => 'bg-slate-100 text-slate-800 border-slate-200',
                                        'changes_requested' => 'bg-sky-100 text-sky-800 border-sky-200',
                                        'rejected' => 'bg-rose-100 text-rose-800 border-rose-200',
                                        'approved' => 'bg-blue-100 text-blue-800 border-blue-200',
                                        default => 'bg-slate-100 text-slate-600 border-slate-200',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $badgeClasses }}">
                                    {{ ucfirst(str_replace('_', ' ', $camp->status)) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <a href="{{ route('admin.advertiser.campaigns.show', $camp->id) }}" class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition no-underline inline-flex items-center gap-1">
                                    <i class="bi bi-eye"></i> View & Review
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-slate-400 text-xs">
                                <i class="bi bi-inbox text-3xl block mb-2 text-slate-300"></i>
                                No campaigns found matching the selected filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($campaigns->hasPages())
            <div class="pt-3">
                {{ $campaigns->links() }}
            </div>
        @endif
    </div>
@endsection
