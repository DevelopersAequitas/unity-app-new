@extends('admin.layouts.app')

@section('title', 'Registered Visitor')

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

        $formatDate = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : '—';
        };
    @endphp

    <!-- Header Component -->
    @include('admin.activities.partials.header', ['title' => 'Registered Visitor'])

    <!-- Metrics Cards -->
    <div class="activities-stats-grid">
        <div class="activity-metric-card">
            <div class="metric-icon bg-primary-subtle text-primary">
                <i class="bi bi-person-vcard-fill"></i>
            </div>
            <div class="metric-val">{{ number_format($items->total()) }}</div>
            <div class="metric-label">Total Registered Visitors</div>
        </div>

        <div class="activity-metric-card">
            <div class="metric-icon bg-success-subtle text-success">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="metric-val">
                {{ number_format($items->filter(fn($item) => strtolower((string)$item->status) === 'approved' || strtolower((string)$item->status) === 'attended')->count()) }}
            </div>
            <div class="metric-label">Approved / Attended Visitors (Page)</div>
        </div>
    </div>

    <!-- Filters Section -->
    <form id="adminactivitiesregister-visitorindexFiltersForm" method="GET" action="{{ route('admin.activities.register-visitor.index') }}">
        @include('admin.components.activity-filter-bar-v2', [
            'actionUrl' => route('admin.activities.register-visitor.index'),
            'resetUrl' => route('admin.activities.register-visitor.index'),
            'filters' => $filters,
            'circles' => $circles ?? collect(),
            'showExport' => false,
            'renderFormTag' => false,
            'formId' => 'adminactivitiesregister-visitorindexFiltersForm',
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
                            <th>Event Details</th>
                            <th>Visitor Details</th>
                            <th>Status</th>
                            <th>Coins Awarded</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        <tr class="bg-light filter-row">
                            <th class="text-muted">—</th>
                            <th><input type="text" name="peer_name" value="{{ $filters['peer_name'] ?? '' }}" placeholder="Peer Name" class="form-control form-control-sm"></th>
                            <th><input type="text" name="peer_phone" value="{{ $filters['peer_phone'] ?? '' }}" placeholder="Phone" class="form-control form-control-sm"></th>
                            <th>
                                <input type="text" name="event_type" value="{{ $filters['event_type'] ?? '' }}" placeholder="Event type" class="form-control form-control-sm mb-1">
                                <input type="text" name="event_name" value="{{ $filters['event_name'] ?? '' }}" placeholder="Event name" class="form-control form-control-sm mb-1">
                                <input type="date" name="event_date" value="{{ $filters['event_date'] ?? '' }}" class="form-control form-control-sm">
                            </th>
                            <th>
                                <input type="text" name="visitor_name" value="{{ $filters['visitor_name'] ?? '' }}" placeholder="Visitor name" class="form-control form-control-sm mb-1">
                                <input type="text" name="visitor_mobile" value="{{ $filters['visitor_mobile'] ?? '' }}" placeholder="Mobile" class="form-control form-control-sm mb-1">
                                <input type="text" name="visitor_city" value="{{ $filters['visitor_city'] ?? '' }}" placeholder="City" class="form-control form-control-sm mb-1">
                                <input type="text" name="visitor_business" value="{{ $filters['visitor_business'] ?? '' }}" placeholder="Business" class="form-control form-control-sm">
                            </th>
                            <th><input type="text" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="Status" class="form-control form-control-sm"></th>
                            <th><input type="number" name="coins_awarded" value="{{ $filters['coins_awarded'] ?? '' }}" placeholder="Coins" class="form-control form-control-sm"></th>
                            <th>
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm px-3">Apply</button>
                                    <a href="{{ route('admin.activities.register-visitor.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                                </div>
                            </th>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Visitor Name</th>
                        <th>Phone Number</th>
                        <th>Business Name</th>
                        <th>Visitor City</th>
                        <th>Event Date</th>
                        <th>Event Name</th>
                        <th>Event Type</th>
                        <th>Status</th>
                        <th>Coins Awarded</th>
                        <th class="text-end">Actions</th>
                        <th>Submitted At</th>
                    </tr>
                    <tr>
                        <th><input type="text" name="visitor_name" value="{{ $filters['visitor_name'] ?? '' }}" placeholder="Visitor Name" class="form-control form-control-sm"></th>
                        <th><input type="text" name="visitor_mobile" value="{{ $filters['visitor_mobile'] ?? '' }}" placeholder="Visitor Mobile" class="form-control form-control-sm"></th>
                        <th><input type="text" name="visitor_business" value="{{ $filters['visitor_business'] ?? '' }}" placeholder="Visitor Business" class="form-control form-control-sm"></th>
                        <th><input type="text" name="visitor_city" value="{{ $filters['visitor_city'] ?? '' }}" placeholder="Visitor City" class="form-control form-control-sm"></th>
                        <th><input type="date" name="event_date" value="{{ $filters['event_date'] ?? '' }}" class="form-control form-control-sm"></th>
                        <th><input type="text" name="event_name" value="{{ $filters['event_name'] ?? '' }}" placeholder="Event Name" class="form-control form-control-sm"></th>
                        <th><input type="text" name="event_type" value="{{ $filters['event_type'] ?? '' }}" placeholder="Event Type" class="form-control form-control-sm"></th>
                        <th><input type="text" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="Status" class="form-control form-control-sm"></th>
                        <th><input type="number" name="coins_awarded" value="{{ $filters['coins_awarded'] ?? '' }}" placeholder="Coins" class="form-control form-control-sm"></th>
                        <th class="text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                                <a href="{{ route('admin.activities.register-visitor.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                            </div>
                        </th>
                        <th class="text-muted">—</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $visitorSearch = $item->visitor_mobile ? ['search' => $item->visitor_mobile] : [];
                        @endphp
                        <tr>
                            <td>{{ $item->visitor_full_name ?? '—' }}</td>
                            <td>{{ $item->visitor_mobile ?? '—' }}</td>
                            <td>{{ $item->visitor_business ?? '—' }}</td>
                            <td>{{ $item->visitor_city ?? '—' }}</td>
                            <td>{{ $formatDate($item->event_date ?? null) }}</td>
                            <td>{{ $item->event_name ?? '—' }}</td>
                            <td>{{ ucfirst($item->event_type ?? '—') }}</td>
                            <td>{{ ucfirst($item->status ?? '—') }}</td>
                            <td>{{ $item->coins_awarded ? 'Yes' : 'No' }}</td>
                            <td class="text-end">
                                @if ($item->visitor_mobile)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.visitor-registrations.index', $visitorSearch) }}">
                                        Open Approval Page
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $formatDateTime($item->created_at ?? null) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted">No visitor registrations found.</td>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            @php
                                $peerName = $item->peer_name ?? '—';
                                $visitorSearch = $item->visitor_mobile ? ['search' => $item->visitor_mobile] : [];
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
                                <td>
                                    <div class="fw-semibold text-dark small">{{ $item->event_name ?? '—' }}</div>
                                    <div class="small text-muted"><span class="badge bg-light text-dark border">{{ ucfirst($item->event_type ?? '—') }}</span></div>
                                    @if($item->event_date)
                                        <div class="small text-muted mt-1"><i class="bi bi-calendar-event me-1"></i>{{ $formatDate($item->event_date) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark small">{{ $item->visitor_full_name ?? '—' }}</div>
                                    <div class="small text-muted"><i class="bi bi-telephone me-1"></i>{{ $item->visitor_mobile ?? '—' }}</div>
                                    <div class="small text-muted"><i class="bi bi-building me-1"></i>{{ $item->visitor_business ?? '—' }} ({{ $item->visitor_city ?? '—' }})</div>
                                </td>
                                <td>
                                    <span class="badge {{ strtolower((string)$item->status) === 'approved' || strtolower((string)$item->status) === 'attended' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' }} px-2 py-1">
                                        {{ ucfirst($item->status ?? '—') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $item->coins_awarded ? 'bg-info-subtle text-info-emphasis border border-info-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} px-2 py-1">
                                        {{ $item->coins_awarded ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @if ($item->visitor_mobile)
                                        <a class="btn btn-xs btn-outline-primary" style="font-size: 0.72rem; padding: 2px 8px;" href="{{ route('admin.visitor-registrations.index', $visitorSearch) }}">
                                            Open Approval
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No visitor registrations found.</td>
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
