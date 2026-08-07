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
            <table class="min-w-[1100px] w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left sticky left-0 z-10" style="min-width:160px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">Peer Name</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Circle</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Category</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Reason for Joining</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">DED Approval</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Payment</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Actions</th>
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
                        @endphp
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-3 py-2.5 text-xs sticky left-0 z-10 surface" style="min-width:160px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.10);">
                                @if ($peer)
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($peerName) }}">
                                            {{ $getInitials($peerName) }}
                                        </div>
                                        <a href="#" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $peer->id }}', event);" class="text-indigo-600 font-semibold hover:underline no-underline">
                                            {{ $peerName }}
                                        </a>
                                    </div>
                                @else
                                    <span class="t3">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs t2">{{ $peerCompany }}</td>
                            <td class="px-3 py-2.5 text-xs t2">{{ $peerCity }}</td>
                            <td class="px-3 py-2.5 text-xs t2">{{ $peerCircle }}</td>
                            <td class="px-3 py-2.5 text-xs t2 max-w-[180px]">
                                @if($row->circleCategory)
                                    <div class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline cursor-pointer text-[12px] truncate" 
                                         onclick="openCategoryModal('Category Details', 'Category: {{ addslashes($row->circleCategory->name) }}\nID: {{ $row->circleCategory->id }}')" 
                                         title="{{ $row->circleCategory->name }} (Click to view details)">
                                        Category: {{ $row->circleCategory->name }}
                                    </div>
                                    <div class="t3 text-[10px] mt-0.5">ID: {{ $row->circleCategory->id }}</div>
                                @else
                                    <div class="t3">—</div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs t2 max-w-[200px] truncate">
                                @if(!empty($row->reason_for_joining))
                                    <span class="cursor-pointer hover:text-indigo-600 hover:underline transition font-medium" 
                                          onclick="openCategoryModal('Reason for Joining', '{{ addslashes($row->reason_for_joining) }}')"
                                          title="{{ $row->reason_for_joining }} (Click to view full message)">
                                        {{ \Illuminate\Support\Str::limit((string)$row->reason_for_joining, 30) }}
                                    </span>
                                @else
                                    <span class="t3">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-md whitespace-nowrap bg-gray-100 text-gray-700 border border-gray-200">{{ $statusLabels[$row->status] ?? $row->status }}</span>
                                @if($row->status === 'rejected_by_cd' && $row->cd_rejection_reason)
                                    <div class="t3 text-[10px] text-rose-600 mt-0.5 cursor-pointer hover:underline" 
                                         onclick="openCategoryModal('Rejection Reason (CD)', '{{ addslashes($row->cd_rejection_reason) }}')"
                                         title="{{ $row->cd_rejection_reason }} (Click to view full message)">
                                        Reason: {{ \Illuminate\Support\Str::limit((string) $row->cd_rejection_reason, 40) }}
                                    </div>
                                @elseif($row->status === 'rejected_by_id' && $row->id_rejection_reason)
                                    <div class="t3 text-[10px] text-rose-600 mt-0.5 cursor-pointer hover:underline" 
                                         onclick="openCategoryModal('Rejection Reason (ID)', '{{ addslashes($row->id_rejection_reason) }}')"
                                         title="{{ $row->id_rejection_reason }} (Click to view full message)">
                                        Reason: {{ \Illuminate\Support\Str::limit((string) $row->id_rejection_reason, 40) }}
                                    </div>
                                @elseif($row->status === 'circle_member')
                                    <div class="t3 text-[10px] text-emerald-600 mt-0.5">Payment completed</div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                @php($dedApprovalStatus = $row->effectiveDedApprovalStatus())
                                @if($dedApprovalStatus === 'approved')
                                    <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-md whitespace-nowrap bg-emerald-50 text-emerald-700 border border-emerald-200">Approved</span>
                                    <div class="t3 text-[10px] text-emerald-600 mt-0.5">Approved{{ $row->dedApprovedBy ? ' by ' . $row->dedApprovedBy->adminDisplayName() : ' by DED' }}</div>
                                @elseif($dedApprovalStatus === 'rejected')
                                    <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-md whitespace-nowrap bg-rose-50 text-rose-700 border border-rose-200">Rejected</span>
                                @else
                                    <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-md whitespace-nowrap bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                @php($paymentStatus = $row->paymentStatusLabel())
                                @if($paymentStatus === 'Paid')
                                    <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-md whitespace-nowrap bg-emerald-50 text-emerald-700 border border-emerald-200">Paid</span>
                                @elseif($paymentStatus === 'Unpaid')
                                    <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-md whitespace-nowrap bg-amber-50 text-amber-700 border border-amber-200">Unpaid</span>
                                @else
                                    <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-md whitespace-nowrap bg-gray-100 text-gray-700 border border-gray-200">{{ $paymentStatus }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs text-center whitespace-nowrap">
                                <div class="flex justify-center gap-1.5 items-center">
                                    <a href="{{ route('admin.circle-joining-requests.show', $row->id) }}" class="px-2.5 py-1 text-xs font-semibold rounded-md border bs t2 hover:t1 hover:surface-2 transition no-underline">Review</a>

                                    @if($row->can_approve_cd)
                                        <form method="POST" action="{{ route('admin.circle-joining-requests.approve-cd', $row->id) }}" class="inline">@csrf<button class="px-2.5 py-1 text-xs font-semibold rounded-md bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring">Approve</button></form>
                                        <form method="POST" action="{{ route('admin.circle-joining-requests.reject-cd', $row->id) }}" class="inline" onsubmit="const r = prompt('Enter rejection reason (required):'); if (!r || !r.trim()) { return false; } this.querySelector('input[name=reason]').value = r.trim(); return true;">@csrf<input type="hidden" name="reason"><button class="px-2.5 py-1 text-xs font-semibold rounded-md border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring">Reject</button></form>
                                    @endif

                                    @if($row->can_approve_id)
                                        <form method="POST" action="{{ route('admin.circle-joining-requests.approve-id', $row->id) }}" class="inline">@csrf<button class="px-2.5 py-1 text-xs font-semibold rounded-md bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring">Approve</button></form>
                                        <form method="POST" action="{{ route('admin.circle-joining-requests.reject-id', $row->id) }}" class="inline" onsubmit="const r = prompt('Enter rejection reason (required):'); if (!r || !r.trim()) { return false; } this.querySelector('input[name=reason]').value = r.trim(); return true;">@csrf<input type="hidden" name="reason"><button class="px-2.5 py-1 text-xs font-semibold rounded-md border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring">Reject</button></form>
                                    @endif

                                    @if($row->can_approve_ded)
                                        <form method="POST" action="{{ route('admin.circle-joining-requests.approve-ded', $row->id) }}" class="inline">@csrf<button class="px-2.5 py-1 text-xs font-semibold rounded-md bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring">Approve</button></form>
                                        <form method="POST" action="{{ route('admin.circle-joining-requests.reject-ded', $row->id) }}" class="inline" onsubmit="const r = prompt('Enter rejection remarks (required):'); if (!r || !r.trim()) { return false; } this.querySelector('input[name=remarks]').value = r.trim(); return true;">@csrf<input type="hidden" name="remarks"><button class="px-2.5 py-1 text-xs font-semibold rounded-md border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring">Reject</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center py-8 text-xs t3">No requests found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
            {{ $requests->links() }}
        </div>
    </div>
</div>

<!-- Category / Detail Modal -->
<div id="categoryDetailModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-5 relative border border-gray-200">
        <button type="button" onclick="closeCategoryModal()" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 text-lg font-bold w-7 h-7 rounded-full flex items-center justify-center hover:bg-gray-100 transition">&times;</button>
        <h3 id="categoryModalTitle" class="font-display font-semibold text-sm text-indigo-600 uppercase tracking-wider mb-3">Category Details</h3>
        <div id="categoryModalBody" class="text-xs text-gray-800 leading-relaxed whitespace-pre-wrap break-words p-3 bg-gray-50 rounded-lg border border-gray-200/80"></div>
        <div class="flex justify-end mt-4">
            <button type="button" onclick="closeCategoryModal()" class="px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition">Close</button>
        </div>
    </div>
</div>

<script>
    function openCategoryModal(title, text) {
        document.getElementById('categoryModalTitle').textContent = title;
        document.getElementById('categoryModalBody').textContent = text;
        document.getElementById('categoryDetailModal').classList.remove('hidden');
    }

    function closeCategoryModal() {
        document.getElementById('categoryDetailModal').classList.add('hidden');
    }
</script>

@include('admin.circle_join_requests.partials.ded_approval_modal')
@endsection

