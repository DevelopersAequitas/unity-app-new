@extends('admin.layouts.app')

@section('title', 'Email Logs')

@section('content')
@php
    $statusBadgeClass = fn ($status) => match ($status) {
        'sent' => 'bg-success-subtle text-success border border-success-subtle',
        'failed' => 'bg-danger-subtle text-danger border border-danger-subtle',
        'queued', 'pending' => 'bg-warning-subtle text-warning border border-warning-subtle',
        default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
    };
    $sortUrl = function (string $field) use ($filters) {
        $direction = ($filters['sort'] === $field && $filters['direction'] === 'asc') ? 'desc' : 'asc';
        return request()->fullUrlWithQuery(['sort' => $field, 'direction' => $direction]);
    };
@endphp
<form id="emailLogsFiltersForm" method="GET" action="{{ route('admin.email-logs.index') }}"></form>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Email Logs</h1>
    <div class="d-flex gap-2"><a class="btn btn-outline-success btn-sm" href="{{ route('admin.email-logs.export', array_merge(request()->query(), ['format' => 'csv'])) }}">Export CSV</a><a class="btn btn-outline-success btn-sm" href="{{ route('admin.email-logs.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}">Export Excel</a><span class="badge bg-light text-dark border align-self-center">Total: {{ number_format($emailLogs->total()) }}</span></div>
</div>
<div class="card shadow-sm mb-3"><div class="card-body"><div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label small text-muted">Search</label><input name="search" form="emailLogsFiltersForm" value="{{ $filters['search'] }}" class="form-control" placeholder="Subject, email, name, template, module"></div>
    <div class="col-md-3"><label class="form-label small text-muted">Recipient Email</label><input name="recipient_email" form="emailLogsFiltersForm" value="{{ $filters['recipient_email'] }}" class="form-control"></div>
    <div class="col-md-3"><label class="form-label small text-muted">Subject</label><input name="subject" form="emailLogsFiltersForm" value="{{ $filters['subject'] }}" class="form-control"></div>
    <div class="col-md-3"><label class="form-label small text-muted">Template Key</label><select name="template_key" form="emailLogsFiltersForm" class="form-select"><option value="">All</option>@foreach($templateKeys as $value)<option value="{{ $value }}" @selected($filters['template_key']===$value)>{{ $value }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label small text-muted">Source Module</label><select name="source_module" form="emailLogsFiltersForm" class="form-select"><option value="">All</option>@foreach($sourceModules as $value)<option value="{{ $value }}" @selected($filters['source_module']===$value)>{{ $value }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label small text-muted">Status</label><select name="status" form="emailLogsFiltersForm" class="form-select"><option value="all">All</option>@foreach(['queued','sent','failed','pending'] as $value)<option value="{{ $value }}" @selected($filters['status']===$value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label small text-muted">Triggered By</label><select name="triggered_by" form="emailLogsFiltersForm" class="form-select"><option value="">All</option>@foreach(['Admin','User','System','Scheduled Job','Queue Worker'] as $value)<option value="{{ $value }}" @selected($filters['triggered_by']===$value)>{{ $value }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label small text-muted">Admin</label><select name="admin_id" form="emailLogsFiltersForm" class="form-select"><option value="">All</option>@foreach($admins as $admin)<option value="{{ $admin->id }}" @selected($filters['admin_id']===$admin->id)>{{ $admin->name ?: $admin->email }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label small text-muted">User</label><select name="user_id" form="emailLogsFiltersForm" class="form-select"><option value="">All</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected($filters['user_id']===$user->id)>{{ $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->email }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label small text-muted">From</label><input type="date" name="date_from" form="emailLogsFiltersForm" value="{{ $filters['date_from'] }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label small text-muted">To</label><input type="date" name="date_to" form="emailLogsFiltersForm" value="{{ $filters['date_to'] }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label small text-muted">Per Page</label><select name="per_page" form="emailLogsFiltersForm" class="form-select">@foreach([10,20,50,100] as $n)<option value="{{ $n }}" @selected($filters['per_page']==$n)>{{ $n }}</option>@endforeach</select></div>
    <div class="col-md-2 d-flex gap-2"><button form="emailLogsFiltersForm" class="btn btn-primary">Apply</button><a href="{{ route('admin.email-logs.index') }}" class="btn btn-outline-secondary">Reset</a></div>
</div></div></div>
<div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0 align-middle"><thead class="table-light"><tr>
    <th>ID</th><th><a href="{{ $sortUrl('recipient_email') }}">Recipient Email</a></th><th>Recipient Name</th><th><a href="{{ $sortUrl('subject') }}">Subject</a></th><th>Template Key</th><th>Source Module</th><th><a href="{{ $sortUrl('status') }}">Status</a></th><th><a href="{{ $sortUrl('sent_at') }}">Sent At</a></th><th><a href="{{ $sortUrl('created_at') }}">Created At</a></th><th>Triggered By</th><th>Triggered User</th><th>Mail Provider</th><th>Error Message</th><th class="text-end">Actions</th>
</tr></thead><tbody>
@forelse($emailLogs as $emailLog)<tr>
    <td><code>{{ Str::limit($emailLog->id, 8, '') }}</code></td><td>{{ $emailLog->to_email }}</td><td>{{ $emailLog->to_name ?: optional($emailLog->user)->display_name ?: '—' }}</td><td style="min-width:220px">{{ $emailLog->subject ?: '—' }}</td><td>{{ $emailLog->template_key ?: '—' }}</td><td>{{ $emailLog->source_module ?: '—' }}</td><td><span class="badge {{ $statusBadgeClass((string)$emailLog->status) }}">{{ ucfirst((string)$emailLog->status) }}</span></td><td>{{ optional($emailLog->sent_at)->format('Y-m-d H:i:s') ?? '—' }}</td><td>{{ optional($emailLog->created_at)->format('Y-m-d H:i:s') ?? '—' }}</td><td>{{ $emailLog->triggered_by ?: 'System' }}</td><td>{{ $emailLog->trigger_user_name ?: $emailLog->trigger_user_email ?: '—' }}</td><td>{{ $emailLog->mail_provider ?: '—' }}</td><td class="text-truncate" style="max-width:220px">{{ $emailLog->error_message ?: '—' }}</td><td class="text-end"><a href="{{ route('admin.email-logs.show', $emailLog->id) }}" class="btn btn-sm btn-outline-primary">View</a></td>
</tr>@empty<tr><td colspan="14" class="text-center text-muted py-4">No email logs found.</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $emailLogs->links() }}</div>
@endsection
