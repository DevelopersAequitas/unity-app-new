@extends('admin.layouts.app')

@section('title', 'Introduction Requests')

@section('content')
    <form id="introRequestsFiltersForm" method="GET" action="{{ route('admin.introduction-requests.index') }}"></form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Introduction Requests</h1>
        <span class="badge bg-light text-dark border">Pending: {{ number_format($introductionRequests->total()) }}</span>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" name="search" form="introRequestsFiltersForm"
                           value="{{ $filters['search'] ?? '' }}"
                           class="form-control" placeholder="Search requester or introducer name, email, company">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" form="introRequestsFiltersForm" class="btn btn-primary">Apply</button>
                    <a href="{{ route('admin.introduction-requests.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-premium mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Requester</th>
                        <th>Requested Introducer</th>
                        <th>Requested At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($introductionRequests as $introRequest)
                    @php
                        $requester  = $introRequest->requester;
                        $introducer = $introRequest->introducer;
                    @endphp
                    <tr>
                        <td>
                            @if ($requester)
                                @include('admin.partials.peer_identity', ['user' => $requester])
                                <div class="small text-muted mt-1">{{ $requester->email ?? '—' }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($introducer)
                                @include('admin.partials.peer_identity', ['user' => $introducer])
                                <div class="small text-muted mt-1">{{ $introducer->email ?? '—' }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="small text-muted">
                                {{ $introRequest->requested_at?->format('d M Y, h:i A') ?? '—' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <form method="POST"
                                  action="{{ route('admin.introduction-requests.approve', $introRequest->id) }}"
                                  class="d-inline">
                                @csrf
                                <button type="submit"
                                        class="btn btn-sm btn-success"
                                        onclick="return confirm('Approve this introduction request?')">
                                    Approve
                                </button>
                            </form>

                            {{-- Reject modal trigger --}}
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal"
                                    data-bs-target="#rejectModal{{ $introRequest->id }}">
                                Reject
                            </button>

                            {{-- Reject Modal --}}
                            <div class="modal fade" id="rejectModal{{ $introRequest->id }}" tabindex="-1"
                                 aria-labelledby="rejectModalLabel{{ $introRequest->id }}" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST"
                                              action="{{ route('admin.introduction-requests.reject', $introRequest->id) }}">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="rejectModalLabel{{ $introRequest->id }}">
                                                    Reject Introduction Request
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="text-muted small mb-3">
                                                    Rejecting will not change the requester's introducer assignment.
                                                </p>
                                                <div class="mb-3">
                                                    <label for="admin_note_{{ $introRequest->id }}" class="form-label">
                                                        Admin Note <span class="text-muted">(optional)</span>
                                                    </label>
                                                    <textarea id="admin_note_{{ $introRequest->id }}"
                                                              name="admin_note"
                                                              class="form-control"
                                                              rows="3"
                                                              maxlength="1000"
                                                              placeholder="Reason for rejection…"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Reject</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No pending introduction requests.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $introductionRequests->links() }}</div>
@endsection
