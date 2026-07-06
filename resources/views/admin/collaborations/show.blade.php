@extends('admin.layouts.app')

@section('title', 'Collaboration Details')

@section('content')
@php 
    use App\Support\CollaborationFormatter;
    $getInitials = function($name) {
        $words = explode(' ', trim($name));
        $initials = '';
        foreach ($words as $w) {
            if(!empty($w)) $initials .= strtoupper(substr($w, 0, 1));
        }
        return substr($initials, 0, 2) ?: 'P';
    };
    $getAvatarBg = function($name) {
        $colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
        $hash = crc32($name);
        return $colors[abs($hash) % count($colors)];
    };

    $user = $post->user;
    $name = $user?->name ?: $user?->display_name ?: trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? ''));
    $name = $name !== '' ? $name : 'Unnamed Peer';
    $userCompany = $user?->company_name ?? $user?->company ?? $user?->business_name ?? null;
    $userCity = $user?->city ?? $user?->current_city ?? null;
    $postCity = $post->city ?? null;
    $displayCity = $postCity ?: $userCity;
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1 class="h3 mb-1 fw-bold text-dark" style="font-family: 'Outfit', sans-serif;">Collaboration Details</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted">Admin</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.collaborations.index') }}" class="text-decoration-none text-muted">Collaborations</a></li>
                <li class="breadcrumb-item active text-primary fw-medium" aria-current="page">Details</li>
            </ol>
        </nav>
    </div>
    <div>
        <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Collaborations
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Peer Profile Info -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm h-100" style="border-radius: var(--radius-md);">
            <div class="card-header bg-white border-bottom py-3">
                <span class="fw-bold text-dark"><i class="bi bi-person-badge text-primary me-2"></i>Peer Information</span>
            </div>
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="peer-badge-avatar" style="width: 54px; height: 54px; font-size: 1.2rem; background-color: {{ $getAvatarBg($name) }}">
                        {{ $getInitials($name) }}
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-1">{{ $name }}</h5>
                        @if($userCompany)
                            <div class="text-muted small"><i class="bi bi-building me-1"></i>{{ $userCompany }}</div>
                        @endif
                    </div>
                </div>

                <div class="d-flex flex-column gap-3">
                    <div class="d-flex border-bottom pb-2">
                        <span class="text-muted small w-30">Email</span>
                        <span class="small fw-semibold text-dark">{{ $user?->email ?? '—' }}</span>
                    </div>
                    <div class="d-flex border-bottom pb-2">
                        <span class="text-muted small w-30">Phone</span>
                        <span class="small fw-semibold text-dark">{{ $user?->phone ?? '—' }}</span>
                    </div>
                    <div class="d-flex">
                        <span class="text-muted small w-30">City</span>
                        <span class="small fw-semibold text-dark">{{ $userCity ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Post Info -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm h-100" style="border-radius: var(--radius-md);">
            <div class="card-header bg-white border-bottom py-3">
                <span class="fw-bold text-dark"><i class="bi bi-file-earmark-post text-primary me-2"></i>Post Details</span>
            </div>
            <div class="card-body p-4">
                <h4 class="fw-bold text-dark mb-2">{{ $post->title ?? '—' }}</h4>
                <div class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 mb-4">
                    {{ $post->collaborationType?->name ?? CollaborationFormatter::humanize($post->collaboration_type) }}
                </div>

                <div class="mb-4 bg-light p-3 rounded border">
                    <div class="text-muted small fw-semibold mb-1">DESCRIPTION</div>
                    <p class="small text-secondary mb-0 text-wrap text-break">{{ $post->description ?? '—' }}</p>
                </div>

                <div class="row g-3">
                    <div class="col-6 col-sm-4">
                        <div class="text-muted small">Scope</div>
                        <div class="fw-semibold text-dark small mt-1">{{ CollaborationFormatter::humanize($post->scope ?? $post->collaboration_scope ?? $post->scope_text) }}</div>
                    </div>
                    <div class="col-6 col-sm-4">
                        <div class="text-muted small">Preferred Mode</div>
                        <div class="fw-semibold text-dark small mt-1">{{ CollaborationFormatter::humanize($post->preferred_mode ?? $post->preferred_model ?? $post->meeting_mode ?? $post->mode) }}</div>
                    </div>
                    <div class="col-6 col-sm-4">
                        <div class="text-muted small">Business Stage</div>
                        <div class="fw-semibold text-dark small mt-1">{{ CollaborationFormatter::humanize($post->business_stage ?? $post->stage ?? $post->business_stage_text) }}</div>
                    </div>
                    <div class="col-6 col-sm-4">
                        <div class="text-muted small">Years in Operation</div>
                        <div class="fw-semibold text-dark small mt-1">{{ CollaborationFormatter::humanize($post->year_in_operation ?? $post->years_in_operation ?? $post->operating_years ?? $post->years) }}</div>
                    </div>
                    <div class="col-6 col-sm-4">
                        <div class="text-muted small">Post City</div>
                        <div class="fw-semibold text-dark small mt-1">{{ $displayCity ?? '—' }}</div>
                    </div>
                    <div class="col-6 col-sm-4">
                        <div class="text-muted small">Status</div>
                        <div class="mt-1">
                            <span class="badge {{ strtolower((string)($post->status ?? '')) === 'active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} px-2 py-1">
                                {{ CollaborationFormatter::humanize((string) ($post->status ?? '—')) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                    <span class="small text-muted">Created: {{ $post->created_at?->format('Y-m-d H:i') ?? '—' }}</span>
                    <span class="small text-muted">Post UUID: <code class="small">{{ $post->id }}</code></span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
