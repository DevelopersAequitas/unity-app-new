@extends('admin.layouts.app')

@section('title', 'User Activity & Security Logs')

@include('admin.partials.grid-head')

@section('content')
<div class="container-fluid px-3 py-3 space-y-4">
    <!-- Header Banner -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h5 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-person-badge text-primary"></i>
                User Activity & Security Logs
            </h2>
            <p class="text-muted small mb-0">Real-time mobile app and web activity forensics, user navigation, security alerts, and authentication tracking.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.user-activity-logs.export', request()->query()) }}" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1.5 fw-semibold">
                <i class="bi bi-download"></i>
                Export CSV
            </a>
            <a href="{{ route('admin.user-activity-logs.index') }}" class="btn btn-light btn-sm" title="Reset Filters">
                <i class="bi bi-arrow-counterclockwise"></i>
            </a>
        </div>
    </div>

    @if ($tableMissing)
        <!-- Table Missing Notice with Ready SQL Query -->
        <div class="alert alert-warning border-warning shadow-sm rounded-3 p-3">
            <div class="d-flex align-items-start gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-5 text-warning mt-0.5"></i>
                <div class="flex-grow-1">
                    <h6 class="fw-bold mb-1">Database Table Pending Setup</h6>
                    <p class="small text-muted mb-2">
                        The <code>user_activity_logs</code> table has not been created yet in this database environment. Please execute the following PostgreSQL query in your database client (pgAdmin / DBeaver) to activate live activity logging:
                    </p>
                    <pre class="bg-dark text-light p-3 rounded small font-monospace user-select-all mb-0" style="max-height: 220px; overflow-y: auto;">CREATE TABLE IF NOT EXISTS user_activity_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NULL,
    activity_type VARCHAR(100) NOT NULL,
    action VARCHAR(100) NULL,
    description TEXT NULL,
    module VARCHAR(100) NULL,
    entity_type VARCHAR(100) NULL,
    entity_id VARCHAR(100) NULL,
    status VARCHAR(50) DEFAULT 'success',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    device_info JSONB NULL,
    platform VARCHAR(50) NULL,
    app_version VARCHAR(50) NULL,
    properties JSONB NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_activity_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_user_activity_logs_user_id ON user_activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_user_activity_logs_activity_type ON user_activity_logs(activity_type);
CREATE INDEX IF NOT EXISTS idx_user_activity_logs_module ON user_activity_logs(module);
CREATE INDEX IF NOT EXISTS idx_user_activity_logs_status ON user_activity_logs(status);
CREATE INDEX IF NOT EXISTS idx_user_activity_logs_ip ON user_activity_logs(ip_address);
CREATE INDEX IF NOT EXISTS idx_user_activity_logs_created_at ON user_activity_logs(created_at DESC);</pre>
                </div>
            </div>
        </div>
    @endif

    <!-- Quick Stats Cards -->
    <div class="row g-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                <span class="text-muted extra-small text-uppercase fw-bold">Total Activity Records</span>
                <div class="h4 mb-0 fw-bold text-dark mt-1">{{ number_format($stats['total_logs'] ?? 0) }}</div>
                <small class="text-muted">Platform events recorded</small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                <span class="text-muted extra-small text-uppercase fw-bold">Today's Events</span>
                <div class="h4 mb-0 fw-bold text-success mt-1">{{ number_format($stats['today_logs'] ?? 0) }}</div>
                <small class="text-success fw-semibold"><i class="bi bi-clock-history"></i> Live activity</small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                <span class="text-muted extra-small text-uppercase fw-bold">Security Alerts & Failures</span>
                <div class="h4 mb-0 fw-bold text-danger mt-1">{{ number_format($stats['failed_logs'] ?? 0) }}</div>
                <small class="text-muted">Failed, Warning or Blocked</small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                <span class="text-muted extra-small text-uppercase fw-bold">Mobile App Logs</span>
                <div class="h4 mb-0 fw-bold text-primary mt-1">{{ number_format($stats['mobile_logs'] ?? 0) }}</div>
                <small class="text-muted">Android & iOS devices</small>
            </div>
        </div>
    </div>

    <!-- Filters Container -->
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
        <form method="GET" action="{{ route('admin.user-activity-logs.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label extra-small text-uppercase fw-bold text-muted mb-1">Search Logs</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="User, action, description, IP..." value="{{ $filters['search'] ?? '' }}">
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
            <div class="col-md-1">
                <label class="form-label extra-small text-uppercase fw-bold text-muted mb-1">Platform</label>
                <select name="platform" class="form-select form-select-sm">
                    <option value="all" @selected(($filters['platform'] ?? 'all') === 'all')>All</option>
                    <option value="android" @selected(($filters['platform'] ?? '') === 'android')>Android</option>
                    <option value="ios" @selected(($filters['platform'] ?? '') === 'ios')>iOS</option>
                    <option value="web" @selected(($filters['platform'] ?? '') === 'web')>Web</option>
                    <option value="windows" @selected(($filters['platform'] ?? '') === 'windows')>Windows</option>
                    <option value="macos" @selected(($filters['platform'] ?? '') === 'macos')>macOS</option>
                </select>
            </div>
            <div class="col-md-2">
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

    <!-- Activity Records Table -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-sm">
                <thead class="table-light">
                    <tr>
                        <th style="width: 130px;">Timestamp</th>
                        <th style="width: 180px;">User / Peer</th>
                        <th style="width: 140px;">Activity Type</th>
                        <th style="width: 140px;">Module & Action</th>
                        <th>Description</th>
                        <th style="width: 120px;">Platform / App</th>
                        <th style="width: 120px;">IP Address</th>
                        <th style="width: 90px;" class="text-center">Status</th>
                        <th style="width: 70px;" class="text-end">Details</th>
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
                                @if ($log->user)
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 170px;">
                                        {{ $log->user->display_name ?: $log->user->first_name.' '.$log->user->last_name }}
                                    </div>
                                    <div class="text-muted extra-small text-truncate" style="max-width: 170px;">
                                        {{ $log->user->email ?: ($log->user->phone ?: $log->user->id) }}
                                    </div>
                                @elseif (!empty($log->user_id))
                                    <div class="font-monospace extra-small text-muted text-truncate" style="max-width: 170px;">
                                        {{ $log->user_id }}
                                    </div>
                                @else
                                    <span class="badge bg-light text-muted border">Guest / System</span>
                                @endif
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
                                <p class="mb-0 text-dark small text-truncate" style="max-width: 320px;" title="{{ $log->description }}">
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
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" data-bs-toggle="modal" data-bs-target="#userLogModal{{ $loop->index }}">
                                    <i class="bi bi-eye"></i>
                                </button>

                                <!-- Details Modal -->
                                <div class="modal fade text-start" id="userLogModal{{ $loop->index }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header">
                                                <h5 class="modal-title h6 fw-bold d-flex align-items-center gap-2">
                                                    <i class="bi bi-shield-check text-primary"></i>
                                                    User Activity Details
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
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                                No user activity logs found matching the selected filters.
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
