@extends('admin.layouts.app')

@section('title', 'Conclaves & Events - Peers Global Web')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-indigo-subtle text-indigo rounded-pill px-2.5 py-1" style="background: rgba(99, 102, 241, 0.12); color: #6366f1; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-calendar-event me-1"></i> Peers Global Website
                </span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Conclaves, Summits & Events</h1>
            <p class="text-muted small mb-0 mt-0.5">Flagship national conclaves, chapter meetings, and leadership summits</p>
        </div>
    </div>

    <div class="card border-0 shadow-xs rounded-4 p-4 bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.84rem;">
                <thead class="table-light text-uppercase tracking-wider text-muted" style="font-size: 0.7rem;">
                    <tr>
                        <th>Event Name</th>
                        <th>Date & Time</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $event)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $event->title }}</div>
                                <small class="text-muted">{{ $event->subtitle ?? 'Peers Global Conclave' }}</small>
                            </td>
                            <td class="text-muted">{{ $event->event_date ? $event->event_date->format('M d, Y h:i A') : 'TBA' }}</td>
                            <td class="text-muted">{{ $event->location ?? 'Virtual / Hybrid' }}</td>
                            <td><span class="badge bg-light text-secondary border">{{ $event->type ?? 'Conclave' }}</span></td>
                            <td>
                                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(16, 185, 129, 0.12); color: #059669;">{{ $event->status ?? 'Active' }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.events.show', $event->id) }}" class="btn btn-sm btn-light border fw-semibold">
                                    Manage
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No events found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
