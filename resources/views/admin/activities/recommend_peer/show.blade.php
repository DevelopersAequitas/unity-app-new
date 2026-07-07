@extends('admin.layouts.app')

@section('title', 'Recommend A Peer - Peer Activity')

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

        $displayName = function (?string $display, ?string $first, ?string $last): string {
            if ($display) {
                return $display;
            }
            $name = trim(($first ?? '') . ' ' . ($last ?? ''));
            return $name !== '' ? $name : '—';
        };

        $formatDateTime = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : '—';
        };

        $peerName = $displayName($peer->display_name ?? null, $peer->first_name ?? null, $peer->last_name ?? null);
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark" style="font-family: 'Outfit', sans-serif;">Recommend A Peer Entries</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted">Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.activities.recommend-peer.index') }}" class="text-decoration-none text-muted">Recommend A Peer</a></li>
                    <li class="breadcrumb-item active text-primary fw-medium" aria-current="page">{{ $peerName }}</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('admin.activities.recommend-peer.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Recommendations
        </a>
    </div>

    <!-- Peer Profile Summary Card -->
    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: var(--radius-md);">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="peer-badge-avatar" style="width: 60px; height: 60px; font-size: 1.3rem; background-color: {{ $getAvatarBg($peerName) }}">
                {{ $getInitials($peerName) }}
            </div>
            <div>
                <h4 class="fw-bold text-dark mb-1">{{ $peerName }}</h4>
                <div class="text-muted small">
                    <span class="me-3"><i class="bi bi-envelope me-1"></i>{{ $peer->email ?? '—' }}</span>
                    <span><i class="bi bi-telephone me-1"></i>{{ $peer->phone ?? '—' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Entries Logs Table -->
    <div class="card-activities-wrapper">
        <div class="card-header bg-white">
            <span class="fw-bold text-dark"><i class="bi bi-hand-thumbs-up text-primary me-2"></i>Recommendations by this Peer</span>
        </div>
        <div class="table-responsive">
            <table class="table table-premium align-middle mb-0">
                <thead>
                    <tr>
                        <th>Submitted At</th>
                        <th>Recommended Peer Name</th>
                        <th>Recommended Peer Mobile</th>
                        <th>How Well Known</th>
                        <th>Is Aware</th>
                        <th>Coins Awarded</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td><span class="small text-muted">{{ $formatDateTime($item->created_at ?? null) }}</span></td>
                            <td>
                                <div class="peer-badge-wrapper">
                                    <div class="peer-badge-avatar" style="background-color: {{ $getAvatarBg($item->peer_name ?? '') }}">
                                        {{ $getInitials($item->peer_name ?? '') }}
                                    </div>
                                    <div class="peer-badge-info">
                                        <div class="peer-badge-name">{{ $item->peer_name ?? '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="small text-dark fw-medium">{{ $item->peer_mobile ?? '—' }}</span></td>
                            <td><span class="small text-secondary">{{ $item->how_well_known ?? '—' }}</span></td>
                            <td>
                                <span class="badge {{ $item->is_aware ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' }} px-2 py-1">
                                    {{ $item->is_aware ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $item->coins_awarded ? 'bg-info-subtle text-info-emphasis border border-info-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} px-2 py-1">
                                    {{ $item->coins_awarded ? 'Awarded' : 'No' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No entries found.</td>
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
