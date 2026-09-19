@extends('admin.layouts.app')

@section('title', 'Companies Directory - Peers Global Web')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-indigo-subtle text-indigo rounded-pill px-2.5 py-1" style="background: rgba(245, 158, 11, 0.12); color: #d97706; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-building me-1"></i> Peers Global Website
                </span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Enterprise Companies Directory</h1>
            <p class="text-muted small mb-0 mt-0.5">Verified promoters, corporates, and partner institutions</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs fw-semibold" data-bs-toggle="modal" data-bs-target="#newCompanyModal">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Add Company</span>
            </button>
        </div>
    </div>

    {{-- Search Form --}}
    <div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
        <form method="GET" action="{{ route('admin.web.companies.index') }}" class="row g-2 align-items-center">
            <div class="col-12 col-md-10">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control bg-light border-start-0" placeholder="Search companies by name, industry, sector, city...">
                </div>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">Search</button>
            </div>
        </form>
    </div>

    {{-- Grid of Companies --}}
    <div class="row g-4 mb-4">
        @forelse($companies as $company)
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card border-0 shadow-xs rounded-4 p-4 bg-white h-100 d-flex flex-column justify-content-between hover-shadow transition">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="rounded-3 d-flex align-items-center justify-content-center text-white fw-bold" style="width: 44px; height: 44px; background: #6366f1; font-size: 1rem;">
                                {{ substr($company->name, 0, 2) }}
                            </div>
                            @if($company->is_verified)
                                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(16, 185, 129, 0.12); color: #059669;">
                                    <i class="bi bi-patch-check-fill me-1"></i> Verified
                                </span>
                            @endif
                        </div>

                        <h3 class="h6 fw-bold text-dark mb-1 text-truncate">{{ $company->name }}</h3>
                        <p class="text-primary fw-semibold small mb-2" style="font-size: 0.78rem;">{{ $company->industry ?? 'Enterprise' }}</p>
                        <p class="text-muted small mb-3 line-clamp-2" style="font-size: 0.78rem;">{{ $company->description ?? 'Verified promoter corporate member on Peers Global.' }}</p>

                        <div class="d-flex flex-column gap-1 small text-muted mb-3" style="font-size: 0.75rem;">
                            @if($company->city)
                                <div><i class="bi bi-geo-alt me-1.5 text-secondary"></i> {{ $company->city }}</div>
                            @endif
                            @if($company->turnover)
                                <div><i class="bi bi-cash-stack me-1.5 text-secondary"></i> Turnover: <strong class="text-dark">{{ $company->turnover }}</strong></div>
                            @endif
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        @if($company->website)
                            <a href="{{ $company->website }}" target="_blank" class="btn btn-sm btn-light border small text-decoration-none">
                                <i class="bi bi-globe me-1"></i> Website
                            </a>
                        @else
                            <span></span>
                        @endif
                        <form method="POST" action="{{ route('admin.web.companies.destroy', $company->id) }}" onsubmit="return confirm('Delete this company?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-light border text-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card border-0 shadow-xs rounded-4 p-5 text-center text-muted bg-white">
                    <i class="bi bi-building fs-2 mb-2"></i>
                    <p class="mb-0">No companies found.</p>
                </div>
            </div>
        @endforelse
    </div>

    <div class="d-flex justify-content-center">
        {{ $companies->links() }}
    </div>
</div>

{{-- Create Modal --}}
<div class="modal fade" id="newCompanyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('admin.web.companies.store') }}" class="modal-content rounded-4 border-0 shadow">
            @csrf
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Add Company</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Company Name</label>
                    <input type="text" name="name" class="form-control form-control-sm" required placeholder="e.g. Apex Logistics">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Industry</label>
                        <input type="text" name="industry" class="form-control form-control-sm" placeholder="e.g. Supply Chain">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">City</label>
                        <input type="text" name="city" class="form-control form-control-sm" placeholder="e.g. Mumbai">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Turnover</label>
                        <input type="text" name="turnover" class="form-control form-control-sm" placeholder="e.g. ₹ 85 Cr">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Website</label>
                        <input type="url" name="website" class="form-control form-control-sm" placeholder="https://...">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea name="description" rows="3" class="form-control form-control-sm" placeholder="Company background & business focus..."></textarea>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_verified" value="1" id="isVerifiedCheck" checked>
                    <label class="form-check-label small" for="isVerifiedCheck">Verified Promoter Enterprise</label>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">Save Company</button>
            </div>
        </form>
    </div>
</div>
@endsection
