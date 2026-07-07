@extends('admin.layouts.app')

@section('title', 'Referral Report')

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
@endphp

<div class="card-activities-wrapper">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-3 py-3 px-4">
        <div>
            <h1 class="h4 mb-1 text-dark fw-bold"><i class="bi bi-person-lines-fill text-primary me-2"></i>Referral Report</h1>
            <p class="text-muted small mb-0 mt-1">See which peer referred how many users and how many referral coins were granted.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge bg-light text-dark border">Total Referrers: {{ number_format($records->total()) }}</span>
            <a href="{{ route('admin.referral-report.export', request()->query()) }}" class="btn btn-success btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
        </div>
    </div>

    <div class="p-4">
        <form id="referralReportFilters" method="GET" action="{{ route('admin.referral-report.index') }}"></form>

        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
            <div class="small text-muted">
                @if($records->total() > 0)
                    Records {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }}
                @else
                    No records found
                @endif
            </div>
            <div class="d-flex flex-wrap justify-content-end align-items-end gap-2">
                <div>
                    <label class="form-label small text-muted mb-1">From Date</label>
                    <input type="date" name="from" form="referralReportFilters" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div>
                    <label class="form-label small text-muted mb-1">To Date</label>
                    <input type="date" name="to" form="referralReportFilters" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-premium align-middle mb-0">
                <thead>
                    <tr>
                        <th style="min-width: 250px;">Referrer</th>
                        <th style="min-width: 140px;">Referral Code</th>
                        <th style="min-width: 320px;">Referred Users</th>
                        <th class="text-center" style="min-width: 130px;">Total Users</th>
                        <th class="text-center" style="min-width: 140px;">Coins Granted</th>
                        <th style="min-width: 170px;">Last Referral Date</th>
                        <th class="text-end" style="min-width: 150px;">Action</th>
                    </tr>
                    <tr class="bg-light filter-row">
                        <th>
                            <input
                                type="text"
                                name="q"
                                form="referralReportFilters"
                                value="{{ $filters['q'] ?? '' }}"
                                class="form-control form-control-sm"
                                placeholder="Name, email, phone, code"
                            >
                        </th>
                        <th>
                            <input
                                type="text"
                                name="referral_code"
                                form="referralReportFilters"
                                value="{{ $filters['referral_code'] ?? '' }}"
                                class="form-control form-control-sm"
                                placeholder="Referral Code"
                            >
                        </th>
                        <th>
                            <input
                                type="text"
                                name="referred_q"
                                form="referralReportFilters"
                                value="{{ $filters['referred_q'] ?? '' }}"
                                class="form-control form-control-sm"
                                placeholder="Search referred user"
                            >
                        </th>
                        <th>
                            <select name="sort" form="referralReportFilters" class="form-select form-select-sm js-no-searchable-select">
                                <option value="last_referral_date" @selected(($filters['sort'] ?? '') === 'last_referral_date')>Sort: Last Referral</option>
                                <option value="total_referred_users" @selected(($filters['sort'] ?? '') === 'total_referred_users')>Sort: Total Users</option>
                            </select>
                        </th>
                        <th>
                            <select name="reward_status" form="referralReportFilters" class="form-select form-select-sm js-no-searchable-select" @disabled(! $hasRewardStatus)>
                                <option value="">All Statuses</option>
                                @foreach (['granted' => 'Granted', 'pending' => 'Pending', 'failed' => 'Failed'] as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['reward_status'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </th>
                        <th>
                            <select name="direction" form="referralReportFilters" class="form-select form-select-sm js-no-searchable-select">
                                <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Descending</option>
                                <option value="asc" @selected(($filters['direction'] ?? 'desc') === 'asc')>Ascending</option>
                            </select>
                        </th>
                        <th class="text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <select id="perPage" name="per_page" form="referralReportFilters" class="form-select form-select-sm js-no-searchable-select" style="width: 80px;">
                                    @foreach ([10, 20, 50, 100] as $size)
                                        <option value="{{ $size }}" @selected(($filters['per_page'] ?? 20) == $size)>{{ $size }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" form="referralReportFilters" class="btn btn-primary btn-sm px-3">Apply</button>
                                <a href="{{ route('admin.referral-report.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td>
                                <div class="peer-badge-wrapper">
                                    <div class="peer-badge-avatar" style="background-color: {{ $getAvatarBg($record->referrer_name ?? '') }}">
                                        {{ $getInitials($record->referrer_name ?? '') }}
                                    </div>
                                    <div class="peer-badge-info">
                                        <div class="peer-badge-name">{{ $record->referrer_name ?: 'Deleted / Unknown User' }}</div>
                                        <div class="peer-badge-meta">
                                            @if($record->referrer_company) <span>{{ $record->referrer_company }}</span> @endif
                                            @if($record->referrer_city) &bull; <span>{{ $record->referrer_city }}</span> @endif
                                            @if($record->referrer_phone) &bull; <span>{{ $record->referrer_phone }}</span> @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border text-wrap">{{ $record->referral_codes ?: '—' }}</span>
                            </td>
                            <td style="min-width: 320px;">
                                @php
                                    $referredUsers = $referredUsersByReferrer->get((string) $record->referrer_user_id, collect());
                                @endphp
                                @if($referredUsers->isNotEmpty())
                                    <div class="d-flex flex-column gap-2" style="max-height: 260px; overflow-y: auto;">
                                        @foreach($referredUsers as $referredUser)
                                            <div class="peer-badge-wrapper border rounded-3 p-2 bg-light-subtle">
                                                <div class="peer-badge-avatar" style="background-color: {{ $getAvatarBg($referredUser->referred_name ?? '') }}">
                                                    {{ $getInitials($referredUser->referred_name ?? '') }}
                                                </div>
                                                <div class="peer-badge-info">
                                                    <div class="peer-badge-name">{{ $referredUser->referred_name ?: 'Deleted / Unknown User' }}</div>
                                                    <div class="peer-badge-meta">
                                                        @if($referredUser->company_name) <span>{{ $referredUser->company_name }}</span> @endif
                                                        @if($referredUser->city) &bull; <span>{{ $referredUser->city }}</span> @endif
                                                        @if($referredUser->referred_phone) &bull; <span>{{ $referredUser->referred_phone }}</span> @endif
                                                    </div>
                                                    <div class="d-flex flex-wrap gap-2 mt-1 small">
                                                        <span class="badge bg-light text-dark border">{{ $referredUser->used_at ? \Illuminate\Support\Carbon::parse($referredUser->used_at)->format('d-m-Y h:i A') : 'No date' }}</span>
                                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">{{ number_format((int) $referredUser->coins) }} coins</span>
                                                        <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">{{ $referredUser->reward_status ?: '—' }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted small">No referred users found.</span>
                                @endif
                            </td>
                            <td class="text-center fw-semibold">{{ number_format((int) $record->total_referred_users) }}</td>
                            <td class="text-center">
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                    {{ number_format((int) $record->total_coins_granted) }} coins
                                </span>
                            </td>
                            <td>{{ $record->last_referral_date ? \Illuminate\Support\Carbon::parse($record->last_referral_date)->format('d-m-Y h:i A') : '—' }}</td>
                            <td class="text-end">
                                @if($record->referrer_user_id)
                                    <a href="{{ route('admin.referral-report.show', $record->referrer_user_id) }}" class="btn btn-outline-primary btn-sm">
                                        View Users
                                    </a>
                                @else
                                    <span class="text-muted small">No referrer ID</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No referral users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $records->links() }}
        </div>
    </div>
</div>
@endsection
