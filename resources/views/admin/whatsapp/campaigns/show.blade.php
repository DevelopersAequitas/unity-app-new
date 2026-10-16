@extends('admin.layouts.app')

@section('title', 'Campaign: ' . $campaign->name . ' - Peers Global Unity')

@push('styles')
<style>
    .badge-status-draft { background-color: #f1f5f9; color: #475569; }
    .badge-status-scheduled { background-color: #e0e7ff; color: #4338ca; }
    .badge-status-queued { background-color: #fef3c7; color: #d97706; }
    .badge-status-processing { background-color: #dbeafe; color: #1d4ed8; }
    .badge-status-completed { background-color: #dcfce7; color: #15803d; }
    .badge-status-completed_with_errors { background-color: #fef9c3; color: #854d0e; }
    .badge-status-failed { background-color: #fee2e2; color: #b91c1c; }
    .badge-status-cancelled { background-color: #f3f4f6; color: #6b7280; }

    .recipient-badge-sent { background-color: #e0e7ff; color: #4338ca; }
    .recipient-badge-delivered { background-color: #dcfce7; color: #15803d; }
    .recipient-badge-read { background-color: #e0f2fe; color: #0369a1; }
    .recipient-badge-failed { background-color: #fee2e2; color: #b91c1c; }
    .recipient-badge-pending { background-color: #fef3c7; color: #b45309; }
    .recipient-badge-processing { background-color: #dbeafe; color: #1d4ed8; }
    .recipient-badge-cancelled { background-color: #f1f5f9; color: #64748b; }
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
                    <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.campaigns.index') }}" class="text-decoration-none">Bulk Campaign</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $campaign->name }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2">
                <h1 class="h3 mb-0 text-gray-800 fw-bold">{{ $campaign->name }}</h1>
                <span class="badge rounded-pill px-3 py-2 badge-status-{{ strtolower($campaign->status) }}">
                    {{ ucfirst(str_replace('_', ' ', $campaign->status)) }}
                </span>
            </div>
            <div class="small text-muted mt-1">
                Template: <strong>{{ $campaign->template_name_snapshot }}</strong> ({{ $campaign->template_language }}) &bull;
                Created by: {{ $campaign->creator?->name ?? 'System Admin' }} &bull;
                {{ $campaign->created_at?->format('d M Y, h:i A') }}
            </div>
        </div>
        <div class="d-flex gap-2">
            <form action="{{ route('admin.whatsapp.campaigns.sync_statuses', $campaign->id) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm" title="Synchronize latest delivery and read receipts from WhatsApp Webhooks">
                    <i class="bi bi-arrow-clockwise me-1"></i>Sync Delivery Status
                </button>
            </form>
            <a href="{{ route('admin.whatsapp.campaigns.export', $campaign->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
            @if($campaign->canRetry())
                <form action="{{ route('admin.whatsapp.campaigns.retry', $campaign->id) }}" method="POST" onsubmit="return confirm('Retry sending to {{ $campaign->failed_count }} failed recipients?');">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm">
                        <i class="bi bi-arrow-repeat me-1"></i>Retry Failures ({{ $campaign->failed_count }})
                    </button>
                </form>
            @endif
            @if($campaign->canCancel())
                <form action="{{ route('admin.whatsapp.campaigns.cancel', $campaign->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to cancel this campaign?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-x-circle me-1"></i>Cancel Campaign
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.whatsapp.campaigns.index') }}" class="btn btn-light btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>
    </div>

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

    {{-- Aggregate Delivery KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm rounded-3 text-center p-3 h-100">
                <div class="text-muted small fw-semibold">Total Valid</div>
                <div class="fs-4 fw-bold text-gray-800">{{ number_format($campaign->valid_count) }}</div>
                <div class="small text-muted">{{ $campaign->total_rows }} imported</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm rounded-3 text-center p-3 h-100">
                <div class="text-muted small fw-semibold">Sent</div>
                <div class="fs-4 fw-bold text-primary">{{ number_format($campaign->sent_count) }}</div>
                <div class="small text-muted">{{ $campaign->progress_percentage }}% progress</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm rounded-3 text-center p-3 h-100">
                <div class="text-muted small fw-semibold">Delivered</div>
                <div class="fs-4 fw-bold text-success">{{ number_format($campaign->delivered_count) }}</div>
                <div class="small text-success">{{ $campaign->delivered_rate }}% rate</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm rounded-3 text-center p-3 h-100">
                <div class="text-muted small fw-semibold">Read</div>
                <div class="fs-4 fw-bold text-info">{{ number_format($campaign->read_count) }}</div>
                <div class="small text-info">{{ $campaign->read_rate }}% rate</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm rounded-3 text-center p-3 h-100">
                <div class="text-muted small fw-semibold">Failed</div>
                <div class="fs-4 fw-bold text-danger">{{ number_format($campaign->failed_count) }}</div>
                <div class="small text-danger">{{ $campaign->valid_count > 0 ? round(($campaign->failed_count / $campaign->valid_count) * 100) : 0 }}% rate</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm rounded-3 text-center p-3 h-100">
                <div class="text-muted small fw-semibold">Timing</div>
                <div class="fs-6 fw-bold text-secondary text-truncate">
                    {{ $campaign->schedule_type === 'scheduled' ? 'Scheduled' : 'Immediate' }}
                </div>
                <div class="small text-muted">
                    {{ $campaign->scheduled_at ? $campaign->scheduled_at->format('d M, h:i A') : ($campaign->started_at ? $campaign->started_at->format('d M, h:i A') : '—') }}
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs Container --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom">
            <ul class="nav nav-tabs card-header-tabs" id="campaignDetailTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold" id="recipients-tab" data-bs-toggle="tab" data-bs-target="#recipientsTab" type="button" role="tab">
                        <i class="bi bi-people me-1"></i>Recipients ({{ number_format($campaign->valid_count) }})
                    </button>
                </li>
                @if($importErrors->count() > 0)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold text-danger" id="errors-tab" data-bs-toggle="tab" data-bs-target="#errorsTab" type="button" role="tab">
                            <i class="bi bi-exclamation-triangle me-1"></i>Import Errors ({{ $campaign->invalid_count }})
                        </button>
                    </li>
                @endif
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logsTab" type="button" role="tab">
                        <i class="bi bi-journal-text me-1"></i>Activity Logs
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-0">
            <div class="tab-content" id="campaignDetailTabsContent">

                {{-- Tab 1: Recipients List --}}
                <div class="tab-pane fade show active" id="recipientsTab" role="tabpanel">
                    <div class="p-3 border-bottom bg-light">
                        <form method="GET" action="{{ route('admin.whatsapp.campaigns.show', $campaign->id) }}" class="row g-2 align-items-center">
                            <div class="col-md-5">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="Search recipient name or phone..." value="{{ $search }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select name="recipient_status" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="">All Recipient Statuses</option>
                                    <option value="pending" {{ $recipientStatus === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="sent" {{ $recipientStatus === 'sent' ? 'selected' : '' }}>Sent</option>
                                    <option value="delivered" {{ $recipientStatus === 'delivered' ? 'selected' : '' }}>Delivered</option>
                                    <option value="read" {{ $recipientStatus === 'read' ? 'selected' : '' }}>Read</option>
                                    <option value="failed" {{ $recipientStatus === 'failed' ? 'selected' : '' }}>Failed</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-outline-secondary btn-sm">Filter</button>
                                @if($search || $recipientStatus)
                                    <a href="{{ route('admin.whatsapp.campaigns.show', $campaign->id) }}" class="btn btn-light btn-sm text-danger">Reset</a>
                                @endif
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase text-muted">
                                <tr>
                                    <th style="width: 60px;">Row</th>
                                    <th>Recipient</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Meta Message ID</th>
                                    <th>Timeline</th>
                                    <th>Error Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($recipients && $recipients->count() > 0)
                                    @foreach($recipients as $rec)
                                        <tr>
                                            <td class="text-muted fw-bold">{{ $rec->csv_row_number }}</td>
                                            <td>
                                                <div class="fw-bold text-gray-800">{{ $rec->name }}</div>
                                            </td>
                                            <td>
                                                <code class="text-dark">{{ $rec->phone }}</code>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill px-2 py-1 recipient-badge-{{ strtolower($rec->status) }}">
                                                    {{ strtoupper($rec->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($rec->whatsapp_message_id)
                                                    <span class="small font-monospace text-muted" title="{{ $rec->whatsapp_message_id }}">
                                                        {{ \Illuminate\Support\Str::limit($rec->whatsapp_message_id, 18) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted small">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="small lh-sm text-muted">
                                                    @if($rec->read_at)
                                                        <span class="text-info"><i class="bi bi-eye me-1"></i>Read: {{ $rec->read_at->format('h:i A') }}</span>
                                                    @elseif($rec->delivered_at)
                                                        <span class="text-success"><i class="bi bi-check2-all me-1"></i>Delivered: {{ $rec->delivered_at->format('h:i A') }}</span>
                                                    @elseif($rec->sent_at)
                                                        <span><i class="bi bi-check2 me-1"></i>Sent: {{ $rec->sent_at->format('h:i A') }}</span>
                                                    @else
                                                        <span>Pending</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @if($rec->error_message)
                                                    <span class="badge bg-danger bg-opacity-10 text-danger small text-wrap text-start" title="{{ $rec->error_message }}">
                                                        {{ \Illuminate\Support\Str::limit($rec->error_message, 45) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted small">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            No recipients found matching the filter.
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    @if($recipients && $recipients->hasPages())
                        <div class="p-3 border-top d-flex justify-content-between align-items-center">
                            <div class="small text-muted">
                                Showing {{ $recipients->firstItem() }} to {{ $recipients->lastItem() }} of {{ $recipients->total() }} recipients
                            </div>
                            <div>
                                {{ $recipients->links() }}
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Tab 2: Import Errors --}}
                @if($importErrors->count() > 0)
                    <div class="tab-pane fade" id="errorsTab" role="tabpanel">
                        <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                            <span class="small text-muted">Rows from the CSV import that had missing or invalid values.</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light small text-muted">
                                    <tr>
                                        <th>CSV Row</th>
                                        <th>Reason / Error Message</th>
                                        <th>Raw Row Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($importErrors as $err)
                                        <tr>
                                            <td class="fw-bold text-danger">{{ $err->row_number }}</td>
                                            <td class="text-danger small">{{ $err->error_message }}</td>
                                            <td>
                                                <code class="small text-dark">{{ json_encode($err->raw_data) }}</code>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Tab 3: Activity Logs --}}
                <div class="tab-pane fade" id="logsTab" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th>Event</th>
                                    <th>Details</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($logs && $logs->count() > 0)
                                    @foreach($logs as $log)
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary">{{ $log->event_type }}</span>
                                            </td>
                                            <td class="small">{{ $log->message }}</td>
                                            <td class="small text-muted">{{ $log->created_at?->format('d M Y, h:i:s A') }}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">No logs recorded yet.</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
