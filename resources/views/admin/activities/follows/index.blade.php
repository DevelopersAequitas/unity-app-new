@extends('admin.layouts.app')

@section('title', 'Follow Analytics')

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
    border-color: #8b5cf6;
    box-shadow: 0 10px 24px -4px rgba(139, 92, 246, 0.16);
}
.analytics-summary-card.active-filter {
    border-color: #8b5cf6;
    background: linear-gradient(135deg, rgba(139,92,246,0.06), rgba(139,92,246,0.01));
    box-shadow: 0 0 0 2px rgba(139,92,246,0.4);
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
    color: #8b5cf6;
    opacity: 1;
}
.filter-section { background: var(--surface); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 14px 16px; }
.filter-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--text-3); margin-bottom: 4px; }
.preset-pills { display: flex; flex-wrap: wrap; gap: 5px; }
.preset-pill { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; cursor: pointer; border: 1px solid var(--border-subtle); background: var(--surface); color: var(--text-2); text-decoration: none; transition: all .15s; }
.preset-pill:hover, .preset-pill.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.chart-card { background: var(--surface); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 16px; }
</style>
@endpush

@section('content')
@php
    $displayName = fn(?string $d, ?string $f, ?string $l): string =>
        (trim(($f??'').' '.($l??'')) !== '') ? trim(($f??'').' '.($l??'')) : ($d ?? '—');
    $fmt = fn($v) => $v ? \Carbon\Carbon::parse($v)->format('Y-m-d H:i') : '—';
    $presets = [
        'today'=>'Today','yesterday'=>'Yesterday','this_week'=>'This Week','last_week'=>'Last Week',
        'this_month'=>'This Month','last_month'=>'Last Month','this_quarter'=>'This Quarter',
        'last_quarter'=>'Last Quarter','this_year'=>'This Year','last_year'=>'Last Year',
        'last_7_days'=>'Last 7 Days','last_30_days'=>'Last 30 Days','last_90_days'=>'Last 90 Days',
    ];
    $activePreset = $filters['date_preset'] ?? '';
    $currStatus   = request('status', '');
@endphp

<div class="space-y-4">

    {{-- ── PAGE HEADER ── --}}
    <div class="flex flex-wrap justify-between items-start gap-3">
        <div>
            <h2 class="font-display font-bold text-sm text-purple-500 uppercase tracking-wider m-0">Follow Analytics</h2>
            <p class="text-xs text-muted m-0 mt-1">Visibility into who follows whom and follower activity across the platform</p>
        </div>
        <a href="{{ route('admin.activities.follows.export', request()->except(['page'])) }}"
           class="btn btn-sm btn-outline-primary text-xs">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>

    {{-- ── FILTERS ── --}}
    <form id="followFiltersForm" method="GET" action="{{ route('admin.activities.follows.index') }}" class="space-y-3">
        <div class="filter-section">
            <div class="filter-label mb-2">Date Period</div>
            <div class="preset-pills">
                <a href="{{ route('admin.activities.follows.index', array_merge(request()->except(['date_preset','from','to','page']),['date_preset'=>''])) }}"
                   class="preset-pill {{ $activePreset === '' ? 'active' : '' }}">All Time</a>
                @foreach($presets as $key => $label)
                <a href="{{ route('admin.activities.follows.index', array_merge(request()->except(['date_preset','from','to','page']),['date_preset'=>$key])) }}"
                   class="preset-pill {{ $activePreset === $key ? 'active' : '' }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="flex gap-2 mt-2 flex-wrap items-end">
                <div>
                    <div class="filter-label">From</div>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                           class="form-control form-control-sm text-xs" style="width:150px"
                           onchange="document.getElementById('followFiltersForm').submit()">
                </div>
                <div>
                    <div class="filter-label">To</div>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                           class="form-control form-control-sm text-xs" style="width:150px"
                           onchange="document.getElementById('followFiltersForm').submit()">
                </div>
                <input type="hidden" name="date_preset" value="{{ $filters['date_preset'] ?? '' }}">
                <a href="{{ route('admin.activities.follows.index') }}" class="btn btn-sm btn-outline-secondary text-xs">
                    <i class="bi bi-x-circle me-1"></i>Clear
                </a>
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-label mb-2">Advanced Filters</div>
            <div class="row g-2">
                <div class="col-md-2">
                    <label class="filter-label">Status</label>
                    <select name="status" class="form-select form-select-sm text-xs" onchange="this.form.submit()">
                        <option value="" @selected($currStatus === '')>All Statuses</option>
                        <option value="accepted" @selected($currStatus === 'accepted')>Accepted</option>
                        <option value="pending" @selected($currStatus === 'pending')>Pending</option>
                        <option value="rejected" @selected($currStatus === 'rejected')>Rejected</option>
                        <option value="blocked" @selected($currStatus === 'blocked')>Blocked</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Follower Name</label>
                    <input type="text" name="follower_name" value="{{ $filters['follower_name'] ?? '' }}" class="form-control form-control-sm text-xs" placeholder="Name">
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Follower Email</label>
                    <input type="text" name="follower_email" value="{{ $filters['follower_email'] ?? '' }}" class="form-control form-control-sm text-xs" placeholder="Email">
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Followed Name</label>
                    <input type="text" name="followed_name" value="{{ $filters['followed_name'] ?? '' }}" class="form-control form-control-sm text-xs" placeholder="Name">
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
                    <label class="filter-label">Global Search</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm text-xs" placeholder="Name or email…">
                </div>
            </div>
        </div>
    </form>

    {{-- ── SUMMARY CARDS ── --}}
    @php
        $cards = [
            [
                'label'      => 'Total Follows',
                'value'      => number_format($summary['total_follows']),
                'icon'       => 'bi-people-fill',
                'color_code' => '#8b5cf6',
                'bg'         => 'rgba(139, 92, 246, 0.1)',
                'hint'       => 'Show all records',
                'url'        => route('admin.activities.follows.index', array_merge(request()->except(['status','page']), ['status'=>''])).'#records-table',
                'is_active'  => $currStatus === '',
            ],
            [
                'label'      => 'Accepted Follows',
                'value'      => number_format($summary['accepted_follows'] ?? 0),
                'icon'       => 'bi-check-circle-fill',
                'color_code' => '#10b981',
                'bg'         => 'rgba(16, 185, 129, 0.1)',
                'hint'       => 'Filter accepted',
                'url'        => route('admin.activities.follows.index', array_merge(request()->except(['status','page']), ['status'=>'accepted'])).'#records-table',
                'is_active'  => $currStatus === 'accepted',
            ],
            [
                'label'      => 'Pending Follows',
                'value'      => number_format($summary['pending_follows'] ?? 0),
                'icon'       => 'bi-hourglass-split',
                'color_code' => '#f59e0b',
                'bg'         => 'rgba(245, 158, 11, 0.1)',
                'hint'       => 'Filter pending',
                'url'        => route('admin.activities.follows.index', array_merge(request()->except(['status','page']), ['status'=>'pending'])).'#records-table',
                'is_active'  => $currStatus === 'pending',
            ],
            [
                'label'      => 'Rejected / Blocked',
                'value'      => number_format(($summary['rejected_follows'] ?? 0) + ($summary['blocked_follows'] ?? 0)),
                'icon'       => 'bi-slash-circle-fill',
                'color_code' => '#ef4444',
                'bg'         => 'rgba(239, 68, 68, 0.1)',
                'hint'       => 'Filter rejected',
                'url'        => route('admin.activities.follows.index', array_merge(request()->except(['status','page']), ['status'=>'rejected'])).'#records-table',
                'is_active'  => in_array($currStatus, ['rejected','blocked']),
            ],
            [
                'label'      => 'Unique Followers',
                'value'      => number_format($summary['unique_followers']),
                'icon'       => 'bi-person-plus-fill',
                'color_code' => '#6366f1',
                'bg'         => 'rgba(99, 102, 241, 0.1)',
                'hint'       => 'Top followers',
                'url'        => '#top-followers',
                'is_active'  => false,
            ],
            [
                'label'      => 'Unique Followed',
                'value'      => number_format($summary['unique_followed']),
                'icon'       => 'bi-star-fill',
                'color_code' => '#ec4899',
                'bg'         => 'rgba(236, 72, 153, 0.1)',
                'hint'       => 'Top followed',
                'url'        => '#top-followed',
                'is_active'  => false,
            ],
            [
                'label'      => 'Avg / Member',
                'value'      => $summary['avg_per_member'],
                'icon'       => 'bi-graph-up',
                'color_code' => '#06b6d4',
                'bg'         => 'rgba(6, 182, 212, 0.1)',
                'hint'       => 'View trends',
                'url'        => '#trends-section',
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
        <div class="col-6 col-md-3">
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
                <i class="bi bi-graph-up text-purple-500"></i>
                <span class="font-display font-semibold text-xs text-purple-400 uppercase tracking-wider">Follow Activity Trends</span>
            </div>
            <span class="text-xs text-muted">{{ count($trends['labels']) }} days</span>
        </div>
        <div style="position: relative; height: 160px; width: 100%;">
            <canvas id="followTrendsChart"></canvas>
        </div>
    </div>

    {{-- ── TOP REPORTS ── --}}
    <div class="row g-3">
        <div class="col-md-6" id="top-followed">
            <div class="rounded-xl border bs surface overflow-hidden">
                <div class="px-3 py-2 surface-2 border-b bs flex items-center justify-between">
                    <span class="font-display font-semibold text-xs text-pink-400 uppercase tracking-wider">Most Followed Members (Top 10)</span>
                    <i class="bi bi-star text-pink-400 text-xs"></i>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-[12px]">
                        <thead>
                            <tr class="surface-2 border-b bs text-[10px] uppercase tracking-wider t3 font-semibold">
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2 text-left">Member</th>
                                <th class="px-3 py-2 text-right">Followers</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/40">
                            @forelse($topFollowed as $i => $m)
                            <tr class="hover:surface-2 transition">
                                <td class="px-3 py-2 text-xs font-bold t3 text-center">#{{ $i+1 }}</td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-xs t1 truncate" style="max-width:200px">{{ $m->member_name }}</div>
                                    <div class="text-[10px] t3 truncate">{{ $m->member_city ?? '' }}</div>
                                </td>
                                <td class="px-3 py-2 text-right font-bold text-pink-600">{{ number_format($m->total_followers) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="px-3 py-4 text-center text-xs t3">No data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6" id="top-followers">
            <div class="rounded-xl border bs surface overflow-hidden">
                <div class="px-3 py-2 surface-2 border-b bs flex items-center justify-between">
                    <span class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider">Most Active Followers (Top 10)</span>
                    <i class="bi bi-people text-indigo-400 text-xs"></i>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-[12px]">
                        <thead>
                            <tr class="surface-2 border-b bs text-[10px] uppercase tracking-wider t3 font-semibold">
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2 text-left">Member</th>
                                <th class="px-3 py-2 text-right">Following</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/40">
                            @forelse($topFollowing as $i => $m)
                            <tr class="hover:surface-2 transition">
                                <td class="px-3 py-2 text-xs font-bold t3 text-center">#{{ $i+1 }}</td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-xs t1 truncate" style="max-width:200px">{{ $m->member_name }}</div>
                                    <div class="text-[10px] t3 truncate">{{ $m->member_city ?? '' }}</div>
                                </td>
                                <td class="px-3 py-2 text-right font-bold text-indigo-600">{{ number_format($m->total_following) }}</td>
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

    {{-- ── DETAILED TABLE ── --}}
    <div class="rounded-xl border bs surface overflow-hidden" id="records-table">
        <div class="px-4 py-3 surface-2 border-b bs flex justify-between items-center flex-wrap gap-2">
            <span class="font-display font-semibold text-xs text-purple-400 uppercase tracking-wider">
                Follow Details — {{ number_format($total) }} records
            </span>
            <div class="flex gap-2 items-center">
                <form method="GET" action="{{ route('admin.activities.follows.index') }}" class="d-flex gap-1">
                    @foreach(request()->except(['q','page']) as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                           class="form-control form-control-sm text-xs" placeholder="Search name/email…" style="width:200px">
                    <button type="submit" class="btn btn-sm btn-outline-secondary text-xs"><i class="bi bi-search"></i></button>
                </form>
                <select onchange="window.location=this.value" class="form-select form-select-sm text-xs" style="width:100px">
                    @foreach([10,20,50,100] as $pp)
                    <option value="{{ route('admin.activities.follows.index', array_merge(request()->except(['per_page','page']),['per_page'=>$pp])) }}"
                        {{ ($filters['per_page']??20)==$pp ? 'selected' : '' }}>{{ $pp }} / page</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-[12px]">
                <thead>
                    <tr class="surface-2 border-b bs text-[10px] uppercase tracking-wider t3 font-semibold">
                        <th class="px-3 py-2 text-left">Follow Date</th>
                        <th class="px-3 py-2 text-left">Follower</th>
                        <th class="px-3 py-2 text-left">Follower City</th>
                        <th class="px-3 py-2 text-left">Followed Member</th>
                        <th class="px-3 py-2 text-left">Followed City</th>
                        <th class="px-3 py-2 text-left">Follower Membership</th>
                        <th class="px-3 py-2 text-left">Followed Membership</th>
                        <th class="px-3 py-2 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200/40">
                    @forelse($items as $item)
                    @php
                        $followerName = $item->follower_name ?? $displayName($item->follower_display_name??null,$item->follower_first_name??null,$item->follower_last_name??null);
                        $followedName = $item->followed_name ?? $displayName($item->followed_display_name??null,$item->followed_first_name??null,$item->followed_last_name??null);
                        $at = $item->created_at ? \Carbon\Carbon::parse($item->created_at) : null;
                    @endphp
                    <tr class="hover:surface-2 transition border-b bs">
                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                            {{ $at ? $at->format('Y-m-d') : '—' }}
                            <div class="text-[10px] t3">{{ $at ? $at->format('H:i') : '' }}</div>
                        </td>
                        <td class="px-3 py-2.5">
                            @include('admin.components.peer-card', [
                                'name'   => $followerName,
                                'city'   => $item->follower_city ?? '',
                                'userId' => $item->follower_id ?? null,
                            ])
                        </td>
                        <td class="px-3 py-2.5 text-xs t3">{{ $item->follower_city ?? '—' }}</td>
                        <td class="px-3 py-2.5">
                            @include('admin.components.peer-card', [
                                'name'   => $followedName,
                                'city'   => $item->followed_city ?? '',
                                'userId' => $item->following_id ?? null,
                            ])
                        </td>
                        <td class="px-3 py-2.5 text-xs t3">{{ $item->followed_city ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-xs">
                            @if($item->follower_membership_status)
                                <span class="chip px-2 py-0.5 text-xs bg-indigo-50 text-indigo-700 border-indigo-200 font-medium capitalize">{{ $item->follower_membership_status }}</span>
                            @else
                                <span class="t3">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs">
                            @if($item->followed_membership_status)
                                <span class="chip px-2 py-0.5 text-xs bg-purple-50 text-purple-700 border-purple-200 font-medium capitalize">{{ $item->followed_membership_status }}</span>
                            @else
                                <span class="t3">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="chip px-2 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200 capitalize">
                                {{ $item->status ?? 'followed' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-10 text-xs t3">
                            <i class="bi bi-person-plus fs-4 d-block mb-2 opacity-30"></i>
                            No follow records found for the selected filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 border-t bs flex justify-between items-center">
            <span class="text-xs t3">Showing {{ $items->firstItem() ?? 0 }}–{{ $items->lastItem() ?? 0 }} of {{ number_format($total) }}</span>
            {{ $items->links() }}
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const labels = @json($trends['labels']);
    const totals = @json($trends['totals']);
    if (!labels.length) return;
    const ctx = document.getElementById('followTrendsChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'New Follows',
                data: totals,
                backgroundColor: 'rgba(139,92,246,.6)',
                borderColor: '#8b5cf6',
                borderWidth: 1,
                borderRadius: 3,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { font: { size: 11 }, boxWidth: 14 } },
                tooltip: { bodyFont: { size: 11 }, titleFont: { size: 11 } },
            },
            scales: {
                x: { ticks: { font: { size: 10 }, maxTicksLimit: 15, maxRotation: 0 }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { font: { size: 10 }, precision: 0 }, grid: { color: 'rgba(0,0,0,.05)' } },
            },
        },
    });
})();
</script>
@endpush
