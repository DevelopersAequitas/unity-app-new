@extends('admin.layouts.app')

@section('title', 'Birthday Creative Template Manager')

@include('admin.partials.grid-head')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-gift me-2 text-primary"></i>Birthday Creative
        </h1>
        <span class="badge bg-light text-dark border">Today's Birthdays: {{ $birthdayUsers->count() }}</span>
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
                        <i class="bi bi-cloud-upload me-2"></i>Upload Birthday Template
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.birthday-creative.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <!-- Hidden fields to pass existing config details to satisfy controller validation -->
                        <input type="hidden" name="background_gradient_start" value="{{ $config->background_gradient_start }}">
                        <input type="hidden" name="background_gradient_end" value="{{ $config->background_gradient_end }}">
                        <input type="hidden" name="text_color" value="{{ $config->text_color }}">

                        <div class="mb-3">
                            <label for="template_image" class="form-label fw-semibold text-muted small">Template Image (Base Reference)</label>
                            <input type="file" class="form-control @error('template_image') is-invalid @enderror" id="template_image" name="template_image" accept="image/*" required>
                            @error('template_image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text text-muted small mt-1">Recommended size: 1080x1080. Max 10MB.</div>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_enabled_checkbox" value="1" checked onchange="document.getElementById('isEnabledValue').value = this.checked ? '1' : '0'">
                            <input type="hidden" name="is_enabled" id="isEnabledValue" value="1">
                            <label class="form-check-label fw-semibold text-muted small" for="is_active">Enable Template Immediately</label>
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
            <div class="rounded-xl border bs surface p-4 space-y-3">
                <div class="flex justify-between items-center">
                    <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Configured Templates</h2>
                </div>

                <div class="rounded-xl border bs surface overflow-hidden">
                    <div class="overflow-x-auto relative">
                        <table class="min-w-full border-collapse text-[13px]">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left w-20">Preview</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Template Information</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="grid-body" class="divide-y divide-gray-200/50">
                                @if($config->template_file_id)
                                    <tr class="hover:surface-2 transition border-b bs">
                                        <td class="px-3 py-2.5">
                                            <div class="w-12 h-12 rounded border bs overflow-hidden surface-2 flex-shrink-0">
                                                <img src="{{ url('/api/v1/files/' . $config->template_file_id) }}" 
                                                     class="w-full h-full object-cover cursor-pointer"
                                                     alt="Birthday Template"
                                                     onclick="viewTemplateOriginal('{{ url('/api/v1/files/' . $config->template_file_id) }}')" />
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs">
                                            <div class="t1 font-medium">Active Birthday Background</div>
                                            <div class="t3 text-[10px] mt-0.5">File ID: <code class="font-mono">{{ $config->template_file_id }}</code></div>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs">
                                            @if($config->is_enabled)
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Active</span>
                                            @else
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                            <div class="flex justify-end gap-1.5 items-center">
                                                <!-- Toggle Form -->
                                                <form action="{{ route('admin.birthday-creative.update') }}" method="POST" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="background_gradient_start" value="{{ $config->background_gradient_start }}">
                                                    <input type="hidden" name="background_gradient_end" value="{{ $config->background_gradient_end }}">
                                                    <input type="hidden" name="text_color" value="{{ $config->text_color }}">
                                                    <input type="hidden" name="is_enabled" value="{{ $config->is_enabled ? '0' : '1' }}">
                                                    <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">
                                                        {{ $config->is_enabled ? 'Disable' : 'Enable' }}
                                                    </button>
                                                </form>

                                                <!-- Delete Form -->
                                                <form action="{{ route('admin.birthday-creative.update') }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this template?')" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="background_gradient_start" value="{{ $config->background_gradient_start }}">
                                                    <input type="hidden" name="background_gradient_end" value="{{ $config->background_gradient_end }}">
                                                    <input type="hidden" name="text_color" value="{{ $config->text_color }}">
                                                    <input type="hidden" name="is_enabled" value="{{ $config->is_enabled ? '1' : '0' }}">
                                                    <input type="hidden" name="delete_template" value="1">
                                                    <button type="submit" class="px-2 py-0.5 text-xs font-semibold rounded bg-rose-600 hover:bg-rose-500 text-white transition focus-ring">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @else
                                    <tr>
                                        <td colspan="4" class="text-center py-8 text-xs t3">
                                            No custom templates configured. Default birthday template file on disk will be used.
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dynamic Overlay Live Preview -->
    <div class="rounded-xl border bs surface p-4 mt-4 space-y-3">
        <div class="flex justify-between items-center">
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Live Creative Preview with User Overlay</h2>
        </div>
        <div class="p-4 text-center">
            <div class="mb-4 max-w-sm mx-auto">
                <label for="previewUserSelect" class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Select Peer for preview</label>
                <select class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" id="previewUserSelect" onchange="updatePreviewImage()">
                    @foreach($previewUsers as $pUser)
                        <option value="{{ $pUser->id }}">{{ $pUser->display_name ?: ($pUser->first_name . ' ' . $pUser->last_name) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="preview-canvas-wrapper border rounded p-2 surface-2 shadow-inner mx-auto mb-2" style="max-width: 380px; width: 100%; aspect-ratio: 1/1;">
                <div id="previewLoader" class="d-none w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white rounded">
                    <div class="spinner-border text-primary mb-2" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="text-muted small">Generating Creative...</div>
                </div>
                <img id="creativePreviewImg" src="" class="img-fluid rounded border w-100 h-100 d-block" alt="Birthday Creative Preview" style="object-fit: contain;">
            </div>
        </div>
    </div>

    <!-- Today's Birthday Users Panel -->
    <div class="rounded-xl border bs surface p-4 mt-4 space-y-3">
        <div class="flex justify-between items-center">
            <h2 class="font-display font-semibold text-xs text-rose-500 uppercase tracking-wider m-0">Today's Birthdays ({{ now()->format('d M') }})</h2>
        </div>
        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left w-14">Photo</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Email</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Date of Birth</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Designation</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse($birthdayUsers as $bUser)
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5">
                                    @if($bUser->profile_photo_file_id)
                                        <img src="{{ url('/api/v1/files/' . $bUser->profile_photo_file_id) }}" class="w-9 h-9 object-cover rounded-full border bs">
                                    @elseif($bUser->profile_photo_url)
                                        <img src="{{ $bUser->profile_photo_url }}" class="w-9 h-9 object-cover rounded-full border bs">
                                    @else
                                        <div class="w-9 h-9 rounded-full surface-2 text-indigo-600 font-bold flex items-center justify-center border bs text-xs">
                                            {{ strtoupper(substr($bUser->display_name ?: $bUser->first_name ?: 'U', 0, 1)) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs font-semibold t1">{{ $bUser->display_name ?: ($bUser->first_name . ' ' . $bUser->last_name) }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $bUser->email }}</td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $bUser->dob ? \Carbon\Carbon::parse($bUser->dob)->format('d M Y') : '—' }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $bUser->designation ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $bUser->company_name ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-8 text-xs t3">No users celebrating a birthday today.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
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
        const srcUrl = `/admin/birthday-creative/preview/${userId}?t=${t}`;

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
