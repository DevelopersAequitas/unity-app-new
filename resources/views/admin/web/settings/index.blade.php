@extends('admin.layouts.app')

@section('title', 'Website Settings - Peers Global Web')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-indigo-subtle text-indigo rounded-pill px-2.5 py-1" style="background: rgba(99, 102, 241, 0.12); color: #6366f1; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-gear me-1"></i> Peers Global Website
                </span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Website Settings & Configuration</h1>
            <p class="text-muted small mb-0 mt-0.5">Control global branding, contact channels, and SEO parameters</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card border-0 shadow-xs rounded-4 p-4 bg-white">
        <form method="POST" action="{{ route('admin.web.settings.update') }}">
            @csrf
            <div class="row g-4">
                @foreach($settings as $group => $items)
                    <div class="col-12">
                        <h2 class="h6 fw-bold text-dark text-uppercase tracking-wider border-bottom pb-2 mb-3">
                            {{ ucfirst($group) }} Configuration
                        </h2>
                        <div class="row g-3">
                            @foreach($items as $setting)
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold text-dark">{{ ucwords(str_replace('_', ' ', $setting->key)) }}</label>
                                    <input type="text" name="{{ $setting->key }}" value="{{ $setting->value }}" class="form-control form-control-sm">
                                    @if($setting->description)
                                        <small class="text-muted" style="font-size: 0.72rem;">{{ $setting->description }}</small>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="pt-4 mt-4 border-top text-end">
                <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-xs">
                    Save Website Settings
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
