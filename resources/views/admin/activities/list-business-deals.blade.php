@extends('admin.layouts.app')

@section('title', 'Business Deals')

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

        $formatDateTime = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : '—';
        };

        $formatDate = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : '—';
        };

        $peerName = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')) ?: $member->display_name ?: 'Unnamed Peer';
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark" style="font-family: 'Outfit', sans-serif;">Business Deals Log</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted">Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.activities.index') }}" class="text-decoration-none text-muted">Activities Summary</a></li>
                    <li class="breadcrumb-item active text-primary fw-medium" aria-current="page">Business Deals of {{ $peerName }}</li>
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
                    <a href="{{ route('admin.activities.business-deals', $member) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-premium align-middle mb-0">
                <thead>
                    <tr>
                        <th>Deal With</th>
                        <th>Deal Info</th>
                        <th>Amount</th>
                        <th>Comment</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $deal)
                        @php
                            $targetName = $deal->toUser->display_name ?? trim(($deal->toUser->first_name ?? '') . ' ' . ($deal->toUser->last_name ?? '')) ?: '—';
                        @endphp
                        <tr>
                            <td>
                                <div class="peer-badge-wrapper">
                                    <div class="peer-badge-avatar" style="background-color: {{ $getAvatarBg($targetName) }}">
                                        {{ $getInitials($targetName) }}
                                    </div>
                                    <div class="peer-badge-info">
                                        <div class="peer-badge-name">{{ $targetName }}</div>
                                        <div class="peer-badge-meta">
                                            <span>{{ $deal->toUser->email ?? '—' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($deal->deal_date)
                                    <div class="fw-semibold text-dark small"><i class="bi bi-calendar-check me-1 text-muted"></i>{{ $formatDate($deal->deal_date) }}</div>
                                @endif
                                <span class="badge bg-light text-dark border mt-1">{{ $deal->business_type ?? '—' }}</span>
                            </td>
                            <td>
                                <span class="fw-bold text-success">₹{{ $deal->deal_amount !== null ? number_format((float) $deal->deal_amount, 2) : '—' }}</span>
                            </td>
                            <td>
                                <div class="text-truncate-multi text-secondary small" style="max-width: 250px;" title="{{ $deal->comment }}">
                                    {{ $deal->comment ?? '—' }}
                                </div>
                            </td>
                            <td>
                                <span class="small text-muted">{{ $formatDateTime($deal->created_at ?? null) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No business deals found.</td>
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
