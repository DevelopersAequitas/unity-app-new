@extends('admin.layouts.app')

@section('title', 'Anniversary Creative Template Manager')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-images me-2 text-primary"></i>Anniversary Creative
        </h1>
        <span class="badge bg-light text-dark border">Today's Anniversaries: {{ $anniversaryUsers->count() }}</span>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <!-- Template Upload Form -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0 fs-6 fw-bold">
                        <i class="bi bi-cloud-upload me-2"></i>Upload Anniversary Template
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.anniversary-creatives.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="image" class="form-label fw-semibold text-muted small">Template Image (Base Reference)</label>
                            <input type="file" class="form-control @error('image') is-invalid @enderror" id="image" name="image" accept="image/*" required>
                            @error('image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text text-muted small mt-1">Recommended size: 1080x1080. Max 10MB.</div>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label fw-semibold text-muted small">Anniversary Message Overlay</label>
                            <textarea class="form-control @error('message') is-invalid @enderror" id="message" name="message" rows="4" placeholder="Wishing you a lifetime of love and happiness..." required>{{ old('message') }}</textarea>
                            @error('message')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text text-muted small mt-1">This text will be wrapped and centered dynamically.</div>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" checked>
                            <label class="form-check-label fw-semibold text-muted small" for="is_active">Enable Template Immediately</label>
                            <div class="form-text text-muted small">Activating this template automatically disables all other templates.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                            <i class="bi bi-plus-circle me-2"></i>Save &amp; Activate
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Saved Templates Grid -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="card-title mb-0 fs-6 fw-bold text-dark">
                        <i class="bi bi-list-task me-2 text-primary"></i>Configured Templates
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small">
                                <tr>
                                    <th class="ps-4">Preview</th>
                                    <th>Message Overlay</th>
                                    <th>Status</th>
                                    <th>Uploaded At</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($templates as $tpl)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="position-relative d-inline-block rounded border overflow-hidden bg-light" style="width: 70px; height: 70px;">
                                                <img src="{{ Storage::disk(config('filesystems.default', 'public'))->url($tpl->image_path) }}" 
                                                     class="img-fluid w-100 h-100 object-fit-cover cursor-pointer"
                                                     alt="Anniversary Template"
                                                     onclick="viewTemplateOriginal('{{ Storage::disk(config('filesystems.default', 'public'))->url($tpl->image_path) }}')" />
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-dark fw-medium small mb-1">{{ Str::limit($tpl->message, 80) }}</div>
                                            <div class="text-muted small">ID: <code class="small">{{ $tpl->id }}</code></div>
                                        </td>
                                        <td>
                                            @if($tpl->is_active)
                                                <span class="badge bg-success shadow-sm px-2.5 py-1.5 small fw-semibold">
                                                    <i class="bi bi-check-circle me-1"></i>Active
                                                </span>
                                            @else
                                                <span class="badge bg-secondary shadow-sm px-2.5 py-1.5 small fw-semibold">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="text-muted small">{{ $tpl->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="text-end pe-4">
                                            <div class="d-inline-flex gap-2">
                                                <form action="{{ route('admin.anniversary-creatives.toggle', $tpl->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-{{ $tpl->is_active ? 'secondary' : 'success' }} fw-semibold px-3">
                                                        {{ $tpl->is_active ? 'Disable' : 'Enable' }}
                                                    </button>
                                                </form>
                                                <form action="{{ route('admin.anniversary-creatives.destroy', $tpl->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this template?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger fw-semibold px-2">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <div class="text-muted mb-2">
                                                <i class="bi bi-image-fill fs-1 text-light"></i>
                                            </div>
                                            <div class="fw-semibold text-muted">No custom templates configured.</div>
                                            <div class="text-muted small">The system will dynamically use the default anniversary template.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dynamic Overlay Live Preview -->
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="card-title mb-0 fs-6 fw-bold text-dark">
                <i class="bi bi-eye me-2 text-primary"></i>Live Creative Preview with User Overlay
            </h5>
        </div>
        <div class="card-body p-4 text-center">
            <div class="mb-4 w-50 mx-auto">
                <label for="previewUserSelect" class="form-label text-muted small fw-semibold">Select Peer to generate live preview</label>
                <select class="form-select mx-auto" id="previewUserSelect" onchange="updatePreviewImage()">
                    @foreach($previewUsers as $pUser)
                        <option value="{{ $pUser->id }}">{{ $pUser->display_name ?: ($pUser->first_name . ' ' . $pUser->last_name) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="preview-canvas-wrapper border rounded p-2 bg-light shadow-inner mx-auto mb-2" style="max-width: 380px; width: 100%; aspect-ratio: 1/1;">
                <div id="previewLoader" class="d-none w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white rounded">
                    <div class="spinner-border text-primary mb-2" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="text-muted small">Generating Creative...</div>
                </div>
                <img id="creativePreviewImg" src="" class="img-fluid rounded border w-100 h-100 d-block" alt="Anniversary Creative Preview" style="object-fit: contain;">
            </div>
        </div>
    </div>

    <!-- Today's Anniversaries Panel -->
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="card-title mb-0 fs-6 fw-bold text-danger">
                <i class="bi bi-calendar-event me-2"></i>Today's Anniversaries ({{ now()->format('d M') }})
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Photo</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Anniversary Date</th>
                        <th>Designation</th>
                        <th>Company</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($anniversaryUsers as $aUser)
                        <tr>
                            <td class="ps-4">
                                @if($aUser->profile_photo_file_id)
                                    <img src="{{ url('/api/v1/files/' . $aUser->profile_photo_file_id) }}" style="width: 40px; height: 40px; object-fit: cover;" class="rounded-circle border">
                                @elseif($aUser->profile_photo_url)
                                    <img src="{{ $aUser->profile_photo_url }}" style="width: 40px; height: 40px; object-fit: cover;" class="rounded-circle border">
                                @else
                                    <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px; font-size: 16px;">
                                        {{ strtoupper(substr($aUser->display_name ?: $aUser->first_name ?: 'U', 0, 1)) }}
                                    </div>
                                @endif
                            </td>
                            <td><strong>{{ $aUser->display_name ?: ($aUser->first_name . ' ' . $aUser->last_name) }}</strong></td>
                            <td>{{ $aUser->email }}</td>
                            <td>{{ $aUser->anniversary_date ? \Carbon\Carbon::parse($aUser->anniversary_date)->format('d M Y') : '—' }}</td>
                            <td>{{ $aUser->designation ?: '—' }}</td>
                            <td>{{ $aUser->company_name ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No users celebrating a wedding anniversary today.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Image Preview Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true" style="z-index: 1070;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 bg-transparent">
            <div class="modal-body text-center p-0 position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                <img id="modalPreviewImg" src="" class="img-fluid rounded shadow border border-secondary" style="max-height: 85vh;" />
            </div>
        </div>
    </div>
</div>

<script>
    function updatePreviewImage() {
        const userId = document.getElementById('previewUserSelect').value;
        if (!userId) return;

        const img = document.getElementById('creativePreviewImg');
        const loader = document.getElementById('previewLoader');

        // Show loader, hide image
        img.classList.add('d-none');
        loader.classList.remove('d-none');

        // Generate cache-busting timestamp
        const t = new Date().getTime();
        const srcUrl = `/admin/anniversary-creatives/preview/${userId}?t=${t}`;

        // Load image in background
        const tempImg = new Image();
        tempImg.onload = function() {
            img.src = srcUrl;
            loader.classList.add('d-none');
            img.classList.remove('d-none');
        };
        tempImg.onerror = function() {
            loader.classList.add('d-none');
            img.classList.remove('d-none');
            alert('Failed to generate creative preview.');
        };
        tempImg.src = srcUrl;
    }

    function viewTemplateOriginal(url) {
        document.getElementById('modalPreviewImg').src = url;
        const previewModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
        previewModal.show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        updatePreviewImage();
    });
</script>
@endsection
