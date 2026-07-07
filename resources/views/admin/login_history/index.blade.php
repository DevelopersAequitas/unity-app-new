@extends('admin.layouts.app')

@section('title', 'Login History')

@section('content')
<div class="card p-3">
    <form id="loginHistoryFiltersForm" method="GET" action="{{ route('admin.login-history.index') }}"></form>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="d-flex align-items-center gap-2">
            <label for="perPage" class="form-label mb-0 small text-muted">Rows per page:</label>
            <select id="perPage" name="per_page" form="loginHistoryFiltersForm" class="form-select form-select-sm" style="width: 90px;">
                @foreach ([10, 20, 50, 100] as $size)
                    <option value="{{ $size }}" @selected(($filters['per_page'] ?? 20) == $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <input
                type="datetime-local"
                name="from"
                form="loginHistoryFiltersForm"
                value="{{ $filters['from'] ?? '' }}"
                class="form-control form-control-sm"
                style="min-width: 180px;"
                title="From Time"
            >
            <input
                type="datetime-local"
                name="to"
                form="loginHistoryFiltersForm"
                value="{{ $filters['to'] ?? '' }}"
                class="form-control form-control-sm"
                style="min-width: 180px;"
                title="To Time"
            >
        </div>

        <div class="small text-muted">
            @if($records->total() > 0)
                Records {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }}
            @else
                No records found
            @endif
        </div>
    </div>

    <div class="table-responsive premium-table-card">
        <table class="table premium-table align-middle">
            <thead>
                <tr>
                    <th style="padding-left: 20px !important;">Peer Name</th>
                    <th>Company Name</th>
                    <th>City</th>
                    <th>Circle</th>
                    <th class="text-end" style="padding-right: 20px !important;">Last Login</th>
                </tr>
                <tr class="bg-light align-middle">
                    <th style="padding-left: 20px !important;">
                        <input
                            type="text"
                            name="q"
                            form="loginHistoryFiltersForm"
                            class="form-control form-control-sm"
                            placeholder="Search Name"
                            value="{{ $filters['q'] ?? '' }}"
                        >
                    </th>
                    <th>
                        <input
                            type="text"
                            name="company"
                            form="loginHistoryFiltersForm"
                            class="form-control form-control-sm"
                            placeholder="Filter Company"
                            value="{{ $filters['company'] ?? '' }}"
                        >
                    </th>
                    <th>
                        <input
                            type="text"
                            name="city"
                            form="loginHistoryFiltersForm"
                            class="form-control form-control-sm"
                            placeholder="Filter City"
                            value="{{ $filters['city'] ?? '' }}"
                        >
                    </th>
                    <th>
                        <select name="circle_id" form="loginHistoryFiltersForm" class="form-select form-select-sm">
                            <option value="">All Circles</option>
                            @foreach ($circleOptions as $id => $name)
                                <option value="{{ $id }}" @selected(($filters['circle_id'] ?? '') == (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <select name="join_status" form="loginHistoryFiltersForm" class="form-select form-select-sm mt-2">
                            <option value="all" @selected(($filters['join_status'] ?? 'all') === 'all')>All</option>
                            <option value="joined" @selected(($filters['join_status'] ?? 'all') === 'joined')>Joined</option>
                            <option value="not_joined" @selected(($filters['join_status'] ?? 'all') === 'not_joined')>Not Joined</option>
                        </select>
                    </th>
                    <th style="padding-right: 20px !important;">
                        <input
                            type="date"
                            name="last_login_date"
                            form="loginHistoryFiltersForm"
                            class="form-control form-control-sm mb-2"
                            value="{{ $filters['last_login_date'] ?? '' }}"
                        >
                        <div class="d-flex gap-2 justify-content-end">
                            <button type="submit" form="loginHistoryFiltersForm" class="btn btn-primary btn-sm">Apply</button>
                            <a href="{{ route('admin.login-history.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $peerName = $record->peer_name ?: '—';
                        $avatar = $record->profile_photo_url ?? ($record->profile_photo_file_id ? url('/api/v1/files/' . $record->profile_photo_file_id) : null);
                        
                        // Parse values and strip out standard empty string placeholders
                        $rawCity = $record->city ?? '';
                        if (is_string($rawCity)) {
                            $rawCity = trim($rawCity);
                            if (str_starts_with($rawCity, '{')) {
                                $decodedCity = json_decode($rawCity, true);
                                if (is_array($decodedCity)) {
                                    $cityName = $decodedCity['name'] ?? $decodedCity['label'] ?? $rawCity;
                                } elseif (preg_match('/name:\s*([^,}]+)/', $rawCity, $matches)) {
                                    $cityName = trim($matches[1], " \t\n\r\0\x0B\"'");
                                } else {
                                    $cityName = $rawCity;
                                }
                            } else {
                                $cityName = $rawCity;
                            }
                        } elseif (is_array($rawCity)) {
                            $cityName = $rawCity['name'] ?? $rawCity['label'] ?? '';
                        } elseif (is_object($rawCity)) {
                            $cityName = $rawCity->name ?? $rawCity->label ?? '';
                        } else {
                            $cityName = $rawCity;
                        }
                        
                        if (in_array(strtolower(trim((string)$cityName)), ['', 'no city', 'none', 'null', 'no_city'], true)) {
                            $cityName = null;
                        }
                        
                        $company = $record->company ?? '';
                        if (in_array(strtolower(trim((string)$company)), ['', 'no company', 'none', 'null', 'no_company', 'peers global'], true)) {
                            $company = null;
                        }
                        
                        $gradientIndex = abs(crc32((string) $record->id)) % 5;
                    @endphp
                    <tr>
                        <td style="padding-left: 20px !important;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="peer-avatar-wrapper">
                                    @if ($avatar)
                                        <img src="{{ $avatar }}" alt="{{ $peerName }}" class="peer-avatar-image">
                                    @else
                                        <div class="peer-avatar-placeholder bg-gradient-peer-{{ $gradientIndex }}">
                                            {{ strtoupper(substr($peerName !== '' ? $peerName : 'U', 0, 1)) }}
                                        </div>
                                    @endif
                                </div>
                                <div class="d-flex flex-column">
                                    <div class="fw-semibold text-dark text-nowrap" style="font-size: 0.92rem;">{{ $peerName }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if ($company)
                                <span class="text-dark d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                    <i class="bi bi-building text-muted small"></i>{{ $company }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($cityName)
                                <span class="text-dark d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                    <i class="bi bi-geo-alt text-muted small"></i>{{ $cityName }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if (!empty($record->circles_names))
                                <span class="text-primary fw-semibold d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;" title="{{ $record->circles_names }}">
                                    <i class="bi bi-people text-primary small"></i>{{ explode(', ', $record->circles_names)[0] }}
                                    @if ((int) $record->circles_count > 1)
                                        <span class="badge bg-primary-subtle text-primary border-0 rounded-pill ms-1" style="font-size: 0.7rem; padding: 2px 6px;">+{{ (int) $record->circles_count - 1 }}</span>
                                    @endif
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end" style="padding-right: 20px !important;">
                            @if ($record->last_login_at)
                                <span class="text-secondary d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                    <i class="bi bi-clock text-muted small"></i>{{ \Illuminate\Support\Carbon::parse($record->last_login_at)->format('d M Y h:i A') }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-2">
        {{ $records->links() }}
    </div>
</div>
@endsection
