@extends('admin.layouts.app')

@section('title', 'Tutorials Management')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><i class="bi bi-play-btn me-2 text-primary"></i>Tutorials Management</h1>
    <span class="badge bg-light text-dark border">Total Videos: {{ $tutorials->count() }}</span>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        @foreach($errors->all() as $error)
            {{ $error }}
        @endforeach
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-4">
    <!-- Left/Top: Add Tutorial Video Form -->
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0"><i class="bi bi-plus-circle me-2 text-secondary"></i>Add YouTube Video Tutorial</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.tutorials.store') }}" method="POST">
                    @csrf
                    <div class="row g-3 align-items-center">
                        <div class="col-md-9 col-lg-10">
                            <label for="youtube_url" class="visually-hidden">YouTube URL</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-youtube text-danger"></i></span>
                                <input type="url" 
                                       name="youtube_url" 
                                       id="youtube_url" 
                                       class="form-control @error('youtube_url') is-invalid @enderror" 
                                       placeholder="Paste YouTube Video URL (e.g. https://www.youtube.com/watch?v=dQw4w9WgXcQ or shorts/ZazxlEXKXKw)" 
                                       value="{{ old('youtube_url') }}" 
                                       required>
                            </div>
                        </div>
                        <div class="col-md-3 col-lg-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-lg me-1"></i>Add Video
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bottom: Tutorials Grid -->
    <div class="col-12">
        @if($tutorials->isEmpty())
            <div class="card shadow-sm border-0 py-5 text-center">
                <div class="card-body">
                    <i class="bi bi-play-btn text-muted" style="font-size: 4rem;"></i>
                    <h5 class="mt-3 text-secondary">No Video Tutorials Added Yet</h5>
                    <p class="text-muted">Submit a YouTube URL above to show help videos to users in the mobile app.</p>
                </div>
            </div>
        @else
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                @foreach($tutorials as $tutorial)
                    <div class="col">
                        <div class="card h-100 shadow-sm border-0 overflow-hidden">
                            <!-- Premium YouTube Player Thumbnail Container -->
                            <div class="position-relative ratio ratio-16x9 bg-dark">
                                <img src="https://img.youtube.com/vi/{{ $tutorial->video_id }}/mqdefault.jpg" 
                                     class="card-img-top" 
                                     alt="YouTube Thumbnail" 
                                     style="object-fit: cover; opacity: 0.95;">
                                <a href="{{ $tutorial->youtube_url }}" 
                                   target="_blank" 
                                   class="position-absolute top-50 start-50 translate-middle btn btn-lg btn-danger rounded-circle shadow opacity-90 d-flex align-items-center justify-content-center" 
                                   style="width: 50px; height: 50px;" 
                                   title="Watch Video">
                                    <i class="bi bi-play-fill text-white fs-4" style="margin-left: 2px;"></i>
                                </a>
                            </div>
                            
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div class="mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="badge bg-secondary font-monospace">{{ $tutorial->video_id }}</span>
                                        <small class="text-muted">{{ $tutorial->created_at->format('M d, Y') }}</small>
                                    </div>
                                    <p class="card-text text-truncate small mb-0">
                                        <a href="{{ $tutorial->youtube_url }}" target="_blank" class="text-decoration-none text-dark fw-semibold">
                                            {{ $tutorial->youtube_url }}
                                        </a>
                                    </p>
                                </div>
                                <div>
                                    <form action="{{ route('admin.tutorials.destroy', $tutorial->id) }}" 
                                          method="POST" 
                                          onsubmit="return confirm('Are you sure you want to remove this tutorial video?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger w-100 btn-sm">
                                            <i class="bi bi-trash me-1"></i>Remove Video
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
