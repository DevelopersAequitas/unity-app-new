@extends('admin.layouts.app')

@section('title', 'Authorized Scanners - ' . $event->title)

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Authorized Scanners - {{ $event->title }}</h1>
            <div class="text-muted small">Manage UnityEventScan app access for this event.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.events.show', $event->id) }}" class="btn btn-outline-primary">View Event</a>
            <a href="{{ route('admin.events.index') }}" class="btn btn-outline-secondary">Back to Events</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><strong>Event:</strong> {{ $event->title }}</div>
                <div class="col-md-3"><strong>Date:</strong> {{ optional($event->start_at)->format('d M Y h:i A') ?? '-' }}</div>
                <div class="col-md-5"><strong>Location:</strong> {{ $event->location_text ?? '-' }}</div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">Add Scanner</div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.events.scanners.index', $event->id) }}" class="row g-2 align-items-end mb-3">
                <div class="col-md-8">
                    <label class="form-label">Search peer/user</label>
                    <input class="form-control" name="search" value="{{ $search }}" placeholder="Search by name, email, phone, or company">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-outline-primary w-100">Search</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.events.scanners.store', $event->id) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-8">
                    <label class="form-label">Search/select peer/user dropdown</label>
                    <select class="form-select" name="scanner_user_id" required>
                        <option value="">Select user</option>
                        @foreach($scannerCandidates as $candidate)
                            <option value="{{ $candidate->id }}" @selected(old('scanner_user_id') === $candidate->id)>
                                {{ $candidate->display_name }} — {{ $candidate->email }} @if($candidate->company_name)({{ $candidate->company_name }})@endif
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">If a scanner was previously revoked, adding them again reactivates the same authorization.</div>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100">Add Scanner</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold">Current Scanner List</div>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead><tr><th>Name</th><th>Email</th><th>Company</th><th>Status</th><th>Assigned At</th><th>Revoked At</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($scannerAuthorizations as $authorization)
                    <tr>
                        <td>{{ $authorization->scanner?->display_name ?? '-' }}</td>
                        <td>{{ $authorization->scanner?->email ?? '-' }}</td>
                        <td>{{ $authorization->scanner?->company_name ?? '-' }}</td>
                        <td><span class="badge {{ $authorization->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $authorization->status }}</span></td>
                        <td>{{ optional($authorization->assigned_at)->format('d M Y h:i A') ?? '-' }}</td>
                        <td>{{ optional($authorization->revoked_at)->format('d M Y h:i A') ?? '-' }}</td>
                        <td>
                            @if($authorization->status === 'active')
                                <form method="POST" action="{{ route('admin.events.scanners.destroy', [$event->id, $authorization->scanner_user_id]) }}" onsubmit="return confirm('Revoke scanner access for this event?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Revoke</button>
                                </form>
                            @else
                                <span class="text-muted small">No action</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No scanners authorized for this event.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
