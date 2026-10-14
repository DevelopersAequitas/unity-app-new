@extends('admin.layouts.app')

@section('title', 'Categories Management — Peers Store')

@section('content')
<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-tags me-2 text-primary"></i>Merchandise Categories
            </h1>
            <p class="text-muted small mb-0">Organize merchandise, apparel, office stationery, and digital collections.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
            <i class="bi bi-plus-circle me-1"></i> Add New Category
        </button>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <!-- Categories Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <form action="{{ route('admin.store.categories.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search category by name or slug..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
                    @if(request('search'))
                        <a href="{{ route('admin.store.categories.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Products</th>
                            <th>Sort Order</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                        <tr>
                            <td>
                                @if($category->image_url)
                                    <img src="{{ $category->image_url }}" alt="{{ $category->name }}" class="rounded border" style="width: 44px; height: 44px; object-fit: cover;">
                                @else
                                    <div class="bg-light rounded border d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                        <i class="bi bi-tag text-muted"></i>
                                    </div>
                                @endif
                            </td>
                            <td class="fw-bold text-dark">{{ $category->name }}</td>
                            <td><code>{{ $category->slug }}</code></td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $category->products_count }} items</span>
                            </td>
                            <td>{{ $category->sort_order }}</td>
                            <td>
                                <span class="badge {{ $category->status === 'ACTIVE' ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $category->status }}
                                </span>
                            </td>
                            <td class="small text-muted">{{ $category->created_at ? $category->created_at->format('d M Y') : 'N/A' }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" data-bs-toggle="modal" data-bs-target="#editCategoryModal{{ $category->id }}">
                                    Edit
                                </button>
                                <form action="{{ route('admin.store.categories.delete', $category->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <!-- Edit Category Modal -->
                        <div class="modal fade" id="editCategoryModal{{ $category->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content border-0 shadow">
                                    <form action="{{ route('admin.store.categories.update', $category->id) }}" method="POST" enctype="multipart/form-data" id="catEditForm_{{ $category->id }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="image_data" id="catEditImageData_{{ $category->id }}">
                                        <div class="modal-header border-bottom">
                                            <h5 class="modal-title fw-bold text-dark">
                                                <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Category: {{ $category->name }}
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-7">
                                                    <label class="form-label small fw-bold">Category Name *</label>
                                                    <input type="text" name="name" class="form-control" value="{{ $category->name }}" required>
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label small fw-bold">Slug</label>
                                                    <input type="text" name="slug" class="form-control" value="{{ $category->slug }}">
                                                </div>
                                            </div>

                                            <div class="row g-3 mb-3">
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">Sort Order</label>
                                                    <input type="number" name="sort_order" class="form-control" value="{{ $category->sort_order }}">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small fw-bold">Status</label>
                                                    <select name="status" class="form-select">
                                                        <option value="ACTIVE" {{ $category->status === 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                                                        <option value="INACTIVE" {{ $category->status === 'INACTIVE' ? 'selected' : '' }}>INACTIVE</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Category Image Section -->
                                            <div class="mb-3 border rounded-3 p-3 bg-light">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <label class="form-label small fw-bold mb-0">Category Image</label>
                                                    <span class="text-muted extra-small">Recommended: 1:1 Square</span>
                                                </div>

                                                @if($category->image_url)
                                                <div class="d-flex align-items-center gap-3 p-2 bg-white rounded border mb-2" id="catCurrentBox_{{ $category->id }}">
                                                    <img src="{{ $category->image_url }}" alt="" class="rounded border" style="width: 48px; height: 48px; object-fit: cover;">
                                                    <div class="small flex-grow-1 text-truncate">
                                                        <div class="fw-semibold text-dark">Current Image</div>
                                                        <div class="text-muted extra-small text-truncate">{{ $category->image_url }}</div>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleCatReplace('{{ $category->id }}')">
                                                        <i class="bi bi-arrow-repeat me-1"></i> Replace
                                                    </button>
                                                </div>
                                                @endif

                                                <div id="catReplaceArea_{{ $category->id }}" class="{{ $category->image_url ? 'd-none' : '' }}">
                                                    <!-- Nav tabs for browse vs url -->
                                                    <ul class="nav nav-pills nav-fill mb-2 bg-white p-1 rounded border extra-small" role="tablist">
                                                        <li class="nav-item">
                                                            <button class="nav-link active py-1 extra-small fw-semibold" id="catTabDevBtn_{{ $category->id }}" type="button" onclick="switchCatMode('{{ $category->id }}', 'device')">
                                                                <i class="bi bi-laptop me-1"></i> Browse Device
                                                            </button>
                                                        </li>
                                                        <li class="nav-item">
                                                            <button class="nav-link py-1 extra-small fw-semibold" id="catTabUrlBtn_{{ $category->id }}" type="button" onclick="switchCatMode('{{ $category->id }}', 'url')">
                                                                <i class="bi bi-link-45deg me-1"></i> Image URL
                                                            </button>
                                                        </li>
                                                    </ul>

                                                    <!-- Dropzone -->
                                                    <div id="catDevBox_{{ $category->id }}">
                                                        <div class="img-adjuster-dropzone p-3 text-center mb-2" id="catDropzone_{{ $category->id }}" onclick="document.getElementById('catFileInput_{{ $category->id }}').click()">
                                                            <i class="bi bi-cloud-arrow-up fs-4 text-primary d-block mb-1"></i>
                                                            <span class="small fw-bold">Click to browse or drag & drop</span>
                                                            <p class="text-muted extra-small mb-0">JPG, PNG, WEBP up to 10MB</p>
                                                            <input type="file" id="catFileInput_{{ $category->id }}" name="image_file" accept="image/*" class="d-none">
                                                        </div>
                                                    </div>

                                                    <!-- URL box -->
                                                    <div id="catUrlBox_{{ $category->id }}" class="mb-2 d-none">
                                                        <div class="input-group input-group-sm">
                                                            <input type="url" name="image_url" id="catUrlInput_{{ $category->id }}" class="form-control" value="{{ $category->image_url }}" placeholder="https://images.unsplash.com/...">
                                                            <button class="btn btn-outline-primary" type="button" onclick="loadCatUrl('{{ $category->id }}')">
                                                                Load & Adjust
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <!-- Cropper Studio -->
                                                    <div id="catStudio_{{ $category->id }}" class="d-none border rounded p-2 bg-white mb-2">
                                                        <div class="row g-2">
                                                            <div class="col-8">
                                                                <div class="cropper-container-box" style="height: 220px;">
                                                                    <img id="catImgEl_{{ $category->id }}" src="" alt="Crop" style="max-width: 100%;">
                                                                </div>
                                                            </div>
                                                            <div class="col-4 d-flex flex-column justify-content-between">
                                                                <div>
                                                                    <label class="form-label extra-small fw-bold text-muted text-uppercase mb-1">Preview</label>
                                                                    <div class="crop-preview-box mx-auto mb-2" style="width: 70px; height: 70px;">
                                                                        <img id="catPreviewBox_{{ $category->id }}" src="" alt="Preview">
                                                                    </div>
                                                                    <div class="d-flex gap-1 mb-1 justify-content-center">
                                                                        <button type="button" class="btn btn-xs btn-light border" title="Zoom Out" onclick="window.catCroppers['{{ $category->id }}'].zoom(-0.1)">-</button>
                                                                        <button type="button" class="btn btn-xs btn-light border" title="Zoom In" onclick="window.catCroppers['{{ $category->id }}'].zoom(0.1)">+</button>
                                                                        <button type="button" class="btn btn-xs btn-light border" title="Rotate" onclick="window.catCroppers['{{ $category->id }}'].rotate(90)">⟳</button>
                                                                        <button type="button" class="btn btn-xs btn-light border" title="Reset" onclick="window.catCroppers['{{ $category->id }}'].reset()">↺</button>
                                                                    </div>
                                                                </div>
                                                                <button type="button" class="btn btn-link text-danger p-0 extra-small" onclick="window.catCroppers['{{ $category->id }}'].clear()">Clear</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small fw-bold">Description</label>
                                                <textarea name="description" class="form-control" rows="2">{{ $category->description }}</textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-light border-top">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No merchandise categories found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($categories->hasPages())
            <div class="p-3 border-top">
                {{ $categories->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Create Category Modal -->
<div class="modal fade" id="createCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('admin.store.categories.store') }}" method="POST" enctype="multipart/form-data" id="catCreateForm">
                @csrf
                <input type="hidden" name="image_data" id="catCreateImageData">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="bi bi-plus-circle me-2 text-primary"></i>Add New Merchandise Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Category Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Apparel & Merchandise" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Slug (Optional)</label>
                            <input type="text" name="slug" class="form-control" placeholder="e.g. apparel-merchandise">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" value="1">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="status" class="form-select">
                                <option value="ACTIVE" selected>ACTIVE</option>
                                <option value="INACTIVE">INACTIVE</option>
                            </select>
                        </div>
                    </div>

                    <!-- Category Image Section with Device Browse + Live Adjuster -->
                    <div class="mb-3 border rounded-3 p-3 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-bold mb-0">Category Image</label>
                            <span class="text-muted extra-small">Recommended: 1:1 Square</span>
                        </div>

                        <!-- Source Nav -->
                        <ul class="nav nav-pills nav-fill mb-2 bg-white p-1 rounded border extra-small" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active py-1 extra-small fw-semibold" id="catCreateTabDevice" type="button" onclick="switchCatCreateMode('device')">
                                    <i class="bi bi-laptop me-1"></i> Browse Device / Upload
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link py-1 extra-small fw-semibold" id="catCreateTabUrl" type="button" onclick="switchCatCreateMode('url')">
                                    <i class="bi bi-link-45deg me-1"></i> Paste Image Web URL
                                </button>
                            </li>
                        </ul>

                        <!-- Device Dropzone -->
                        <div id="catCreateDevBox">
                            <div class="img-adjuster-dropzone p-3 text-center mb-2" id="catCreateDropzone" onclick="document.getElementById('catCreateFileInput').click()">
                                <i class="bi bi-cloud-arrow-up fs-3 text-primary d-block mb-1"></i>
                                <span class="small fw-bold">Click to browse or drag & drop</span>
                                <p class="text-muted extra-small mb-0">JPG, PNG, WEBP, GIF up to 10MB</p>
                                <input type="file" id="catCreateFileInput" name="image_file" accept="image/*" class="d-none">
                            </div>
                        </div>

                        <!-- URL Box -->
                        <div id="catCreateUrlBox" class="mb-2 d-none">
                            <div class="input-group input-group-sm">
                                <input type="url" name="image_url" id="catCreateUrlInput" class="form-control" placeholder="https://images.unsplash.com/...">
                                <button class="btn btn-outline-primary" type="button" onclick="loadCatCreateUrl()">
                                    Load & Adjust
                                </button>
                            </div>
                        </div>

                        <!-- Cropper Studio -->
                        <div id="catCreateStudio" class="d-none border rounded p-3 bg-white mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-bold text-dark"><i class="bi bi-crop me-1 text-primary"></i> Adjust & Crop Image Before Upload</span>
                                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 extra-small" onclick="window.catCreateCropper.clear()">
                                    Change Image
                                </button>
                            </div>
                            <div class="row g-2">
                                <div class="col-8">
                                    <div class="cropper-container-box" style="height: 240px;">
                                        <img id="catCreateImgEl" src="" alt="Crop" style="max-width: 100%;">
                                    </div>
                                </div>
                                <div class="col-4 d-flex flex-column justify-content-between">
                                    <div>
                                        <label class="form-label extra-small fw-bold text-muted text-uppercase mb-1">Live Store Preview</label>
                                        <div class="crop-preview-box mx-auto mb-2" style="width: 80px; height: 80px;">
                                            <img id="catCreatePreviewBox" src="" alt="Preview">
                                        </div>
                                        <div class="d-flex gap-1 mb-1 justify-content-center">
                                            <button type="button" class="btn btn-sm btn-light border" title="Zoom Out" onclick="window.catCreateCropper.zoom(-0.1)">-</button>
                                            <button type="button" class="btn btn-sm btn-light border" title="Zoom In" onclick="window.catCreateCropper.zoom(0.1)">+</button>
                                            <button type="button" class="btn btn-sm btn-light border" title="Rotate" onclick="window.catCreateCropper.rotate(90)">⟳</button>
                                            <button type="button" class="btn btn-sm btn-light border" title="Reset" onclick="window.catCreateCropper.reset()">↺</button>
                                        </div>
                                    </div>
                                    <div class="text-muted extra-small text-center">Square 1:1 format</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief summary of items in this category"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Create Category</button>
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
.crop-preview-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/cropper.min.js') }}"></script>
<script src="{{ asset('js/store-image-adjuster.js') }}"></script>
<script>
window.catCroppers = {};

document.addEventListener('DOMContentLoaded', function() {
    // Create modal cropper init
    window.catCreateCropper = StoreImageAdjuster.init({
        id: 'cat_create',
        fileInputId: 'catCreateFileInput',
        urlInputId: 'catCreateUrlInput',
        hiddenDataId: 'catCreateImageData',
        dropzoneId: 'catCreateDropzone',
        cropperContainerId: 'catCreateStudio',
        imageElementId: 'catCreateImgEl',
        previewBoxId: 'catCreatePreviewBox',
        aspectRatio: 1,
        formId: 'catCreateForm'
    });

    // Init cropper on edit modals when opened
    @foreach($categories as $cat)
    (function(catId) {
        const modalEl = document.getElementById('editCategoryModal' + catId);
        if (modalEl) {
            modalEl.addEventListener('show.bs.modal', function() {
                if (!window.catCroppers[catId]) {
                    window.catCroppers[catId] = StoreImageAdjuster.init({
                        id: 'cat_edit_' + catId,
                        fileInputId: 'catFileInput_' + catId,
                        urlInputId: 'catUrlInput_' + catId,
                        hiddenDataId: 'catEditImageData_' + catId,
                        dropzoneId: 'catDropzone_' + catId,
                        cropperContainerId: 'catStudio_' + catId,
                        imageElementId: 'catImgEl_' + catId,
                        previewBoxId: 'catPreviewBox_' + catId,
                        aspectRatio: 1,
                        formId: 'catEditForm_' + catId
                    });
                }
            });
        }
    })('{{ $cat->id }}');
    @endforeach
});

function switchCatCreateMode(mode) {
    const devBox = document.getElementById('catCreateDevBox');
    const urlBox = document.getElementById('catCreateUrlBox');
    const tabDev = document.getElementById('catCreateTabDevice');
    const tabUrl = document.getElementById('catCreateTabUrl');

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

function loadCatCreateUrl() {
    const url = document.getElementById('catCreateUrlInput').value.trim();
    if (!url) {
        alert('Please enter an image URL.');
        return;
    }
    if (window.catCreateCropper) {
        window.catCreateCropper.loadFromUrl(url);
    }
}

function toggleCatReplace(catId) {
    const area = document.getElementById('catReplaceArea_' + catId);
    if (area) {
        area.classList.toggle('d-none');
    }
}

function switchCatMode(catId, mode) {
    const devBox = document.getElementById('catDevBox_' + catId);
    const urlBox = document.getElementById('catUrlBox_' + catId);
    const tabDev = document.getElementById('catTabDevBtn_' + catId);
    const tabUrl = document.getElementById('catTabUrlBtn_' + catId);

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

function loadCatUrl(catId) {
    const input = document.getElementById('catUrlInput_' + catId);
    const url = input ? input.value.trim() : '';
    if (!url) {
        alert('Please enter an image URL.');
        return;
    }
    if (window.catCroppers[catId]) {
        window.catCroppers[catId].loadFromUrl(url);
    }
}
</script>
@endpush
