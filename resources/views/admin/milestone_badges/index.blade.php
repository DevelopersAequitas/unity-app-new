@extends('admin.layouts.app')

@section('title', 'Milestone Badges')

@section('content')
<div class="container-fluid py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-award-fill text-primary"></i> Dynamic Milestone Badges
            </h4>
            <p class="text-muted small mb-0">Manage badge thresholds and rewards for Life Impact, Coins, and Member Introductions.</p>
        </div>
        <a href="{{ route('admin.milestone-badges.create') }}" class="btn btn-primary shadow-sm fw-semibold">
            <i class="bi bi-plus-lg me-1"></i> Add New Badge
        </a>
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

    {{-- Filter Card --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form id="milestoneBadgesFiltersForm" method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="badgeSearch" class="form-label fw-semibold small text-secondary">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="badgeSearch" name="q" class="form-control" placeholder="Search by title or description..." value="{{ $filters['search'] ?? '' }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="typeFilter" class="form-label fw-semibold small text-secondary">Badge Category</label>
                    <select id="typeFilter" name="type" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <option value="life_impact" @selected(($filters['type'] ?? '') === 'life_impact')>❤️ Life Impact Badges</option>
                        <option value="coins" @selected(($filters['type'] ?? '') === 'coins')>🪙 Coin Badges</option>
                        <option value="member_introduction" @selected(($filters['type'] ?? '') === 'member_introduction')>👥 Member Introduction Badges</option>
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
                    <a href="{{ route('admin.milestone-badges.index') }}" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Category Pills --}}
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <a href="{{ route('admin.milestone-badges.index', array_merge(request()->query(), ['type' => ''])) }}" class="btn btn-sm {{ empty($filters['type']) ? 'btn-primary' : 'btn-light border' }} fw-semibold">
            All Badges
        </a>
        <a href="{{ route('admin.milestone-badges.index', array_merge(request()->query(), ['type' => 'life_impact'])) }}" class="btn btn-sm {{ ($filters['type'] ?? '') === 'life_impact' ? 'btn-primary' : 'btn-light border' }} fw-semibold">
            ❤️ Life Impact
        </a>
        <a href="{{ route('admin.milestone-badges.index', array_merge(request()->query(), ['type' => 'coins'])) }}" class="btn btn-sm {{ ($filters['type'] ?? '') === 'coins' ? 'btn-primary' : 'btn-light border' }} fw-semibold">
            🪙 Coins
        </a>
        <a href="{{ route('admin.milestone-badges.index', array_merge(request()->query(), ['type' => 'member_introduction'])) }}" class="btn btn-sm {{ ($filters['type'] ?? '') === 'member_introduction' ? 'btn-primary' : 'btn-light border' }} fw-semibold">
            👥 Member Introductions
        </a>
    </div>

    {{-- Badges Table Card --}}
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-sm">
                <thead class="table-light border-bottom">
                    <tr>
                        <th class="py-3 px-4 text-secondary small font-mono" style="width: 60px;">#</th>
                        <th class="py-3 px-4 text-secondary small" style="width: 80px;">Icon</th>
                        <th class="py-3 px-4 text-secondary small">Badge Title & Description</th>
                        <th class="py-3 px-4 text-secondary small">Category</th>
                        <th class="py-3 px-4 text-secondary small">Required Threshold</th>
                        <th class="py-3 px-4 text-secondary small">Status</th>
                        <th class="py-3 px-4 text-secondary small">Sort Order</th>
                        <th class="py-3 px-4 text-secondary small text-end" style="width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($badges as $index => $badge)
                        <tr>
                            <td class="py-3 px-4 fw-semibold text-muted">
                                {{ $badges->firstItem() + $index }}
                            </td>
                            <td class="py-3 px-4">
                                <a href="{{ route('admin.milestone-badges.edit', $badge->id) }}" class="d-inline-block">
                                    @if($badge->badge_image_url)
                                        <img src="{{ $badge->badge_image_url }}" alt="{{ $badge->title }}" class="rounded border p-1 bg-white" style="width: 44px; height: 44px; object-fit: contain;" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'44\' height=\'44\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%236c757d\' stroke-width=\'2\'><circle cx=\'12\' cy=\'8\' r=\'6\'/><path d=\'M15.477 12.89 17 22l-5-3-5 3 1.523-9.11\'/></svg>';">
                                    @else
                                        <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted" style="width: 44px; height: 44px;">
                                            <i class="bi bi-award fs-5"></i>
                                        </div>
                                    @endif
                                </a>
                            </td>
                            <td class="py-3 px-4">
                                <a href="{{ route('admin.milestone-badges.edit', $badge->id) }}" class="text-decoration-none text-dark d-block">
                                    <div class="fw-bold text-dark">{{ $badge->title }}</div>
                                    @if($badge->description)
                                        <div class="text-muted small text-truncate" style="max-width: 320px;">{{ $badge->description }}</div>
                                    @endif
                                </a>
                            </td>
                            <td class="py-3 px-4">
                                @if($badge->type === 'life_impact')
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5 rounded-pill">
                                        <i class="bi bi-heart-pulse-fill me-1"></i> Life Impact
                                    </span>
                                @elseif($badge->type === 'coins')
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1.5 rounded-pill">
                                        <i class="bi bi-coin me-1"></i> Coins
                                    </span>
                                @else
                                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-2.5 py-1.5 rounded-pill">
                                        <i class="bi bi-people-fill me-1"></i> Member Intro
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 fw-mono fw-bold text-primary fs-6">
                                {{ number_format($badge->required_count) }}
                            </td>
                            <td class="py-3 px-4">
                                @if($badge->is_active)
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
                                {{ $badge->sort_order }}
                            </td>
                            <td class="py-3 px-4 text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('admin.milestone-badges.edit', $badge->id) }}" class="btn btn-outline-primary" title="Edit Badge">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.milestone-badges.toggle-status', $badge->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn {{ $badge->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" title="{{ $badge->is_active ? 'Deactivate Badge' : 'Activate Badge' }}">
                                            <i class="bi {{ $badge->is_active ? 'bi-pause-fill' : 'bi-play-fill' }}"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.milestone-badges.destroy', $badge->id) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this badge?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Delete Badge">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-award display-6 d-block mb-3 text-secondary"></i>
                                <h6 class="fw-bold">No Milestone Badges Found</h6>
                                <p class="small mb-3">Get started by adding your first badge definition.</p>
                                <a href="{{ route('admin.milestone-badges.create') }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg me-1"></i> Create Badge
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($badges->hasPages())
            <div class="card-footer bg-white border-top py-3">
                {{ $badges->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
