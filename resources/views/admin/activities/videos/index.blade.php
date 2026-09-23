@extends('admin.layouts.app')

@section('title', 'Activity Video Management')

@push('styles')
<style>
.video-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 16px 18px;
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 4px rgba(15, 23, 42, 0.02);
}
.video-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
}
.activity-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    transition: all 0.2s ease;
    overflow: hidden;
}
.activity-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
}
.video-thumb-container {
    height: 140px;
    background: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}
.video-thumb-container img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0.85;
    transition: transform 0.3s ease;
}
.activity-card:hover .video-thumb-container img {
    transform: scale(1.05);
    opacity: 0.95;
}
.video-play-overlay {
    position: absolute;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.9);
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    transition: all 0.2s ease;
    cursor: pointer;
}
.video-play-overlay:hover {
    transform: scale(1.15);
    background: #ffffff;
    color: #dc2626;
}
</style>
@endpush

@section('content')
<div class="space-y-4">

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(239,68,68,0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    <i class="bi bi-play-btn-fill"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0" style="color: #0f172a; font-size: 1.25rem;">Activity Video Management</h4>
                    <p class="text-muted mb-0" style="font-size: 0.82rem;">Manage founder & explainer videos (YouTube URLs or uploaded MP4 files) for all activities</p>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-primary px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm"
                    style="background: #0d9488; border-color: #0d9488; border-radius: 10px;"
                    onclick="openVideoModal()">
                <i class="bi bi-plus-circle-fill"></i> Configure Activity Video
            </button>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center gap-2" role="alert" style="border-radius: 12px; background: #ecfdf5; color: #065f46;">
        <i class="bi bi-check-circle-fill fs-5 text-success"></i>
        <div>{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center gap-2" role="alert" style="border-radius: 12px; background: #fef2f2; color: #991b1b;">
        <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
        <div>{{ session('error') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    {{-- 6 Summary Stat Cards --}}
    <div class="row g-3">
        <div class="col-6 col-md-2">
            <div class="video-stat-card">
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 11px;">Total Activities</div>
                <div class="fs-3 fw-bold text-dark mt-1">{{ $stats['total_activities'] }}</div>
                <div class="text-muted small" style="font-size: 11px;">System activities</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="video-stat-card">
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 11px;">Configured</div>
                <div class="fs-3 fw-bold text-teal-600 mt-1" style="color: #0d9488;">{{ $stats['configured_count'] }}</div>
                <div class="text-muted small" style="font-size: 11px;">With video attached</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="video-stat-card">
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 11px;">Active</div>
                <div class="fs-3 fw-bold text-success mt-1">{{ $stats['active_count'] }}</div>
                <div class="text-muted small" style="font-size: 11px;">Visible in Mobile API</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="video-stat-card">
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 11px;">YouTube Links</div>
                <div class="fs-3 fw-bold text-danger mt-1">{{ $stats['youtube_count'] }}</div>
                <div class="text-muted small" style="font-size: 11px;">Streamed from YouTube</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="video-stat-card">
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 11px;">Uploaded Files</div>
                <div class="fs-3 fw-bold text-primary mt-1">{{ $stats['file_count'] }}</div>
                <div class="text-muted small" style="font-size: 11px;">Stored MP4/Video</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="video-stat-card">
                <div class="text-muted small text-uppercase fw-bold" style="font-size: 11px;">Pending</div>
                <div class="fs-3 fw-bold text-warning mt-1">{{ $stats['missing_count'] }}</div>
                <div class="text-muted small" style="font-size: 11px;">No video configured</div>
            </div>
        </div>
    </div>

    {{-- Activities Video Cards Grid --}}
    <div class="card border shadow-sm" style="border-radius: 16px; overflow: hidden;">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <span class="fw-bold text-dark" style="font-size: 0.95rem;">Activity Video Catalog</span>
                <span class="badge bg-light text-dark border ms-2 px-2 py-1" style="font-size: 11px;">{{ count($activities) }} Activities</span>
            </div>
            <div class="text-muted small">
                <i class="bi bi-info-circle me-1 text-primary"></i> Mobile API endpoint: <code class="bg-light px-2 py-0.5 rounded text-dark">GET /api/activities/videos</code>
            </div>
        </div>

        <div class="card-body p-4 bg-light">
            <div class="row g-3">
                @foreach($activities as $key => $name)
                @php
                    $video = $configured->get($key);
                    $hasVideo = ($video && !empty($video->video_url));
                    $isYt = ($hasVideo && $video->video_type === 'youtube');
                    $isFile = ($hasVideo && $video->video_type === 'file');
                    $isActive = ($hasVideo && $video->is_active);

                    // Extract YouTube video ID for thumbnail preview
                    $ytId = '';
                    if ($isYt) {
                        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $video->video_url, $match)) {
                            $ytId = $match[1];
                        }
                    }
                @endphp
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="activity-card h-100 d-flex flex-column">
                        
                        {{-- Video Thumbnail / Banner --}}
                        <div class="video-thumb-container">
                            @if($isYt && $ytId)
                                <img src="https://img.youtube.com/vi/{{ $ytId }}/mqdefault.jpg" alt="{{ $name }}" onerror="this.src='https://placehold.co/400x200/0f172a/ffffff?text={{ urlencode($name) }}';">
                                <div class="video-play-overlay" onclick="openPlayerModal('youtube', '{{ $video->video_url }}', '{{ $name }}')">
                                    <i class="bi bi-play-fill"></i>
                                </div>
                            @elseif($isFile)
                                <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-white p-3 text-center" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
                                    <i class="bi bi-file-earmark-play-fill fs-1 text-primary mb-1"></i>
                                    <span class="small fw-semibold">Uploaded Video File</span>
                                </div>
                                <div class="video-play-overlay" onclick="openPlayerModal('file', '{{ $video->video_url }}', '{{ $name }}')">
                                    <i class="bi bi-play-fill"></i>
                                </div>
                            @else
                                <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted p-3 text-center" style="background: #e2e8f0;">
                                    <i class="bi bi-camera-video-off fs-2 mb-1 opacity-50"></i>
                                    <span class="small fw-semibold text-secondary">No Video Configured</span>
                                </div>
                            @endif

                            {{-- Activity badge top left --}}
                            <div style="position: absolute; top: 10px; left: 10px;">
                                <span class="badge bg-dark text-white bg-opacity-75 shadow-xs px-2.5 py-1" style="border-radius: 8px; font-size: 11px;">
                                    {{ $name }}
                                </span>
                            </div>

                            {{-- Status badge top right --}}
                            <div style="position: absolute; top: 10px; right: 10px;">
                                @if(!$hasVideo)
                                    <span class="badge bg-secondary bg-opacity-75 shadow-xs px-2 py-1" style="border-radius: 8px; font-size: 10.5px;">Pending</span>
                                @elseif($isActive)
                                    <span class="badge bg-success shadow-xs px-2.5 py-1" style="border-radius: 8px; font-size: 10.5px;"><i class="bi bi-check-circle me-1"></i>Active</span>
                                @else
                                    <span class="badge bg-danger shadow-xs px-2.5 py-1" style="border-radius: 8px; font-size: 10.5px;"><i class="bi bi-x-circle me-1"></i>Inactive</span>
                                @endif
                            </div>
                        </div>

                        {{-- Card Body --}}
                        <div class="card-body p-3 d-flex flex-column justify-content-between flex-grow-1">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="fw-bold text-dark mb-0" style="font-size: 14px;">{{ $name }}</h6>
                                    @if($isYt)
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 rounded-pill" style="font-size: 10.5px;">
                                            <i class="bi bi-youtube me-1"></i> YouTube
                                        </span>
                                    @elseif($isFile)
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5 rounded-pill" style="font-size: 10.5px;">
                                            <i class="bi bi-file-earmark-play me-1"></i> Uploaded
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted border px-2 py-0.5 rounded-pill" style="font-size: 10.5px;">
                                            Not set
                                        </span>
                                    @endif
                                </div>

                                @if($hasVideo)
                                    <div class="bg-light p-2 rounded-3 border mb-3">
                                        <div class="text-muted truncate small" style="font-size: 11px;">
                                            <i class="bi bi-link-45deg me-1"></i>
                                            <a href="{{ $video->video_url }}" target="_blank" class="text-decoration-none text-teal-700" title="{{ $video->video_url }}">
                                                {{ $video->video_url }}
                                            </a>
                                        </div>
                                    </div>
                                @else
                                    <p class="text-muted small mb-3" style="font-size: 11.5px;">
                                        Founder or explainer video has not been attached yet. Click configure to add a YouTube link or upload a file.
                                    </p>
                                @endif
                            </div>

                            {{-- Actions Footer --}}
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top gap-2">
                                @if($hasVideo)
                                    <div class="d-flex align-items-center gap-1">
                                        <form method="POST" action="{{ route('admin.activities.videos.toggle', $video->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-light border py-1 px-2.5 rounded-pill" style="font-size: 11px;" title="{{ $isActive ? 'Deactivate video' : 'Activate video' }}">
                                                @if($isActive)
                                                    <i class="bi bi-toggle-on text-success fs-6"></i>
                                                @else
                                                    <i class="bi bi-toggle-off text-muted fs-6"></i>
                                                @endif
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.activities.videos.destroy', $video->id) }}" onsubmit="return confirm('Are you sure you want to remove the video configuration for {{ $name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2 rounded-pill" style="font-size: 11px;" title="Delete video">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <button type="button" class="btn btn-sm btn-outline-primary py-1 px-3 rounded-pill fw-semibold" style="font-size: 11.5px;"
                                            onclick="openVideoModal('{{ $key }}', '{{ $name }}', '{{ $video->video_type }}', '{{ $video->video_url }}', {{ $video->is_active ? 'true' : 'false' }})">
                                        <i class="bi bi-pencil me-1"></i> Edit Video
                                    </button>
                                @else
                                    <div></div>
                                    <button type="button" class="btn btn-sm btn-primary py-1 px-3 rounded-pill fw-semibold"
                                            style="background: #0d9488; border-color: #0d9488; font-size: 11.5px;"
                                            onclick="openVideoModal('{{ $key }}', '{{ $name }}')">
                                        <i class="bi bi-plus-circle me-1"></i> Configure
                                    </button>
                                @endif
                            </div>
                        </div>

                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

</div>

{{-- ── CONFIGURE / EDIT VIDEO MODAL ── --}}
<div class="modal fade" id="activityVideoModal" tabindex="-1" aria-labelledby="activityVideoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="border-radius: 20px; overflow: hidden;">
            <form method="POST" action="{{ route('admin.activities.videos.store') }}" enctype="multipart/form-data" id="activityVideoForm">
                @csrf

                {{-- Modal Header --}}
                <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 34px; height: 34px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 18px;">
                            <i class="bi bi-camera-video-fill"></i>
                        </div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="activityVideoModalLabel" style="font-size: 1.05rem;">Configure Activity Video</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                {{-- Modal Body --}}
                <div class="modal-body p-4">
                    {{-- Activity Select --}}
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Select Activity <span class="text-danger">*</span></label>
                        <select name="activity_key" id="modalActivityKey" class="form-select form-select-sm" style="border-radius: 10px;" required>
                            <option value="">-- Choose Activity --</option>
                            @foreach($activities as $k => $n)
                                <option value="{{ $k }}">{{ $n }} ({{ $k }})</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Video Source Type Radio Selector --}}
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Video Source <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="border rounded-3 p-3 text-center d-block cursor-pointer position-relative source-card" id="sourceCardYt" style="cursor: pointer;">
                                    <input type="radio" name="video_type" value="youtube" id="typeYt" class="position-absolute top-0 end-0 m-2" checked onchange="toggleSourceType('youtube')">
                                    <i class="bi bi-youtube fs-2 text-danger d-block mb-1"></i>
                                    <div class="fw-bold small text-dark">YouTube Video</div>
                                    <div class="text-muted" style="font-size: 10.5px;">Founder doc YouTube link</div>
                                </label>
                            </div>
                            <div class="col-6">
                                <label class="border rounded-3 p-3 text-center d-block cursor-pointer position-relative source-card" id="sourceCardFile" style="cursor: pointer;">
                                    <input type="radio" name="video_type" value="file" id="typeFile" class="position-absolute top-0 end-0 m-2" onchange="toggleSourceType('file')">
                                    <i class="bi bi-cloud-arrow-up-fill fs-2 text-primary d-block mb-1"></i>
                                    <div class="fw-bold small text-dark">Upload Video</div>
                                    <div class="text-muted" style="font-size: 10.5px;">Direct MP4 / WebM upload</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- YouTube URL Input --}}
                    <div class="mb-3" id="youtubeInputGroup">
                        <label class="form-label fw-bold text-dark small">YouTube Video URL <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-danger"><i class="bi bi-youtube"></i></span>
                            <input type="url" name="youtube_url" id="modalYoutubeUrl" class="form-control" placeholder="https://www.youtube.com/watch?v=..." style="border-radius: 0 8px 8px 0;">
                        </div>
                        <div class="form-text text-muted" style="font-size: 11px;">Supported: standard youtube.com watch links or youtu.be shortlinks.</div>
                    </div>

                    {{-- File Upload Input --}}
                    <div class="mb-3 d-none" id="fileInputGroup">
                        <label class="form-label fw-bold text-dark small">Upload Video File <span class="text-danger">*</span></label>
                        <input type="file" name="video_file" id="modalVideoFile" class="form-control form-control-sm" accept="video/mp4,video/webm,video/quicktime,video/x-matroska" style="border-radius: 8px;">
                        <div class="form-text text-muted" style="font-size: 11px;">Supported: MP4, WebM, MOV (Max size: 50MB).</div>
                        <div id="modalExistingFileNotice" class="small text-muted mt-1 d-none">
                            <i class="bi bi-check-circle text-success me-1"></i> An uploaded video file is already saved. Leave empty to keep existing file.
                        </div>
                    </div>

                    {{-- Active Toggle --}}
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="modalIsActive" checked>
                        <label class="form-check-label fw-bold text-dark small" for="modalIsActive">
                            Active (Expose to Mobile / Flutter API)
                        </label>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-secondary px-3" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-4 fw-semibold" style="background: #0d9488; border-color: #0d9488; border-radius: 8px;">
                        <i class="bi bi-save me-1"></i> Save Video Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── VIDEO PLAYER MODAL ── --}}
<div class="modal fade" id="videoPlayerModal" tabindex="-1" aria-labelledby="videoPlayerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-xl border-0 bg-dark text-white" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header border-secondary px-4 py-3 bg-dark">
                <h5 class="modal-title fw-bold text-white mb-0" id="videoPlayerModalLabel" style="font-size: 1.05rem;">
                    <i class="bi bi-play-circle-fill text-danger me-2"></i> Activity Video Preview
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="stopVideoPlayer()"></button>
            </div>
            <div class="modal-body p-0 bg-black text-center" style="min-height: 400px; display: flex; align-items: center; justify-content: center;">
                <div id="playerContainer" class="w-100" style="height: 440px;">
                    <!-- Embedded iframe or HTML5 video dynamically injected -->
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleSourceType(type) {
    const ytGroup = document.getElementById('youtubeInputGroup');
    const fileGroup = document.getElementById('fileInputGroup');
    const cardYt = document.getElementById('sourceCardYt');
    const cardFile = document.getElementById('sourceCardFile');

    if (type === 'youtube') {
        if (ytGroup) ytGroup.classList.remove('d-none');
        if (fileGroup) fileGroup.classList.add('d-none');
        if (cardYt) cardYt.style.borderColor = '#0d9488';
        if (cardFile) cardFile.style.borderColor = '#e2e8f0';
    } else {
        if (ytGroup) ytGroup.classList.add('d-none');
        if (fileGroup) fileGroup.classList.remove('d-none');
        if (cardFile) cardFile.style.borderColor = '#0d9488';
        if (cardYt) cardYt.style.borderColor = '#e2e8f0';
    }
}

function openVideoModal(key = '', name = '', type = 'youtube', url = '', isActive = true) {
    const modalEl = document.getElementById('activityVideoModal');
    if (!modalEl) return;

    const selectEl = document.getElementById('modalActivityKey');
    const ytInput = document.getElementById('modalYoutubeUrl');
    const activeCheck = document.getElementById('modalIsActive');
    const titleEl = document.getElementById('activityVideoModalLabel');
    const noticeEl = document.getElementById('modalExistingFileNotice');

    if (key) {
        if (selectEl) selectEl.value = key;
        if (titleEl) titleEl.textContent = 'Edit Video — ' + name;
    } else {
        if (selectEl) selectEl.value = '';
        if (titleEl) titleEl.textContent = 'Configure Activity Video';
    }

    if (type === 'youtube') {
        document.getElementById('typeYt').checked = true;
        toggleSourceType('youtube');
        if (ytInput) ytInput.value = url || '';
        if (noticeEl) noticeEl.classList.add('d-none');
    } else {
        document.getElementById('typeFile').checked = true;
        toggleSourceType('file');
        if (ytInput) ytInput.value = '';
        if (url && noticeEl) noticeEl.classList.remove('d-none');
        else if (noticeEl) noticeEl.classList.add('d-none');
    }

    if (activeCheck) activeCheck.checked = isActive;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function openPlayerModal(type, url, title) {
    const modalEl = document.getElementById('videoPlayerModal');
    const container = document.getElementById('playerContainer');
    const titleEl = document.getElementById('videoPlayerModalLabel');

    if (titleEl) titleEl.innerHTML = `<i class="bi bi-play-circle-fill text-danger me-2"></i> ${title}`;

    if (type === 'youtube') {
        let ytId = '';
        const match = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i);
        if (match) ytId = match[1];

        if (ytId) {
            container.innerHTML = `<iframe src="https://www.youtube.com/embed/${ytId}?autoplay=1" class="w-100 h-100 border-0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
        } else {
            container.innerHTML = `<iframe src="${url}" class="w-100 h-100 border-0" allowfullscreen></iframe>`;
        }
    } else {
        container.innerHTML = `
            <video controls autoplay class="w-100 h-100" style="object-fit: contain;">
                <source src="${url}" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        `;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function stopVideoPlayer() {
    const container = document.getElementById('playerContainer');
    if (container) container.innerHTML = '';
}

document.getElementById('videoPlayerModal')?.addEventListener('hidden.bs.modal', function () {
    stopVideoPlayer();
});
</script>
@endpush
