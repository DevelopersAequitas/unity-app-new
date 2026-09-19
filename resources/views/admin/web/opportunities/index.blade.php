@extends('admin.layouts.app')

@section('title', 'Opportunities Management - Peers Global Web')

@section('content')
<div class="container-fluid px-0">
    {{-- Header --}}
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-indigo-subtle text-indigo rounded-pill px-2.5 py-1" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-compass me-1"></i> Peers Global Website
                </span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Opportunities Pipeline</h1>
            <p class="text-muted small mb-0 mt-0.5">Manage deals, RFP tenders, and joint venture opportunities</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs fw-semibold" data-bs-toggle="modal" data-bs-target="#newOpportunityModal">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Post Opportunity</span>
            </button>
        </div>
    </div>

    {{-- Filter Toolbar --}}
    <div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
        <form method="GET" action="{{ route('admin.web.opportunities.index') }}" class="row g-2 align-items-center">
            <div class="col-12 col-md-8">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control bg-light border-start-0" placeholder="Search opportunities by title, sector, proposer...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm bg-light" onchange="this.form.submit()">
                    <option value="All" @selected(request('status') === 'All')>All Statuses</option>
                    <option value="Active" @selected(request('status') === 'Active')>Active</option>
                    <option value="Under Review" @selected(request('status') === 'Under Review')>Under Review</option>
                    <option value="Closed" @selected(request('status') === 'Closed')>Closed</option>
                </select>
            </div>
            <div class="col-6 col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">Filter</button>
            </div>
        </form>
    </div>

    {{-- Opportunities List Table --}}
    <div class="card border-0 shadow-xs rounded-4 p-4 bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.84rem;">
                <thead class="table-light text-uppercase tracking-wider text-muted" style="font-size: 0.7rem;">
                    <tr>
                        <th>Code</th>
                        <th>Opportunity Title</th>
                        <th>Sector</th>
                        <th>Value</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Deadline</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($opportunities as $opp)
                        <tr>
                            <td><span class="badge bg-light text-secondary border font-monospace">{{ $opp->code }}</span></td>
                            <td>
                                <div class="fw-bold text-dark">{{ $opp->title }}</div>
                                <small class="text-muted">{{ $opp->proposer_company ?? 'Peers Global Network' }}</small>
                            </td>
                            <td class="text-muted">{{ $opp->sector ?? 'Enterprise' }}</td>
                            <td class="fw-bold text-dark">{{ $opp->value ?? '—' }}</td>
                            <td class="text-muted">{{ $opp->location ?? 'India' }}</td>
                            <td>
                                @if($opp->status === 'Active')
                                    <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(16, 185, 129, 0.12); color: #059669;">● Active</span>
                                @else
                                    <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(245, 158, 11, 0.12); color: #d97706;">● {{ $opp->status }}</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $opp->deadline ? $opp->deadline->format('M d, Y') : 'Open' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.web.opportunities.destroy', $opp->id) }}" onsubmit="return confirm('Delete this opportunity?');" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light border text-danger rounded-2 px-2.5">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No opportunities found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center pt-3">
            {{ $opportunities->links() }}
        </div>
    </div>
</div>

{{-- Create Modal --}}
<div class="modal fade" id="newOpportunityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('admin.web.opportunities.store') }}" class="modal-content rounded-4 border-0 shadow">
            @csrf
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Post New Opportunity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Opportunity Title</label>
                    <input type="text" name="title" class="form-control form-control-sm" required placeholder="e.g. Turnkey Industrial Solar 5MW">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Sector</label>
                        <input type="text" name="sector" class="form-control form-control-sm" placeholder="e.g. CleanTech">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Value</label>
                        <input type="text" name="value" class="form-control form-control-sm" placeholder="e.g. ₹ 7.5 Cr">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Location</label>
                        <input type="text" name="location" class="form-control form-control-sm" placeholder="e.g. Gujarat, India">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="Active" selected>Active</option>
                            <option value="Under Review">Under Review</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea name="description" rows="3" class="form-control form-control-sm" placeholder="Opportunity summary & specifications..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">Post Opportunity</button>
            </div>
        </form>
    </div>
</div>
@endsection
