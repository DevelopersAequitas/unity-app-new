@extends('admin.layouts.app')

@section('title', 'Circle Joining Requests')

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
@endphp

@section('content')
<div class="container-fluid">
    <div class="card mb-3"><div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="Search peer/email/phone/company" value="{{ $filters['search'] ?? '' }}"></div>
            <div class="col-md-2"><select name="circle_id" class="form-select js-no-searchable-select"><option value="">All Circles</option>@foreach($circles as $circle)<option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? '')===$circle->id)>{{ $circle->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="status" class="form-select js-no-searchable-select"><option value="">All Statuses</option>@foreach(array_keys($statusLabels) as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '')===$status)>{{ $statusLabels[$status] }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="col-md-2"><input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="col-md-1"><button class="btn btn-primary w-100">Apply</button></div>
        </form>
    </div></div>

    <div class="card-activities-wrapper"><div class="table-responsive">
        <table class="table table-premium mb-0 align-middle">
            <thead><tr><th>Submitted At</th><th>Peer</th><th>Circle</th><th>Reason for Joining</th><th>Status</th><th>DED Approval</th><th>Payment</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($requests as $row)
                <tr>
                    <td>{{ optional($row->requested_at)->format('d M Y H:i') }}</td>
                    <td>
                        @include('admin.partials.peer_identity', ['user' => $row->user])
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">{{ $row->circle?->name }}</div>
                        <div class="small text-muted mt-1" style="font-size: 0.75rem;">ID: <span class="user-select-all">{{ $row->circle_id }}</span></div>
                        @if($row->circle?->template)
                            <div class="small text-muted" style="font-size: 0.75rem;">Template: {{ $row->circle->template->name }} ({{ $row->circle->template->slug }})</div>
                        @endif
                        @if($row->circle?->categories && $row->circle->categories->isNotEmpty())
                            <div class="mt-1 d-flex flex-wrap gap-1">
                                @foreach($row->circle->categories as $cat)
                                    <span class="badge bg-light text-secondary border border-secondary-subtle" style="font-size: 0.7rem; padding: 2px 6px;">{{ $cat->name }}</span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td>{{ \Illuminate\Support\Str::limit((string)$row->reason_for_joining, 50) }}</td>
                    <td>
                        <span class="badge text-bg-secondary">{{ $statusLabels[$row->status] ?? $row->status }}</span>
                        @if($row->status === 'rejected_by_cd' && $row->cd_rejection_reason)
                            <div class="small text-danger mt-1">Reason: {{ \Illuminate\Support\Str::limit((string) $row->cd_rejection_reason, 60) }}</div>
                        @elseif($row->status === 'rejected_by_id' && $row->id_rejection_reason)
                            <div class="small text-danger mt-1">Reason: {{ \Illuminate\Support\Str::limit((string) $row->id_rejection_reason, 60) }}</div>
                        @elseif($row->status === 'circle_member')
                            <div class="small text-success mt-1">Payment completed</div>
                        @endif
                    </td>
                    <td>
                        @php($dedApprovalStatus = $row->effectiveDedApprovalStatus())
                        @if($dedApprovalStatus === 'approved')
                            <span class="badge text-bg-success">Approved</span>
                            <div class="small text-success mt-1">Approved{{ $row->dedApprovedBy ? ' by ' . $row->dedApprovedBy->adminDisplayName() : ' by DED' }}</div>
                        @elseif($dedApprovalStatus === 'rejected')
                            <span class="badge text-bg-danger">Rejected</span>
                        @else
                            <span class="badge text-bg-warning">Pending</span>
                        @endif
                    </td>
                    <td>
                        @php($paymentStatus = $row->paymentStatusLabel())
                        <span class="badge {{ $paymentStatus === 'Paid' ? 'text-bg-success' : ($paymentStatus === 'Unpaid' ? 'text-bg-warning' : 'text-bg-secondary') }}">{{ $paymentStatus }}</span>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.circle-joining-requests.show', $row->id) }}" class="btn btn-sm btn-outline-primary">Review</a>

                        @if($row->can_approve_cd)
                            <form method="POST" action="{{ route('admin.circle-joining-requests.approve-cd', $row->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success">Approve</button></form>
                            <form method="POST" action="{{ route('admin.circle-joining-requests.reject-cd', $row->id) }}" class="d-inline" onsubmit="const r = prompt('Enter rejection reason (required):'); if (!r || !r.trim()) { return false; } this.querySelector('input[name=reason]').value = r.trim(); return true;">@csrf<input type="hidden" name="reason"><button class="btn btn-sm btn-outline-danger">Reject</button></form>
                        @endif

                        @if($row->can_approve_id)
                            <form method="POST" action="{{ route('admin.circle-joining-requests.approve-id', $row->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success">Approve</button></form>
                            <form method="POST" action="{{ route('admin.circle-joining-requests.reject-id', $row->id) }}" class="d-inline" onsubmit="const r = prompt('Enter rejection reason (required):'); if (!r || !r.trim()) { return false; } this.querySelector('input[name=reason]').value = r.trim(); return true;">@csrf<input type="hidden" name="reason"><button class="btn btn-sm btn-outline-danger">Reject</button></form>
                        @endif

                        @if($row->can_approve_ded)
                            <form method="POST" action="{{ route('admin.circle-joining-requests.approve-ded', $row->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success">Approve</button></form>
                            <form method="POST" action="{{ route('admin.circle-joining-requests.reject-ded', $row->id) }}" class="d-inline" onsubmit="const r = prompt('Enter rejection remarks (required):'); if (!r || !r.trim()) { return false; } this.querySelector('input[name=remarks]').value = r.trim(); return true;">@csrf<input type="hidden" name="remarks"><button class="btn btn-sm btn-outline-danger">Reject</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted">No requests found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3 border-top">
        {{ $requests->links() }}
    </div>
    </div>
</div>
@include('admin.circle_join_requests.partials.ded_approval_modal')
@endsection
