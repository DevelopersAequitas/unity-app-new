@extends('admin.layouts.app')

@section('title', 'Notification Monitoring Dashboard')

@include('admin.partials.grid-head')

@push('styles')
<style>
/* ── Premium Summary Card System ── */
.monitor-summary-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 16px 18px;
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    cursor: pointer;
    text-decoration: none !important;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    color: inherit;
    box-shadow: 0 2px 4px rgba(15, 23, 42, 0.02);
    min-height: 115px;
    overflow: hidden;
}
.monitor-summary-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: transparent;
    transition: all 0.2s;
}
.monitor-summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px -6px rgba(15, 23, 42, 0.08);
}

.mcard-teal:hover { border-color: #0d9488; }
.mcard-teal:hover::before, .mcard-teal.active-filter::before { background: #0d9488; }
.mcard-teal.active-filter { border-color: #0d9488; box-shadow: 0 0 0 2px rgba(13, 148, 136, 0.25); background: linear-gradient(180deg, rgba(13,148,136,0.04) 0%, #fff 100%); }

.mcard-emerald:hover { border-color: #10b981; }
.mcard-emerald:hover::before, .mcard-emerald.active-filter::before { background: #10b981; }
.mcard-emerald.active-filter { border-color: #10b981; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25); background: linear-gradient(180deg, rgba(16,185,129,0.04) 0%, #fff 100%); }

.mcard-rose:hover { border-color: #f43f5e; }
.mcard-rose:hover::before, .mcard-rose.active-filter::before { background: #f43f5e; }
.mcard-rose.active-filter { border-color: #f43f5e; box-shadow: 0 0 0 2px rgba(244, 63, 94, 0.25); background: linear-gradient(180deg, rgba(244,63,94,0.04) 0%, #fff 100%); }

.mcard-amber:hover { border-color: #f59e0b; }
.mcard-amber:hover::before, .mcard-amber.active-filter::before { background: #f59e0b; }
.mcard-amber.active-filter { border-color: #f59e0b; box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.25); background: linear-gradient(180deg, rgba(245,158,11,0.04) 0%, #fff 100%); }

.mcard-sky:hover { border-color: #0ea5e9; }
.mcard-sky:hover::before, .mcard-sky.active-filter::before { background: #0ea5e9; }
.mcard-sky.active-filter { border-color: #0ea5e9; box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.25); background: linear-gradient(180deg, rgba(14,165,233,0.04) 0%, #fff 100%); }

.mcard-indigo:hover { border-color: #6366f1; }
.mcard-indigo:hover::before, .mcard-indigo.active-filter::before { background: #6366f1; }
.mcard-indigo.active-filter { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25); background: linear-gradient(180deg, rgba(99,102,241,0.04) 0%, #fff 100%); }

.monitor-summary-card .icon-box {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.monitor-summary-card .stat-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 700;
    color: #64748b;
}
.monitor-summary-card .stat-value {
    font-size: 24px;
    font-weight: 800;
    line-height: 1.15;
    color: #0f172a;
    letter-spacing: -0.02em;
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
}
.monitor-summary-card .card-hint {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 6px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
}

/* Filter Sections */
.filter-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 16px 20px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}
.filter-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #64748b;
    margin-bottom: 5px;
}
.preset-pills { display: flex; flex-wrap: wrap; gap: 6px; }
.preset-pill {
    padding: 4px 12px;
    border-radius: 9999px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    text-decoration: none;
    transition: all .15s ease;
}
.preset-pill:hover {
    background: #e2e8f0;
    color: #0f172a;
    border-color: #cbd5e1;
}
.preset-pill.active {
    background: #0d9488;
    color: #ffffff;
    border-color: #0d9488;
    box-shadow: 0 2px 6px rgba(13, 148, 136, 0.3);
}

.avatar-circle-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 11.5px;
    color: #ffffff;
    flex-shrink: 0;
}
</style>
@endpush

@section('content')
@php
    $presets = [
        'today'       => 'Today',
        'yesterday'   => 'Yesterday',
        'last_7_days' => 'Last 7 Days',
        'last_30_days'=> 'Last 30 Days',
        'this_month'  => 'This Month',
        'last_month'  => 'Last Month',
        'all'         => 'All Time',
    ];
    $activePreset = $filters['date_preset'] ?? 'today';
    $currStatus   = $filters['status'] ?? '';
    $currType     = $filters['notification_type'] ?? '';
    $currChannel  = $filters['channel'] ?? '';

    $avatarBg = function($name) {
        $colors = ['#6366f1', '#0d9488', '#0ea5e9', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6'];
        $hash = crc32($name ?? 'User');
        return $colors[abs($hash) % count($colors)];
    };
    $initials = function($name) {
        $parts = explode(' ', trim($name ?? 'U'));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        }
        return strtoupper(substr($name ?? 'U', 0, 2));
    };

    $buildUrl = function(array $params) {
        return route('admin.notifications.monitoring', array_merge(request()->except(['page']), $params));
    };
@endphp

<div class="space-y-4">

    {{-- ── PAGE HEADER ── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(13,148,136,0.12); color: #0d9488; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    <i class="bi bi-activity"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0" style="color: #0f172a; font-size: 1.25rem;">Notification Monitoring Dashboard</h4>
                    <p class="text-muted mb-0" style="font-size: 0.82rem;">Complete delivery status tracking, failure analytics & logs across all notification channels</p>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.notifications.monitoring.export', request()->except(['page'])) }}"
               class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1.5 px-3 py-2 fw-semibold" style="border-radius: 10px;">
                <i class="bi bi-file-earmark-arrow-down"></i> Export CSV
            </a>
        </div>
    </div>

    {{-- ── 6 SUMMARY METRIC CARDS ── --}}
    <div class="row g-3">
        {{-- 1. Total Notifications --}}
        <div class="col-6 col-md-2">
            <a href="{{ $buildUrl(['status'=>'']) }}#notification-table"
               class="monitor-summary-card mcard-teal {{ empty($currStatus) ? 'active-filter' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Total Notifications</div>
                        <div class="stat-value text-teal-600" style="color: #0d9488;">{{ number_format($summary['total']) }}</div>
                    </div>
                    <div class="icon-box" style="background: rgba(13,148,136,0.1); color: #0d9488;">
                        <i class="bi bi-bell-fill"></i>
                    </div>
                </div>
                <div class="card-hint">
                    <span>Show all records</span> <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        {{-- 2. Successfully Sent --}}
        <div class="col-6 col-md-2">
            <a href="{{ $buildUrl(['status'=>'sent']) }}#notification-table"
               class="monitor-summary-card mcard-emerald {{ $currStatus==='sent' ? 'active-filter' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Sent / Delivered</div>
                        <div class="stat-value text-emerald-600" style="color: #10b981;">{{ number_format($summary['sent']) }}</div>
                    </div>
                    <div class="icon-box" style="background: rgba(16,185,129,0.1); color: #10b981;">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                </div>
                <div class="card-hint">
                    <span class="text-success fw-bold">{{ $summary['success_rate'] }}% Success</span>
                    <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        {{-- 3. Failed --}}
        <div class="col-6 col-md-2">
            <a href="{{ $buildUrl(['status'=>'failed']) }}#notification-table"
               class="monitor-summary-card mcard-rose {{ $currStatus==='failed' ? 'active-filter' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Failed</div>
                        <div class="stat-value text-rose-600" style="color: #e11d48;">{{ number_format($summary['failed']) }}</div>
                    </div>
                    <div class="icon-box" style="background: rgba(244,63,94,0.1); color: #f43f5e;">
                        <i class="bi bi-exclamation-octagon-fill"></i>
                    </div>
                </div>
                <div class="card-hint">
                    <span class="text-danger fw-bold">{{ $summary['failure_rate'] }}% Failure</span>
                    <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        {{-- 4. Pending --}}
        <div class="col-6 col-md-2">
            <a href="{{ $buildUrl(['status'=>'pending']) }}#notification-table"
               class="monitor-summary-card mcard-amber {{ $currStatus==='pending' ? 'active-filter' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Pending / Queue</div>
                        <div class="stat-value" style="color: #d97706;">{{ number_format($summary['pending']) }}</div>
                    </div>
                    <div class="icon-box" style="background: rgba(245,158,11,0.1); color: #f59e0b;">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
                <div class="card-hint">
                    <span>Awaiting worker</span> <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        {{-- 5. Partial / In Flight --}}
        <div class="col-6 col-md-2">
            <a href="{{ $buildUrl(['status'=>'partial']) }}#notification-table"
               class="monitor-summary-card mcard-sky {{ $currStatus==='partial' ? 'active-filter' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Partial Delivery</div>
                        <div class="stat-value" style="color: #0284c7;">{{ number_format($summary['partial']) }}</div>
                    </div>
                    <div class="icon-box" style="background: rgba(14,165,233,0.1); color: #0ea5e9;">
                        <i class="bi bi-pie-chart-fill"></i>
                    </div>
                </div>
                <div class="card-hint">
                    <span>1 channel passed</span> <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        {{-- 6. Read / Opened --}}
        <div class="col-6 col-md-2">
            <div class="monitor-summary-card mcard-indigo">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Read / Clicked</div>
                        <div class="stat-value" style="color: #4f46e5;">{{ number_format($summary['read_count']) }}</div>
                    </div>
                    <div class="icon-box" style="background: rgba(99,102,241,0.1); color: #6366f1;">
                        <i class="bi bi-envelope-open-fill"></i>
                    </div>
                </div>
                <div class="card-hint">
                    <span>{{ number_format($summary['clicked_count']) }} tapped open</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── ADVANCED FILTERS SECTION ── --}}
    <div class="filter-card">
        <form method="GET" action="{{ route('admin.notifications.monitoring') }}" id="filterForm">
            {{-- Quick Date Range Presets --}}
            <div class="mb-3">
                <div class="filter-label mb-2"><i class="bi bi-calendar-event me-1"></i> Quick Date Filter (Default: Today):</div>
                <div class="preset-pills">
                    @foreach($presets as $key => $label)
                        <a href="{{ route('admin.notifications.monitoring', array_merge(request()->except(['date_preset','from_date','to_date','page']), ['date_preset'=>$key])) }}"
                           class="preset-pill {{ $activePreset===$key ? 'active' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <input type="hidden" name="date_preset" value="{{ $activePreset }}">
            </div>

            {{-- Multi-filter Toolbar --}}
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-2">
                    <label class="filter-label">From Date</label>
                    <input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" class="form-control form-control-sm" style="border-radius: 8px;">
                </div>
                <div class="col-6 col-md-2">
                    <label class="filter-label">To Date</label>
                    <input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" class="form-control form-control-sm" style="border-radius: 8px;">
                </div>
                <div class="col-6 col-md-2">
                    <label class="filter-label">Status</label>
                    <select name="status" class="form-select form-select-sm" style="border-radius: 8px;">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $stKey => $stLabel)
                            <option value="{{ $stKey }}" {{ $currStatus===$stKey ? 'selected' : '' }}>{{ $stLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="filter-label">Notification Type</label>
                    <select name="notification_type" class="form-select form-select-sm" style="border-radius: 8px;">
                        <option value="">All Notification Types ({{ count($notificationTypes) }})</option>
                        @foreach($notificationTypes as $typeKey => $typeName)
                            <option value="{{ $typeKey }}" {{ $currType===$typeKey ? 'selected' : '' }}>{{ $typeName }} ({{ $typeKey }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="filter-label">Channel</label>
                    <select name="channel" class="form-select form-select-sm" style="border-radius: 8px;">
                        <option value="">All Channels</option>
                        @foreach($channels as $chKey => $chLabel)
                            <option value="{{ $chKey }}" {{ $currChannel===$chKey ? 'selected' : '' }}>{{ $chLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                        <input type="text" name="user_query" value="{{ $filters['user_query'] ?? '' }}" class="form-control" placeholder="Search recipient (name, email, phone)…" style="border-radius: 0 8px 8px 0;">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Search title, message or error…" style="border-radius: 0 8px 8px 0;">
                    </div>
                </div>
                <div class="col-12 col-md-4 d-flex align-items-center gap-2">
                    <button type="submit" class="btn btn-sm btn-primary px-4 fw-semibold shadow-xs" style="background: #0d9488; border-color: #0d9488; border-radius: 8px;">
                        <i class="bi bi-funnel me-1"></i> Apply Filters
                    </button>
                    <a href="{{ route('admin.notifications.monitoring', ['date_preset'=>'today']) }}" class="btn btn-sm btn-light border px-3 fw-semibold text-muted" style="border-radius: 8px;" title="Reset filters to Today">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- ── NOTIFICATION RECORDS TABLE ── --}}
    <div class="card border shadow-sm" style="border-radius: 16px; overflow: hidden;" id="notification-table">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-dark" style="font-size: 0.95rem;">Notification Logs & Delivery History</span>
                <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 11px;">{{ number_format($total) }} Records</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <select onchange="window.location=this.value" class="form-select form-select-sm" style="width: 110px; border-radius: 8px;">
                    @foreach([10,20,50,100] as $pp)
                    <option value="{{ route('admin.notifications.monitoring', array_merge(request()->except(['per_page','page']),['per_page'=>$pp])) }}"
                        {{ ($filters['per_page']??20)==$pp ? 'selected' : '' }}>{{ $pp }} / page</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 12.5px;">
                <thead class="table-light">
                    <tr class="text-uppercase text-muted" style="font-size: 10.5px; letter-spacing: 0.05em;">
                        <th class="ps-4 py-3">Date & Time</th>
                        <th class="py-3">Recipient</th>
                        <th class="py-3">Notification Type</th>
                        <th class="text-center py-3">Channel</th>
                        <th class="py-3" style="max-width: 280px;">Title & Message</th>
                        <th class="text-center py-3">Status</th>
                        <th class="text-end pe-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($items as $item)
                    @php
                        $user = $item->user;
                        $userName = $user
                            ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->display_name ?? $user->email ?? '—')
                            : '—';
                        $at = $item->created_at ? \Carbon\Carbon::parse($item->created_at) : null;
                        $status = strtolower((string) ($item->status ?? 'sent'));
                    @endphp
                    <tr>
                        <td class="ps-4 py-3 whitespace-nowrap">
                            <div class="fw-semibold text-dark">{{ $at ? $at->format('M d, Y') : '—' }}</div>
                            <div class="text-muted small" style="font-size: 11px;"><i class="bi bi-clock me-1"></i>{{ $at ? $at->format('h:i:s A') : '' }}</div>
                        </td>
                        <td class="py-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle-sm" style="background: {{ $avatarBg($userName) }};">
                                    {{ $initials($userName) }}
                                </div>
                                <div class="truncate" style="max-width: 180px;">
                                    <div class="fw-bold text-dark">{{ $userName }}</div>
                                    <div class="text-muted small" style="font-size: 11px;">{{ $user?->email ?? $user?->phone ?? '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3">
                            <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 11px;">
                                {{ $notificationTypes[$item->type] ?? ucwords(str_replace('_', ' ', (string) $item->type)) }}
                            </span>
                            @if(!empty($item->category) && $item->category !== $item->type)
                                <div class="text-muted small mt-0.5" style="font-size: 10.5px;">{{ $item->category }}</div>
                            @endif
                        </td>
                        <td class="py-3 text-center">
                            @if($item->channel === 'push')
                                <span class="badge bg-indigo-subtle text-indigo-700 border border-indigo-subtle px-2 py-1 rounded-pill" style="font-size: 11px;">
                                    <i class="bi bi-phone me-1"></i>Push
                                </span>
                            @elseif($item->channel === 'email')
                                <span class="badge bg-sky-subtle text-sky-700 border border-sky-subtle px-2 py-1 rounded-pill" style="font-size: 11px;">
                                    <i class="bi bi-envelope me-1"></i>Email
                                </span>
                            @elseif($item->channel === 'push_email')
                                <span class="badge bg-purple-subtle text-purple-700 border border-purple-subtle px-2 py-1 rounded-pill" style="font-size: 11px;">
                                    <i class="bi bi-broadcast me-1"></i>Push+Email
                                </span>
                            @elseif($item->channel === 'whatsapp')
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill" style="font-size: 11px;">
                                    <i class="bi bi-whatsapp me-1"></i>WhatsApp
                                </span>
                            @else
                                <span class="badge bg-light text-muted border px-2 py-1 rounded-pill" style="font-size: 11px;">
                                    <i class="bi bi-inbox me-1"></i>In-App
                                </span>
                            @endif
                        </td>
                        <td class="py-3" style="max-width: 280px;">
                            <div class="fw-semibold text-dark truncate" title="{{ $item->title }}">{{ $item->title ?? '—' }}</div>
                            <div class="text-muted small truncate" title="{{ $item->body ?? $item->message }}" style="font-size: 11px;">
                                {{ $item->body ?? $item->message ?? '—' }}
                            </div>
                        </td>
                        <td class="py-3 text-center">
                            @if($status === 'sent')
                                <span class="badge px-2.5 py-1 rounded-pill" style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; font-weight: 600;">
                                    <i class="bi bi-check-circle-fill me-1"></i>Sent
                                </span>
                            @elseif($status === 'failed')
                                <span class="badge px-2.5 py-1 rounded-pill" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; font-weight: 600;" title="{{ $item->failure_reason }}">
                                    <i class="bi bi-x-circle-fill me-1"></i>Failed
                                </span>
                            @elseif($status === 'pending')
                                <span class="badge px-2.5 py-1 rounded-pill" style="background: #fffbeb; color: #d97706; border: 1px solid #fde68a; font-weight: 600;">
                                    <i class="bi bi-hourglass-split me-1"></i>Pending
                                </span>
                            @elseif($status === 'partial')
                                <span class="badge px-2.5 py-1 rounded-pill" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-weight: 600;">
                                    <i class="bi bi-pie-chart me-1"></i>Partial
                                </span>
                            @else
                                <span class="badge px-2.5 py-1 rounded-pill bg-light text-muted border" style="font-weight: 600;">
                                    {{ strtoupper($status) }}
                                </span>
                            @endif
                        </td>
                        <td class="text-end pe-4 py-3 whitespace-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2.5 rounded-pill d-inline-flex align-items-center gap-1 fw-semibold"
                                    onclick="openNotificationDetails('{{ $item->id }}')" style="font-size: 11.5px;">
                                <i class="bi bi-eye"></i> View Details
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <div class="py-3">
                                <i class="bi bi-bell-slash fs-1 d-block mb-2 text-muted opacity-50"></i>
                                <div class="fw-semibold">No notification records found</div>
                                <div class="small">Try selecting another date range or adjusting your search filters.</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-top p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted small">Showing {{ $items->firstItem() ?? 0 }}–{{ $items->lastItem() ?? 0 }} of {{ number_format($total) }} total records</span>
            <div>{{ $items->links() }}</div>
        </div>
    </div>

</div>

{{-- ── NOTIFICATION DETAILS MODAL ── --}}
<div class="modal fade" id="notificationDetailModal" tabindex="-1" aria-labelledby="notificationDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0" style="border-radius: 20px; overflow: hidden;">
            {{-- Header --}}
            <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);">
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 34px; height: 34px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 18px;">
                        <i class="bi bi-bell-fill"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="notificationDetailModalLabel" style="font-size: 1.05rem;">Notification Details</h5>
                        <div class="small text-white-50" id="modalNotificationId">ID: —</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Body --}}
            <div class="modal-body p-4" style="background: #f8fafc;">
                {{-- Loading Spinner --}}
                <div id="modalLoading" class="text-center py-5">
                    <div class="spinner-border text-teal-600 mb-2" role="status" style="color: #0d9488;"></div>
                    <div class="text-muted small">Fetching notification payload & delivery logs…</div>
                </div>

                {{-- Content Container --}}
                <div id="modalContent" class="d-none">
                    {{-- Recipient & Status Strip --}}
                    <div class="card border-0 shadow-sm mb-3" style="border-radius: 14px; background: #ffffff;">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="avatar-circle-sm" id="modalUserAvatar" style="background: #6366f1; width: 38px; height: 38px; font-size: 14px;">U</div>
                                    <div>
                                        <div class="fw-bold text-dark" id="modalUserName">User Name</div>
                                        <div class="text-muted small" id="modalUserMeta" style="font-size: 11px;">email • phone</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2" id="modalStatusBadges">
                                    <!-- Badges injected here -->
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Payload Details --}}
                    <div class="card border shadow-sm mb-3" style="border-radius: 14px; background: #ffffff;">
                        <div class="card-header bg-light border-bottom py-2 px-3 fw-bold small text-dark">
                            <i class="bi bi-chat-left-text me-1 text-teal-600" style="color: #0d9488;"></i> Message Content & Destination
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-2">
                                <div class="text-muted small fw-bold text-uppercase" style="font-size: 10.5px;">Title</div>
                                <div class="fw-bold text-dark" id="modalTitle" style="font-size: 14px;">Title text</div>
                            </div>
                            <div class="mb-2">
                                <div class="text-muted small fw-bold text-uppercase" style="font-size: 10.5px;">Body Message</div>
                                <div class="text-dark p-2.5 bg-light rounded-3" id="modalBody" style="font-size: 13px; white-space: pre-wrap;">Body text</div>
                            </div>
                            <div class="row g-2 pt-2 border-top">
                                <div class="col-6">
                                    <div class="text-muted small" style="font-size: 11px;"><strong>Screen / Tap Target:</strong> <code id="modalScreen">/home</code></div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted small" style="font-size: 11px;"><strong>Channel:</strong> <span id="modalChannel">push</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Failure Section (if failed) --}}
                    <div class="alert alert-danger border-0 shadow-sm d-none mb-3" id="modalFailureAlert" style="border-radius: 14px; background: #fef2f2; color: #991b1b;">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-exclamation-triangle-fill fs-5 text-danger flex-shrink-0 mt-0.5"></i>
                            <div>
                                <strong class="d-block mb-1">Delivery Failure Reason</strong>
                                <div id="modalFailureText" style="font-size: 12.5px; word-break: break-word;">Error explanation</div>
                            </div>
                        </div>
                    </div>

                    {{-- Status Timeline --}}
                    <div class="card border shadow-sm mb-3" style="border-radius: 14px; background: #ffffff;">
                        <div class="card-header bg-light border-bottom py-2 px-3 fw-bold small text-dark">
                            <i class="bi bi-clock-history me-1 text-primary"></i> Lifecycle Timestamps
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-2 text-muted small" style="font-size: 11.5px;">
                                <div class="col-6 col-md-3"><strong>Created:</strong> <div id="modalCreatedAt" class="text-dark">—</div></div>
                                <div class="col-6 col-md-3"><strong>Sent:</strong> <div id="modalSentAt" class="text-dark">—</div></div>
                                <div class="col-6 col-md-3"><strong>Read:</strong> <div id="modalReadAt" class="text-dark">—</div></div>
                                <div class="col-6 col-md-3"><strong>Clicked / Opened:</strong> <div id="modalClickedAt" class="text-dark">—</div></div>
                            </div>
                        </div>
                    </div>

                    {{-- Delivery Logs Section --}}
                    <div class="card border shadow-sm mb-0" style="border-radius: 14px; background: #ffffff;" id="modalDeliveryLogsCard">
                        <div class="card-header bg-light border-bottom py-2 px-3 d-flex justify-content-between align-items-center">
                            <span class="fw-bold small text-dark"><i class="bi bi-broadcast me-1 text-teal-600" style="color: #0d9488;"></i> Provider Delivery Attempts</span>
                            <span class="badge bg-light text-dark border" id="modalDeliveryLogsCount">0 Logs</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0" style="font-size: 11.5px;">
                                    <thead class="table-light">
                                        <tr class="text-uppercase text-muted" style="font-size: 10px;">
                                            <th class="ps-3 py-2">Provider</th>
                                            <th class="py-2">Status</th>
                                            <th class="py-2">Attempted At</th>
                                            <th class="py-2">Message ID / Info</th>
                                        </tr>
                                    </thead>
                                    <tbody id="modalDeliveryLogsTbody">
                                        <!-- Injected rows -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Footer --}}
            <div class="modal-footer bg-white px-4 py-3 d-flex justify-content-end">
                <button type="button" class="btn btn-sm btn-secondary px-4 fw-semibold" data-bs-dismiss="modal" style="border-radius: 8px;">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openNotificationDetails(id) {
    const modalEl = document.getElementById('notificationDetailModal');
    if (!modalEl) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    const loader = document.getElementById('modalLoading');
    const content = document.getElementById('modalContent');
    const idLabel = document.getElementById('modalNotificationId');

    if (loader) loader.classList.remove('d-none');
    if (content) content.classList.add('d-none');
    if (idLabel) idLabel.textContent = 'ID: ' + id;

    fetch('/admin/notifications/monitoring/' + encodeURIComponent(id) + '/details', {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        }
    })
    .then(response => {
        if (!response.ok) throw new Error('Failed to retrieve notification details');
        return response.json();
    })
    .then(json => {
        if (loader) loader.classList.add('d-none');
        if (!json.success || !json.data) return;

        const d = json.data;
        if (content) content.classList.remove('d-none');

        // User info
        const u = d.user || {};
        document.getElementById('modalUserName').textContent = u.name || '—';
        document.getElementById('modalUserMeta').textContent = (u.city ? u.city + ' • ' : '') + (u.email || '') + (u.phone ? ' • ' + u.phone : '');
        document.getElementById('modalUserAvatar').textContent = u.name ? u.name.substring(0, 2).toUpperCase() : 'U';

        // Badges
        const badgesContainer = document.getElementById('modalStatusBadges');
        let statusHtml = '';
        if (d.status === 'sent') {
            statusHtml += '<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-check-circle me-1"></i>Sent</span>';
        } else if (d.status === 'failed') {
            statusHtml += '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-x-circle me-1"></i>Failed</span>';
        } else {
            statusHtml += `<span class="badge bg-light text-muted border px-2.5 py-1 rounded-pill">${(d.status || '').toUpperCase()}</span>`;
        }
        statusHtml += `<span class="badge bg-light text-dark border px-2 py-1 rounded-pill">${(d.channel || 'push').toUpperCase()}</span>`;
        if (badgesContainer) badgesContainer.innerHTML = statusHtml;

        // Content
        document.getElementById('modalTitle').textContent = d.title || '—';
        document.getElementById('modalBody').textContent = d.body || '—';
        document.getElementById('modalScreen').textContent = d.screen || '/home';
        document.getElementById('modalChannel').textContent = (d.channel || 'push');

        // Failure alert
        const failAlert = document.getElementById('modalFailureAlert');
        const failText = document.getElementById('modalFailureText');
        if (d.status === 'failed' || d.failure_reason) {
            if (failAlert) failAlert.classList.remove('d-none');
            if (failText) failText.textContent = d.failure_reason || 'Unknown delivery failure';
        } else {
            if (failAlert) failAlert.classList.add('d-none');
        }

        // Timestamps
        document.getElementById('modalCreatedAt').textContent = d.created_at || '—';
        document.getElementById('modalSentAt').textContent = d.sent_at || '—';
        document.getElementById('modalReadAt').textContent = d.read_at || '—';
        document.getElementById('modalClickedAt').textContent = d.clicked_at || '—';

        // Delivery Logs
        const logs = d.delivery_logs || [];
        const logsCount = document.getElementById('modalDeliveryLogsCount');
        if (logsCount) logsCount.textContent = logs.length + ' Logs';

        const tbody = document.getElementById('modalDeliveryLogsTbody');
        if (tbody) {
            tbody.innerHTML = '';
            if (logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">No provider delivery attempts logged.</td></tr>';
            } else {
                logs.forEach(log => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="ps-3 py-2 fw-semibold">${log.provider || log.channel}</td>
                        <td class="py-2"><span class="badge ${log.status==='sent'?'bg-success':'bg-danger'}">${(log.status||'').toUpperCase()}</span></td>
                        <td class="py-2 text-muted">${log.attempted_at || '—'}</td>
                        <td class="py-2 text-muted truncate" style="max-width: 200px;">${log.provider_message_id || log.error_message || '—'}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }
        }
    })
    .catch(err => {
        console.error('Error fetching notification details:', err);
        if (loader) loader.classList.add('d-none');
        if (content) {
            content.classList.remove('d-none');
            content.innerHTML = `<div class="alert alert-danger my-3 text-center"><i class="bi bi-exclamation-triangle me-1"></i> Could not load notification details. Please try again.</div>`;
        }
    });
}
</script>
@endpush
