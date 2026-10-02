@extends('admin.layouts.app')

@section('title', 'Circle Joining Request Detail')

@include('admin.partials.grid-head')

@php
    $statusLabels = [
        'pending_cd_approval' => 'Pending for CD Approval',
        'pending_id_approval' => 'Pending for ID Approval',
        'pending_circle_fee' => 'Pending for Circle Fee',
        'circle_member' => 'Circle Member (Approved)',
        'paid' => 'Paid',
        'rejected_by_cd' => 'Rejected by CD',
        'rejected_by_id' => 'Rejected by ID',
        'cancelled' => 'Cancelled',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];
    $hasValidCircle = !empty($record->circle_id) && !empty($record->circle);
@endphp

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-6 relative admin-grid-card space-y-6">
    <!-- Top Navigation & Actions Bar -->
    <div class="flex flex-wrap justify-between items-center gap-4 border-b bs pb-4">
        <div class="flex items-center gap-3">
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">
                Circle Joining Request Detail
            </h2>
            @php($statusKey = $record->status)
            <span class="chip px-2.5 py-0.5 text-xs font-semibold rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200">
                {{ $statusLabels[$statusKey] ?? $statusKey }}
            </span>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if($canApprove)
                <form method="POST" action="{{ route('admin.circle-joining-requests.approve', $record->id) }}" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition cursor-pointer shadow-sm">
                        Approve Request
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.circle-joining-requests.reject', $record->id) }}" class="inline" onsubmit="const r = prompt('Enter rejection reason (required):'); if (!r || !r.trim()) { return false; } this.querySelector('input[name=reason]').value = r.trim(); return true;">
                    @csrf
                    <input type="hidden" name="reason">
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg border border-rose-300 bg-white text-rose-600 hover:bg-rose-50 transition cursor-pointer shadow-sm">
                        Reject Request
                    </button>
                </form>
            @endif

            <a href="{{ route('admin.circle-joining-requests.index') }}" class="px-3.5 py-2 text-xs font-semibold rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition no-underline flex items-center gap-1.5">
                <span>←</span> Back
            </a>
        </div>
    </div>

    @if(!$hasValidCircle)
        <div class="p-4 rounded-xl border border-rose-200 bg-rose-50 text-rose-800 text-xs font-medium flex items-center gap-2">
            <span class="font-bold text-sm">⚠️</span>
            <span>Requested Circle is missing or invalid. This request cannot be approved until a valid Circle is associated.</span>
        </div>
    @endif

    <!-- Grid Sections -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left Column: APPLICANT & Status Overview -->
        <div class="space-y-6">
            <!-- APPLICANT Card -->
            <div class="p-5 rounded-xl border bs surface space-y-4">
                <div class="border-b bs pb-3">
                    <span class="block text-[11px] uppercase tracking-wider font-semibold text-indigo-500 mb-1">Section</span>
                    <h3 class="font-display font-bold text-sm text-gray-900 uppercase tracking-wide m-0">APPLICANT</h3>
                </div>

                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        {{ strtoupper(substr($record->user?->adminDisplayName() ?? 'P', 0, 1)) }}
                    </div>
                    <div>
                        <h3 class="font-bold text-base t1 m-0">{{ $record->user?->adminDisplayName() ?? '—' }}</h3>
                        <p class="text-xs t3 m-0">Applicant Peer</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs pt-2">
                    <div>
                        <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-0.5">Email</span>
                        <span class="font-medium t1 text-sm">{{ $record->user?->email ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-0.5">Phone</span>
                        <span class="font-medium t1 text-sm">{{ $record->user?->phone ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-0.5">Company</span>
                        <span class="font-medium t1 text-sm">{{ $record->user?->adminCompanyLabel() ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-0.5">City</span>
                        <span class="font-medium t1 text-sm">{{ $record->user?->adminCityLabel() ?? '—' }}</span>
                    </div>
                </div>
            </div>

            <!-- Status Overview Card -->
            <div class="p-5 rounded-xl border bs surface space-y-4">
                <h4 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Status Overview</h4>
                
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="p-3 rounded-lg border bs surface-2">
                        <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Request Status</span>
                        <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-md bg-gray-100 text-gray-800 border border-gray-200">
                            {{ $statusLabels[$record->status] ?? $record->status }}
                        </span>
                    </div>

                    <div class="p-3 rounded-lg border bs surface-2">
                        <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Payment Status</span>
                        @php($paymentStatus = $record->paymentStatusLabel())
                        @if($paymentStatus === 'Paid')
                            <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200">Paid</span>
                        @elseif($paymentStatus === 'Unpaid')
                            <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-md bg-amber-50 text-amber-700 border border-amber-200">Unpaid</span>
                        @else
                            <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-md bg-gray-100 text-gray-700 border border-gray-200">{{ $paymentStatus }}</span>
                        @endif
                    </div>

                    <div class="p-3 rounded-lg border bs surface-2">
                        <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">DED Approval</span>
                        @php($dedApprovalStatus = $record->effectiveDedApprovalStatus())
                        @if($dedApprovalStatus === 'approved')
                            <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200">Approved</span>
                            <span class="block text-[11px] text-emerald-600 mt-1">Approved{{ $record->dedApprovedBy ? ' by ' . $record->dedApprovedBy->adminDisplayName() : ' by DED' }}</span>
                        @elseif($dedApprovalStatus === 'rejected')
                            <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-md bg-rose-50 text-rose-700 border border-rose-200">Rejected</span>
                        @else
                            <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-md bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                        @endif
                    </div>
                </div>

                <div class="pt-2">
                    <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1.5">Reason for Joining</span>
                    <div class="p-3.5 rounded-lg border bs bg-gray-50 text-xs t1 leading-relaxed">
                        {{ $record->reason_for_joining ?: 'No reason specified.' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: CIRCLE TO JOIN & APPROVAL -->
        <div class="space-y-6">
            <!-- CIRCLE TO JOIN Card -->
            <div class="p-5 rounded-xl border bs surface space-y-4">
                <div class="border-b bs pb-3">
                    <span class="block text-[11px] uppercase tracking-wider font-semibold text-indigo-500 mb-1">Section</span>
                    <h3 class="font-display font-bold text-sm text-gray-900 uppercase tracking-wide m-0">CIRCLE TO JOIN</h3>
                </div>

                <div class="space-y-3">
                    <div>
                        <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Requested Circle:</span>
                        @if($hasValidCircle)
                            <h3 class="font-bold text-lg text-indigo-900 m-0">{{ $record->circle->name }}</h3>
                            <span class="block text-[11px] font-mono text-gray-500 mt-1">Circle ID: {{ $record->circle->id }}</span>
                            @if($record->circle->template)
                                <span class="inline-block mt-1 px-2 py-0.5 text-[11px] font-medium rounded bg-gray-100 text-gray-600 border border-gray-200">
                                    Template: {{ $record->circle->template->name }} ({{ $record->circle->template->slug }})
                                </span>
                            @endif
                        @else
                            <div class="text-rose-600 font-semibold text-sm">
                                Requested Circle is missing or invalid.
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t bs">
                        <div>
                            <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-0.5">Category</span>
                            <span class="font-semibold text-xs t1">{{ $categoryPath['level1']?->name ?? ($record->circleCategory?->name ?? '—') }}</span>
                        </div>
                        @if(!empty($categoryPath['subCategory']))
                            <div>
                                <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-0.5">Sub Category</span>
                                <span class="font-semibold text-xs text-indigo-600">{{ $categoryPath['subCategory']->name }}</span>
                            </div>
                        @endif
                    </div>

                    @if(($categoryPath['level1'] ?? null) || ($categoryPath['level2'] ?? null) || ($categoryPath['level3'] ?? null) || ($categoryPath['level4'] ?? null))
                        <div class="pt-3 border-t bs">
                            <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1.5">Category Hierarchy</span>
                            <div class="p-3.5 rounded-lg border bs bg-gray-50/80 space-y-1.5 text-xs font-medium">
                                @if($categoryPath['level1'] ?? null)
                                    <div class="text-indigo-600 font-semibold flex items-center gap-1.5">
                                        <span>{{ $categoryPath['level1']->name }}</span>
                                        @if(($categoryPath['level2'] ?? null) || ($categoryPath['level3'] ?? null) || ($categoryPath['level4'] ?? null))
                                            <span class="text-gray-400">→</span>
                                        @endif
                                    </div>
                                @endif
                                @if(($categoryPath['level2'] ?? null) || ($categoryPath['level3'] ?? null) || ($categoryPath['level4'] ?? null))
                                    <div class="flex items-center gap-1.5 flex-wrap text-gray-700">
                                        @if($categoryPath['level2'] ?? null)
                                            <span class="font-semibold text-gray-800 uppercase tracking-wide text-[11px]">{{ $categoryPath['level2']->name }}</span>
                                        @endif
                                        @if($categoryPath['level3'] ?? null)
                                            <span class="text-gray-400">→</span>
                                            <span>{{ $categoryPath['level3']->name }}</span>
                                        @endif
                                        @if($categoryPath['level4'] ?? null)
                                            <span class="text-gray-400">→</span>
                                            <span>{{ $categoryPath['level4']->name }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- APPROVAL Card -->
            <div class="p-5 rounded-xl border bs surface space-y-4">
                <div class="border-b bs pb-3">
                    <span class="block text-[11px] uppercase tracking-wider font-semibold text-indigo-500 mb-1">Section</span>
                    <h3 class="font-display font-bold text-sm text-gray-900 uppercase tracking-wide m-0">APPROVAL</h3>
                </div>

                <div class="space-y-4">
                    <div>
                        <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Circle:</span>
                        <div class="p-3 rounded-lg border bs bg-gray-50 font-semibold text-xs text-gray-800">
                            {{ $record->circle?->name ?? 'Requested Circle is missing or invalid' }}
                        </div>
                    </div>

                    @if($canApprove)
                        @if($hasValidCircle)
                            <div class="flex items-center gap-3 pt-2">
                                <form method="POST" action="{{ route('admin.circle-joining-requests.approve', $record->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="px-5 py-2.5 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition cursor-pointer shadow-sm flex items-center gap-1.5">
                                        <span>✓</span> Approve Request
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.circle-joining-requests.reject', $record->id) }}" class="inline" onsubmit="const r = prompt('Enter rejection reason (required):'); if (!r || !r.trim()) { return false; } this.querySelector('input[name=reason]').value = r.trim(); return true;">
                                    @csrf
                                    <input type="hidden" name="reason">
                                    <button type="submit" class="px-5 py-2.5 text-xs font-semibold rounded-lg border border-rose-300 bg-white text-rose-600 hover:bg-rose-50 transition cursor-pointer shadow-sm flex items-center gap-1.5">
                                        <span>✕</span> Reject Request
                                    </button>
                                </form>
                            </div>
                        @else
                            <div class="text-xs text-rose-600 font-medium">
                                Cannot approve this request because the requested Circle is missing or invalid.
                            </div>
                        @endif
                    @elseif(in_array((string)$record->status, ['circle_member', 'paid'], true))
                        <div class="p-3 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 text-xs font-medium flex items-center gap-2">
                            <span>✓</span>
                            <span>Approved — User is an active Circle Member of <strong>{{ $record->circle?->name }}</strong>.</span>
                        </div>
                    @elseif(str_contains((string)$record->status, 'reject') || $record->status === 'cancelled')
                        <div class="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-800 text-xs font-medium space-y-1">
                            <div class="font-semibold">Rejected — This request has been rejected.</div>
                            @if($record->cd_rejection_reason || $record->id_rejection_reason || !empty($record->notes['rejection_reason']))
                                <div class="text-[11px] text-rose-700">
                                    Reason: {{ $record->cd_rejection_reason ?: ($record->id_rejection_reason ?: ($record->notes['rejection_reason'] ?? '')) }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
