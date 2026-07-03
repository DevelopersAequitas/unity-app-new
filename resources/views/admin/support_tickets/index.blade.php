@extends('admin.layouts.app')

@section('title', 'Support Tickets')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Support Tickets</h4>
            <p class="text-muted small mb-0">Manage customer queries, issues, and support requests.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Filters and Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.support-tickets.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="search" class="form-label text-muted small fw-semibold">Search</label>
                    <input type="text" name="search" id="search" class="form-control form-control-sm" placeholder="Ticket #, name, email, subject..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label text-muted small fw-semibold">Status</label>
                    <select name="status" id="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="all" {{ request('status') === 'all' || !request('status') ? 'selected' : '' }}>All Statuses</option>
                        <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                        <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="priority" class="form-label text-muted small fw-semibold">Priority</label>
                    <select name="priority" id="priority" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="all" {{ request('priority') === 'all' || !request('priority') ? 'selected' : '' }}>All Priorities</option>
                        <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                        <option value="normal" {{ request('priority') === 'normal' ? 'selected' : '' }}>Normal</option>
                        <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                        <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <a href="{{ route('admin.support-tickets.index') }}" class="btn btn-sm btn-outline-secondary w-100">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tickets Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Ticket Number</th>
                            <th>Contact Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Submitted At</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                            @php
                                $statusBadge = match($ticket->status) {
                                    'open' => 'bg-info-subtle text-info border border-info-subtle',
                                    'in_progress' => 'bg-warning-subtle text-warning border border-warning-subtle',
                                    'resolved' => 'bg-success-subtle text-success border border-success-subtle',
                                    'closed' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                                    default => 'bg-light text-dark'
                                };
                                $priorityBadge = match($ticket->priority) {
                                    'low' => 'bg-light text-secondary border',
                                    'normal' => 'bg-info-subtle text-info border border-info-subtle',
                                    'high' => 'bg-warning-subtle text-warning border border-warning-subtle',
                                    'urgent' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                    default => 'bg-light text-dark'
                                };
                            @endphp
                            <tr>
                                <td class="ps-4 fw-semibold text-primary">#{{ $ticket->ticket_number }}</td>
                                <td>{{ $ticket->contact_name }}</td>
                                <td><a href="mailto:{{ $ticket->email }}" class="text-decoration-none">{{ $ticket->email }}</a></td>
                                <td>
                                    <div class="text-wrap" style="max-width: 250px;">
                                        {{ $ticket->subject }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $statusBadge }} px-2 py-1">{{ ucwords(str_replace('_', ' ', $ticket->status)) }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $priorityBadge }} px-2 py-1">{{ ucfirst($ticket->priority) }}</span>
                                </td>
                                <td class="text-muted small">
                                    {{ $ticket->created_at->format('Y-m-d H:i') }}
                                    <br>
                                    <span class="text-muted" style="font-size: 11px;">{{ $ticket->created_at->diffForHumans() }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('admin.support-tickets.show', $ticket->id) }}" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center">
                                        <i class="bi bi-eye me-1"></i> View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-ticket fs-1 d-block mb-2 text-secondary"></i>
                                    No support tickets found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($tickets->hasPages())
                <div class="card-footer bg-white border-top py-3">
                    {{ $tickets->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
