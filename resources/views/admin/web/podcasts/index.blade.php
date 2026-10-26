@extends('admin.layouts.app')

@section('title', 'Add Podcast & Media - Media Channels')

@section('content')
<div class="container-fluid px-0">

    {{-- Top Header Bar --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(99, 102, 241, 0.12); color: #6366f1; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-mic-fill me-1"></i> Add Media
                </span>
                <span class="text-muted" style="font-size: 0.8rem;">● Add Podcast & Media</span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Podcast & Media Channels — Featured Episodes & Broadcasts</h1>
            <p class="text-muted small mb-0 mt-0.5">Manage YouTube podcast links, Spotify episodes, TV features, and guest discussions for the website media page.</p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-primary btn-sm rounded-3 px-3 py-2 fw-semibold d-flex align-items-center gap-1.5 shadow-sm" data-bs-toggle="modal" data-bs-target="#podcastModal" onclick="resetPodcastModal()">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Add Podcast Episode</span>
            </button>
            <a href="https://peersglobal.com/media#channels" target="_blank" class="btn btn-outline-secondary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs">
                <i class="bi bi-box-arrow-up-right"></i>
                <span class="fw-semibold">Preview Media Channels</span>
            </a>
        </div>
    </div>

    {{-- Flash Alerts --}}
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
    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-xs mb-4" role="alert">
            <strong class="d-block mb-1"><i class="bi bi-exclamation-circle me-1"></i> Form validation errors:</strong>
            <ul class="mb-0 ps-3 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(!empty($missingTable) || !empty($dbError))
        <div class="card border-warning border-2 shadow-xs rounded-4 p-4 bg-warning bg-opacity-10 mb-4">
            <div class="d-flex align-items-start gap-3">
                <div class="p-2.5 rounded-3 bg-warning text-dark shadow-xs">
                    <i class="bi bi-database-exclamation fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <h5 class="fw-bold text-dark mb-1">Database Table Setup Required</h5>
                    <p class="text-secondary small mb-2">
                        @if(!empty($dbError))
                            Error: <code>{{ $dbError }}</code>
                        @else
                            The <code>web_media</code> table was not found or is missing required columns in this database environment.
                        @endif
                    </p>
                    <p class="small text-muted mb-2">Please run the following safe SQL in Adminer to ensure the table and all podcast/media columns exist:</p>
                    <pre class="bg-dark text-white p-3 rounded-3 small user-select-all mb-0 font-monospace" style="max-height: 200px; overflow-y: auto;">
CREATE TABLE IF NOT EXISTS web_media (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(500),
    tag VARCHAR(100) DEFAULT 'MANUFACTURING',
    media_format VARCHAR(100) DEFAULT 'podcast',
    duration VARCHAR(50) DEFAULT '45 mins',
    youtube_url VARCHAR(1000),
    youtube_video_id VARCHAR(50),
    spotify_url VARCHAR(1000),
    apple_podcast_url VARCHAR(1000),
    external_url VARCHAR(1000),
    cover_image VARCHAR(1000),
    guest_name VARCHAR(255),
    guest_title VARCHAR(500),
    guest_avatar VARCHAR(1000),
    show_notes JSONB DEFAULT '[]'::jsonb,
    file_name VARCHAR(255),
    file_path VARCHAR(1000),
    file_type VARCHAR(50) DEFAULT 'video',
    file_size BIGINT DEFAULT 0,
    category VARCHAR(100) DEFAULT 'podcast',
    alt_text VARCHAR(255),
    dimensions VARCHAR(50),
    metadata JSONB DEFAULT '{}'::jsonb,
    sort_order INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE web_media ADD COLUMN IF NOT EXISTS title VARCHAR(255);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS subtitle VARCHAR(500);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS tag VARCHAR(100) DEFAULT 'MANUFACTURING';
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS media_format VARCHAR(100) DEFAULT 'podcast';
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS duration VARCHAR(50) DEFAULT '45 mins';
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS youtube_url VARCHAR(1000);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS youtube_video_id VARCHAR(50);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS spotify_url VARCHAR(1000);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS apple_podcast_url VARCHAR(1000);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS external_url VARCHAR(1000);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS cover_image VARCHAR(1000);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS guest_name VARCHAR(255);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS guest_title VARCHAR(500);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS guest_avatar VARCHAR(1000);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS show_notes JSONB DEFAULT '[]'::jsonb;
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS file_name VARCHAR(255);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS file_path VARCHAR(1000);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS file_type VARCHAR(50) DEFAULT 'video';
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS file_size BIGINT DEFAULT 0;
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT 'podcast';
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS alt_text VARCHAR(255);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS dimensions VARCHAR(50);
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS metadata JSONB DEFAULT '{}'::jsonb;
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0;
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS is_featured BOOLEAN DEFAULT FALSE;
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE web_media ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE web_media ALTER COLUMN title DROP NOT NULL;
ALTER TABLE web_media ALTER COLUMN subtitle DROP NOT NULL;
ALTER TABLE web_media ALTER COLUMN tag DROP NOT NULL;
ALTER TABLE web_media ALTER COLUMN media_format DROP NOT NULL;
ALTER TABLE web_media ALTER COLUMN duration DROP NOT NULL;
ALTER TABLE web_media ALTER COLUMN name DROP NOT NULL;</pre>
                </div>
            </div>
        </div>
    @endif

    {{-- KPI Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.68rem;">TOTAL EPISODES</span>
                    <div class="p-2 rounded-3" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                        <i class="bi bi-mic-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-dark mb-0">{{ $episodes->total() }}</div>
                <div class="text-muted small mt-1">Episodes across all channels</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.68rem;">FEATURED SPOTLIGHT</span>
                    <div class="p-2 rounded-3" style="background: rgba(245, 158, 11, 0.1); color: #d97706;">
                        <i class="bi bi-star-fill"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-dark mb-0">{{ $stats['featured'] }}</div>
                <div class="text-muted small mt-1">Highlighted on channels hero</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.68rem;">YOUTUBE BROADCASTS</span>
                    <div class="p-2 rounded-3" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                        <i class="bi bi-youtube"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-dark mb-0">{{ $stats['youtube'] }}</div>
                <div class="text-muted small mt-1">Video links & streams</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-xs rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.68rem;">SPOTIFY PODCASTS</span>
                    <div class="p-2 rounded-3" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <i class="bi bi-spotify"></i>
                    </div>
                </div>
                <div class="h3 fw-bold text-dark mb-0">{{ $stats['spotify'] }}</div>
                <div class="text-muted small mt-1">Audio streams connected</div>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="card border-0 shadow-xs rounded-4 p-3 bg-white mb-4">
        <form method="GET" action="{{ route('admin.web.podcasts.index') }}" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control bg-light border-start-0" placeholder="Search episode title, guest name, or tag...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="tag" class="form-select form-select-sm bg-light" onchange="this.form.submit()">
                    <option value="all" @selected(request('tag') === 'all' || !request('tag'))>All Industry Tags</option>
                    @foreach($tags as $t)
                        <option value="{{ $t }}" @selected(request('tag') === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="format" class="form-select form-select-sm bg-light" onchange="this.form.submit()">
                    <option value="all" @selected(request('format') === 'all' || !request('format'))>All Media Channels</option>
                    @foreach($formats as $fk => $flabel)
                        <option value="{{ $fk }}" @selected(request('format') === $fk)>{{ $flabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold" title="Filter"><i class="bi bi-funnel-fill"></i></button>
                @if(request()->anyFilled(['search', 'tag', 'format']))
                    <a href="{{ route('admin.web.podcasts.index') }}" class="btn btn-light border btn-sm text-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                @endif
            </div>
        </form>
    </div>

    {{-- Podcast / Media Grid --}}
    @if($episodes->count() > 0)
        <div class="row g-4 mb-4">
            @foreach($episodes as $item)
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card border-0 shadow-xs rounded-4 bg-white h-100 d-flex flex-column justify-content-between overflow-hidden position-relative group-hover transition" style="border: 1px solid rgba(0,0,0,0.06) !important;">
                        
                        {{-- Cover Image Container --}}
                        <div class="position-relative overflow-hidden bg-dark" style="height: 200px;">
                            <img src="{{ $item->cover_image_url }}" alt="{{ $item->title }}" class="w-100 h-100 object-fit-cover" onerror="this.src='/images/industry-panel-leaders.jpg'">

                            {{-- Top Badges --}}
                            <div class="position-absolute top-0 start-0 w-100 p-2.5 d-flex justify-content-between align-items-center" style="background: linear-gradient(180deg, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0) 100%);">
                                <span class="badge rounded-pill px-2.5 py-1 text-uppercase font-monospace" style="background: #e11d48; font-size: 0.68rem; letter-spacing: 0.05em;">
                                    {{ $item->tag }}
                                </span>
                                <span class="badge bg-dark bg-opacity-75 rounded-pill px-2.5 py-1 font-monospace text-white border border-light border-opacity-25" style="font-size: 0.68rem;">
                                    <i class="bi bi-clock me-1"></i> {{ $item->duration ?: '45 mins' }}
                                </span>
                            </div>

                            {{-- Featured Star Badge --}}
                            @if($item->is_featured)
                                <div class="position-absolute bottom-0 start-0 m-2">
                                    <span class="badge bg-warning text-dark rounded-pill px-2.5 py-1 shadow-sm font-monospace fw-bold" style="font-size: 0.68rem;">
                                        <i class="bi bi-star-fill me-1"></i> Featured
                                    </span>
                                </div>
                            @endif

                            {{-- Media Link Buttons Overlay --}}
                            <div class="position-absolute bottom-0 end-0 m-2 d-flex gap-1.5">
                                @if($item->youtube_url)
                                    <a href="{{ $item->youtube_url }}" target="_blank" class="btn btn-sm btn-danger rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; padding: 0;" title="Watch on YouTube">
                                        <i class="bi bi-youtube"></i>
                                    </a>
                                @endif
                                @if($item->spotify_url)
                                    <a href="{{ $item->spotify_url }}" target="_blank" class="btn btn-sm btn-success rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; padding: 0;" title="Listen on Spotify">
                                        <i class="bi bi-spotify"></i>
                                    </a>
                                @endif
                                @if($item->external_url)
                                    <a href="{{ $item->external_url }}" target="_blank" class="btn btn-sm btn-light rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; padding: 0;" title="External Article / Link">
                                        <i class="bi bi-link-45deg"></i>
                                    </a>
                                @endif
                            </div>
                        </div>

                        {{-- Details Body --}}
                        <div class="p-3.5 d-flex flex-column flex-grow-1 justify-content-between">
                            <div>
                                <h3 class="h6 fw-bold text-dark mb-1 line-clamp-2" title="{{ $item->title }}">{{ $item->title }}</h3>

                                @if($item->subtitle)
                                    <p class="text-muted small mb-3 line-clamp-2" style="font-size: 0.8rem; line-height: 1.4;" title="{{ $item->subtitle }}">{{ $item->subtitle }}</p>
                                @endif

                                {{-- Guest Meta --}}
                                @if($item->guest_name)
                                    <div class="d-flex align-items-center gap-2 p-2 rounded-3 bg-light mb-3">
                                        <img src="{{ $item->guest_avatar_url }}" alt="{{ $item->guest_name }}" class="rounded-circle object-fit-cover border" style="width: 34px; height: 34px;" onerror="this.src='/images/avatar-placeholder.png'">
                                        <div class="overflow-hidden">
                                            <div class="fw-bold text-dark text-truncate" style="font-size: 0.78rem;">{{ $item->guest_name }}</div>
                                            <div class="text-muted text-truncate" style="font-size: 0.72rem;">{{ $item->guest_title ?: 'Featured Guest' }}</div>
                                        </div>
                                    </div>
                                @endif

                                {{-- Show Notes Preview --}}
                                @if(!empty($item->show_notes) && count($item->show_notes) > 0)
                                    <div class="mb-3">
                                        <span class="text-uppercase tracking-wider text-muted fw-bold font-monospace" style="font-size: 0.65rem;">DISCUSSION NOTES ({{ count($item->show_notes) }})</span>
                                        <ul class="list-unstyled mb-0 ps-0 mt-1">
                                            @foreach(array_slice($item->show_notes, 0, 2) as $note)
                                                <li class="small text-muted mb-1 text-truncate" style="font-size: 0.75rem;">
                                                    <i class="bi bi-check2 text-primary me-1"></i> {{ $note }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>

                            {{-- Footer Controls --}}
                            <div class="d-flex align-items-center justify-content-between pt-2.5 border-top">
                                <span class="badge rounded-pill bg-light text-secondary border font-monospace text-uppercase" style="font-size: 0.68rem;">
                                    {{ str_replace('_', ' ', $item->media_format ?? 'podcast') }}
                                </span>

                                <div class="d-flex align-items-center gap-1.5">
                                    {{-- Toggle Featured --}}
                                    <form method="POST" action="{{ route('admin.web.podcasts.toggle-featured', $item->id) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm {{ $item->is_featured ? 'btn-warning text-dark' : 'btn-outline-secondary' }} rounded-3 px-2 py-1" style="font-size: 0.75rem;" title="{{ $item->is_featured ? 'Remove from Featured' : 'Pin as Featured' }}">
                                            <i class="bi bi-star{{ $item->is_featured ? '-fill' : '' }}"></i>
                                        </button>
                                    </form>

                                    {{-- Toggle Status --}}
                                    <form method="POST" action="{{ route('admin.web.podcasts.toggle-status', $item->id) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm {{ $item->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }} rounded-3 px-2 py-1" style="font-size: 0.75rem;" title="{{ $item->is_active ? 'Published (Click to Draft)' : 'Draft (Click to Publish)' }}">
                                            <i class="bi bi-{{ $item->is_active ? 'toggle-on' : 'toggle-off' }}"></i>
                                        </button>
                                    </form>

                                    {{-- Edit Button --}}
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-3 px-2 py-1" style="font-size: 0.75rem;" onclick='openEditPodcastModal(@json($item))' title="Edit Episode">
                                        <i class="bi bi-pencil-fill"></i> Edit
                                    </button>

                                    {{-- Delete Button --}}
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-3 px-2 py-1" style="font-size: 0.75rem;" onclick="confirmDeletePodcast('{{ $item->id }}', '{{ addslashes($item->title) }}')" title="Delete Episode">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="d-flex justify-content-center mt-3">
            {{ $episodes->withQueryString()->links() }}
        </div>
    @else
        <div class="card border-0 shadow-xs rounded-4 p-5 text-center bg-white">
            <div class="mb-3 text-muted" style="font-size: 3rem;">
                <i class="bi bi-mic"></i>
            </div>
            <h4 class="h5 fw-bold text-dark">No podcast episodes or media found</h4>
            <p class="text-muted small mb-4">No content matches your filter or no episodes have been uploaded yet.</p>
            <div class="d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-primary btn-sm rounded-3 px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#podcastModal" onclick="resetPodcastModal()">
                    <i class="bi bi-plus-circle-fill me-1"></i> Add First Episode
                </button>
                @if(request()->anyFilled(['search', 'tag', 'format']))
                    <a href="{{ route('admin.web.podcasts.index') }}" class="btn btn-light border btn-sm rounded-3 px-3 py-2">Reset Filters</a>
                @endif
            </div>
        </div>
    @endif

</div>

{{-- Add / Edit Podcast Modal --}}
<div class="modal fade" id="podcastModal" tabindex="-1" aria-labelledby="podcastModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form id="podcastForm" method="POST" action="{{ route('admin.web.podcasts.store') }}" enctype="multipart/form-data" novalidate>
                @csrf
                <div id="podcastMethodSpoof"></div>

                <div class="modal-header border-bottom p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 rounded-3 bg-primary text-white shadow-xs">
                            <i class="bi bi-mic-fill"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="podcastModalLabel">Add Podcast Episode & Media</h5>
                            <p class="text-muted small mb-0">Publish podcast links, YouTube broadcasts, and featured interviews</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        {{-- Title --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark small mb-1">Episode / Feature Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="podcast_title" class="form-control rounded-3" placeholder="e.g. From Near Bankruptcy to 120-Person Precision Engineering" required>
                        </div>

                        {{-- Subtitle --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark small mb-1">Subtitle / Tagline</label>
                            <input type="text" name="subtitle" id="podcast_subtitle" class="form-control rounded-3" placeholder="e.g. Building through family resistance and industrial downturns.">
                        </div>

                        {{-- Duration & Sort Order --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-1">Duration</label>
                            <input type="text" name="duration" id="podcast_duration" class="form-control rounded-3" placeholder="e.g. 48 mins">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-1">Sort Order</label>
                            <input type="number" name="sort_order" id="podcast_sort_order" class="form-control rounded-3" value="0" min="0">
                        </div>

                        {{-- YouTube URL & External Article Link --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-1">
                                <i class="bi bi-youtube text-danger me-1"></i> YouTube Video Link
                            </label>
                            <input type="text" name="youtube_url" id="podcast_youtube_url" class="form-control rounded-3" placeholder="https://www.youtube.com/watch?v=... or youtu.be/...">
                            <div class="form-text small">Thumbnail will be generated automatically if no custom cover is uploaded.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-1">
                                <i class="bi bi-link-45deg me-1"></i> External Article / Press Link
                            </label>
                            <input type="text" name="external_url" id="podcast_external_url" class="form-control rounded-3" placeholder="https://vyapaarjagat.com/... or press article URL">
                        </div>

                        {{-- Custom Cover Image File Upload --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark small mb-1">Cover Image Upload (JPG, PNG, WebP)</label>
                            <input type="file" name="cover_file" id="podcast_cover_file" class="form-control rounded-3" accept="image/*">
                            <div class="form-text small">Leave empty to use high-res YouTube thumbnail.</div>
                        </div>

                        {{-- Guest Information --}}
                        <div class="col-12">
                            <h6 class="fw-bold text-dark pt-2 border-top small mb-2"><i class="bi bi-person-badge me-1 text-primary"></i> Guest / Leader Information</h6>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold text-dark small mb-1">Guest Name</label>
                            <input type="text" name="guest_name" id="podcast_guest_name" class="form-control rounded-3" placeholder="e.g. Rajesh Shah">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold text-dark small mb-1">Guest Title & Company</label>
                            <input type="text" name="guest_title" id="podcast_guest_title" class="form-control rounded-3" placeholder="e.g. Founder, Apex Precision Engineering, Ahmedabad">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold text-dark small mb-1">Guest Avatar Photo</label>
                            <input type="file" name="guest_avatar_file" id="podcast_guest_avatar_file" class="form-control rounded-3" accept="image/*">
                        </div>

                        {{-- Show Notes (Discussion bullet points) --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold text-dark small mb-1">Show Notes / Key Takeaways (One per line)</label>
                            <textarea name="show_notes" id="podcast_show_notes" rows="3" class="form-control rounded-3 font-monospace small" placeholder="How raw materials inflation in 2021 pushed their primary plant to 14 days of remaining liquidity.&#10;Why peer accountability inside Gujarat Manufacturing Circle gave them clarity.&#10;Transitioning from owner-dependent shopfloor to professionalized second line."></textarea>
                            <div class="form-text small">Each line entered here will appear as a bullet point discussion note on the website episode card.</div>
                        </div>

                        {{-- Switches --}}
                        <div class="col-12 col-md-6">
                            <div class="form-check form-switch pt-2">
                                <input class="form-check-input" type="checkbox" name="is_featured" id="podcast_is_featured" value="1">
                                <label class="form-check-label fw-semibold text-dark small" for="podcast_is_featured">
                                    <i class="bi bi-star-fill text-warning me-1"></i> Feature on homepage & channels spotlight
                                </label>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="form-check form-switch pt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="podcast_is_active" value="1" checked>
                                <label class="form-check-label fw-semibold text-dark small" for="podcast_is_active">
                                    <i class="bi bi-check-circle-fill text-success me-1"></i> Publish live on website channels
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top p-3 bg-light bg-opacity-50">
                    <button type="button" class="btn btn-light rounded-3 px-3 py-2 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="podcastForm" class="btn btn-primary rounded-3 px-4 py-2 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm" id="podcastSubmitBtn">
                        <i class="bi bi-cloud-arrow-up-fill" id="podcastSubmitBtnIcon"></i>
                        <span id="podcastSubmitBtnText">Save Episode & Publish</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div class="modal fade" id="deletePodcastModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4 text-center p-4">
            <div class="mb-3 text-danger" style="font-size: 2.5rem;">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">Delete Episode?</h5>
            <p class="text-muted small mb-3">Are you sure you want to delete <span class="fw-semibold text-dark" id="deletePodcastTitle">this item</span>? This action cannot be undone.</p>
            
            <form id="deletePodcastForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-light rounded-3 px-3 py-1.5" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-3 px-3 py-1.5 fw-semibold">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function resetPodcastModal() {
        const form = document.getElementById('podcastForm');
        form.reset();
        form.action = "{{ route('admin.web.podcasts.store') }}";
        document.getElementById('podcastMethodSpoof').innerHTML = '';
        document.getElementById('podcastModalLabel').innerText = 'Add Podcast Episode & Media';
        
        const submitBtn = document.getElementById('podcastSubmitBtn');
        const submitBtnText = document.getElementById('podcastSubmitBtnText');
        const submitBtnIcon = document.getElementById('podcastSubmitBtnIcon');
        if (submitBtn) submitBtn.disabled = false;
        if (submitBtnIcon) submitBtnIcon.classList.remove('d-none');
        if (submitBtnText) submitBtnText.innerText = 'Save Episode & Publish';

        document.getElementById('podcast_is_active').checked = true;
        document.getElementById('podcast_is_featured').checked = false;
        document.getElementById('podcast_sort_order').value = 0;
    }

    function openEditPodcastModal(item) {
        resetPodcastModal();
        const form = document.getElementById('podcastForm');
        form.action = "/admin/web/podcasts/" + item.id;
        document.getElementById('podcastMethodSpoof').innerHTML = '<input type="hidden" name="_method" value="PUT">';
        document.getElementById('podcastModalLabel').innerText = 'Edit Podcast Episode & Media';
        
        const submitBtn = document.getElementById('podcastSubmitBtn');
        const submitBtnText = document.getElementById('podcastSubmitBtnText');
        if (submitBtn) submitBtn.disabled = false;
        if (submitBtnText) submitBtnText.innerText = 'Update Episode';

        document.getElementById('podcast_title').value = item.title || '';
        document.getElementById('podcast_subtitle').value = item.subtitle || '';
        document.getElementById('podcast_duration').value = item.duration || '48 mins';
        document.getElementById('podcast_youtube_url').value = item.youtube_url || '';
        document.getElementById('podcast_external_url').value = item.external_url || '';
        document.getElementById('podcast_guest_name').value = item.guest_name || '';
        document.getElementById('podcast_guest_title').value = item.guest_title || '';
        document.getElementById('podcast_sort_order').value = item.sort_order || 0;
        document.getElementById('podcast_is_featured').checked = item.is_featured ? true : false;
        document.getElementById('podcast_is_active').checked = item.is_active ? true : false;

        if (Array.isArray(item.show_notes)) {
            document.getElementById('podcast_show_notes').value = item.show_notes.join('\n');
        } else {
            document.getElementById('podcast_show_notes').value = '';
        }

        const modal = new bootstrap.Modal(document.getElementById('podcastModal'));
        modal.show();
    }

    function confirmDeletePodcast(id, title) {
        document.getElementById('deletePodcastForm').action = "/admin/web/podcasts/" + id;
        document.getElementById('deletePodcastTitle').innerText = '"' + title + '"';
        const modal = new bootstrap.Modal(document.getElementById('deletePodcastModal'));
        modal.show();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('podcastForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                const titleInput = document.getElementById('podcast_title');
                if (!titleInput || !titleInput.value.trim()) {
                    e.preventDefault();
                    e.stopPropagation();
                    alert('Please enter an Episode / Feature Title.');
                    if (titleInput) {
                        titleInput.focus();
                        titleInput.classList.add('is-invalid');
                    }
                    return false;
                }

                if (titleInput) {
                    titleInput.classList.remove('is-invalid');
                }

                const submitBtn = document.getElementById('podcastSubmitBtn');
                const submitBtnText = document.getElementById('podcastSubmitBtnText');
                const submitBtnIcon = document.getElementById('podcastSubmitBtnIcon');
                if (submitBtnIcon) submitBtnIcon.classList.add('d-none');
                if (submitBtn) submitBtn.disabled = true;
                if (submitBtnText) {
                    submitBtnText.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving & Publishing...';
                }

                return true;
            });
        }
    });
</script>
@endpush
@endsection
