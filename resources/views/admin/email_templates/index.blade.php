@extends('admin.layouts.app')

@section('title', 'All Available Email Lists')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark">All Available Email Lists</h4>
            <p class="text-muted small mb-0">Browse, search, preview, and edit all system email templates in real-time.</p>
        </div>
        <div>
            <span class="badge bg-primary px-3 py-2 fs-6 rounded-pill" style="background-color: #240e5c !important;">
                {{ count($templates) }} Templates Configured
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Search & Filter Controls -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-6 col-lg-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="templateSearch" class="form-control bg-light border-0" placeholder="Search templates by name or key..." onkeyup="filterTemplates()">
                    </div>
                </div>
                <div class="col-md-6 col-lg-8 text-md-end text-muted small">
                    Showing <span id="visibleCount" class="fw-bold text-dark">{{ count($templates) }}</span> of {{ count($templates) }} templates
                </div>
            </div>
        </div>
    </div>

    <!-- Cards Grid -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4" id="templatesGrid">
        @foreach($templates as $tpl)
            <div class="col template-card-item" data-name="{{ strtolower($tpl['name']) }}" data-key="{{ strtolower($tpl['key']) }}">
                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative hover-shadow transition">
                    <!-- Accent color bar matching sidebar purple -->
                    <div style="height: 4px; background: linear-gradient(90deg, #240e5c, #6366f1);"></div>
                    
                    <div class="card-body p-4 d-flex flex-column">
                        <!-- Header: Icon & Name -->
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div class="rounded-4 d-inline-flex align-items-center justify-content-center flex-shrink-0" 
                                 style="background-color: rgba(36, 14, 92, 0.08); width: 48px; height: 48px;">
                                <i class="{{ $tpl['icon'] }} fs-4" style="color: #240e5c;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <h6 class="mb-1 fw-bold text-dark text-truncate" title="{{ $tpl['name'] }}" style="font-size: 15px;">
                                    {{ $tpl['name'] }}
                                </h6>
                                <span class="badge bg-light text-secondary border rounded-pill font-monospace" style="font-size: 9.5px; padding: 2px 8px;">
                                    {{ $tpl['key'] }}
                                </span>
                            </div>
                        </div>

                        <!-- Description -->
                        <p class="card-text text-muted mb-3 flex-grow-1" style="font-size: 13px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; height: 58.5px;">
                            {{ $tpl['description'] }}
                        </p>

                        <!-- Dynamic Params Badges -->
                        <div class="mb-4">
                            <div class="d-flex flex-wrap gap-1" style="max-height: 52px; overflow: hidden;">
                                @forelse($tpl['dynamic_params'] as $param => $desc)
                                    <span class="badge bg-white text-dark border font-monospace" style="font-size: 10.5px; padding: 3px 6px;" title="{{ $desc }}">
                                        {{ $param }}
                                    </span>
                                @empty
                                    <span class="text-muted italic small" style="font-size: 12px;">No dynamic parameters</span>
                                @endforelse
                            </div>
                        </div>

                        <!-- Footer: Info & Action Buttons -->
                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                            <div class="text-muted small d-inline-flex align-items-center gap-1" style="font-size: 11px;">
                                <i class="bi bi-clock"></i>
                                <span>{{ substr($tpl['last_modified'], 0, 10) }}</span>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('admin.email-templates.preview', $tpl['key']) }}" target="_blank" 
                                   class="btn btn-xs btn-outline-secondary d-inline-flex align-items-center gap-1 rounded-pill px-3 py-1" style="font-size: 12px;">
                                    <i class="bi bi-eye"></i> Preview
                                </a>
                                <a href="{{ route('admin.email-templates.edit', $tpl['key']) }}" 
                                   class="btn btn-xs btn-primary d-inline-flex align-items-center gap-1 rounded-pill px-3 py-1"
                                   style="background-color: #240e5c; border-color: #240e5c; font-size: 12px;">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
    function filterTemplates() {
        const query = document.getElementById('templateSearch').value.toLowerCase().trim();
        const cards = document.getElementsByClassName('template-card-item');
        let visibleCount = 0;

        for (let i = 0; i < cards.length; i++) {
            const card = cards[i];
            const name = card.getAttribute('data-name');
            const key = card.getAttribute('data-key');

            if (name.includes(query) || key.includes(query)) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        }
        document.getElementById('visibleCount').innerText = visibleCount;
    }
</script>

<style>
    .hover-shadow {
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .hover-shadow:hover {
        transform: translateY(-4px);
        box-shadow: 0 0.75rem 1.75rem rgba(36, 14, 92, 0.08) !important;
    }
    .transition {
        transition: all 0.25s ease;
    }
</style>
@endsection
