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
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Page Media Assignments</h1>
            <p class="text-muted small mb-0 mt-0.5">Control live hero videos, section loops, and imagery across 30+ public website pages</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs fw-semibold" data-bs-toggle="modal" data-bs-target="#newPageMediaModal">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Assign Page Media</span>
            </button>
        </div>
    </div>

    {{-- Filter by Page --}}
    <div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
        <form method="GET" action="{{ route('admin.web.page-media.index') }}" class="row g-2 align-items-center">
            <div class="col-12 col-md-6">
                <select name="page_id" class="form-select form-select-sm bg-light" onchange="this.form.submit()">
                    <option value="all" @selected(request('page_id') === 'all')>All Website Pages</option>
                    @foreach($pages as $pg)
                        <option value="{{ $pg['id'] }}" @selected(request('page_id') === $pg['id'])>{{ $pg['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-4">
                <select name="source" class="form-select form-select-sm bg-light" onchange="this.form.submit()">
                    <option value="all" @selected(request('source') === 'all')>All Media Sources</option>
                    <option value="local" @selected(request('source') === 'local')>Local / Public Folder Assets</option>
                    <option value="url" @selected(request('source') === 'url')>External URL / CDN</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">Filter</button>
            </div>
        </form>
    </div>

    {{-- Table of Page Media Assignments --}}
    <div class="card border-0 shadow-xs rounded-4 p-4 bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.84rem;">
                <thead class="table-light text-uppercase tracking-wider text-muted" style="font-size: 0.7rem;">
                    <tr>
                        <th>Page & Section</th>
                        <th>Media Title</th>
                        <th>Type</th>
                        <th>Source</th>
                        <th>Target File / URL</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $pm)
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark border fw-bold mb-1">{{ strtoupper($pm->page_id) }}</span>
                                <div class="text-muted small">{{ $pm->section_key ?? 'Hero Section' }}</div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $pm->title }}</div>
                                <small class="text-muted">{{ $pm->description }}</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border">
                                    <i class="bi bi-{{ $pm->media_type === 'video' ? 'film' : 'image' }} me-1"></i> {{ strtoupper($pm->media_type) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-primary border font-monospace">
                                    {{ strtoupper($pm->media_source) }}
                                </span>
                            </td>
                            <td class="font-monospace text-muted small text-truncate" style="max-width: 200px;">
                                {{ $pm->media_url ?? $pm->local_file_name ?? '—' }}
                            </td>
                            <td>
                                @if($pm->is_active)
                                    <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(16, 185, 129, 0.12); color: #059669;">Active</span>
                                @else
                                    <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(100, 116, 139, 0.12); color: #64748b;">Disabled</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.web.page-media.destroy', $pm->id) }}" onsubmit="return confirm('Remove this media assignment?');" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light border text-danger rounded-2 px-2.5">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No page media assignments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Create Modal --}}
<div class="modal fade" id="newPageMediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('admin.web.page-media.store') }}" class="modal-content rounded-4 border-0 shadow">
            @csrf
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Assign Page Media</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Select Page</label>
                        <select name="page_id" class="form-select form-select-sm" required>
                            @foreach($pages as $pg)
                                <option value="{{ $pg['id'] }}">{{ $pg['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Section Key</label>
                        <input type="text" name="section_key" class="form-control form-control-sm" placeholder="e.g. home-hero">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Media Title</label>
                    <input type="text" name="title" class="form-control form-control-sm" required placeholder="e.g. Ambient Loop 4K">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Media Type</label>
                        <select name="media_type" class="form-select form-select-sm">
                            <option value="video" selected>Video (.mp4, .webm)</option>
                            <option value="photo">Photo (.jpg, .png)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Source</label>
                        <select name="media_source" class="form-select form-select-sm">
                            <option value="local" selected>Local Asset</option>
                            <option value="url">Direct URL / CDN</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Media URL or File Path</label>
                    <input type="text" name="media_url" class="form-control form-control-sm" placeholder="/videos/hero-background.mp4">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description</label>
                    <textarea name="description" rows="2" class="form-control form-control-sm" placeholder="Where and how this media appears..."></textarea>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActiveCheck" checked>
                    <label class="form-check-label small" for="isActiveCheck">Activate on Live Website</label>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">Save Media</button>
            </div>
        </form>
    </div>
</div>
@endsection
