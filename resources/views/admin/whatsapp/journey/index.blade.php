@extends('admin.layouts.app')

@section('title', '30-Day WhatsApp Onboarding Journey - Peers Global Unity')

@section('content')
<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-calendar-check text-primary me-2"></i>30-Day WhatsApp Member Onboarding Journey
            </h1>
            <p class="text-muted small mb-0">Automated daily progressive WhatsApp delivery (Days 1–30) powered by Meta Cloud API with smart profile skipping.</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <span class="badge bg-success-subtle text-success border border-success-subtle py-2 px-3 small d-inline-flex align-items-center">
                <span class="spinner-grow spinner-grow-sm me-2 text-success" role="status" style="width: 8px; height: 8px;"></span>
                30 Meta Templates (en_IN)
            </span>
            <form action="{{ route('admin.whatsapp.journey.process_due') }}" method="POST" class="d-inline" onsubmit="return confirm('Process and dispatch all messages due right now?');">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm shadow-sm">
                    <i class="bi bi-send-check me-1"></i>Process Due Sends
                </button>
            </form>
            <form action="{{ route('admin.whatsapp.journey.initialize_all') }}" method="POST" class="d-inline" onsubmit="return confirm('Initialize 30-day journey schedule for all existing peers?');">
                @csrf
                <button type="submit" class="btn btn-outline-success btn-sm" title="Bulk schedule 30-day journey for all peers">
                    <i class="bi bi-magic me-1"></i>Initialize All Existing Peers
                </button>
            </form>
            <form action="{{ route('admin.whatsapp.journey.sync_statuses') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-success btn-sm" title="Sync live delivery and read receipts from Meta">
                    <i class="bi bi-arrow-repeat me-1"></i>Sync Status
                </button>
            </form>
            <a href="{{ route('admin.whatsapp.templates') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-file-earmark-text me-1"></i>Templates
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()" title="Refresh Page">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <!-- Alert Flash Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <style>
        .journey-metric-card {
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            cursor: pointer;
            text-decoration: none !important;
            color: inherit;
        }
        .journey-metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.12)!important;
        }
    </style>

    <!-- Metrics Cards (Clickable quick filters) -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.journey.index', array_merge(request()->except('status', 'page'), [])) }}" class="card shadow-sm border-0 border-start border-primary border-4 h-100 journey-metric-card {{ $status === '' ? 'ring-2 bg-primary-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-semibold">Total Peers</span>
                        <i class="bi bi-people text-primary"></i>
                    </div>
                    <div class="h4 mb-0 fw-bold text-gray-800">{{ number_format($stats['total_peers']) }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.journey.index', array_merge(request()->except('status', 'page'), ['status' => 'in_journey'])) }}" class="card shadow-sm border-0 border-start border-info border-4 h-100 journey-metric-card {{ $status === 'in_journey' ? 'ring-2 bg-info-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-semibold">In Journey</span>
                        <i class="bi bi-calendar3 text-info"></i>
                    </div>
                    <div class="h4 mb-0 fw-bold text-info">{{ number_format($stats['total_in_journey']) }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.journey.index', array_merge(request()->except('status', 'page'), ['status' => 'completed'])) }}" class="card shadow-sm border-0 border-start border-success border-4 h-100 journey-metric-card {{ $status === 'completed' ? 'ring-2 bg-success-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-semibold">Messages Sent</span>
                        <i class="bi bi-check2-all text-success"></i>
                    </div>
                    <div class="h4 mb-0 fw-bold text-success">{{ number_format($stats['total_sent']) }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.journey.index', array_merge(request()->except('status', 'page'), ['status' => 'in_progress'])) }}" class="card shadow-sm border-0 border-start border-secondary border-4 h-100 journey-metric-card">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-semibold" title="Days 1, 2, or 3 skipped because member already completed their profile">
                            Smart Skipped <i class="bi bi-info-circle text-muted"></i>
                        </span>
                        <i class="bi bi-forward-fill text-secondary"></i>
                    </div>
                    <div class="h4 mb-0 fw-bold text-secondary">{{ number_format($stats['total_skipped']) }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.journey.index', array_merge(request()->except('status', 'page'), ['status' => 'in_progress'])) }}" class="card shadow-sm border-0 border-start border-warning border-4 h-100 journey-metric-card">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-semibold">Due Dispatch</span>
                        <i class="bi bi-clock-history text-warning"></i>
                    </div>
                    <div class="h4 mb-0 fw-bold text-warning">{{ number_format($stats['total_due_today']) }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.journey.index', array_merge(request()->except('status', 'page'), ['status' => 'failed'])) }}" class="card shadow-sm border-0 border-start border-danger border-4 h-100 journey-metric-card {{ $status === 'failed' ? 'ring-2 bg-danger-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-semibold">Failed</span>
                        <i class="bi bi-exclamation-octagon text-danger"></i>
                    </div>
                    <div class="h4 mb-0 fw-bold text-danger">{{ number_format($stats['total_failed']) }}</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Filters & Quick Pills -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('admin.whatsapp.journey.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search by member name, phone (+91...), or email..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Peers ({{ number_format($stats['total_peers']) }})</option>
                        <option value="in_journey" {{ $status === 'in_journey' ? 'selected' : '' }}>In Journey ({{ number_format($stats['total_in_journey']) }})</option>
                        <option value="not_started" {{ $status === 'not_started' ? 'selected' : '' }}>Not Started (No journey yet)</option>
                        <option value="in_progress" {{ $status === 'in_progress' ? 'selected' : '' }}>In Progress (Has pending sends)</option>
                        <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed (All 30 processed)</option>
                        <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Has Failures</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary btn-sm px-3">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    @if($search !== '' || $status !== '')
                        <a href="{{ route('admin.whatsapp.journey.index') }}" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    @endif
                </div>
            </form>

            <!-- Quick Status Filter Pills -->
            <div class="d-flex gap-2 mt-3 flex-wrap align-items-center pt-2 border-top">
                <span class="small text-muted fw-semibold me-1"><i class="bi bi-tags me-1"></i>Quick Filter:</span>
                <a href="{{ route('admin.whatsapp.journey.index', array_merge(request()->except('status', 'page'), [])) }}" class="badge rounded-pill text-decoration-none px-3 py-2 {{ $status === '' ? 'bg-primary text-white' : 'bg-light text-secondary border' }}">
                    All
                </a>
                <a href="{{ route('admin.whatsapp.journey.index', array_merge(request()->except('status', 'page'), ['status' => 'in_journey'])) }}" class="badge rounded-pill text-decoration-none px-3 py-2 {{ $status === 'in_journey' ? 'bg-info text-white' : 'bg-light text-secondary border' }}">
                    In Journey
                </a>
                <a href="{{ route('admin.whatsapp.journey.index', array_merge(request()->except('status', 'page'), ['status' => 'not_started'])) }}" class="badge rounded-pill text-decoration-none px-3 py-2 {{ $status === 'not_started' ? 'bg-secondary text-white' : 'bg-light text-secondary border' }}">
                    Not Started
                </a>
                <a href="{{ route('admin.whatsapp.journey.index', array_merge(request()->except('status', 'page'), ['status' => 'in_progress'])) }}" class="badge rounded-pill text-decoration-none px-3 py-2 {{ $status === 'in_progress' ? 'bg-warning text-dark' : 'bg-light text-secondary border' }}">
                    In Progress
                </a>
                <a href="{{ route('admin.whatsapp.journey.index', array_merge(request()->except('status', 'page'), ['status' => 'completed'])) }}" class="badge rounded-pill text-decoration-none px-3 py-2 {{ $status === 'completed' ? 'bg-success text-white' : 'bg-light text-secondary border' }}">
                    Completed
                </a>
                <a href="{{ route('admin.whatsapp.journey.index', array_merge(request()->except('status', 'page'), ['status' => 'failed'])) }}" class="badge rounded-pill text-decoration-none px-3 py-2 {{ $status === 'failed' ? 'bg-danger text-white' : 'bg-light text-secondary border' }}">
                    Failures
                </a>
            </div>
        </div>
    </div>

    <!-- Members Table -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-gray-800">
                <i class="bi bi-people-fill me-2 text-primary"></i>Member Journey Timelines & Progress
            </h6>
            <span class="small text-muted">Showing {{ $members->firstItem() ?? 0 }} to {{ $members->lastItem() ?? 0 }} of {{ $members->total() }} members</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="min-width: 240px;">Member</th>
                        <th style="min-width: 170px;">WhatsApp Phone</th>
                        <th style="min-width: 130px;">Registered</th>
                        <th style="min-width: 220px;">Progress (30 Days)</th>
                        <th class="text-center">Sent</th>
                        <th class="text-center">Skipped</th>
                        <th class="text-center">Scheduled</th>
                        <th class="text-center">Failed</th>
                        <th class="text-end pe-3" style="min-width: 160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($members as $member)
                        @php
                            $totalDays = (int) ($member->total_days_count ?? 0);
                            $totalSent = (int) ($member->total_sent ?? 0);
                            $totalSkipped = (int) ($member->total_skipped ?? 0);
                            $totalScheduled = (int) ($member->total_days_scheduled ?? 0);
                            $totalFailed = (int) ($member->total_failed ?? 0);
                            $processedDays = $totalSent + $totalSkipped;
                            $percent = $totalDays > 0 ? round(($processedDays / 30) * 100) : 0;
                            $memberDisplayName = $member->display_name ?: trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
                            $initials = strtoupper(substr($memberDisplayName ?: 'P', 0, 1));
                            $memberPhone = trim((string) ($member->phone ?? ''));
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; font-size: 14px;">
                                        {{ $initials }}
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="fw-bold text-dark text-truncate" title="{{ $memberDisplayName }}">{{ $memberDisplayName ?: 'Peer Member' }}</div>
                                        <div class="text-muted small text-truncate" style="font-size: 12px;" title="{{ $member->email }}">{{ $member->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if(!empty($memberPhone))
                                    @php
                                        $cleanPhone = preg_replace('/\D/', '', $memberPhone);
                                    @endphp
                                    <a href="https://wa.me/{{ $cleanPhone }}" target="_blank" class="badge bg-success-subtle text-success border border-success-subtle font-monospace text-decoration-none px-2 py-1" title="Open WhatsApp chat with {{ $memberDisplayName }}">
                                        <i class="bi bi-whatsapp me-1"></i>{{ $memberPhone }}
                                    </a>
                                @else
                                    <span class="badge bg-light text-muted border" title="Phone missing from member profile">
                                        <i class="bi bi-phone me-1"></i>No Phone
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="small text-muted">
                                    {{ $member->created_at ? $member->created_at->format('d M Y') : '—' }}
                                </span>
                            </td>
                            <td>
                                @if($totalDays > 0)
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="small fw-bold {{ $percent === 100 ? 'text-success' : 'text-primary' }}">{{ $percent }}%</span>
                                            <span class="small text-muted">{{ $processedDays }}/30 Days</span>
                                        </div>
                                        <div class="progress" style="height: 6px; border-radius: 4px;">
                                            <div class="progress-bar {{ $percent === 100 ? 'bg-success' : 'bg-primary' }}" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <i class="bi bi-hourglass me-1"></i>Not Started
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($totalSent > 0)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        {{ $totalSent }}
                                    </span>
                                @else
                                    <span class="text-muted small">0</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($totalSkipped > 0)
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1" title="Profile condition met — skipped & cascaded to next day">
                                        {{ $totalSkipped }}
                                    </span>
                                @else
                                    <span class="text-muted small">0</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($totalScheduled > 0)
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                        {{ $totalScheduled }}
                                    </span>
                                @else
                                    <span class="text-muted small">0</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($totalFailed > 0)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                        {{ $totalFailed }}
                                    </span>
                                @else
                                    <span class="text-muted small">0</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                @if($totalDays > 0)
                                    <a href="{{ route('admin.whatsapp.journey.show', $member->id) }}" class="btn btn-outline-primary btn-sm shadow-sm px-2">
                                        <i class="bi bi-calendar3 me-1"></i>Timeline
                                    </a>
                                @else
                                    <form action="{{ route('admin.whatsapp.journey.initialize', $member->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm shadow-sm px-2" title="Activate 30-Day Journey for this member">
                                            <i class="bi bi-play-fill me-1"></i>Start Journey
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.whatsapp.journey.show', $member->id) }}" class="btn btn-outline-secondary btn-sm" title="View details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                No members found matching your search.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($members->hasPages())
            <div class="card-footer bg-white py-3 border-0">
                {{ $members->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
