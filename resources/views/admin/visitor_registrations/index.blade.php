@extends('admin.layouts.app')

@section('title', 'Visitor Registrations')

@section('content')
    @php
        $displayName = function (?string $display, ?string $first, ?string $last, ?string $name = null): string {
            if (! empty($name)) {
                return $name;
            }
            if ($display) {
                return $display;
            }
            $computed = trim(($first ?? '') . ' ' . ($last ?? ''));
            return $computed !== '' ? $computed : '—';
        };

        $formatDate = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : '—';
        };

        $formatDateTime = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : '—';
        };
    @endphp

    <form id="visitorRegistrationsFiltersForm" method="GET" action="{{ route('admin.visitor-registrations.index') }}"></form>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="h4 mb-0">Visitor Registrations</h1>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#importVisitorModal">
                <i class="bi bi-upload me-1"></i> Import Bulk CSV
            </button>
            <a href="{{ route('admin.visitor-registrations.export', request()->query()) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-download me-1"></i> Export Bulk CSV
            </a>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addVisitorModal">
                <i class="bi bi-plus-lg me-1"></i> Add Visitor
            </button>
            <span class="badge bg-light text-dark border ms-1">Total: {{ number_format($registrations->total()) }}</span>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" name="search" form="visitorRegistrationsFiltersForm" value="{{ $filters['search'] }}" class="form-control" placeholder="Search visitor/peer/event">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Status</label>
                    <select name="status" form="visitorRegistrationsFiltersForm" class="form-select">
                        <option value="all" @selected($filters['status'] === 'all')>All</option>
                        <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                        <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                        <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Circle</label>
                    <select name="circle_id" form="visitorRegistrationsFiltersForm" class="form-select">
                        <option value="all">All Circles</option>
                        @foreach($circles as $circle)
                            <option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? 'all') == $circle->id)>{{ $circle->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex flex-column gap-2">
                    <button type="submit" form="visitorRegistrationsFiltersForm" class="btn btn-primary">Apply</button>
                    <a href="{{ route('admin.visitor-registrations.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Submitted At</th>
                        <th>Peer Name</th>
                        <th>Peer Phone</th>
                        <th>Event Type</th>
                        <th>Event Name</th>
                        <th>Event Date</th>
                        <th>Visitor Name</th>
                        <th>Visitor Mobile</th>
                        <th>Visitor City</th>
                        <th>Visitor Business</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    <tr>
                        <th></th>
                        <th>
                            <input type="text" name="peer_q" form="visitorRegistrationsFiltersForm" class="form-control form-control-sm" placeholder="Peer/Company/City" value="{{ $filters['peer_q'] }}">
                        </th>
                        <th>
                            <input type="text" name="peer_phone" form="visitorRegistrationsFiltersForm" class="form-control form-control-sm" placeholder="Peer Phone" value="{{ $filters['peer_phone'] }}">
                        </th>
                        <th>
                            <input type="text" name="event_type" form="visitorRegistrationsFiltersForm" class="form-control form-control-sm" placeholder="Event Type" value="{{ $filters['event_type'] }}">
                        </th>
                        <th>
                            <input type="text" name="event_name" form="visitorRegistrationsFiltersForm" class="form-control form-control-sm" placeholder="Event Name" value="{{ $filters['event_name'] }}">
                        </th>
                        <th>
                            <input type="date" name="event_date" form="visitorRegistrationsFiltersForm" class="form-control form-control-sm" value="{{ $filters['event_date'] }}">
                        </th>
                        <th>
                            <input type="text" name="visitor_name" form="visitorRegistrationsFiltersForm" class="form-control form-control-sm" placeholder="Visitor Name" value="{{ $filters['visitor_name'] }}">
                        </th>
                        <th>
                            <input type="text" name="visitor_mobile" form="visitorRegistrationsFiltersForm" class="form-control form-control-sm" placeholder="Visitor Mobile" value="{{ $filters['visitor_mobile'] }}">
                        </th>
                        <th>
                            <input type="text" name="visitor_city" form="visitorRegistrationsFiltersForm" class="form-control form-control-sm" placeholder="Visitor City" value="{{ $filters['visitor_city'] }}">
                        </th>
                        <th>
                            <input type="text" name="visitor_business" form="visitorRegistrationsFiltersForm" class="form-control form-control-sm" placeholder="Visitor Business" value="{{ $filters['visitor_business'] }}">
                        </th>
                        <th>
                            <select name="status" form="visitorRegistrationsFiltersForm" class="form-select form-select-sm">
                                <option value="all" @selected($filters['status'] === 'all')>All</option>
                                <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                                <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                                <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                            </select>
                        </th>
                        <th class="text-end">
                            <div class="d-inline-flex align-items-center gap-2" style="white-space:nowrap;">
                                <button type="submit" form="visitorRegistrationsFiltersForm" class="btn btn-sm btn-primary">Apply</button>
                                <a href="{{ route('admin.visitor-registrations.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registrations as $registration)
                        @php
                            $member = $registration->user;
                            $memberName = $displayName($member->display_name ?? null, $member->first_name ?? null, $member->last_name ?? null, $member->name ?? null);
                            $memberCompany = $member->company_name ?? $member->company ?? $member->business_name ?? 'No Company';
                            $memberCity = $member->city ?? 'No City';
                            $memberCircle = optional($member?->circleMembers?->first()?->circle)->name ?? 'No Circle';
                        @endphp
                        <tr>
                            <td>{{ $formatDateTime($registration->created_at ?? null) }}</td>
                            <td>
                                <div class="d-flex flex-column">
                                    <div class="fw-semibold">{{ $memberName }}</div>
                                    <div class="text-muted small">{{ $memberCompany }}</div>
                                    <div class="text-muted small">{{ $memberCity }}</div>
                                    <div class="text-muted small">{{ $memberCircle }}</div>
                                </div>
                            </td>
                            <td>{{ $member->phone ?? '—' }}</td>
                            <td>{{ ucfirst($registration->event_type ?? '—') }}</td>
                            <td>{{ $registration->event_name ?? '—' }}</td>
                            <td>{{ $formatDate($registration->event_date ?? null) }}</td>
                            <td>{{ $registration->visitor_full_name ?? '—' }}</td>
                            <td>{{ $registration->visitor_mobile ?? '—' }}</td>
                            <td>{{ $registration->visitor_city ?? '—' }}</td>
                            <td>{{ $registration->visitor_business ?? '—' }}</td>
                            <td>{{ ucfirst($registration->status ?? '—') }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1 justify-content-end align-items-center">
                                    <a href="{{ route('admin.visitor-registrations.export-single', $registration->id) }}" class="btn btn-sm btn-outline-primary" title="Export Single CSV">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    @if ($registration->status === 'pending')
                                        <form method="POST" action="{{ route('admin.visitor-registrations.approve', $registration->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this visitor registration?')">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.visitor-registrations.reject', $registration->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this visitor registration?')">Reject</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center text-muted">No visitor registrations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $registrations->links() }}
    </div>

    <!-- Add Visitor Modal -->
    <div class="modal fade" id="addVisitorModal" tabindex="-1" aria-labelledby="addVisitorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content shadow border-0 rounded-4">
                <form action="{{ route('admin.visitor-registrations.store') }}" method="POST">
                    @csrf
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-dark" id="addVisitorModalLabel">
                            <i class="bi bi-person-plus-fill me-2 text-primary"></i> Add Visitor
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-3">
                        <div class="row g-3">
                            <!-- Peer selection (MANDATORY) -->
                            <div class="col-md-12">
                                <label for="user_id" class="form-label fw-medium small text-muted">Peer (Who invited this visitor?) <span class="text-danger">*</span></label>
                                <select name="user_id" id="user_id" class="form-select" required>
                                    <option value="">Select Peer...</option>
                                    @foreach($users as $user)
                                        @php
                                            $uName = $displayName($user->display_name ?? null, $user->first_name ?? null, $user->last_name ?? null, $user->name ?? null);
                                            $uCompany = $user->company_name ?? $user->company ?? 'No Company';
                                        @endphp
                                        <option value="{{ $user->id }}" @selected(old('user_id') === $user->id)>
                                            {{ $uName }} ({{ $uCompany }} - {{ $user->phone }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Visitor Name, Mobile, Email (MANDATORY) -->
                            <div class="col-md-4">
                                <label for="visitor_full_name" class="form-label fw-medium small text-muted">Visitor Name <span class="text-danger">*</span></label>
                                <input type="text" name="visitor_full_name" id="visitor_full_name" value="{{ old('visitor_full_name') }}" class="form-control" placeholder="Visitor Name" required>
                            </div>
                            <div class="col-md-4">
                                <label for="visitor_mobile" class="form-label fw-medium small text-muted">Visitor Mobile <span class="text-danger">*</span></label>
                                <input type="text" name="visitor_mobile" id="visitor_mobile" value="{{ old('visitor_mobile') }}" class="form-control" placeholder="Visitor Mobile" required>
                            </div>
                            <div class="col-md-4">
                                <label for="visitor_email" class="form-label fw-medium small text-muted">Visitor Email <span class="text-danger">*</span></label>
                                <input type="email" name="visitor_email" id="visitor_email" value="{{ old('visitor_email') }}" class="form-control" placeholder="Visitor Email" required>
                            </div>

                            <!-- Event Details (OPTIONAL) -->
                            <div class="col-md-4">
                                <label for="event_type" class="form-label fw-medium small text-muted">Event Type</label>
                                <select name="event_type" id="event_type" class="form-select">
                                    <option value="Physical" @selected(old('event_type', 'Physical') === 'Physical')>Physical</option>
                                    <option value="Virtual" @selected(old('event_type') === 'Virtual')>Virtual</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="event_name" class="form-label fw-medium small text-muted">Event Name</label>
                                <input type="text" name="event_name" id="event_name" value="{{ old('event_name') }}" class="form-control" placeholder="e.g. buildcon circle meet">
                            </div>
                            <div class="col-md-4">
                                <label for="event_date" class="form-label fw-medium small text-muted">Event Date</label>
                                <input type="date" name="event_date" id="event_date" value="{{ old('event_date', now()->format('Y-m-d')) }}" class="form-control">
                            </div>

                            <!-- Other Details (OPTIONAL) -->
                            <div class="col-md-6">
                                <label for="visitor_city" class="form-label fw-medium small text-muted">Visitor City</label>
                                <input type="text" name="visitor_city" id="visitor_city" value="{{ old('visitor_city') }}" class="form-control" placeholder="Visitor City">
                            </div>
                            <div class="col-md-6">
                                <label for="visitor_business" class="form-label fw-medium small text-muted">Visitor Business</label>
                                <input type="text" name="visitor_business" id="visitor_business" value="{{ old('visitor_business') }}" class="form-control" placeholder="e.g. interior designer">
                            </div>
                            <div class="col-md-6">
                                <label for="visitor_designation" class="form-label fw-medium small text-muted">Visitor Designation</label>
                                <input type="text" name="visitor_designation" id="visitor_designation" value="{{ old('visitor_designation') }}" class="form-control" placeholder="Designation">
                            </div>
                            <div class="col-md-12">
                                <label for="visitor_business_brief" class="form-label fw-medium small text-muted">Visitor Business Brief</label>
                                <textarea name="visitor_business_brief" id="visitor_business_brief" class="form-control" rows="2" placeholder="Brief description of visitor's business">{{ old('visitor_business_brief') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Visitor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Visitor Modal -->
    <div class="modal fade" id="importVisitorModal" tabindex="-1" aria-labelledby="importVisitorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content shadow border-0 rounded-4">
                <form action="{{ route('admin.visitor-registrations.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-dark" id="importVisitorModalLabel">
                            <i class="bi bi-file-earmark-spreadsheet-fill me-2 text-success"></i> Import Visitors in Bulk
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="csv_file" class="form-label fw-medium small text-muted">Select CSV File <span class="text-danger">*</span></label>
                                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv, text/csv, text/plain" required>
                                <div class="form-text mt-1">
                                    Upload a CSV file containing visitor records. 
                                    <a href="{{ route('admin.visitor-registrations.sample-csv') }}" class="fw-semibold text-primary text-decoration-none ms-1">
                                        <i class="bi bi-download me-0.5"></i> Download Sample CSV
                                    </a>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="default_user_id" class="form-label fw-medium small text-muted">Default Peer (Inviter)</label>
                                <select name="default_user_id" id="default_user_id" class="form-select">
                                    <option value="">Auto-resolve from CSV (peer_email or peer_phone)...</option>
                                    @foreach($users as $user)
                                        @php
                                            $uName = $displayName($user->display_name ?? null, $user->first_name ?? null, $user->last_name ?? null, $user->name ?? null);
                                            $uCompany = $user->company_name ?? $user->company ?? 'No Company';
                                        @endphp
                                        <option value="{{ $user->id }}">
                                            {{ $uName }} ({{ $uCompany }} - {{ $user->phone }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Used if a row lacks `peer_email` or `peer_phone`.</div>
                            </div>
                        </div>

                        <!-- Column Requirements -->
                        <div class="bg-light p-3 rounded-3 border mt-3">
                            <div class="fw-semibold text-dark mb-1 small"><i class="bi bi-info-circle me-1 text-primary"></i> Mandatory Columns in CSV:</div>
                            <ul class="mb-3 text-muted small ps-3">
                                <li><strong>visitor_name</strong> (or visitor_full_name)</li>
                                <li><strong>visitor_mobile</strong> (or mobile)</li>
                                <li><strong>visitor_email</strong> (or email)</li>
                                <li><strong>peer_email</strong> or <strong>peer_phone</strong> (or select Default Peer above)</li>
                            </ul>

                            <div class="fw-semibold text-dark mb-2 small"><i class="bi bi-table me-1 text-success"></i> CSV Template Structure Preview:</div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm fs-8 text-muted mb-0 bg-white" style="font-size: 0.75rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>peer_email <span class="text-danger">*</span></th>
                                            <th>visitor_name <span class="text-danger">*</span></th>
                                            <th>visitor_mobile <span class="text-danger">*</span></th>
                                            <th>visitor_email <span class="text-danger">*</span></th>
                                            <th>event_type</th>
                                            <th>event_name</th>
                                            <th>event_date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>peer@example.com</td>
                                            <td>Arpan Pandya</td>
                                            <td>9876543210</td>
                                            <td>arpan@example.com</td>
                                            <td>Physical</td>
                                            <td>Buildcon Meeting</td>
                                            <td>{{ now()->format('Y-m-d') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-upload me-1"></i> Upload & Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
