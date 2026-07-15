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
                <div class="modal-content border-0 rounded-4 shadow-lg" style="max-height: 90vh;">
                    <div class="modal-header border-bottom bg-light py-3">
                        <h5 class="modal-title fw-bold text-dark"><i class="bi bi-journal-text text-primary me-2"></i>Story Submission Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-start p-4">
                        
                        <!-- Screen 1: Basic Information -->
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 collapse-header" data-bs-toggle="collapse" data-bs-target="#collapseAuthor{{ $item->id }}" aria-expanded="true">
                            <h6 class="text-dark fw-bold text-uppercase tracking-wider small mb-0"><i class="bi bi-person-badge text-primary me-2"></i>Basic Information</h6>
                            <i class="bi bi-chevron-down text-muted collapse-icon"></i>
                        </div>
                        <div class="collapse show mb-4" id="collapseAuthor{{ $item->id }}">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="small text-muted">Full Name</div>
                                    <div class="fw-semibold text-dark">
                                        @if ($item->user)
                                            <a href="{{ route('admin.users.show', $item->user->id) }}">{{ $item->full_name ?: ($item->user->display_name ?: trim($item->user->first_name . ' ' . $item->user->last_name)) }}</a>
                                        @else
                                            {{ $item->full_name ?: 'Not Provided' }}
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small text-muted">Designation</div>
                                    <div class="text-dark">{{ $item->designation ?: 'Not Provided' }}</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small text-muted">Company Name</div>
                                    <div class="text-dark">{{ $item->company_name ?: ($item->business_name ?: 'Not Provided') }}</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small text-muted">Website</div>
                                    <div class="d-flex align-items-center">
                                        @if($item->website)
                                            <a href="{{ $item->website }}" target="_blank" class="text-decoration-none text-primary"><i class="bi bi-globe me-1"></i>{{ $item->website }}</a>
                                            <button class="btn btn-link btn-sm p-0 ms-2 text-muted copy-btn" data-clipboard-text="{{ $item->website }}" title="Copy Website">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        @else
                                            <span class="text-muted">Not Provided</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small text-muted font-monospace">Email</div>
                                    <div class="text-dark d-flex align-items-center">
                                        <span>{{ $item->user ? $item->user->email : ($item->email ?: 'Not Provided') }}</span>
                                        @if($item->user ? $item->user->email : $item->email)
                                            <button class="btn btn-link btn-sm p-0 ms-2 text-muted copy-btn" data-clipboard-text="{{ $item->user ? $item->user->email : $item->email }}" title="Copy Email">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small text-muted font-monospace">Contact Number</div>
                                    <div class="text-dark">{{ $item->user ? ($item->user->phone ?: '—') : ($item->contact_number ?: '—') }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Media Preview Cards -->
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 collapse-header" data-bs-toggle="collapse" data-bs-target="#collapseMedia{{ $item->id }}" aria-expanded="true">
                            <h6 class="text-dark fw-bold text-uppercase tracking-wider small mb-0"><i class="bi bi-images text-primary me-2"></i>Media</h6>
                            <i class="bi bi-chevron-down text-muted collapse-icon"></i>
                        </div>
                        <div class="collapse show mb-4" id="collapseMedia{{ $item->id }}">
                            <div class="row g-4">
                                <!-- Profile Photo Card -->
                                <div class="col-md-6">
                                    <div class="card h-100 shadow-sm border border-light-subtle rounded-3">
                                        <div class="card-header bg-light border-0 py-2">
                                            <div class="small fw-semibold text-muted text-uppercase">Profile Photo</div>
                                        </div>
                                        <div class="card-body d-flex flex-column align-items-center justify-content-center py-3">
                                            @if($item->profile_photo || $item->cover_image)
                                                <a href="#" data-bs-toggle="modal" data-bs-target="#profilePhotoLightbox{{ $item->id }}" class="text-center d-block">
                                                    <img src="{{ url('/api/v1/files/' . ($item->profile_photo ?: $item->cover_image)) }}" class="img-fluid rounded border shadow-sm" style="max-height: 220px; object-fit: contain; cursor: zoom-in;" alt="Profile Photo">
                                                    <div class="mt-2 small text-primary"><i class="bi bi-zoom-in me-1"></i>Click to Enlarge</div>
                                                </a>
                                            @else
                                                <div class="text-center py-4">
                                                    <i class="bi bi-person-bounding-box text-muted fs-1 mb-2"></i>
                                                    <div class="text-muted small">Not Provided</div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Company Logo Card -->
                                <div class="col-md-6">
                                    <div class="card h-100 shadow-sm border border-light-subtle rounded-3">
                                        <div class="card-header bg-light border-0 py-2">
                                            <div class="small fw-semibold text-muted text-uppercase">Company Logo</div>
                                        </div>
                                        <div class="card-body d-flex flex-column align-items-center justify-content-center py-3">
                                            @if($item->company_logo)
                                                <a href="#" data-bs-toggle="modal" data-bs-target="#companyLogoLightbox{{ $item->id }}" class="text-center d-block">
                                                    <img src="{{ url('/api/v1/files/' . $item->company_logo) }}" class="img-fluid rounded border shadow-sm" style="max-height: 220px; object-fit: contain; cursor: zoom-in;" alt="Company Logo">
                                                    <div class="mt-2 small text-primary"><i class="bi bi-zoom-in me-1"></i>Click to Enlarge</div>
                                                </a>
                                            @else
                                                <div class="text-center py-4">
                                                    <i class="bi bi-building text-muted fs-1 mb-2"></i>
                                                    <div class="text-muted small">Not Provided</div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Screen 2: Entrepreneur Profile Cards -->
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 collapse-header" data-bs-toggle="collapse" data-bs-target="#collapseProfile{{ $item->id }}" aria-expanded="true">
                            <h6 class="text-dark fw-bold text-uppercase tracking-wider small mb-0"><i class="bi bi-chat-left-quote text-primary me-2"></i>Entrepreneur Profile</h6>
                            <i class="bi bi-chevron-down text-muted collapse-icon"></i>
                        </div>
                        <div class="collapse show mb-4" id="collapseProfile{{ $item->id }}">
                            <div class="row g-3">
                                <!-- Entrepreneurial Journey -->
                                <div class="col-md-12">
                                    <div class="card border rounded-3 shadow-none bg-light-subtle">
                                        <div class="card-body">
                                            <div class="small text-muted fw-bold mb-1">Entrepreneurial Journey</div>
                                            <div class="text-dark py-1" style="white-space: pre-wrap; word-break: break-word;">{{ $item->entrepreneurial_journey ?: ($item->story ?: 'Not Provided') }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Business Description -->
                                <div class="col-md-12">
                                    <div class="card border rounded-3 shadow-none bg-light-subtle">
                                        <div class="card-body">
                                            <div class="small text-muted fw-bold mb-1">What does your business do?</div>
                                            <div class="text-dark py-1" style="white-space: pre-wrap; word-break: break-word;">{{ $item->business_description ?: ($item->short_description ?: 'Not Provided') }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Biggest Challenge -->
                                <div class="col-md-12">
                                    <div class="card border rounded-3 shadow-none bg-light-subtle">
                                        <div class="card-body">
                                            <div class="small text-muted fw-bold mb-1">Biggest Challenge & How you overcame it</div>
                                            <div class="text-dark py-1" style="white-space: pre-wrap; word-break: break-word;">{{ $item->biggest_challenge ?: 'Not Provided' }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Biggest Achievement -->
                                <div class="col-md-12">
                                    <div class="card border rounded-3 shadow-none bg-light-subtle">
                                        <div class="card-body">
                                            <div class="small text-muted fw-bold mb-1">What achievement are you most proud of?</div>
                                            <div class="text-dark py-1" style="white-space: pre-wrap; word-break: break-word;">{{ $item->biggest_achievement ?: 'Not Provided' }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Business Impact -->
                                <div class="col-md-12">
                                    <div class="card border rounded-3 shadow-none bg-light-subtle">
                                        <div class="card-body">
                                            <div class="small text-muted fw-bold mb-1">What impact are you creating?</div>
                                            <div class="text-dark py-1" style="white-space: pre-wrap; word-break: break-word;">{{ $item->business_impact ?: 'Not Provided' }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Future Goals -->
                                <div class="col-md-12">
                                    <div class="card border rounded-3 shadow-none bg-light-subtle">
                                        <div class="card-body">
                                            <div class="small text-muted fw-bold mb-1">What are your future goals?</div>
                                            <div class="text-dark py-1" style="white-space: pre-wrap; word-break: break-word;">{{ $item->future_goals ?: 'Not Provided' }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Advice for Entrepreneurs -->
                                <div class="col-md-12">
                                    <div class="card border rounded-3 shadow-none bg-light-subtle">
                                        <div class="card-body">
                                            <div class="small text-muted fw-bold mb-1">What advice would you like to give aspiring entrepreneurs?</div>
                                            <div class="text-dark py-1" style="white-space: pre-wrap; word-break: break-word;">{{ $item->advice_for_entrepreneurs ?: 'Not Provided' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Screen 3: Promotion (Social Links) -->
                        @if($item->linkedin_url || $item->facebook_url || $item->instagram_url || $item->twitter_url)
                            <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 collapse-header" data-bs-toggle="collapse" data-bs-target="#collapseSocial{{ $item->id }}" aria-expanded="true">
                                <h6 class="text-dark fw-bold text-uppercase tracking-wider small mb-0"><i class="bi bi-share text-primary me-2"></i>Social Profiles & Promotion</h6>
                                <i class="bi bi-chevron-down text-muted collapse-icon"></i>
                            </div>
                            <div class="collapse show mb-4" id="collapseSocial{{ $item->id }}">
                                <div class="row g-3">
                                    @if($item->linkedin_url)
                                        <div class="col-md-3">
                                            <div class="small text-muted">LinkedIn Profile</div>
                                            <div class="d-flex align-items-center">
                                                <a href="{{ $item->linkedin_url }}" target="_blank" class="text-decoration-none text-primary"><i class="bi bi-linkedin me-1 text-info"></i>LinkedIn</a>
                                                <button class="btn btn-link btn-sm p-0 ms-2 text-muted copy-btn" data-clipboard-text="{{ $item->linkedin_url }}" title="Copy LinkedIn Link">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                    @if($item->facebook_url)
                                        <div class="col-md-3">
                                            <div class="small text-muted">Facebook Profile/Page</div>
                                            <div class="d-flex align-items-center">
                                                <a href="{{ $item->facebook_url }}" target="_blank" class="text-decoration-none text-primary"><i class="bi bi-facebook me-1 text-primary"></i>Facebook</a>
                                                <button class="btn btn-link btn-sm p-0 ms-2 text-muted copy-btn" data-clipboard-text="{{ $item->facebook_url }}" title="Copy Facebook Link">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                    @if($item->instagram_url)
                                        <div class="col-md-3">
                                            <div class="small text-muted">Instagram Profile</div>
                                            <div class="d-flex align-items-center">
                                                <a href="{{ $item->instagram_url }}" target="_blank" class="text-decoration-none text-primary"><i class="bi bi-instagram me-1 text-danger"></i>Instagram</a>
                                                <button class="btn btn-link btn-sm p-0 ms-2 text-muted copy-btn" data-clipboard-text="{{ $item->instagram_url }}" title="Copy Instagram Link">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                    @if($item->twitter_url)
                                        <div class="col-md-3">
                                            <div class="small text-muted">X (Twitter) Profile</div>
                                            <div class="d-flex align-items-center">
                                                <a href="{{ $item->twitter_url }}" target="_blank" class="text-decoration-none text-primary"><i class="bi bi-twitter me-1 text-dark"></i>Twitter/X</a>
                                                <button class="btn btn-link btn-sm p-0 ms-2 text-muted copy-btn" data-clipboard-text="{{ $item->twitter_url }}" title="Copy Twitter Link">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Review History -->
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3 collapse-header" data-bs-toggle="collapse" data-bs-target="#collapseReview{{ $item->id }}" aria-expanded="true">
                            <h6 class="text-dark fw-bold text-uppercase tracking-wider small mb-0"><i class="bi bi-clipboard-check text-primary me-2"></i>Review Details</h6>
                            <i class="bi bi-chevron-down text-muted collapse-icon"></i>
                        </div>
                        <div class="collapse show" id="collapseReview{{ $item->id }}">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="small text-muted">Status</div>
                                    <span class="badge {{ $statusBadgeClass($item->status) }}">{{ $formatLabel($item->status) }}</span>
                                </div>
                                <div class="col-md-4">
                                    <div class="small text-muted">Submitted Date</div>
                                    <div>{{ $formatDate($item->submitted_at ?: $item->created_at) }}</div>
                                </div>
                                @if ($item->approved_at || $item->reviewed_at)
                                    <div class="col-md-4">
                                        <div class="small text-muted">Reviewed Date</div>
                                        <div>{{ $formatDate($item->approved_at ?: $item->reviewed_at) }}</div>
                                    </div>
                                @endif
                                @if ($item->rejected_reason ?: ($item->notes ?: $item->admin_remark))
                                    <div class="col-12 mt-3">
                                        <div class="small text-muted">Admin Remark / Rejection Reason</div>
                                        <div class="border rounded p-3 bg-light text-dark" style="white-space: pre-wrap;">{{ $item->rejected_reason ?: ($item->notes ?: $item->admin_remark) }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light py-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Photo Lightbox Modal -->
        @if($item->profile_photo || $item->cover_image)
            <div class="modal fade" id="profilePhotoLightbox{{ $item->id }}" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-0 rounded-4 shadow-lg bg-dark">
                        <div class="modal-header border-0 pb-0 justify-content-end">
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center p-4">
                            <img src="{{ url('/api/v1/files/' . ($item->profile_photo ?: $item->cover_image)) }}" class="img-fluid rounded shadow" style="max-height: 80vh; object-fit: contain;" alt="Profile Photo Lightbox">
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Company Logo Lightbox Modal -->
        @if($item->company_logo)
            <div class="modal fade" id="companyLogoLightbox{{ $item->id }}" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-0 rounded-4 shadow-lg bg-dark">
                        <div class="modal-header border-0 pb-0 justify-content-end">
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center p-4">
                            <img src="{{ url('/api/v1/files/' . $item->company_logo) }}" class="img-fluid rounded shadow" style="max-height: 80vh; object-fit: contain;" alt="Company Logo Lightbox">
                        </div>
                    </div>
                </div>
            </div>
        @endif

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

    @push('styles')
    <style>
        .collapse-header {
            cursor: pointer;
            user-select: none;
        }
        .collapse-header .collapse-icon {
            transition: transform 0.2s ease-in-out;
            transform: rotate(0deg);
        }
        .collapse-header[aria-expanded="true"] .collapse-icon {
            transform: rotate(180deg);
        }
        .copy-btn {
            opacity: 0.6;
            transition: opacity 0.2s, transform 0.1s;
        }
        .copy-btn:hover {
            opacity: 1;
            transform: scale(1.1);
        }
        .copy-btn:active {
            transform: scale(0.9);
        }
    </style>
    @endpush

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Copy to clipboard handler
        document.querySelectorAll('.copy-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const text = this.getAttribute('data-clipboard-text');
                if (!text) return;
                navigator.clipboard.writeText(text).then(() => {
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.classList.remove('bi-clipboard');
                        icon.classList.add('bi-check-lg', 'text-success');
                        setTimeout(() => {
                            icon.classList.remove('bi-check-lg', 'text-success');
                            icon.classList.add('bi-clipboard');
                        }, 2000);
                    }
                }).catch(err => {
                    console.error('Failed to copy: ', err);
                });
            });
        });
    });
    </script>
    @endpush
@endsection
