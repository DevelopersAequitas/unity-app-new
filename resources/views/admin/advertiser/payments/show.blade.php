@extends('admin.layouts.app')

@section('title', 'Payment Details - ' . ($payment->provider_payment_id ?? $payment->id))

@include('admin.partials.grid-head')

@section('content')
    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <i class="bi bi-check-circle-fill text-emerald-600"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="text-emerald-700 hover:text-emerald-900 border-0 bg-transparent cursor-pointer text-sm" onclick="this.parentElement.remove()">&times;</button>
        </div>
    @endif

    <div class="space-y-4">
        {{-- Top Bar --}}
        <div class="rounded-2xl border-2 bs p-4 bg-white shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="text-xs font-mono font-bold px-2.5 py-0.5 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200">
                        {{ strtoupper($payment->provider) }} PAYMENT
                    </span>
                    @php
                        $badgeColor = match($payment->status) {
                            'captured' => 'bg-emerald-50 text-emerald-700 border-emerald-300 ring-1 ring-emerald-500/20',
                            'pending', 'created' => 'bg-amber-50 text-amber-700 border-amber-300 ring-1 ring-amber-500/20',
                            default => 'bg-rose-50 text-rose-700 border-rose-300',
                        };
                    @endphp
                    <span class="px-3 py-0.5 rounded-full border text-xs font-bold uppercase tracking-wider {{ $badgeColor }}">
                        {{ $payment->status }}
                    </span>
                </div>
                <h1 class="text-xl font-black text-slate-900 mt-2 flex items-center gap-2">
                    <i class="bi bi-credit-card-2-front-fill text-indigo-600"></i>
                    {{ $payment->currency }} {{ number_format((float)$payment->amount, 2) }}
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Company: <span class="font-bold text-slate-700">{{ $payment->subscription?->application?->company_name ?? '—' }}</span> &bull; 
                    Recorded {{ $payment->created_at?->format('M d, Y H:i:s') }}
                </p>
            </div>

            {{-- Actions --}}
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.advertiser.payments.index') }}" class="px-3 py-1.5 text-xs font-bold rounded-lg border bs text-slate-700 hover:text-slate-900 hover:bg-slate-50 transition no-underline">
                    &larr; Back to Payments
                </a>

                @if($payment->subscription_id)
                    <a href="{{ route('admin.advertiser.subscriptions.show', $payment->subscription_id) }}" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition no-underline flex items-center gap-1.5">
                        <i class="bi bi-card-checklist"></i> View Subscription
                    </a>
                @endif

                <form method="POST" action="{{ route('admin.advertiser.payments.reconcile', $payment) }}">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition border-0 cursor-pointer shadow-sm flex items-center gap-1.5">
                        <i class="bi bi-arrow-repeat"></i> Reconcile with Gateway
                    </button>
                </form>
            </div>
        </div>

        {{-- Quick Stat Highlights --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5">
            <div class="p-4 rounded-2xl border-2 border-emerald-200 bg-gradient-to-br from-emerald-50/80 via-white to-teal-50/30 shadow-sm">
                <div class="text-xs font-bold text-emerald-800 uppercase">Paid Amount</div>
                <div class="text-2xl font-black text-emerald-700 font-mono mt-1.5">
                    {{ $payment->currency }} {{ number_format((float)$payment->amount, 2) }}
                </div>
                <div class="text-[11px] text-emerald-600/80 mt-0.5">Razorpay transaction</div>
            </div>

            <div class="p-4 rounded-2xl border-2 border-indigo-200 bg-gradient-to-br from-indigo-50/80 via-white to-purple-50/30 shadow-sm">
                <div class="text-xs font-bold text-indigo-800 uppercase">Payment Status</div>
                <div class="text-2xl font-black text-indigo-900 capitalize mt-1.5">
                    {{ $payment->status }}
                </div>
                <div class="text-[11px] text-indigo-600/80 mt-0.5">{{ $payment->captured_at ? 'Captured successfully' : 'Not captured' }}</div>
            </div>

            <div class="p-4 rounded-2xl border-2 border-sky-200 bg-gradient-to-br from-sky-50/80 via-white to-cyan-50/30 shadow-sm">
                <div class="text-xs font-bold text-sky-800 uppercase">Captured At</div>
                <div class="text-base font-black text-sky-900 mt-2">
                    {{ $payment->captured_at ? $payment->captured_at->format('d M Y, H:i') : ($payment->verified_at ? $payment->verified_at->format('d M Y, H:i') : '—') }}
                </div>
                <div class="text-[11px] text-sky-600/80 mt-0.5">Verification timestamp</div>
            </div>

            <div class="p-4 rounded-2xl border-2 border-slate-200 bg-gradient-to-br from-slate-50 via-white to-slate-50/50 shadow-sm">
                <div class="text-xs font-bold text-slate-700 uppercase">Provider</div>
                <div class="text-xl font-black text-slate-900 capitalize mt-2">
                    {{ $payment->provider }}
                </div>
                <div class="text-[11px] text-slate-500 mt-0.5">Payment gateway</div>
            </div>
        </div>

        {{-- Details & Gateway Payload --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 space-y-4">
                {{-- Payment Identifiers --}}
                <div class="rounded-2xl border-2 bs p-5 bg-white shadow-sm space-y-3">
                    <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2 border-b pb-2">
                        <i class="bi bi-shield-lock-fill text-indigo-600"></i>
                        Transaction Identifiers
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                        <div class="p-3 rounded-xl bg-slate-50 border">
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">System Payment UUID</span>
                            <span class="font-mono text-slate-800 select-all font-semibold break-all">{{ $payment->id }}</span>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border">
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Provider Order ID (Razorpay)</span>
                            <span class="font-mono text-slate-800 select-all font-semibold break-all">{{ $payment->provider_order_id ?? '—' }}</span>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border">
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Provider Payment ID (Razorpay)</span>
                            <span class="font-mono text-slate-800 select-all font-semibold break-all">{{ $payment->provider_payment_id ?? '—' }}</span>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border">
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Idempotency Key</span>
                            <span class="font-mono text-slate-800 select-all font-semibold break-all">{{ $payment->idempotency_key ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Raw Gateway Payload --}}
                @if($payment->provider_payload)
                    <div class="rounded-2xl border-2 bs p-5 bg-white shadow-sm space-y-3">
                        <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2 border-b pb-2">
                            <i class="bi bi-code-square text-indigo-600"></i>
                            Raw Gateway Response Payload
                        </h3>
                        <pre class="p-3 rounded-xl bg-slate-900 text-emerald-400 font-mono text-[11px] overflow-x-auto max-h-96">{{ json_encode($payment->provider_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                @endif
            </div>

            {{-- Right Column: Linked Subscription --}}
            <div class="space-y-4">
                <div class="rounded-2xl border-2 bs p-5 bg-white shadow-sm space-y-3">
                    <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2 border-b pb-2">
                        <i class="bi bi-card-checklist text-indigo-600"></i>
                        Linked Subscription
                    </h3>

                    @if($payment->subscription)
                        <div>
                            <div class="text-base font-extrabold text-slate-900">{{ $payment->subscription->application?->company_name ?? 'Advertiser' }}</div>
                            <div class="text-xs text-slate-500">Plan: <span class="font-bold text-slate-700">{{ $payment->subscription->package_name_snapshot }}</span></div>
                        </div>

                        <div class="space-y-2 text-xs pt-1">
                            <div class="flex justify-between py-1 border-b border-slate-100">
                                <span class="text-slate-500">Subscription Status</span>
                                <span class="font-bold capitalize text-emerald-600">{{ $payment->subscription->status }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100">
                                <span class="text-slate-500">Duration</span>
                                <span class="font-bold text-slate-800">{{ $payment->subscription->duration_months }} Month(s)</span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-slate-500">Active Period</span>
                                <span class="font-bold text-slate-800">
                                    {{ $payment->subscription->starts_at?->format('d M Y') ?? '—' }} 
                                    to 
                                    {{ $payment->subscription->expires_at?->format('d M Y') ?? '—' }}
                                </span>
                            </div>
                        </div>

                        <div class="pt-2 border-t">
                            <a href="{{ route('admin.advertiser.subscriptions.show', $payment->subscription_id) }}" class="w-full text-center block px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition no-underline shadow-sm">
                                View Full Subscription &rarr;
                            </a>
                        </div>
                    @else
                        <div class="text-slate-400 text-xs py-2">No linked subscription found.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
