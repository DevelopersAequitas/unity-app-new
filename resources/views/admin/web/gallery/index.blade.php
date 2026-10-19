@extends('admin.layouts.app')

@section('title', 'Gallery Collection - Add Media')

@section('content')
<div class="container-fluid px-0">

    {{-- Top Header Bar --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(6, 182, 212, 0.12); color: #0891b2; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-images me-1"></i> Add Media
                </span>
                <span class="text-muted" style="font-size: 0.8rem;">● Gallery Collection</span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Gallery Collection — Moments Across Our Community</h1>
            <p class="text-muted small mb-0 mt-0.5">Upload, curate and organize photos for the Peers Global website gallery across conclaves, circle meetings, and summits.</p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-primary btn-sm rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-1.5 shadow-sm" data-bs-toggle="modal" data-bs-target="#galleryModal" onclick="resetGalleryModal()">
                <i class="bi bi-cloud-arrow-up-fill"></i>
                <span>Add Gallery Photo</span>
            </button>
            <a href="https://peersglobal.com/gallery" target="_blank" class="btn btn-outline-secondary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs">
                <i class="bi bi-box-arrow-up-right"></i>
                <span class="fw-semibold">Preview Website Gallery</span>
            </a>
        </div>
    </div>

    {{-- Flash Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-xs mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-xs mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-xs mb-4" role="alert">
            <strong class="d-block mb-1"><i class="bi bi-exclamation-circle me-1"></i> Please check form validation errors:</strong>
            <ul class="mb-0 ps-3 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif


    {{-- KPI Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-6">
            <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.68rem;">TOTAL PHOTOS</span>
                    <div class="p-2 rounded-3" style="background: rgba(0, 98, 210, 0.1); color: #0062D2;">
                        <i class="bi bi-images"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-dark mb-0">{{ $items->total() }}</div>
                <div class="text-muted small mt-1">Uploaded in gallery collection</div>
            </div>
        </div>
        <div class="col-6 col-md-6">
            <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.68rem;">LIVE ON WEBSITE</span>
                    <div class="p-2 rounded-3" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <i class="bi bi-broadcast-pin"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-dark mb-0">{{ $stats['active'] }}</div>
                <div class="text-muted small mt-1">Published and visible on live website</div>
            </div>
        </div>
    </div>

    {{-- Search & Filter Bar --}}
    <div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
        <form method="GET" action="{{ route('admin.web.gallery.index') }}" class="row g-2 align-items-center">
            <div class="col-12 col-md-10">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control bg-light border-start-0" placeholder="Search photos by caption or keyword...">
                </div>
            </div>
            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-semibold"><i class="bi bi-search me-1"></i> Search</button>
                @if(request()->filled('search'))
                    <a href="{{ route('admin.web.gallery.index') }}" class="btn btn-light border btn-sm text-secondary" title="Reset Search"><i class="bi bi-arrow-counterclockwise"></i></a>
                @endif
            </div>
        </form>
    </div>

    {{-- Gallery Photo Grid --}}
    @if($items->count() > 0)
        <div class="row g-4 mb-4">
            @foreach($items as $item)
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card border-0 shadow-xs rounded-4 bg-white h-100 d-flex flex-column justify-content-between overflow-hidden position-relative group-hover transition" style="border: 1px solid rgba(0,0,0,0.06) !important;">
                        
                        {{-- Photo Container --}}
                        <div class="position-relative overflow-hidden bg-dark" style="height: 220px;">
                            <img src="{{ $item->image_url }}" alt="{{ $item->caption ?: 'Gallery Photo' }}" class="w-100 h-100 object-fit-cover transition" style="transition: transform 0.4s ease;" onerror="this.src='/images/avatar-placeholder.png'">

                            {{-- Status Badge --}}
                            <div class="position-absolute bottom-0 start-0 p-2">
                                @if($item->is_active)
                                    <span class="badge bg-success bg-opacity-90 rounded-pill px-2.5 py-1 small shadow-sm">
                                        <i class="bi bi-check-circle-fill me-1"></i> Live
                                    </span>
                                @else
                                    <span class="badge bg-secondary bg-opacity-90 rounded-pill px-2.5 py-1 small shadow-sm">
                                        <i class="bi bi-eye-slash-fill me-1"></i> Draft
                                    </span>
                                @endif
                            </div>

                            {{-- Quick Preview Button --}}
                            <button type="button" class="btn btn-sm btn-light bg-white bg-opacity-90 rounded-circle position-absolute bottom-0 end-0 m-2 shadow-sm" style="width: 32px; height: 32px; padding: 0;" onclick="openPreviewModal('{{ $item->image_url }}', '{{ addslashes($item->caption ?? '') }}')" title="Zoom Photo">
                                <i class="bi bi-arrows-fullscreen small"></i>
                            </button>
                        </div>

                        {{-- Details Body --}}
                        <div class="p-3.5 d-flex flex-column flex-grow-1 justify-content-between">
                            <div>
                                @if($item->caption)
                                    <p class="text-dark fw-medium small mb-2 line-clamp-3" style="font-size: 0.88rem; line-height: 1.5;" title="{{ $item->caption }}">
                                        {{ $item->caption }}
                                    </p>
                                @else
                                    <p class="text-muted fst-italic small mb-2" style="font-size: 0.82rem;">No caption added.</p>
                                @endif
                            </div>

                            {{-- Actions Footer --}}
                            <div class="d-flex align-items-center justify-content-between pt-2.5 border-top">
                                <span class="text-muted font-monospace" style="font-size: 0.72rem;">
                                    {{ $item->created_at ? $item->created_at->format('M d, Y') : '' }}
                                </span>
                                
                                <div class="d-flex align-items-center gap-1.5">
                                    {{-- Toggle Status --}}
                                    <form method="POST" action="{{ route('admin.web.gallery.toggle-status', $item->id) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm {{ $item->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }} rounded-3 px-2 py-1" style="font-size: 0.75rem;" title="{{ $item->is_active ? 'Click to Unpublish' : 'Click to Publish' }}">
                                            <i class="bi bi-{{ $item->is_active ? 'toggle-on' : 'toggle-off' }}"></i>
                                        </button>
                                    </form>

                                    {{-- Edit Button --}}
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-2 py-1" style="font-size: 0.75rem;" onclick='openEditGalleryModal(@json($item))' title="Edit Photo">
                                        <i class="bi bi-pencil-fill"></i> Edit
                                    </button>

                                    {{-- Delete Button --}}
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-3 px-2 py-1" style="font-size: 0.75rem;" onclick="confirmDeleteGallery('{{ $item->id }}', '{{ addslashes($item->caption ?: 'this photo') }}')" title="Delete Photo">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="d-flex justify-content-center mt-3">
            {{ $items->withQueryString()->links() }}
        </div>
    @else
        <div class="card border-0 shadow-xs rounded-4 p-5 text-center bg-white">
            <div class="mb-3 text-muted" style="font-size: 3rem;">
                <i class="bi bi-images"></i>
            </div>
            <h4 class="h5 fw-bold text-dark">No gallery moments found</h4>
            <p class="text-muted small mb-4">No moments match your current filter selection or none have been uploaded yet.</p>
            <div class="d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-primary btn-sm rounded-3 px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#galleryModal" onclick="resetGalleryModal()">
                    <i class="bi bi-cloud-arrow-up-fill me-1"></i> Add First Photo
                </button>
                @if(request()->anyFilled(['search', 'city', 'circle', 'category', 'year']))
                    <a href="{{ route('admin.web.gallery.index') }}" class="btn btn-light border btn-sm rounded-3 px-3 py-2">Reset Filters</a>
                @endif
            </div>
        </div>
    @endif

</div>

{{-- Add / Edit Gallery Modal --}}
<div class="modal fade" id="galleryModal" tabindex="-1" aria-labelledby="galleryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form id="galleryForm" method="POST" action="{{ route('admin.web.gallery.store') }}" enctype="multipart/form-data">
                @csrf
                <div id="methodSpoof"></div>

                <div class="modal-header border-bottom p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 rounded-3 bg-primary text-white shadow-xs">
                            <i class="bi bi-cloud-arrow-up-fill"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="galleryModalLabel">Upload Photo</h5>
                            <p class="text-muted small mb-0">Upload an image or provide an image link for the gallery</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        {{-- Photo File Upload Button --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark small mb-1">
                                <i class="bi bi-image me-1 text-primary"></i> Upload Photo (Choose File)
                            </label>
                            <input type="file" name="image" id="gallery_image" class="form-control rounded-3" accept="image/*" onchange="previewSelectedImage(this)">
                            <div class="form-text small">JPG, PNG, WebP or GIF — Max 10MB</div>

                            <div id="imagePreviewContainer" class="mt-2 text-center p-2 border rounded-3 bg-light d-none">
                                <img id="imagePreview" src="" alt="Preview" class="rounded-2" style="max-height: 200px; max-width: 100%; object-fit: contain;">
                            </div>
                        </div>

                        {{-- Image URL (Alternative) --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark small mb-1">
                                <i class="bi bi-link-45deg me-1 text-secondary"></i> Or Image URL <span class="text-muted fw-normal">(If hosted elsewhere)</span>
                            </label>
                            <div class="input-group">
                                <input type="text" name="image_url" id="gallery_image_url" class="form-control rounded-start-3" placeholder="https://... or /images/..." oninput="previewUrlImage(this.value)">
                                <button type="button" class="btn btn-outline-secondary btn-sm px-3 rounded-end-3" onclick="previewUrlImage(document.getElementById('gallery_image_url').value)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Caption (Optional) --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark small mb-1">
                                <i class="bi bi-chat-left-text me-1 text-muted"></i> Caption <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <textarea name="caption" id="gallery_caption" rows="3" class="form-control rounded-3" placeholder="Brief 1-2 sentence caption or story description (not required)..."></textarea>
                        </div>

                        {{-- Publish Immediately Toggle --}}
                        <div class="col-12 pt-2 border-top">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="is_active" id="gallery_is_active" value="1" checked>
                                <label class="form-check-label fw-semibold text-dark small" for="gallery_is_active">Publish immediately on website</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top p-3 bg-light bg-opacity-50">
                    <button type="button" class="btn btn-light rounded-3 px-3 py-2 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold" id="gallerySubmitBtn">
                        <i class="bi bi-cloud-arrow-up-fill me-1"></i> Save Photo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Lightbox Image Preview Modal --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden bg-dark text-white">
            <div class="position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 z-3" data-bs-dismiss="modal" aria-label="Close"></button>
                <img id="lightboxImage" src="" alt="Full Photo" class="w-100" style="max-height: 550px; object-fit: contain; background: #000;">
            </div>
            <div class="p-3 bg-dark border-top border-secondary border-opacity-25" id="lightboxCaptionWrapper">
                <p id="lightboxCaption" class="text-white small mb-0"></p>
            </div>
        </div>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4 text-center p-4">
            <div class="mb-3 text-danger" style="font-size: 2.5rem;">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Delete Photo?</h5>
            <p class="text-muted small mb-3">Are you sure you want to delete <span class="fw-semibold text-dark" id="deleteItemTitle">this photo</span>? This action cannot be undone.</p>
            
            <form id="deleteForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-light rounded-3 px-3 py-1.5" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-3 px-3 py-1.5 fw-semibold">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function resetGalleryModal() {
        const form = document.getElementById('galleryForm');
        form.reset();
        form.action = "{{ route('admin.web.gallery.store') }}";
        document.getElementById('methodSpoof').innerHTML = '';
        document.getElementById('galleryModalLabel').innerText = 'Upload Photo';
        document.getElementById('gallerySubmitBtn').innerHTML = '<i class="bi bi-cloud-arrow-up-fill me-1"></i> Save Photo';
        document.getElementById('gallery_is_active').checked = true;
        document.getElementById('gallery_image_url').value = '';
        document.getElementById('gallery_caption').value = '';
        document.getElementById('imagePreview').src = '';
        document.getElementById('imagePreviewContainer').classList.add('d-none');
    }

    function openEditGalleryModal(item) {
        resetGalleryModal();
        const form = document.getElementById('galleryForm');
        form.action = "/admin/web/gallery/" + item.id;
        document.getElementById('methodSpoof').innerHTML = '<input type="hidden" name="_method" value="PUT">';
        document.getElementById('galleryModalLabel').innerText = 'Edit Photo';
        document.getElementById('gallerySubmitBtn').innerHTML = '<i class="bi bi-check2 me-1"></i> Update Photo';

        document.getElementById('gallery_image_url').value = item.image_path || '';
        document.getElementById('gallery_caption').value = item.caption || '';
        document.getElementById('gallery_is_active').checked = item.is_active ? true : false;

        if (item.image_url) {
            document.getElementById('imagePreview').src = item.image_url;
            document.getElementById('imagePreviewContainer').classList.remove('d-none');
        }

        const modal = new bootstrap.Modal(document.getElementById('galleryModal'));
        modal.show();
    }

    function previewSelectedImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imagePreview').src = e.target.result;
                document.getElementById('imagePreviewContainer').classList.remove('d-none');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function previewUrlImage(url) {
        if (url && (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('/'))) {
            document.getElementById('imagePreview').src = url;
            document.getElementById('imagePreviewContainer').classList.remove('d-none');
        }
    }

    function openPreviewModal(imgUrl, caption) {
        document.getElementById('lightboxImage').src = imgUrl;
        const captionElem = document.getElementById('lightboxCaption');
        const captionWrapper = document.getElementById('lightboxCaptionWrapper');
        if (caption && caption.trim().length > 0) {
            captionElem.innerText = caption;
            captionWrapper.classList.remove('d-none');
        } else {
            captionWrapper.classList.add('d-none');
        }

        const modal = new bootstrap.Modal(document.getElementById('previewModal'));
        modal.show();
    }

    function confirmDeleteGallery(id, captionOrTitle) {
        document.getElementById('deleteForm').action = "/admin/web/gallery/" + id;
        document.getElementById('deleteItemTitle').innerText = captionOrTitle ? '"' + captionOrTitle + '"' : 'this photo';
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }
</script>
@endpush
@endsection
