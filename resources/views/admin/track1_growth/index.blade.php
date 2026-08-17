@extends('admin.layouts.app')

@section('title', 'Track 1 — Growth Honours')

@section('content')
<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-graph-up-arrow text-primary"></i> 🔵 Track 1 — Growth Honours
            </h4>
            <p class="text-muted small mb-0">Measures paid members personally introduced to Peers Global. Lifetime cumulative counts that never reset.</p>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('admin.track1-growth.seed') }}" onsubmit="return confirm('Seed / Sync default Track 1 — Growth Honours?');">
                @csrf
                <button type="submit" class="btn btn-outline-primary shadow-sm fw-semibold">
                    <i class="bi bi-arrow-repeat me-1"></i> Sync Default Honours
                </button>
            </form>
            <a href="{{ route('admin.track1-growth.create') }}" class="btn btn-primary shadow-sm fw-semibold">
                <i class="bi bi-plus-lg me-1"></i> Add Growth Honour
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Tier Overview Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 bg-gradient p-3 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase text-secondary fw-bold text-xs">Digital Honours</div>
                        <div class="h4 fw-bold text-info mb-0 mt-1">{{ $stats['digital'] }} Badges</div>
                        <div class="text-muted text-xs">1, 3, 5, 10 Introduced</div>
                    </div>
                    <div class="rounded-circle bg-info-subtle p-3 text-info">
                        <i class="bi bi-award fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 bg-gradient p-3 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase text-secondary fw-bold text-xs">Circle Honours</div>
                        <div class="h4 fw-bold text-primary mb-0 mt-1">{{ $stats['circle'] }} Badges</div>
                        <div class="text-muted text-xs">Pinned before your Circle (20, 35, 50)</div>
                    </div>
                    <div class="rounded-circle bg-primary-subtle p-3 text-primary">
                        <i class="bi bi-diagram-3 fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 bg-gradient p-3 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase text-secondary fw-bold text-xs">City Honours</div>
                        <div class="h4 fw-bold text-warning-emphasis mb-0 mt-1">{{ $stats['city'] }} Badges</div>
                        <div class="text-muted text-xs">Pinned at City Meeting (75, 100, 150)</div>
                    </div>
                    <div class="rounded-circle bg-warning-subtle p-3 text-warning-emphasis">
                        <i class="bi bi-building fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 bg-gradient p-3 border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase text-secondary fw-bold text-xs">National Honours</div>
                        <div class="h4 fw-bold text-danger mb-0 mt-1">{{ $stats['national'] }} Badges</div>
                        <div class="text-muted text-xs">Awarded on National Stage (250, 500)</div>
                    </div>
                    <div class="rounded-circle bg-danger-subtle p-3 text-danger">
                        <i class="bi bi-globe fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form id="track1FiltersForm" method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="badgeSearch" class="form-label fw-semibold small text-secondary">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="badgeSearch" name="q" class="form-control" placeholder="Search by title or description..." value="{{ $filters['search'] ?? '' }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="tierFilter" class="form-label fw-semibold small text-secondary">Honour Tier</label>
                    <select id="tierFilter" name="tier" class="form-select form-select-sm">
                        <option value="">All Tiers</option>
                        <option value="digital" @selected(($filters['tier'] ?? '') === 'digital')>🎖️ Digital Honours (1 - 10)</option>
                        <option value="circle" @selected(($filters['tier'] ?? '') === 'circle')>⭕ Circle Honours (20 - 50)</option>
                        <option value="city" @selected(($filters['tier'] ?? '') === 'city')>🏙️ City Honours (75 - 150)</option>
                        <option value="national" @selected(($filters['tier'] ?? '') === 'national')>👑 National Honours (250 - 500)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="statusFilter" class="form-label fw-semibold small text-secondary">Status</label>
                    <select id="statusFilter" name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="1" @selected(($filters['status'] ?? '') === '1')>Active</option>
                        <option value="0" @selected(($filters['status'] ?? '') === '0')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">Filter</button>
                    <a href="{{ route('admin.track1-growth.index') }}" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Honours Table Card --}}
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-sm">
                <thead class="table-light border-bottom">
                    <tr>
                        <th class="py-3 px-4 text-secondary small font-mono" style="width: 60px;">#</th>
                        <th class="py-3 px-4 text-secondary small" style="width: 80px;">Icon</th>
                        <th class="py-3 px-4 text-secondary small">Honour Title & What It Means</th>
                        <th class="py-3 px-4 text-secondary small">Tier</th>
                        <th class="py-3 px-4 text-secondary small">Required Introductions</th>
                        <th class="py-3 px-4 text-secondary small">Status</th>
                        <th class="py-3 px-4 text-secondary small">Sort Order</th>
                        <th class="py-3 px-4 text-secondary small text-end" style="width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($honours as $index => $honour)
                        <tr>
                            <td class="py-3 px-4 fw-semibold text-muted">
                                {{ $honours->firstItem() + $index }}
                            </td>
                            <td class="py-3 px-4">
                                <a href="{{ route('admin.track1-growth.edit', $honour->id) }}" class="d-inline-block">
                                    @if($honour->badge_image_url)
                                        <img src="{{ $honour->badge_image_url }}" alt="{{ $honour->title }}" class="rounded border p-1 bg-white" style="width: 44px; height: 44px; object-fit: contain;">
                                    @else
                                        <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted" style="width: 44px; height: 44px;">
                                            <i class="bi bi-award fs-5 text-primary"></i>
                                        </div>
                                    @endif
                                </a>
                            </td>
                            <td class="py-3 px-4">
                                <a href="{{ route('admin.track1-growth.edit', $honour->id) }}" class="text-decoration-none text-dark d-block">
                                    <div class="fw-bold text-dark fs-6">{{ $honour->title }}</div>
                                    @if($honour->description)
                                        <div class="text-muted small text-truncate" style="max-width: 380px;">{{ $honour->description }}</div>
                                    @endif
                                </a>
                            </td>
                            <td class="py-3 px-4">
                                @if($honour->required_count <= 10)
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2.5 py-1.5 rounded-pill">
                                        <i class="bi bi-award me-1"></i> Digital Honour
                                    </span>
                                @elseif($honour->required_count <= 50)
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 rounded-pill">
                                        <i class="bi bi-diagram-3 me-1"></i> Circle Honour
                                    </span>
                                @elseif($honour->required_count <= 150)
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1.5 rounded-pill">
                                        <i class="bi bi-building me-1"></i> City Honour
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5 rounded-pill">
                                        <i class="bi bi-globe me-1"></i> National Honour
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-mono fw-bold text-primary fs-6">
                                {{ number_format($honour->required_count) }} {{ Str::plural('Member', $honour->required_count) }}
                            </td>
                            <td class="py-3 px-4">
                                @if($honour->is_active)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                                        <i class="bi bi-check-circle-fill me-1"></i> Active
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill">
                                        <i class="bi bi-dash-circle-fill me-1"></i> Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 fw-semibold text-secondary">
                                {{ $honour->sort_order }}
                            </td>
                            <td class="py-3 px-4 text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('admin.track1-growth.edit', $honour->id) }}" class="btn btn-outline-primary" title="Edit Honour">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.track1-growth.toggle-status', $honour->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn {{ $honour->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $honour->is_active ? 'Deactivate Honour' : 'Activate Honour' }}">
                                            <i class="bi {{ $honour->is_active ? 'bi-pause-fill' : 'bi-play-fill' }}"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.track1-growth.destroy', $honour->id) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this honour?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Delete Honour">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-graph-up display-6 d-block mb-3 text-secondary"></i>
                                <h6 class="fw-bold">No Track 1 Growth Honours Found</h6>
                                <p class="small mb-3">Click below to seed the standard 12 Growth Track Honours definitions.</p>
                                <form method="POST" action="{{ route('admin.track1-growth.seed') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="bi bi-arrow-repeat me-1"></i> Seed 12 Track 1 Growth Honours
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($honours->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $honours->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
