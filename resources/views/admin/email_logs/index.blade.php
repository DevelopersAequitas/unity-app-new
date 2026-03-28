@extends('admin.layouts.app')

@section('title', 'Email Logs')

@section('content')
    @php
        $statusBadgeClass = static function (string $status): string {
            return match ($status) {
                'sent' => 'bg-success-subtle text-success border border-success-subtle',
                'failed' => 'bg-danger-subtle text-danger border border-danger-subtle',
                'pending' => 'bg-warning-subtle text-warning border border-warning-subtle',
                default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            };
        };
    @endphp

    <form id="emailLogsFiltersForm" method="GET" action="{{ route('admin.email-logs.index') }}"></form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Email Logs</h1>
        <span class="badge bg-light text-dark border">Total: {{ number_format($emailLogs->total()) }}</span>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" name="search" form="emailLogsFiltersForm" value="{{ $filters['search'] }}" class="form-control" placeholder="Search email or subject">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Template Key</label>
                    <select name="template_key" form="emailLogsFiltersForm" class="form-select">
                        <option value="">All</option>
                        @foreach ($templateKeys as $templateKeyOption)
                            <option value="{{ $templateKeyOption }}" @selected($filters['template_key'] === $templateKeyOption)>{{ $templateKeyOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Status</label>
                    <select name="status" form="emailLogsFiltersForm" class="form-select">
                        <option value="all" @selected($filters['status'] === 'all')>All</option>
                        <option value="sent" @selected($filters['status'] === 'sent')>Sent</option>
                        <option value="failed" @selected($filters['status'] === 'failed')>Failed</option>
                        <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">From</label>
                    <input type="date" name="date_from" form="emailLogsFiltersForm" value="{{ $filters['date_from'] }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">To</label>
                    <input type="date" name="date_to" form="emailLogsFiltersForm" value="{{ $filters['date_to'] }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Per Page</label>
                    <select name="per_page" form="emailLogsFiltersForm" class="form-select">
                        @foreach ([10, 20, 50, 100] as $perPageOption)
                            <option value="{{ $perPageOption }}" @selected($filters['per_page'] == $perPageOption)>{{ $perPageOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" form="emailLogsFiltersForm" class="btn btn-primary">Apply</button>
                    <a href="{{ route('admin.email-logs.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Sent At</th>
                    <th>To Email</th>
                    <th>Subject</th>
                    <th>Template Key</th>
                    <th>Source Module</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($emailLogs as $emailLog)
                    <tr>
                        <td>{{ optional($emailLog->sent_at)->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td>{{ $emailLog->to_email }}</td>
                        <td class="text-wrap" style="max-width: 280px;">{{ $emailLog->subject ?: '—' }}</td>
                        <td>{{ $emailLog->template_key ?: '—' }}</td>
                        <td>{{ $emailLog->source_module ?: '—' }}</td>
                        <td>
                            <span class="badge {{ $statusBadgeClass((string) $emailLog->status) }}">{{ ucfirst((string) $emailLog->status) }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.email-logs.show', $emailLog->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">No email logs found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $emailLogs->links() }}</div>
@endsection
