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
                        <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=800&auto=format&fit=crop&q=60';">
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
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <form method="POST" action="{{ route('admin.store.serviceability.banners.update', $banner->id) }}" enctype="multipart/form-data" id="bannerEditForm_{{ $banner->id }}">
                            @csrf
                            <input type="hidden" name="image_data" id="bannerEditData_{{ $banner->id }}">
                            <div class="modal-header border-bottom">
                                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil text-primary me-2"></i>Edit Banner: {{ $banner->title }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Banner Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" value="{{ $banner->title }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Subtitle / Tagline</label>
                                    <input type="text" name="subtitle" class="form-control" value="{{ $banner->subtitle }}">
                                </div>

                                <!-- Banner Image Section -->
                                <div class="mb-3 border rounded-3 p-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label small fw-bold mb-0">Banner Image</label>
                                        <span class="text-muted extra-small">Recommended: 16:9 or 2:1 Aspect Ratio</span>
                                    </div>

                                    @if($banner->image_url)
                                    <div class="d-flex align-items-center gap-3 p-2 bg-white rounded border mb-2" id="bannerCurrentBox_{{ $banner->id }}">
                                        <img src="{{ $banner->image_url }}" alt="" class="rounded border" style="width: 80px; height: 45px; object-fit: cover;">
                                        <div class="small flex-grow-1 text-truncate">
                                            <div class="fw-semibold text-dark">Current Banner Image</div>
                                            <div class="text-muted extra-small text-truncate">{{ $banner->image_url }}</div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleBannerReplace('{{ $banner->id }}')">
                                            <i class="bi bi-arrow-repeat me-1"></i> Replace Image
                                        </button>
                                    </div>
                                    @endif

                                    <div id="bannerReplaceArea_{{ $banner->id }}" class="{{ $banner->image_url ? 'd-none' : '' }}">
                                        <ul class="nav nav-pills nav-fill mb-2 bg-white p-1 rounded border extra-small" role="tablist">
                                            <li class="nav-item">
                                                <button class="nav-link active py-1 extra-small fw-semibold" id="bannerTabDevBtn_{{ $banner->id }}" type="button" onclick="switchBannerMode('{{ $banner->id }}', 'device')">
                                                    <i class="bi bi-laptop me-1"></i> Browse Device
                                                </button>
                                            </li>
                                            <li class="nav-item">
                                                <button class="nav-link py-1 extra-small fw-semibold" id="bannerTabUrlBtn_{{ $banner->id }}" type="button" onclick="switchBannerMode('{{ $banner->id }}', 'url')">
                                                    <i class="bi bi-link-45deg me-1"></i> Image URL
                                                </button>
                                            </li>
                                        </ul>

                                        <div id="bannerDevBox_{{ $banner->id }}">
                                            <div class="img-adjuster-dropzone p-3 text-center mb-2" id="bannerDropzone_{{ $banner->id }}" onclick="document.getElementById('bannerFileInput_{{ $banner->id }}').click()">
                                                <i class="bi bi-cloud-arrow-up fs-3 text-primary d-block mb-1"></i>
                                                <span class="small fw-bold">Click to browse or drag & drop</span>
                                                <p class="text-muted extra-small mb-0">JPG, PNG, WEBP up to 10MB</p>
                                                <input type="file" id="bannerFileInput_{{ $banner->id }}" name="image" accept="image/*" class="d-none">
                                            </div>
                                        </div>

                                        <div id="bannerUrlBox_{{ $banner->id }}" class="mb-2 d-none">
                                            <div class="input-group input-group-sm">
                                                <input type="url" name="image_url" id="bannerUrlInput_{{ $banner->id }}" class="form-control" value="{{ str_starts_with($banner->image_url, 'http') ? $banner->image_url : '' }}" placeholder="https://example.com/banner.jpg">
                                                <button class="btn btn-outline-primary" type="button" onclick="loadBannerUrl('{{ $banner->id }}')">
                                                    Load & Adjust
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Cropper Studio -->
                                        <div id="bannerStudio_{{ $banner->id }}" class="d-none border rounded p-2 bg-white mb-2">
                                            <div class="row g-2">
                                                <div class="col-8">
                                                    <div class="cropper-container-box" style="height: 220px;">
                                                        <img id="bannerImgEl_{{ $banner->id }}" src="" alt="Crop" style="max-width: 100%;">
                                                    </div>
                                                </div>
                                                <div class="col-4 d-flex flex-column justify-content-between">
                                                    <div>
                                                        <label class="form-label extra-small fw-bold text-muted text-uppercase mb-1">Preview</label>
                                                        <div class="crop-preview-box mx-auto mb-2" style="width: 110px; height: 60px;">
                                                            <img id="bannerPreviewBox_{{ $banner->id }}" src="" alt="Preview" style="object-fit: cover; width: 100%; height: 100%;">
                                                        </div>
                                                        <div class="d-flex gap-1 mb-1 justify-content-center">
                                                            <button type="button" class="btn btn-xs btn-light border" title="Zoom Out" onclick="window.bannerCroppers['{{ $banner->id }}'].zoom(-0.1)">-</button>
                                                            <button type="button" class="btn btn-xs btn-light border" title="Zoom In" onclick="window.bannerCroppers['{{ $banner->id }}'].zoom(0.1)">+</button>
                                                            <button type="button" class="btn btn-xs btn-light border" title="Rotate" onclick="window.bannerCroppers['{{ $banner->id }}'].rotate(90)">⟳</button>
                                                            <button type="button" class="btn btn-xs btn-light border" title="Reset" onclick="window.bannerCroppers['{{ $banner->id }}'].reset()">↺</button>
                                                        </div>
                                                    </div>
                                                    <button type="button" class="btn btn-link text-danger p-0 extra-small" onclick="window.bannerCroppers['{{ $banner->id }}'].clear()">Clear</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
                            <div class="modal-footer bg-light border-top">
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2 me-1"></i> Save Changes</button>
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
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="{{ route('admin.store.serviceability.banners.store') }}" enctype="multipart/form-data" id="bannerCreateForm">
                @csrf
                <input type="hidden" name="image_data" id="bannerCreateImageData">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-plus-lg text-primary me-2"></i>Create Store Banner</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Banner Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Diwali Executive Gifting Festival" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subtitle / Caption</label>
                        <input type="text" name="subtitle" class="form-control" placeholder="e.g. Redeem premium luxury watches with Earned Coins">
                    </div>

                    <!-- Banner Image Section with Device Browse + Live Adjuster -->
                    <div class="mb-3 border rounded-3 p-3 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-bold mb-0">Banner Image</label>
                            <span class="text-muted extra-small">Recommended: 16:9 or 2:1 Aspect Ratio</span>
                        </div>

                        <!-- Source Nav -->
                        <ul class="nav nav-pills nav-fill mb-2 bg-white p-1 rounded border extra-small" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active py-1 extra-small fw-semibold" id="bannerCreateTabDev" type="button" onclick="switchBannerCreateMode('device')">
                                    <i class="bi bi-laptop me-1"></i> Browse Device / Upload
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link py-1 extra-small fw-semibold" id="bannerCreateTabUrl" type="button" onclick="switchBannerCreateMode('url')">
                                    <i class="bi bi-link-45deg me-1"></i> Paste Image Web URL
                                </button>
                            </li>
                        </ul>

                        <!-- Device Dropzone -->
                        <div id="bannerCreateDevBox">
                            <div class="img-adjuster-dropzone p-3 text-center mb-2" id="bannerCreateDropzone" onclick="document.getElementById('bannerCreateFileInput').click()">
                                <i class="bi bi-cloud-arrow-up fs-3 text-primary d-block mb-1"></i>
                                <span class="small fw-bold">Click to browse or drag & drop</span>
                                <p class="text-muted extra-small mb-0">JPG, PNG, WEBP, GIF up to 10MB</p>
                                <input type="file" id="bannerCreateFileInput" name="image" accept="image/*" class="d-none">
                            </div>
                        </div>

                        <!-- URL Box -->
                        <div id="bannerCreateUrlBox" class="mb-2 d-none">
                            <div class="input-group input-group-sm">
                                <input type="url" name="image_url" id="bannerCreateUrlInput" class="form-control" placeholder="https://example.com/banner.jpg">
                                <button class="btn btn-outline-primary" type="button" onclick="loadBannerCreateUrl()">
                                    Load & Adjust
                                </button>
                            </div>
                        </div>

                        <!-- Cropper Studio -->
                        <div id="bannerCreateStudio" class="d-none border rounded p-3 bg-white mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-bold text-dark"><i class="bi bi-crop me-1 text-primary"></i> Adjust & Crop Banner Before Upload</span>
                                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 extra-small" onclick="window.bannerCreateCropper.clear()">
                                    Change Image
                                </button>
                            </div>
                            <div class="row g-2">
                                <div class="col-8">
                                    <div class="cropper-container-box" style="height: 240px;">
                                        <img id="bannerCreateImgEl" src="" alt="Crop" style="max-width: 100%;">
                                    </div>
                                </div>
                                <div class="col-4 d-flex flex-column justify-content-between">
                                    <div>
                                        <label class="form-label extra-small fw-bold text-muted text-uppercase mb-1">Live Banner Preview</label>
                                        <div class="crop-preview-box mx-auto mb-2" style="width: 120px; height: 68px;">
                                            <img id="bannerCreatePreviewBox" src="" alt="Preview" style="object-fit: cover; width: 100%; height: 100%;">
                                        </div>
                                        <div class="d-flex gap-1 mb-1 justify-content-center">
                                            <button type="button" class="btn btn-sm btn-light border" title="Zoom Out" onclick="window.bannerCreateCropper.zoom(-0.1)">-</button>
                                            <button type="button" class="btn btn-sm btn-light border" title="Zoom In" onclick="window.bannerCreateCropper.zoom(0.1)">+</button>
                                            <button type="button" class="btn btn-sm btn-light border" title="Rotate" onclick="window.bannerCreateCropper.rotate(90)">⟳</button>
                                            <button type="button" class="btn btn-sm btn-light border" title="Reset" onclick="window.bannerCreateCropper.reset()">↺</button>
                                        </div>
                                    </div>
                                    <div class="text-muted extra-small text-center">16:9 Banner format</div>
                                </div>
                            </div>
                        </div>
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
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Create Banner</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/cropper.min.css') }}">
<style>
.img-adjuster-dropzone {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    background-color: #f8fafc;
    transition: all 0.2s ease;
    cursor: pointer;
}
.img-adjuster-dropzone:hover, .img-adjuster-dropzone.border-primary {
    border-color: #3b82f6 !important;
    background-color: #eff6ff !important;
}
.cropper-container-box {
    background-color: #0f172a;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
.crop-preview-box {
    border-radius: 8px;
    border: 2px solid #cbd5e1;
    overflow: hidden;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/cropper.min.js') }}"></script>
<script src="{{ asset('js/store-image-adjuster.js') }}"></script>
<script>
window.bannerCroppers = {};

document.addEventListener('DOMContentLoaded', function() {
    window.bannerCreateCropper = StoreImageAdjuster.init({
        id: 'banner_create',
        fileInputId: 'bannerCreateFileInput',
        urlInputId: 'bannerCreateUrlInput',
        hiddenDataId: 'bannerCreateImageData',
        dropzoneId: 'bannerCreateDropzone',
        cropperContainerId: 'bannerCreateStudio',
        imageElementId: 'bannerCreateImgEl',
        previewBoxId: 'bannerCreatePreviewBox',
        aspectRatio: 16 / 9,
        formId: 'bannerCreateForm'
    });

    @foreach($banners as $b)
    (function(bId) {
        const modalEl = document.getElementById('editBannerModal' + bId);
        if (modalEl) {
            modalEl.addEventListener('show.bs.modal', function() {
                if (!window.bannerCroppers[bId]) {
                    window.bannerCroppers[bId] = StoreImageAdjuster.init({
                        id: 'banner_edit_' + bId,
                        fileInputId: 'bannerFileInput_' + bId,
                        urlInputId: 'bannerUrlInput_' + bId,
                        hiddenDataId: 'bannerEditData_' + bId,
                        dropzoneId: 'bannerDropzone_' + bId,
                        cropperContainerId: 'bannerStudio_' + bId,
                        imageElementId: 'bannerImgEl_' + bId,
                        previewBoxId: 'bannerPreviewBox_' + bId,
                        aspectRatio: 16 / 9,
                        formId: 'bannerEditForm_' + bId
                    });
                }
            });
        }
    })('{{ $b->id }}');
    @endforeach
});

function switchBannerCreateMode(mode) {
    const devBox = document.getElementById('bannerCreateDevBox');
    const urlBox = document.getElementById('bannerCreateUrlBox');
    const tabDev = document.getElementById('bannerCreateTabDev');
    const tabUrl = document.getElementById('bannerCreateTabUrl');

    if (mode === 'device') {
        if (devBox) devBox.classList.remove('d-none');
        if (urlBox) urlBox.classList.add('d-none');
        if (tabDev) tabDev.classList.add('active');
        if (tabUrl) tabUrl.classList.remove('active');
    } else {
        if (devBox) devBox.classList.add('d-none');
        if (urlBox) urlBox.classList.remove('d-none');
        if (tabDev) tabDev.classList.remove('active');
        if (tabUrl) tabUrl.classList.add('active');
    }
}

function loadBannerCreateUrl() {
    const url = document.getElementById('bannerCreateUrlInput').value.trim();
    if (!url) {
        alert('Please enter an image URL.');
        return;
    }
    if (window.bannerCreateCropper) {
        window.bannerCreateCropper.loadFromUrl(url);
    }
}

function toggleBannerReplace(bId) {
    const area = document.getElementById('bannerReplaceArea_' + bId);
    if (area) {
        area.classList.toggle('d-none');
    }
}

function switchBannerMode(bId, mode) {
    const devBox = document.getElementById('bannerDevBox_' + bId);
    const urlBox = document.getElementById('bannerUrlBox_' + bId);
    const tabDev = document.getElementById('bannerTabDevBtn_' + bId);
    const tabUrl = document.getElementById('bannerTabUrlBtn_' + bId);

    if (mode === 'device') {
        if (devBox) devBox.classList.remove('d-none');
        if (urlBox) urlBox.classList.add('d-none');
        if (tabDev) tabDev.classList.add('active');
        if (tabUrl) tabUrl.classList.remove('active');
    } else {
        if (devBox) devBox.classList.add('d-none');
        if (urlBox) urlBox.classList.remove('d-none');
        if (tabDev) tabDev.classList.remove('active');
        if (tabUrl) tabUrl.classList.add('active');
    }
}

function loadBannerUrl(bId) {
    const input = document.getElementById('bannerUrlInput_' + bId);
    const url = input ? input.value.trim() : '';
    if (!url) {
        alert('Please enter an image URL.');
        return;
    }
    if (window.bannerCroppers[bId]) {
        window.bannerCroppers[bId].loadFromUrl(url);
    }
}
</script>
@endpush
