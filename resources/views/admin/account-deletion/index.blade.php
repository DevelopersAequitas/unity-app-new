@extends('admin.layouts.app')

@section('title', 'Account Deletion Requests')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Account Deletion Requests</h4>
            <p class="text-muted small mb-0">Manage user requests to permanently delete their accounts and data.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <!-- Filter Bar -->
            <form method="GET" action="{{ route('admin.account-deletion.index') }}" class="row g-3 align-items-center">
                <div class="col-auto">
                    <label for="status" class="form-label mb-0 me-2 text-muted small">Filter by Status:</label>
                    <select name="status" id="status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                        <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Requests</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="ongoing" {{ $status === 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                        <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">User</th>
                            <th>Email</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3 bg-secondary-subtle rounded-circle d-flex align-items-center justify-content-center text-secondary" style="width: 38px; height: 38px;">
                                            <i class="bi bi-person fs-5"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0 text-dark">
                                                @if($req->user)
                                                    {{ $req->user->display_name ?? ($req->user->first_name . ' ' . $req->user->last_name) }}
                                                @else
                                                    <span class="text-muted">Deleted User</span>
                                                @endif
                                            </h6>
                                            @if($req->user)
                                                <small class="text-muted">ID: {{ $req->user->id }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($req->user)
                                        <a href="mailto:{{ $req->user->email }}" class="text-decoration-none">{{ $req->user->email }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <div class="text-wrap" style="max-width: 350px;">
                                        {{ $req->reason }}
                                    </div>
                                </td>
                                <td>
                                    @if($req->status === 'pending')
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2.5 py-1.5">Pending</span>
                                    @elseif($req->status === 'ongoing')
                                        <span class="badge bg-info-subtle text-info border border-info-subtle px-2.5 py-1.5">Ongoing</span>
                                    @elseif($req->status === 'completed')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5">Completed</span>
                                    @elseif($req->status === 'approved')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5">Approved</span>
                                    @elseif($req->status === 'rejected')
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5">Rejected</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1.5">{{ ucfirst($req->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-muted small">
                                    {{ $req->created_at->format('Y-m-d H:i') }}
                                    <br>
                                    {{ $req->created_at->diffForHumans() }}
                                </td>
                                <td class="text-end pe-4">
                                    <form action="{{ route('admin.account-deletion.update-status', $req->id) }}" method="POST" class="d-inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                            <option value="pending" {{ $req->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                            <option value="ongoing" {{ $req->status === 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                                            <option value="completed" {{ $req->status === 'completed' ? 'selected' : '' }}>Completed</option>
                                            @if(!in_array($req->status, ['pending', 'ongoing', 'completed']))
                                                <option value="{{ $req->status }}" selected>{{ ucfirst($req->status) }}</option>
                                            @endif
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                    No account deletion requests found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($requests->hasPages())
                <div class="card-footer bg-white border-top py-3">
                    {{ $requests->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
