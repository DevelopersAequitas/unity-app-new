@extends('admin.layouts.app')

@section('title', 'Event Gallery')

@push('styles')
<style>
    /* ── Sidebar list ──────────────────────────────────────── */
    .eg-sidebar-item {
        display: block;
        padding: 12px 16px;
        border-left: 3px solid transparent;
        transition: background 0.15s, border-color 0.15s;
        text-decoration: none;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border);
    }
    .eg-sidebar-item:hover {
        background: var(--primary-subtle, #eef2ff);
        color: var(--text-primary);
    }
    .eg-sidebar-item.active {
        background: var(--primary-subtle, #eef2ff);
        border-left-color: #6366f1;
        color: var(--text-primary);
    }
    .eg-sidebar-item .eg-item-meta {
        font-size: 0.78rem;
        color: var(--text-muted);
        margin-top: 2px;
    }

    /* ── Media card ────────────────────────────────────────── */
    .eg-media-card {
        border: 1px solid var(--border, #e5e7eb);
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
        transition: box-shadow 0.2s, transform 0.2s;
        position: relative;
    }
    .eg-media-card:hover {
        box-shadow: 0 8px 24px rgba(99,102,241,.12);
        transform: translateY(-2px);
    }

    /* ── Thumbnail area ────────────────────────────────────── */
    .eg-thumb {
        position: relative;
        padding-top: 66%;
        background: #1a1a2e;
        overflow: hidden;
    }
    .eg-thumb img,
    .eg-thumb video {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Play icon overlay */
    .eg-play-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,.38);
        color: #fff;
        font-size: 2.25rem;
        text-decoration: none;
        transition: background 0.2s;
    }
    .eg-play-overlay:hover { background: rgba(0,0,0,.55); color: #fff; }

    /* ── Delete overlay ────────────────────────────────────── */
    .eg-delete-overlay {
        position: absolute;
        top: 8px;
        right: 8px;
        opacity: 0;
        transition: opacity 0.2s;
    }
    .eg-media-card:hover .eg-delete-overlay { opacity: 1; }
    .eg-delete-overlay button {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        border: none;
        background: rgba(239,68,68,.92);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        box-shadow: 0 2px 8px rgba(0,0,0,.25);
        transition: background 0.15s, transform 0.15s;
        cursor: pointer;
    }
    .eg-delete-overlay button:hover {
        background: #dc2626;
        transform: scale(1.1);
    }

    /* ── Caption strip ─────────────────────────────────────── */
    .eg-caption {
        padding: 8px 12px;
        font-size: 0.8rem;
        color: var(--text-muted);
        border-top: 1px solid var(--border, #e5e7eb);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-height: 34px;
    }

    /* ── Grid scroll area ──────────────────────────────────── */
    .eg-grid-scroll {
        max-height: 62vh;
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 4px;
    }
    .eg-grid-scroll::-webkit-scrollbar { width: 5px; }
    .eg-grid-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

    /* ── Sidebar scroll ────────────────────────────────────── */
    .eg-sidebar-scroll {
        max-height: 62vh;
        overflow-y: auto;
    }
    .eg-sidebar-scroll::-webkit-scrollbar { width: 4px; }
    .eg-sidebar-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

    /* ── Empty state ───────────────────────────────────────── */
    .eg-empty {
        padding: 60px 24px;
        text-align: center;
        color: var(--text-muted);
    }
    .eg-empty i { font-size: 3rem; opacity: .3; display: block; margin-bottom: 12px; }
</style>
@endpush

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Page Header --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h4 mb-0 fw-bold">Event Gallery</h1>
        <p class="text-muted mb-0 small mt-1">Manage event photos &amp; videos</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-2"
                data-bs-toggle="modal" data-bs-target="#addEventModal">
            <i class="bi bi-calendar-plus"></i> Add Event
        </button>
        <button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2"
                data-bs-toggle="modal" data-bs-target="#addMediaModal">
            <i class="bi bi-upload"></i> Add Media
        </button>
    </div>
</div>

<div class="row g-3">
    {{-- ── Left: Event List ───────────────────────────────── --}}
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="q" id="eventSearch"
                           class="form-control form-control-sm"
                           placeholder="Search events…"
                           value="{{ $search }}">
                    <button class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>
            <div class="eg-sidebar-scroll">
                @forelse ($events as $event)
                    @php
                        $isActive  = $selectedEvent && $selectedEvent->id === $event->id;
                        $eventDate = $event->event_date ? $event->event_date->format('M d, Y') : 'Date TBD';
                        $query     = array_filter(['event_id' => $event->id, 'q' => $search]);
                        $imgCount  = $event->images_count ?? 0;
                        $vidCount  = $event->videos_count ?? 0;
                    @endphp
                    <a href="{{ route('admin.event-gallery.index', $query) }}"
                       class="eg-sidebar-item {{ $isActive ? 'active' : '' }}">
                        <div class="fw-semibold">{{ $event->event_name }}</div>
                        <div class="eg-item-meta">{{ $eventDate }}</div>
                        <div class="eg-item-meta d-flex align-items-center gap-2 mt-1">
                            <span><i class="bi bi-image me-1"></i>{{ $imgCount }}</span>
                            <span><i class="bi bi-camera-video me-1"></i>{{ $vidCount }}</span>
                        </div>
                    </a>
                @empty
                    <div class="eg-empty">
                        <i class="bi bi-calendar-x"></i>
                        No events found.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ── Right: Media Grid ──────────────────────────────── --}}
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                @if ($selectedEvent)
                    {{-- Header --}}
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 pb-3 border-bottom">
                        <div>
                            <h2 class="h5 fw-bold mb-1">{{ $selectedEvent->event_name }}</h2>
                            <div class="text-muted small">
                                <i class="bi bi-calendar3 me-1"></i>
                                {{ $selectedEvent->event_date ? $selectedEvent->event_date->format('M d, Y') : 'Date TBD' }}
                                <span class="ms-2 badge rounded-pill bg-primary-subtle text-primary px-2.5 py-1" style="font-size:.75rem;">
                                    {{ $selectedEvent->media->count() }} media items
                                </span>
                            </div>
                            @if ($selectedEvent->description)
                                <p class="mt-1 mb-0 small text-muted">{{ $selectedEvent->description }}</p>
                            @endif
                        </div>
                        <div>
                            <button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1.5 shadow-sm cursor-pointer"
                                    onclick="openUploadMediaModalForEvent('{{ $selectedEvent->id }}', '{{ addslashes($selectedEvent->event_name) }}')">
                                <i class="bi bi-plus-lg"></i> Add Media to {{ $selectedEvent->event_name }}
                            </button>
                        </div>
                    </div>

                    {{-- Grid --}}
                    <div class="eg-grid-scroll">
                        <div class="row g-3">
                            @forelse ($selectedEvent->media as $media)
                                <div class="col-sm-6 col-xl-4">
                                    <div class="eg-media-card">

                                        {{-- Thumbnail --}}
                                        <div class="eg-thumb">
                                            @if ($media->media_type === 'video' && $media->thumbnail_url)
                                                <a href="{{ $media->url }}" target="_blank" rel="noopener" class="eg-play-overlay" title="Play video">
                                                    <img src="{{ $media->thumbnail_url }}" alt="Thumbnail">
                                                    <span class="position-absolute"><i class="bi bi-play-circle-fill"></i></span>
                                                </a>
                                            @elseif ($media->media_type === 'video')
                                                <video preload="metadata">
                                                    <source src="{{ $media->url }}" type="video/mp4">
                                                </video>
                                                <a href="{{ $media->url }}" target="_blank" rel="noopener" class="eg-play-overlay" title="Play video">
                                                    <i class="bi bi-play-circle-fill"></i>
                                                </a>
                                            @else
                                                <a href="{{ $media->url }}" target="_blank" rel="noopener" title="Open image">
                                                    <img src="{{ $media->url }}" alt="Event media" loading="lazy">
                                                </a>
                                            @endif
                                        </div>

                                        {{-- Delete button (hover overlay) --}}
                                        <div class="eg-delete-overlay">
                                            <form method="POST"
                                                  action="{{ route('admin.event-gallery.media.destroy', $media->id) }}"
                                                  class="eg-delete-form"
                                                  data-confirm="Delete this media item? This cannot be undone.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Delete">
                                                    <i class="bi bi-trash3-fill"></i>
                                                </button>
                                            </form>
                                        </div>

                                        {{-- Caption --}}
                                        @if ($media->caption)
                                            <div class="eg-caption">
                                                {{ $media->caption }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="eg-empty">
                                        <i class="bi bi-images"></i>
                                        No media added yet.
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#addMediaModal">
                                                <i class="bi bi-upload me-1"></i> Upload Media
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @else
                    <div class="eg-empty">
                        <i class="bi bi-hand-index-thumb"></i>
                        Select an event from the list to view its media.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── Add Event Modal ────────────────────────────────────── --}}
<div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.event-gallery.events.store') }}">
                @csrf
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold" id="addEventModalLabel">
                        <i class="bi bi-calendar-plus text-primary me-2"></i>Add Event
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Event Name <span class="text-danger">*</span></label>
                        <input type="text" name="event_name" class="form-control" required maxlength="180"
                               placeholder="e.g. Family Socials 2026">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Event Date</label>
                        <input type="date" name="event_date" class="form-control">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" rows="3" class="form-control"
                                  placeholder="Brief description…"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check2 me-1"></i> Save Event
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Add Media Modal ────────────────────────────────────── --}}
<div class="modal fade" id="addMediaModal" tabindex="-1" aria-labelledby="addMediaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.event-gallery.media.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold" id="addMediaModalLabel">
                        <i class="bi bi-upload text-primary me-2"></i>Upload Media
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Existing Event <span class="text-danger">*</span></label>
                            <select name="event_gallery_id" id="modalEventGallerySelect" class="form-select js-no-searchable-select">
                                <option value="">-- Select Event --</option>
                                @foreach ($events as $event)
                                    <option value="{{ $event->id }}" @selected($selectedEvent && $selectedEvent->id === $event->id)>
                                        {{ $event->event_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Or Create New Event</label>
                            <input type="text" name="event_name" id="modalNewEventName" class="form-control" maxlength="180"
                                   placeholder="New event name…">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Media Type <span class="text-danger">*</span></label>
                            <select name="media_type" class="form-select js-no-searchable-select" required>
                                <option value="image">Image</option>
                                <option value="video">Video</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Caption <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="text" name="caption" class="form-control" maxlength="255"
                                   placeholder="Short caption…">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Upload Files <span class="text-danger">*</span></label>
                            <input type="file" name="file[]" class="form-control" multiple required>
                            <div class="form-text">You can select multiple images or videos at once.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Video Thumbnail <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="file" name="thumbnail_file" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-cloud-upload me-1"></i> Save Media
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Helper to open upload media modal pre-bound to a specific event
    window.openUploadMediaModalForEvent = function(eventId, eventName) {
        const select = document.getElementById('modalEventGallerySelect');
        if (select && eventId) {
            select.value = eventId;
        }
        const modalTitle = document.getElementById('addMediaModalLabel');
        if (modalTitle && eventName) {
            modalTitle.innerHTML = `<i class="bi bi-upload text-primary me-2"></i>Upload Media for <strong>${eventName}</strong>`;
        }
        const modalEl = document.getElementById('addMediaModal');
        if (modalEl) {
            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        }
    };

    // Confirm before deleting media
    document.querySelectorAll('.eg-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const msg = form.dataset.confirm || 'Delete this item?';
            if (window.confirm(msg)) {
                form.submit();
            }
        });
    });
</script>
@endpush
