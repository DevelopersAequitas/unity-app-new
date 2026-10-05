@extends('admin.layouts.app')

@section('title', 'Peers Store — Store Banners')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Store Banners</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-images text-primary"></i> Promotional Banners & Hero Carousels
            </h1>
        </div>
        <div>
            <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addBannerModal">
                <i class="bi bi-plus-lg"></i> Add New Banner
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Banners Grid --}}
    <div class="row g-4">
        @forelse($banners as $banner)
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm border-0 h-100 overflow-hidden {{ !$banner->is_active ? 'opacity-75' : '' }}">
                    <div class="position-relative bg-dark" style="height: 160px;">
                        <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" style="width: 100%; height: 100%; object-fit: cover;">
                        <div class="position-absolute top-0 end-0 p-2">
                            <span class="badge {{ $banner->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $banner->is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </div>
                        <div class="position-absolute bottom-0 start-0 p-2 bg-gradient bg-dark bg-opacity-75 text-white w-100">
                            <span class="small fw-semibold"><i class="bi bi-sort-numeric-down"></i> Sort Order: {{ $banner->sort_order }}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title fw-bold text-dark mb-1">{{ $banner->title }}</h5>
                        @if($banner->subtitle)
                            <p class="text-muted small mb-2">{{ $banner->subtitle }}</p>
                        @endif
                        <div class="small mb-3">
                            <span class="text-muted">Target Action:</span>
                            <code class="text-primary">{{ $banner->action_type ?: 'None' }}</code>
                            @if($banner->action_value)
                                <div class="text-truncate text-muted" title="{{ $banner->action_value }}">{{ $banner->action_value }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center pb-3">
                        <form method="POST" action="{{ route('admin.store.serviceability.banners.status', $banner->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm {{ $banner->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                <i class="bi {{ $banner->is_active ? 'bi-pause-fill' : 'bi-play-fill' }}"></i>
                                {{ $banner->is_active ? 'Pause' : 'Activate' }}
                            </button>
                        </form>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editBannerModal{{ $banner->id }}">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <form method="POST" action="{{ route('admin.store.serviceability.banners.destroy', $banner->id) }}" onsubmit="return confirm('Delete this banner permanently?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Edit Banner Modal --}}
            <div class="modal fade" id="editBannerModal{{ $banner->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.store.serviceability.banners.update', $banner->id) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="bi bi-pencil text-primary"></i> Edit Banner: {{ $banner->title }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Banner Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" value="{{ $banner->title }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Subtitle / Tagline</label>
                                    <input type="text" name="subtitle" class="form-control" value="{{ $banner->subtitle }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Replace Image File (Optional)</label>
                                    <input type="file" name="image" class="form-control" accept="image/*">
                                    <small class="text-muted">Recommended aspect ratio: 16:9 or 2:1</small>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Action Type</label>
                                        <select name="action_type" class="form-select">
                                            <option value="" {{ empty($banner->action_type) ? 'selected' : '' }}>None (Display only)</option>
                                            <option value="product" {{ $banner->action_type === 'product' ? 'selected' : '' }}>Open Product</option>
                                            <option value="category" {{ $banner->action_type === 'category' ? 'selected' : '' }}>Open Category</option>
                                            <option value="url" {{ $banner->action_type === 'url' ? 'selected' : '' }}>External Web Link</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold">Sort Order</label>
                                        <input type="number" name="sort_order" class="form-control" value="{{ $banner->sort_order }}" min="0">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Action Value / Target ID / URL</label>
                                    <input type="text" name="action_value" class="form-control" value="{{ $banner->action_value }}" placeholder="e.g. Product ID, Category ID, or URL">
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editBannerActive{{ $banner->id }}" {{ $banner->is_active ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="editBannerActive{{ $banner->id }}">Banner Active</label>
                                </div>
                            </div>
                            <div class="modal-footer bg-light">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5 text-muted">
                <i class="bi bi-images fs-1 d-block mb-2 text-secondary"></i>
                No store promotional banners created yet.
            </div>
        @endforelse
    </div>
</div>

{{-- Add Banner Modal --}}
<div class="modal fade" id="addBannerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.store.serviceability.banners.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-lg text-primary"></i> Create Store Banner</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Banner Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Diwali Executive Gifting Festival" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subtitle / Caption</label>
                        <input type="text" name="subtitle" class="form-control" placeholder="e.g. Redeem premium luxury watches with Earned Coins">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Banner Image File <span class="text-danger">*</span></label>
                        <input type="file" name="image" class="form-control" accept="image/*" required>
                        <small class="text-muted">Recommended: 1200x600px PNG/JPG</small>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Action Type</label>
                            <select name="action_type" class="form-select">
                                <option value="">None (Display only)</option>
                                <option value="product">Open Product</option>
                                <option value="category">Open Category</option>
                                <option value="url">External Web Link</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" value="0" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Action Value / URL</label>
                        <input type="text" name="action_value" class="form-control" placeholder="e.g. Product ID, Slug, or External URL">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="newBannerActive" checked>
                        <label class="form-check-label fw-semibold" for="newBannerActive">Immediately Active on Mobile App</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create Banner</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
