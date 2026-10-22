@extends('admin.layouts.app')

@section('title', 'Subscription Details - ' . ($subscription->application?->company_name ?? $subscription->id))

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
                        Sub ID: {{ substr($subscription->id, 0, 8) }}...
                    </span>
                    @php
                        $badgeColor = match($subscription->status) {
                            'active' => 'bg-emerald-50 text-emerald-700 border-emerald-300 ring-1 ring-emerald-500/20',
                            'pending_payment' => 'bg-amber-50 text-amber-700 border-amber-300 ring-1 ring-amber-500/20',
                            'expired' => 'bg-slate-100 text-slate-700 border-slate-300',
                            default => 'bg-rose-50 text-rose-700 border-rose-300',
                        };
                    @endphp
                    <span class="px-3 py-0.5 rounded-full border text-xs font-bold uppercase tracking-wider {{ $badgeColor }}">
                        {{ str_replace('_', ' ', $subscription->status) }}
                    </span>
                    @if($subscription->status === 'active')
                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-100/70 px-2.5 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span> Live in Unity App
                        </span>
                    @endif
                </div>
                <h1 class="text-xl font-black text-slate-900 mt-2 flex items-center gap-2">
                    <i class="bi bi-card-checklist text-indigo-600"></i>
                    {{ $subscription->application?->company_name ?? 'Advertiser Subscription' }}
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Plan: <span class="font-bold text-slate-700">{{ $subscription->package_name_snapshot }}</span> &bull; 
                    Created {{ $subscription->created_at?->format('M d, Y H:i') }}
                </p>
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.advertiser.subscriptions.index') }}" class="px-3 py-1.5 text-xs font-bold rounded-lg border bs text-slate-700 hover:text-slate-900 hover:bg-slate-50 transition no-underline">
                    &larr; Back to Subscriptions
                </a>

                @if($subscription->application)
                    <a href="{{ route('admin.advertiser.applications.show', $subscription->application_id) }}" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition no-underline flex items-center gap-1.5">
                        <i class="bi bi-file-earmark-person"></i> View Application
                    </a>
                @endif

                @if($subscription->brand_partner_id)
                    <a href="{{ route('admin.brand-partners.index', ['q' => $subscription->application?->company_name]) }}" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition no-underline shadow-sm flex items-center gap-1.5">
                        <i class="bi bi-building"></i> Live Brand Partner &rarr;
                    </a>
                @endif
            </div>
        </div>

        {{-- Quick Stat Highlights --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5">
            {{-- Package Amount --}}
            <div class="p-4 rounded-2xl border-2 border-emerald-200 bg-gradient-to-br from-emerald-50/80 via-white to-teal-50/30 shadow-sm">
                <div class="flex items-center justify-between text-xs font-bold text-emerald-800 uppercase">
                    <span>Package Amount</span>
                    <i class="bi bi-cash-stack text-emerald-600 text-base"></i>
                </div>
                <div class="text-2xl font-black text-emerald-700 font-mono mt-1.5">
                    {{ $subscription->currency }} {{ number_format((float)$subscription->amount, 2) }}
                </div>
                <div class="text-[11px] text-emerald-600/80 mt-0.5">Fixed plan fee</div>
            </div>

            {{-- Duration --}}
            <div class="p-4 rounded-2xl border-2 border-indigo-200 bg-gradient-to-br from-indigo-50/80 via-white to-purple-50/30 shadow-sm">
                <div class="flex items-center justify-between text-xs font-bold text-indigo-800 uppercase">
                    <span>Campaign Duration</span>
                    <i class="bi bi-calendar3 text-indigo-600 text-base"></i>
                </div>
                <div class="text-2xl font-black text-indigo-900 mt-1.5">
                    {{ $subscription->duration_months }} Month(s)
                </div>
                <div class="text-[11px] text-indigo-600/80 mt-0.5">{{ $subscription->auto_renew ? 'Auto-renew enabled' : 'Manual renewal' }}</div>
            </div>

            {{-- Activation Date --}}
            <div class="p-4 rounded-2xl border-2 border-sky-200 bg-gradient-to-br from-sky-50/80 via-white to-cyan-50/30 shadow-sm">
                <div class="flex items-center justify-between text-xs font-bold text-sky-800 uppercase">
                    <span>Starts At</span>
                    <i class="bi bi-play-circle text-sky-600 text-base"></i>
                </div>
                <div class="text-lg font-black text-sky-900 mt-1.5">
                    {{ $subscription->starts_at ? $subscription->starts_at->format('d M Y') : '—' }}
                </div>
                <div class="text-[11px] text-sky-600/80 mt-0.5">{{ $subscription->starts_at ? $subscription->starts_at->format('H:i A') : 'Pending payment' }}</div>
            </div>

            {{-- Expiry Date --}}
            <div class="p-4 rounded-2xl border-2 border-amber-200 bg-gradient-to-br from-amber-50/80 via-white to-orange-50/30 shadow-sm">
                <div class="flex items-center justify-between text-xs font-bold text-amber-800 uppercase">
                    <span>Expires At</span>
                    <i class="bi bi-hourglass-split text-amber-600 text-base"></i>
                </div>
                <div class="text-lg font-black text-amber-900 mt-1.5">
                    {{ $subscription->expires_at ? $subscription->expires_at->format('d M Y') : '—' }}
                </div>
                <div class="text-[11px] text-amber-700/80 mt-0.5">
                    @if($subscription->expires_at)
                        @if($subscription->expires_at->isPast())
                            <span class="text-rose-600 font-bold">Expired</span>
                        @else
                            {{ $subscription->expires_at->diffForHumans() }}
                        @endif
                    @else
                        Unscheduled
                    @endif
                </div>
            </div>
        </div>

        {{-- Main Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Left 2 Columns: Subscription Details & Payment History --}}
            <div class="lg:col-span-2 space-y-4">
                {{-- Subscription Details Card --}}
                <div class="rounded-2xl border-2 bs p-5 bg-white shadow-sm space-y-3">
                    <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2 border-b pb-2">
                        <i class="bi bi-info-circle-fill text-indigo-600"></i>
                        Subscription Snapshot
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                        <div class="p-3 rounded-xl bg-slate-50 border">
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Subscription UUID</span>
                            <span class="font-mono text-slate-800 select-all font-semibold break-all">{{ $subscription->id }}</span>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border">
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Package Name</span>
                            <span class="font-bold text-slate-800">{{ $subscription->package_name_snapshot }}</span>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border">
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Applicant User</span>
                            <span class="font-semibold text-slate-800">{{ $subscription->user?->name ?? '—' }} ({{ $subscription->user?->email ?? 'No Email' }})</span>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border">
                            <span class="text-slate-400 block text-[10px] uppercase font-bold">Activated At</span>
                            <span class="font-semibold text-slate-800">{{ $subscription->activated_at ? $subscription->activated_at->format('M d, Y H:i:s') : 'Not yet activated' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Payment History Card --}}
                <div class="rounded-2xl border-2 bs p-5 bg-white shadow-sm space-y-3">
                    <div class="flex items-center justify-between border-b pb-2">
                        <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                            <i class="bi bi-credit-card-2-front-fill text-emerald-600"></i>
                            Associated Payment Attempts
                        </h3>
                        <span class="text-xs font-bold text-slate-500">{{ $subscription->payments->count() }} attempt(s)</span>
                    </div>

                    @if($subscription->payments->isEmpty())
                        <div class="text-center py-6 text-slate-400 text-xs">
                            <i class="bi bi-credit-card text-2xl block mb-1"></i>
                            No payment attempts recorded for this subscription yet.
                        </div>
                    @else
                        <div class="overflow-x-auto rounded-xl border">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 font-semibold text-slate-600 border-b">
                                    <tr>
                                        <th class="p-2.5">Provider / Order ID</th>
                                        <th class="p-2.5">Payment ID</th>
                                        <th class="p-2.5">Amount</th>
                                        <th class="p-2.5">Status</th>
                                        <th class="p-2.5">Date</th>
                                        <th class="p-2.5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($subscription->payments as $pmt)
                                        @php
                                            $pmtBadge = match($pmt->status) {
                                                'captured' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                'pending', 'created' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                default => 'bg-rose-50 text-rose-700 border-rose-200',
                                            };
                                        @endphp
                                        <tr class="hover:bg-slate-50/50 transition">
                                            <td class="p-2.5 font-mono text-[11px]">
                                                <div class="font-bold text-slate-800 uppercase">{{ $pmt->provider }}</div>
                                                <div class="text-slate-400 select-all">{{ $pmt->provider_order_id ?? '—' }}</div>
                                            </td>
                                            <td class="p-2.5 font-mono text-[11px] text-slate-700 select-all">
                                                {{ $pmt->provider_payment_id ?? '—' }}
                                            </td>
                                            <td class="p-2.5 font-bold font-mono text-emerald-600">
                                                {{ $pmt->currency }} {{ number_format((float)$pmt->amount, 2) }}
                                            </td>
                                            <td class="p-2.5">
                                                <span class="px-2 py-0.5 rounded-full border text-[10px] font-bold uppercase {{ $pmtBadge }}">
                                                    {{ $pmt->status }}
                                                </span>
                                            </td>
                                            <td class="p-2.5 text-slate-500 text-[11px]">
                                                {{ $pmt->created_at?->format('d M, H:i') }}
                                            </td>
                                            <td class="p-2.5 text-right">
                                                <a href="{{ route('admin.advertiser.payments.show', $pmt->id) }}" class="px-2 py-1 rounded bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-[11px] font-bold no-underline">
                                                    Details &rarr;
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Live Brand Partner Record Card (if active) --}}
                @if($subscription->brandPartner)
                    <div class="rounded-2xl border-2 border-emerald-300 bg-gradient-to-br from-emerald-50/40 via-white to-teal-50/20 p-5 shadow-sm space-y-3">
                        <div class="flex items-center justify-between border-b border-emerald-100 pb-2">
                            <h3 class="text-sm font-extrabold text-emerald-900 flex items-center gap-2">
                                <i class="bi bi-patch-check-fill text-emerald-600"></i>
                                Live Brand Partner Record in Database
                            </h3>
                            <span class="text-xs font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                                ID: {{ $subscription->brand_partner_id }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                            <div class="p-3 rounded-xl bg-white border border-emerald-100">
                                <span class="text-slate-400 block text-[10px] uppercase font-bold">Partner Name</span>
                                <span class="font-bold text-slate-800">{{ $subscription->brandPartner->name }}</span>
                            </div>
                            <div class="p-3 rounded-xl bg-white border border-emerald-100">
                                <span class="text-slate-400 block text-[10px] uppercase font-bold">Valid Period</span>
                                <span class="font-semibold text-slate-800">
                                    {{ $subscription->brandPartner->valid_from ? \Carbon\Carbon::parse($subscription->brandPartner->valid_from)->format('d M Y') : '—' }} 
                                    to 
                                    {{ $subscription->brandPartner->valid_to ? \Carbon\Carbon::parse($subscription->brandPartner->valid_to)->format('d M Y') : '—' }}
                                </span>
                            </div>
                            <div class="p-3 rounded-xl bg-white border border-emerald-100">
                                <span class="text-slate-400 block text-[10px] uppercase font-bold">App Status</span>
                                <span class="font-bold text-emerald-600">
                                    {{ $subscription->brandPartner->is_active ? 'Active' : 'Inactive' }} &bull; 
                                    {{ $subscription->brandPartner->is_sponsored ? 'Sponsored' : 'Regular' }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right Column: Company & Application Profile --}}
            <div class="space-y-4">
                @if($subscription->application)
                    <div class="rounded-2xl border-2 bs p-5 bg-white shadow-sm space-y-3">
                        <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2 border-b pb-2">
                            <i class="bi bi-building text-indigo-600"></i>
                            Linked Company
                        </h3>

                        <div>
                            <div class="text-base font-extrabold text-slate-900">{{ $subscription->application->company_name }}</div>
                            <div class="text-xs text-slate-500 font-mono">App # {{ $subscription->application->application_number }}</div>
                        </div>

                        <div class="space-y-2 text-xs pt-1">
                            <div class="flex items-center gap-2 text-slate-600">
                                <i class="bi bi-person text-indigo-500"></i>
                                <span>{{ $subscription->application->contact_person_name }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-slate-600">
                                <i class="bi bi-envelope text-indigo-500"></i>
                                <span>{{ $subscription->application->email }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-slate-600">
                                <i class="bi bi-telephone text-indigo-500"></i>
                                <span>{{ $subscription->application->mobile_number }}</span>
                            </div>
                            @if($subscription->application->website_url)
                                <div class="flex items-center gap-2 text-indigo-600 font-semibold truncate">
                                    <i class="bi bi-globe"></i>
                                    <a href="{{ $subscription->application->website_url }}" target="_blank" class="truncate hover:underline">
                                        {{ $subscription->application->website_url }}
                                    </a>
                                </div>
                            @endif
                        </div>

                        <div class="pt-2 border-t">
                            <a href="{{ route('admin.advertiser.applications.show', $subscription->application_id) }}" class="w-full text-center block px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition no-underline shadow-sm">
                                View Full Application Review &rarr;
                            </a>
                        </div>
                    </div>
                @endif

                {{-- Package Features Snapshot --}}
                @if($subscription->package)
                    <div class="rounded-2xl border-2 bs p-5 bg-white shadow-sm space-y-3">
                        <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2 border-b pb-2">
                            <i class="bi bi-box-seam-fill text-indigo-600"></i>
                            Package Entitlements
                        </h3>

                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between items-center py-1 border-b border-slate-100">
                                <span class="text-slate-500">Max Creatives</span>
                                <span class="font-bold text-slate-800">{{ $subscription->package->max_creatives }}</span>
                            </div>
                            <div class="flex justify-between items-center py-1 border-b border-slate-100">
                                <span class="text-slate-500">Placement Types</span>
                                <span class="font-bold text-slate-800">
                                    {{ is_array($subscription->package->placement_types) ? implode(', ', $subscription->package->placement_types) : 'Standard' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center py-1">
                                <span class="text-slate-500">Billing Interval</span>
                                <span class="font-bold text-slate-800">{{ $subscription->package->billing_interval }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
