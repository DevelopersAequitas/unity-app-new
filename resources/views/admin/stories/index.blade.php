@extends('admin.layouts.app')

@section('title', 'Story Submissions')

@section('content')
    @php
        $statusBadgeClass = static function (?string $status): string {
            return match (strtolower((string) $status)) {
                'approved' => 'bg-success-subtle text-success border border-success-subtle',
                'rejected' => 'bg-danger-subtle text-danger border border-danger-subtle',
                'new', 'pending' => 'bg-warning-subtle text-warning border border-warning-subtle',
                default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            };
        };

        $formatLabel = static fn (string $value): string => str($value)->replace('_', ' ')->title()->toString();
        $formatDate = static fn ($value): string => $value ? $value->format('d M Y, h:i A') : '—';
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h1 class="h4 mb-1">Story Submissions</h1>
            <div class="text-muted small">Review and manage SME & Business story submissions.</div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <div class="fw-semibold mb-1">Please fix the following:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.stories.index') }}" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small text-muted">Status</label>
                    <select name="status" class="form-select form-select-sm js-no-searchable-select">
                        <option value="all">All</option>
                        @foreach (['pending' => 'Pending/New', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">From Date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm" value="{{ $filters['from_date'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">To Date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm" value="{{ $filters['to_date'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="Title, author, or business">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">Apply</button>
                    <button type="submit" name="export" value="csv" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Export</button>
                    <a href="{{ route('admin.stories.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card-activities-wrapper">
        <div class="table-responsive">
            <table class="table table-premium mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Author</th>
                        <th>Title</th>
                        <th>Business Name</th>
                        <th>Status</th>
                        <th>Submission Date</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>
                                @if ($item->user)
                                    <a href="{{ route('admin.users.show', $item->user->id) }}" class="fw-semibold">{{ $item->user->display_name ?: trim($item->user->first_name . ' ' . $item->user->last_name) }}</a>
                                @else
                                    <span class="text-dark">{{ $item->full_name }}</span>
                                    <span class="badge bg-light text-muted border ms-1">Guest</span>
                                @endif
                            </td>
                            <td>{{ $item->title ?: ($item->business_name ? 'Story of ' . $item->business_name : '—') }}</td>
                            <td>{{ $item->business_name ?: '—' }}</td>
                            <td><span class="badge {{ $statusBadgeClass($item->status) }}">{{ $formatLabel($item->status) }}</span></td>
                            <td>{{ $formatDate($item->submitted_at ?: $item->created_at) }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewStory{{ $item->id }}">View</button>
                                    @if (in_array(strtolower($item->status), ['new', 'pending', 'in_review']))
                                        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#approveStory{{ $item->id }}">Approve</button>
                                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectStory{{ $item->id }}">Reject</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No story submissions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $items->links() }}
    </div>

    @foreach ($items as $item)
        <!-- Details Modal -->
        <div class="modal fade" id="viewStory{{ $item->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-dark"><i class="bi bi-journal-text text-primary me-2"></i>Story Submission Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-start">
                        <h6 class="border-bottom pb-2 mb-3 text-dark fw-bold">Author Information</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="small text-muted">Author Name</div>
                                <div class="fw-semibold">
                                    @if ($item->user)
                                        <a href="{{ route('admin.users.show', $item->user->id) }}">{{ $item->user->display_name ?: trim($item->user->first_name . ' ' . $item->user->last_name) }}</a>
                                    @else
                                        {{ $item->full_name }}
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="small text-muted">Email</div>
                                <div>{{ $item->user ? $item->user->email : $item->email }}</div>
                            </div>
                            <div class="col-md-3">
                                <div class="small text-muted">Contact Number</div>
                                <div>{{ $item->user ? ($item->user->phone ?: '—') : ($item->contact_number ?: '—') }}</div>
                            </div>
                            <div class="col-md-3">
                                <div class="small text-muted">Business Name</div>
                                <div>{{ $item->business_name ?: '—' }}</div>
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-3 text-dark fw-bold">Story Information</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-12">
                                <div class="small text-muted">Title</div>
                                <div class="fw-semibold fs-5">{{ $item->title ?: ($item->business_name ? 'Story of ' . $item->business_name : '—') }}</div>
                            </div>
                            @if ($item->short_description)
                                <div class="col-md-12">
                                    <div class="small text-muted">Short Description</div>
                                    <div class="border rounded p-3 bg-light text-dark">{{ $item->short_description }}</div>
                                </div>
                            @endif
                            <div class="col-md-12">
                                <div class="small text-muted">Story Content</div>
                                <div class="border rounded p-3 bg-light text-dark" style="white-space: pre-wrap;">{{ $item->story ?: $item->company_introduction }}</div>
                            </div>

                            @if($item->co_founders_and_partners_details)
                                <div class="col-md-12">
                                    <div class="small text-muted">Co-Founders & Partners Details</div>
                                    <div class="border rounded p-3 bg-light text-dark" style="white-space: pre-wrap;">{{ $item->co_founders_and_partners_details }}</div>
                                </div>
                            @endif

                            @if($item->cover_image)
                                <div class="col-md-12">
                                    <div class="small text-muted">Cover Image</div>
                                    <div class="mt-2 text-center bg-light p-2 border rounded">
                                        <img src="{{ url('/api/v1/files/' . $item->cover_image) }}" class="img-fluid rounded shadow-sm" style="max-height: 350px; object-fit: contain;" alt="Cover image">
                                    </div>
                                </div>
                            @endif

                            @if($item->attachments && count($item->attachments) > 0)
                                <div class="col-md-12">
                                    <div class="small text-muted">Attachments</div>
                                    <div class="list-group list-group-flush border rounded mt-2">
                                        @foreach($item->attachments as $attachmentId)
                                            <a href="{{ url('/api/v1/files/' . $attachmentId) }}" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center py-2 text-dark">
                                                <i class="bi bi-file-earmark-check text-primary me-2"></i>
                                                <span class="small text-truncate">File ID: {{ $attachmentId }}</span>
                                                <i class="bi bi-box-arrow-up-right ms-auto small text-muted"></i>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        <h6 class="border-bottom pb-2 mb-3 text-dark fw-bold">Review History</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="small text-muted">Status</div>
                                <span class="badge {{ $statusBadgeClass($item->status) }}">{{ $formatLabel($item->status) }}</span>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-muted">Submitted Date</div>
                                <div>{{ $formatDate($item->submitted_at ?: $item->created_at) }}</div>
                            </div>
                            @if ($item->approved_at)
                                <div class="col-md-4">
                                    <div class="small text-muted">Approved Date</div>
                                    <div>{{ $formatDate($item->approved_at) }}</div>
                                </div>
                            @endif
                            @if ($item->rejected_reason ?: $item->notes)
                                <div class="col-12">
                                    <div class="small text-muted">Rejection Reason / Notes</div>
                                    <div class="border rounded p-3 bg-danger-subtle text-danger border-danger-subtle">{{ $item->rejected_reason ?: $item->notes }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approve Modal -->
        <div class="modal fade" id="approveStory{{ $item->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.stories.approve', $item->id) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-dark">Approve Story Submission</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-start">
                        <p class="text-dark">Approve the story submission "{{ $item->title ?: ($item->business_name ? 'Story of ' . $item->business_name : '—') }}" for <strong>{{ $item->user ? $item->user->display_name : $item->full_name }}</strong>?</p>
                        <label class="form-label text-muted small fw-semibold">Admin Note (optional)</label>
                        <textarea name="admin_note" class="form-control text-dark" rows="3" placeholder="Approval message or notes...">{{ old('admin_note') }}</textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success px-3">Approve</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reject Modal -->
        <div class="modal fade" id="rejectStory{{ $item->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.stories.reject', $item->id) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold text-dark text-danger">Reject Story Submission</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-start">
                        <p class="text-dark">Reject the story submission "{{ $item->title ?: ($item->business_name ? 'Story of ' . $item->business_name : '—') }}" for <strong>{{ $item->user ? $item->user->display_name : $item->full_name }}</strong>?</p>
                        <label class="form-label text-muted small fw-semibold">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea name="admin_note" class="form-control text-dark" rows="3" required placeholder="Describe the reason for rejection...">{{ old('admin_note') }}</textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-3">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
