@extends('admin.layouts.app')

@section('title', 'Recommended Peers')

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
    @endphp

    <!-- Header Component -->
    @include('admin.activities.partials.header', ['title' => 'Recommended Peers'])

    <!-- Metrics Cards -->
    <div class="activities-stats-grid">
        <div class="activity-metric-card">
            <div class="metric-icon bg-primary-subtle text-primary">
                <i class="bi bi-hand-thumbs-up-fill"></i>
            </div>
            <div class="metric-val">{{ number_format($items->total()) }}</div>
            <div class="metric-label">Total Recommendations</div>
        </div>

        <div class="activity-metric-card">
            <div class="metric-icon bg-success-subtle text-success">
                <i class="bi bi-person-fill-check"></i>
            </div>
            <div class="metric-val">
                {{ number_format($items->filter(fn($item) => $item->is_aware)->count()) }}
            </div>
            <div class="metric-label">Peers Aware (Page)</div>
        </div>
    </div>

    <!-- Filters Section -->
    <form id="adminactivitiesrecommend-peerindexFiltersForm" method="GET" action="{{ route('admin.activities.recommend-peer.index') }}">
        @include('admin.components.activity-filter-bar-v2', [
            'actionUrl' => route('admin.activities.recommend-peer.index'),
            'resetUrl' => route('admin.activities.recommend-peer.index'),
            'filters' => $filters,
            'circles' => $circles ?? collect(),
            'showExport' => false,
            'renderFormTag' => false,
            'formId' => 'adminactivitiesrecommend-peerindexFiltersForm',
        ])

        <!-- Table Card -->
        <div class="card-activities-wrapper">
            <div class="table-responsive">
                <table class="table table-premium align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Submitted At</th>
                            <th>Recommender Details</th>
                            <th>Recommender Phone</th>
                            <th>Recommended Peer Name</th>
                            <th>Recommended Peer Mobile</th>
                            <th>How Well Known</th>
                            <th>Is Aware</th>
                            <th>Coins Awarded</th>
                        </tr>
                        <tr class="bg-light filter-row">
                            <th class="text-muted">—</th>
                            <th><input type="text" name="peer_name" value="{{ $filters['peer_name'] ?? '' }}" placeholder="Peer Name" class="form-control form-control-sm"></th>
                            <th><input type="text" name="peer_phone" value="{{ $filters['peer_phone'] ?? '' }}" placeholder="Phone" class="form-control form-control-sm"></th>
                            <th><input type="text" name="recommended_peer_name" value="{{ $filters['recommended_peer_name'] ?? '' }}" placeholder="Rec Peer Name" class="form-control form-control-sm"></th>
                            <th><input type="text" name="recommended_peer_mobile" value="{{ $filters['recommended_peer_mobile'] ?? '' }}" placeholder="Mobile" class="form-control form-control-sm"></th>
                            <th><input type="text" name="how_well_known" value="{{ $filters['how_well_known'] ?? '' }}" placeholder="Known" class="form-control form-control-sm"></th>
                            <th>
                                <select name="is_aware" class="form-select form-select-sm">
                                    <option value="">Any</option>
                                    <option value="yes" @selected(($filters['is_aware'] ?? '')==='yes')>Yes</option>
                                    <option value="no" @selected(($filters['is_aware'] ?? '')==='no')>No</option>
                                </select>
                            </th>
                            <th>
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm px-3">Apply</button>
                                    <a href="{{ route('admin.activities.recommend-peer.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            @php
                                $peerName = $item->from_user_name ?? '—';
                            @endphp
                            <tr>
                                <td><span class="small text-muted">{{ $formatDateTime($item->created_at ?? null) }}</span></td>
                                <td>
                                    <div class="peer-badge-wrapper">
                                        <div class="peer-badge-avatar" style="background-color: {{ $getAvatarBg($peerName) }}">
                                            {{ $getInitials($peerName) }}
                                        </div>
                                        <div class="peer-badge-info">
                                            <div class="peer-badge-name">{{ $peerName }}</div>
                                            <div class="peer-badge-meta">
                                                @if($item->from_company) <span>{{ $item->from_company }}</span> @endif
                                                @if($item->from_city) &bull; <span>{{ $item->from_city }}</span> @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="small">{{ $item->from_phone ?? '—' }}</span></td>
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
                                <td colspan="8" class="text-center text-muted py-4">No entries found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <div class="mt-3">
        {{ $items->links() }}
    </div>
@endsection
@extends('admin.layouts.app')

@section('title', 'Recommend A Peer')

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
    @endphp

    <!-- Header Component -->
    @include('admin.activities.partials.header', ['title' => 'Recommended Peers'])

    <!-- Metrics Cards -->
    <div class="activities-stats-grid">
        <div class="activity-metric-card">
            <div class="metric-icon bg-primary-subtle text-primary">
                <i class="bi bi-hand-thumbs-up-fill"></i>
            </div>
            <div class="metric-val">{{ number_format($items->total()) }}</div>
            <div class="metric-label">Total Recommendations</div>
        </div>

        <div class="activity-metric-card">
            <div class="metric-icon bg-success-subtle text-success">
                <i class="bi bi-person-fill-check"></i>
            </div>
            <div class="metric-val">
                {{ number_format($items->filter(fn($item) => $item->is_aware)->count()) }}
            </div>
            <div class="metric-label">Peers Aware (Page)</div>
        </div>
    </div>

    <!-- Filters Section -->
    <form id="adminactivitiesrecommend-peerindexFiltersForm" method="GET" action="{{ route('admin.activities.recommend-peer.index') }}">
        @include('admin.components.activity-filter-bar-v2', [
            'actionUrl' => route('admin.activities.recommend-peer.index'),
            'resetUrl' => route('admin.activities.recommend-peer.index'),
            'filters' => $filters,
            'circles' => $circles ?? collect(),
            'showExport' => false,
            'renderFormTag' => false,
            'formId' => 'adminactivitiesrecommend-peerindexFiltersForm',
        ])

        <!-- Table Card -->
        <div class="card-activities-wrapper">
            <div class="table-responsive">
                <table class="table table-premium align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Submitted At</th>
                            <th>Recommender Details</th>
                            <th>Recommender Phone</th>
                            <th>Recommended Peer Name</th>
                            <th>Recommended Peer Mobile</th>
                            <th>How Well Known</th>
                            <th>Is Aware</th>
                            <th>Coins Awarded</th>
                        </tr>
                        <tr class="bg-light filter-row">
                            <th class="text-muted">—</th>
                            <th><input type="text" name="peer_name" value="{{ $filters['peer_name'] ?? '' }}" placeholder="Peer Name" class="form-control form-control-sm"></th>
                            <th><input type="text" name="peer_phone" value="{{ $filters['peer_phone'] ?? '' }}" placeholder="Phone" class="form-control form-control-sm"></th>
                            <th><input type="text" name="recommended_peer_name" value="{{ $filters['recommended_peer_name'] ?? '' }}" placeholder="Rec Peer Name" class="form-control form-control-sm"></th>
                            <th><input type="text" name="recommended_peer_mobile" value="{{ $filters['recommended_peer_mobile'] ?? '' }}" placeholder="Mobile" class="form-control form-control-sm"></th>
                            <th><input type="text" name="how_well_known" value="{{ $filters['how_well_known'] ?? '' }}" placeholder="Known" class="form-control form-control-sm"></th>
                            <th>
                                <select name="is_aware" class="form-select form-select-sm">
                                    <option value="">Any</option>
                                    <option value="yes" @selected(($filters['is_aware'] ?? '')==='yes')>Yes</option>
                                    <option value="no" @selected(($filters['is_aware'] ?? '')==='no')>No</option>
                                </select>
                            </th>
                            <th>
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm px-3">Apply</button>
                                    <a href="{{ route('admin.activities.recommend-peer.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            @php
                                $peerName = $item->from_user_name ?? '—';
                            @endphp
                            <tr>
                                <td><span class="small text-muted">{{ $formatDateTime($item->created_at ?? null) }}</span></td>
                                <td>
                                    <div class="peer-badge-wrapper">
                                        <div class="peer-badge-avatar" style="background-color: {{ $getAvatarBg($peerName) }}">
                                            {{ $getInitials($peerName) }}
                                        </div>
                                        <div class="peer-badge-info">
                                            <div class="peer-badge-name">{{ $peerName }}</div>
                                            <div class="peer-badge-meta">
                                                @if($item->from_company) <span>{{ $item->from_company }}</span> @endif
                                                @if($item->from_city) &bull; <span>{{ $item->from_city }}</span> @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="small">{{ $item->from_phone ?? '—' }}</span></td>
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
                                <td colspan="8" class="text-center text-muted py-4">No entries found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <div class="mt-3">
        {{ $items->links() }}
    </div>
@endsection
