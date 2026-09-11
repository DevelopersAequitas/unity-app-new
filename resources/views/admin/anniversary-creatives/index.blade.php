@extends('admin.layouts.app')

@section('title', 'Anniversary Creative Template Manager')

@include('admin.partials.grid-head')

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
            <div class="rounded-xl border bs surface p-4 space-y-4 shadow-2xs">
                <div class="flex justify-between items-center">
                    <h2 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider m-0 flex items-center gap-2">
                        <i class="bi bi-collection-play text-sm me-1"></i>Configured Templates
                    </h2>
                </div>

                <div class="rounded-xl border bs surface overflow-hidden">
                    <div class="overflow-x-auto relative">
                        <table class="w-full min-w-full border-collapse text-[13px] align-middle">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                    <th class="th-cell px-3 py-2.5 text-left" style="width: 100px;">Preview</th>
                                    <th class="th-cell px-3 py-2.5 text-left" style="min-width: 240px;">Message Overlay</th>
                                    <th class="th-cell px-3 py-2.5 text-left" style="width: 110px;">Status</th>
                                    <th class="th-cell px-3 py-2.5 text-left" style="width: 140px;">Uploaded At</th>
                                    <th class="th-cell px-3 py-2.5 text-right" style="width: 170px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200/50">
                                @forelse($templates as $tpl)
                                    <tr class="hover:surface-2 transition border-b bs">
                                        <td class="px-3 py-3">
                                            <div class="w-20 h-20 rounded-lg border bs overflow-hidden surface-2 flex-shrink-0 relative shadow-2xs" style="width: 80px; height: 80px; max-width: 80px; max-height: 80px;">
                                                <img src="{{ Storage::disk(config('filesystems.default', 'public'))->url($tpl->image_path) }}" 
                                                     class="w-full h-full object-cover cursor-pointer hover:scale-105 transition-transform"
                                                     style="width: 100%; height: 100%; object-fit: cover;"
                                                     alt="Anniversary Template"
                                                     onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';"
                                                     onclick="viewTemplateOriginal('{{ Storage::disk(config('filesystems.default', 'public'))->url($tpl->image_path) }}')" />
                                                <div class="w-full h-full bg-slate-100 text-slate-400 flex flex-col items-center justify-center text-[10px] text-center p-1" style="display:none; width: 100%; height: 100%;">
                                                    <i class="bi bi-image text-lg mb-0.5 text-slate-400"></i>
                                                    <span class="font-medium text-[9px] leading-tight">No preview</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-xs">
                                            <div class="t1 font-medium max-w-[300px] leading-relaxed" style="word-break:break-word; white-space:normal;" title="{{ $tpl->message }}">{{ Str::limit($tpl->message, 100) }}</div>
                                        </td>
                                        <td class="px-3 py-3 text-xs">
                                            @if($tpl->is_active)
                                                <span class="chip px-2.5 py-1 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200 rounded-full inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Active</span>
                                            @else
                                                <span class="chip px-2.5 py-1 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200 rounded-full">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-xs t3 whitespace-nowrap font-mono">{{ $tpl->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="px-3 py-3 text-xs text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-2">
                                                <form action="{{ route('admin.anniversary-creatives.toggle', $tpl->id) }}" method="POST" class="inline m-0">
                                                    @csrf
                                                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition min-w-[72px] text-center">
                                                        {{ $tpl->is_active ? 'Disable' : 'Enable' }}
                                                    </button>
                                                </form>
                                                @if($tpl->is_active)
                                                    <button type="button" disabled title="Deactivate template before deleting" aria-disabled="true" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 text-slate-400 border bs cursor-not-allowed min-w-[72px] text-center opacity-60">
                                                        Delete
                                                    </button>
                                                @else
                                                    <form action="{{ route('admin.anniversary-creatives.destroy', $tpl->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this template?')" class="inline m-0">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-500 text-white transition min-w-[72px] text-center shadow-2xs">
                                                            Delete
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-10 text-xs t3">
                                            <i class="bi bi-images text-2xl d-block mb-1 text-slate-300"></i>
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
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0 flex items-center gap-2">
                <i class="bi bi-eye text-sm me-1"></i>Live Creative Preview with User Overlay
            </h2>
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

    <!-- Today's Anniversary Users Panel -->
    <div class="rounded-xl border bs surface p-4 mt-4 space-y-3">
        <div class="flex justify-between items-center">
            <h2 class="font-display font-semibold text-xs text-rose-500 uppercase tracking-wider m-0 flex items-center gap-2">
                <i class="bi bi-balloon-heart text-sm me-1"></i>Today's Anniversaries ({{ now()->format('d M') }})
            </h2>
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
                                        <div class="w-9 h-9 rounded-full bg-rose-100 text-rose-700 flex items-center justify-center font-bold text-xs border bs">
                                            {{ strtoupper(substr($aUser->display_name ?: $aUser->first_name ?: 'A', 0, 1)) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs font-semibold t1">
                                    @php $anniversaryName = $aUser->display_name ?: trim(($aUser->first_name ?? '').' '.($aUser->last_name ?? '')); @endphp
                                    @if($aUser->id)
                                        <a href="#" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $aUser->id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline no-underline font-semibold">{{ $anniversaryName ?: '—' }}</a>
                                    @else
                                        {{ $anniversaryName ?: '—' }}
                                    @endif
                                </td>
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

        img.classList.add('d-none');
        loader.classList.remove('d-none');

        const t = new Date().getTime();
        const srcUrl = `/admin/anniversary-creatives/preview/${userId}?t=${t}`;

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
