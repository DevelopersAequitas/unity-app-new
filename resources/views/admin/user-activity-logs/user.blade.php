@extends('admin.layouts.app')

@section('title', ($user->display_name ?: $user->first_name.' '.$user->last_name) . ' - User Activity Logs')

@include('admin.partials.grid-head')

@section('content')
<div class="container-fluid px-3 py-3 space-y-4">
    <!-- Breadcrumbs & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 extra-small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.user-activity-logs.index') }}">User Activity & Security</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $user->display_name ?: $user->first_name.' '.$user->last_name }}</li>
                </ol>
            </nav>
            <h2 class="h5 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-primary"></i>
                Activity Timeline: {{ $user->display_name ?: $user->first_name.' '.$user->last_name }}
            </h2>
            <p class="text-muted small mb-0">Complete chronological audit trail and activity forensics for this peer.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.user-activity-logs.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
                <i class="bi bi-arrow-left"></i>
                Back to Peers
            </a>
            <a href="{{ route('admin.user-activity-logs.export', array_merge(request()->query(), ['search' => $user->email ?: $user->id])) }}" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1.5 fw-semibold">
                <i class="bi bi-download"></i>
                Export User CSV
            </a>
            <button type="button" class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1.5 fw-semibold" onclick="confirmDeleteAllUserLogs('{{ $user->id }}', '{{ addslashes($user->display_name ?: ($user->first_name ?: 'this user')) }}')">
                <i class="bi bi-trash"></i>
                Clear All Logs
            </button>
        </div>
    </div>

    <!-- User Profile & Quick Stats Card -->
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
        <div class="row g-3 align-items-center">
            <div class="col-md-5 d-flex align-items-center gap-3">
                @if (!empty($user->profile_photo_url))
                    <img src="{{ $user->profile_photo_url }}" alt="{{ $user->display_name }}" class="rounded-circle" style="width: 54px; height: 54px; object-fit: cover;">
                @else
                    <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center" style="width: 54px; height: 54px; font-size: 20px;">
                        {{ strtoupper(substr($user->display_name ?: ($user->first_name ?: 'U'), 0, 1)) }}
                    </div>
                @endif
                <div>
                    <h5 class="fw-bold text-dark mb-0.5">{{ $user->display_name ?: $user->first_name.' '.$user->last_name }}</h5>
                    <div class="text-muted small">
                        <i class="bi bi-envelope me-1"></i>{{ $user->email ?: 'No email' }}
                        @if ($user->phone)
                            <span class="mx-1">•</span><i class="bi bi-telephone me-1"></i>{{ $user->phone }}
                        @endif
                    </div>
                    @if (!empty($user->company_name) || !empty($user->designation))
                        <div class="extra-small text-secondary mt-0.5">
                            <i class="bi bi-briefcase me-1"></i>{{ $user->designation ?: 'Member' }} @if(!empty($user->company_name)) at {{ $user->company_name }} @endif
                        </div>
                    @endif
                </div>
            </div>
            <div class="col-md-7">
                <div class="row g-2 text-center">
                    <div class="col-3">
                        <div class="p-2 border rounded bg-light">
                            <span class="text-muted extra-small d-block text-uppercase fw-semibold">Total Logs</span>
                            <strong class="fs-5 text-dark">{{ number_format($userStats['total_logs'] ?? 0) }}</strong>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded bg-light">
                            <span class="text-muted extra-small d-block text-uppercase fw-semibold">Today</span>
                            <strong class="fs-5 text-success">{{ number_format($userStats['today_logs'] ?? 0) }}</strong>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded bg-light">
                            <span class="text-muted extra-small d-block text-uppercase fw-semibold">Alerts</span>
                            <strong class="fs-5 text-danger">{{ number_format($userStats['failed_logs'] ?? 0) }}</strong>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded bg-light">
                            <span class="text-muted extra-small d-block text-uppercase fw-semibold">Screens</span>
                            <strong class="fs-5 text-primary">{{ number_format($userStats['screen_views'] ?? 0) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar for this user -->
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
        <form method="GET" action="{{ route('admin.user-activity-logs.user', $user->id) }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label extra-small text-uppercase fw-bold text-muted mb-1">Search within User Activity</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Action, description, IP..." value="{{ $filters['search'] ?? '' }}">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label extra-small text-uppercase fw-bold text-muted mb-1">Activity Type</label>
                <select name="activity_type" class="form-select form-select-sm">
                    <option value="all">All Types</option>
                    @foreach ($options['activity_types'] ?? [] as $t)
                        <option value="{{ $t }}" @selected(($filters['activity_type'] ?? '') === $t)>{{ strtoupper($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label extra-small text-uppercase fw-bold text-muted mb-1">Module</label>
                <select name="module" class="form-select form-select-sm">
                    <option value="all">All Modules</option>
                    @foreach ($options['modules'] ?? [] as $m)
                        <option value="{{ $m }}" @selected(($filters['module'] ?? '') === $m)>{{ strtoupper($m) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label extra-small text-uppercase fw-bold text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>All</option>
                    <option value="success" @selected(($filters['status'] ?? '') === 'success')>Success</option>
                    <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>Failed</option>
                    <option value="warning" @selected(($filters['status'] ?? '') === 'warning')>Warning</option>
                    <option value="blocked" @selected(($filters['status'] ?? '') === 'blocked')>Blocked</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label extra-small text-uppercase fw-bold text-muted mb-1">Date Range</label>
                <div class="d-flex gap-1">
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-sm">
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-sm">
                </div>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
            </div>
        </form>
    </div>

    <!-- User Activities Table -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-sm">
                <thead class="table-light">
                    <tr>
                        <th style="width: 140px;">Timestamp</th>
                        <th style="width: 140px;">Activity Type</th>
                        <th style="width: 150px;">Module & Action</th>
                        <th>Description</th>
                        <th style="width: 140px;">Platform / App</th>
                        <th style="width: 130px;">IP Address</th>
                        <th style="width: 90px;" class="text-center">Status</th>
                        <th style="width: 90px;" class="text-end">Actions</th>
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
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    {{ strtoupper($log->activity_type) }}
                                </span>
                            </td>
                            <td>
                                @if ($log->module)
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle mb-1">
                                        {{ strtoupper($log->module) }}
                                    </span>
                                @endif
                                @if ($log->action)
                                    <div class="font-monospace extra-small text-muted text-truncate" style="max-width: 140px;" title="{{ $log->action }}">
                                        {{ $log->action }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <p class="mb-0 text-dark small text-truncate" style="max-width: 340px;" title="{{ $log->description }}">
                                    {{ $log->description ?: '-' }}
                                </p>
                            </td>
                            <td>
                                @php
                                    $platformLower = strtolower((string) $log->platform);
                                @endphp
                                <div class="d-flex align-items-center gap-1.5">
                                    @if (str_contains($platformLower, 'android'))
                                        <i class="bi bi-android2 text-success"></i>
                                    @elseif (str_contains($platformLower, 'ios') || str_contains($platformLower, 'apple') || str_contains($platformLower, 'mac'))
                                        <i class="bi bi-apple text-dark"></i>
                                    @elseif (str_contains($platformLower, 'web'))
                                        <i class="bi bi-browser-chrome text-primary"></i>
                                    @elseif (str_contains($platformLower, 'win'))
                                        <i class="bi bi-windows text-info"></i>
                                    @else
                                        <i class="bi bi-phone text-secondary"></i>
                                    @endif
                                    <span class="text-capitalize small fw-semibold">{{ $log->platform ?: 'Unknown' }}</span>
                                </div>
                                @if ($log->app_version)
                                    <div class="extra-small text-muted font-monospace">v{{ $log->app_version }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="font-monospace extra-small text-muted">
                                    <i class="bi bi-globe me-1"></i>{{ $log->ip_address ?: '127.0.0.1' }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if ($log->status === 'success')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Success</span>
                                @elseif ($log->status === 'warning')
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Warning</span>
                                @elseif ($log->status === 'blocked')
                                    <span class="badge bg-danger text-white">Blocked</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ ucfirst($log->status) }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" data-bs-toggle="modal" data-bs-target="#userLogDetailModal{{ $loop->index }}" title="Inspect details">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="confirmDeleteSingleLog('{{ $log->id }}')" title="Delete log">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>

                                <!-- Details Modal -->
                                <div class="modal fade text-start" id="userLogDetailModal{{ $loop->index }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header">
                                                <h5 class="modal-title h6 fw-bold d-flex align-items-center gap-2">
                                                    <i class="bi bi-shield-check text-primary"></i>
                                                    Activity Payload Details
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body space-y-3">
                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <span class="text-muted extra-small d-block text-uppercase">Log ID</span>
                                                        <code class="small text-break">{{ $log->id }}</code>
                                                    </div>
                                                    <div class="col-6">
                                                        <span class="text-muted extra-small d-block text-uppercase">Recorded At</span>
                                                        <span class="small font-monospace">{{ $log->created_at?->toIso8601String() }}</span>
                                                    </div>
                                                </div>

                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <span class="text-muted extra-small d-block text-uppercase">Activity Type</span>
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ strtoupper($log->activity_type) }}</span>
                                                    </div>
                                                    <div class="col-6">
                                                        <span class="text-muted extra-small d-block text-uppercase">Status</span>
                                                        <span class="badge bg-light text-dark border">{{ strtoupper($log->status) }}</span>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <span class="text-muted extra-small d-block text-uppercase mb-1">Description</span>
                                                    <p class="p-2.5 bg-light rounded small mb-0">{{ $log->description ?: 'No description provided.' }}</p>
                                                </div>

                                                @if (!empty($log->device_info))
                                                    <div class="mb-3">
                                                        <span class="text-muted extra-small d-block text-uppercase mb-1">Device Information</span>
                                                        <pre class="bg-dark text-info p-3 rounded small font-monospace mb-0" style="max-height: 180px; overflow-y: auto;">{{ json_encode($log->device_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </div>
                                                @endif

                                                @if (!empty($log->properties))
                                                    <div class="mb-3">
                                                        <span class="text-muted extra-small d-block text-uppercase mb-1">Custom Payload & Security Properties</span>
                                                        <pre class="bg-dark text-success p-3 rounded small font-monospace mb-0" style="max-height: 200px; overflow-y: auto;">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </div>
                                                @endif

                                                @if ($log->user_agent)
                                                    <div>
                                                        <span class="text-muted extra-small d-block text-uppercase mb-1">Client User Agent</span>
                                                        <code class="extra-small text-muted text-break">{{ $log->user_agent }}</code>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="modal-footer py-2 d-flex justify-content-between">
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDeleteSingleLog('{{ $log->id }}')">
                                                    <i class="bi bi-trash me-1"></i> Delete Log
                                                </button>
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
                                No activity logs recorded for this peer matching the filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs && $logs->hasPages())
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

<script>
function confirmDeleteSingleLog(logId) {
    if (!confirm('Are you sure you want to permanently delete this user activity log?')) return;
    
    fetch("{{ url('admin/user-activity-logs') }}/" + logId, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data && data.success) {
            alert('Log deleted successfully.');
            location.reload();
        } else {
            alert(data.message || 'Failed to delete log.');
        }
    })
    .catch(() => alert('Network error while deleting log.'));
}

function confirmDeleteAllUserLogs(userId, userName) {
    if (!confirm(`Are you sure you want to permanently delete all activity logs for ${userName || 'this user'}?`)) return;
    
    fetch("{{ url('admin/user-activity-logs/user') }}/" + userId, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data && data.success) {
            alert('All logs for this user deleted successfully.');
            window.location.href = "{{ route('admin.user-activity-logs.index') }}";
        } else {
            alert(data.message || 'Failed to delete user logs.');
        }
    })
    .catch(() => alert('Network error while deleting user logs.'));
}
</script>
@endsection
