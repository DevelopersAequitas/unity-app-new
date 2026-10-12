@extends('admin.layouts.app')

@section('title', 'WhatsApp Message Logs - Peers Global Unity')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-chat-dots text-primary me-2"></i>WhatsApp Message Logs
            </h1>
            <p class="text-muted small mb-0">Delivery, status, read receipts, and error tracking for Meta Cloud API messages.</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <span class="badge bg-success-subtle text-success border border-success-subtle py-2 px-3 small d-inline-flex align-items-center">
                <span class="spinner-grow spinner-grow-sm me-2 text-success" role="status" style="width: 8px; height: 8px;"></span>
                Cloud API Active
            </span>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()" title="Refresh Statuses">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
            @if(($stats['delivered'] ?? 0) > 0)
                <form action="{{ route('admin.whatsapp.messages.mark_all_read') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary btn-sm" title="Mark all delivered test messages as read">
                        <i class="bi bi-check2-all me-1"></i>Mark All Read
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.whatsapp.test_message') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-send me-1"></i>Send Test Message
            </a>
            <a href="{{ route('admin.whatsapp.webhook_logs') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left-right me-1"></i>Webhook Events
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold">Total</div>
                    <div class="h4 mb-0 fw-bold text-gray-800">{{ $stats['total'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card shadow-sm border-0 border-start border-info border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold">Sent</div>
                    <div class="h4 mb-0 fw-bold text-info">{{ $stats['sent'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold">Delivered</div>
                    <div class="h4 mb-0 fw-bold text-success">{{ $stats['delivered'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold">Read</div>
                    <div class="h4 mb-0 fw-bold text-primary">{{ $stats['read'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card shadow-sm border-0 border-start border-danger border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold">Failed</div>
                    <div class="h4 mb-0 fw-bold text-danger">{{ $stats['failed'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card shadow-sm border-0 border-start border-warning border-4 h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold">Test / Dry Run</div>
                    <div class="h4 mb-0 fw-bold text-warning-emphasis">{{ ($stats['test'] ?? 0) + ($stats['dry_run'] ?? 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('admin.whatsapp.messages') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search phone, ID, template, event..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="sent" {{ strtolower((string)$status) === 'sent' ? 'selected' : '' }}>SENT</option>
                        <option value="delivered" {{ strtolower((string)$status) === 'delivered' ? 'selected' : '' }}>DELIVERED</option>
                        <option value="read" {{ strtolower((string)$status) === 'read' ? 'selected' : '' }}>READ</option>
                        <option value="failed" {{ strtolower((string)$status) === 'failed' ? 'selected' : '' }}>FAILED</option>
                        <option value="dry_run" {{ strtolower((string)$status) === 'dry_run' ? 'selected' : '' }}>DRY RUN</option>
                        <option value="skipped" {{ strtolower((string)$status) === 'skipped' ? 'selected' : '' }}>SKIPPED</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="text" name="template_key" class="form-control form-control-sm" placeholder="Template Key" value="{{ $templateKey ?? $template }}">
                </div>
                <div class="col-md-1">
                    <input type="date" name="date_from" class="form-control form-control-sm" title="Date From" value="{{ $dateFrom ?? '' }}">
                </div>
                <div class="col-md-1">
                    <input type="date" name="date_to" class="form-control form-control-sm" title="Date To" value="{{ $dateTo ?? '' }}">
                </div>
                <div class="col-md-2">
                    <select name="is_test" class="form-select form-select-sm">
                        <option value="">All Environments</option>
                        <option value="1" {{ ($isTest === '1' || $isTest === 'true') ? 'selected' : '' }}>Test Messages</option>
                        <option value="0" {{ ($isTest === '0' || $isTest === 'false') ? 'selected' : '' }}>Production Only</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                    @if($search || $status || $template || $dateFrom || $dateTo || $isTest !== null)
                        <a href="{{ route('admin.whatsapp.messages') }}" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Messages Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center border-bottom">
            <span class="small fw-semibold text-muted text-uppercase" style="letter-spacing: 0.5px;">Message Dispatch Stream</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1 py-1 px-2" style="font-size: 11px;">
                <span class="spinner-grow spinner-grow-sm text-success" role="status" style="width: 7px; height: 7px;"></span>
                Live Audit Stream
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Date / Sent</th>
                        <th>Recipient</th>
                        <th>User</th>
                        <th>Template</th>
                        <th>Event</th>
                        <th>Status</th>
                        <th>Meta Message ID</th>
                        <th>Error Info</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($messages as $msg)
                        <tr>
                            <td class="ps-3 small">
                                <div class="fw-semibold text-dark">{{ $msg->created_at ? $msg->created_at->format('d M Y') : '-' }}</div>
                                <div class="text-muted" style="font-size: 11px;">{{ $msg->created_at ? $msg->created_at->format('H:i:s') : '' }}</div>
                            </td>
                            <td>
                                @if($msg->is_test)
                                    <div>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle font-monospace py-0.5 px-1.5" style="font-size: 10px;">
                                            <i class="bi bi-shield-exclamation me-1"></i>TEST ROUTE
                                        </span>
                                        <div class="font-monospace fw-bold text-dark mt-0.5" style="font-size: 12.5px;">
                                            {{ $msg->actual_recipient_phone ?: $msg->recipient_phone }}
                                        </div>
                                        @if($msg->requested_recipient_phone && $msg->requested_recipient_phone !== $msg->actual_recipient_phone)
                                            <div class="text-muted small" style="font-size: 10.5px;">
                                                Req: <span class="font-monospace">{{ $msg->requested_recipient_phone }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span class="font-monospace fw-bold text-dark">{{ $msg->recipient_phone }}</span>
                                @endif
                            </td>
                            <td class="small">
                                @if($msg->user)
                                    <div class="fw-semibold text-dark">{{ $msg->user->first_name ?? $msg->user->name ?? 'User' }}</div>
                                    <div class="text-muted" style="font-size: 11px;">{{ Str::limit($msg->user->email ?? '', 20) }}</div>
                                @elseif($msg->user_id)
                                    <span class="font-monospace text-muted small" title="{{ $msg->user_id }}">{{ Str::limit($msg->user_id, 8) }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="badge bg-light text-dark border">{{ $msg->template_name ?: $msg->template_key }}</div>
                                @if($msg->template_key && $msg->template_key !== $msg->template_name)
                                    <div class="text-muted font-monospace mt-0.5" style="font-size: 10px;">{{ $msg->template_key }}</div>
                                @endif
                            </td>
                            <td>
                                @if($msg->event_key)
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size: 11px;">
                                        {{ $msg->event_key }}
                                    </span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $st = strtolower((string)$msg->status);
                                @endphp
                                @if($st === 'read')
                                    <span class="badge bg-primary"><i class="bi bi-check2-all me-1"></i>READ</span>
                                @elseif($st === 'delivered')
                                    <span class="badge bg-success"><i class="bi bi-check2-all me-1"></i>DELIVERED</span>
                                @elseif($st === 'sent')
                                    <span class="badge bg-info text-dark" title="Accepted by Meta Cloud API"><i class="bi bi-check2 me-1"></i>SENT</span>
                                @elseif($st === 'failed')
                                    <span class="badge bg-danger"><i class="bi bi-exclamation-octagon me-1"></i>FAILED</span>
                                @elseif($st === 'dry_run')
                                    <span class="badge bg-secondary"><i class="bi bi-eye-slash me-1"></i>DRY RUN</span>
                                @elseif($st === 'skipped')
                                    <span class="badge bg-warning text-dark"><i class="bi bi-dash-circle me-1"></i>SKIPPED</span>
                                @else
                                    <span class="badge bg-light text-dark border"><i class="bi bi-clock me-1"></i>{{ strtoupper($st) }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="font-monospace small text-muted text-truncate d-inline-block" style="max-width: 140px;" title="{{ $msg->provider_message_id }}">
                                    {{ $msg->provider_message_id ?: '-' }}
                                </span>
                            </td>
                            <td>
                                @if($msg->error_code)
                                    <div>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-semibold" title="{{ $msg->error_message }}">
                                            <i class="bi bi-exclamation-triangle me-1"></i>Error {{ $msg->error_code }}
                                        </span>
                                        <div class="text-danger small text-truncate mt-1" style="max-width: 180px; font-size: 11px;" title="{{ $msg->error_message }}">
                                            {{ $msg->error_message }}
                                        </div>
                                    </div>
                                @elseif($st === 'sent')
                                    <span class="text-muted small" title="Meta accepted outbound message. Awaiting network delivery callback.">
                                        <i class="bi bi-hourglass-split text-secondary me-1"></i>In transit
                                    </span>
                                @elseif($st === 'dry_run')
                                    <span class="text-muted small">No delivery call made</span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <a href="{{ route('admin.whatsapp.messages.show', $msg->id) }}" class="btn btn-outline-secondary btn-sm" title="View Payload & Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if(in_array($st, ['sent', 'delivered'], true))
                                        <div class="dropdown d-inline-block">
                                            <button class="btn btn-outline-primary btn-sm dropdown-toggle py-1 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Simulate Status">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 13px;">
                                                <li class="dropdown-header py-1 text-muted" style="font-size: 11px;">SIMULATE WEBHOOK</li>
                                                @if($st === 'sent')
                                                    <li>
                                                        <form action="{{ route('admin.whatsapp.messages.simulate_status', $msg->id) }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="status" value="delivered">
                                                            <button type="submit" class="dropdown-item py-1 text-success">
                                                                <i class="bi bi-check2 me-1"></i>Mark Delivered
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endif
                                                <li>
                                                    <form action="{{ route('admin.whatsapp.messages.simulate_status', $msg->id) }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="status" value="read">
                                                        <button type="submit" class="dropdown-item py-1 text-primary">
                                                            <i class="bi bi-check2-all me-1"></i>Mark Read (Blue Ticks)
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form action="{{ route('admin.whatsapp.messages.simulate_status', $msg->id) }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="status" value="failed">
                                                        <button type="submit" class="dropdown-item py-1 text-danger">
                                                            <i class="bi bi-x-circle me-1"></i>Mark Failed
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-chat-square-dots fs-1 d-block mb-2 text-muted"></i>
                                No WhatsApp message records found matching criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($messages->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="small text-muted">
                        Showing {{ $messages->firstItem() }} to {{ $messages->lastItem() }} of {{ $messages->total() }} records
                    </div>
                    <div>
                        {{ $messages->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
