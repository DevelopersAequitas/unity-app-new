@extends('admin.layouts.app')

@section('title', 'Media Library - Peers Global Web')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(6, 182, 212, 0.12); color: #06b6d4; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-images me-1"></i> Peers Global Website
                </span>
                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-cloud-check me-1"></i> Public Storage Active
                </span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Website Media Library</h1>
            <p class="text-muted small mb-0 mt-0.5">High definition 4K videos, ambient backdrops, brand assets, and web banners</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.web.page-media.index') }}" class="btn btn-outline-secondary btn-sm rounded-3 px-3 py-2 fw-semibold">
                <i class="bi bi-collection-play me-1"></i> Page Slots
            </a>
            <button type="button" class="btn btn-primary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs fw-semibold" data-bs-toggle="modal" data-bs-target="#uploadMediaModal">
                <i class="bi bi-cloud-arrow-up-fill"></i>
                <span>Upload Media Asset</span>
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-xs mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Filter & Search Bar --}}
    <div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
        <form method="GET" action="{{ route('admin.web.media.index') }}" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control bg-light border-start-0" placeholder="Search by media title or file name...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="type" class="form-select form-select-sm bg-light" onchange="this.form.submit()">
                    <option value="all" @selected(request('type') === 'all' || !request('type'))>All Media Types</option>
                    <option value="video" @selected(request('type') === 'video')>Videos Only (.mp4, .webm)</option>
                    <option value="image" @selected(request('type') === 'image')>Images Only (.png, .jpg, .webp)</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="category" class="form-select form-select-sm bg-light" onchange="this.form.submit()">
                    <option value="all" @selected(request('category') === 'all' || !request('category'))>All Categories</option>
                    <option value="home" @selected(request('category') === 'home')>Homepage</option>
                    <option value="brand" @selected(request('category') === 'brand')>Brand & Logos</option>
                    <option value="events" @selected(request('category') === 'events')>Events & Conclaves</option>
                    <option value="leadership" @selected(request('category') === 'leadership')>Leadership</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">Filter</button>
            </div>
        </form>
    </div>

    {{-- Grid of Media Assets --}}
    <div class="row g-4 mb-4">
        @forelse($mediaAssets as $media)
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card border-0 shadow-xs rounded-4 p-4 bg-white h-100 d-flex flex-column justify-content-between hover-shadow transition">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="badge rounded-pill px-2.5 py-1" style="background: {{ $media->file_type === 'video' ? 'rgba(99, 102, 241, 0.12)' : 'rgba(16, 185, 129, 0.12)' }}; color: {{ $media->file_type === 'video' ? '#6366f1' : '#10b981' }}; font-weight: 600;">
                                <i class="bi bi-{{ $media->file_type === 'video' ? 'film' : 'image' }} me-1"></i> {{ strtoupper($media->file_type) }}
                            </span>
                            <span class="badge bg-light text-secondary border font-monospace text-uppercase">{{ $media->category }}</span>
                        </div>

                        <div class="rounded-3 p-3 text-center mb-3 bg-light d-flex align-items-center justify-content-center position-relative overflow-hidden" style="height: 140px; border: 1px dashed #cbd5e1;">
                            @if($media->file_type === 'video')
                                <i class="bi bi-play-circle-fill text-primary" style="font-size: 3rem; opacity: 0.85;"></i>
                            @else
                                <i class="bi bi-file-earmark-image text-success" style="font-size: 3rem; opacity: 0.85;"></i>
                            @endif
                        </div>

                        <h3 class="h6 fw-bold text-dark mb-1 line-clamp-1" title="{{ $media->title }}">{{ $media->title }}</h3>
                        <p class="text-muted font-monospace small mb-3 text-truncate" style="font-size: 0.75rem;" title="{{ $media->file_path }}">{{ $media->file_path }}</p>
                    </div>

                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        <span class="text-muted small">{{ $media->created_at?->format('d M Y') ?? '—' }}</span>
                        <div class="d-flex align-items-center gap-1.5">
                            <button type="button" class="btn btn-sm btn-light border" onclick="navigator.clipboard.writeText('{{ $media->url }}'); alert('Media URL copied: {{ $media->url }}');" title="Copy URL">
                                <i class="bi bi-clipboard"></i>
                            </button>
                            <a href="{{ $media->url }}" target="_blank" class="btn btn-sm btn-light border fw-semibold" title="Preview">
                                <i class="bi bi-eye"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.web.media.destroy', $media->id) }}" onsubmit="return confirm('Delete this media asset?');" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-light border text-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card border-0 shadow-xs rounded-4 p-5 bg-white text-center">
                    <i class="bi bi-images display-4 text-secondary mb-3 opacity-50"></i>
                    <h5 class="fw-bold text-dark mb-1">No Media Assets Found</h5>
                    <p class="text-muted small mb-3">Upload videos or images to make them available across the Peers Global website.</p>
                    <div>
                        <button type="button" class="btn btn-primary btn-sm px-4 py-2 fw-semibold rounded-3" data-bs-toggle="modal" data-bs-target="#uploadMediaModal">
                            <i class="bi bi-cloud-arrow-up-fill me-1"></i> Upload First Asset
                        </button>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    @if($mediaAssets instanceof \Illuminate\Pagination\LengthAwarePaginator && $mediaAssets->hasPages())
        <div class="d-flex justify-content-center">
            {{ $mediaAssets->links() }}
        </div>
    @endif
</div>

{{-- Upload Media Modal --}}
<div class="modal fade" id="uploadMediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('admin.web.media.store') }}" enctype="multipart/form-data" class="modal-content rounded-4 border-0 shadow">
            @csrf
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Upload Website Media Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Asset Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control form-control-sm" required placeholder="e.g. Cyber Earth Network 4K Loop">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Media Type <span class="text-danger">*</span></label>
                        <select name="file_type" class="form-select form-select-sm" required>
                            <option value="video" selected>Video (.mp4, .webm, .mov)</option>
                            <option value="image">Image / Graphic (.png, .jpg, .webp, .svg)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Category</label>
                        <select name="category" class="form-select form-select-sm">
                            <option value="general" selected>General</option>
                            <option value="home">Homepage</option>
                            <option value="brand">Brand & Logos</option>
                            <option value="events">Events & Conclaves</option>
                            <option value="leadership">Leadership</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3 p-3 bg-light rounded-3 border">
                    <label class="form-label small fw-semibold">Choose File (Up to 100MB)</label>
                    <input type="file" name="media_file" class="form-control form-control-sm" accept="video/*,image/*">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Or Enter External Video / Image URL</label>
                    <input type="text" name="media_url" class="form-control form-control-sm font-monospace" placeholder="https://cdn.example.com/asset.mp4">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">Upload Asset</button>
            </div>
        </form>
    </div>
</div>
@endsection
