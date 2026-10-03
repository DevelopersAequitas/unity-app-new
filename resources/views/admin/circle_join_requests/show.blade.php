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
            <?php $statusKey = $record->status; ?>
            <span class="chip px-2.5 py-0.5 text-xs font-semibold rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200">
                {{ $statusLabels[$statusKey] ?? $statusKey }}
            </span>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if($canApprove)
                @if($record->status === \App\Models\CircleJoinRequest::STATUS_PENDING_CD_APPROVAL)
                    <button type="button" onclick="openCircleSelectModal()" class="px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition cursor-pointer shadow-sm flex items-center gap-1.5">
                        <span>✓</span> Approve CD & Select Circle
                    </button>
                @elseif($record->status === \App\Models\CircleJoinRequest::STATUS_PENDING_ID_APPROVAL)
                    <form method="POST" action="{{ route('admin.circle-joining-requests.approve-id', $record->id) }}" class="inline">
                        @csrf
                        <input type="hidden" name="circle_id" value="{{ $record->circle_id }}">
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition cursor-pointer shadow-sm flex items-center gap-1.5" onclick="return confirm('Approve ID for circle: {{ addslashes($record->circle?->name ?? 'assigned circle') }}?');">
                            <span>✓</span> Approve ID Request
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.circle-joining-requests.approve', $record->id) }}" class="inline">
                        @csrf
                        <input type="hidden" name="circle_id" value="{{ $record->circle_id }}">
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition cursor-pointer shadow-sm">
                            Approve Request
                        </button>
                    </form>
                @endif

                <button type="button" onclick="submitRejection()" class="px-4 py-2 text-xs font-semibold rounded-lg border border-rose-300 bg-white text-rose-600 hover:bg-rose-50 transition cursor-pointer shadow-sm">
                    Reject Request
                </button>
            @endif

            <a href="{{ route('admin.circle-joining-requests.index') }}" class="px-3.5 py-2 text-xs font-semibold rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition no-underline flex items-center gap-1.5">
                <span>←</span> Back
            </a>
        </div>
    </div>

    @if($record->status === \App\Models\CircleJoinRequest::STATUS_PENDING_CD_APPROVAL)
        <div class="p-4 rounded-xl border border-indigo-200 bg-indigo-50/80 text-indigo-900 text-xs font-medium flex items-center gap-3">
            <span class="text-base">📋</span>
            <div>
                <strong>Step 1 of 2 (CD Approval):</strong> Please select the specific Circle for this applicant from the matching category below. Upon approval, this request will advance to ID approval.
            </div>
        </div>
    @elseif($record->status === \App\Models\CircleJoinRequest::STATUS_PENDING_ID_APPROVAL)
        <div class="p-4 rounded-xl border border-emerald-200 bg-emerald-50/80 text-emerald-900 text-xs font-medium flex items-center gap-3">
            <span class="text-base">✓</span>
            <div>
                <strong>Step 2 of 2 (ID Approval):</strong> Circle <strong>{{ $record->circle?->name }}</strong> was assigned during CD approval. Click &ldquo;Approve ID Request&rdquo; to complete approval and open payment.
            </div>
        </div>
    @elseif(!$hasValidCircle)
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
                        <?php $paymentStatus = $record->paymentStatusLabel(); ?>
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
                        <?php $dedApprovalStatus = $record->effectiveDedApprovalStatus(); ?>
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
                        <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Assigned / Requested Circle:</span>
                        @if($hasValidCircle)
                            <h3 class="font-bold text-lg text-indigo-900 m-0">{{ $record->circle->name }}</h3>
                            <span class="block text-[11px] font-mono text-gray-500 mt-1">Circle ID: {{ $record->circle->id }}</span>
                            @if($record->circle->template)
                                <span class="inline-block mt-1 px-2 py-0.5 text-[11px] font-medium rounded bg-gray-100 text-gray-600 border border-gray-200">
                                    Template: {{ $record->circle->template->name }} ({{ $record->circle->template->slug }})
                                </span>
                            @endif
                        @elseif($record->status === \App\Models\CircleJoinRequest::STATUS_PENDING_CD_APPROVAL)
                            <div class="text-amber-700 font-semibold text-sm flex items-center gap-1.5">
                                <span>⏳</span> Pending Circle Selection by CD / Admin
                            </div>
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
                    @if($record->status === \App\Models\CircleJoinRequest::STATUS_PENDING_CD_APPROVAL)
                        <!-- Step 1: CD Approval with Circle Selection -->
                        <div class="space-y-3">
                            <div class="p-3.5 rounded-lg border border-indigo-100 bg-indigo-50/50 space-y-1">
                                <span class="block text-[11px] uppercase tracking-wider font-semibold text-indigo-600">Step 1 of 2: CD Approval & Circle Assignment</span>
                                <p class="text-xs text-indigo-950 m-0">
                                    Select the circle to assign for this request. Circles matching requested category <strong>{{ $categoryPath['level1']?->name ?? ($record->circleCategory?->name ?? 'None') }}</strong> are listed first.
                                </p>
                            </div>

                            @if($canApprove)
                                <form method="POST" action="{{ route('admin.circle-joining-requests.approve-cd', $record->id) }}" id="cardApprovalForm" class="space-y-3">
                                    @csrf
                                    <div>
                                        <label for="card_select_circle_id" class="block text-[11px] uppercase tracking-wider font-semibold text-gray-700 mb-1">
                                            Select Circle to Assign <span class="text-rose-500">*</span>
                                        </label>
                                        <select name="circle_id" id="card_select_circle_id" required class="w-full text-xs font-medium p-2.5 rounded-lg border border-gray-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-white shadow-sm">
                                            @if($categoryCircles->isNotEmpty())
                                                <optgroup label="Circles in {{ $categoryPath['level1']?->name ?? 'Requested Category' }} (Recommended)">
                                                    @foreach($categoryCircles as $circle)
                                                        <option value="{{ $circle->id }}" {{ (string)$record->circle_id === (string)$circle->id ? 'selected' : '' }}>
                                                            ★ {{ $circle->name }}
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                            @if($otherCircles->isNotEmpty())
                                                <optgroup label="Other Available Circles">
                                                    @foreach($otherCircles as $circle)
                                                        <option value="{{ $circle->id }}" {{ (string)$record->circle_id === (string)$circle->id ? 'selected' : '' }}>
                                                            {{ $circle->name }}
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                        </select>
                                    </div>

                                    <div class="flex items-center gap-3 pt-2">
                                        <button type="submit" class="px-5 py-2.5 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition cursor-pointer shadow-sm flex items-center gap-1.5">
                                            <span>✓</span> Approve CD & Assign Circle
                                        </button>

                                        <button type="button" onclick="submitRejection()" class="px-5 py-2.5 text-xs font-semibold rounded-lg border border-rose-300 bg-white text-rose-600 hover:bg-rose-50 transition cursor-pointer shadow-sm flex items-center gap-1.5">
                                            <span>✕</span> Reject Request
                                        </button>
                                    </div>
                                </form>
                            @else
                                <div class="text-xs text-gray-500 font-medium">
                                    You do not have permission to approve this request at this stage.
                                </div>
                            @endif
                        </div>

                    @elseif($record->status === \App\Models\CircleJoinRequest::STATUS_PENDING_ID_APPROVAL)
                        <!-- Step 2: ID Approval (Circle Already Assigned, DO NOT ask again) -->
                        <div class="space-y-3">
                            <div class="p-3.5 rounded-lg border border-emerald-200 bg-emerald-50/60 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="block text-[11px] uppercase tracking-wider font-semibold text-emerald-800">Step 2 of 2: ID Approval</span>
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-emerald-100 text-emerald-700 border border-emerald-300">✓ Circle Assigned</span>
                                </div>
                                <div class="text-xs text-gray-800">
                                    Assigned Circle: <strong class="text-indigo-900 text-sm font-bold">{{ $record->circle?->name ?? '—' }}</strong>
                                </div>
                                <p class="text-[11px] text-emerald-700 m-0">
                                    The circle was assigned during CD approval. Approving now will advance the request to Circle Fee Payment.
                                </p>
                            </div>

                            @if($canApprove)
                                <form method="POST" action="{{ route('admin.circle-joining-requests.approve-id', $record->id) }}" class="space-y-3">
                                    @csrf
                                    <input type="hidden" name="circle_id" value="{{ $record->circle_id }}">
                                    <div class="flex items-center gap-3 pt-2">
                                        <button type="submit" class="px-5 py-2.5 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition cursor-pointer shadow-sm flex items-center gap-1.5">
                                            <span>✓</span> Approve ID Request
                                        </button>

                                        <button type="button" onclick="submitRejection()" class="px-5 py-2.5 text-xs font-semibold rounded-lg border border-rose-300 bg-white text-rose-600 hover:bg-rose-50 transition cursor-pointer shadow-sm flex items-center gap-1.5">
                                            <span>✕</span> Reject Request
                                        </button>
                                    </div>
                                </form>
                            @else
                                <div class="text-xs text-gray-500 font-medium">
                                    You do not have permission to approve this request at this stage.
                                </div>
                            @endif
                        </div>

                    @elseif($record->status === \App\Models\CircleJoinRequest::STATUS_PENDING_CIRCLE_FEE)
                        <div class="p-4 rounded-lg border border-blue-200 bg-blue-50 text-blue-900 text-xs space-y-2">
                            <div class="font-bold flex items-center gap-1.5">
                                <span class="text-sm">✓</span> Approved by CD & ID
                            </div>
                            <div>
                                Assigned Circle: <strong>{{ $record->circle?->name }}</strong>
                            </div>
                            <div class="text-[11px] text-blue-700">
                                Status: <strong>Pending Circle Fee</strong>. Awaiting payment from the peer. Upon payment verification, member will join this circle automatically.
                            </div>
                        </div>

                    @elseif(in_array((string)$record->status, ['circle_member', 'paid'], true))
                        <div class="p-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 text-xs font-medium space-y-1">
                            <div class="font-bold flex items-center gap-1.5">
                                <span>✓</span> Active Circle Member
                            </div>
                            <div>
                                User is an active Circle Member of <strong>{{ $record->circle?->name }}</strong>.
                            </div>
                        </div>

                    @elseif(str_contains((string)$record->status, 'reject') || $record->status === 'cancelled')
                        <div class="p-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-800 text-xs font-medium space-y-1">
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

<!-- Circle Selection Modal Popup (For CD Approval) -->
@if($record->status === \App\Models\CircleJoinRequest::STATUS_PENDING_CD_APPROVAL && $canApprove)
<div id="circleSelectModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 relative border border-gray-200 space-y-4">
        <button type="button" onclick="closeCircleSelectModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl font-bold w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 transition cursor-pointer">&times;</button>

        <div class="border-b bs pb-3">
            <h3 class="font-bold text-base text-gray-900 m-0">Approve Request & Select Circle</h3>
            <p class="text-xs text-indigo-600 font-semibold m-0 mt-0.5">Step 1 of 2: CD Approval</p>
        </div>

        <div class="p-3 rounded-lg border bs bg-indigo-50/50 text-xs text-indigo-900 space-y-1">
            <span class="block text-[11px] uppercase tracking-wider font-semibold text-indigo-600">Applicant & Category</span>
            <div><strong>Applicant:</strong> {{ $record->user?->name ?? '—' }} ({{ $record->user?->email ?? '—' }})</div>
            <div><strong>Requested Category:</strong> {{ $categoryPath['level1']?->name ?? ($record->circleCategory?->name ?? '—') }}</div>
        </div>

        <form method="POST" action="{{ route('admin.circle-joining-requests.approve-cd', $record->id) }}" class="space-y-4">
            @csrf
            <div>
                <label for="modal_select_circle_id" class="block text-xs font-semibold text-gray-700 mb-1">
                    Select Circle to Assign <span class="text-rose-500">*</span>
                </label>
                <select name="circle_id" id="modal_select_circle_id" required class="w-full text-xs font-medium p-2.5 rounded-lg border border-gray-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-white shadow-sm">
                    @if($categoryCircles->isNotEmpty())
                        <optgroup label="Circles in {{ $categoryPath['level1']?->name ?? 'Requested Category' }} (Recommended)">
                            @foreach($categoryCircles as $circle)
                                <option value="{{ $circle->id }}" {{ (string)$record->circle_id === (string)$circle->id ? 'selected' : '' }}>
                                    ★ {{ $circle->name }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endif
                    @if($otherCircles->isNotEmpty())
                        <optgroup label="Other Available Circles">
                            @foreach($otherCircles as $circle)
                                <option value="{{ $circle->id }}" {{ (string)$record->circle_id === (string)$circle->id ? 'selected' : '' }}>
                                    {{ $circle->name }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
                <p class="text-[11px] text-gray-500 mt-1 m-0">The applicant will be assigned to this circle upon CD approval.</p>
            </div>

            <div class="flex justify-end items-center gap-3 pt-3 border-t bs">
                <button type="button" onclick="closeCircleSelectModal()" class="px-4 py-2 text-xs font-semibold rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition cursor-pointer shadow-sm">
                    ✓ Confirm & Approve CD
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<form id="globalRejectForm" method="POST" action="{{ route('admin.circle-joining-requests.reject', $record->id) }}" style="display:none;">
    @csrf
    <input type="hidden" name="reason" id="globalRejectReason">
</form>

<script>
function openCircleSelectModal() {
    const m = document.getElementById('circleSelectModal');
    if (m) m.classList.remove('hidden');
}
function closeCircleSelectModal() {
    const m = document.getElementById('circleSelectModal');
    if (m) m.classList.add('hidden');
}
function submitRejection() {
    const r = prompt('Enter rejection reason (required):');
    if (!r || !r.trim()) return;
    document.getElementById('globalRejectReason').value = r.trim();
    document.getElementById('globalRejectForm').submit();
}
</script>
@endsection
