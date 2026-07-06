@extends('admin.layouts.app')

@section('title', 'Become A Leader')

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
    @endphp

    <!-- Header Component -->
    @include('admin.activities.partials.header', ['title' => 'Become A Leader'])

    <!-- Metrics Cards -->
    <div class="activities-stats-grid">
        <div class="activity-metric-card">
            <div class="metric-icon bg-primary-subtle text-primary">
                <i class="bi bi-award-fill"></i>
            </div>
            <div class="metric-val">{{ number_format($items->total()) }}</div>
            <div class="metric-label">Total Submissions</div>
        </div>

        <div class="activity-metric-card">
            <div class="metric-icon bg-success-subtle text-success">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="metric-val">
                {{ number_format($items->filter(fn($item) => $item->created_at >= now()->subDays(30))->count()) }}
            </div>
            <div class="metric-label">Recent Submissions (30 Days)</div>
        </div>
    </div>

    <!-- Filters Section -->
    <form id="adminactivitiesbecome-a-leaderindexFiltersForm" method="GET" action="{{ route('admin.activities.become-a-leader.index') }}">
        @include('admin.components.activity-filter-bar-v2', [
            'actionUrl' => route('admin.activities.become-a-leader.index'),
            'resetUrl' => route('admin.activities.become-a-leader.index'),
            'filters' => $filters,
            'circles' => $circles ?? collect(),
            'showExport' => false,
            'renderFormTag' => false,
            'formId' => 'adminactivitiesbecome-a-leaderindexFiltersForm',
        ])

        <!-- Table Card -->
        <div class="card-activities-wrapper">
            <div class="table-responsive">
                <table class="table table-premium align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Submitted At</th>
                            <th>Peer Details</th>
                            <th>Peer Phone</th>
                            <th>Applying For</th>
                            <th>Referred Details</th>
                            <th>Leadership Roles</th>
                            <th>City / Region</th>
                            <th>Primary Domain</th>
                            <th>Why Interested</th>
                        </tr>
                        <tr class="bg-light filter-row">
                            <th class="text-muted">—</th>
                            <th><input type="text" name="peer_name" value="{{ $filters['peer_name'] ?? '' }}" placeholder="Peer Name" class="form-control form-control-sm"></th>
                            <th><input type="text" name="peer_phone" value="{{ $filters['peer_phone'] ?? '' }}" placeholder="Phone" class="form-control form-control-sm"></th>
                            <th><input type="text" name="applying_for" value="{{ $filters['applying_for'] ?? '' }}" placeholder="Applying For" class="form-control form-control-sm"></th>
                            <th>
                                <input type="text" name="referred_name" value="{{ $filters['referred_name'] ?? '' }}" placeholder="Referred Name" class="form-control form-control-sm mb-1">
                                <input type="text" name="referred_mobile" value="{{ $filters['referred_mobile'] ?? '' }}" placeholder="Mobile" class="form-control form-control-sm">
                            </th>
                            <th><input type="text" name="leadership_roles" value="{{ $filters['leadership_roles'] ?? '' }}" placeholder="Roles" class="form-control form-control-sm"></th>
                            <th><input type="text" name="city_region" value="{{ $filters['city_region'] ?? '' }}" placeholder="City" class="form-control form-control-sm"></th>
                            <th><input type="text" name="primary_domain" value="{{ $filters['primary_domain'] ?? '' }}" placeholder="Domain" class="form-control form-control-sm"></th>
                            <th>
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm px-3">Apply</button>
                                    <a href="{{ route('admin.activities.become-a-leader.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            @php
                                $peerName = $item->peer_name ?? '—';
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
                                                @if($item->peer_company) <span>{{ $item->peer_company }}</span> @endif
                                                @if($item->peer_city) &bull; <span>{{ $item->peer_city }}</span> @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="small">{{ $item->peer_phone ?? '—' }}</span></td>
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
                                    <div class="text-truncate-multi text-muted small" style="max-width: 250px;" title="{{ $item->why_interested }}">
                                        {{ $item->why_interested ?? '—' }}
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No submissions found.</td>
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
