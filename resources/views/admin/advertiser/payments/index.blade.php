@extends('admin.layouts.app')

@section('title', 'Advertiser Payments')

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
                    <i class="bi bi-credit-card text-indigo-600"></i>
                    Advertiser Payments & Razorpay Reconciliation
                </h1>
                <p class="text-xs t3 mt-0.5">Audit orders, captured payments, provider signatures, and resolve delayed transactions.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.advertiser.applications.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition no-underline">
                    <i class="bi bi-file-earmark-person me-1"></i> Applications
                </a>
                <a href="{{ route('admin.advertiser.subscriptions.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition no-underline">
                    <i class="bi bi-card-checklist me-1"></i> Subscriptions
                </a>
            </div>
        </div>

        {{-- Clickable KPI Tabs with Rich Color Themes & Effects --}}
        @php
            $currentStatus = request('status');
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3.5">
            {{-- All Attempts --}}
            <a href="{{ route('admin.advertiser.payments.index') }}" 
               class="group p-4 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1.5 hover:shadow-xl relative overflow-hidden {{ !$currentStatus ? 'bg-gradient-to-br from-indigo-100/80 via-white to-purple-50 border-indigo-500 shadow-lg shadow-indigo-100/50 ring-2 ring-indigo-400/30' : 'bg-gradient-to-br from-indigo-50/40 via-white to-slate-50/30 border-slate-200 hover:border-indigo-400 hover:shadow-md' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold tracking-wider uppercase {{ !$currentStatus ? 'text-indigo-900' : 'text-slate-600' }}">All Attempts</span>
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center text-sm shadow-md shadow-indigo-200 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-receipt"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-slate-900 mt-2">{{ $counts['total'] }}</div>
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-100/80">
                    <span class="text-[11px] font-medium text-slate-500">Total checkouts</span>
                    @if(!$currentStatus)
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-indigo-700 bg-indigo-100/80 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-600 animate-pulse"></span> Active View
                        </span>
                    @else
                        <span class="text-[10px] text-slate-400 group-hover:text-indigo-600 transition">Click to view &rarr;</span>
                    @endif
                </div>
            </a>

            {{-- Captured --}}
            <a href="{{ route('admin.advertiser.payments.index', ['status' => 'captured']) }}" 
               class="group p-4 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1.5 hover:shadow-xl relative overflow-hidden {{ $currentStatus === 'captured' ? 'bg-gradient-to-br from-emerald-100/80 via-white to-teal-50 border-emerald-500 shadow-lg shadow-emerald-100/50 ring-2 ring-emerald-400/30' : 'bg-gradient-to-br from-emerald-50/40 via-white to-slate-50/30 border-slate-200 hover:border-emerald-400 hover:shadow-md' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold tracking-wider uppercase {{ $currentStatus === 'captured' ? 'text-emerald-900' : 'text-emerald-700' }}">Captured</span>
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-600 text-white flex items-center justify-center text-sm shadow-md shadow-emerald-200 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-emerald-600 mt-2">{{ $counts['captured'] }}</div>
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-emerald-100/50">
                    <span class="text-[11px] font-medium text-emerald-700/80">Paid & active</span>
                    @if($currentStatus === 'captured')
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span> Active View
                        </span>
                    @else
                        <span class="text-[10px] text-emerald-600/70 group-hover:text-emerald-700 transition">Click to view &rarr;</span>
                    @endif
                </div>
            </a>

            {{-- Pending --}}
            <a href="{{ route('admin.advertiser.payments.index', ['status' => 'pending']) }}" 
               class="group p-4 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1.5 hover:shadow-xl relative overflow-hidden {{ $currentStatus === 'pending' ? 'bg-gradient-to-br from-amber-100/80 via-white to-orange-50 border-amber-500 shadow-lg shadow-amber-100/50 ring-2 ring-amber-400/30' : 'bg-gradient-to-br from-amber-50/40 via-white to-slate-50/30 border-slate-200 hover:border-amber-400 hover:shadow-md' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold tracking-wider uppercase {{ $currentStatus === 'pending' ? 'text-amber-900' : 'text-amber-700' }}">Pending</span>
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-orange-500 text-white flex items-center justify-center text-sm shadow-md shadow-amber-200 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-amber-600 mt-2">{{ $counts['pending'] }}</div>
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-amber-100/50">
                    <span class="text-[11px] font-medium text-amber-700/80">Awaiting capture</span>
                    @if($currentStatus === 'pending')
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-600 animate-pulse"></span> Active View
                        </span>
                    @else
                        <span class="text-[10px] text-amber-600/70 group-hover:text-amber-700 transition">Click to view &rarr;</span>
                    @endif
                </div>
            </a>

            {{-- Failed --}}
            <a href="{{ route('admin.advertiser.payments.index', ['status' => 'failed']) }}" 
               class="group p-4 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1.5 hover:shadow-xl relative overflow-hidden {{ $currentStatus === 'failed' ? 'bg-gradient-to-br from-rose-100/80 via-white to-red-50 border-rose-500 shadow-lg shadow-rose-100/50 ring-2 ring-rose-400/30' : 'bg-gradient-to-br from-rose-50/40 via-white to-slate-50/30 border-slate-200 hover:border-rose-400 hover:shadow-md' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold tracking-wider uppercase {{ $currentStatus === 'failed' ? 'text-rose-900' : 'text-rose-700' }}">Failed</span>
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-rose-500 to-red-600 text-white flex items-center justify-center text-sm shadow-md shadow-rose-200 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-x-circle"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-rose-600 mt-2">{{ $counts['failed'] }}</div>
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-rose-100/50">
                    <span class="text-[11px] font-medium text-rose-700/80">Declined/cancelled</span>
                    @if($currentStatus === 'failed')
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-800 bg-rose-100 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-600 animate-pulse"></span> Active View
                        </span>
                    @else
                        <span class="text-[10px] text-rose-600/70 group-hover:text-rose-700 transition">Click to view &rarr;</span>
                    @endif
                </div>
            </a>

            {{-- Total Revenue --}}
            <div class="group p-4 rounded-2xl border-2 border-indigo-300 bg-gradient-to-br from-indigo-100/90 via-purple-50/50 to-white col-span-2 md:col-span-1 shadow-md shadow-indigo-100/50 transform hover:-translate-y-1.5 hover:shadow-xl transition-all duration-300 relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold tracking-wider uppercase text-indigo-900">Total Revenue</span>
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-600 text-white flex items-center justify-center text-sm shadow-md shadow-indigo-200 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-currency-rupee"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-indigo-950 font-mono mt-2">₹{{ number_format($counts['total_captured_amount'], 2) }}</div>
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-indigo-100/80">
                    <span class="text-[11px] text-indigo-700 font-semibold">Captured in INR</span>
                    <i class="bi bi-shield-check text-emerald-600 text-sm"></i>
                </div>
            </div>
        </div>

        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('admin.advertiser.payments.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-2 pt-1">
            <div class="md:col-span-3">
                <div class="relative">
                    <i class="bi bi-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search company, app number, order ID, or payment ID..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border bs bg-surface focus:outline-none focus:ring-1 focus:ring-indigo-500">
                </div>
            </div>
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition shadow-sm border-0 cursor-pointer">
                    Filter
                </button>
                <a href="{{ route('admin.advertiser.payments.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition text-center no-underline">
                    Clear
                </a>
            </div>
        </form>

        {{-- Table --}}
        <div class="overflow-x-auto rounded-lg border bs">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-50 border-b bs font-semibold t2">
                    <tr>
                        <th class="p-3">Company & App #</th>
                        <th class="p-3">Amount</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Razorpay Order ID</th>
                        <th class="p-3">Payment ID</th>
                        <th class="p-3">Created / Captured</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($payments as $payment)
                        @php
                            $badgeColor = match($payment->status) {
                                'captured' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'created', 'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'failed' => 'bg-rose-50 text-rose-700 border-rose-200',
                                default => 'bg-slate-100 text-slate-700 border-slate-200',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="p-3">
                                <div class="font-bold text-slate-900 text-sm">
                                    {{ $payment->subscription?->application?->company_name ?? '—' }}
                                </div>
                                <div class="text-slate-400 font-mono text-[11px]">
                                    App: {{ $payment->subscription?->application?->application_number ?? '—' }}
                                </div>
                            </td>
                            <td class="p-3 font-mono font-bold text-emerald-600">
                                {{ $payment->currency }} {{ number_format((float)$payment->amount, 2) }}
                            </td>
                            <td class="p-3">
                                <span class="px-2.5 py-0.5 rounded-full border text-xs font-semibold capitalize {{ $badgeColor }}">
                                    {{ $payment->status }}
                                </span>
                            </td>
                            <td class="p-3 font-mono text-slate-600">
                                {{ $payment->provider_order_id ?: '—' }}
                            </td>
                            <td class="p-3 font-mono text-slate-600">
                                {{ $payment->provider_payment_id ?: '—' }}
                            </td>
                            <td class="p-3 text-slate-500">
                                <div>{{ $payment->created_at->format('M d, Y H:i') }}</div>
                                @if($payment->captured_at)
                                    <div class="text-emerald-600 text-[11px] font-semibold">Captured: {{ $payment->captured_at->format('H:i:s') }}</div>
                                @endif
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.advertiser.payments.show', $payment) }}" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 transition no-underline">
                                        <i class="bi bi-eye"></i>
                                        <span>Details</span>
                                    </a>
                                    @if($payment->status !== 'captured' && $payment->provider_order_id)
                                        <form method="POST" action="{{ route('admin.advertiser.payments.reconcile', $payment) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 text-xs rounded-lg bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 transition font-semibold cursor-pointer">
                                                Reconcile
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400">
                                <i class="bi bi-inbox text-3xl block mb-2"></i>
                                No payment attempts recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($payments->hasPages())
            <div class="pt-2">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
@endsection
