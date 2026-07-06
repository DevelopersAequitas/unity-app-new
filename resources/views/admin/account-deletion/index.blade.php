@extends('admin.layouts.app')

@section('title', 'Account Deletion Requests')

@section('content')
<div class="container-fluid">

    {{-- ── Page Header ───────────────────────────────────────────── --}}
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h4 class="mb-1 fw-bold">
                <i class="bi bi-person-dash-fill text-danger me-2"></i>Account Deletion Requests
            </h4>
            <p class="text-muted small mb-0">Review and manage user requests to delete or deactivate their accounts.</p>
        </div>
        <div class="text-end">
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fs-6">
                <i class="bi bi-shield-exclamation me-1"></i>
                {{ $requests->total() }} Request{{ $requests->total() !== 1 ? 's' : '' }}
            </span>
        </div>
    </div>

    {{-- ── Flash Messages ─────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill fs-5 flex-shrink-0"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- ── Filter Bar ──────────────────────────────────────────────── --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('admin.account-deletion.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-semibold mb-1">Filter by Status</label>
                    <select name="status" id="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="all"     {{ $status === 'all'     ? 'selected' : '' }}>All Requests</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="ongoing" {{ $status === 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                        <option value="approved"{{ $status === 'approved'? 'selected' : '' }}>Approved</option>
                        <option value="rejected"{{ $status === 'rejected'? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-auto">
                    <a href="{{ route('admin.account-deletion.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Requests Table ───────────────────────────────────────────── --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width: 900px;">
                    <thead style="background: #f8f9fa; border-bottom: 2px solid #e9ecef;">
                        <tr>
                            <th class="ps-4 py-3 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em;">User</th>
                            <th class="py-3 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em;">Email</th>
                            <th class="py-3 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em;">Reason</th>
                            <th class="py-3 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em;">Request Status</th>
                            <th class="py-3 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em;">Submitted</th>
                            <th class="py-3 pe-4 text-end text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            @php
                                // linked_user is resolved by the controller: user_id FK first, then email fallback
                                $linkedUser        = $req->linked_user ?? null;
                                $userIsDeactivated = $linkedUser && $linkedUser->trashed();
                                $userIsActive      = $linkedUser && !$linkedUser->trashed();

                                // Build avatar initials
                                if ($linkedUser) {
                                    $nameParts = explode(' ', trim($linkedUser->display_name ?? ($linkedUser->first_name . ' ' . $linkedUser->last_name)));
                                    $initials  = strtoupper(substr($nameParts[0] ?? 'U', 0, 1) . substr($nameParts[1] ?? '', 0, 1));
                                } else {
                                    $initials = '?';
                                }

                                // Status badge classes
                                $statusClass = match($req->status) {
                                    'pending'  => 'bg-warning-subtle text-warning border border-warning-subtle',
                                    'ongoing'  => 'bg-info-subtle text-info border border-info-subtle',
                                    'approved' => 'bg-success-subtle text-success border border-success-subtle',
                                    'rejected' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                    default    => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                                };
                            @endphp
                            <tr style="border-bottom: 1px solid #f1f3f5;">
                                {{-- User column --}}
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        {{-- Avatar with initials --}}
                                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                                             style="width:40px;height:40px;font-size:.8rem;
                                                    background:{{ $userIsDeactivated ? '#adb5bd' : ($linkedUser ? '#4361ee' : '#dee2e6') }};">
                                            {{ $initials }}
                                        </div>
                                        <div>
                                            @if($linkedUser)
                                                <div class="fw-semibold text-dark" style="font-size:.875rem;">
                                                    {{ $linkedUser->display_name ?? trim($linkedUser->first_name . ' ' . $linkedUser->last_name) }}
                                                </div>
                                                <div class="text-muted" style="font-size:.75rem;">
                                                    @if($userIsDeactivated)
                                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size:.65rem;">
                                                            <i class="bi bi-person-slash me-1"></i>Deactivated
                                                        </span>
                                                    @else
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.65rem;">
                                                            <i class="bi bi-person-check me-1"></i>Active
                                                        </span>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="text-muted fw-medium" style="font-size:.875rem;">
                                                    {{ $req->email ?? 'Unknown User' }}
                                                </div>
                                                <span class="badge bg-light text-secondary border" style="font-size:.65rem;">
                                                    <i class="bi bi-person-x me-1"></i>No Account
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- Email column --}}
                                <td class="py-3" style="font-size:.875rem;">
                                    @if($linkedUser)
                                        <a href="mailto:{{ $linkedUser->email }}" class="text-decoration-none text-primary">
                                            <i class="bi bi-envelope me-1 opacity-50"></i>{{ $linkedUser->email }}
                                        </a>
                                    @elseif($req->email)
                                        <a href="mailto:{{ $req->email }}" class="text-decoration-none text-muted">
                                            <i class="bi bi-envelope me-1 opacity-50"></i>{{ $req->email }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                {{-- Reason column --}}
                                <td class="py-3">
                                    <div class="text-muted text-wrap" style="max-width:280px;font-size:.8rem;line-height:1.5;">
                                        {{ Str::limit($req->reason, 120) }}
                                    </div>
                                </td>

                                {{-- Request status badge --}}
                                <td class="py-3">
                                    <span class="badge {{ $statusClass }} px-2 py-1" style="font-size:.75rem;">
                                        {{ ucfirst($req->status) }}
                                    </span>
                                </td>

                                {{-- Submitted at --}}
                                <td class="py-3">
                                    <div class="text-dark" style="font-size:.8rem;">{{ $req->created_at->format('d M Y') }}</div>
                                    <div class="text-muted" style="font-size:.72rem;">{{ $req->created_at->format('H:i') }} · {{ $req->created_at->diffForHumans() }}</div>
                                </td>

                                {{-- Actions --}}
                                <td class="py-3 pe-4 text-end">
                                    <div class="d-flex align-items-center justify-content-end gap-2">

                                        {{-- Request status dropdown --}}
                                        <form action="{{ route('admin.account-deletion.update-status', $req->id) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status"
                                                    class="form-select form-select-sm"
                                                    style="width:auto;min-width:110px;font-size:.8rem;"
                                                    onchange="this.form.submit()"
                                                    title="Change request status">
                                                <option value="pending" {{ $req->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                                <option value="ongoing" {{ $req->status === 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                                                @if(!in_array($req->status, ['pending', 'ongoing']))
                                                    <option value="{{ $req->status }}" selected>{{ ucfirst($req->status) }}</option>
                                                @endif
                                            </select>
                                        </form>

                                        {{-- Account action button: one only, based on real user status --}}
                                        @if($userIsDeactivated)
                                            <form action="{{ route('admin.account-deletion.activate-account', $req->id) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('Activate this user account?\nThey will become visible in all listings again.')">
                                                @csrf
                                                <button type="submit"
                                                        class="btn btn-success btn-sm d-inline-flex align-items-center gap-1"
                                                        style="white-space:nowrap;font-size:.8rem;"
                                                        title="Activate Account">
                                                    <i class="bi bi-person-check-fill"></i> Activate
                                                </button>
                                            </form>
                                        @elseif($userIsActive)
                                            <form action="{{ route('admin.account-deletion.deactivate-account', $req->id) }}"
                                                  method="POST"
                                                  onsubmit="return confirm('Deactivate this user account?\nThey will be hidden from all listings.\nNo data will be permanently deleted.')">
                                                @csrf
                                                <button type="submit"
                                                        class="btn btn-warning btn-sm d-inline-flex align-items-center gap-1"
                                                        style="white-space:nowrap;font-size:.8rem;"
                                                        title="Deactivate Account">
                                                    <i class="bi bi-person-dash-fill"></i> Deactivate
                                                </button>
                                            </form>
                                        @else
                                            <span class="badge bg-light text-secondary border px-2 py-2" style="font-size:.72rem;" title="No user account linked to this request">
                                                <i class="bi bi-dash-circle me-1"></i>No Account
                                            </span>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="py-4">
                                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3"
                                             style="width:64px;height:64px;">
                                            <i class="bi bi-inbox fs-2 text-secondary"></i>
                                        </div>
                                        <h6 class="text-muted fw-semibold">No requests found</h6>
                                        <p class="text-muted small mb-0">
                                            @if($status !== 'all')
                                                No <strong>{{ $status }}</strong> requests at this time.
                                                <a href="{{ route('admin.account-deletion.index') }}" class="ms-1">View all</a>
                                            @else
                                                No account deletion requests have been submitted yet.
                                            @endif
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($requests->hasPages())
                <div class="px-4 py-3 border-top bg-white">
                    {{ $requests->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
