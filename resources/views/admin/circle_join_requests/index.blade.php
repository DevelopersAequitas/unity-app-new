@extends('admin.layouts.app')

@section('title', 'Circle Joining Requests')

@include('admin.partials.grid-head')

@php
    $statusLabels = [
        'pending_cd_approval' => 'Pending for CD Approval',
        'pending_id_approval' => 'Pending for ID Approval',
        'pending_circle_fee' => 'Pending for Circle Fee',
        'circle_member' => 'Paid',
        'paid' => 'Paid',
        'rejected_by_cd' => 'Rejected by CD',
        'rejected_by_id' => 'Rejected by ID',
        'cancelled' => 'Cancelled',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    $getInitials = function (?string $name): string {
        if (! $name) return 'P';
        $words = explode(' ', trim($name));
        $initials = '';
        foreach ($words as $w) {
            if (! empty($w)) $initials .= strtoupper(substr($w, 0, 1));
        }
        return substr($initials, 0, 2) ?: 'P';
    };

    $getAvatarBg = function (?string $name): string {
        if (! $name) return '#6366f1';
        $colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
        $hash = crc32($name);
        return $colors[abs($hash) % count($colors)];
    };
@endphp

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
    <div class="flex flex-wrap justify-between items-center gap-3">
        <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Circle Joining Requests</h2>
    </div>

    <!-- Filter Card -->
    <div class="p-3 rounded-lg border bs surface-2">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-7 gap-2.5 items-end">
            <div>
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                <input type="text" name="search" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Peer/email/phone" value="{{ $filters['search'] ?? '' }}">
            </div>
            <div>
              
            <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Circle</label>
                <select name="circle_id" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring"><option value="">All Circles</option>@foreach($circles as $circle)<option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? '')===$circle->id)>{{ $circle->name }}</option>@endforeach</select>
            </div>
            <div>
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Category</label>
                <select name="circle_category_id" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring"><option value="">All Categories</option>@foreach($categories as $cat)<option value="{{ $cat->id }}" @selected(($filters['circle_category_id'] ?? '')===(string)$cat->id)>{{ $cat->name }}</option>@endforeach</select>
            </div>
            <div>
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                <select name="status" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring"><option value="">All Statuses</option>@foreach(array_keys($statusLabels) as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '')===$status)>{{ $statusLabels[$status] }}</option>@endforeach</select>
            </div>
            <div>
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">From</label>
                <input type="date" name="date_from" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div>
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">To</label>
                <input type="date" name="date_to" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="flex justify-end">
                <a href="{{ route('admin.circle-joining-requests.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center no-underline">Clear</a>
            </div>
        </form>
    </div>

    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="overflow-x-auto relative">
            <table class="min-w-[1100px] w-full border-collapse text-[13px] align-middle">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs whitespace-nowrap">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left sticky left-0 z-10 whitespace-nowrap" style="min-width:160px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">Peer Name</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left whitespace-nowrap">Company</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left whitespace-nowrap">City</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left whitespace-nowrap">Circle</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left whitespace-nowrap">Category</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left whitespace-nowrap">Reason for Joining</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left whitespace-nowrap">Status</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left whitespace-nowrap">DED Approval</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left whitespace-nowrap">Payment</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-center whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/50">
                    @forelse($requests as $row)
                        @php
                            $peer = $row->user;
                            $peerName = $peer ? ($peer->display_name ?: trim(($peer->first_name ?? '') . ' ' . ($peer->last_name ?? ''))) : '—';
                            $peerCompany = $peer->company_name ?? $peer->company ?? $peer->business_name ?? '—';
                            $peerCity = $peer->city ?? '—';
                            $peerCircles = $peer ? $peer->circleMembers->map(fn($cm) => optional($cm->circle)->name)->filter()->unique()->implode(', ') : '';
                            $peerCircle = $peerCircles !== '' ? $peerCircles : '—';
                            $categoryName = $row->circleCategory ? $row->circleCategory->name : '—';
                            $categoryId = $row->circleCategory ? $row->circleCategory->id : '';
                            $st = strtolower((string)$row->status);
                            $statusLabel = $statusLabels[$row->status] ?? ucfirst(str_replace('_', ' ', $row->status));
                            $dedApprovalStatus = $row->effectiveDedApprovalStatus();
                            $dedApprovedBy = $row->dedApprovedBy ? $row->dedApprovedBy->adminDisplayName() : '';
                            $paymentStatus = $row->paymentStatusLabel();
                            $reasonText = (string)($row->reason_for_joining ?? '');

                            $rowData = [
                                'id' => $row->id,
                                'peerName' => $peerName,
                                'peerId' => $peer?->id,
                                'peerCompany' => $peerCompany,
                                'peerCity' => $peerCity,
                                'peerCircle' => $peerCircle,
                                'category' => $categoryName,
                                'categoryId' => $categoryId,
                                'reason' => $reasonText !== '' ? $reasonText : '—',
                                'status' => $statusLabel,
                                'statusRaw' => $row->status,
                                'dedApproval' => $dedApprovalStatus,
                                'dedApprovedBy' => $dedApprovedBy,
                                'payment' => $paymentStatus,
                                'showUrl' => route('admin.circle-joining-requests.show', $row->id),
                                'canApproveCd' => (bool)$row->can_approve_cd,
                                'approveCdUrl' => route('admin.circle-joining-requests.approve-cd', $row->id),
                                'rejectCdUrl' => route('admin.circle-joining-requests.reject-cd', $row->id),
                                'canApproveId' => (bool)$row->can_approve_id,
                                'approveIdUrl' => route('admin.circle-joining-requests.approve-id', $row->id),
                                'rejectIdUrl' => route('admin.circle-joining-requests.reject-id', $row->id),
                                'canApproveDed' => (bool)$row->can_approve_ded,
                                'approveDedUrl' => route('admin.circle-joining-requests.approve-ded', $row->id),
                                'rejectDedUrl' => route('admin.circle-joining-requests.reject-ded', $row->id),
                            ];
                        @endphp
                        <tr class="hover:surface-2 transition border-b bs cursor-pointer" onclick="openRequestRowModal({{ json_encode($rowData) }})" title="Click row to view full request details">
                            <td class="px-3 py-2.5 text-xs sticky left-0 z-10 surface whitespace-nowrap" style="min-width:160px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.10);">
                                @if ($peer)
                                    <div class="flex items-center gap-2 whitespace-nowrap">
                                        <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($peerName) }}">
                                            {{ $getInitials($peerName) }}
                                        </div>
                                        <span class="text-indigo-600 font-semibold hover:underline no-underline whitespace-nowrap">
                                            {{ $peerName }}
                                        </span>
                                    </div>
                                @else
                                    <span class="t3 whitespace-nowrap">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $peerCompany }}</td>
                            <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $peerCity }}</td>
                            <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $peerCircle }}</td>
                            <td class="px-3 py-2.5 text-xs t2 max-w-[180px]">
                                @if($row->circleCategory)
                                    <div class="font-semibold text-indigo-600 hover:text-indigo-800 text-[12px] truncate" title="{{ $row->circleCategory->name }}">
                                        Category: {{ $row->circleCategory->name }}
                                    </div>
                                    <div class="t3 text-[10px] mt-0.5">ID: {{ $row->circleCategory->id }}</div>
                                @else
                                    <div class="t3">—</div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs t2 max-w-[200px] truncate">
                                @if(!empty($row->reason_for_joining))
                                    <span class="font-medium text-slate-700" title="{{ $row->reason_for_joining }}">
                                        {{ \Illuminate\Support\Str::limit((string)$row->reason_for_joining, 30) }}
                                    </span>
                                @else
                                    <span class="t3">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                @if(str_contains($st, 'approved') || $st === 'circle_member')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>{{ $statusLabel }}
                                    </span>
                                @elseif(str_contains($st, 'rejected'))
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>{{ $statusLabel }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>{{ $statusLabel }}
                                    </span>
                                @endif

                                @if($row->status === 'rejected_by_cd' && $row->cd_rejection_reason)
                                    <div class="t3 text-[10px] text-rose-600 mt-0.5">
                                        Reason: {{ \Illuminate\Support\Str::limit((string) $row->cd_rejection_reason, 40) }}
                                    </div>
                                @elseif($row->status === 'rejected_by_id' && $row->id_rejection_reason)
                                    <div class="t3 text-[10px] text-rose-600 mt-0.5">
                                        Reason: {{ \Illuminate\Support\Str::limit((string) $row->id_rejection_reason, 40) }}
                                    </div>
                                @elseif($row->status === 'circle_member')
                                    <div class="t3 text-[10px] text-emerald-600 mt-0.5">Payment completed</div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                @if($dedApprovalStatus === 'approved')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Approved
                                    </span>
                                    <div class="t3 text-[10px] text-emerald-600 mt-0.5">Approved{{ $row->dedApprovedBy ? ' by ' . $row->dedApprovedBy->adminDisplayName() : ' by DED' }}</div>
                                @elseif($dedApprovalStatus === 'rejected')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Rejected
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Pending
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                @if($paymentStatus === 'Paid')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Paid
                                    </span>
                                @elseif($paymentStatus === 'Unpaid')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-amber-50 text-amber-700 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Unpaid
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-slate-100 text-slate-700 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>{{ $paymentStatus }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs text-center whitespace-nowrap" onclick="event.stopPropagation()">
                                <div class="flex justify-center gap-1.5 items-center whitespace-nowrap">
                                    <a href="{{ route('admin.circle-joining-requests.show', $row->id) }}" class="px-2.5 py-1 text-xs font-semibold rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition no-underline whitespace-nowrap">Review</a>

                                    @if($row->can_approve_cd)
                                        <form method="POST" action="{{ route('admin.circle-joining-requests.approve-cd', $row->id) }}" class="inline">@csrf<button class="px-2.5 py-1 text-xs font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition whitespace-nowrap">Approve</button></form>
                                        <form method="POST" action="{{ route('admin.circle-joining-requests.reject-cd', $row->id) }}" class="inline" onsubmit="const r = prompt('Enter rejection reason (required):'); if (!r || !r.trim()) { return false; } this.querySelector('input[name=reason]').value = r.trim(); return true;">@csrf<input type="hidden" name="reason"><button class="px-2.5 py-1 text-xs font-semibold rounded-md bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition whitespace-nowrap">Reject</button></form>
                                    @endif

                                    @if($row->can_approve_id)
                                        <form method="POST" action="{{ route('admin.circle-joining-requests.approve-id', $row->id) }}" class="inline">@csrf<button class="px-2.5 py-1 text-xs font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition whitespace-nowrap">Approve</button></form>
                                        <form method="POST" action="{{ route('admin.circle-joining-requests.reject-id', $row->id) }}" class="inline" onsubmit="const r = prompt('Enter rejection reason (required):'); if (!r || !r.trim()) { return false; } this.querySelector('input[name=reason]').value = r.trim(); return true;">@csrf<input type="hidden" name="reason"><button class="px-2.5 py-1 text-xs font-semibold rounded-md bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition whitespace-nowrap">Reject</button></form>
                                    @endif

                                    @if($row->can_approve_ded)
                                        <form method="POST" action="{{ route('admin.circle-joining-requests.approve-ded', $row->id) }}" class="inline">@csrf<button class="px-2.5 py-1 text-xs font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition whitespace-nowrap">Approve</button></form>
                                        <form method="POST" action="{{ route('admin.circle-joining-requests.reject-ded', $row->id) }}" class="inline" onsubmit="const r = prompt('Enter rejection remarks (required):'); if (!r || !r.trim()) { return false; } this.querySelector('input[name=remarks]').value = r.trim(); return true;">@csrf<input type="hidden" name="remarks"><button class="px-2.5 py-1 text-xs font-semibold rounded-md bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition whitespace-nowrap">Reject</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center py-8 text-xs t3 whitespace-nowrap">No requests found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
            {{ $requests->links() }}
        </div>
    </div>
</div>

<!-- Row Detail Popup Modal -->
<div id="requestRowDetailModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 relative border border-gray-200 space-y-4 max-h-[90vh] overflow-y-auto">
        <button type="button" onclick="closeRequestRowModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl font-bold w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 transition cursor-pointer">&times;</button>
        
        <div class="border-b bs pb-3">
            <h3 id="modalRowPeerName" class="font-bold text-base text-gray-900 m-0">Circle Joining Request</h3>
            <p class="text-xs text-indigo-600 font-semibold m-0 mt-0.5">Request Details Overview</p>
        </div>

        <div class="grid grid-cols-2 gap-3 text-xs">
            <div class="p-3 rounded-lg border bs bg-gray-50/70">
                <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500 mb-0.5">Company</span>
                <span id="modalRowCompany" class="font-semibold text-gray-900">—</span>
            </div>
            <div class="p-3 rounded-lg border bs bg-gray-50/70">
                <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500 mb-0.5">City</span>
                <span id="modalRowCity" class="font-semibold text-gray-900">—</span>
            </div>
            <div class="p-3 rounded-lg border bs bg-gray-50/70">
                <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500 mb-0.5">Circle</span>
                <span id="modalRowCircle" class="font-semibold text-gray-900">—</span>
            </div>
            <div class="p-3 rounded-lg border bs bg-gray-50/70">
                <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500 mb-0.5">Category</span>
                <span id="modalRowCategory" class="font-semibold text-indigo-600">—</span>
            </div>
        </div>

        <div class="p-3 rounded-lg border bs bg-gray-50/70 space-y-1">
            <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500">Reason for Joining</span>
            <p id="modalRowReason" class="text-xs text-gray-800 leading-relaxed whitespace-pre-wrap break-words m-0">—</p>
        </div>

        <div class="grid grid-cols-3 gap-2.5 text-xs">
            <div class="p-2.5 rounded-lg border bs bg-gray-50/70">
                <span class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Status</span>
                <span id="modalRowStatus" class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
            </div>
            <div class="p-2.5 rounded-lg border bs bg-gray-50/70">
                <span class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-1">DED Approval</span>
                <span id="modalRowDed" class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-gray-100 text-gray-700 border border-gray-200">Pending</span>
            </div>
            <div class="p-2.5 rounded-lg border bs bg-gray-50/70">
                <span class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Payment</span>
                <span id="modalRowPayment" class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-gray-100 text-gray-700 border border-gray-200">Unpaid</span>
            </div>
        </div>

            <div class="pt-3 border-t bs flex justify-between items-center gap-2 flex-wrap">
                <div class="flex items-center gap-2 flex-wrap">
                    <a id="modalRowReviewBtn" href="#" class="px-4 py-2 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition no-underline shadow-sm flex items-center gap-1.5">
                        Open Full Page
                    </a>

                    <!-- Dynamic Approve Form -->
                    <form id="modalApproveForm" method="POST" action="" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-sm cursor-pointer flex items-center gap-1.5">
                            Approve
                        </button>
                    </form>

                    <!-- Dynamic Reject Form -->
                    <form id="modalRejectForm" method="POST" action="" class="inline" onsubmit="const r = prompt('Enter rejection reason:'); if (!r || !r.trim()) { return false; } this.querySelector('input[name=reason_field]').value = r.trim(); return true;">
                        @csrf
                        <input type="hidden" name="reason_field" id="modalRejectReasonInput">
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg border border-rose-300 bg-white text-rose-600 hover:bg-rose-50 transition shadow-sm cursor-pointer flex items-center gap-1.5">
                            Reject
                        </button>
                    </form>
                </div>

                <button type="button" onclick="closeRequestRowModal()" class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold transition cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        function openRequestRowModal(data) {
            document.getElementById('modalRowPeerName').textContent = data.peerName || 'Circle Joining Request';
            document.getElementById('modalRowCompany').textContent = data.peerCompany || '—';
            document.getElementById('modalRowCity').textContent = data.peerCity || '—';
            document.getElementById('modalRowCircle').textContent = data.peerCircle || '—';
            document.getElementById('modalRowCategory').textContent = data.category + (data.categoryId ? ' (ID: ' + data.categoryId + ')' : '');
            document.getElementById('modalRowReason').textContent = data.reason || '—';
            
            // Status Badge
            const statusEl = document.getElementById('modalRowStatus');
            statusEl.textContent = data.status || 'Pending';
            if ((data.statusRaw || '').includes('approved') || data.statusRaw === 'circle_member') {
                statusEl.className = 'inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200';
            } else if ((data.statusRaw || '').includes('rejected')) {
                statusEl.className = 'inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-rose-50 text-rose-700 border border-rose-200';
            } else {
                statusEl.className = 'inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-amber-50 text-amber-700 border border-amber-200';
            }

            // DED Approval
            const dedEl = document.getElementById('modalRowDed');
            dedEl.textContent = data.dedApproval === 'approved' ? 'Approved' : (data.dedApproval === 'rejected' ? 'Rejected' : 'Pending');
            if (data.dedApproval === 'approved') {
                dedEl.className = 'inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200';
            } else if (data.dedApproval === 'rejected') {
                dedEl.className = 'inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-rose-50 text-rose-700 border border-rose-200';
            } else {
                dedEl.className = 'inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-amber-50 text-amber-700 border border-amber-200';
            }

            // Payment
            const payEl = document.getElementById('modalRowPayment');
            payEl.textContent = data.payment || 'Unpaid';
            if (data.payment === 'Paid') {
                payEl.className = 'inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200';
            } else if (data.payment === 'Unpaid') {
                payEl.className = 'inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-amber-50 text-amber-700 border border-amber-200';
            } else {
                payEl.className = 'inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-slate-100 text-slate-700 border border-slate-200';
            }

            // Action Forms (Approve / Reject always displayed next to Open Full Page)
            const approveForm = document.getElementById('modalApproveForm');
            const rejectForm = document.getElementById('modalRejectForm');
            const rejectInput = document.getElementById('modalRejectReasonInput');

            let approveUrl = data.approveCdUrl;
            let rejectUrl = data.rejectCdUrl;
            let rejectField = 'reason';

            if (data.canApproveDed || (data.statusRaw || '').includes('ded')) {
                approveUrl = data.approveDedUrl;
                rejectUrl = data.rejectDedUrl;
                rejectField = 'remarks';
            } else if (data.canApproveId || (data.statusRaw || '').includes('id')) {
                approveUrl = data.approveIdUrl;
                rejectUrl = data.rejectIdUrl;
                rejectField = 'reason';
            }

            approveForm.action = approveUrl || data.approveCdUrl;
            rejectForm.action = rejectUrl || data.rejectCdUrl;
            rejectInput.name = rejectField;
            approveForm.classList.remove('hidden');
            rejectForm.classList.remove('hidden');

            // Full Review Link
            document.getElementById('modalRowReviewBtn').href = data.showUrl || '#';

            document.getElementById('requestRowDetailModal').classList.remove('hidden');
        }

        function closeRequestRowModal() {
            document.getElementById('requestRowDetailModal').classList.add('hidden');
        }
    </script>

    @include('admin.circle_join_requests.partials.ded_approval_modal')
@endsection
