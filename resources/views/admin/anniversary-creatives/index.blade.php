@extends('admin.layouts.app')

@section('title', 'Anniversary Creative Template Manager')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-images me-2 text-primary"></i>Anniversary Creative
        </h1>
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
                                                     onclick="previewImage('{{ Storage::disk(config('filesystems.default', 'public'))->url($tpl->image_path) }}')" />
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
function previewImage(url) {
    document.getElementById('modalPreviewImg').src = url;
    const previewModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    previewModal.show();
}
</script>
@endsection
