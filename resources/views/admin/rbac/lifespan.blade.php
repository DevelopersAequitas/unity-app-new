@extends('admin.layouts.app')
@section('title', 'Role Lifespan & History — RBAC')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Role Lifespan & History Tracker</h4>
    @include('admin.rbac.partials.header_nav')
</div>

{{-- Top Row: Widgets --}}
<div class="row mb-4 g-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-gradient-primary text-white h-100" style="background: linear-gradient(135deg, #4f46e5, #06b6d4);">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-white-50 mb-1 fw-medium text-uppercase small">Active Assignments</h6>
                    <h2 class="mb-0 fw-bold">{{ $totalActiveCount }}</h2>
                </div>
                <div class="rounded p-3 text-white" style="background: rgba(255, 255, 255, 0.25);">
                    <i class="bi bi-shield-check fs-2"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-white-50 mb-1 fw-medium text-uppercase small">Unique Assigned Users</h6>
                    <h2 class="mb-0 fw-bold">{{ $totalUniqueUsers }}</h2>
                </div>
                <div class="rounded p-3 text-white" style="background: rgba(255, 255, 255, 0.25);">
                    <i class="bi bi-people-fill fs-2"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white;">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-white-50 mb-1 fw-medium text-uppercase small">Average Lifespan</h6>
                    <h2 class="mb-0 fw-bold">{{ $avgDurationText }}</h2>
                </div>
                <div class="rounded p-3 text-white" style="background: rgba(255, 255, 255, 0.25);">
                    <i class="bi bi-hourglass-split fs-2"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 bg-white">
            <div class="card-body">
                <h6 class="text-muted mb-2 fw-semibold text-uppercase small">Active Roles Breakdown</h6>
                <div style="max-height: 80px; overflow-y: auto;" class="small">
                    @forelse($breakdown as $role => $count)
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-secondary fw-semibold">{{ $role }}</span>
                            <span class="badge bg-light text-dark fw-bold border">{{ $count }}</span>
                        </div>
                    @empty
                        <span class="text-muted small">No active assignments</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters Card --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.rbac.lifespan.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold text-secondary small">Search User Name / Email</label>
                <div class="input-group input-group-merge">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="{{ $search }}">
                </div>
            </div>
            
            <div class="col-md-4">
                <label class="form-label fw-semibold text-secondary small">Filter by Role</label>
                <select name="role_id" class="form-select">
                    <option value="">All Roles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ $selectedRoleId == $role->id ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Filter Results
                </button>
                <a href="{{ route('admin.rbac.lifespan.index') }}" class="btn btn-light border">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Main Details and Tabs --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        <ul class="nav nav-tabs card-header-tabs" id="lifespanTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" id="active-tab" data-bs-toggle="tab" data-bs-target="#active-pane" type="button" role="tab" aria-controls="active-pane" aria-selected="true">
                    <i class="bi bi-shield-fill-check me-2 text-success"></i>Active Assignments ({{ count($activeAssignments) }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="historical-tab" data-bs-toggle="tab" data-bs-target="#historical-pane" type="button" role="tab" aria-controls="historical-pane" aria-selected="false">
                    <i class="bi bi-calendar-range me-2 text-primary"></i>Historical Lifespans ({{ count($historicalLifespans) }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline-pane" type="button" role="tab" aria-controls="timeline-pane" aria-selected="false">
                    <i class="bi bi-clock-history me-2 text-warning"></i>Audit Timeline Trail
                </button>
            </li>
        </ul>
    </div>
    
    <div class="card-body p-0">
        <div class="tab-content" id="lifespanTabsContent">
            
            {{-- Tab 1: Active Assignments --}}
            <div class="tab-pane fade show active" id="active-pane" role="tabpanel" aria-labelledby="active-tab">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3">User & Info</th>
                                <th class="py-3">Role Details</th>
                                <th class="py-3">Scope / Geography</th>
                                <th class="py-3">Assigned Date & Duration</th>
                                <th class="py-3">Assigned By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activeAssignments as $active)
                                <tr>
                                    <td class="px-4">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar bg-light text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-weight: bold;">
                                                {{ strtoupper(substr($active['user_name'], 0, 2)) }}
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold">{{ $active['user_name'] }}</h6>
                                                <span class="text-muted small">{{ $active['user_email'] }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $keyLower = strtolower($active['role_key']);
                                            $badgeClass = 'bg-secondary';
                                            if (str_contains($keyLower, 'admin')) $badgeClass = 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25';
                                            elseif (str_contains($keyLower, 'ded')) $badgeClass = 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25';
                                            elseif (str_contains($keyLower, 'id')) $badgeClass = 'bg-info bg-opacity-10 text-info border border-info border-opacity-25';
                                            elseif (str_contains($keyLower, 'cd') || str_contains($keyLower, 'chair')) $badgeClass = 'bg-success bg-opacity-10 text-success border border-success border-opacity-25';
                                        @endphp
                                        <span class="badge {{ $badgeClass }} px-2 py-1.5 fw-semibold">
                                            {{ $active['role_name'] }}
                                        </span>
                                        <div class="text-muted small mt-1">Code: <code>{{ $active['role_key'] }}</code></div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($active['scopes'] as $scope)
                                                <span class="badge bg-light text-dark border px-2 py-1 fw-normal">
                                                    <i class="bi bi-geo-alt me-1 text-secondary"></i>{{ $scope }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark small"><i class="bi bi-calendar3 me-1"></i>{{ $active['assigned_at'] }}</div>
                                        <div class="text-success small fw-semibold mt-1">
                                            <i class="bi bi-clock me-1"></i>Active for {{ $active['duration'] }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ $active['assigned_by'] }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-shield-slash fs-1 d-block mb-3 text-light"></i>
                                        No active role assignments found matching criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tab 2: Historical Lifespans --}}
            <div class="tab-pane fade" id="historical-pane" role="tabpanel" aria-labelledby="historical-tab">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3">User & Info</th>
                                <th class="py-3">Role Details</th>
                                <th class="py-3">Assignment Window</th>
                                <th class="py-3">Lifespan Duration</th>
                                <th class="py-3">Actor Trail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($historicalLifespans as $history)
                                <tr>
                                    <td class="px-4">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar bg-light text-secondary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                {{ strtoupper(substr($history['user_name'], 0, 2)) }}
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold">{{ $history['user_name'] }}</h6>
                                                <span class="text-muted small">{{ $history['user_email'] }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1.5 fw-semibold">
                                            {{ $history['role_name'] }}
                                        </span>
                                        <div class="text-muted small mt-1">Code: <code>{{ $history['role_key'] }}</code></div>
                                    </td>
                                    <td>
                                        <div class="small mb-1">
                                            <span class="text-success fw-semibold">Assigned:</span> {{ $history['assigned_at'] }}
                                        </div>
                                        <div class="small">
                                            <span class="text-danger fw-semibold">Removed:</span> {{ $history['removed_at'] }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark fw-bold border">
                                            <i class="bi bi-hourglass-split me-1 text-warning"></i>{{ $history['duration'] }}
                                        </span>
                                        <div class="text-muted small mt-1">{{ $history['reason'] ?? 'Removed / Superceded' }}</div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <div class="mb-1 text-muted">Assigned by: <span class="badge bg-light text-dark border">{{ $history['assigned_by'] }}</span></div>
                                            <div class="text-muted">Removed by: <span class="badge bg-light text-dark border">{{ $history['removed_by'] }}</span></div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-clock fs-1 d-block mb-3 text-light"></i>
                                        No historical role records found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tab 3: Timeline --}}
            <div class="tab-pane fade" id="timeline-pane" role="tabpanel" aria-labelledby="timeline-tab">
                <div class="p-4">
                    <div class="timeline-container ms-3">
                        @forelse($timelineEvents as $event)
                            <div class="timeline-item d-flex mb-4">
                                <div class="timeline-icon-wrap me-3 text-center" style="width: 32px;">
                                    <i class="{{ $event['icon'] }} fs-4"></i>
                                    <div class="timeline-line bg-light mx-auto" style="width: 2px; height: 100%; min-height: 40px;"></div>
                                </div>
                                <div class="timeline-content bg-light p-3 rounded shadow-sm flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="badge bg-white text-dark border">{{ $event['action'] }}</span>
                                        <span class="text-muted small"><i class="bi bi-clock me-1"></i>{{ $event['date'] }}</span>
                                    </div>
                                    <p class="mb-0 text-dark small">{!! $event['description'] !!}</p>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-timeline fs-1 d-block mb-3 text-light"></i>
                                No recent timeline events logged.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
.timeline-item:last-child .timeline-line {
    display: none;
}
.bg-gradient-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
}
.nav-tabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    color: #4b5563;
    padding: 1rem 1.5rem;
}
.nav-tabs .nav-link.active {
    border-color: #3b82f6;
    color: #3b82f6;
    background: transparent;
}
.avatar {
    font-weight: 600;
    font-size: 14px;
}
</style>
@endsection
