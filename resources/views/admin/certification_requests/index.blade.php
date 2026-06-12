@extends('admin.layouts.app')

@section('title', $resource['title'])

@push('styles')
    <style>
        .certification-confirm-modal .modal-dialog {
            max-width: 480px;
            width: calc(100% - 2rem);
            margin-left: auto;
            margin-right: auto;
        }

        .certification-confirm-modal .modal-content {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 1.5rem 4rem rgba(15, 23, 42, 0.28);
            overflow: hidden;
        }

        .certification-confirm-modal .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #eef2f7;
            align-items: flex-start;
        }

        .certification-confirm-modal .modal-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.35;
        }

        .certification-confirm-modal .btn-close {
            margin: 0;
            padding: .5rem;
            border-radius: 999px;
        }

        .certification-confirm-modal .modal-body {
            padding: 1.5rem;
            color: #475569;
            line-height: 1.55;
        }

        .certification-confirm-modal .modal-footer {
            padding: 1rem 1.5rem 1.25rem;
            border-top: 0;
            background: #f8fafc;
            gap: .5rem;
        }

        .certification-confirm-modal .modal-footer .btn {
            min-height: 40px;
            border-radius: .55rem;
            padding-left: 1.1rem;
            padding-right: 1.1rem;
            font-weight: 600;
        }

        .modal {
            z-index: 1055;
        }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            width: 100vw;
            height: 100vh;
            background-color: #0f172a;
            z-index: 1050;
        }

        .modal-backdrop.show {
            opacity: .58;
        }
    </style>
@endpush

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
                                @if($status === 'approved' && ! empty($resource['certificate_route']))
                                    <a href="{{ route($resource['certificate_route'], $certificationRequest->id) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">Certificate PDF</a>
                                @endif
                                @if($status === 'new')
                                    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#approve{{ $certificationRequest->id }}">Approve</button>
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reject{{ $certificationRequest->id }}">Reject</button>
                                @endif
                            </div>
                        </td>
                    </tr>

                @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">No certification requests found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $requests->links() }}</div>
    </div>

    @foreach($requests as $certificationRequest)
        @if($certificationRequest->status === 'new')
            <div class="modal fade certification-confirm-modal" id="approve{{ $certificationRequest->id }}" tabindex="-1" aria-labelledby="approve{{ $certificationRequest->id }}Label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" method="POST" action="{{ route($resource['approve_route'], $certificationRequest->id) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="approve{{ $certificationRequest->id }}Label">Approve {{ $resource['singular_title'] }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">Are you sure you want to approve this certification request?</p>
                        </div>
                        <div class="modal-footer justify-content-end">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Approve</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal fade certification-confirm-modal" id="reject{{ $certificationRequest->id }}" tabindex="-1" aria-labelledby="reject{{ $certificationRequest->id }}Label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" method="POST" action="{{ route($resource['reject_route'], $certificationRequest->id) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="reject{{ $certificationRequest->id }}Label">Reject {{ $resource['singular_title'] }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">Are you sure you want to reject this certification request?</p>
                        </div>
                        <div class="modal-footer justify-content-end">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Reject</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endforeach
</div>
@endsection
