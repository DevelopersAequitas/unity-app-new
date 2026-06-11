@extends('admin.layouts.app')

@section('title', $resource['title'])

@section('content')
@php
    $statusClasses = [
        'new' => 'warning text-dark',
        'approved' => 'success',
        'rejected' => 'danger',
    ];

    $statusLabels = [
        'new' => 'Pending / New',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    $formatDate = static fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y h:i A') : '-';
    $formatNumber = static fn ($value) => $value === null || $value === '' ? '-' : rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
@endphp

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
        <div>
            <h1 class="h4 mb-1">{{ $resource['title'] }}</h1>
            <p class="text-muted mb-0">{{ $resource['description'] }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card border-warning"><div class="card-body"><div class="text-muted small">Pending/New Requests</div><div class="h3 mb-0">{{ $summary['pending'] ?? 0 }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-success"><div class="card-body"><div class="text-muted small">Approved Requests</div><div class="h3 mb-0">{{ $summary['approved'] ?? 0 }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-danger"><div class="card-body"><div class="text-muted small">Rejected Requests</div><div class="h3 mb-0">{{ $summary['rejected'] ?? 0 }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-primary"><div class="card-body"><div class="text-muted small">Total Requests</div><div class="h3 mb-0">{{ $summary['total'] ?? 0 }}</div></div></div></div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="Name, business, email, contact, certification">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" @selected($filters['status'] === 'all')>All</option>
                        <option value="new" @selected($filters['status'] === 'new')>Pending / New</option>
                        <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                        <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">From</label>
                    <input type="date" name="from_date" class="form-control" value="{{ $filters['from_date'] }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">To</label>
                    <input type="date" name="to_date" class="form-control" value="{{ $filters['to_date'] }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary w-100" type="submit">Filter</button>
                    <a href="{{ route($resource['index_route']) }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Full Name</th>
                        <th>Business Name</th>
                        <th>Email</th>
                        <th>Contact Number</th>
                        <th>Total Score</th>
                        <th>Percentage</th>
                        <th>{{ $resource['tier_label'] }}</th>
                        <th>Status</th>
                        <th>Submitted At</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($requests as $certificationRequest)
                    @php
                        $status = (string) $certificationRequest->status;
                        $statusClass = $statusClasses[$status] ?? 'secondary';
                        $statusLabel = $statusLabels[$status] ?? ucfirst($status ?: 'Unknown');
                    @endphp
                    <tr>
                        <td>{{ $certificationRequest->full_name ?: '-' }}</td>
                        <td>{{ $certificationRequest->business_name ?: '-' }}</td>
                        <td>{{ $certificationRequest->email ?: '-' }}</td>
                        <td>{{ $certificationRequest->contact_no ?: '-' }}</td>
                        <td>{{ $certificationRequest->total_score ?? '-' }}</td>
                        <td>{{ $formatNumber($certificationRequest->percentage) }}@if($certificationRequest->percentage !== null)%@endif</td>
                        <td>{{ data_get($certificationRequest, $resource['tier_column']) ?: '-' }}</td>
                        <td><span class="badge bg-{{ $statusClass }}">{{ $statusLabel }}</span></td>
                        <td>{{ $formatDate($certificationRequest->created_at) }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="{{ route($resource['show_route'], $certificationRequest->id) }}" class="btn btn-outline-primary">View</a>
                                @if($status === 'new')
                                    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#approve{{ $certificationRequest->id }}">Approve</button>
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reject{{ $certificationRequest->id }}">Reject</button>
                                @endif
                            </div>
                        </td>
                    </tr>

                    @if($status === 'new')
                        <div class="modal fade" id="approve{{ $certificationRequest->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route($resource['approve_route'], $certificationRequest->id) }}">
                                @csrf
                                <div class="modal-header"><h5 class="modal-title">Approve {{ $resource['singular_title'] }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body"><p class="mb-0">Are you sure you want to approve this certification request?</p></div>
                                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Approve</button></div>
                            </form></div>
                        </div>
                        <div class="modal fade" id="reject{{ $certificationRequest->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route($resource['reject_route'], $certificationRequest->id) }}">
                                @csrf
                                <div class="modal-header"><h5 class="modal-title">Reject {{ $resource['singular_title'] }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body"><p class="mb-0">Are you sure you want to reject this certification request?</p></div>
                                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Reject</button></div>
                            </form></div>
                        </div>
                    @endif
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">No certification requests found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $requests->links() }}</div>
    </div>
</div>
@endsection
