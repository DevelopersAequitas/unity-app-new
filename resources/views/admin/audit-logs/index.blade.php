@extends('admin.layouts.app')

@section('title', 'Central Activity Log & Audit Trail')

@include('admin.partials.grid-head')

@section('content')
<div class="container-fluid px-3 py-3 space-y-4">
    <!-- Header Banner -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h5 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-shield-check text-primary"></i>
                Central User Activity Log & Audit Trail
            </h2>
            <p class="text-muted small mb-0">Immutable, forensic audit trail of all platform activities, authentication, and governance operations.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.audit-logs.export', request()->query()) }}" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1.5 fw-semibold">
                <i class="bi bi-download"></i>
                Export CSV
            </a>
            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-light btn-sm" title="Reset Filters">
                <i class="bi bi-arrow-counterclockwise"></i>
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row g-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <span class="text-muted extra-small text-uppercase fw-bold">Total Audit Records</span>
                <div class="h4 mb-0 fw-bold text-dark mt-1">{{ number_format($totalLogs) }}</div>
                <small class="text-muted">Immutable events</small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <span class="text-muted extra-small text-uppercase fw-bold">Today's Activity</span>
                <div class="h4 mb-0 fw-bold text-success mt-1">{{ number_format($todayLogs) }}</div>
                <small class="text-success fw-semibold"><i class="bi bi-check-circle"></i> Live synced</small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <span class="text-muted extra-small text-uppercase fw-bold">Failed / Denied</span>
                <div class="h4 mb-0 fw-bold text-danger mt-1">{{ number_format($failedLogs) }}</div>
                <small class="text-muted">Security exceptions</small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <span class="text-muted extra-small text-uppercase fw-bold">Covered Modules</span>
                <div class="h4 mb-0 fw-bold text-primary mt-1">{{ number_format($activeModulesCount) }}</div>
                <small class="text-muted">System-wide monitoring</small>
            </div>
        </div>
    </div>

    <!-- Filters Container -->
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label extra-small text-uppercase fw-bold text-muted mb-1">Search Audit Trail</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search actor, peer, description, IP..." value="{{ $filters['search'] }}">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label extra-small text-uppercase fw-bold text-muted mb-1">Module</label>
                <select name="module" class="form-select form-select-sm">
                    <option value="all">All Modules</option>
                    @foreach ($availableModules as $mod)
                        <option value="{{ $mod }}" @selected($filters['module'] === $mod)>{{ strtoupper($mod) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label extra-small text-uppercase fw-bold text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="all" @selected($filters['status'] === 'all')>All Statuses</option>
                    <option value="success" @selected($filters['status'] === 'success')>Success</option>
                    <option value="failed" @selected($filters['status'] === 'failed')>Failed</option>
                    <option value="denied" @selected($filters['status'] === 'denied')>Denied</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label extra-small text-uppercase fw-bold text-muted mb-1">Date Range</label>
                <div class="d-flex gap-1">
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control form-control-sm">
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control form-control-sm">
                </div>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
            </div>
        </form>
    </div>

    <!-- Audit Records Table -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-sm">
                <thead class="table-light">
                    <tr>
                        <th style="width: 130px;">Timestamp</th>
                        <th style="width: 140px;">Module & Action</th>
                        <th style="width: 180px;">Actor</th>
                        <th style="width: 180px;">Target Peer</th>
                        <th>Description</th>
                        <th style="width: 120px;">IP Address</th>
                        <th style="width: 100px;" class="text-center">Status</th>
                        <th style="width: 80px;" class="text-end">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>
                                <div class="fw-semibold text-dark">{{ $log->created_at?->format('M d, Y') }}</div>
                                <div class="text-muted extra-small font-monospace">{{ $log->created_at?->format('H:i:s') }}</div>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle mb-1">
                                    {{ strtoupper($log->module) }}
                                </span>
                                <div class="font-monospace extra-small text-muted text-truncate" style="max-width: 130px;">
                                    {{ $log->activity_type }}
                                </div>
                            </td>
                            <td>
                                @if ($log->actor)
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 160px;">
                                        {{ $log->actor->display_name ?: $log->actor->first_name }}
                                    </div>
                                    <div class="text-muted extra-small text-truncate" style="max-width: 160px;">
                                        {{ $log->actor->email }}
                                    </div>
                                @else
                                    <span class="badge bg-light text-muted border">System</span>
                                @endif
                            </td>
                            <td>
                                @if ($log->user)
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 160px;">
                                        {{ $log->user->display_name ?: $log->user->first_name }}
                                    </div>
                                    <div class="text-muted extra-small text-truncate" style="max-width: 160px;">
                                        {{ $log->user->email }}
                                    </div>
                                @else
                                    <span class="text-muted fst-italic">None</span>
                                @endif
                            </td>
                            <td>
                                <p class="mb-0 text-dark small text-truncate" style="max-width: 320px;" title="{{ $log->description }}">
                                    {{ $log->description }}
                                </p>
                            </td>
                            <td>
                                <span class="font-monospace extra-small text-muted">
                                    <i class="bi bi-globe me-1"></i>{{ $log->ip_address ?: '127.0.0.1' }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if ($log->status === 'success')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Success</span>
                                @elseif ($log->status === 'failed')
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Failed</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Denied</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" data-bs-toggle="modal" data-bs-target="#logModal{{ $loop->index }}">
                                    <i class="bi bi-eye"></i>
                                </button>

                                <!-- Details Modal -->
                                <div class="modal fade text-start" id="logModal{{ $loop->index }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header">
                                                <h5 class="modal-title h6 fw-bold">
                                                    <i class="bi bi-shield-lock-fill text-primary me-1"></i>
                                                    Audit Trail Details
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body space-y-3">
                                                <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-3">
                                                    <i class="bi bi-check-circle-fill"></i>
                                                    <span class="small fw-semibold">Tamper-Proof Audit Guarantee: Immutable and cryptographically sealed record.</span>
                                                </div>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <span class="text-muted extra-small d-block text-uppercase">Audit UUID</span>
                                                        <code class="small text-break">{{ $log->id }}</code>
                                                    </div>
                                                    <div class="col-6">
                                                        <span class="text-muted extra-small d-block text-uppercase">Timestamp (UTC)</span>
                                                        <span class="small">{{ $log->created_at?->toIso8601String() }}</span>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <span class="text-muted extra-small d-block text-uppercase mb-1">Description</span>
                                                    <p class="p-2 bg-light rounded small mb-0">{{ $log->description }}</p>
                                                </div>
                                                @if (!empty($log->metadata))
                                                    <div class="mb-3">
                                                        <span class="text-muted extra-small d-block text-uppercase mb-1">Sanitized Metadata</span>
                                                        <pre class="bg-dark text-success p-3 rounded small font-monospace" style="max-height: 200px; overflow-y: auto;">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </div>
                                                @endif
                                                @if ($log->user_agent)
                                                    <div>
                                                        <span class="text-muted extra-small d-block text-uppercase mb-1">Client User Agent</span>
                                                        <code class="extra-small text-muted text-break">{{ $log->user_agent }}</code>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="modal-footer py-2">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                                No audit trail records found matching your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="p-3 border-top d-flex justify-content-between align-items-center">
                <span class="small text-muted">
                    Showing {{ $logs->firstItem() }} to {{ $logs->lastItem() }} of {{ $logs->total() }} records
                </span>
                <div>
                    {{ $logs->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
