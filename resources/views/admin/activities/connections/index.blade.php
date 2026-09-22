@extends('admin.layouts.app')

@section('title', 'Connection Analytics')

@include('admin.partials.grid-head')

@push('styles')
<style>
/* ── Premium Analytics Card System (Connections) ── */
.analytics-summary-card {
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
    box-shadow: 0 2px 4px rgba(15,23,42,0.02), 0 1px 2px rgba(15,23,42,0.03);
    min-height: 110px;
    overflow: hidden;
}
.analytics-summary-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: transparent;
    transition: all 0.2s;
}
.analytics-summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px -6px rgba(15,23,42,0.08), 0 4px 10px -2px rgba(15,23,42,0.04);
}
.card-indigo:hover { border-color: #6366f1; }
.card-indigo:hover::before, .card-indigo.active-filter::before { background: #6366f1; }
.card-indigo.active-filter { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,0.25); background: linear-gradient(180deg, rgba(99,102,241,0.04) 0%, #fff 100%); }
.card-emerald:hover { border-color: #10b981; }
.card-emerald:hover::before, .card-emerald.active-filter::before { background: #10b981; }
.card-emerald.active-filter { border-color: #10b981; box-shadow: 0 0 0 2px rgba(16,185,129,0.25); background: linear-gradient(180deg, rgba(16,185,129,0.04) 0%, #fff 100%); }
.card-amber:hover { border-color: #f59e0b; }
.card-amber:hover::before, .card-amber.active-filter::before { background: #f59e0b; }
.card-amber.active-filter { border-color: #f59e0b; box-shadow: 0 0 0 2px rgba(245,158,11,0.25); background: linear-gradient(180deg, rgba(245,158,11,0.04) 0%, #fff 100%); }
.card-sky:hover { border-color: #0ea5e9; }
.card-sky:hover::before, .card-sky.active-filter::before { background: #0ea5e9; }
.card-sky.active-filter { border-color: #0ea5e9; box-shadow: 0 0 0 2px rgba(14,165,233,0.25); background: linear-gradient(180deg, rgba(14,165,233,0.04) 0%, #fff 100%); }
.card-violet:hover { border-color: #8b5cf6; }
.card-violet:hover::before, .card-violet.active-filter::before { background: #8b5cf6; }
.card-violet.active-filter { border-color: #8b5cf6; box-shadow: 0 0 0 2px rgba(139,92,246,0.25); background: linear-gradient(180deg, rgba(139,92,246,0.04) 0%, #fff 100%); }
.card-rose:hover { border-color: #f43f5e; }
.card-rose:hover::before, .card-rose.active-filter::before { background: #f43f5e; }
.card-rose.active-filter { border-color: #f43f5e; box-shadow: 0 0 0 2px rgba(244,63,94,0.25); background: linear-gradient(180deg, rgba(244,63,94,0.04) 0%, #fff 100%); }
.card-cyan:hover { border-color: #06b6d4; }
.card-cyan:hover::before, .card-cyan.active-filter::before { background: #06b6d4; }
.card-cyan.active-filter { border-color: #06b6d4; box-shadow: 0 0 0 2px rgba(6,182,212,0.25); background: linear-gradient(180deg, rgba(6,182,212,0.04) 0%, #fff 100%); }
.card-orange:hover { border-color: #f97316; }
.card-orange:hover::before, .card-orange.active-filter::before { background: #f97316; }
.card-orange.active-filter { border-color: #f97316; box-shadow: 0 0 0 2px rgba(249,115,22,0.25); background: linear-gradient(180deg, rgba(249,115,22,0.04) 0%, #fff 100%); }

.analytics-summary-card .icon-box {
    width: 40px; height: 40px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0; transition: transform 0.2s;
}
.analytics-summary-card:hover .icon-box { transform: scale(1.08); }
.analytics-summary-card .stat-label {
    font-size: 11px; text-transform: uppercase; letter-spacing: .06em;
    font-weight: 700; color: #64748b;
}
.analytics-summary-card .stat-value {
    font-size: 26px; font-weight: 800; line-height: 1.15;
    color: #0f172a; letter-spacing: -0.02em;
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
}
.analytics-summary-card .card-hint {
    font-size: 11px; color: #94a3b8; margin-top: 6px;
    display: flex; align-items: center; gap: 4px; font-weight: 600; transition: color 0.15s;
}
.card-indigo:hover .card-hint, .card-indigo.active-filter .card-hint { color: #6366f1; }
.card-emerald:hover .card-hint, .card-emerald.active-filter .card-hint { color: #10b981; }
.card-amber:hover .card-hint, .card-amber.active-filter .card-hint { color: #d97706; }
.card-sky:hover .card-hint, .card-sky.active-filter .card-hint { color: #0ea5e9; }
.card-violet:hover .card-hint, .card-violet.active-filter .card-hint { color: #8b5cf6; }
.card-rose:hover .card-hint, .card-rose.active-filter .card-hint { color: #f43f5e; }
.card-cyan:hover .card-hint, .card-cyan.active-filter .card-hint { color: #06b6d4; }
.card-orange:hover .card-hint, .card-orange.active-filter .card-hint { color: #f97316; }

.filter-section { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px 20px; box-shadow: 0 1px 3px rgba(15,23,42,0.03); }
.filter-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #64748b; margin-bottom: 4px; }
.preset-pills { display: flex; flex-wrap: wrap; gap: 6px; }
.preset-pill { padding: 4px 12px; border-radius: 9999px; font-size: 11.5px; font-weight: 600; cursor: pointer; border: 1px solid #e2e8f0; background: #f8fafc; color: #475569; text-decoration: none; transition: all .15s ease; }
.preset-pill:hover { background: #e2e8f0; color: #0f172a; border-color: #cbd5e1; }
.preset-pill.active { background: #6366f1; color: #ffffff; border-color: #6366f1; box-shadow: 0 2px 6px rgba(99,102,241,0.3); }
.chart-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(15,23,42,0.03); }
.rank-badge { width: 22px; height: 22px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; }
.rank-1 { background: #fef3c7; color: #b45309; }
.rank-2 { background: #f1f5f9; color: #475569; }
.rank-3 { background: #ffedd5; color: #c2410c; }
.rank-other { background: #f8fafc; color: #94a3b8; }
.avatar-circle { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; color: #ffffff; flex-shrink: 0; }
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
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(99,102,241,0.12); color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0" style="color: #0f172a; font-size: 1.25rem;">Connection Request Analytics</h4>
                    <p class="text-muted mb-0" style="font-size: 0.82rem;">Complete visibility into connection request activity across the platform</p>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.activities.connections.export', request()->except(['page'])) }}"
               class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 px-3 py-2 fw-semibold" style="border-radius: 10px;">
                <i class="bi bi-file-earmark-arrow-down"></i> Export CSV
            </a>
        </div>
    </div>

    {{-- ── FILTERS FORM ── --}}
    <div class="filter-section">
        <form id="connFiltersForm" method="GET" action="{{ route('admin.activities.connections.index') }}">
            <div class="mb-3">
                <div class="filter-label mb-2"><i class="bi bi-calendar-event me-1"></i> Quick Date Range:</div>
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
                <input type="hidden" name="date_preset" value="{{ $filters['date_preset'] ?? '' }}">
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <label class="filter-label">From Date</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm" style="border-radius:8px;">
                </div>
                <div class="col-6 col-md-3">
                    <label class="filter-label">To Date</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm" style="border-radius:8px;">
                </div>
                <div class="col-12 col-md-6 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary px-4 fw-semibold" style="border-radius:8px; background:#6366f1; border-color:#6366f1;">
                        <i class="bi bi-funnel me-1"></i> Apply Filter
                    </button>
                    <a href="{{ route('admin.activities.connections.index') }}" class="btn btn-sm btn-light border px-3 fw-semibold text-muted" style="border-radius:8px;">
                        <i class="bi bi-x-circle me-1"></i> Clear
                    </a>
                </div>
            </div>
            <div class="border-top pt-3">
                <div class="filter-label mb-2"><i class="bi bi-sliders me-1"></i> Advanced Filters:</div>
                <div class="row g-2">
                    <div class="col-6 col-md-2">
                        <label class="text-muted small" style="font-size:11px;">Status</label>
                        <select name="status" class="form-select form-select-sm" style="border-radius:8px;">
                            <option value="" @selected($activeStatus === '')>Any Status</option>
                            <option value="accepted" @selected($activeStatus === 'accepted')>✓ Accepted</option>
                            <option value="pending" @selected($activeStatus === 'pending')>⏳ Pending</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="text-muted small" style="font-size:11px;">Sender Name</label>
                        <input type="text" name="sender_name" value="{{ $filters['sender_name'] ?? '' }}" class="form-control form-control-sm" placeholder="Sender name" style="border-radius:8px;">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="text-muted small" style="font-size:11px;">Sender City</label>
                        <input type="text" name="sender_city" value="{{ $filters['sender_city'] ?? '' }}" class="form-control form-control-sm" placeholder="City" style="border-radius:8px;">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="text-muted small" style="font-size:11px;">Receiver Name</label>
                        <input type="text" name="receiver_name" value="{{ $filters['receiver_name'] ?? '' }}" class="form-control form-control-sm" placeholder="Receiver name" style="border-radius:8px;">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="text-muted small" style="font-size:11px;">Receiver City</label>
                        <input type="text" name="receiver_city" value="{{ $filters['receiver_city'] ?? '' }}" class="form-control form-control-sm" placeholder="City" style="border-radius:8px;">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="text-muted small" style="font-size:11px;">Circle</label>
                        <select name="circle_id" class="form-select form-select-sm" style="border-radius:8px;">
                            <option value="">All Circles</option>
                            @foreach($circles as $circle)
                                <option value="{{ $circle->id }}" @selected(($filters['circle_id']??'') === (string)$circle->id)>{{ $circle->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="text-muted small" style="font-size:11px;">Global Search</label>
                        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Name or email…" style="border-radius:8px;">
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- ── SUMMARY CARDS ── --}}
    @php $currStatus = request('status', ''); @endphp
    <div class="row g-3">
        {{-- 1. Total Requests --}}
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.activities.connections.index', array_merge(request()->except(['status','page']), ['status'=>''])) }}#records-table"
               class="analytics-summary-card card-indigo {{ $currStatus === '' ? 'active-filter' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Total Requests</div>
                        <div class="stat-value" style="color:#4f46e5;">{{ number_format($summary['total_requests']) }}</div>
                    </div>
                    <div class="icon-box" style="background:rgba(99,102,241,0.1); color:#6366f1;"><i class="bi bi-send-fill"></i></div>
                </div>
                <div class="card-hint"><span>Show all records</span> <i class="bi bi-arrow-right"></i></div>
            </a>
        </div>
        {{-- 2. Accepted --}}
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.activities.connections.index', array_merge(request()->except(['status','page']), ['status'=>'accepted'])) }}#records-table"
               class="analytics-summary-card card-emerald {{ $currStatus === 'accepted' ? 'active-filter' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Accepted</div>
                        <div class="stat-value" style="color:#10b981;">{{ number_format($summary['total_accepted']) }}</div>
                    </div>
                    <div class="icon-box" style="background:rgba(16,185,129,0.1); color:#10b981;"><i class="bi bi-check-circle-fill"></i></div>
                </div>
                <div class="card-hint"><span>Filter accepted</span> <i class="bi bi-arrow-right"></i></div>
            </a>
        </div>
        {{-- 3. Pending --}}
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.activities.connections.index', array_merge(request()->except(['status','page']), ['status'=>'pending'])) }}#records-table"
               class="analytics-summary-card card-amber {{ $currStatus === 'pending' ? 'active-filter' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Pending</div>
                        <div class="stat-value" style="color:#d97706;">{{ number_format($summary['total_pending']) }}</div>
                    </div>
                    <div class="icon-box" style="background:rgba(245,158,11,0.1); color:#f59e0b;"><i class="bi bi-hourglass-split"></i></div>
                </div>
                <div class="card-hint"><span>Filter pending</span> <i class="bi bi-arrow-right"></i></div>
            </a>
        </div>
        {{-- 4. Acceptance Rate --}}
        <div class="col-6 col-md-3">
            <a href="#trends-section" class="analytics-summary-card card-sky">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Acceptance Rate</div>
                        <div class="stat-value" style="color:#0284c7;">{{ $summary['acceptance_rate'] }}%</div>
                    </div>
                    <div class="icon-box" style="background:rgba(14,165,233,0.1); color:#0ea5e9;"><i class="bi bi-graph-up-arrow"></i></div>
                </div>
                <div class="card-hint"><span>View trends</span> <i class="bi bi-arrow-down"></i></div>
            </a>
        </div>
        {{-- 5. Unique Senders --}}
        <div class="col-6 col-md-3">
            <a href="#top-senders" class="analytics-summary-card card-violet">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Unique Senders</div>
                        <div class="stat-value" style="color:#7c3aed;">{{ number_format($summary['unique_senders']) }}</div>
                    </div>
                    <div class="icon-box" style="background:rgba(139,92,246,0.1); color:#8b5cf6;"><i class="bi bi-person-plus-fill"></i></div>
                </div>
                <div class="card-hint"><span>Top senders</span> <i class="bi bi-arrow-down"></i></div>
            </a>
        </div>
        {{-- 6. Unique Receivers --}}
        <div class="col-6 col-md-3">
            <a href="#top-receivers" class="analytics-summary-card card-rose">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Unique Receivers</div>
                        <div class="stat-value" style="color:#e11d48;">{{ number_format($summary['unique_receivers']) }}</div>
                    </div>
                    <div class="icon-box" style="background:rgba(244,63,94,0.1); color:#f43f5e;"><i class="bi bi-person-check-fill"></i></div>
                </div>
                <div class="card-hint"><span>Top receivers</span> <i class="bi bi-arrow-down"></i></div>
            </a>
        </div>
        {{-- 7. Avg / Member --}}
        <div class="col-6 col-md-3">
            <a href="#most-accepted" class="analytics-summary-card card-cyan">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Avg / Member</div>
                        <div class="stat-value" style="color:#0891b2;">{{ $summary['avg_per_member'] }}</div>
                    </div>
                    <div class="icon-box" style="background:rgba(6,182,212,0.1); color:#06b6d4;"><i class="bi bi-people-fill"></i></div>
                </div>
                <div class="card-hint"><span>Most connected</span> <i class="bi bi-arrow-down"></i></div>
            </a>
        </div>
        {{-- 8. Avg / Day --}}
        <div class="col-6 col-md-3">
            <a href="#trends-section" class="analytics-summary-card card-orange">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Avg / Day</div>
                        <div class="stat-value" style="color:#ea580c;">{{ $summary['avg_per_day'] }}</div>
                    </div>
                    <div class="icon-box" style="background:rgba(249,115,22,0.1); color:#f97316;"><i class="bi bi-calendar2-day-fill"></i></div>
                </div>
                <div class="card-hint"><span>View trends</span> <i class="bi bi-arrow-down"></i></div>
            </a>
        </div>
    </div>

    {{-- ── TRENDS CHART ── --}}
    <div class="chart-card" id="trends-section">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center gap-2">
                <div style="width:28px;height:28px;border-radius:8px;background:rgba(99,102,241,0.12);color:#6366f1;display:flex;align-items:center;justify-content:center;font-size:14px;">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <span class="fw-bold" style="color:#0f172a;font-size:0.9rem;">Connection Request Trends</span>
            </div>
            <span class="badge bg-light text-muted border px-2 py-1" style="font-size:11px;">{{ count($trends['labels']) }} days timeline</span>
        </div>
        <div style="position: relative; height: 160px; width: 100%;">
            <canvas id="connectionTrendsChart"></canvas>
        </div>
    </div>

    {{-- ── TOP ACTIVITY REPORTS ── --}}
    <div class="row g-3">
        {{-- Top Senders --}}
        <div class="col-md-4" id="top-senders">
            <div class="card border shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark text-uppercase" style="font-size:11.5px;letter-spacing:0.04em;">
                        <i class="bi bi-send-fill me-1" style="color:#6366f1;"></i> Top Request Senders
                    </span>
                    <span class="badge bg-light text-dark border">Top 10</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle" style="font-size:12px;">
                        <thead class="table-light">
                            <tr style="font-size:10.5px;color:#64748b;" class="text-uppercase">
                                <th class="ps-3 py-2" style="width:36px;">#</th>
                                <th class="py-2">Member</th>
                                <th class="text-end py-2">Sent</th>
                                <th class="text-end pe-3 py-2">Accepted</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topSenders as $i => $m)
                            <tr>
                                <td class="ps-3 py-2"><span class="rank-badge {{ $i===0?'rank-1':($i===1?'rank-2':($i===2?'rank-3':'rank-other')) }}">{{ $i+1 }}</span></td>
                                <td class="py-2">
                                    <div class="fw-bold text-dark" style="max-width:130px;">{{ $m->member_name }}</div>
                                    <div class="text-muted" style="font-size:10.5px;">{{ $m->member_city ?? '—' }}</div>
                                </td>
                                <td class="text-end py-2 fw-bold" style="color:#6366f1;">{{ number_format($m->total_sent) }}</td>
                                <td class="text-end pe-3 py-2 fw-bold" style="color:#10b981;">{{ number_format($m->total_accepted) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center py-4 text-muted">No data found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Top Receivers --}}
        <div class="col-md-4" id="top-receivers">
            <div class="card border shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark text-uppercase" style="font-size:11.5px;letter-spacing:0.04em;">
                        <i class="bi bi-person-check-fill me-1" style="color:#f43f5e;"></i> Top Request Receivers
                    </span>
                    <span class="badge bg-light text-dark border">Top 10</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle" style="font-size:12px;">
                        <thead class="table-light">
                            <tr style="font-size:10.5px;color:#64748b;" class="text-uppercase">
                                <th class="ps-3 py-2" style="width:36px;">#</th>
                                <th class="py-2">Member</th>
                                <th class="text-end pe-3 py-2">Received</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topReceivers as $i => $m)
                            <tr>
                                <td class="ps-3 py-2"><span class="rank-badge {{ $i===0?'rank-1':($i===1?'rank-2':($i===2?'rank-3':'rank-other')) }}">{{ $i+1 }}</span></td>
                                <td class="py-2">
                                    <div class="fw-bold text-dark" style="max-width:140px;">{{ $m->member_name }}</div>
                                    <div class="text-muted" style="font-size:10.5px;">{{ $m->member_city ?? '—' }}</div>
                                </td>
                                <td class="text-end pe-3 py-2 fw-bold" style="color:#f43f5e;">{{ number_format($m->total_received) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center py-4 text-muted">No data found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Most Accepted --}}
        <div class="col-md-4" id="most-accepted">
            <div class="card border shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark text-uppercase" style="font-size:11.5px;letter-spacing:0.04em;">
                        <i class="bi bi-check2-circle me-1" style="color:#10b981;"></i> Most Accepted Members
                    </span>
                    <span class="badge bg-light text-dark border">Top 10</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle" style="font-size:12px;">
                        <thead class="table-light">
                            <tr style="font-size:10.5px;color:#64748b;" class="text-uppercase">
                                <th class="ps-3 py-2" style="width:36px;">#</th>
                                <th class="py-2">Member</th>
                                <th class="text-end pe-3 py-2">Accepted</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topAccepted as $i => $m)
                            <tr>
                                <td class="ps-3 py-2"><span class="rank-badge {{ $i===0?'rank-1':($i===1?'rank-2':($i===2?'rank-3':'rank-other')) }}">{{ $i+1 }}</span></td>
                                <td class="py-2">
                                    <div class="fw-bold text-dark" style="max-width:140px;">{{ $m->member_name }}</div>
                                    <div class="text-muted" style="font-size:10.5px;">{{ $m->member_city ?? '—' }}</div>
                                </td>
                                <td class="text-end pe-3 py-2 fw-bold" style="color:#10b981;">{{ number_format($m->total_accepted) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center py-4 text-muted">No data found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ── DETAILED CONNECTION TABLE ── --}}
    <div class="card border shadow-sm" style="border-radius:16px;overflow:hidden;" id="records-table">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-dark" style="font-size:0.95rem;">Connection Activity Details</span>
                <span class="badge bg-light text-dark border px-2 py-1" style="font-size:11px;">{{ number_format($total) }} Records</span>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <form method="GET" action="{{ route('admin.activities.connections.index') }}" class="d-flex gap-1">
                    @foreach(request()->except(['q','page','sort','direction']) as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <div class="input-group input-group-sm" style="width:210px;">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control border-start-0 ps-0" placeholder="Search name/email…">
                    </div>
                </form>
                <select onchange="window.location=this.value" class="form-select form-select-sm" style="width:110px;border-radius:8px;">
                    @foreach([10,20,50,100] as $pp)
                    <option value="{{ route('admin.activities.connections.index', array_merge(request()->except(['per_page','page']),['per_page'=>$pp])) }}"
                        {{ ($filters['per_page']??20) == $pp ? 'selected' : '' }}>{{ $pp }} / page</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:12.5px;">
                <thead class="table-light">
                    <tr class="text-uppercase text-muted" style="font-size:10.5px;letter-spacing:0.05em;">
                        @php
                            $sortLink = fn(string $col, string $label) =>
                                '<a href="'.route('admin.activities.connections.index', array_merge(request()->except(['sort','direction','page']),['sort'=>$col,'direction'=>($filters['sort']==$col&&$filters['direction']=='asc')?'desc':'asc'])).'" class="text-muted d-flex align-items-center gap-1 text-decoration-none" style="font-size:10.5px;">'
                                .$label.'<i class="bi bi-'.($filters['sort']==$col ? ($filters['direction']=='asc'?'sort-up-alt':'sort-down-alt') : 'arrow-down-up').'" style="font-size:9px"></i></a>';
                        @endphp
                        <th class="ps-4 py-3">{!! $sortLink('created_at', 'Request Date') !!}</th>
                        <th class="py-3">Sender</th>
                        <th class="py-3">Receiver</th>
                        <th class="text-center py-3">{!! $sortLink('is_approved', 'Status') !!}</th>
                        <th class="py-3">Sender City</th>
                        <th class="py-3">Receiver City</th>
                        <th class="py-3">Sender Membership</th>
                        <th class="py-3">Receiver Membership</th>
                        <th class="py-3">{!! $sortLink('approved_at', 'Response Date') !!}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    @php
                        $senderName   = $item->sender_name ?? $displayName($item->actor_display_name ?? null, $item->actor_first_name ?? null, $item->actor_last_name ?? null);
                        $receiverName = $item->receiver_name ?? $displayName($item->peer_display_name ?? null, $item->peer_first_name ?? null, $item->peer_last_name ?? null);
                        $requestedAt  = $item->created_at ? \Carbon\Carbon::parse($item->created_at) : null;
                        $respondedAt  = $item->approved_at ? \Carbon\Carbon::parse($item->approved_at) : null;
                    @endphp
                    <tr>
                        <td class="ps-4 py-3 whitespace-nowrap">
                            <div class="fw-semibold text-dark">{{ $requestedAt ? $requestedAt->format('M d, Y') : '—' }}</div>
                            <div class="text-muted small" style="font-size:11px;"><i class="bi bi-clock me-1"></i>{{ $requestedAt ? $requestedAt->format('h:i A') : '' }}</div>
                        </td>
                        <td class="py-3">
                            @include('admin.components.peer-card', [
                                'name'    => $senderName,
                                'company' => $item->sender_company ?? '',
                                'city'    => $item->actor_city ?? '',
                                'userId'  => $item->requester_id ?? null,
                            ])
                        </td>
                        <td class="py-3">
                            @include('admin.components.peer-card', [
                                'name'    => $receiverName,
                                'company' => $item->receiver_company ?? '',
                                'city'    => $item->peer_city ?? '',
                                'userId'  => $item->addressee_id ?? null,
                            ])
                        </td>
                        <td class="py-3 text-center">
                            @if($item->is_approved)
                                <span class="badge px-2 py-1 rounded-pill" style="background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;font-weight:600;">
                                    <i class="bi bi-check2 me-1"></i>Accepted
                                </span>
                            @else
                                <span class="badge px-2 py-1 rounded-pill" style="background:#fffbeb;color:#d97706;border:1px solid #fde68a;font-weight:600;">
                                    <i class="bi bi-hourglass-split me-1"></i>Pending
                                </span>
                            @endif
                        </td>
                        <td class="py-3 text-muted">{{ $item->actor_city ?? '—' }}</td>
                        <td class="py-3 text-muted">{{ $item->peer_city ?? '—' }}</td>
                        <td class="py-3">
                            @if($item->actor_membership_status)
                                <span class="badge px-2 py-1 rounded-pill" style="background:#eef2ff;color:#4f46e5;border:1px solid #c7d2fe;font-size:11px;">{{ $item->actor_membership_status }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="py-3">
                            @if($item->peer_membership_status)
                                <span class="badge px-2 py-1 rounded-pill" style="background:#eef2ff;color:#4f46e5;border:1px solid #c7d2fe;font-size:11px;">{{ $item->peer_membership_status }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="py-3">
                            @if($respondedAt)
                                <div class="fw-semibold text-dark">{{ $respondedAt->format('M d, Y') }}</div>
                                <div class="text-muted small" style="font-size:11px;">{{ $respondedAt->format('h:i A') }}</div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <div class="py-3">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-muted opacity-50"></i>
                                <div class="fw-semibold">No connection records found</div>
                                <div class="small">Try adjusting your filters or search query.</div>
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
