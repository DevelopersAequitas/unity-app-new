@extends('admin.layouts.app')

@section('title', 'Peers Store — Security & Audit Logs')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.config.index') }}" class="text-decoration-none text-muted">Configuration</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Audit Trail</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-shield-lock-fill text-primary"></i> Administrator Action Audit Trail
            </h1>
        </div>
        <div>
            <a href="{{ route('admin.store.config.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Configuration
            </a>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.store.config.audit-log') }}" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search audit logs..." value="{{ $search }}">
                </div>
                <div class="col-md-3">
                    <select name="admin_id" class="form-select">
                        <option value="">All Administrators</option>
                        @foreach($adminUsers as $admin)
                            <option value="{{ $admin->id }}" {{ (string)$adminFilter === (string)$admin->id ? 'selected' : '' }}>
                                {{ $admin->name }} (ID #{{ $admin->id }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-filter"></i> Filter</button>
                    <a href="{{ route('admin.store.config.audit-log') }}" class="btn btn-light"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Audit Log Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 15%;">Timestamp</th>
                            <th style="width: 15%;">Administrator</th>
                            <th style="width: 20%;">Action Name</th>
                            <th style="width: 35%;">Action Details & Payload</th>
                            <th class="pe-4 text-end" style="width: 15%;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold text-dark">{{ !empty($log->created_at) ? \Carbon\Carbon::parse($log->created_at)->format('d M Y') : '—' }}</div>
                                    <small class="text-muted">{{ !empty($log->created_at) ? \Carbon\Carbon::parse($log->created_at)->format('h:i:s A') : '' }}</small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $log->admin_name ?? ('Admin #' . ($log->actor_id ?? ($log->admin_id ?? ($log->changed_by ?? 'System')))) }}</div>
                                    <small class="text-muted">{{ $log->actor_type ?? 'Admin Action' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        {{ ucfirst(str_replace(['store.', '_'], ['', ' '], $log->to_status ?? ($log->action ?? ($log->status ?? 'Status Change')))) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="text-dark small">{{ $log->notes ?? ($log->note ?? ($log->reason ?? ($log->details ?? ($log->description ?? 'Order status updated')))) }}</div>
                                    <small class="text-muted">Order: <strong>{{ $log->order_number ?? '' }}</strong></small>
                                </td>
                                <td class="pe-4 text-end">
                                    <code class="text-muted small">{{ $log->ip_address ?? '127.0.0.1' }}</code>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-shield-check fs-1 d-block mb-2 text-secondary"></i>
                                    No audit log records found matching this filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($logs->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
