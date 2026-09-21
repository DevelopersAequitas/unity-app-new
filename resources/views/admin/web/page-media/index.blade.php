@extends('admin.layouts.app')

@section('title', 'Page Media Config - Peers Global Web')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-indigo-subtle text-indigo rounded-pill px-2.5 py-1" style="background: rgba(168, 85, 247, 0.12); color: #a855f7; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-collection-play me-1"></i> Peers Global Website
                </span>
                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-broadcast me-1"></i> Public API Synced
                </span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Website Page Media & Video Config</h1>
            <p class="text-muted small mb-0 mt-0.5">Control live hero videos, section loops, ambient reels, and imagery across all public website pages</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.web.media.index') }}" class="btn btn-outline-secondary btn-sm rounded-3 px-3 py-2 fw-semibold">
                <i class="bi bi-images me-1"></i> Media Library
            </a>
            <button type="button" class="btn btn-primary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs fw-semibold" onclick="openMediaModal()">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Assign Page Media / Video</span>
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-xs mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Filter by Page & Source --}}
    <div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
        <form method="GET" action="{{ route('admin.web.page-media.index') }}" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <label class="form-label small text-muted mb-1 fw-semibold">Filter By Page</label>
                <select name="page_id" class="form-select form-select-sm bg-light" onchange="this.form.submit()">
                    <option value="all" @selected(request('page_id') === 'all' || !request('page_id'))>All Website Pages</option>
                    @foreach($pages as $pg)
                        <option value="{{ $pg['id'] }}" @selected(request('page_id') === $pg['id'])>{{ $pg['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label small text-muted mb-1 fw-semibold">Media Source</label>
                <select name="source" class="form-select form-select-sm bg-light" onchange="this.form.submit()">
                    <option value="all" @selected(request('source') === 'all' || !request('source'))>All Sources</option>
                    <option value="localhost" @selected(request('source') === 'localhost')>Localhost / Server File</option>
                    <option value="url" @selected(request('source') === 'url')>External URL / CDN / YouTube</option>
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold" style="height: 31px;">Apply Filters</button>
            </div>
        </form>
    </div>

    {{-- Table of Page Media Assignments --}}
    <div class="card border-0 shadow-xs rounded-4 p-4 bg-white">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="fw-bold text-dark mb-0">Active Media Slots ({{ $items->count() }})</h6>
            <div class="small text-muted font-monospace">API Endpoint: <span class="badge bg-light text-primary border">GET /api/v1/web-media</span></div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.84rem;">
                <thead class="table-light text-uppercase tracking-wider text-muted" style="font-size: 0.7rem;">
                    <tr>
                        <th>Page & Section</th>
                        <th>Media Details</th>
                        <th>Type</th>
                        <th>Source</th>
                        <th>Media Preview / Path</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $pm)
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark border fw-bold mb-1">{{ strtoupper($pm->page_id) }}</span>
                                <div class="text-muted small fw-semibold">{{ $pm->section_key ?? 'Main Section' }}</div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $pm->title }}</div>
                                <small class="text-muted line-clamp-1">{{ $pm->description ?: 'No description provided' }}</small>
                            </td>
                            <td>
                                <span class="badge rounded-pill px-2.5 py-1" style="background: {{ $pm->media_type === 'video' ? 'rgba(99, 102, 241, 0.12)' : 'rgba(16, 185, 129, 0.12)' }}; color: {{ $pm->media_type === 'video' ? '#6366f1' : '#10b981' }}; font-weight: 600;">
                                    <i class="bi bi-{{ $pm->media_type === 'video' ? 'film' : 'image' }} me-1"></i> {{ strtoupper($pm->media_type) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border font-monospace">
                                    {{ strtoupper($pm->media_source) }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if($pm->media_type === 'video')
                                        <div class="rounded-2 bg-dark text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 30px; font-size: 0.8rem;">
                                            <i class="bi bi-play-fill text-warning"></i>
                                        </div>
                                    @else
                                        <div class="rounded-2 bg-light border d-flex align-items-center justify-content-center overflow-hidden" style="width: 38px; height: 30px;">
                                            <i class="bi bi-image text-muted"></i>
                                        </div>
                                    @endif
                                    <div class="font-monospace text-muted small text-truncate" style="max-width: 180px;" title="{{ $pm->media_url }}">
                                        {{ $pm->media_url }}
                                    </div>
                                    @if($pm->media_url)
                                        <a href="{{ $pm->media_url }}" target="_blank" class="btn btn-sm btn-link text-primary p-0" title="Open media">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($pm->is_active)
                                    <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-weight: 600;">Active</span>
                                @else
                                    <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(100, 116, 139, 0.12); color: #64748b; font-weight: 600;">Disabled</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <button type="button" class="btn btn-sm btn-light border rounded-2 px-2" onclick='editMediaSlot(@json($pm))' title="Edit Media">
                                        <i class="bi bi-pencil-square text-primary"></i>
                                    </button>
                                    <form method="POST" action="{{ route('admin.web.page-media.destroy', $pm->id) }}" onsubmit="return confirm('Remove this media assignment?');" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light border text-danger rounded-2 px-2" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-collection-play display-6 text-secondary d-block mb-2 opacity-50"></i>
                                No page media assignments found for this filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Media Assign & Upload Modal --}}
<div class="modal fade" id="pageMediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="{{ route('admin.web.page-media.store') }}" enctype="multipart/form-data" class="modal-content rounded-4 border-0 shadow">
            @csrf
            <input type="hidden" name="id" id="modalMediaId" value="">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Assign Website Media / Video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Target Website Page <span class="text-danger">*</span></label>
                        <select name="page_id" id="modalPageId" class="form-select form-select-sm" required>
                            @foreach($pages as $pg)
                                <option value="{{ $pg['id'] }}">{{ $pg['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold">Section Identifier / Key</label>
                        <input type="text" name="section_key" id="modalSectionKey" class="form-control form-control-sm" placeholder="e.g. Hero Background, Who We Are, Conclave Reel">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Media Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="modalMediaTitle" class="form-control form-control-sm" required placeholder="e.g. Peers Global 4K Ambient Hero Reel">
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Media Type</label>
                        <select name="media_type" id="modalMediaType" class="form-select form-select-sm">
                            <option value="video" selected>Video (.mp4, .webm, .mov)</option>
                            <option value="photo">Photo / Banner (.png, .jpg, .webp)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Source Mode</label>
                        <select name="media_source" id="modalMediaSource" class="form-select form-select-sm">
                            <option value="localhost" selected>Local / Uploaded to Server</option>
                            <option value="url">External URL / CDN / YouTube</option>
                        </select>
                    </div>
                </div>

                {{-- Direct File Upload Input --}}
                <div class="mb-3 p-3 bg-light rounded-3 border">
                    <label class="form-label small fw-semibold d-flex align-items-center justify-content-between">
                        <span>Upload Video or Image from Device (Max 100MB)</span>
                        <span class="badge bg-secondary-subtle text-secondary">Optional</span>
                    </label>
                    <input type="file" name="media_file" class="form-control form-control-sm" accept="video/mp4,video/webm,video/quicktime,image/*">
                    <small class="text-muted d-block mt-1">If uploaded, the file will be saved automatically to server storage and linked to this page slot.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Or Enter Direct URL / File Path</label>
                    <input type="text" name="media_url" id="modalMediaUrl" class="form-control form-control-sm font-monospace" placeholder="/videos/hero-background.mp4 or https://cdn.example.com/video.mp4">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description / Subtitle</label>
                    <textarea name="description" id="modalDescription" rows="2" class="form-control form-control-sm" placeholder="Cinematic full-bleed ambient reel showing promoters..."></textarea>
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="modalIsActive" checked>
                    <label class="form-check-label small fw-semibold" for="modalIsActive">Activate Immediately on Public Website</label>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold" id="modalSubmitBtn">Save & Sync with Website</button>
            </div>
        </form>
    </div>
</div>

<script>
function openMediaModal() {
    document.getElementById('modalMediaId').value = '';
    document.getElementById('modalTitle').innerText = 'Assign Website Media / Video';
    document.getElementById('modalSubmitBtn').innerText = 'Save & Sync with Website';
    document.getElementById('modalPageId').value = 'home';
    document.getElementById('modalSectionKey').value = '';
    document.getElementById('modalMediaTitle').value = '';
    document.getElementById('modalMediaType').value = 'video';
    document.getElementById('modalMediaSource').value = 'localhost';
    document.getElementById('modalMediaUrl').value = '';
    document.getElementById('modalDescription').value = '';
    document.getElementById('modalIsActive').checked = true;

    new bootstrap.Modal(document.getElementById('pageMediaModal')).show();
}

function editMediaSlot(item) {
    document.getElementById('modalMediaId').value = item.id;
    document.getElementById('modalTitle').innerText = 'Edit Media Slot: ' + item.title;
    document.getElementById('modalSubmitBtn').innerText = 'Update & Sync';
    document.getElementById('modalPageId').value = item.page_id || 'home';
    document.getElementById('modalSectionKey').value = item.section_key || '';
    document.getElementById('modalMediaTitle').value = item.title || '';
    document.getElementById('modalMediaType').value = item.media_type || 'video';
    document.getElementById('modalMediaSource').value = item.media_source || 'localhost';
    document.getElementById('modalMediaUrl').value = item.media_url || '';
    document.getElementById('modalDescription').value = item.description || '';
    document.getElementById('modalIsActive').checked = Boolean(item.is_active);

    new bootstrap.Modal(document.getElementById('pageMediaModal')).show();
}
</script>
@endsection
