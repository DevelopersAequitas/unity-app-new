@extends('admin.layouts.app')

@section('title', 'Become A Leader - Peer Activity')

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

        $formatRoles = function ($roles): string {
            if (! $roles) {
                return '—';
            }
            $list = is_array($roles) ? $roles : (array) $roles;
            $list = array_filter($list);
            return $list ? implode(', ', $list) : '—';
        };

        $truncate = function ($value, int $limit = 80): string {
            return $value ? \Illuminate\Support\Str::limit($value, $limit) : '—';
        };

        $peerName = $displayName($peer->display_name ?? null, $peer->first_name ?? null, $peer->last_name ?? null);
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark" style="font-family: 'Outfit', sans-serif;">Become A Leader Entries</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted">Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.activities.become-a-leader.index') }}" class="text-decoration-none text-muted">Become A Leader</a></li>
                    <li class="breadcrumb-item active text-primary fw-medium" aria-current="page">{{ $peerName }}</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('admin.activities.become-a-leader.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Leader Submissions
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
            <span class="fw-bold text-dark"><i class="bi bi-award text-primary me-2"></i>Submissions by this Peer</span>
        </div>
        <div class="table-responsive">
            <table class="table table-premium align-middle mb-0">
                <thead>
                    <tr>
                        <th>Submitted At</th>
                        <th>Applying For</th>
                        <th>Referred Details</th>
                        <th>Leadership Roles</th>
                        <th>City / Region</th>
                        <th>Primary Domain</th>
                        <th>Why Interested</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td><span class="small text-muted">{{ $formatDateTime($item->created_at ?? null) }}</span></td>
                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">{{ $item->applying_for ?? '—' }}</span></td>
                            <td>
                                @if($item->referred_name)
                                    <div class="small fw-semibold text-dark">{{ $item->referred_name }}</div>
                                    <div class="small text-muted"><i class="bi bi-telephone me-1"></i>{{ $item->referred_mobile ?: '—' }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><span class="small">{{ $formatRoles($item->leadership_roles ?? null) }}</span></td>
                            <td><span class="small text-secondary">{{ $item->contribute_city ?? '—' }}</span></td>
                            <td><span class="small">{{ $item->primary_domain ?? '—' }}</span></td>
                            <td>
                                <div class="text-truncate-multi text-muted small" style="max-width: 300px;" title="{{ $item->why_interested }}">
                                    {{ $item->why_interested ?? '—' }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No entries found.</td>
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
