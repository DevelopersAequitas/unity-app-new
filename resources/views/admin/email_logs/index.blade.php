@extends('admin.layouts.app')

@section('title', 'Email Logs')

@section('content')
    @php
        $statusBadgeClass = static fn (?string $status): string => match ($status) {
            'sent' => 'bg-success-subtle text-success border border-success-subtle',
            'failed' => 'bg-danger-subtle text-danger border border-danger-subtle',
            'queued', 'pending' => 'bg-warning-subtle text-warning border border-warning-subtle',
            default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
        $sortUrl = static fn (string $column) => route('admin.email-logs.index', array_merge(request()->query(), [
            'sort' => $column,
            'direction' => (($filters['sort'] ?? 'created_at') === $column && ($filters['direction'] ?? 'desc') === 'asc') ? 'desc' : 'asc',
        ]));
    @endphp

    <form id="emailLogsFiltersForm" method="GET" action="{{ route('admin.email-logs.index') }}"></form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Email Logs</h1>
        <div class="d-flex gap-2 align-items-center">
            <a class="btn btn-sm btn-outline-success" href="{{ route('admin.email-logs.export.csv', request()->query()) }}">Export CSV</a>
            <span class="badge bg-light text-dark border">Total: {{ number_format($emailLogs->total()) }}</span>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label small text-muted">Search</label><input type="text" name="search" form="emailLogsFiltersForm" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Subject, recipient, template, module"></div>
                <div class="col-md-3"><label class="form-label small text-muted">Recipient Email</label><input type="text" name="recipient_email" form="emailLogsFiltersForm" value="{{ $filters['recipient_email'] ?? '' }}" class="form-control"></div>
                <div class="col-md-3"><label class="form-label small text-muted">Subject</label><input type="text" name="subject" form="emailLogsFiltersForm" value="{{ $filters['subject'] ?? '' }}" class="form-control"></div>
                <div class="col-md-3"><label class="form-label small text-muted">Template Key</label><select name="template_key" form="emailLogsFiltersForm" class="form-select"><option value="">All</option>@foreach ($templateKeys as $templateKeyOption)<option value="{{ $templateKeyOption }}" @selected(($filters['template_key'] ?? '') === $templateKeyOption)>{{ $templateKeyOption }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label small text-muted">Source Module</label><select name="source_module" form="emailLogsFiltersForm" class="form-select"><option value="">All</option>@foreach ($sourceModules as $sourceModuleOption)<option value="{{ $sourceModuleOption }}" @selected(($filters['source_module'] ?? '') === $sourceModuleOption)>{{ $sourceModuleOption }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label small text-muted">Status</label><select name="status" form="emailLogsFiltersForm" class="form-select"><option value="all" @selected(($filters['status'] ?? 'all') === 'all')>All</option><option value="queued" @selected(($filters['status'] ?? 'all') === 'queued')>Queued</option><option value="sent" @selected(($filters['status'] ?? 'all') === 'sent')>Sent</option><option value="failed" @selected(($filters['status'] ?? 'all') === 'failed')>Failed</option><option value="pending" @selected(($filters['status'] ?? 'all') === 'pending')>Pending</option></select></div>
                <div class="col-md-2"><label class="form-label small text-muted">Triggered By</label><select name="triggered_by" form="emailLogsFiltersForm" class="form-select"><option value="all" @selected(empty($filters['triggered_by']))>All</option><option value="admin" @selected(($filters['triggered_by'] ?? '') === 'admin')>Admin</option><option value="user" @selected(($filters['triggered_by'] ?? '') === 'user')>User</option><option value="system" @selected(($filters['triggered_by'] ?? '') === 'system')>System</option><option value="scheduled_job" @selected(($filters['triggered_by'] ?? '') === 'scheduled_job')>Scheduled Job</option><option value="queue_worker" @selected(($filters['triggered_by'] ?? '') === 'queue_worker')>Queue Worker</option></select></div>
                <div class="col-md-2"><label class="form-label small text-muted">Admin ID</label><input type="text" name="admin_id" form="emailLogsFiltersForm" value="{{ $filters['admin_id'] ?? '' }}" class="form-control"></div>
                <div class="col-md-2"><label class="form-label small text-muted">User ID</label><input type="text" name="user_id" form="emailLogsFiltersForm" value="{{ $filters['user_id'] ?? '' }}" class="form-control"></div>
                <div class="col-md-2"><label class="form-label small text-muted">From</label><input type="date" name="date_from" form="emailLogsFiltersForm" value="{{ $filters['date_from'] ?? '' }}" class="form-control"></div>
                <div class="col-md-2"><label class="form-label small text-muted">To</label><input type="date" name="date_to" form="emailLogsFiltersForm" value="{{ $filters['date_to'] ?? '' }}" class="form-control"></div>
                <div class="col-md-2"><label class="form-label small text-muted">Per Page</label><select name="per_page" form="emailLogsFiltersForm" class="form-select">@foreach ([10, 20, 50, 100] as $perPageOption)<option value="{{ $perPageOption }}" @selected(($filters['per_page'] ?? 20) == $perPageOption)>{{ $perPageOption }}</option>@endforeach</select></div>
                <div class="col-md-2 d-flex gap-2"><button type="submit" form="emailLogsFiltersForm" class="btn btn-primary">Apply</button><a href="{{ route('admin.email-logs.index') }}" class="btn btn-outline-secondary">Reset</a></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm"><div class="table-responsive"><table class="table mb-0 align-middle"><thead class="table-light"><tr>
        <th>ID</th><th><a href="{{ $sortUrl('recipient_email') }}">Recipient Email</a></th><th>Recipient Name</th><th><a href="{{ $sortUrl('subject') }}">Subject</a></th><th>Template Key</th><th>Source Module</th><th><a href="{{ $sortUrl('status') }}">Status</a></th><th><a href="{{ $sortUrl('sent_at') }}">Sent At</a></th><th><a href="{{ $sortUrl('created_at') }}">Created At</a></th><th>Triggered By</th><th>Triggered User</th><th>Mail Provider</th><th>Error Message</th><th class="text-end">Actions</th>
    </tr></thead><tbody>
    @forelse ($emailLogs as $emailLog)
        <tr>
            <td><span class="small text-muted">{{ Str::limit($emailLog->id, 8, '') }}</span></td><td>{{ $emailLog->to_email ?: '—' }}</td><td>{{ $emailLog->to_name ?: '—' }}</td><td class="text-wrap" style="max-width:260px;">{{ $emailLog->subject ?: '—' }}</td><td>{{ $emailLog->template_key ?: '—' }}</td><td>{{ $emailLog->source_module ?: '—' }}</td><td><span class="badge {{ $statusBadgeClass((string) $emailLog->status) }}">{{ ucfirst((string) $emailLog->status) }}</span></td><td>{{ optional($emailLog->sent_at)->format('Y-m-d H:i:s') ?? '—' }}</td><td>{{ optional($emailLog->created_at)->format('Y-m-d H:i:s') ?? '—' }}</td><td>{{ $emailLog->triggered_by_label }}</td><td>{{ $emailLog->trigger_user_name ?: $emailLog->user?->name ?: '—' }}</td><td>{{ $emailLog->mail_provider ?: '—' }}</td><td class="text-danger small">{{ $emailLog->status === 'failed' ? Str::limit((string) $emailLog->error_message, 80) : '—' }}</td><td class="text-end"><a href="{{ route('admin.email-logs.show', array_merge(['id' => $emailLog->id], request()->query())) }}" class="btn btn-sm btn-outline-primary">View</a></td>
        </tr>
    @empty
        <tr><td colspan="14" class="text-center text-muted">No email logs found.</td></tr>
    @endforelse
    </tbody></table></div></div>

    <div class="mt-3">{{ $emailLogs->links() }}</div>
@endsection
