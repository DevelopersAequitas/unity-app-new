@extends('admin.layouts.app')

@section('title', 'Peer Circles - Peers Global Web')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-indigo-subtle text-indigo rounded-pill px-2.5 py-1" style="background: rgba(99, 102, 241, 0.12); color: #6366f1; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-diagram-3 me-1"></i> Peers Global Website
                </span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Peer Circles Showcase</h1>
            <p class="text-muted small mb-0 mt-0.5">High-trust city hubs, chapter leadership, and member rosters</p>
        </div>
    </div>

    <div class="row g-4 mb-4">
        @forelse($circles as $circle)
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card border-0 shadow-xs rounded-4 p-4 bg-white h-100 d-flex flex-column justify-content-between hover-shadow transition">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1" style="font-size: 0.7rem;">
                                {{ $circle->city->name ?? 'Regional Hub' }}
                            </span>
                            <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(16, 185, 129, 0.12); color: #059669;">● Active</span>
                        </div>

                        <h3 class="h6 fw-bold text-dark mb-2">{{ $circle->name }}</h3>
                        <p class="text-muted small mb-3 line-clamp-2" style="font-size: 0.78rem;">{{ $circle->description ?? 'Promoter circle hub for cross-industry collaboration and syndicate growth.' }}</p>
                    </div>

                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        <span class="text-muted small"><i class="bi bi-people me-1"></i> {{ $circle->members_count ?? 0 }} Members</span>
                        <a href="{{ route('admin.circles.show', $circle->id) }}" class="btn btn-sm btn-light border fw-semibold">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card border-0 shadow-xs rounded-4 p-5 text-center text-muted bg-white">
                    <i class="bi bi-diagram-3 fs-2 mb-2"></i>
                    <p class="mb-0">No circles found.</p>
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
