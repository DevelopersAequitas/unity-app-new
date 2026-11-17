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
            <p class="text-muted small mb-0">Track platform user activities, live navigation, authentication, and security forensics by user.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.user-activity-logs.export', request()->query()) }}" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1.5 fw-semibold">
                <i class="bi bi-download"></i>
                Export CSV
            </a>
            <a href="{{ route('admin.user-activity-logs.index', ['view' => $viewMode]) }}" class="btn btn-light btn-sm" title="Reset Filters">
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

    <!-- Navigation Tabs (By User vs Raw Logs) -->
    <div class="d-flex border-bottom mb-2 gap-2">
        <a href="{{ route('admin.user-activity-logs.index', array_merge(request()->query(), ['view' => 'users'])) }}" 
           class="btn btn-sm pb-2 fw-semibold border-0 rounded-0 {{ $viewMode === 'users' ? 'border-bottom border-primary border-3 text-primary' : 'text-muted' }}">
            <i class="bi bi-people me-1"></i> All Peers (Click User to View Activity)
        </a>
        <a href="{{ route('admin.user-activity-logs.index', array_merge(request()->query(), ['view' => 'logs'])) }}" 
           class="btn btn-sm pb-2 fw-semibold border-0 rounded-0 {{ $viewMode === 'logs' ? 'border-bottom border-primary border-3 text-primary' : 'text-muted' }}">
            <i class="bi bi-list-columns-reverse me-1"></i> All Live Logs Stream
        </a>
    </div>

    <!-- Filters Container -->
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
        <form method="GET" action="{{ route('admin.user-activity-logs.index') }}" class="row g-2 align-items-end">
            <input type="hidden" name="view" value="{{ $viewMode }}">
            <div class="col-md-3">
                <label class="form-label extra-small text-uppercase fw-bold text-muted mb-1">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="User name, email, phone, IP..." value="{{ $filters['search'] ?? '' }}">
                </div>
            </div>
            @if ($viewMode === 'logs')
                <div class="col-md-2">
                    <label class="form-label extra-small text-uppercase fw-bold text-muted mb-1">Activity Type</label>
                    <select name="activity_type" class="form-select form-select-sm">
                        <option value="all">All Types</option>
                        @foreach ($options['activity_types'] ?? [] as $t)
                            <option value="{{ $t }}" @selected(($filters['activity_type'] ?? '') === $t)>{{ strtoupper($t) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-2">
                <label class="form-label extra-small text-uppercase fw-bold text-muted mb-1">Module</label>
                <select name="module" class="form-select form-select-sm">
                    <option value="all">All Modules</option>
                    @foreach ($options['modules'] ?? [] as $m)
                        <option value="{{ $m }}" @selected(($filters['module'] ?? '') === $m)>{{ strtoupper($m) }}</option>
                    @endforeach
                </select>
            </div>
            @if ($viewMode === 'logs')
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
            @endif
            <div class="col-md-2">
                <label class="form-label extra-small text-uppercase fw-bold text-muted mb-1">Platform</label>
                <select name="platform" class="form-select form-select-sm">
                    <option value="all" @selected(($filters['platform'] ?? 'all') === 'all')>All Platforms</option>
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

    @if ($viewMode === 'users')
        <!-- ======================================================== -->
        <!-- VIEW 1: USERS DIRECTORY (CLICK USER TO SHOW ALL ACTIVITY) -->
        <!-- ======================================================== -->
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-people-fill text-primary"></i>
                    All Peers &amp; User Activity
                </h6>
                <span class="badge bg-light text-muted border">Latest active users appear at top &bull; Click any peer to open their timeline</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-sm">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 250px;">Peer / User</th>
                            <th style="width: 140px;" class="text-center">Total Activities</th>
                            <th style="width: 130px;" class="text-center">Today</th>
                            <th style="width: 200px;">Last Active At</th>
                            <th>Latest Action & Module</th>
                            <th style="width: 150px;">Latest Device</th>
                            <th style="width: 160px;" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $u)
                            @php
                                $userName = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
                                if ($userName === '') {
                                    $userName = 'Peer User';
                                }
                                $latestLog = $u->latest_log ?? null;
                            @endphp
                            <tr style="cursor: pointer;" onclick="openUserDrawer('{{ $u->user_id }}', '{{ addslashes($userName) }}', '{{ addslashes($u->email ?? '') }}')">
                                <td>
                                    <div class="d-flex align-items-center gap-2.5">
                                        @if (!empty($u->profile_photo_url))
                                            <img src="{{ $u->profile_photo_url }}" alt="{{ $userName }}" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                        @else
                                            <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-size: 15px;">
                                                {{ strtoupper(substr($userName, 0, 1)) }}
                                            </div>
                                        @endif
                                        <div class="overflow-hidden">
                                            <div class="d-flex align-items-center gap-1">
                                                <a href="{{ route('admin.user-activity-logs.user', $u->user_id) }}" class="fw-bold text-dark text-decoration-none text-truncate" style="max-width: 180px;" onclick="event.stopPropagation();">
                                                    {{ $userName }}
                                                </a>
                                                @if ($loop->first && $u->last_active_at)
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle extra-small flex-shrink-0" style="font-size: 10px;">
                                                        <i class="bi bi-broadcast me-0.5"></i>LATEST ACTIVE
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-muted extra-small text-truncate" style="max-width: 180px;">
                                                {{ $u->email ?: ($u->phone ?: 'No email') }}
                                            </div>
                                            @if (!empty($u->company_name))
                                                <div class="extra-small text-secondary text-truncate" style="max-width: 180px;">
                                                    <i class="bi bi-briefcase me-1"></i>{{ $u->company_name }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if ((int) $u->total_activities > 0)
                                        <span class="badge bg-primary px-2.5 py-1.5 fs-7 rounded-pill">
                                            {{ number_format((int) $u->total_activities) }} logs
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted border px-2.5 py-1.5 fs-7 rounded-pill">
                                            0 logs
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ((int) $u->today_activities > 0)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                                            <i class="bi bi-lightning-fill"></i> {{ number_format((int) $u->today_activities) }} today
                                        </span>
                                    @else
                                        <span class="text-muted extra-small">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($u->last_active_at)
                                        @php
                                            $dt = \Carbon\Carbon::parse($u->last_active_at);
                                        @endphp
                                        <div class="fw-semibold text-dark">{{ $dt->format('M d, Y') }}</div>
                                        <div class="text-muted extra-small font-monospace">{{ $dt->format('H:i:s') }} ({{ $dt->diffForHumans() }})</div>
                                    @else
                                        <span class="text-muted extra-small">No activity yet</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($latestLog)
                                        @if ($latestLog->module)
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle mb-1">
                                                {{ strtoupper($latestLog->module) }}
                                            </span>
                                        @endif
                                        @if ($latestLog->activity_type)
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle mb-1">
                                                {{ strtoupper($latestLog->activity_type) }}
                                            </span>
                                        @endif
                                        <div class="small text-dark text-truncate" style="max-width: 280px;" title="{{ $latestLog->description ?: $latestLog->action }}">
                                            {{ $latestLog->description ?: $latestLog->action }}
                                        </div>
                                    @else
                                        <span class="text-muted extra-small">No activity recorded</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($latestLog)
                                        @php
                                            $platformLower = strtolower((string) $latestLog->platform);
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
                                            <span class="text-capitalize small fw-semibold">{{ $latestLog->platform ?: 'Device' }}</span>
                                            @if ($latestLog->app_version)
                                                <span class="extra-small text-muted font-monospace">v{{ $latestLog->app_version }}</span>
                                            @endif
                                        </div>
                                        <div class="font-monospace extra-small text-muted">
                                            <i class="bi bi-globe me-0.5"></i>{{ $latestLog->ip_address ?: '127.0.0.1' }}
                                        </div>
                                    @else
                                        <span class="text-muted extra-small">-</span>
                                    @endif
                                </td>
                                <td class="text-end" onclick="event.stopPropagation();">
                                    <div class="d-flex align-items-center justify-content-end gap-1.5">
                                        <button type="button" class="btn btn-primary btn-sm px-2.5 py-1 d-inline-flex align-items-center gap-1 shadow-sm" 
                                                onclick="openUserDrawer('{{ $u->user_id }}', '{{ addslashes($userName) }}', '{{ addslashes($u->email ?? '') }}')">
                                            <i class="bi bi-clock-history"></i>
                                            <span>Show Activity</span>
                                        </button>
                                        <a href="{{ route('admin.user-activity-logs.user', $u->user_id) }}" class="btn btn-outline-secondary btn-sm p-1" title="Open Full Page Activity">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-2 d-block mb-2 text-muted"></i>
                                    No peers found with recorded activities.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users && $users->hasPages())
                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <span class="small text-muted">
                        Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} peers
                    </span>
                    <div>
                        {{ $users->links() }}
                    </div>
                </div>
            @endif
        </div>

    @else
        <!-- ======================================================== -->
        <!-- VIEW 2: ALL RAW LOGS STREAM                              -->
        <!-- ======================================================== -->
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
                                            <a href="{{ route('admin.user-activity-logs.user', $log->user->id) }}" class="text-decoration-none text-dark fw-bold">
                                                {{ $log->user->display_name ?: $log->user->first_name.' '.$log->user->last_name }}
                                            </a>
                                        </div>
                                        <div class="text-muted extra-small text-truncate" style="max-width: 170px;">
                                            {{ $log->user->email ?: ($log->user->phone ?: $log->user->id) }}
                                        </div>
                                    @elseif (!empty($log->user_id))
                                        <a href="{{ route('admin.user-activity-logs.user', $log->user_id) }}" class="font-monospace extra-small text-muted text-truncate d-block" style="max-width: 170px;">
                                            {{ $log->user_id }}
                                        </a>
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
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" data-bs-toggle="modal" data-bs-target="#rawLogModal{{ $loop->index }}">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <!-- Details Modal -->
                                    <div class="modal fade text-start" id="rawLogModal{{ $loop->index }}" tabindex="-1" aria-hidden="true">
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
    @endif
</div>

<!-- ======================================================== -->
<!-- USER ACTIVITY SLIDE-OVER DRAWER (OFFCANVAS)              -->
<!-- ======================================================== -->
<div class="offcanvas offcanvas-end shadow-lg border-0" tabindex="-1" id="userActivityDrawer" aria-labelledby="userActivityDrawerLabel" style="width: 720px; max-width: 95vw; z-index: 1060;">
    <div class="offcanvas-header bg-dark text-white p-3">
        <div class="d-flex align-items-center gap-2.5 overflow-hidden">
            <div id="drawerAvatar" class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px; font-size: 16px;">
                U
            </div>
            <div class="overflow-hidden">
                <h5 class="offcanvas-title fw-bold text-white mb-0 h6 text-truncate" id="drawerUserName">User Activity</h5>
                <span id="drawerUserEmail" class="text-light-50 extra-small font-monospace text-truncate d-block">loading...</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="#" id="drawerFullPageBtn" class="btn btn-outline-light btn-sm px-2 py-1" title="Open Full Page View">
                <i class="bi bi-box-arrow-up-right me-1"></i> Full Page
            </a>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
    </div>
    <div class="offcanvas-body p-0 bg-light d-flex flex-column">
        <!-- Quick User Stats -->
        <div class="p-3 bg-white border-bottom">
            <div class="row g-2 text-center" id="drawerStatsRow">
                <div class="col-3">
                    <div class="p-2 border rounded bg-light">
                        <span class="text-muted extra-small d-block text-uppercase">Total</span>
                        <strong class="fs-6 text-dark" id="drawerStatTotal">0</strong>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 border rounded bg-light">
                        <span class="text-muted extra-small d-block text-uppercase">Today</span>
                        <strong class="fs-6 text-success" id="drawerStatToday">0</strong>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 border rounded bg-light">
                        <span class="text-muted extra-small d-block text-uppercase">Alerts</span>
                        <strong class="fs-6 text-danger" id="drawerStatFailed">0</strong>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 border rounded bg-light">
                        <span class="text-muted extra-small d-block text-uppercase">Screens</span>
                        <strong class="fs-6 text-primary" id="drawerStatScreens">0</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Timeline / Stream in Drawer -->
        <div class="flex-grow-1 p-3 overflow-auto" id="drawerLogsContainer">
            <div class="text-center py-5 text-muted" id="drawerLoadingSpinner">
                <div class="spinner-border text-primary spinner-border-sm mb-2" role="status"></div>
                <p class="small mb-0">Loading user activity timeline...</p>
            </div>
            <div id="drawerLogsList" class="space-y-2"></div>
        </div>
    </div>
</div>

<script>
function openUserDrawer(userId, userName, userEmail) {
    const drawerEl = document.getElementById('userActivityDrawer');
    if (!drawerEl) return;

    const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
    
    // Set Header
    document.getElementById('drawerUserName').innerText = userName || 'Peer User';
    document.getElementById('drawerUserEmail').innerText = userEmail || userId;
    document.getElementById('drawerAvatar').innerText = (userName ? userName.charAt(0) : 'U').toUpperCase();
    document.getElementById('drawerFullPageBtn').href = "{{ url('admin/user-activity-logs/user') }}/" + userId;

    // Reset view to loading
    document.getElementById('drawerLoadingSpinner').style.display = 'block';
    document.getElementById('drawerLogsList').innerHTML = '';
    document.getElementById('drawerStatTotal').innerText = '...';
    document.getElementById('drawerStatToday').innerText = '...';
    document.getElementById('drawerStatFailed').innerText = '...';
    document.getElementById('drawerStatScreens').innerText = '...';

    bsOffcanvas.show();

    // Fetch user activity via AJAX
    fetch("{{ url('admin/user-activity-logs/user') }}/" + userId, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('drawerLoadingSpinner').style.display = 'none';

        if (!data || !data.success) {
            document.getElementById('drawerLogsList').innerHTML = '<div class="alert alert-warning small p-2">Failed to load activity logs for this user.</div>';
            return;
        }

        // Update stats
        if (data.stats) {
            document.getElementById('drawerStatTotal').innerText = data.stats.total_logs || 0;
            document.getElementById('drawerStatToday').innerText = data.stats.today_logs || 0;
            document.getElementById('drawerStatFailed').innerText = data.stats.failed_logs || 0;
            document.getElementById('drawerStatScreens').innerText = data.stats.screen_views || 0;
        }

        const logs = data.logs || [];
        if (logs.length === 0) {
            document.getElementById('drawerLogsList').innerHTML = '<div class="text-center py-4 text-muted small"><i class="bi bi-inbox fs-3 d-block mb-1"></i>No activity logs found for this user.</div>';
            return;
        }

        let html = '';
        logs.forEach((log, index) => {
            const dateStr = log.created_at ? new Date(log.created_at).toLocaleString() : '-';
            const statusClass = log.status === 'success' ? 'bg-success-subtle text-success border-success-subtle' : (log.status === 'warning' ? 'bg-warning-subtle text-warning border-warning-subtle' : 'bg-danger-subtle text-danger border-danger-subtle');
            
            html += `
            <div class="card border-0 shadow-sm rounded-3 mb-2.5 bg-white">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-1.5">
                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                            ${index === 0 ? '<span class="badge bg-success text-white extra-small fw-bold"><i class="bi bi-broadcast me-1"></i>LATEST</span>' : ''}
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle extra-small text-uppercase fw-bold">${log.activity_type || 'ACTIVITY'}</span>
                            ${log.module ? `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle extra-small">${log.module.toUpperCase()}</span>` : ''}
                            <span class="badge ${statusClass} border extra-small text-uppercase">${log.status || 'success'}</span>
                        </div>
                        <span class="extra-small text-muted font-monospace">${dateStr}</span>
                    </div>

                    <div class="fw-semibold text-dark small mb-1">${escapeHtml(log.description || log.action || 'Activity recorded')}</div>
                    
                    ${log.action && log.action !== log.description ? `<div class="extra-small font-monospace text-muted mb-1.5"><i class="bi bi-arrow-right-short"></i>${escapeHtml(log.action)}</div>` : ''}

                    <div class="d-flex align-items-center justify-content-between text-muted extra-small pt-1.5 border-top">
                        <div>
                            <span class="me-2"><i class="bi bi-phone me-1"></i>${log.platform || 'Unknown'} ${log.app_version ? 'v' + log.app_version : ''}</span>
                            <span><i class="bi bi-globe me-1"></i>${log.ip_address || '127.0.0.1'}</span>
                        </div>
                    </div>
                </div>
            </div>`;
        });

        document.getElementById('drawerLogsList').innerHTML = html;
    })
    .catch(err => {
        document.getElementById('drawerLoadingSpinner').style.display = 'none';
        document.getElementById('drawerLogsList').innerHTML = '<div class="alert alert-danger small p-2">Error loading user activities.</div>';
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>
@endsection
