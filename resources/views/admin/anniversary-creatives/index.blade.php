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
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Message Overlay</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Uploaded At</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="grid-body" class="divide-y divide-gray-200/50">
                                @forelse($templates as $tpl)
                                    <tr class="hover:surface-2 transition border-b bs">
                                        <td class="px-3 py-2.5">
                                            <div class="w-12 h-12 rounded border bs overflow-hidden surface-2 flex-shrink-0">
                                                <img src="{{ Storage::disk(config('filesystems.default', 'public'))->url($tpl->image_path) }}" 
                                                     class="w-full h-full object-cover cursor-pointer"
                                                     alt="Anniversary Template"
                                                     onclick="viewTemplateOriginal('{{ Storage::disk(config('filesystems.default', 'public'))->url($tpl->image_path) }}')" />
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs">
                                            <div class="t1 font-medium max-w-[250px] truncate" title="{{ $tpl->message }}">{{ Str::limit($tpl->message, 80) }}</div>
                                            <div class="t3 text-[10px] mt-0.5">ID: <code class="font-mono">{{ $tpl->id }}</code></div>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs">
                                            @if($tpl->is_active)
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Active</span>
                                            @else
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $tpl->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                            <div class="flex justify-end gap-1.5 items-center">
                                                <form action="{{ route('admin.anniversary-creatives.toggle', $tpl->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">
                                                        {{ $tpl->is_active ? 'Disable' : 'Enable' }}
                                                    </button>
                                                </form>
                                                <form action="{{ route('admin.anniversary-creatives.destroy', $tpl->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this template?')" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="px-2 py-0.5 text-xs font-semibold rounded bg-rose-600 hover:bg-rose-500 text-white transition focus-ring">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-8 text-xs t3">
                                            No custom templates configured. Default anniversary template will be used.
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
                <img id="creativePreviewImg" src="" class="img-fluid rounded border w-100 h-100 d-block" alt="Anniversary Creative Preview" style="object-fit: contain;">
            </div>
        </div>
    </div>

    <!-- Today's Anniversaries Panel -->
    <div class="rounded-xl border bs surface p-4 mt-4 space-y-3">
        <div class="flex justify-between items-center">
            <h2 class="font-display font-semibold text-xs text-rose-500 uppercase tracking-wider m-0">Today's Anniversaries ({{ now()->format('d M') }})</h2>
        </div>
        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left w-14">Photo</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Email</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Anniversary Date</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Designation</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse($anniversaryUsers as $aUser)
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5">
                                    @if($aUser->profile_photo_file_id)
                                        <img src="{{ url('/api/v1/files/' . $aUser->profile_photo_file_id) }}" class="w-9 h-9 object-cover rounded-full border bs">
                                    @elseif($aUser->profile_photo_url)
                                        <img src="{{ $aUser->profile_photo_url }}" class="w-9 h-9 object-cover rounded-full border bs">
                                    @else
                                        <div class="w-9 h-9 rounded-full surface-2 text-indigo-600 font-bold flex items-center justify-center border bs text-xs">
                                            {{ strtoupper(substr($aUser->display_name ?: $aUser->first_name ?: 'U', 0, 1)) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs font-semibold t1">{{ $aUser->display_name ?: ($aUser->first_name . ' ' . $aUser->last_name) }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $aUser->email }}</td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $aUser->anniversary_date ? \Carbon\Carbon::parse($aUser->anniversary_date)->format('d M Y') : '—' }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $aUser->designation ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $aUser->company_name ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-8 text-xs t3">No users celebrating a wedding anniversary today.</td>
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
