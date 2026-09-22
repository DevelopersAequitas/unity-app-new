@extends('admin.layouts.app')

@section('title', 'Message Analytics')

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
    border-color: #0d9488;
    box-shadow: 0 10px 24px -4px rgba(13, 148, 136, 0.16);
}
.analytics-summary-card.active-filter {
    border-color: #0d9488;
    background: linear-gradient(135deg, rgba(13,148,136,0.06), rgba(13,148,136,0.01));
    box-shadow: 0 0 0 2px rgba(13,148,136,0.4);
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
    color: #0d9488;
    opacity: 1;
}
.filter-section { background: var(--surface); border: 1px solid var(--border-subtle); border-radius: 10px; padding: 14px 16px; }
.filter-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--text-3); margin-bottom: 4px; }
.preset-pills { display: flex; flex-wrap: wrap; gap: 5px; }
.preset-pill { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; cursor: pointer; border: 1px solid var(--border-subtle); background: var(--surface); color: var(--text-2); text-decoration: none; transition: all .15s; }
.preset-pill:hover, .preset-pill.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.chart-card { background: var(--surface); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 16px; }
.privacy-notice { background: rgba(16,185,129,.06); border: 1px solid rgba(16,185,129,.2); border-radius: 8px; padding: 10px 14px; font-size: 12px; color: #047857; }
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
    $currType     = $filters['message_type'] ?? '';
    $currRead     = $filters['read_status'] ?? '';
@endphp

<div class="space-y-4">

    {{-- ── PAGE HEADER ── --}}
    <div class="flex flex-wrap justify-between items-start gap-3">
        <div>
            <h2 class="font-display font-bold text-sm text-teal-500 uppercase tracking-wider m-0">Message Analytics</h2>
            <p class="text-xs text-muted m-0 mt-1">Volume and activity insights across private conversations — message content is never shown</p>
        </div>
        <a href="{{ route('admin.activities.messages.export', request()->except(['page'])) }}"
           class="btn btn-sm btn-outline-primary text-xs">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
    </div>

    {{-- Privacy notice --}}
    <div class="privacy-notice flex items-center gap-2">
        <i class="bi bi-shield-lock-fill"></i>
        <span><strong>Privacy Protected:</strong> Message content is never displayed or exported. Only activity metadata (sender, receiver, timestamp, type, read status) is analyzed.</span>
    </div>

    {{-- ── FILTERS ── --}}
    <form id="msgFiltersForm" method="GET" action="{{ route('admin.activities.messages.index') }}" class="space-y-3">
        <div class="filter-section">
            <div class="filter-label mb-2">Date Period</div>
            <div class="preset-pills">
                <a href="{{ route('admin.activities.messages.index', array_merge(request()->except(['date_preset','from','to','page']),['date_preset'=>''])) }}"
                   class="preset-pill {{ $activePreset === '' ? 'active' : '' }}">All Time</a>
                @foreach($presets as $key => $label)
                <a href="{{ route('admin.activities.messages.index', array_merge(request()->except(['date_preset','from','to','page']),['date_preset'=>$key])) }}"
                   class="preset-pill {{ $activePreset === $key ? 'active' : '' }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="flex gap-2 mt-2 flex-wrap items-end">
                <div>
                    <div class="filter-label">From</div>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm text-xs" style="width:150px" onchange="document.getElementById('msgFiltersForm').submit()">
                </div>
                <div>
                    <div class="filter-label">To</div>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm text-xs" style="width:150px" onchange="document.getElementById('msgFiltersForm').submit()">
                </div>
                <input type="hidden" name="date_preset" value="{{ $filters['date_preset'] ?? '' }}">
                <a href="{{ route('admin.activities.messages.index') }}" class="btn btn-sm btn-outline-secondary text-xs">
                    <i class="bi bi-x-circle me-1"></i>Clear
                </a>
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-label mb-2">Advanced Filters</div>
            <div class="row g-2">
                <div class="col-md-2">
                    <label class="filter-label">Message Type</label>
                    <select name="message_type" class="form-select form-select-sm text-xs" onchange="this.form.submit()">
                        <option value="" @selected(($filters['message_type']??'') === '')>Any Type</option>
                        <option value="text"  @selected(($filters['message_type']??'') === 'text')>Text Only</option>
                        <option value="media" @selected(($filters['message_type']??'') === 'media')>Media / Attachments</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Read Status</label>
                    <select name="read_status" class="form-select form-select-sm text-xs" onchange="this.form.submit()">
                        <option value="" @selected(($filters['read_status']??'') === '')>Any</option>
                        <option value="read"   @selected(($filters['read_status']??'') === 'read')>Read</option>
                        <option value="unread" @selected(($filters['read_status']??'') === 'unread')>Unread</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Sender Name</label>
                    <input type="text" name="sender_name" value="{{ $filters['sender_name'] ?? '' }}" class="form-control form-control-sm text-xs" placeholder="Name">
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Sender City</label>
                    <input type="text" name="sender_city" value="{{ $filters['sender_city'] ?? '' }}" class="form-control form-control-sm text-xs" placeholder="City">
                </div>
                <div class="col-md-2">
                    <label class="filter-label">Receiver Name</label>
                    <input type="text" name="receiver_name" value="{{ $filters['receiver_name'] ?? '' }}" class="form-control form-control-sm text-xs" placeholder="Name">
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
            </div>
        </div>
    </form>

    {{-- ── SUMMARY CARDS ── --}}
    @php
        $cards = [
            [
                'label'      => 'Total Messages',
                'value'      => number_format($summary['total_messages']),
                'icon'       => 'bi-chat-dots-fill',
                'color_code' => '#0d9488',
                'bg'         => 'rgba(13, 148, 136, 0.1)',
                'hint'       => 'Show all records',
                'url'        => route('admin.activities.messages.index', array_merge(request()->except(['message_type','read_status','page']), [])).'#records-table',
                'is_active'  => $currType === '' && $currRead === '',
            ],
            [
                'label'      => 'Text Messages',
                'value'      => number_format($summary['total_text'] ?? 0),
                'icon'       => 'bi-chat-text-fill',
                'color_code' => '#64748b',
                'bg'         => 'rgba(100, 116, 139, 0.1)',
                'hint'       => 'Filter text msgs',
                'url'        => route('admin.activities.messages.index', array_merge(request()->except(['message_type','page']), ['message_type'=>'text'])).'#records-table',
                'is_active'  => $currType === 'text',
            ],
            [
                'label'      => 'Media / Attachments',
                'value'      => number_format($summary['total_media'] ?? 0),
                'icon'       => 'bi-paperclip',
                'color_code' => '#8b5cf6',
                'bg'         => 'rgba(139, 92, 246, 0.1)',
                'hint'       => 'Filter media msgs',
                'url'        => route('admin.activities.messages.index', array_merge(request()->except(['message_type','page']), ['message_type'=>'media'])).'#records-table',
                'is_active'  => $currType === 'media',
            ],
            [
                'label'      => 'Read Messages',
                'value'      => number_format($summary['total_read'] ?? 0),
                'icon'       => 'bi-check2-all',
                'color_code' => '#10b981',
                'bg'         => 'rgba(16, 185, 129, 0.1)',
                'hint'       => 'Filter read msgs',
                'url'        => route('admin.activities.messages.index', array_merge(request()->except(['read_status','page']), ['read_status'=>'read'])).'#records-table',
                'is_active'  => $currRead === 'read',
            ],
            [
                'label'      => 'Unread Messages',
                'value'      => number_format($summary['total_unread']),
                'icon'       => 'bi-envelope-exclamation-fill',
                'color_code' => '#f59e0b',
                'bg'         => 'rgba(245, 158, 11, 0.1)',
                'hint'       => 'Filter unread',
                'url'        => route('admin.activities.messages.index', array_merge(request()->except(['read_status','page']), ['read_status'=>'unread'])).'#records-table',
                'is_active'  => $currRead === 'unread',
            ],
            [
                'label'      => 'Conversations',
                'value'      => number_format($summary['total_conversations']),
                'icon'       => 'bi-chat-heart-fill',
                'color_code' => '#0ea5e9',
                'bg'         => 'rgba(14, 165, 233, 0.1)',
                'hint'       => 'Top conversations',
                'url'        => '#top-conversations',
                'is_active'  => false,
            ],
            [
                'label'      => 'Unique Senders',
                'value'      => number_format($summary['unique_senders']),
                'icon'       => 'bi-person-fill-up',
                'color_code' => '#6366f1',
                'bg'         => 'rgba(99, 102, 241, 0.1)',
                'hint'       => 'Top senders',
                'url'        => '#top-senders',
                'is_active'  => false,
            ],
            [
                'label'      => 'Unique Receivers',
                'value'      => number_format($summary['unique_receivers']),
                'icon'       => 'bi-person-fill-down',
                'color_code' => '#ec4899',
                'bg'         => 'rgba(236, 72, 153, 0.1)',
                'hint'       => 'Top receivers',
                'url'        => '#top-receivers',
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
                <i class="bi bi-graph-up text-teal-500"></i>
                <span class="font-display font-semibold text-xs text-teal-500 uppercase tracking-wider">Message Volume Trends</span>
            </div>
            <span class="text-xs text-muted">{{ count($trends['labels']) }} days shown</span>
        </div>
        <div style="position: relative; height: 160px; width: 100%;">
            <canvas id="messageTrendsChart"></canvas>
        </div>
    </div>

    {{-- ── TOP REPORTS ── --}}
    <div class="row g-3">
        {{-- Top Senders --}}
        <div class="col-md-4" id="top-senders">
            <div class="rounded-xl border bs surface overflow-hidden">
                <div class="px-3 py-2 surface-2 border-b bs flex items-center justify-between">
                    <span class="font-display font-semibold text-xs text-teal-500 uppercase tracking-wider">Top Message Senders</span>
                    <i class="bi bi-send text-teal-500 text-xs"></i>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-[12px]">
                        <thead>
                            <tr class="surface-2 border-b bs text-[10px] uppercase tracking-wider t3 font-semibold">
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2 text-left">Member</th>
                                <th class="px-3 py-2 text-right">Sent</th>
                                <th class="px-3 py-2 text-right">Convos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/40">
                            @forelse($topSenders as $i => $m)
                            <tr class="hover:surface-2 transition">
                                <td class="px-3 py-2 text-xs font-bold t3 text-center">#{{ $i+1 }}</td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-xs t1 truncate" style="max-width:130px">{{ $m->member_name }}</div>
                                    <div class="text-[10px] t3 truncate">{{ $m->member_city ?? '' }}</div>
                                </td>
                                <td class="px-3 py-2 text-right font-bold text-teal-600">{{ number_format($m->total_sent) }}</td>
                                <td class="px-3 py-2 text-right text-xs t3">{{ number_format($m->total_conversations) }}</td>
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
                    <span class="font-display font-semibold text-xs text-purple-400 uppercase tracking-wider">Top Message Receivers</span>
                    <i class="bi bi-person-check text-purple-400 text-xs"></i>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-[12px]">
                        <thead>
                            <tr class="surface-2 border-b bs text-[10px] uppercase tracking-wider t3 font-semibold">
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2 text-left">Member</th>
                                <th class="px-3 py-2 text-right">Received</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/40">
                            @forelse($topReceivers as $i => $m)
                            <tr class="hover:surface-2 transition">
                                <td class="px-3 py-2 text-xs font-bold t3 text-center">#{{ $i+1 }}</td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-xs t1 truncate" style="max-width:150px">{{ $m->member_name }}</div>
                                    <div class="text-[10px] t3 truncate">{{ $m->member_city ?? '' }}</div>
                                </td>
                                <td class="px-3 py-2 text-right font-bold text-purple-600">{{ number_format($m->total_received) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="px-3 py-4 text-center text-xs t3">No data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Top Conversations --}}
        <div class="col-md-4" id="top-conversations">
            <div class="rounded-xl border bs surface overflow-hidden">
                <div class="px-3 py-2 surface-2 border-b bs flex items-center justify-between">
                    <span class="font-display font-semibold text-xs text-sky-500 uppercase tracking-wider">Most Active Conversations</span>
                    <i class="bi bi-chat-heart text-sky-500 text-xs"></i>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-[12px]">
                        <thead>
                            <tr class="surface-2 border-b bs text-[10px] uppercase tracking-wider t3 font-semibold">
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2 text-left">Participants</th>
                                <th class="px-3 py-2 text-right">Msgs</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/40">
                            @forelse($topConversations as $i => $conv)
                            <tr class="hover:surface-2 transition">
                                <td class="px-3 py-2 text-xs font-bold t3 text-center">#{{ $i+1 }}</td>
                                <td class="px-3 py-2">
                                    <div class="text-xs t1 font-medium truncate" style="max-width:150px">{{ $conv->user1_name }}</div>
                                    <div class="text-[10px] t3 truncate">↔ {{ $conv->user2_name }}</div>
                                </td>
                                <td class="px-3 py-2 text-right font-bold text-sky-600">{{ number_format($conv->total_messages) }}</td>
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
            <span class="font-display font-semibold text-xs text-teal-500 uppercase tracking-wider">
                Message Details — {{ number_format($total) }} records
                <span class="text-[10px] font-normal t3 normal-case ml-1">(content not shown)</span>
            </span>
            <div class="flex gap-2 items-center">
                <form method="GET" action="{{ route('admin.activities.messages.index') }}" class="d-flex gap-1">
                    @foreach(request()->except(['q','page']) as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm text-xs" placeholder="Search name/email…" style="width:200px">
                    <button type="submit" class="btn btn-sm btn-outline-secondary text-xs"><i class="bi bi-search"></i></button>
                </form>
                <select onchange="window.location=this.value" class="form-select form-select-sm text-xs" style="width:100px">
                    @foreach([10,20,50,100] as $pp)
                    <option value="{{ route('admin.activities.messages.index', array_merge(request()->except(['per_page','page']),['per_page'=>$pp])) }}"
                        {{ ($filters['per_page']??20)==$pp ? 'selected' : '' }}>{{ $pp }} / page</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-[12px]">
                <thead>
                    <tr class="surface-2 border-b bs text-[10px] uppercase tracking-wider t3 font-semibold">
                        <th class="px-3 py-2 text-left">Date</th>
                        <th class="px-3 py-2 text-left">Sender</th>
                        <th class="px-3 py-2 text-left">Receiver</th>
                        <th class="px-3 py-2 text-center">Type</th>
                        <th class="px-3 py-2 text-center">Read</th>
                        <th class="px-3 py-2 text-left">Sender City</th>
                        <th class="px-3 py-2 text-left">Receiver City</th>
                        <th class="px-3 py-2 text-left">Sender Membership</th>
                        <th class="px-3 py-2 text-left">Receiver Membership</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200/40">
                    @forelse($items as $item)
                    @php
                        $senderName   = $item->sender_name   ?? $displayName($item->sender_display_name??null,$item->sender_first_name??null,$item->sender_last_name??null);
                        $receiverName = $item->receiver_name ?? $displayName($item->receiver_display_name??null,$item->receiver_first_name??null,$item->receiver_last_name??null);
                        $at = $item->created_at ? \Carbon\Carbon::parse($item->created_at) : null;
                    @endphp
                    <tr class="hover:surface-2 transition border-b bs">
                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                            {{ $at ? $at->format('Y-m-d') : '—' }}
                            <div class="text-[10px] t3">{{ $at ? $at->format('H:i') : '' }}</div>
                        </td>
                        <td class="px-3 py-2.5">
                            @include('admin.components.peer-card', [
                                'name'   => $senderName,
                                'city'   => $item->sender_city ?? '',
                                'userId' => $item->sender_id ?? null,
                            ])
                        </td>
                        <td class="px-3 py-2.5">
                            @include('admin.components.peer-card', [
                                'name'   => $receiverName,
                                'city'   => $item->receiver_city ?? '',
                                'userId' => null,
                            ])
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            @if(($item->message_type ?? 'text') === 'media')
                                <span class="chip px-2 py-0.5 text-xs font-semibold bg-purple-50 text-purple-700 border-purple-200">
                                    <i class="bi bi-image me-1"></i>Media
                                </span>
                            @else
                                <span class="chip px-2 py-0.5 text-xs font-semibold bg-slate-50 text-slate-600 border-slate-200">
                                    <i class="bi bi-chat-text me-1"></i>Text
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            @if($item->is_read)
                                <span class="chip px-2 py-0.5 text-xs bg-emerald-50 text-emerald-700 border-emerald-200">Read</span>
                            @else
                                <span class="chip px-2 py-0.5 text-xs bg-amber-50 text-amber-700 border-amber-200">Unread</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs t3">{{ $item->sender_city ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-xs t3">{{ $item->receiver_city ?? '—' }}</td>
                        <td class="px-3 py-2.5 text-xs">
                            @if($item->sender_membership_status ?? null)
                                <span class="chip px-2 py-0.5 text-xs bg-indigo-50 text-indigo-700 border-indigo-200 font-medium capitalize">{{ $item->sender_membership_status }}</span>
                            @else <span class="t3">—</span> @endif
                        </td>
                        <td class="px-3 py-2.5 text-xs">
                            @if($item->receiver_membership_status ?? null)
                                <span class="chip px-2 py-0.5 text-xs bg-teal-50 text-teal-700 border-teal-200 font-medium capitalize">{{ $item->receiver_membership_status }}</span>
                            @else <span class="t3">—</span> @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-10 text-xs t3">
                            <i class="bi bi-chat-slash fs-4 d-block mb-2 opacity-30"></i>
                            No message records found for the selected filters.
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
    const labels        = @json($trends['labels']);
    const totals        = @json($trends['totals']);
    const conversations = @json($trends['conversations']);
    if (!labels.length) return;
    const ctx = document.getElementById('messageTrendsChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Messages Sent',
                    data: totals,
                    borderColor: '#0d9488',
                    backgroundColor: 'rgba(13,148,136,.08)',
                    tension: 0.35,
                    fill: true,
                    pointRadius: labels.length > 30 ? 0 : 3,
                    borderWidth: 2,
                },
                {
                    label: 'Active Conversations',
                    data: conversations,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,.05)',
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
                x: { ticks: { font: { size: 10 }, maxTicksLimit: 15, maxRotation: 0 }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { font: { size: 10 }, precision: 0 }, grid: { color: 'rgba(0,0,0,.05)' } },
            },
        },
    });
})();
</script>
@endpush
