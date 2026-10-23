@extends('admin.layouts.app')

@section('title', 'Advertiser Subscriptions')

@include('admin.partials.grid-head')

@section('content')
    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        {{-- Header Bar --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
                <h1 class="text-xl font-bold t1 flex items-center gap-2">
                    <i class="bi bi-card-checklist text-indigo-600"></i>
                    Advertiser Subscriptions
                </h1>
                <p class="text-xs t3 mt-0.5">Track campaign lifecycles, active periods, and renewal statuses.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.advertiser.applications.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition no-underline">
                    <i class="bi bi-file-earmark-person me-1"></i> Applications
                </a>
                <a href="{{ route('admin.advertiser.payments.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition no-underline">
                    <i class="bi bi-credit-card me-1"></i> Payments
                </a>
            </div>
        </div>

        {{-- Clickable KPI Tabs with Rich Color Themes & Effects --}}
        @php
            $currentSubStatus = request('status');
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5">
            {{-- All Subscriptions --}}
            <a href="{{ route('admin.advertiser.subscriptions.index') }}" 
               class="group p-4 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1.5 hover:shadow-xl relative overflow-hidden {{ !$currentSubStatus ? 'bg-gradient-to-br from-indigo-100/80 via-white to-purple-50 border-indigo-500 shadow-lg shadow-indigo-100/50 ring-2 ring-indigo-400/30' : 'bg-gradient-to-br from-indigo-50/40 via-white to-slate-50/30 border-slate-200 hover:border-indigo-400 hover:shadow-md' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold tracking-wider uppercase {{ !$currentSubStatus ? 'text-indigo-900' : 'text-slate-600' }}">All Subscriptions</span>
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center text-sm shadow-md shadow-indigo-200 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-collection-fill"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-slate-900 mt-2">{{ $counts['total'] }}</div>
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-100/80">
                    <span class="text-[11px] font-medium text-slate-500">Total registered</span>
                    @if(!$currentSubStatus)
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-indigo-700 bg-indigo-100/80 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-600 animate-pulse"></span> Active View
                        </span>
                    @else
                        <span class="text-[10px] text-slate-400 group-hover:text-indigo-600 transition">Click to view &rarr;</span>
                    @endif
                </div>
            </a>

            {{-- Active Campaigns --}}
            <a href="{{ route('admin.advertiser.subscriptions.index', ['status' => 'active']) }}" 
               class="group p-4 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1.5 hover:shadow-xl relative overflow-hidden {{ $currentSubStatus === 'active' ? 'bg-gradient-to-br from-emerald-100/80 via-white to-teal-50 border-emerald-500 shadow-lg shadow-emerald-100/50 ring-2 ring-emerald-400/30' : 'bg-gradient-to-br from-emerald-50/40 via-white to-slate-50/30 border-slate-200 hover:border-emerald-400 hover:shadow-md' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold tracking-wider uppercase {{ $currentSubStatus === 'active' ? 'text-emerald-900' : 'text-emerald-700' }}">Active Campaigns</span>
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-600 text-white flex items-center justify-center text-sm shadow-md shadow-emerald-200 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-broadcast"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-emerald-600 mt-2">{{ $counts['active'] }}</div>
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-emerald-100/50">
                    <span class="text-[11px] font-medium text-emerald-700/80">Live in Unity app</span>
                    @if($currentSubStatus === 'active')
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span> Active View
                        </span>
                    @else
                        <span class="text-[10px] text-emerald-600/70 group-hover:text-emerald-700 transition">Click to view &rarr;</span>
                    @endif
                </div>
            </a>

            {{-- Pending Payment --}}
            <a href="{{ route('admin.advertiser.subscriptions.index', ['status' => 'pending_payment']) }}" 
               class="group p-4 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1.5 hover:shadow-xl relative overflow-hidden {{ $currentSubStatus === 'pending_payment' ? 'bg-gradient-to-br from-amber-100/80 via-white to-orange-50 border-amber-500 shadow-lg shadow-amber-100/50 ring-2 ring-amber-400/30' : 'bg-gradient-to-br from-amber-50/40 via-white to-slate-50/30 border-slate-200 hover:border-amber-400 hover:shadow-md' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold tracking-wider uppercase {{ $currentSubStatus === 'pending_payment' ? 'text-amber-900' : 'text-amber-700' }}">Pending Payment</span>
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-500 text-white flex items-center justify-center text-sm shadow-md shadow-amber-200 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-amber-600 mt-2">{{ $counts['pending_payment'] }}</div>
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-amber-100/50">
                    <span class="text-[11px] font-medium text-amber-700/80">Approved, unpaid</span>
                    @if($currentSubStatus === 'pending_payment')
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-600 animate-pulse"></span> Active View
                        </span>
                    @else
                        <span class="text-[10px] text-amber-600/70 group-hover:text-amber-700 transition">Click to view &rarr;</span>
                    @endif
                </div>
            </a>

            {{-- Expired --}}
            <a href="{{ route('admin.advertiser.subscriptions.index', ['status' => 'expired']) }}" 
               class="group p-4 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1.5 hover:shadow-xl relative overflow-hidden {{ $currentSubStatus === 'expired' ? 'bg-gradient-to-br from-slate-100 via-white to-rose-50/30 border-slate-500 shadow-lg ring-2 ring-slate-400/30' : 'bg-gradient-to-br from-slate-50/40 via-white to-slate-50/30 border-slate-200 hover:border-slate-400 hover:shadow-md' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold tracking-wider uppercase {{ $currentSubStatus === 'expired' ? 'text-slate-800' : 'text-slate-500' }}">Expired</span>
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-slate-500 to-slate-700 text-white flex items-center justify-center text-sm shadow-md shadow-slate-200 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-calendar-x-fill"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-slate-600 mt-2">{{ $counts['expired'] }}</div>
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-100">
                    <span class="text-[11px] font-medium text-slate-500">Past validity</span>
                    @if($currentSubStatus === 'expired')
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-700 bg-slate-200 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-600 animate-pulse"></span> Active View
                        </span>
                    @else
                        <span class="text-[10px] text-slate-400 group-hover:text-slate-600 transition">Click to view &rarr;</span>
                    @endif
                </div>
            </a>
        </div>

        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('admin.advertiser.subscriptions.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-2 pt-1">
            <div class="md:col-span-3">
                <div class="relative">
                    <i class="bi bi-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search company, app number, brand partner..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border bs bg-surface focus:outline-none focus:ring-1 focus:ring-indigo-500">
                </div>
            </div>
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition shadow-sm border-0 cursor-pointer">
                    Filter
                </button>
                <a href="{{ route('admin.advertiser.subscriptions.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition text-center no-underline">
                    Clear
                </a>
            </div>
        </form>

        {{-- Table --}}
        <div class="overflow-x-auto rounded-lg border bs">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-50 border-b bs font-semibold t2">
                    <tr>
                        <th class="p-3">Company / Brand</th>
                        <th class="p-3">Package Snapshot</th>
                        <th class="p-3">Amount</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Active Period</th>
                        <th class="p-3">Linked Partner</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($subscriptions as $sub)
                        @php
                            $badgeColor = match($sub->status) {
                                'active' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'pending_payment' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'expired' => 'bg-slate-100 text-slate-600 border-slate-200',
                                default => 'bg-rose-50 text-rose-700 border-rose-200',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="p-3">
                                <div class="font-bold text-slate-900 text-sm">
                                    {{ $sub->application?->company_name ?? '—' }}
                                </div>
                                <div class="text-slate-400 font-mono text-[11px]">
                                    App: {{ $sub->application?->application_number ?? '—' }}
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="font-semibold text-slate-800">{{ $sub->package_name_snapshot }}</div>
                                <div class="text-slate-500 text-[11px]">{{ $sub->duration_months }} Month(s)</div>
                            </td>
                            <td class="p-3 font-mono font-bold text-emerald-600">
                                {{ $sub->currency }} {{ number_format((float) $sub->amount, 2) }}
                            </td>
                            <td class="p-3">
                                <span class="px-2.5 py-0.5 rounded-full border text-xs font-semibold capitalize {{ $badgeColor }}">
                                    {{ str_replace('_', ' ', $sub->status) }}
                                </span>
                            </td>
                            <td class="p-3 text-slate-600">
                                @if($sub->starts_at && $sub->expires_at)
                                    <div>{{ $sub->starts_at->format('M d, Y') }} &rarr; {{ $sub->expires_at->format('M d, Y') }}</div>
                                    <div class="text-[11px] text-slate-400">
                                        @if($sub->expires_at->isFuture())
                                            {{ $sub->expires_at->diffForHumans() }}
                                        @else
                                            Expired {{ $sub->expires_at->diffForHumans() }}
                                        @endif
                                    </div>
                                @else
                                    <span class="text-slate-400">Not activated yet</span>
                                @endif
                            </td>
                            <td class="p-3">
                                @if($sub->brandPartner)
                                    <a href="{{ route('admin.brand-partners.show', $sub->brandPartner) }}" class="text-indigo-600 font-semibold hover:underline inline-flex items-center gap-1">
                                        <span>{{ $sub->brandPartner->name }}</span>
                                        <i class="bi bi-box-arrow-up-right text-[10px]"></i>
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.advertiser.subscriptions.show', $sub) }}" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 transition no-underline">
                                    <i class="bi bi-eye"></i>
                                    <span>Details</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-400">
                                <i class="bi bi-inbox text-3xl block mb-2"></i>
                                No subscriptions recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($subscriptions->hasPages())
            <div class="pt-2">
                {{ $subscriptions->links() }}
            </div>
        @endif
    </div>
@endsection
