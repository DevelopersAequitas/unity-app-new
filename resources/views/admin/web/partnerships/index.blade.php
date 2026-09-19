@extends('admin.layouts.app')

@section('title', 'Partnerships Management - Peers Global Web')

@section('content')
<div class="container-fluid px-0">
    {{-- Header --}}
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-indigo-subtle text-indigo rounded-pill px-2.5 py-1" style="background: rgba(99, 102, 241, 0.12); color: #6366f1; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-people-fill me-1"></i> Peers Global Website
                </span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Partnerships Management</h1>
            <p class="text-muted small mb-0 mt-0.5">Manage bilateral promoter partnerships and cross-border ventures</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs fw-semibold" data-bs-toggle="modal" data-bs-target="#newPartnershipModal">
                <i class="bi bi-plus-circle-fill"></i>
                <span>New Partnership</span>
            </button>
        </div>
    </div>

    {{-- Filter Toolbar --}}
    <div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
        <form method="GET" action="{{ route('admin.web.partnerships.index') }}" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control bg-light border-start-0" placeholder="Search by title, company, sector, code...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm bg-light" onchange="this.form.submit()">
                    <option value="All" @selected(request('status') === 'All')>All Statuses</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st }}" @selected(request('status') === $st)>{{ $st }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="sector" class="form-select form-select-sm bg-light" onchange="this.form.submit()">
                    <option value="All" @selected(request('sector') === 'All')>All Sectors</option>
                    @foreach($sectors as $sec)
                        <option value="{{ $sec }}" @selected(request('sector') === $sec)>{{ $sec }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">Filter</button>
                @if(request()->hasAny(['q', 'status', 'sector']))
                    <a href="{{ route('admin.web.partnerships.index') }}" class="btn btn-light btn-sm border" title="Clear Filters"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </form>
    </div>

    {{-- Grid View Cards --}}
    <div class="row g-4 mb-4">
        @forelse($partnerships as $item)
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card border-0 shadow-xs rounded-4 p-4 bg-white h-100 d-flex flex-column justify-content-between hover-shadow transition">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 font-monospace" style="font-size: 0.7rem;">
                                {{ $item->code }}
                            </span>
                            @if($item->status === 'Active')
                                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(16, 185, 129, 0.12); color: #059669;">● Active</span>
                            @elseif($item->status === 'Negotiation')
                                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(99, 102, 241, 0.12); color: #4f46e5;">● Negotiation</span>
                            @elseif($item->status === 'Completed')
                                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(59, 130, 246, 0.12); color: #2563eb;">● Completed</span>
                            @else
                                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(245, 158, 11, 0.12); color: #d97706;">● Under Review</span>
                            @endif
                        </div>

                        <h3 class="h6 fw-bold text-dark mb-2 line-clamp-2" style="font-size: 0.95rem; line-height: 1.4;">
                            {{ $item->title }}
                        </h3>

                        {{-- Companies handshake --}}
                        <div class="d-flex align-items-center gap-2 p-2.5 rounded-3 bg-light my-3">
                            <div class="d-flex align-items-center gap-1.5 flex-grow-1 min-w-0">
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shrink-0" style="width: 26px; height: 26px; background: #6366f1; font-size: 0.65rem;">
                                    {{ substr($item->company_a, 0, 2) }}
                                </div>
                                <span class="fw-bold text-dark small text-truncate">{{ $item->company_a }}</span>
                            </div>
                            <i class="bi bi-arrow-left-right text-muted px-1"></i>
                            <div class="d-flex align-items-center gap-1.5 flex-grow-1 min-w-0 justify-content-end">
                                <span class="fw-bold text-dark small text-truncate">{{ $item->company_b }}</span>
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shrink-0" style="width: 26px; height: 26px; background: #06b6d4; font-size: 0.65rem;">
                                    {{ substr($item->company_b, 0, 2) }}
                                </div>
                            </div>
                        </div>

                        <p class="text-muted small mb-3 line-clamp-2" style="font-size: 0.78rem;">
                            {{ $item->description ?? 'Bilateral strategic alliance between verified promoter enterprises.' }}
                        </p>

                        {{-- Route & Sector --}}
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @if($item->sector)
                                <span class="badge bg-light text-secondary border rounded-pill" style="font-size: 0.68rem;">
                                    <i class="bi bi-tag me-1"></i>{{ $item->sector }}
                                </span>
                            @endif
                            @if($item->route)
                                <span class="badge bg-light text-secondary border rounded-pill" style="font-size: 0.68rem;">
                                    <i class="bi bi-geo-alt me-1"></i>{{ $item->route }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div>
                        {{-- Progress Bar --}}
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small text-muted mb-1" style="font-size: 0.72rem;">
                                <span>Execution Stage: <strong class="text-dark">{{ $item->stage }}</strong></span>
                                <span class="fw-bold text-primary">{{ $item->progress_percent }}%</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $item->progress_percent }}%"></div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                            <div>
                                <span class="text-muted" style="font-size: 0.68rem;">Deal Value</span>
                                <div class="fw-bold text-dark">{{ $item->value ?? '—' }}</div>
                            </div>
                            <div class="d-flex gap-1.5">
                                <button type="button" class="btn btn-sm btn-light border rounded-2 px-2.5" data-bs-toggle="modal" data-bs-target="#editModal{{ $item->id }}" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" action="{{ route('admin.web.partnerships.destroy', $item->id) }}" onsubmit="return confirm('Delete this partnership record?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light border text-danger rounded-2 px-2.5" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Edit Modal --}}
            <div class="modal fade" id="editModal{{ $item->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('admin.web.partnerships.update', $item->id) }}" class="modal-content rounded-4 border-0 shadow">
                        @csrf
                        @method('PUT')
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title fw-bold">Edit Partnership</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Title</label>
                                <input type="text" name="title" value="{{ $item->title }}" class="form-control form-control-sm" required>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Company A</label>
                                    <input type="text" name="company_a" value="{{ $item->company_a }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Company B</label>
                                    <input type="text" name="company_b" value="{{ $item->company_b }}" class="form-control form-control-sm" required>
                                </div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Sector</label>
                                    <input type="text" name="sector" value="{{ $item->sector }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Deal Value</label>
                                    <input type="text" name="value" value="{{ $item->value }}" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Status</label>
                                    <select name="status" class="form-select form-select-sm">
                                        @foreach($statuses as $st)
                                            <option value="{{ $st }}" @selected($item->status === $st)>{{ $st }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Stage</label>
                                    <input type="text" name="stage" value="{{ $item->stage }}" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Progress (%)</label>
                                <input type="number" min="0" max="100" name="progress_percent" value="{{ $item->progress_percent }}" class="form-control form-control-sm">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Description</label>
                                <textarea name="description" rows="3" class="form-control form-control-sm">{{ $item->description }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card border-0 shadow-xs rounded-4 p-5 text-center text-muted bg-white">
                    <i class="bi bi-people fs-2 mb-2"></i>
                    <p class="mb-0">No partnerships matched your search criteria.</p>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="d-flex justify-content-center">
        {{ $partnerships->links() }}
    </div>
</div>

{{-- Create Partnership Modal --}}
<div class="modal fade" id="newPartnershipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('admin.web.partnerships.store') }}" class="modal-content rounded-4 border-0 shadow">
            @csrf
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Create New Partnership</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Partnership Title</label>
                    <input type="text" name="title" class="form-control form-control-sm" placeholder="e.g. Clean Energy Microgrid JV" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Company A (Lead)</label>
                        <input type="text" name="company_a" class="form-control form-control-sm" placeholder="e.g. Apex Logistics" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Company B (Partner)</label>
                        <input type="text" name="company_b" class="form-control form-control-sm" placeholder="e.g. Zen Cloud Solutions" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Sector</label>
                        <input type="text" name="sector" class="form-control form-control-sm" placeholder="e.g. Supply & Freight Tech">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Deal Value</label>
                        <input type="text" name="value" class="form-control form-control-sm" placeholder="e.g. ₹ 4.5 Cr">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="Active">Active</option>
                            <option value="Under Review" selected>Under Review</option>
                            <option value="Negotiation">Negotiation</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Stage</label>
                        <input type="text" name="stage" value="Initiation" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Route / Region</label>
                    <input type="text" name="route" class="form-control form-control-sm" placeholder="e.g. Mumbai ↔ Bengaluru">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea name="description" rows="3" class="form-control form-control-sm" placeholder="Synergies, scope, and objectives..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">Create Partnership</button>
            </div>
        </form>
    </div>
</div>
@endsection
