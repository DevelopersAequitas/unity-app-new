@extends('admin.layouts.app')

@section('title', 'Media Library - Peers Global Web')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-indigo-subtle text-indigo rounded-pill px-2.5 py-1" style="background: rgba(6, 182, 212, 0.12); color: #06b6d4; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-images me-1"></i> Peers Global Website
                </span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Website Media Library</h1>
            <p class="text-muted small mb-0 mt-0.5">High definition 4K videos, ambient backdrops, brand vectors, and hero banners</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs fw-semibold" onclick="alert('Upload functionality linked to media storage.');">
                <i class="bi bi-cloud-arrow-up-fill"></i>
                <span>Upload Media</span>
            </button>
        </div>
    </div>

    {{-- Grid of Media Assets --}}
    <div class="row g-4 mb-4">
        @foreach($mediaAssets as $media)
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card border-0 shadow-xs rounded-4 p-4 bg-white h-100 d-flex flex-column justify-content-between hover-shadow transition">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="badge rounded-pill px-2.5 py-1" style="background: {{ $media['type'] === 'video' ? 'rgba(99, 102, 241, 0.12)' : 'rgba(16, 185, 129, 0.12)' }}; color: {{ $media['type'] === 'video' ? '#6366f1' : '#10b981' }}; font-weight: 600;">
                                <i class="bi bi-{{ $media['type'] === 'video' ? 'film' : 'image' }} me-1"></i> {{ strtoupper($media['type']) }}
                            </span>
                            <span class="text-muted small font-monospace">{{ $media['size'] }}</span>
                        </div>

                        <div class="rounded-3 p-4 text-center mb-3 bg-light d-flex align-items-center justify-content-center" style="height: 140px; border: 1px dashed #cbd5e1;">
                            <i class="bi bi-{{ $media['type'] === 'video' ? 'play-circle-fill' : 'file-earmark-image' }} text-secondary" style="font-size: 3rem; opacity: 0.7;"></i>
                        </div>

                        <h3 class="h6 fw-bold text-dark mb-1 line-clamp-1">{{ $media['title'] }}</h3>
                        <p class="text-muted font-monospace small mb-3" style="font-size: 0.75rem;">{{ $media['file_name'] }}</p>
                    </div>

                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        <span class="text-muted small">{{ $media['updated_at'] }}</span>
                        <a href="{{ $media['url'] }}" target="_blank" class="btn btn-sm btn-light border fw-semibold">
                            <i class="bi bi-eye me-1"></i> View Asset
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
