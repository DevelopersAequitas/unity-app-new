@extends('admin.layouts.app')

@section('title', 'Peers Store — System Health & Diagnostics')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.config.index') }}" class="text-decoration-none text-muted">Configuration</a></li>
                    <li class="breadcrumb-item active" aria-current="page">System Health</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-heart-pulse-fill text-danger"></i> Peers Store System Health & Diagnostics
            </h1>
        </div>
        <div>
            <a href="{{ route('admin.store.config.system-health') }}" class="btn btn-outline-primary d-flex align-items-center gap-2">
                <i class="bi bi-arrow-clockwise"></i> Refresh Diagnostics
            </a>
        </div>
    </div>

    {{-- Services Health Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-success bg-opacity-10 border-success border-opacity-25 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-database-check text-success fs-1"></i>
                    <div>
                        <span class="text-muted fw-semibold small text-uppercase">Database Connection</span>
                        <h4 class="fw-bold text-success my-1">{{ $dbStatus ? 'Connected (Healthy)' : 'Degraded' }}</h4>
                        <small class="text-muted">PostgreSQL Engine active</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-primary bg-opacity-10 border-primary border-opacity-25 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-hdd-network text-primary fs-1"></i>
                    <div>
                        <span class="text-muted fw-semibold small text-uppercase">Cache Subsystem</span>
                        <h4 class="fw-bold text-primary my-1">{{ $cacheStatus ? 'Operational' : 'Failed' }}</h4>
                        <small class="text-muted">Application cache responsive</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 bg-info bg-opacity-10 border-info border-opacity-25 h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-clock-history text-info fs-1"></i>
                    <div>
                        <span class="text-muted fw-semibold small text-uppercase">Server Local Timestamp</span>
                        <h5 class="fw-bold text-dark my-1">{{ $serverTime }}</h5>
                        <small class="text-muted">Timezone: {{ config('app.timezone') }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Actionable Operational Backlogs --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="card-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-octagon text-warning"></i> Operational Attention Matrix
            </h5>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-boxes fs-4 text-warning"></i>
                        <div>
                            <span class="fw-bold text-dark">Depleted / Low Stock SKUs</span>
                            <div class="text-muted small">Items requiring immediate purchase order replenishment</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge {{ $lowStockAlertCount > 0 ? 'bg-danger' : 'bg-success' }} fs-6 px-3 py-1.5">
                            {{ $lowStockAlertCount }} SKU(s)
                        </span>
                        <a href="{{ route('admin.store.inventory.low-stock') }}" class="btn btn-sm btn-outline-secondary">View</a>
                    </div>
                </li>

                <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-shield-check fs-4 text-primary"></i>
                        <div>
                            <span class="fw-bold text-dark">Pending Maker-Checker Wallet Adjustments</span>
                            <div class="text-muted small">Requests awaiting authorization by a secondary administrator</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge {{ $pendingAdjustmentsCount > 0 ? 'bg-warning text-dark' : 'bg-success' }} fs-6 px-3 py-1.5">
                            {{ $pendingAdjustmentsCount }} Request(s)
                        </span>
                        <a href="{{ route('admin.store.wallet.adjustments') }}" class="btn btn-sm btn-outline-secondary">View</a>
                    </div>
                </li>

                <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-headset fs-4 text-info"></i>
                        <div>
                            <span class="fw-bold text-dark">Unresolved Support Inquiries</span>
                            <div class="text-muted small">Open customer helpdesk tickets awaiting customer support reply</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge {{ $unresolvedTicketsCount > 0 ? 'bg-info text-white' : 'bg-success' }} fs-6 px-3 py-1.5">
                            {{ $unresolvedTicketsCount }} Ticket(s)
                        </span>
                        <a href="{{ route('admin.store.support.index') }}" class="btn btn-sm btn-outline-secondary">View</a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</div>
@endsection
