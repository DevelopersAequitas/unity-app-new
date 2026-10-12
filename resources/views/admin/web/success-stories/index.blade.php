@extends('admin.layouts.app')

@section('title', 'Add Media for Homepage - Success Stories')

@section('content')
<div class="container-fluid px-0">

    {{-- 1. Top Header Bar --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-indigo-subtle text-indigo rounded-pill px-2.5 py-1" style="background: rgba(99, 102, 241, 0.12); color: #6366f1; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-camera-reels me-1"></i> Add Media for Homepage
                </span>
                <span class="text-muted" style="font-size: 0.8rem;">● Success Stories</span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Homepage Success Stories & Case Studies</h1>
            <p class="text-muted small mb-0 mt-0.5">Manage YouTube video links, member case studies, and cover portrait photos for the homepage collage section.</p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-primary btn-sm rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-1.5 shadow-sm" data-bs-toggle="modal" data-bs-target="#storyModal" onclick="resetStoryModal()">
                <i class="bi bi-plus-lg"></i>
                <span>Add Success Story</span>
            </button>
            <a href="https://peersglobal.com" target="_blank" class="btn btn-outline-secondary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs">
                <i class="bi bi-box-arrow-up-right"></i>
                <span class="fw-semibold">Visit Live Site</span>
            </a>
        </div>
    </div>

    {{-- Alerts --}}
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
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-xs mb-4" role="alert">
            <strong class="d-block mb-1"><i class="bi bi-exclamation-circle me-1"></i> Please check the form errors:</strong>
            <ul class="mb-0 ps-3 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- 2. KPI Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.68rem;">TOTAL STORIES</span>
                    <div class="p-2 rounded-3" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                        <i class="bi bi-play-circle-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-dark mb-0">{{ $stats['total'] }}</div>
                <div class="text-muted small mt-1">Configured for homepage</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.68rem;">ACTIVE ON SITE</span>
                    <div class="p-2 rounded-3" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-dark mb-0">{{ $stats['active'] }}</div>
                <div class="text-success small mt-1"><i class="bi bi-eye"></i> Live in story collage</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.68rem;">CUSTOM PORTRAITS</span>
                    <div class="p-2 rounded-3" style="background: rgba(168, 85, 247, 0.1); color: #a855f7;">
                        <i class="bi bi-person-bounding-box"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-dark mb-0">{{ $stats['customCovers'] }}</div>
                <div class="text-muted small mt-1">Uploaded custom photos</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.68rem;">AUTO YOUTUBE COVERS</span>
                    <div class="p-2 rounded-3" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                        <i class="bi bi-youtube"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-dark mb-0">{{ $stats['youtubeThumbs'] }}</div>
                <div class="text-muted small mt-1">Direct from YouTube link</div>
            </div>
        </div>
    </div>

    {{-- 3. Explanatory Guide Box regarding YouTube Thumbnail vs Custom Cover --}}
    <div class="card border-0 shadow-xs rounded-4 p-3 p-md-4 mb-4" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(236, 72, 153, 0.05) 100%); border-left: 4px solid #6366f1 !important;">
        <div class="d-flex align-items-start gap-3">
            <div class="p-2.5 rounded-3 bg-white text-indigo shadow-xs flex-shrink-0" style="color: #6366f1;">
                <i class="bi bi-lightbulb-fill fs-5"></i>
            </div>
            <div>
                <h5 class="fw-bold text-dark mb-1" style="font-size: 0.95rem;">How Cover Media Works for YouTube Success Stories</h5>
                <p class="text-muted small mb-0 lh-base">
                    <strong>1. Automatic YouTube Cover:</strong> When you paste a YouTube link, the system automatically pulls the high-definition YouTube thumbnail so you don't even need to upload an image.<br>
                    <strong>2. Optional Custom Portrait Photo:</strong> In the website's success story layout (as seen in the Mindvalley-style floating collage), cards look best with clean, portrait/square headshots of members. If you upload a custom photo, the website will use that photo for the card, and clicking it will smoothly open and play the YouTube video!
                </p>
            </div>
        </div>
    </div>

    {{-- 4. Filter Toolbar & View Switcher --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-3 bg-white rounded-4 border shadow-xs mb-4">
        <form method="GET" action="{{ route('admin.web.success-stories.index') }}" class="d-flex align-items-center gap-2 flex-grow-1 flex-wrap">
            <div class="input-group input-group-sm" style="max-width: 320px;">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="Search member, company, title..." value="{{ $filters['search'] ?? '' }}">
            </div>

            <select name="status" class="form-select form-select-sm" style="max-width: 150px;" onchange="this.form.submit()">
                <option value="all" {{ ($filters['status'] ?? '') === 'all' ? 'selected' : '' }}>All Status</option>
                <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active Only</option>
                <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Hidden Only</option>
            </select>

            <button type="submit" class="btn btn-light btn-sm border">Filter</button>
            @if(!empty($filters['search']) || !empty($filters['status']))
                <a href="{{ route('admin.web.success-stories.index') }}" class="btn btn-link btn-sm text-muted text-decoration-none">Clear</a>
            @endif
        </form>

        <div class="d-flex align-items-center gap-1 bg-light p-1 rounded-3">
            <button type="button" class="btn btn-sm px-2.5 py-1 rounded-2 fw-semibold btn-white shadow-xs active" id="btnViewGrid" onclick="switchView('grid')">
                <i class="bi bi-grid-fill me-1"></i> Collage Grid
            </button>
            <button type="button" class="btn btn-sm px-2.5 py-1 rounded-2 text-muted fw-semibold" id="btnViewTable" onclick="switchView('table')">
                <i class="bi bi-table me-1"></i> Table View
            </button>
        </div>
    </div>

    {{-- 5. Empty State --}}
    @if($stories->isEmpty())
        <div class="card border-0 shadow-xs rounded-4 p-5 text-center bg-white">
            <div class="mx-auto mb-3 p-3 rounded-circle bg-light d-inline-flex text-indigo" style="width: 64px; height: 64px; color: #6366f1;">
                <i class="bi bi-camera-reels fs-3 m-auto"></i>
            </div>
            <h4 class="fw-bold text-dark">No Success Stories Found</h4>
            <p class="text-muted small mx-auto mb-3" style="max-width: 420px;">
                Start building your homepage story collage by adding your first YouTube success story link and cover image.
            </p>
            <div>
                <button type="button" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#storyModal" onclick="resetStoryModal()">
                    <i class="bi bi-plus-lg me-1"></i> Add Your First Story
                </button>
            </div>
        </div>
    @else

        {{-- 6. Collage Visual Grid View --}}
        <div id="viewGridContainer">
            <div class="row g-4 mb-4">
                @foreach($stories as $story)
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                        <div class="card border-0 shadow-xs rounded-4 overflow-hidden bg-white h-100 hover-shadow transition position-relative">
                            {{-- Cover Image Container with 4:5 Portrait Ratio --}}
                            <div class="position-relative bg-light" style="padding-top: 110%; overflow: hidden;">
                                <img src="{{ $story->cover_image_url }}" 
                                     alt="{{ $story->person_name }}" 
                                     class="position-absolute top-0 start-0 w-100 h-100" 
                                     style="object-fit: cover; transition: transform 0.3s ease;"
                                     onerror="this.src='https://img.youtube.com/vi/{{ $story->youtube_video_id }}/hqdefault.jpg'">

                                {{-- Dark Gradient Overlay on Hover --}}
                                <div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-between p-3" 
                                     style="background: linear-gradient(180deg, rgba(0,0,0,0.4) 0%, rgba(0,0,0,0) 40%, rgba(0,0,0,0.7) 100%);">
                                    
                                    {{-- Top Badges --}}
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="badge {{ $story->is_active ? 'bg-success' : 'bg-secondary' }} rounded-pill px-2.5 py-1 shadow-xs" style="font-size: 0.65rem;">
                                            {{ $story->is_active ? '● Live' : '○ Hidden' }}
                                        </span>

                                        <span class="badge bg-dark bg-opacity-75 text-white rounded-pill px-2.5 py-1 shadow-xs" style="font-size: 0.65rem;">
                                            @if($story->has_custom_cover)
                                                <i class="bi bi-image me-1"></i> Custom Portrait
                                            @else
                                                <i class="bi bi-youtube text-danger me-1"></i> YouTube Cover
                                            @endif
                                        </span>
                                    </div>

                                    {{-- Centered Play Button Trigger --}}
                                    <div class="d-flex align-items-center justify-content-center">
                                        <button type="button" 
                                                class="btn btn-danger rounded-circle shadow-lg p-0 d-flex align-items-center justify-content-center" 
                                                style="width: 48px; height: 48px; transition: transform 0.2s;"
                                                onclick="openVideoModal('{{ $story->youtube_video_id }}', '{{ addslashes($story->person_name) }}')"
                                                title="Preview YouTube Video">
                                            <i class="bi bi-play-fill fs-4 ms-0.5"></i>
                                        </button>
                                    </div>

                                    {{-- Bottom Overlay Info --}}
                                    <div>
                                        <h6 class="text-white fw-bold mb-0 text-shadow">{{ $story->person_name }}</h6>
                                        @if($story->designation || $story->company)
                                            <div class="text-white-50 small text-truncate" style="font-size: 0.72rem;">
                                                {{ $story->designation }}{{ ($story->designation && $story->company) ? ' • ' : '' }}{{ $story->company }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Card Body --}}
                            <div class="p-3 d-flex flex-column justify-content-between flex-grow-1">
                                <div>
                                    @if($story->story_title)
                                        <div class="fw-semibold text-dark small mb-1 line-clamp-1">
                                            {{ $story->story_title }}
                                        </div>
                                    @endif
                                    @if($story->quote)
                                        <p class="text-muted small mb-2 line-clamp-2" style="font-size: 0.76rem; font-style: italic;">
                                            "{{ $story->quote }}"
                                        </p>
                                    @endif
                                </div>

                                <div class="pt-2 border-top d-flex align-items-center justify-content-between mt-auto">
                                    <div class="d-flex align-items-center gap-1.5">
                                        <span class="badge bg-light text-muted border rounded-pill px-2" style="font-size: 0.65rem;">
                                            Order: #{{ $story->sort_order }}
                                        </span>
                                    </div>

                                    <div class="d-flex align-items-center gap-1">
                                        {{-- Toggle Active --}}
                                        <form method="POST" action="{{ route('admin.web.success-stories.toggle-status', $story->id) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-light border p-1 rounded-2" title="{{ $story->is_active ? 'Hide Story' : 'Publish Story' }}">
                                                <i class="bi {{ $story->is_active ? 'bi-eye-slash text-muted' : 'bi-eye text-success' }}"></i>
                                            </button>
                                        </form>

                                        {{-- Edit Button --}}
                                        <button type="button" 
                                                class="btn btn-sm btn-light border p-1 rounded-2 text-primary" 
                                                title="Edit Story"
                                                onclick='editStory(@json($story))'>
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        {{-- Delete Button --}}
                                        <form method="POST" action="{{ route('admin.web.success-stories.destroy', $story->id) }}" class="d-inline" onsubmit="return confirm('Delete this success story?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light border p-1 rounded-2 text-danger" title="Delete Story">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 7. Detailed Table View --}}
        <div id="viewTableContainer" class="d-none">
            <div class="card border-0 shadow-xs rounded-4 overflow-hidden bg-white mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr class="text-uppercase text-muted fw-bold" style="font-size: 0.68rem; letter-spacing: 0.05em;">
                                <th class="ps-3 py-3" style="width: 70px;">Cover</th>
                                <th>Person & Role</th>
                                <th>Story Title & Quote</th>
                                <th>YouTube Link</th>
                                <th class="text-center" style="width: 80px;">Order</th>
                                <th class="text-center" style="width: 100px;">Status</th>
                                <th class="pe-3 text-end" style="width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stories as $story)
                                <tr>
                                    <td class="ps-3 py-2">
                                        <div class="position-relative rounded-3 overflow-hidden shadow-xs cursor-pointer" 
                                             style="width: 50px; height: 50px; background: #eee;"
                                             onclick="openVideoModal('{{ $story->youtube_video_id }}', '{{ addslashes($story->person_name) }}')">
                                            <img src="{{ $story->cover_image_url }}" 
                                                 alt="{{ $story->person_name }}" 
                                                 class="w-100 h-100" 
                                                 style="object-fit: cover;"
                                                 onerror="this.src='https://img.youtube.com/vi/{{ $story->youtube_video_id }}/hqdefault.jpg'">
                                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-25 hover-bg-opacity-50 transition">
                                                <i class="bi bi-play-fill text-white fs-6"></i>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $story->person_name }}</div>
                                        <div class="text-muted small">
                                            {{ $story->designation ?? '—' }} {{ $story->company ? '('.$story->company.')' : '' }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark small">{{ $story->story_title ?? 'Untitled Story' }}</div>
                                        <div class="text-muted small text-truncate" style="max-width: 280px;">
                                            {{ $story->quote ?? 'No quote added' }}
                                        </div>
                                    </td>
                                    <td>
                                        <a href="{{ $story->youtube_url }}" target="_blank" class="d-inline-flex align-items-center gap-1.5 text-danger text-decoration-none small fw-semibold">
                                            <i class="bi bi-youtube fs-6"></i>
                                            <span>{{ $story->youtube_video_id }}</span>
                                        </a>
                                        <div>
                                            <span class="badge {{ $story->has_custom_cover ? 'bg-primary-subtle text-primary' : 'bg-light text-muted border' }}" style="font-size: 0.65rem;">
                                                {{ $story->has_custom_cover ? 'Custom Portrait' : 'Auto YouTube' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-2 py-1">#{{ $story->sort_order }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $story->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} rounded-pill px-2.5 py-1">
                                            {{ $story->is_active ? 'Active' : 'Hidden' }}
                                        </span>
                                    </td>
                                    <td class="pe-3 text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-light border text-danger" title="Play Video" onclick="openVideoModal('{{ $story->youtube_video_id }}', '{{ addslashes($story->person_name) }}')">
                                                <i class="bi bi-play-fill"></i>
                                            </button>
                                            <button type="button" class="btn btn-light border text-primary" title="Edit" onclick='editStory(@json($story))'>
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form method="POST" action="{{ route('admin.web.success-stories.destroy', $story->id) }}" class="d-inline" onsubmit="return confirm('Delete this success story?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-light border text-danger" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    @endif

</div>

{{-- 8. Add / Edit Success Story Modal --}}
<div class="modal fade" id="storyModal" tabindex="-1" aria-labelledby="storyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" id="storyForm" action="{{ route('admin.web.success-stories.store') }}" enctype="multipart/form-data" class="modal-content rounded-4 border-0 shadow">
            @csrf
            <input type="hidden" name="_method" id="storyMethod" value="POST">
            <input type="hidden" name="id" id="storyId" value="">

            <div class="modal-header border-bottom px-4 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 rounded-3 bg-indigo-subtle text-indigo" style="color: #6366f1;">
                        <i class="bi bi-camera-reels-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="storyModalLabel">Add Homepage Success Story</h5>
                        <div class="text-muted small">Connect YouTube video and cover media for homepage collage</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div class="row g-3">

                    {{-- 1. Person / Member Details --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold small text-dark">Person / Member Name <span class="text-danger">*</span></label>
                        <input type="text" name="person_name" id="inputPersonName" class="form-control rounded-3" placeholder="e.g. Sarah Jenkins" required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold small text-dark">Designation / Role</label>
                        <input type="text" name="designation" id="inputDesignation" class="form-control rounded-3" placeholder="e.g. Founder & Managing Director">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold small text-dark">Company / Organization</label>
                        <input type="text" name="company" id="inputCompany" class="form-control rounded-3" placeholder="e.g. Apex Global Logistics">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold small text-dark">Case Study Title / Metric</label>
                        <input type="text" name="story_title" id="inputStoryTitle" class="form-control rounded-3" placeholder="e.g. 4x Cross-Border Trade Expansion">
                    </div>

                    {{-- 2. YouTube Video Link with Live Extraction & Preview --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-dark">YouTube Video URL <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-danger"><i class="bi bi-youtube"></i></span>
                            <input type="url" name="youtube_url" id="inputYoutubeUrl" class="form-control rounded-end-3" 
                                   placeholder="https://www.youtube.com/watch?v=... or https://youtu.be/..." 
                                   required oninput="handleYoutubeUrlChange(this.value)">
                        </div>
                        <div class="form-text small mt-1">Supports standard YouTube links, Shorts, youtu.be, or video ID.</div>

                        {{-- Live YouTube Preview Box --}}
                        <div id="youtubePreviewBox" class="mt-2.5 p-3 rounded-3 border bg-light d-none">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-success rounded-pill px-2.5 py-1 text-white" style="font-size: 0.7rem;">
                                    <i class="bi bi-check-circle-fill me-1"></i> Valid YouTube Video Detected
                                </span>
                                <span class="text-muted small" id="youtubeIdLabel">ID: -</span>
                            </div>
                            <div class="d-flex gap-3 align-items-center">
                                <img id="youtubeThumbPreview" src="" alt="Thumbnail" class="rounded-3 shadow-xs" style="width: 120px; height: 68px; object-fit: cover;">
                                <div>
                                    <div class="fw-semibold text-dark small" id="youtubeTitleLabel">YouTube Video Configured</div>
                                    <div class="text-muted small mt-0.5">This thumbnail will automatically serve as the card cover unless you upload a custom portrait below.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 3. Cover Media: Custom Portrait Upload (Optional) --}}
                    <div class="col-12">
                        <div class="p-3 rounded-3 border" style="background: #fafafa;">
                            <label class="form-label fw-semibold small text-dark d-flex align-items-center justify-content-between mb-1">
                                <span><i class="bi bi-image me-1 text-indigo"></i> Custom Portrait Photo (Optional)</span>
                                <span class="badge bg-light text-muted border">Best for Collage Layout</span>
                            </label>
                            <p class="text-muted small mb-2 lh-sm">
                                If you leave this blank, the system automatically uses the YouTube thumbnail. Uploading a portrait headshot photo is recommended for the homepage floating cards.
                            </p>
                            <input type="file" name="custom_cover_image" id="inputCustomCover" class="form-control form-control-sm rounded-3" accept="image/png,image/jpeg,image/webp" onchange="previewCustomImage(this)">

                            {{-- Current or Newly Selected Custom Image Preview --}}
                            <div id="customCoverPreviewBox" class="mt-2 d-none align-items-center gap-3 p-2 bg-white rounded-3 border">
                                <img id="customCoverImg" src="" alt="Custom Cover" class="rounded-3 shadow-xs" style="width: 54px; height: 68px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-dark small" id="customCoverLabel">Custom Portrait Photo</div>
                                    <div class="text-muted small">Will be displayed on the homepage card.</div>
                                </div>
                                <div id="removeCoverContainer" class="d-none">
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input" type="checkbox" name="remove_custom_cover" id="checkRemoveCover" value="1">
                                        <label class="form-check-label small text-danger" for="checkRemoveCover">Remove custom cover</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 4. Story Quote / Testimonial --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold small text-dark">Story Quote / Testimonial</label>
                        <textarea name="quote" id="inputQuote" rows="3" class="form-control rounded-3" placeholder="Brief quote or highlight from this success story..."></textarea>
                    </div>

                    {{-- 5. Sort Order & Status --}}
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold small text-dark">Display Order</label>
                        <input type="number" name="sort_order" id="inputSortOrder" class="form-control rounded-3" value="0" min="0">
                        <div class="form-text small">Lower numbers appear first on the homepage.</div>
                    </div>

                    <div class="col-12 col-md-6 d-flex flex-column justify-content-center">
                        <label class="form-label fw-semibold small text-dark">Visibility Status</label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="inputIsActive" value="1" checked>
                            <label class="form-check-label small fw-semibold text-dark" for="inputIsActive">Publish to Website Homepage</label>
                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer border-top px-4 py-3 bg-light rounded-bottom-4">
                <button type="button" class="btn btn-light border rounded-3 px-3 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm" id="btnSaveStory">
                    <i class="bi bi-check-lg me-1"></i> Save Success Story
                </button>
            </div>
        </form>
    </div>
</div>

{{-- 9. Interactive Video Preview Modal --}}
<div class="modal fade" id="videoPlayerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg bg-dark overflow-hidden">
            <div class="modal-header border-0 py-2.5 px-3 bg-dark text-white">
                <h6 class="modal-title fw-bold mb-0 text-white" id="videoModalTitle">
                    <i class="bi bi-youtube text-danger me-2"></i> YouTube Video Preview
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="closeVideoModal()"></button>
            </div>
            <div class="modal-body p-0">
                <div class="ratio ratio-16x9 bg-black">
                    <iframe id="videoPlayerIframe" src="" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // YouTube ID extractor helper
    function extractYouTubeId(url) {
        if (!url) return null;
        var regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
        var match = url.match(regExp);
        if (match && match[2].length === 11) {
            return match[2];
        }
        // Check if raw 11 chars
        if (url.trim().length === 11) {
            return url.trim();
        }
        return null;
    }

    function handleYoutubeUrlChange(url) {
        var videoId = extractYouTubeId(url);
        var previewBox = document.getElementById('youtubePreviewBox');
        var thumbPreview = document.getElementById('youtubeThumbPreview');
        var idLabel = document.getElementById('youtubeIdLabel');

        if (videoId) {
            idLabel.textContent = 'ID: ' + videoId;
            thumbPreview.src = 'https://img.youtube.com/vi/' + videoId + '/hqdefault.jpg';
            previewBox.classList.remove('d-none');
        } else {
            previewBox.classList.add('d-none');
        }
    }

    function previewCustomImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var previewBox = document.getElementById('customCoverPreviewBox');
                var img = document.getElementById('customCoverImg');
                var label = document.getElementById('customCoverLabel');
                img.src = e.target.result;
                label.textContent = 'Newly Selected Photo: ' + input.files[0].name;
                previewBox.classList.remove('d-none');
                previewBox.classList.add('d-flex');
            }
            reader.readAsAsDataURL ? reader.readAsDataURL(input.files[0]) : reader.readAsBinaryString(input.files[0]);
        }
    }

    function resetStoryModal() {
        document.getElementById('storyModalLabel').textContent = 'Add Homepage Success Story';
        document.getElementById('storyForm').action = "{{ route('admin.web.success-stories.store') }}";
        document.getElementById('storyMethod').value = 'POST';
        document.getElementById('storyId').value = '';

        document.getElementById('inputPersonName').value = '';
        document.getElementById('inputDesignation').value = '';
        document.getElementById('inputCompany').value = '';
        document.getElementById('inputStoryTitle').value = '';
        document.getElementById('inputYoutubeUrl').value = '';
        document.getElementById('inputCustomCover').value = '';
        document.getElementById('inputQuote').value = '';
        document.getElementById('inputSortOrder').value = '0';
        document.getElementById('inputIsActive').checked = true;

        document.getElementById('youtubePreviewBox').classList.add('d-none');
        document.getElementById('customCoverPreviewBox').classList.add('d-none');
        document.getElementById('customCoverPreviewBox').classList.remove('d-flex');
        document.getElementById('removeCoverContainer').classList.add('d-none');
        if (document.getElementById('checkRemoveCover')) {
            document.getElementById('checkRemoveCover').checked = false;
        }
    }

    function editStory(story) {
        document.getElementById('storyModalLabel').textContent = 'Edit Success Story: ' + story.person_name;
        document.getElementById('storyForm').action = "/admin/web/success-stories/" + story.id;
        document.getElementById('storyMethod').value = 'PUT';
        document.getElementById('storyId').value = story.id;

        document.getElementById('inputPersonName').value = story.person_name || '';
        document.getElementById('inputDesignation').value = story.designation || '';
        document.getElementById('inputCompany').value = story.company || '';
        document.getElementById('inputStoryTitle').value = story.story_title || '';
        document.getElementById('inputYoutubeUrl').value = story.youtube_url || '';
        document.getElementById('inputQuote').value = story.quote || '';
        document.getElementById('inputSortOrder').value = story.sort_order ?? 0;
        document.getElementById('inputIsActive').checked = Boolean(story.is_active);

        // Preview YouTube thumbnail
        if (story.youtube_url) {
            handleYoutubeUrlChange(story.youtube_url);
        }

        // Preview custom cover if exists
        var previewBox = document.getElementById('customCoverPreviewBox');
        var removeContainer = document.getElementById('removeCoverContainer');
        var img = document.getElementById('customCoverImg');
        var label = document.getElementById('customCoverLabel');

        if (story.custom_cover_image) {
            img.src = '/' + story.custom_cover_image;
            label.textContent = 'Current Custom Portrait Cover';
            previewBox.classList.remove('d-none');
            previewBox.classList.add('d-flex');
            removeContainer.classList.remove('d-none');
            if (document.getElementById('checkRemoveCover')) {
                document.getElementById('checkRemoveCover').checked = false;
            }
        } else {
            previewBox.classList.add('d-none');
            previewBox.classList.remove('d-flex');
            removeContainer.classList.add('d-none');
        }

        var modal = new bootstrap.Modal(document.getElementById('storyModal'));
        modal.show();
    }

    function openVideoModal(videoId, title) {
        if (!videoId) return;
        document.getElementById('videoModalTitle').innerHTML = '<i class="bi bi-youtube text-danger me-2"></i> ' + (title || 'Success Story Video');
        document.getElementById('videoPlayerIframe').src = 'https://www.youtube-nocookie.com/embed/' + videoId + '?autoplay=1&rel=0';
        var modal = new bootstrap.Modal(document.getElementById('videoPlayerModal'));
        modal.show();
    }

    function closeVideoModal() {
        document.getElementById('videoPlayerIframe').src = '';
    }

    document.getElementById('videoPlayerModal')?.addEventListener('hidden.bs.modal', function() {
        closeVideoModal();
    });

    function switchView(view) {
        var grid = document.getElementById('viewGridContainer');
        var table = document.getElementById('viewTableContainer');
        var btnGrid = document.getElementById('btnViewGrid');
        var btnTable = document.getElementById('btnViewTable');

        if (view === 'grid') {
            grid?.classList.remove('d-none');
            table?.classList.add('d-none');
            btnGrid?.classList.add('btn-white', 'shadow-xs', 'active');
            btnGrid?.classList.remove('text-muted');
            btnTable?.classList.remove('btn-white', 'shadow-xs', 'active');
            btnTable?.classList.add('text-muted');
        } else {
            grid?.classList.add('d-none');
            table?.classList.remove('d-none');
            btnTable?.classList.add('btn-white', 'shadow-xs', 'active');
            btnTable?.classList.remove('text-muted');
            btnGrid?.classList.remove('btn-white', 'shadow-xs', 'active');
            btnGrid?.classList.add('text-muted');
        }
    }
</script>
@endpush
@endsection
