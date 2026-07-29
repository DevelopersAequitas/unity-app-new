@extends('admin.layouts.app')

@section('title', 'Story Submissions')

@include('admin.partials.grid-head')

@section('content')
    @php
        $statusBadgeClass = static function (?string $status): string {
            return match (strtolower((string) $status)) {
                'approved' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200',
                'rejected' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200',
                'new', 'pending' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200',
                default => 'chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200',
            };
        };

        $formatLabel = static fn (string $value): string => str($value)->replace('_', ' ')->title()->toString();
        $formatDate = static fn ($value): string => $value ? $value->format('d M Y, h:i A') : '—';
    @endphp

    @if (session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
            <div class="font-semibold mb-1">Please fix the following:</div>
            <ul class="mb-0 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Story Submissions</h2>
                <p class="text-xs t3 m-0 mt-0.5">Review and manage SME & Business story submissions.</p>
            </div>
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Total: {{ number_format($items->total()) }}</span>
        </div>

        <!-- Filter Card -->
        <div class="p-3 rounded-lg border bs surface-2">
            <form method="GET" action="{{ route('admin.stories.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-2.5 items-end">
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                    <select name="status" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="all">All</option>
                        @foreach (['pending' => 'Pending/New', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">From Date</label>
                    <input type="date" name="from_date" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" value="{{ $filters['from_date'] ?? '' }}">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">To Date</label>
                    <input type="date" name="to_date" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" value="{{ $filters['to_date'] ?? '' }}">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                    <input type="text" name="search" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" value="{{ $filters['search'] ?? '' }}" placeholder="Title, author, or business">
                </div>
                <div class="col-span-full flex gap-2 justify-end">
                    <button type="submit" name="export" value="csv" class="px-3 py-1.5 text-xs font-semibold rounded border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition">Export</button>
                    <a href="{{ route('admin.stories.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center no-underline">Clear</a>
                </div>
            </form>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Author</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Title</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Business Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Submission Date</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($items as $item)
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs">
                                    @if ($item->user)
                                        <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $item->user->id }}', event);" class="font-semibold text-indigo-600 hover:underline no-underline">{{ $item->user->display_name ?: trim($item->user->first_name . ' ' . $item->user->last_name) }}</a>
                                    @else
                                        <span class="t1 font-medium">{{ $item->full_name }}</span>
                                        <span class="chip px-2 py-0.5 text-[10px] font-semibold bg-gray-100 text-gray-600 border-gray-200 ml-1">Guest</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs font-medium t1 max-w-[250px] truncate" title="{{ $item->title ?: ($item->business_name ? 'Story of ' . $item->business_name : '—') }}">
                                    {{ $item->title ?: ($item->business_name ? 'Story of ' . $item->business_name : '—') }}
                                </td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $item->business_name ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-xs">
                                    <span class="{{ $statusBadgeClass($item->status) }}">{{ $formatLabel($item->status) }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $formatDate($item->submitted_at ?: $item->created_at) }}</td>
                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-1.5 items-center">
                                        <button type="button" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition" data-bs-toggle="modal" data-bs-target="#viewStory{{ $item->id }}">View</button>
                                        @if (in_array(strtolower($item->status), ['new', 'pending', 'in_review']))
                                            <button type="button" class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring" data-bs-toggle="modal" data-bs-target="#approveStory{{ $item->id }}">Approve</button>
                                            <button type="button" class="px-2 py-0.5 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring" data-bs-toggle="modal" data-bs-target="#rejectStory{{ $item->id }}">Reject</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-8 text-xs t3">No story submissions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $items->links() }}
            </div>
        </div>
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
                                            <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $item->user->id }}', event);" class="text-indigo-600 hover:underline no-underline fw-semibold">{{ $item->full_name ?: ($item->user->display_name ?: trim($item->user->first_name . ' ' . $item->user->last_name)) }}</a>
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
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">Admin Note (optional)</label>
                            <textarea name="admin_note" class="form-control text-dark" rows="3" placeholder="Approval message or notes...">{{ old('admin_note') }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">Story Link (required)</label>
                            <input type="url" name="story_link" class="form-control text-dark" required placeholder="https://vyaparjagat.com/..." value="{{ old('story_link', $item->story_link) }}">
                        </div>
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
