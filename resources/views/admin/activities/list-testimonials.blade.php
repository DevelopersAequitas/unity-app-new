@extends('admin.layouts.app')

@section('title', 'Testimonials')

@section('content')
    @php
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

        $resolveFileUrl = function ($value) use ($validMediaIds) {
            if (! $value) {
                return null;
            }

            if (is_string($value) && (str_starts_with($value, 'http://') || str_starts_with($value, 'https://'))) {
                return $value;
            }

            if (is_string($value) && \Illuminate\Support\Str::isUuid($value)) {
                if (in_array($value, $validMediaIds ?? [], true)) {
                    return url('/api/v1/files/' . $value);
                }
            }

            return null;
        };

        $extractMediaUrl = function ($media) use ($resolveFileUrl) {
            if (! $media) {
                return null;
            }

            if (is_array($media)) {
                $first = $media[0] ?? null;
                if (is_array($first)) {
                    $id = $first['id'] ?? null;
                    $url = $first['url'] ?? null;
                    return $resolveFileUrl($url ?: $id);
                }
            }

            return $resolveFileUrl($media);
        };

        $peerName = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')) ?: $member->display_name ?: 'Unnamed Peer';
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark" style="font-family: 'Outfit', sans-serif;">Testimonials Log</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted">Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.activities.index') }}" class="text-decoration-none text-muted">Activities Summary</a></li>
                    <li class="breadcrumb-item active text-primary fw-medium" aria-current="page">Testimonials of {{ $peerName }}</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('admin.activities.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Activities
        </a>
    </div>

    <!-- Member Info Card -->
    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: var(--radius-md);">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="peer-badge-avatar" style="width: 60px; height: 60px; font-size: 1.3rem; background-color: {{ $getAvatarBg($peerName) }}">
                {{ $getInitials($peerName) }}
            </div>
            <div>
                <h4 class="fw-bold text-dark mb-1">{{ $peerName }}</h4>
                <div class="text-muted small">
                    <span class="me-3"><i class="bi bi-envelope me-1"></i>{{ $member->email ?? '—' }}</span>
                    <span><i class="bi bi-telephone me-1"></i>{{ $member->phone ?? '—' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Logs Card -->
    <div class="card-activities-wrapper">
        <div class="border-bottom p-3 bg-light">
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
                <div class="input-group input-group-sm" style="width: 180px;">
                    <span class="input-group-text bg-white"><i class="bi bi-calendar"></i></span>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control" placeholder="From">
                </div>
                <div class="input-group input-group-sm" style="width: 180px;">
                    <span class="input-group-text bg-white"><i class="bi bi-calendar"></i></span>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control" placeholder="To">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary px-3">Apply</button>
                    <a href="{{ route('admin.activities.testimonials', $member) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-premium align-middle mb-0">
                <thead>
                    <tr>
                        <th>To Peer</th>
                        <th>Content</th>
                        <th>Attachment</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $testimonial)
                        @php
                            $attachmentUrl = $extractMediaUrl($testimonial->media ?? null);
                            $toName = $testimonial->toUser->display_name ?? trim(($testimonial->toUser->first_name ?? '') . ' ' . ($testimonial->toUser->last_name ?? '')) ?: '—';
                        @endphp
                        <tr>
                            <td>
                                <div class="peer-badge-wrapper">
                                    <div class="peer-badge-avatar" style="background-color: {{ $getAvatarBg($toName) }}">
                                        {{ $getInitials($toName) }}
                                    </div>
                                    <div class="peer-badge-info">
                                        <div class="peer-badge-name">{{ $toName }}</div>
                                        <div class="peer-badge-meta">
                                            <span>{{ $testimonial->toUser->email ?? '—' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-truncate-multi text-secondary small" style="max-width: 350px;" title="{{ $testimonial->content }}">
                                    {{ $testimonial->content ?? '—' }}
                                </div>
                            </td>
                            <td>
                                @if ($attachmentUrl)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="bi bi-paperclip me-1"></i>Available
                                    </span>
                                    <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary ms-1" style="font-size: 0.72rem; padding: 2px 6px;">View</a>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="small text-muted">{{ optional($testimonial->created_at)->format('Y-m-d H:i') ?? '—' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No testimonials found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $items->links() }}
    </div>
@endsection
