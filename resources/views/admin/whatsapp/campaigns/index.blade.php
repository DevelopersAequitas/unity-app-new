@extends('admin.layouts.app')

@section('title', 'WhatsApp Bulk Campaigns - Peers Global Unity')

@push('styles')
<style>
    .campaign-progress-circle {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: conic-gradient(#4f46e5 calc(var(--percentage) * 1%), #e2e8f0 0);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        color: #1e293b;
        position: relative;
    }
    .campaign-progress-circle::after {
        content: '';
        position: absolute;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #ffffff;
    }
    .campaign-progress-circle span {
        position: relative;
        z-index: 1;
    }
    .badge-status-draft { background-color: #f1f5f9; color: #475569; }
    .badge-status-scheduled { background-color: #e0e7ff; color: #4338ca; }
    .badge-status-queued { background-color: #fef3c7; color: #d97706; }
    .badge-status-processing { background-color: #dbeafe; color: #1d4ed8; }
    .badge-status-completed { background-color: #dcfce7; color: #15803d; }
    .badge-status-completed_with_errors { background-color: #fef9c3; color: #854d0e; }
    .badge-status-failed { background-color: #fee2e2; color: #b91c1c; }
    .badge-status-cancelled { background-color: #f3f4f6; color: #6b7280; }
    .btn-resho-primary {
        background-color: #5542f6;
        color: #ffffff;
        font-weight: 500;
        border-radius: 8px;
    }
    .btn-resho-primary:hover {
        background-color: #4332d6;
        color: #ffffff;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    {{-- Header Section --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small text-muted">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Bulk Campaign</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">
                <i class="bi bi-broadcast-pin text-primary me-2"></i>WhatsApp Bulk Campaigns
            </h1>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.whatsapp.campaigns.create') }}" class="btn btn-resho-primary px-3 shadow-sm">
                <i class="bi bi-plus-lg me-1"></i>Bulk Campaign
            </a>
        </div>
    </div>

    {{-- Session Alerts --}}
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

    @if(isset($dbError) && $dbError)
        <div class="alert alert-warning border-warning shadow-sm alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-database-fill-exclamation fs-3 me-3 text-warning"></i>
                <div>
                    <strong class="d-block">Database Setup Notice:</strong>
                    <span>{{ $dbError }}</span>
                    <div class="mt-1 small text-muted">File location: <code>docs/sql/whatsapp_bulk_campaigns.sql</code></div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Metrics Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 bg-primary bg-opacity-10 text-primary me-3">
                        <i class="bi bi-megaphone fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Campaigns</div>
                        <div class="fs-4 fw-bold text-gray-800">{{ number_format($stats['total']) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 bg-warning bg-opacity-10 text-warning me-3">
                        <i class="bi bi-clock-history fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Pending / In Progress</div>
                        <div class="fs-4 fw-bold text-warning">{{ number_format($stats['pending_or_scheduled']) }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 bg-success bg-opacity-10 text-success me-3">
                        <i class="bi bi-send-check fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Messages Sent</div>
                        <div class="fs-4 fw-bold text-success">{{ number_format($stats['total_sent']) }}</div>
                        <div class="small text-muted">{{ number_format($stats['total_delivered']) }} delivered</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 bg-info bg-opacity-10 text-info me-3">
                        <i class="bi bi-eye fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Messages Read</div>
                        <div class="fs-4 fw-bold text-info">{{ number_format($stats['total_read']) }}</div>
                        <div class="small text-danger">{{ number_format($stats['total_failed']) }} failed</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Filter & Table Card --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3">
            <form method="GET" action="{{ route('admin.whatsapp.campaigns.index') }}" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="Search campaign name..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="scheduled" {{ $status === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                        <option value="queued" {{ $status === 'queued' ? 'selected' : '' }}>Queued</option>
                        <option value="processing" {{ $status === 'processing' ? 'selected' : '' }}>Processing</option>
                        <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="completed_with_errors" {{ $status === 'completed_with_errors' ? 'selected' : '' }}>Completed with Errors</option>
                        <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="template_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Templates</option>
                        @foreach($templates as $tmpl)
                            <option value="{{ $tmpl->id }}" {{ $templateId === (string)$tmpl->id ? 'selected' : '' }}>
                                {{ $tmpl->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2 justify-content-end">
                    <button type="submit" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-filter me-1"></i>Filter
                    </button>
                    @if($search || $status || $templateId)
                        <a href="{{ route('admin.whatsapp.campaigns.index') }}" class="btn btn-light btn-sm text-danger" title="Clear Filters">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Name</th>
                        <th>Template Name</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Created At</th>
                        <th class="text-end" style="width: 80px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @if($campaigns && $campaigns->count() > 0)
                        @foreach($campaigns as $index => $campaign)
                            @php
                                $progressPct = $campaign->progress_percentage;
                                $statusSlug = strtolower($campaign->status);
                                $statusLabel = match($campaign->status) {
                                    'completed' => 'Executed',
                                    'completed_with_errors' => 'Executed (w/ errors)',
                                    'processing' => 'Sending',
                                    'queued' => 'Pending',
                                    'scheduled' => 'Scheduled',
                                    'cancelled' => 'Cancelled',
                                    'failed' => 'Failed',
                                    default => ucfirst($campaign->status)
                                };
                            @endphp
                            <tr>
                                <td class="text-muted fw-bold">
                                    {{ $campaigns->firstItem() + $index }}
                                </td>
                                <td>
                                    <a href="{{ route('admin.whatsapp.campaigns.show', $campaign->id) }}" class="fw-bold text-decoration-none text-primary">
                                        {{ $campaign->name }}
                                    </a>
                                    @if($campaign->schedule_type === 'scheduled' && $campaign->scheduled_at)
                                        <div class="small text-muted">
                                            <i class="bi bi-clock me-1"></i>Scheduled for: {{ $campaign->scheduled_at->format('d M Y, h:i A') }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <code class="text-dark">{{ $campaign->template_name_snapshot }}</code>
                                    <span class="badge bg-light text-muted border ms-1">{{ $campaign->template_language }}</span>
                                </td>
                                <td>
                                    <span class="badge rounded-pill px-3 py-2 badge-status-{{ $statusSlug }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="campaign-progress-circle" style="--percentage: {{ $progressPct }};">
                                            <span>{{ $progressPct }}%</span>
                                        </div>
                                        <div class="small lh-sm">
                                            <div><strong>Total:</strong> {{ $campaign->valid_count }}</div>
                                            <div class="text-muted">Delivered: {{ $campaign->delivered_rate }}%</div>
                                            <div class="text-muted">Read: {{ $campaign->read_count }} ({{ $campaign->read_rate }}%)</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-gray-800 small">
                                        {{ $campaign->created_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}
                                    </div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        {{ $campaign->created_at?->timezone('Asia/Kolkata')->diffForHumans() }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-sm rounded-circle p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('admin.whatsapp.campaigns.show', $campaign->id) }}">
                                                    <i class="bi bi-eye me-2 text-primary"></i>View Details
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="{{ route('admin.whatsapp.campaigns.export', $campaign->id) }}">
                                                    <i class="bi bi-download me-2 text-secondary"></i>Export Results
                                                </a>
                                            </li>
                                            @if($campaign->canRetry())
                                                <li>
                                                    <form action="{{ route('admin.whatsapp.campaigns.retry', $campaign->id) }}" method="POST" onsubmit="return confirm('Retry sending to {{ $campaign->failed_count }} failed recipients?');">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item text-warning">
                                                            <i class="bi bi-arrow-repeat me-2"></i>Retry Failures ({{ $campaign->failed_count }})
                                                        </button>
                                                    </form>
                                                </li>
                                            @endif
                                            @if($campaign->canCancel())
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="{{ route('admin.whatsapp.campaigns.cancel', $campaign->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to cancel this campaign?');">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="bi bi-x-circle me-2"></i>Cancel Campaign
                                                        </button>
                                                    </form>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                    <h5>No Bulk Campaigns Found</h5>
                                    <p class="small mb-3">Create your first WhatsApp bulk campaign using an approved WhatsApp template and CSV contact list.</p>
                                    <a href="{{ route('admin.whatsapp.campaigns.create') }}" class="btn btn-resho-primary btn-sm">
                                        <i class="bi bi-plus-lg me-1"></i>Create Campaign
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        @if($campaigns && $campaigns->hasPages())
            <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
                <div class="small text-muted">
                    Showing {{ $campaigns->firstItem() }} to {{ $campaigns->lastItem() }} of {{ $campaigns->total() }} campaigns
                </div>
                <div>
                    {{ $campaigns->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
