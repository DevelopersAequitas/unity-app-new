@extends('admin.layouts.app')

@section('title', 'All Ads')

@section('content')
<style>
    @media (min-width: 768px) {
        .bp-card-wrapper,
        .bp-table-wrapper {
            overflow: visible !important;
        }
    }
    .bp-action-dropdown {
        max-height: 380px !important;
        overflow-y: auto !important;
        z-index: 1060 !important;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">All Ads</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.ads.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Ad</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Filters & Search Card -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.ads.index') }}" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search ads by title, subtitle, or description...">
            </div>
            <div class="col-md-3">
                <select name="placement" class="form-select form-select-sm">
                    <option value="">All Placements</option>
                    <option value="timeline" @selected(($placement ?? '') == 'timeline')>Timeline</option>
                    <option value="dashboard" @selected(($placement ?? '') == 'dashboard')>Dashboard</option>
                    <option value="home" @selected(($placement ?? '') == 'home')>Home</option>
                    <option value="banner" @selected(($placement ?? '') == 'banner')>Banner</option>
                    <option value="popup" @selected(($placement ?? '') == 'popup')>Popup</option>
                    <option value="sidebar" @selected(($placement ?? '') == 'sidebar')>Sidebar</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active" @selected(($status ?? '') == 'active')>Active</option>
                    <option value="inactive" @selected(($status ?? '') == 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-secondary flex-grow-1"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ route('admin.ads.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Datatable Card -->
<div class="card border-0 shadow-sm bp-card-wrapper">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-secondary">Ads List</h6>
    </div>

    <div class="table-responsive bp-table-wrapper">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 80px;">Image</th>
                    <th>Ad Title</th>
                    <th>Placement</th>
                    <th>Status</th>
                    <th class="text-center">Views</th>
                    <th class="text-center">Clicks</th>
                    <th class="text-end" style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody class="border-top-0">
                @forelse($ads as $ad)
                    <tr>
                        <td>
                            @if($ad->image_url)
                                <img src="{{ $ad->image_url }}" alt="{{ $ad->title }}" class="img-thumbnail rounded" style="width: 48px; height: 48px; object-fit: cover;">
                            @else
                                <div class="bg-light rounded text-center d-flex align-items-center justify-content-center fw-bold text-secondary" style="width: 48px; height: 48px;">
                                    <i class="bi bi-megaphone fs-5"></i>
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $ad->title }}</div>
                            @if($ad->subtitle)
                                <span class="text-muted small">{{ $ad->subtitle }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                {{ ucfirst($ad->placement ?? 'unassigned') }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $ad->is_active ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' }} px-2.5 py-1.5">
                                {{ $ad->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-center fw-medium">{{ number_format($ad->views_count ?? 0) }}</td>
                        <td class="text-center fw-medium">{{ number_format($ad->clicks_count ?? 0) }}</td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">
                                    Actions
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm bp-action-dropdown">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('admin.ads.show', $ad) }}"><i class="bi bi-eye me-2 text-primary"></i>View Details</a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('admin.ads.edit', $ad) }}"><i class="bi bi-pencil me-2 text-primary"></i>Edit Ad</a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.ads.toggle-status', $ad) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi {{ $ad->is_active ? 'bi-eye-slash text-warning' : 'bi-eye text-success' }} me-2"></i>
                                                {{ $ad->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('admin.ads.destroy', $ad) }}" onsubmit="return confirm('Delete this ad permanently? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2 text-danger"></i>Delete Ad</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-megaphone fs-1 d-block mb-2"></i>
                            <div>No ads found.</div>
                            <span class="small text-muted">Create your first advertisement to get started.</span>
                            <div class="mt-3">
                                <a href="{{ route('admin.ads.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Ad</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($ads->hasPages())
        <div class="card-footer bg-white border-top py-3">
            {{ $ads->links() }}
        </div>
    @endif
</div>
@endsection
