@extends('admin.layouts.app')

@section('title', 'Connection Analytics')

@include('admin.partials.grid-head')

@push('styles')
<style>
.analytics-summary-card {
    background: var(--surface);
    border: 1px solid var(--border-subtle);
    border-radius: 14px;
    padding: 16px 18px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    cursor: pointer;
    text-decoration: none !important;
    display: block;
    color: inherit;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.analytics-summary-card:hover {
    transform: translateY(-2px);
    border-color: #6366f1;
    box-shadow: 0 10px 24px -4px rgba(99, 102, 241, 0.16);
}
.analytics-summary-card.active-filter {
    border-color: #6366f1;
    background: linear-gradient(135deg, rgba(99,102,241,0.06), rgba(99,102,241,0.01));
    box-shadow: 0 0 0 2px rgba(99,102,241,0.4);
}
.analytics-summary-card .icon-box {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 19px;
    flex-shrink: 0;
    transition: transform 0.2s;
}
.analytics-summary-card:hover .icon-box {
    transform: scale(1.08);
}
.analytics-summary-card .stat-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 600;
    color: var(--text-3);
}
.analytics-summary-card .stat-value {
    font-size: 24px;
    font-weight: 700;
    line-height: 1.2;
    color: var(--text-1);
    margin-top: 4px;
}
.analytics-summary-card .card-hint {
    font-size: 10.5px;
    color: var(--text-3);
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 3px;
    opacity: 0.75;
    font-weight: 500;
}
.analytics-summary-card:hover .card-hint {
    color: #6366f1;
    opacity: 1;
}
.filter-section { background: var(--surface); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 14px 16px; }
.filter-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--text-3); margin-bottom: 4px; }
.preset-pills { display: flex; flex-wrap: wrap; gap: 5px; }
.preset-pill {
    padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; cursor: pointer;
    border: 1px solid var(--border-subtle); background: var(--surface); color: var(--text-2);
    text-decoration: none; transition: all .15s;
}
.preset-pill:hover, .preset-pill.active {
    background: var(--primary); color: #fff; border-color: var(--primary);
}
.chart-card { background: var(--surface); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 16px; }
.top-member-badge { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 10px; }
</style>
@endpush

@section('content')
@php
    $fmt = fn($v) => $v ? \Carbon\Carbon::parse($v)->format('Y-m-d H:i') : '—';
    $fmtDate = fn($v) => $v ? \Carbon\Carbon::parse($v)->format('Y-m-d') : '—';
    $fmtTime = fn($v) => $v ? \Carbon\Carbon::parse($v)->format('H:i:s') : '—';
    $displayName = fn(?string $d, ?string $f, ?string $l): string =>
        (trim(($f??'').' '.($l??'')) !== '') ? trim(($f??'').' '.($l??'')) : ($d ?? '—');
    $presets = [
        'today'       => 'Today',        'yesterday'   => 'Yesterday',
        'this_week'   => 'This Week',    'last_week'   => 'Last Week',
        'this_month'  => 'This Month',   'last_month'  => 'Last Month',
        'this_quarter'=> 'This Quarter', 'last_quarter'=> 'Last Quarter',
        'this_year'   => 'This Year',    'last_year'   => 'Last Year',
        'last_7_days' => 'Last 7 Days',  'last_30_days'=> 'Last 30 Days',
        'last_90_days'=> 'Last 90 Days', 'all_time'    => 'All Time',
    ];
    $activePreset = $filters['date_preset'] ?? '';
    $activeStatus = $filters['status'] ?? '';
@endphp

<div class="space-y-4">

    {{-- ── PAGE HEADER ── --}}
    <div class="flex flex-wrap justify-between items-start gap-3">
        <div>
            <h2 class="font-display font-bold text-sm text-indigo-500 uppercase tracking-wider m-0">Connection Analytics</h2>
            <p class="text-xs text-muted m-0 mt-1">Complete visibility into connection request activity across the platform</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.activities.connections.export', request()->except(['page'])) }}"
               class="btn btn-sm btn-outline-primary text-xs">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
        </div>
    </div>

    {{-- ── FILTERS FORM ── --}}
    <form id="connFiltersForm" method="GET" action="{{ route('admin.activities.connections.index') }}" class="space-y-3">

        {{-- Date Presets --}}
        <div class="filter-section">
            <div class="filter-label mb-2">Date Period</div>
            <div class="preset-pills">
                <a href="{{ route('admin.activities.connections.index', array_merge(request()->except(['date_preset','from','to','page']), ['date_preset'=>''])) }}"
                   class="preset-pill {{ $activePreset === '' ? 'active' : '' }}">All Time</a>
                @foreach($presets as $key => $label)
                    @if($key !== 'all_time')
                    <a href="{{ route('admin.activities.connections.index', array_merge(request()->except(['date_preset','from','to','page']), ['date_preset'=>$key])) }}"
                       class="preset-pill {{ $activePreset === $key ? 'active' : '' }}">{{ $label }}</a>
                    @endif
                @endforeach
            </div>
            {{-- Custom Date Range --}}
            <div class="flex gap-2 mt-2 flex-wrap items-end">
                <div>
                    <div class="filter-label">From</div>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                           class="form-control form-control-sm text-xs" style="width:150px"
                           onchange="document.getElementById('connFiltersForm').submit()">
                </div>
                <div>
                    <div class="filter-label">To</div>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                           class="form-control form-control-sm text-xs" style="width:150px"
                           onchange="document.getElementById('connFiltersForm').submit()">
                </div>
                <input type="hidden" name="date_preset" value="{{ $filters['date_preset'] ?? '' }}">
                <a href="{{ route('admin.activities.connections.index') }}" class="btn btn-sm btn-outline-secondary text-xs">
                    <i class="bi bi-x-circle me-1"></i>Clear Filters
                </a>
            </div>
        </div>

        {{-- Advanced Filters --}}
        <div class="filter-section">
            <div class="filter-label mb-2">Advanced Filters</div>
            <div class="row g-2">
                <div class="col-md-2">
                    <label class="filter-label">Status</label>
                    <select name="status" class="form-select form-select-sm text-xs" onchange="this.form.submit()">
                        <option value="" @selected($activeStatus === '')>Any Status</option>
                        <option value="accepted" @selected($activeStatus === 'accepted')>Accepted</option>
                        <option value="pending" @selected($activeStatus === 'pending')>Pending</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Sender Name</label>
                    <input type="text" name="sender_name" value="{{ $filters['sender_name'] ?? '' }}"
                           class="form-control form-control-sm text-xs" placeholder="Sender name">
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Sender Email</label>
                    <input type="text" name="sender_email" value="{{ $filters['sender_email'] ?? '' }}"
                           class="form-control form-control-sm text-xs" placeholder="Sender email">
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Sender City</label>
                    <input type="text" name="sender_city" value="{{ $filters['sender_city'] ?? '' }}"
                           class="form-control form-control-sm text-xs" placeholder="City">
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Sender Membership</label>
                    <input type="text" name="sender_membership" value="{{ $filters['sender_membership'] ?? '' }}"
                           class="form-control form-control-sm text-xs" placeholder="e.g. active">
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Circle</label>
                    <select name="circle_id" class="form-select form-select-sm text-xs" onchange="this.form.submit()">
                        <option value="">All Circles</option>
                        @foreach($circles as $circle)
                            <option value="{{ $circle->id }}" @selected(($filters['circle_id']??'') === (string)$circle->id)>{{ $circle->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Receiver Name</label>
                    <input type="text" name="receiver_name" value="{{ $filters['receiver_name'] ?? '' }}"
                           class="form-control form-control-sm text-xs" placeholder="Receiver name">
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Receiver Email</label>
                    <input type="text" name="receiver_email" value="{{ $filters['receiver_email'] ?? '' }}"
                           class="form-control form-control-sm text-xs" placeholder="Receiver email">
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Receiver City</label>
                    <input type="text" name="receiver_city" value="{{ $filters['receiver_city'] ?? '' }}"
                           class="form-control form-control-sm text-xs" placeholder="City">
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Receiver Membership</label>
                    <input type="text" name="receiver_membership" value="{{ $filters['receiver_membership'] ?? '' }}"
                           class="form-control form-control-sm text-xs" placeholder="e.g. active">
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Global Search</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                           class="form-control form-control-sm text-xs" placeholder="Name or email...">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary text-xs w-100">
                        <i class="bi bi-funnel me-1"></i>Apply Filters
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- ── SUMMARY CARDS ── --}}
    @php
        $currStatus = request('status', '');
        $cards = [
            [
                'label'      => 'Total Requests',
                'value'      => number_format($summary['total_requests']),
                'icon'       => 'bi-send-fill',
                'color_code' => '#6366f1',
                'bg'         => 'rgba(99, 102, 241, 0.1)',
                'hint'       => 'Show all records',
                'url'        => route('admin.activities.connections.index', array_merge(request()->except(['status','page']), ['status'=>''])).'#records-table',
                'is_active'  => $currStatus === '',
            ],
            [
                'label'      => 'Accepted',
                'value'      => number_format($summary['total_accepted']),
                'icon'       => 'bi-check-circle-fill',
                'color_code' => '#10b981',
                'bg'         => 'rgba(16, 185, 129, 0.1)',
                'hint'       => 'Filter accepted',
                'url'        => route('admin.activities.connections.index', array_merge(request()->except(['status','page']), ['status'=>'accepted'])).'#records-table',
                'is_active'  => $currStatus === 'accepted',
            ],
            [
                'label'      => 'Pending',
                'value'      => number_format($summary['total_pending']),
                'icon'       => 'bi-hourglass-split',
                'color_code' => '#f59e0b',
                'bg'         => 'rgba(245, 158, 11, 0.1)',
                'hint'       => 'Filter pending',
                'url'        => route('admin.activities.connections.index', array_merge(request()->except(['status','page']), ['status'=>'pending'])).'#records-table',
                'is_active'  => $currStatus === 'pending',
            ],
            [
                'label'      => 'Acceptance Rate',
                'value'      => $summary['acceptance_rate'].'%',
                'icon'       => 'bi-graph-up-arrow',
                'color_code' => '#0ea5e9',
                'bg'         => 'rgba(14, 165, 233, 0.1)',
                'hint'       => 'View trends',
                'url'        => '#trends-section',
                'is_active'  => false,
            ],
            [
                'label'      => 'Unique Senders',
                'value'      => number_format($summary['unique_senders']),
                'icon'       => 'bi-person-plus-fill',
                'color_code' => '#8b5cf6',
                'bg'         => 'rgba(139, 92, 246, 0.1)',
                'hint'       => 'Top senders',
                'url'        => '#top-senders',
                'is_active'  => false,
            ],
            [
                'label'      => 'Unique Receivers',
                'value'      => number_format($summary['unique_receivers']),
                'icon'       => 'bi-person-check-fill',
                'color_code' => '#ec4899',
                'bg'         => 'rgba(236, 72, 153, 0.1)',
                'hint'       => 'Top receivers',
                'url'        => '#top-receivers',
                'is_active'  => false,
            ],
            [
                'label'      => 'Avg / Member',
                'value'      => $summary['avg_per_member'],
                'icon'       => 'bi-people-fill',
                'color_code' => '#06b6d4',
                'bg'         => 'rgba(6, 182, 212, 0.1)',
                'hint'       => 'Most connected',
                'url'        => '#most-accepted',
                'is_active'  => false,
            ],
            [
                'label'      => 'Avg / Day',
                'value'      => $summary['avg_per_day'],
                'icon'       => 'bi-calendar2-day-fill',
                'color_code' => '#f97316',
                'bg'         => 'rgba(249, 115, 22, 0.1)',
                'hint'       => 'View trends',
                'url'        => '#trends-section',
                'is_active'  => false,
            ],
        ];
    @endphp
    <div class="row g-3">
        @foreach($cards as $card)
        <div class="col-6 col-md-3 col-xl-3">
            <a href="{{ $card['url'] }}" class="analytics-summary-card {{ $card['is_active'] ? 'active-filter' : '' }}">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="stat-label">{{ $card['label'] }}</div>
                        <div class="stat-value">{{ $card['value'] }}</div>
                        <div class="card-hint">
                            <span>{{ $card['hint'] }}</span>
                            <i class="bi bi-arrow-right-short"></i>
                        </div>
                    </div>
                    <div class="icon-box" style="background: {{ $card['bg'] }}; color: {{ $card['color_code'] }};">
                        <i class="bi {{ $card['icon'] }}"></i>
                    </div>
                </div>
            </a>
        </div>
        @endforeach
    </div>

    {{-- ── TRENDS CHART ── --}}
    <div class="chart-card" id="trends-section">
        <div class="flex justify-between items-center mb-2">
            <div class="flex items-center gap-2">
                <i class="bi bi-graph-up text-indigo-500"></i>
                <span class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider">Connection Request Trends</span>
            </div>
            <span class="text-xs text-muted">{{ count($trends['labels']) }} days shown</span>
        </div>
        <div style="position: relative; height: 160px; width: 100%;">
            <canvas id="connectionTrendsChart"></canvas>
        </div>
    </div>

    {{-- ── TOP ACTIVITY REPORTS ── --}}
    <div class="row g-3">
        {{-- Top Senders --}}
        <div class="col-md-4" id="top-senders">
            <div class="rounded-xl border bs surface overflow-hidden">
                <div class="px-3 py-2 surface-2 border-b bs flex items-center justify-between">
                    <span class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider">Top Senders</span>
                    <i class="bi bi-send text-indigo-400 text-xs"></i>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-[12px]">
                        <thead>
                            <tr class="surface-2 border-b bs text-[10px] uppercase tracking-wider t3 font-semibold">
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Member</th>
                                <th class="px-3 py-2 text-right">Sent</th>
                                <th class="px-3 py-2 text-right">Accepted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/40">
                            @forelse($topSenders as $i => $m)
                            <tr class="hover:surface-2 transition">
                                <td class="px-3 py-2 text-xs font-bold t3">#{{ $i+1 }}</td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-xs t1 truncate" style="max-width:140px">{{ $m->member_name }}</div>
                                    <div class="text-[10px] t3 truncate">{{ $m->member_city ?? '' }}</div>
                                </td>
                                <td class="px-3 py-2 text-right font-bold text-indigo-600">{{ $m->total_sent }}</td>
                                <td class="px-3 py-2 text-right font-semibold text-emerald-600">{{ $m->total_accepted }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="px-3 py-4 text-center text-xs t3">No data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Top Receivers --}}
        <div class="col-md-4" id="top-receivers">
            <div class="rounded-xl border bs surface overflow-hidden">
                <div class="px-3 py-2 surface-2 border-b bs flex items-center justify-between">
                    <span class="font-display font-semibold text-xs text-pink-400 uppercase tracking-wider">Top Receivers</span>
                    <i class="bi bi-person-check text-pink-400 text-xs"></i>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-[12px]">
                        <thead>
                            <tr class="surface-2 border-b bs text-[10px] uppercase tracking-wider t3 font-semibold">
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Member</th>
                                <th class="px-3 py-2 text-right">Received</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/40">
                            @forelse($topReceivers as $i => $m)
                            <tr class="hover:surface-2 transition">
                                <td class="px-3 py-2 text-xs font-bold t3">#{{ $i+1 }}</td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-xs t1 truncate" style="max-width:160px">{{ $m->member_name }}</div>
                                    <div class="text-[10px] t3 truncate">{{ $m->member_city ?? '' }}</div>
                                </td>
                                <td class="px-3 py-2 text-right font-bold text-pink-600">{{ $m->total_received }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="px-3 py-4 text-center text-xs t3">No data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Top Accepted --}}
        <div class="col-md-4" id="most-accepted">
            <div class="rounded-xl border bs surface overflow-hidden">
                <div class="px-3 py-2 surface-2 border-b bs flex items-center justify-between">
                    <span class="font-display font-semibold text-xs text-emerald-500 uppercase tracking-wider">Most Accepted</span>
                    <i class="bi bi-check2-circle text-emerald-500 text-xs"></i>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-[12px]">
                        <thead>
                            <tr class="surface-2 border-b bs text-[10px] uppercase tracking-wider t3 font-semibold">
                                <th class="px-3 py-2 text-left">#</th>
                                <th class="px-3 py-2 text-left">Member</th>
                                <th class="px-3 py-2 text-right">Accepted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/40">
                            @forelse($topAccepted as $i => $m)
                            <tr class="hover:surface-2 transition">
                                <td class="px-3 py-2 text-xs font-bold t3">#{{ $i+1 }}</td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-xs t1 truncate" style="max-width:160px">{{ $m->member_name }}</div>
                                    <div class="text-[10px] t3 truncate">{{ $m->member_city ?? '' }}</div>
                                </td>
                                <td class="px-3 py-2 text-right font-bold text-emerald-600">{{ $m->total_accepted }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="px-3 py-4 text-center text-xs t3">No data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ── DETAILED CONNECTION TABLE ── --}}
    <div class="rounded-xl border bs surface overflow-hidden" id="records-table">
        <div class="px-4 py-3 surface-2 border-b bs flex justify-between items-center flex-wrap gap-2">
            <span class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider">
                Connection Details — {{ number_format($total) }} total
            </span>
            <div class="flex gap-2 items-center">
                <form method="GET" action="{{ route('admin.activities.connections.index') }}" class="d-flex gap-1">
                    @foreach(request()->except(['q','page','sort','direction']) as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                           class="form-control form-control-sm text-xs" placeholder="Search name/email…" style="width:200px">
                    <button type="submit" class="btn btn-sm btn-outline-secondary text-xs">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
                <select onchange="window.location=this.value" class="form-select form-select-sm text-xs" style="width:100px">
                    @foreach([10,20,50,100] as $pp)
                    <option value="{{ route('admin.activities.connections.index', array_merge(request()->except(['per_page','page']),['per_page'=>$pp])) }}"
                        {{ ($filters['per_page']??20) == $pp ? 'selected' : '' }}>{{ $pp }} / page</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-[12px]">
                <thead>
                    <tr class="surface-2 border-b bs text-[10px] uppercase tracking-wider t3 font-semibold">
                        @php
                            $sortLink = fn(string $col, string $label) =>
                                '<a href="'.route('admin.activities.connections.index', array_merge(request()->except(['sort','direction','page']),['sort'=>$col,'direction'=>($filters['sort']==$col&&$filters['direction']=='asc')?'desc':'asc'])).'" class="text-inherit d-flex align-items-center gap-1 hover:text-indigo-500 transition">'
                                .$label.'<i class="bi bi-'.($filters['sort']==$col ? ($filters['direction']=='asc'?'sort-up':'sort-down') : 'sort').'" style="font-size:9px"></i></a>';
                        @endphp
                        <th class="px-3 py-2 text-left">{!! $sortLink('created_at', 'Request Date') !!}</th>
                        <th class="px-3 py-2 text-left">Sender</th>
                        <th class="px-3 py-2 text-left">Receiver</th>
                        <th class="px-3 py-2 text-center">{!! $sortLink('is_approved', 'Status') !!}</th>
                        <th class="px-3 py-2 text-left">Sender City</th>
                        <th class="px-3 py-2 text-left">Receiver City</th>
                        <th class="px-3 py-2 text-left">Sender Membership</th>
                        <th class="px-3 py-2 text-left">Receiver Membership</th>
                        <th class="px-3 py-2 text-left">{!! $sortLink('approved_at', 'Response Date') !!}</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/40">
                    @forelse($items as $item)
                    @php
                        $senderName   = $item->sender_name ?? $displayName($item->actor_display_name ?? null, $item->actor_first_name ?? null, $item->actor_last_name ?? null);
                        $receiverName = $item->receiver_name ?? $displayName($item->peer_display_name ?? null, $item->peer_first_name ?? null, $item->peer_last_name ?? null);
                        $requestedAt  = $item->created_at ? \Carbon\Carbon::parse($item->created_at) : null;
                        $respondedAt  = $item->approved_at ? \Carbon\Carbon::parse($item->approved_at) : null;
                    @endphp
                    <tr class="hover:surface-2 transition border-b bs">
                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                            {{ $requestedAt ? $requestedAt->format('Y-m-d') : '—' }}
                            <div class="text-[10px] t3">{{ $requestedAt ? $requestedAt->format('H:i') : '' }}</div>
                        </td>
                        <td class="px-3 py-2.5">
                            @include('admin.components.peer-card', [
                                'name'    => $senderName,
                                'company' => $item->sender_company ?? '',
                                'city'    => $item->actor_city ?? '',
                                'userId'  => $item->requester_id ?? null,
                            ])
                        </td>
                        <td class="px-3 py-2.5">
                            @include('admin.components.peer-card', [
                                'name'    => $receiverName,
                                'company' => $item->receiver_company ?? '',
                                'city'    => $item->peer_city ?? '',
                                'userId'  => $item->addressee_id ?? null,
                            ])
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            @if($item->is_approved)
                                <span class="chip px-2 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Accepted</span>
                            @else
                                <span class="chip px-2 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200">Pending</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs t3">{{ $item->actor_city ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-xs t3">{{ $item->peer_city ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-xs">
                            @if($item->actor_membership_status)
                                <span class="chip px-2 py-0.5 text-xs bg-indigo-50 text-indigo-700 border-indigo-200 font-medium capitalize">{{ $item->actor_membership_status }}</span>
                            @else
                                <span class="t3">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs">
                            @if($item->peer_membership_status)
                                <span class="chip px-2 py-0.5 text-xs bg-indigo-50 text-indigo-700 border-indigo-200 font-medium capitalize">{{ $item->peer_membership_status }}</span>
                            @else
                                <span class="t3">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                            @if($respondedAt)
                                {{ $respondedAt->format('Y-m-d') }}
                                <div class="text-[10px] t3">{{ $respondedAt->format('H:i') }}</div>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-10 text-xs t3">
                            <i class="bi bi-inbox fs-4 d-block mb-2 opacity-30"></i>
                            No connection records found for the selected filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
            <span class="text-xs t3">
                Showing {{ $items->firstItem() ?? 0 }}–{{ $items->lastItem() ?? 0 }} of {{ number_format($total) }}
            </span>
            {{ $items->links() }}
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const labels   = @json($trends['labels']);
    const totals   = @json($trends['totals']);
    const accepted = @json($trends['accepted']);
    const pending  = @json($trends['pending']);

    if (!labels.length) return;

    const ctx = document.getElementById('connectionTrendsChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Total Requests',
                    data: totals,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,.08)',
                    tension: 0.35,
                    fill: true,
                    pointRadius: labels.length > 30 ? 0 : 3,
                    borderWidth: 2,
                },
                {
                    label: 'Accepted',
                    data: accepted,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,.06)',
                    tension: 0.35,
                    fill: false,
                    pointRadius: labels.length > 30 ? 0 : 3,
                    borderWidth: 2,
                },
                {
                    label: 'Pending',
                    data: pending,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,.06)',
                    tension: 0.35,
                    fill: false,
                    pointRadius: labels.length > 30 ? 0 : 3,
                    borderWidth: 2,
                    borderDash: [4,3],
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { labels: { font: { size: 11 }, boxWidth: 14 } },
                tooltip: { bodyFont: { size: 11 }, titleFont: { size: 11 } },
            },
            scales: {
                x: {
                    ticks: { font: { size: 10 }, maxTicksLimit: 15, maxRotation: 0 },
                    grid: { display: false },
                },
                y: {
                    beginAtZero: true,
                    ticks: { font: { size: 10 }, precision: 0 },
                    grid: { color: 'rgba(0,0,0,.05)' },
                },
            },
        },
    });
})();
</script>
@endpush
