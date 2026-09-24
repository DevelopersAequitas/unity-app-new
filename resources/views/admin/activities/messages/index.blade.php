@extends('admin.layouts.app')

@section('title', 'Message Analytics & Reporting')

@include('admin.partials.grid-head')

@push('styles')
<style>
/* ── Premium Analytics Card System ── */
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
    box-shadow: 0 2px 4px rgba(15, 23, 42, 0.02), 0 1px 2px rgba(15, 23, 42, 0.03);
    min-height: 110px;
    overflow: hidden;
}
.analytics-summary-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: transparent;
    transition: all 0.2s;
}
.analytics-summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px -6px rgba(15, 23, 42, 0.08), 0 4px 10px -2px rgba(15, 23, 42, 0.04);
}

/* Card color themes */
.card-teal:hover { border-color: #0d9488; }
.card-teal:hover::before, .card-teal.active-filter::before { background: #0d9488; }
.card-teal.active-filter { border-color: #0d9488; box-shadow: 0 0 0 2px rgba(13, 148, 136, 0.25); background: linear-gradient(180deg, rgba(13,148,136,0.04) 0%, #fff 100%); }

.card-indigo:hover { border-color: #6366f1; }
.card-indigo:hover::before, .card-indigo.active-filter::before { background: #6366f1; }
.card-indigo.active-filter { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25); background: linear-gradient(180deg, rgba(99,102,241,0.04) 0%, #fff 100%); }

.card-purple:hover { border-color: #a855f7; }
.card-purple:hover::before, .card-purple.active-filter::before { background: #a855f7; }
.card-purple.active-filter { border-color: #a855f7; box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.25); background: linear-gradient(180deg, rgba(168,85,247,0.04) 0%, #fff 100%); }

.card-emerald:hover { border-color: #10b981; }
.card-emerald:hover::before, .card-emerald.active-filter::before { background: #10b981; }
.card-emerald.active-filter { border-color: #10b981; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25); background: linear-gradient(180deg, rgba(16,185,129,0.04) 0%, #fff 100%); }

.card-amber:hover { border-color: #f59e0b; }
.card-amber:hover::before, .card-amber.active-filter::before { background: #f59e0b; }
.card-amber.active-filter { border-color: #f59e0b; box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.25); background: linear-gradient(180deg, rgba(245,158,11,0.04) 0%, #fff 100%); }

.card-sky:hover { border-color: #0ea5e9; }
.card-sky:hover::before, .card-sky.active-filter::before { background: #0ea5e9; }
.card-sky.active-filter { border-color: #0ea5e9; box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.25); background: linear-gradient(180deg, rgba(14,165,233,0.04) 0%, #fff 100%); }

.card-violet:hover { border-color: #8b5cf6; }
.card-violet:hover::before, .card-violet.active-filter::before { background: #8b5cf6; }
.card-violet.active-filter { border-color: #8b5cf6; box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.25); background: linear-gradient(180deg, rgba(139,92,246,0.04) 0%, #fff 100%); }

.card-rose:hover { border-color: #f43f5e; }
.card-rose:hover::before, .card-rose.active-filter::before { background: #f43f5e; }
.card-rose.active-filter { border-color: #f43f5e; box-shadow: 0 0 0 2px rgba(244, 63, 94, 0.25); background: linear-gradient(180deg, rgba(244,63,94,0.04) 0%, #fff 100%); }

.analytics-summary-card .icon-box {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
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
    font-weight: 700;
    color: #64748b;
}
.analytics-summary-card .stat-value {
    font-size: 26px;
    font-weight: 800;
    line-height: 1.15;
    color: #0f172a;
    letter-spacing: -0.02em;
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
}
.analytics-summary-card .card-hint {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 4px;
    font-weight: 600;
    transition: color 0.15s;
}
.card-teal:hover .card-hint, .card-teal.active-filter .card-hint { color: #0d9488; }
.card-indigo:hover .card-hint, .card-indigo.active-filter .card-hint { color: #6366f1; }
.card-purple:hover .card-hint, .card-purple.active-filter .card-hint { color: #a855f7; }
.card-emerald:hover .card-hint, .card-emerald.active-filter .card-hint { color: #10b981; }
.card-amber:hover .card-hint, .card-amber.active-filter .card-hint { color: #d97706; }
.card-sky:hover .card-hint, .card-sky.active-filter .card-hint { color: #0ea5e9; }
.card-violet:hover .card-hint, .card-violet.active-filter .card-hint { color: #8b5cf6; }
.card-rose:hover .card-hint, .card-rose.active-filter .card-hint { color: #f43f5e; }

/* Filter & Header Sections */
.filter-section {
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

.chart-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 18px 20px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}

.privacy-banner {
    background: linear-gradient(135deg, rgba(13,148,136,0.08) 0%, rgba(16,185,129,0.06) 100%);
    border: 1px solid rgba(13,148,136,0.22);
    border-radius: 12px;
    padding: 12px 18px;
    font-size: 12.5px;
    color: #0f766e;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

/* Modern Rank Badges */
.rank-badge {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
}
.rank-1 { background: #fef3c7; color: #b45309; }
.rank-2 { background: #f1f5f9; color: #475569; }
.rank-3 { background: #ffedd5; color: #c2410c; }
.rank-other { background: #f8fafc; color: #94a3b8; }

/* Interactive Preview Button */
.msg-preview-btn {
    transition: all 0.18s ease;
    cursor: pointer;
    border-radius: 8px;
    padding: 3px 8px;
    font-weight: 600;
    font-size: 11.5px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.msg-preview-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0,0,0,0.08);
}
.msg-bubble-preview {
    background: #f1f5f9;
    border-radius: 16px 16px 16px 4px;
    padding: 14px 18px;
    font-size: 14px;
    line-height: 1.6;
    color: #1e293b;
    border: 1px solid #e2e8f0;
    word-break: break-word;
    white-space: pre-wrap;
}
.avatar-circle {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 12px;
    color: #ffffff;
    flex-shrink: 0;
}
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

    // Color generator for avatar initials
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
@endphp

<div class="space-y-4">
    @include('admin.activities.partials.header', [
        'title' => 'Messages',
        'actionButton' => '<a href="' . route('admin.activities.messages.export', request()->except(['page'])) . '" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1.5 px-3 py-2 fw-semibold" style="border-radius: 10px;"><i class="bi bi-file-earmark-arrow-down"></i> Export CSV</a>'
    ])

    {{-- Privacy & Inspection Notice --}}
    <div class="privacy-banner">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-shield-check-fill fs-5" style="color: #0d9488;"></i>
            <span><strong>Message Inspector Ready:</strong> Click on any message <span class="badge bg-white text-dark border px-2 py-0.5">💬 Text</span> or <span class="badge bg-white text-purple-700 border px-2 py-0.5">🖼️ Media</span> badge to view its full content, attachment image preview, and details.</span>
        </div>
        <span class="badge bg-teal text-white px-2.5 py-1" style="background: #0d9488 !important; border-radius: 20px;">Admin Privilege</span>
    </div>

    {{-- ── FILTERS SECTION ── --}}
    <div class="filter-section">
        <form method="GET" action="{{ route('admin.activities.messages.index') }}" id="filterForm">
            {{-- Preset Pills --}}
            <div class="mb-3">
                <div class="filter-label mb-2"><i class="bi bi-calendar-event me-1"></i> Quick Date Range:</div>
                <div class="preset-pills">
                    <a href="{{ route('admin.activities.messages.index', array_merge(request()->except(['date_preset','from_date','to_date','page']), ['date_preset'=>'all'])) }}"
                       class="preset-pill {{ ($activePreset==='' || $activePreset==='all') ? 'active' : '' }}">All Time</a>
                    @foreach($presets as $key => $label)
                        <a href="{{ route('admin.activities.messages.index', array_merge(request()->except(['date_preset','from_date','to_date','page']), ['date_preset'=>$key])) }}"
                           class="preset-pill {{ $activePreset===$key ? 'active' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <input type="hidden" name="date_preset" value="{{ $activePreset }}">
            </div>

            {{-- Custom Date Inputs & Filters --}}
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <label class="filter-label">From Date</label>
                    <input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" class="form-control form-control-sm" style="border-radius: 8px;">
                </div>
                <div class="col-6 col-md-3">
                    <label class="filter-label">To Date</label>
                    <input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" class="form-control form-control-sm" style="border-radius: 8px;">
                </div>
                <div class="col-12 col-md-6 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary px-4 fw-semibold" style="border-radius: 8px; background: #0d9488; border-color: #0d9488;">
                        <i class="bi bi-funnel me-1"></i> Apply Filter
                    </button>
                    <a href="{{ route('admin.activities.messages.index') }}" class="btn btn-sm btn-light border px-3 fw-semibold text-muted" style="border-radius: 8px;">
                        <i class="bi bi-x-circle me-1"></i> Clear
                    </a>
                </div>
            </div>

            {{-- Advanced Filter Dropdowns --}}
            <div class="border-top pt-3">
                <div class="filter-label mb-2"><i class="bi bi-sliders me-1"></i> Advanced Filters:</div>
                <div class="row g-2">
                    <div class="col-6 col-md-2">
                        <label class="text-muted small" style="font-size: 11px;">Message Type</label>
                        <select name="message_type" class="form-select form-select-sm" style="border-radius: 8px;">
                            <option value="">Any Type</option>
                            <option value="text"  {{ $currType==='text'  ? 'selected' : '' }}>💬 Text Only</option>
                            <option value="media" {{ $currType==='media' ? 'selected' : '' }}>🖼️ Media Attachments</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="text-muted small" style="font-size: 11px;">Read Status</label>
                        <select name="read_status" class="form-select form-select-sm" style="border-radius: 8px;">
                            <option value="">Any Status</option>
                            <option value="read"   {{ $currRead==='read'   ? 'selected' : '' }}>✓✓ Read</option>
                            <option value="unread" {{ $currRead==='unread' ? 'selected' : '' }}>✓ Unread</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="text-muted small" style="font-size: 11px;">Sender Name</label>
                        <input type="text" name="sender_name" value="{{ $filters['sender_name'] ?? '' }}" class="form-control form-control-sm" placeholder="Sender name…" style="border-radius: 8px;">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="text-muted small" style="font-size: 11px;">Sender City</label>
                        <input type="text" name="sender_city" value="{{ $filters['sender_city'] ?? '' }}" class="form-control form-control-sm" placeholder="City…" style="border-radius: 8px;">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="text-muted small" style="font-size: 11px;">Receiver Name</label>
                        <input type="text" name="receiver_name" value="{{ $filters['receiver_name'] ?? '' }}" class="form-control form-control-sm" placeholder="Receiver name…" style="border-radius: 8px;">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="text-muted small" style="font-size: 11px;">Circle</label>
                        <select name="circle_id" class="form-select form-select-sm" style="border-radius: 8px;">
                            <option value="">All Circles</option>
                            @foreach($circles as $circle)
                                <option value="{{ $circle->id }}" {{ ($filters['circle_id']??'')==$circle->id ? 'selected' : '' }}>{{ $circle->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- ── 8 INTERACTIVE SUMMARY CARDS ── --}}
    @php
        $buildUrl = function(array $params) {
            return route('admin.activities.messages.index', array_merge(request()->except(['page']), $params));
        };
    @endphp
    <div class="row g-3">
        {{-- 1. Total Messages --}}
        <div class="col-6 col-md-3">
            <a href="{{ $buildUrl(['message_type'=>'', 'read_status'=>'']) }}#records-table"
               class="analytics-summary-card card-teal {{ ($currType==='' && $currRead==='') ? 'active-filter' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Total Messages</div>
                        <div class="stat-value text-teal-600" style="color: #0d9488;">{{ number_format($summary['total_messages']) }}</div>
                    </div>
                    <div class="icon-box" style="background: rgba(13,148,136,0.1); color: #0d9488;">
                        <i class="bi bi-chat-dots-fill"></i>
                    </div>
                </div>
                <div class="card-hint">
                    <span>Show all records</span> <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        {{-- 2. Text Messages --}}
        <div class="col-6 col-md-3">
            <a href="{{ $buildUrl(['message_type'=>'text']) }}#records-table"
               class="analytics-summary-card card-indigo {{ $currType==='text' ? 'active-filter' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Text Messages</div>
                        <div class="stat-value" style="color: #4f46e5;">{{ number_format($summary['total_text']) }}</div>
                    </div>
                    <div class="icon-box" style="background: rgba(99,102,241,0.1); color: #6366f1;">
                        <i class="bi bi-chat-text-fill"></i>
                    </div>
                </div>
                <div class="card-hint">
                    <span>Filter text msgs</span> <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        {{-- 3. Media / Attachments --}}
        <div class="col-6 col-md-3">
            <a href="{{ $buildUrl(['message_type'=>'media']) }}#records-table"
               class="analytics-summary-card card-purple {{ $currType==='media' ? 'active-filter' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Media / Attachments</div>
                        <div class="stat-value" style="color: #9333ea;">{{ number_format($summary['total_media']) }}</div>
                    </div>
                    <div class="icon-box" style="background: rgba(168,85,247,0.1); color: #a855f7;">
                        <i class="bi bi-paperclip"></i>
                    </div>
                </div>
                <div class="card-hint">
                    <span>Filter media msgs</span> <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        {{-- 4. Read Messages --}}
        <div class="col-6 col-md-3">
            <a href="{{ $buildUrl(['read_status'=>'read']) }}#records-table"
               class="analytics-summary-card card-emerald {{ $currRead==='read' ? 'active-filter' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Read Messages</div>
                        <div class="stat-value text-emerald-600" style="color: #10b981;">{{ number_format($summary['total_read']) }}</div>
                    </div>
                    <div class="icon-box" style="background: rgba(16,185,129,0.1); color: #10b981;">
                        <i class="bi bi-check2-all"></i>
                    </div>
                </div>
                <div class="card-hint">
                    <span>Filter read msgs</span> <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        {{-- 5. Unread Messages --}}
        <div class="col-6 col-md-3">
            <a href="{{ $buildUrl(['read_status'=>'unread']) }}#records-table"
               class="analytics-summary-card card-amber {{ $currRead==='unread' ? 'active-filter' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Unread Messages</div>
                        <div class="stat-value" style="color: #d97706;">{{ number_format($summary['total_unread']) }}</div>
                    </div>
                    <div class="icon-box" style="background: rgba(245,158,11,0.1); color: #f59e0b;">
                        <i class="bi bi-envelope-exclamation-fill"></i>
                    </div>
                </div>
                <div class="card-hint">
                    <span>Filter unread</span> <i class="bi bi-arrow-right"></i>
                </div>
            </a>
        </div>

        {{-- 6. Conversations --}}
        <div class="col-6 col-md-3">
            <a href="#top-conversations"
               class="analytics-summary-card card-sky">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Conversations</div>
                        <div class="stat-value" style="color: #0284c7;">{{ number_format($summary['total_conversations']) }}</div>
                    </div>
                    <div class="icon-box" style="background: rgba(14,165,233,0.1); color: #0ea5e9;">
                        <i class="bi bi-chat-heart-fill"></i>
                    </div>
                </div>
                <div class="card-hint">
                    <span>Top conversations</span> <i class="bi bi-arrow-down"></i>
                </div>
            </a>
        </div>

        {{-- 7. Unique Senders --}}
        <div class="col-6 col-md-3">
            <a href="#top-senders"
               class="analytics-summary-card card-violet">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Unique Senders</div>
                        <div class="stat-value" style="color: #7c3aed;">{{ number_format($summary['unique_senders']) }}</div>
                    </div>
                    <div class="icon-box" style="background: rgba(139,92,246,0.1); color: #8b5cf6;">
                        <i class="bi bi-person-up"></i>
                    </div>
                </div>
                <div class="card-hint">
                    <span>Top senders</span> <i class="bi bi-arrow-down"></i>
                </div>
            </a>
        </div>

        {{-- 8. Unique Receivers --}}
        <div class="col-6 col-md-3">
            <a href="#top-receivers"
               class="analytics-summary-card card-rose">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Unique Receivers</div>
                        <div class="stat-value" style="color: #e11d48;">{{ number_format($summary['unique_receivers']) }}</div>
                    </div>
                    <div class="icon-box" style="background: rgba(244,63,94,0.1); color: #f43f5e;">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                </div>
                <div class="card-hint">
                    <span>Top receivers</span> <i class="bi bi-arrow-down"></i>
                </div>
            </a>
        </div>
    </div>

    {{-- ── TREND CHART ── --}}
    <div class="chart-card" id="trends-section">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center gap-2">
                <div style="width: 28px; height: 28px; border-radius: 8px; background: rgba(13,148,136,0.12); color: #0d9488; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <span class="fw-bold" style="color: #0f172a; font-size: 0.9rem;">Message Volume Trends</span>
            </div>
            <span class="badge bg-light text-muted border px-2 py-1" style="font-size: 11px;">{{ count($trends['labels']) }} days timeline</span>
        </div>
        <div style="position: relative; height: 160px; width: 100%;">
            <canvas id="messageTrendsChart"></canvas>
        </div>
    </div>

    {{-- ── LEADERBOARDS / TOP REPORTS ── --}}
    <div class="row g-3">
        {{-- Top Senders --}}
        <div class="col-md-4" id="top-senders">
            <div class="card border h-100 shadow-sm" style="border-radius: 14px; overflow: hidden;">
                <div class="card-header bg-white border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase" style="letter-spacing: 0.04em; font-size: 11.5px;">
                        <i class="bi bi-send-fill text-teal-600 me-1.5" style="color: #0d9488;"></i> Top Message Senders
                    </span>
                    <span class="badge bg-light text-dark border">Top 10</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle" style="font-size: 12px;">
                        <thead class="table-light">
                            <tr style="font-size: 10.5px; color: #64748b;" class="text-uppercase">
                                <th class="ps-3 py-2" style="width: 36px;">#</th>
                                <th class="py-2">Member</th>
                                <th class="text-end py-2">Sent</th>
                                <th class="text-end pe-3 py-2">Convos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topSenders as $i => $m)
                            <tr>
                                <td class="ps-3 py-2">
                                    <span class="rank-badge {{ $i===0?'rank-1':($i===1?'rank-2':($i===2?'rank-3':'rank-other')) }}">
                                        {{ $i+1 }}
                                    </span>
                                </td>
                                <td class="py-2">
                                    <div class="fw-bold text-dark truncate" style="max-width: 130px;">
                                        @if(!empty($m->member_id))
                                            <a href="#" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline" onclick="event.preventDefault(); openActivityPeerModal('{{ $m->member_id }}', event);">
                                                {{ $m->member_name }}
                                            </a>
                                        @else
                                            {{ $m->member_name }}
                                        @endif
                                    </div>
                                    <div class="text-muted truncate" style="font-size: 10.5px;">{{ $m->member_city ?? '—' }}</div>
                                </td>
                                <td class="text-end py-2 fw-bold" style="color: #0d9488;">{{ number_format($m->total_sent) }}</td>
                                <td class="text-end pe-3 py-2 text-muted">{{ number_format($m->total_conversations) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center py-4 text-muted">No sender data found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Top Receivers --}}
        <div class="col-md-4" id="top-receivers">
            <div class="card border h-100 shadow-sm" style="border-radius: 14px; overflow: hidden;">
                <div class="card-header bg-white border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase" style="letter-spacing: 0.04em; font-size: 11.5px;">
                        <i class="bi bi-person-check-fill me-1.5" style="color: #8b5cf6;"></i> Top Message Receivers
                    </span>
                    <span class="badge bg-light text-dark border">Top 10</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle" style="font-size: 12px;">
                        <thead class="table-light">
                            <tr style="font-size: 10.5px; color: #64748b;" class="text-uppercase">
                                <th class="ps-3 py-2" style="width: 36px;">#</th>
                                <th class="py-2">Member</th>
                                <th class="text-end pe-3 py-2">Received</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topReceivers as $i => $m)
                            <tr>
                                <td class="ps-3 py-2">
                                    <span class="rank-badge {{ $i===0?'rank-1':($i===1?'rank-2':($i===2?'rank-3':'rank-other')) }}">
                                        {{ $i+1 }}
                                    </span>
                                </td>
                                <td class="py-2">
                                    <div class="fw-bold text-dark truncate" style="max-width: 140px;">
                                        @if(!empty($m->member_id))
                                            <a href="#" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline" onclick="event.preventDefault(); openActivityPeerModal('{{ $m->member_id }}', event);">
                                                {{ $m->member_name }}
                                            </a>
                                        @else
                                            {{ $m->member_name }}
                                        @endif
                                    </div>
                                    <div class="text-muted truncate" style="font-size: 10.5px;">{{ $m->member_city ?? '—' }}</div>
                                </td>
                                <td class="text-end pe-3 py-2 fw-bold" style="color: #8b5cf6;">{{ number_format($m->total_received) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center py-4 text-muted">No receiver data found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Top Conversations --}}
        <div class="col-md-4" id="top-conversations">
            <div class="card border h-100 shadow-sm" style="border-radius: 14px; overflow: hidden;">
                <div class="card-header bg-white border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark small text-uppercase" style="letter-spacing: 0.04em; font-size: 11.5px;">
                        <i class="bi bi-chat-heart-fill text-sky-500 me-1.5" style="color: #0ea5e9;"></i> Most Active Chats
                    </span>
                    <span class="badge bg-light text-dark border">Top 10</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle" style="font-size: 12px;">
                        <thead class="table-light">
                            <tr style="font-size: 10.5px; color: #64748b;" class="text-uppercase">
                                <th class="ps-3 py-2" style="width: 36px;">#</th>
                                <th class="py-2">Participants</th>
                                <th class="text-end pe-3 py-2">Msgs</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topConversations as $i => $conv)
                            <tr>
                                <td class="ps-3 py-2">
                                    <span class="rank-badge {{ $i===0?'rank-1':($i===1?'rank-2':($i===2?'rank-3':'rank-other')) }}">
                                        {{ $i+1 }}
                                    </span>
                                </td>
                                <td class="py-2">
                                    <div class="fw-bold text-dark truncate" style="max-width: 140px;">{{ $conv->user1_name }}</div>
                                    <div class="text-muted truncate" style="font-size: 10.5px;">↔ {{ $conv->user2_name }}</div>
                                </td>
                                <td class="text-end pe-3 py-2 fw-bold" style="color: #0ea5e9;">
                                    <div class="d-flex align-items-center justify-content-end gap-1.5">
                                        <span>{{ number_format($conv->total_messages) }}</span>
                                        @if(!empty($conv->chat_id))
                                        <button type="button" class="btn btn-xs btn-outline-info py-0 px-1.5 rounded-pill" style="font-size: 10px;"
                                                onclick="openFullChatModal('{{ $conv->chat_id }}')" title="Open Full Chat History">
                                            <i class="bi bi-chat-dots"></i>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center py-4 text-muted">No conversation data found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ── DETAILED TABLE SECTION ── --}}
    <div class="card border shadow-sm" style="border-radius: 16px; overflow: hidden;" id="records-table">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-dark" style="font-size: 0.95rem;">Message Activity Details</span>
                <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 11px;">{{ number_format($total) }} Records</span>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <form method="GET" action="{{ route('admin.activities.messages.index') }}" class="d-flex gap-1">
                    @foreach(request()->except(['q','page']) as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <div class="input-group input-group-sm" style="width: 210px;">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control border-start-0 ps-0" placeholder="Search name/email…">
                    </div>
                </form>
                <select onchange="window.location=this.value" class="form-select form-select-sm" style="width: 110px; border-radius: 8px;">
                    @foreach([10,20,50,100] as $pp)
                    <option value="{{ route('admin.activities.messages.index', array_merge(request()->except(['per_page','page']),['per_page'=>$pp])) }}"
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
                        <th class="py-3">Sender</th>
                        <th class="py-3">Receiver</th>
                        <th class="text-center py-3">Message Type</th>
                        <th class="text-center py-3">Status</th>
                        <th class="py-3">Sender City</th>
                        <th class="py-3">Receiver City</th>
                        <th class="py-3">Membership</th>
                        <th class="text-end pe-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($items as $item)
                    @php
                        $senderName   = $item->sender_name   ?? $displayName($item->sender_display_name??null,$item->sender_first_name??null,$item->sender_last_name??null);
                        $receiverName = $item->receiver_name ?? $displayName($item->receiver_display_name??null,$item->receiver_first_name??null,$item->receiver_last_name??null);
                        $at = $item->created_at ? \Carbon\Carbon::parse($item->created_at) : null;
                        $isMedia = ($item->message_type ?? 'text') === 'media';

                        // Parse attachments safely
                        $rawAtts = $item->attachments;
                        if (is_string($rawAtts)) {
                            $decodedAtts = json_decode($rawAtts, true);
                            $attachmentsArray = is_array($decodedAtts) ? $decodedAtts : [];
                        } elseif (is_array($rawAtts)) {
                            $attachmentsArray = $rawAtts;
                        } else {
                            $attachmentsArray = [];
                        }

                        // Encode message payload for the interactive modal
                        $payload = [
                            'id' => $item->id,
                            'chat_id' => $item->chat_id,
                            'sender_name' => $senderName,
                            'sender_email' => $item->sender_email ?? '—',
                            'sender_city' => $item->sender_city ?? '—',
                            'sender_membership' => $item->sender_membership_status ?? '—',
                            'receiver_name' => $receiverName,
                            'receiver_email' => $item->receiver_email ?? '—',
                            'receiver_city' => $item->receiver_city ?? '—',
                            'receiver_membership' => $item->receiver_membership_status ?? '—',
                            'is_media' => $isMedia,
                            'is_read' => (bool)$item->is_read,
                            'created_at' => $at ? $at->format('M d, Y h:i A') : '—',
                            'content' => $item->content ?? '',
                            'attachments' => $attachmentsArray,
                        ];
                        $b64Payload = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
                    @endphp
                    <tr>
                        <td class="ps-4 py-3 whitespace-nowrap">
                            <div class="fw-semibold text-dark">{{ $at ? $at->format('M d, Y') : '—' }}</div>
                            <div class="text-muted small" style="font-size: 11px;"><i class="bi bi-clock me-1"></i>{{ $at ? $at->format('h:i A') : '' }}</div>
                        </td>
                        <td class="py-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle" style="background: {{ $avatarBg($senderName) }};">
                                    {{ $initials($senderName) }}
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $senderName }}</div>
                                    <div class="text-muted small" style="font-size: 11px;">{{ $item->sender_email ?? '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle" style="background: {{ $avatarBg($receiverName) }};">
                                    {{ $initials($receiverName) }}
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $receiverName }}</div>
                                    <div class="text-muted small" style="font-size: 11px;">{{ $item->receiver_email ?? '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 text-center">
                            @if($isMedia)
                                <button type="button" class="btn btn-sm btn-light border py-1 px-2.5 rounded-pill d-inline-flex align-items-center gap-1.5 msg-preview-btn"
                                        style="border-color: #f3e8ff !important; background: #faf5ff !important; color: #9333ea;"
                                        data-payload="{{ $b64Payload }}" onclick="openMessagePreview(this)">
                                    <i class="bi bi-image-fill"></i>
                                    <span>Media</span>
                                    <span class="badge bg-purple text-white rounded-pill ms-0.5" style="background: #9333ea !important; font-size: 9px;">Image</span>
                                </button>
                            @else
                                <button type="button" class="btn btn-sm btn-light border py-1 px-2.5 rounded-pill d-inline-flex align-items-center gap-1.5 msg-preview-btn"
                                        style="border-color: #e2e8f0 !important; background: #f8fafc !important; color: #0284c7;"
                                        data-payload="{{ $b64Payload }}" onclick="openMessagePreview(this)">
                                    <i class="bi bi-chat-text-fill text-info"></i>
                                    <span class="fw-semibold">Text</span>
                                    <i class="bi bi-eye text-muted ms-0.5" style="font-size: 10px;"></i>
                                </button>
                            @endif
                        </td>
                        <td class="py-3 text-center">
                            @if($item->is_read)
                                <span class="badge px-2.5 py-1 rounded-pill" style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; font-weight: 600;">
                                    <i class="bi bi-check2-all me-1"></i>Read
                                </span>
                            @else
                                <span class="badge px-2.5 py-1 rounded-pill" style="background: #fffbeb; color: #d97706; border: 1px solid #fde68a; font-weight: 600;">
                                    <i class="bi bi-check me-1"></i>Unread
                                </span>
                            @endif
                        </td>
                        <td class="py-3 text-muted">{{ $item->sender_city ?? '—' }}</td>
                        <td class="py-3 text-muted">{{ $item->receiver_city ?? '—' }}</td>
                        <td class="py-3">
                            <span class="badge px-2 py-1 rounded-pill" style="background: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe; font-size: 11px;">
                                {{ $item->sender_membership_status ?? 'Peer' }}
                            </span>
                        </td>
                        <td class="text-end pe-4 py-3 whitespace-nowrap">
                            <div class="d-inline-flex align-items-center gap-1.5">
                                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2.5 rounded-pill d-inline-flex align-items-center gap-1 fw-semibold"
                                        data-payload="{{ $b64Payload }}" onclick="openMessagePreview(this)" style="font-size: 11.5px;" title="Inspect single message">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                @if(!empty($item->chat_id))
                                <button type="button" class="btn btn-sm btn-primary py-1 px-2.5 rounded-pill d-inline-flex align-items-center gap-1 fw-semibold"
                                        style="background: #0d9488; border-color: #0d9488; font-size: 11.5px;"
                                        onclick="openFullChatModal('{{ $item->chat_id }}')" title="View Full Chat History">
                                    <i class="bi bi-chat-dots-fill"></i> View Full Chat
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <div class="py-3">
                                <i class="bi bi-chat-dots fs-1 d-block mb-2 text-muted opacity-50"></i>
                                <div class="fw-semibold">No message records found</div>
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

{{-- ── MESSAGE & MEDIA PREVIEW MODAL ── --}}
<div class="modal fade" id="messageDetailModal" tabindex="-1" aria-labelledby="messageDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0" style="border-radius: 20px; overflow: hidden;">
            {{-- Modal Header --}}
            <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);">
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 34px; height: 34px; border-radius: 10px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 18px;">
                        <i class="bi bi-chat-left-quote-fill"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="messageDetailModalLabel" style="font-size: 1.05rem;">Message Inspection</h5>
                        <div class="small text-white-50" id="modalSentAt" style="font-size: 11.5px;">Sent at —</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Modal Body --}}
            <div class="modal-body p-4" style="background: #f8fafc;">
                {{-- Participants Row --}}
                <div class="card border-0 shadow-sm mb-4" style="border-radius: 14px; background: #ffffff;">
                    <div class="card-body p-3">
                        <div class="row align-items-center text-center text-md-start g-3">
                            {{-- Sender --}}
                            <div class="col-md-5">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="avatar-circle" id="modalSenderAvatar" style="background: #6366f1; width: 42px; height: 42px; font-size: 15px;">S</div>
                                    <div>
                                        <div class="badge bg-light text-muted border mb-1" style="font-size: 10px;">SENDER</div>
                                        <div class="fw-bold text-dark" id="modalSenderName" style="font-size: 13.5px;">Sender Name</div>
                                        <div class="text-muted small" id="modalSenderMeta" style="font-size: 11px;">city • email</div>
                                    </div>
                                </div>
                            </div>
                            {{-- Arrow Indicator --}}
                            <div class="col-md-2 text-center text-muted">
                                <div class="d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9;">
                                    <i class="bi bi-arrow-right-short fs-4 text-teal-600" style="color: #0d9488;"></i>
                                </div>
                            </div>
                            {{-- Receiver --}}
                            <div class="col-md-5">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="avatar-circle" id="modalReceiverAvatar" style="background: #a855f7; width: 42px; height: 42px; font-size: 15px;">R</div>
                                    <div>
                                        <div class="badge bg-light text-muted border mb-1" style="font-size: 10px;">RECEIVER</div>
                                        <div class="fw-bold text-dark" id="modalReceiverName" style="font-size: 13.5px;">Receiver Name</div>
                                        <div class="text-muted small" id="modalReceiverMeta" style="font-size: 11px;">city • email</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Status Pills --}}
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="filter-label mb-0">Message Content:</span>
                        <span id="modalTypeBadge" class="badge bg-light text-dark border">Text</span>
                    </div>
                    <div id="modalReadBadge">
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Read</span>
                    </div>
                </div>

                {{-- Text Message Content --}}
                <div id="modalTextSection" class="mb-3">
                    <div class="msg-bubble-preview shadow-sm" id="modalTextContent">
                        <!-- Message text here -->
                    </div>
                </div>

                {{-- Media & Attachment Preview Section --}}
                <div id="modalMediaSection" class="mb-3 d-none">
                    <div class="card border shadow-sm" style="border-radius: 14px; background: #ffffff; overflow: hidden;">
                        <div class="card-header bg-light border-bottom py-2 px-3 d-flex justify-content-between align-items-center">
                            <span class="fw-bold small text-dark"><i class="bi bi-paperclip me-1 text-purple"></i> Attached Media & Files</span>
                            <span class="badge bg-purple text-white" id="modalMediaCount" style="background: #9333ea !important;">1 File</span>
                        </div>
                        <div class="card-body p-3 text-center" id="modalMediaContainer">
                            <!-- Image or file attachment cards inserted here -->
                        </div>
                    </div>
                </div>

                {{-- Empty State fallback --}}
                <div id="modalEmptyNotice" class="alert alert-light border text-center text-muted d-none" style="border-radius: 12px;">
                    <i class="bi bi-info-circle me-1"></i> No text or attachment payload recorded for this message.
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="modal-footer bg-white px-4 py-3 d-flex justify-content-between flex-wrap gap-2">
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" id="copyMsgBtn" onclick="copyMessageText()">
                        <i class="bi bi-clipboard me-1"></i> Copy Text
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-primary px-3 fw-semibold d-inline-flex align-items-center gap-1.5"
                            style="background: #0d9488; border-color: #0d9488; border-radius: 8px;"
                            id="previewOpenFullChatBtn" onclick="openFullChatFromPreview()">
                        <i class="bi bi-chat-dots-fill"></i> View Full Chat
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary px-3 fw-semibold" data-bs-dismiss="modal" style="border-radius: 8px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── FULL CONVERSATION / CHAT MODAL ── --}}
<div class="modal fade" id="fullChatModal" tabindex="-1" aria-labelledby="fullChatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content shadow-xl border-0" style="border-radius: 20px; overflow: hidden; height: 85vh; max-height: 850px; display: flex; flex-direction: column;">
            
            {{-- Chat Header --}}
            <div class="modal-header text-white px-4 py-3 border-0 flex-shrink-0" style="background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);">
                <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 20px;">
                            <i class="bi bi-chat-left-text-fill"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h5 class="modal-title fw-bold mb-0 text-white" id="fullChatModalLabel" style="font-size: 1.15rem;">Full Chat History</h5>
                                <span class="badge bg-white text-teal-800 fw-bold px-2.5 py-1 rounded-pill" style="color: #0f766e !important; font-size: 11px;" id="chatTotalCountBadge">0 Messages</span>
                            </div>
                            <div class="text-white-50 small mt-0.5" id="chatParticipantsSub" style="font-size: 11.5px;">User 1 ↔ User 2</div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm" style="width: 220px;">
                            <span class="input-group-text bg-white border-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="chatSearchInput" class="form-control border-0" placeholder="Search in chat…" oninput="filterChatMessages(this.value)">
                        </div>
                        <button type="button" class="btn btn-sm btn-light border-0 px-2.5 py-1.5 text-teal-900 fw-semibold" style="border-radius: 8px;" onclick="reloadCurrentChat()" title="Refresh Chat">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
            </div>

            {{-- Participants Info Strip --}}
            <div class="bg-light border-bottom px-4 py-2.5 flex-shrink-0">
                <div class="row align-items-center g-2 text-center text-md-start">
                    <div class="col-md-5">
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-circle" id="chatU1Avatar" style="background: #6366f1; width: 32px; height: 32px; font-size: 12px;">U1</div>
                            <div class="truncate">
                                <span class="badge bg-white text-secondary border me-1" style="font-size: 9.5px;">PARTICIPANT 1</span>
                                <strong class="text-dark" id="chatU1Name" style="font-size: 12.5px;">User 1</strong>
                                <span class="text-muted small ms-1" id="chatU1Meta" style="font-size: 11px;">(city)</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 text-center text-muted d-none d-md-block">
                        <span class="badge bg-white text-muted border px-2 py-1" style="font-size: 11px;">↔ Chat Thread</span>
                    </div>
                    <div class="col-md-5">
                        <div class="d-flex align-items-center justify-content-md-end gap-2">
                            <div class="text-md-end truncate">
                                <span class="badge bg-white text-secondary border me-1" style="font-size: 9.5px;">PARTICIPANT 2</span>
                                <strong class="text-dark" id="chatU2Name" style="font-size: 12.5px;">User 2</strong>
                                <span class="text-muted small ms-1" id="chatU2Meta" style="font-size: 11px;">(city)</span>
                            </div>
                            <div class="avatar-circle" id="chatU2Avatar" style="background: #0d9488; width: 32px; height: 32px; font-size: 12px;">U2</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Chat Scrollable Body --}}
            <div class="modal-body p-4 flex-grow-1 overflow-auto" id="fullChatScrollContainer" style="background: #f1f5f9; background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size: 20px 20px;">
                {{-- Loader --}}
                <div id="fullChatLoader" class="text-center py-5">
                    <div class="spinner-border text-teal-600 mb-2" role="status" style="color: #0d9488; width: 2.5rem; height: 2.5rem;"></div>
                    <div class="text-muted small fw-semibold">Loading conversation history…</div>
                </div>

                {{-- Empty state --}}
                <div id="fullChatEmpty" class="text-center py-5 d-none">
                    <div class="p-4 bg-white rounded-4 shadow-sm d-inline-block" style="max-width: 360px;">
                        <i class="bi bi-chat-dots fs-1 text-muted opacity-50 mb-2 d-block"></i>
                        <h6 class="fw-bold text-dark">No Messages Found</h6>
                        <p class="text-muted small mb-0">There are no message records stored for this conversation thread.</p>
                    </div>
                </div>

                {{-- Messages Stream Container --}}
                <div id="fullChatMessagesStream" class="d-flex flex-column gap-3 d-none" style="max-width: 860px; margin: 0 auto;">
                    <!-- Chat bubbles injected here dynamically -->
                </div>
            </div>

            {{-- Chat Footer --}}
            <div class="modal-footer bg-white px-4 py-2.5 border-top d-flex justify-content-between align-items-center flex-shrink-0">
                <div class="text-muted small" style="font-size: 11.5px;" id="chatLastActiveInfo">
                    <i class="bi bi-shield-lock text-success me-1"></i> Admin read-only conversation inspector
                </div>
                <button type="button" class="btn btn-sm btn-secondary px-4 fw-semibold" data-bs-dismiss="modal" style="border-radius: 8px;">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Trend Chart ──
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

// ── Interactive Message Modal ──
let currentMessageText = '';
let currentActiveChatId = '';
let fullChatCachedData = null;

function decodePayload(raw) {
    if (!raw) return {};
    try {
        if (raw.startsWith('{') || raw.startsWith('[')) {
            return JSON.parse(raw);
        }
        const binary = atob(raw);
        const bytes = Uint8Array.from(binary, c => c.charCodeAt(0));
        const decoded = new TextDecoder('utf-8').decode(bytes);
        return JSON.parse(decoded);
    } catch(e) {
        try {
            return JSON.parse(raw);
        } catch(err) {
            console.error('Payload decode failed:', err);
            return {};
        }
    }
}

function openMessagePreview(btn) {
    try {
        const raw = btn.getAttribute('data-payload') || '';
        const data = decodePayload(raw);

        currentMessageText = data.content || '';
        currentActiveChatId = data.chat_id || '';

        const sentAtEl = document.getElementById('modalSentAt');
        if (sentAtEl) sentAtEl.textContent = 'Sent on ' + (data.created_at || '—');

        const senderNameEl = document.getElementById('modalSenderName');
        if (senderNameEl) senderNameEl.textContent = data.sender_name || '—';

        const senderMetaEl = document.getElementById('modalSenderMeta');
        if (senderMetaEl) senderMetaEl.textContent = (data.sender_city || '—') + ' • ' + (data.sender_email || '');

        const senderAvatarEl = document.getElementById('modalSenderAvatar');
        if (senderAvatarEl) senderAvatarEl.textContent = (data.sender_name ? data.sender_name.substring(0, 2).toUpperCase() : 'S');

        const receiverNameEl = document.getElementById('modalReceiverName');
        if (receiverNameEl) receiverNameEl.textContent = data.receiver_name || '—';

        const receiverMetaEl = document.getElementById('modalReceiverMeta');
        if (receiverMetaEl) receiverMetaEl.textContent = (data.receiver_city || '—') + ' • ' + (data.receiver_email || '');

        const receiverAvatarEl = document.getElementById('modalReceiverAvatar');
        if (receiverAvatarEl) receiverAvatarEl.textContent = (data.receiver_name ? data.receiver_name.substring(0, 2).toUpperCase() : 'R');

        // Read status
        const readEl = document.getElementById('modalReadBadge');
        if (readEl) {
            if (data.is_read) {
                readEl.innerHTML = '<span class="badge px-2.5 py-1 rounded-pill" style="background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;"><i class="bi bi-check2-all me-1"></i>Read by Receiver</span>';
            } else {
                readEl.innerHTML = '<span class="badge px-2.5 py-1 rounded-pill" style="background:#fffbeb;color:#d97706;border:1px solid #fde68a;"><i class="bi bi-check me-1"></i>Unread</span>';
            }
        }

        // Type badge
        const typeEl = document.getElementById('modalTypeBadge');
        if (typeEl) {
            if (data.is_media) {
                typeEl.className = 'badge bg-purple text-white';
                typeEl.style.background = '#9333ea';
                typeEl.innerHTML = '<i class="bi bi-image me-1"></i> Media Attachment';
            } else {
                typeEl.className = 'badge bg-light text-dark border';
                typeEl.style.background = '';
                typeEl.innerHTML = '<i class="bi bi-chat-text text-primary me-1"></i> Text Message';
            }
        }

        // Text Content
        const textSec = document.getElementById('modalTextSection');
        const textContent = document.getElementById('modalTextContent');
        const emptyNotice = document.getElementById('modalEmptyNotice');

        if (data.content && data.content.trim().length > 0) {
            if (textContent) textContent.textContent = data.content;
            if (textSec) textSec.classList.remove('d-none');
            if (emptyNotice) emptyNotice.classList.add('d-none');
        } else {
            if (textSec) textSec.classList.add('d-none');
        }

        // Attachments / Media
        const mediaSec = document.getElementById('modalMediaSection');
        const mediaContainer = document.getElementById('modalMediaContainer');
        if (mediaContainer) mediaContainer.innerHTML = '';

        let attachments = Array.isArray(data.attachments) ? data.attachments : [];

        if (attachments && attachments.length > 0) {
            if (mediaSec) mediaSec.classList.remove('d-none');
            const countEl = document.getElementById('modalMediaCount');
            if (countEl) countEl.textContent = attachments.length + (attachments.length === 1 ? ' File' : ' Files');

            attachments.forEach(function(att) {
                const fileUrl = att.url || (att.file_id ? '/api/v1/files/' + att.file_id : '#');
                const isImg = (att.kind === 'image') || (att.mime && att.mime.startsWith('image/')) || (att.name && att.name.match(/\.(jpg|jpeg|png|gif|webp)$/i));
                const sizeKb = att.size ? (Math.round(att.size / 1024) + ' KB') : '';

                const wrapper = document.createElement('div');
                wrapper.className = 'mb-3 text-center';

                if (isImg) {
                    wrapper.innerHTML = `
                        <div class="p-2 border rounded-3 bg-light d-inline-block shadow-sm mb-2" style="max-width: 100%;">
                            <a href="${fileUrl}" target="_blank" title="Click to open full image in new tab">
                                <img src="${fileUrl}" alt="${att.name || 'Attachment'}" class="img-fluid rounded" style="max-height: 340px; object-fit: contain; cursor: zoom-in;" onerror="this.onerror=null; this.src='https://placehold.co/400x250/e2e8f0/475569?text=Image+Attachment';">
                            </a>
                        </div>
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <span class="fw-semibold text-dark small">${att.name || 'Image Attachment'}</span>
                            <span class="badge bg-light text-muted border">${sizeKb}</span>
                            <a href="${fileUrl}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px;">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Open
                            </a>
                        </div>
                    `;
                } else {
                    wrapper.innerHTML = `
                        <div class="p-3 border rounded-3 bg-light d-flex align-items-center justify-content-between gap-3 shadow-sm">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-file-earmark-arrow-down fs-3 text-primary"></i>
                                <div class="text-start">
                                    <div class="fw-semibold text-dark small">${att.name || 'File Attachment'}</div>
                                    <div class="text-muted" style="font-size: 11px;">${att.mime || 'Document'} • ${sizeKb}</div>
                                </div>
                            </div>
                            <a href="${fileUrl}" target="_blank" class="btn btn-sm btn-primary px-3 fw-semibold" download>
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                        </div>
                    `;
                }
                if (mediaContainer) mediaContainer.appendChild(wrapper);
            });
        } else {
            if (mediaSec) mediaSec.classList.add('d-none');
            if (!data.content || data.content.trim().length === 0) {
                if (emptyNotice) emptyNotice.classList.remove('d-none');
            }
        }

        const modalEl = document.getElementById('messageDetailModal');
        if (modalEl) {
            if (window.bootstrap && bootstrap.Modal) {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            } else if (typeof $ !== 'undefined' && $.fn && $.fn.modal) {
                $(modalEl).modal('show');
            }
        }
    } catch(err) {
        console.error('Error opening message preview:', err);
    }
}

function copyMessageText() {
    if (!currentMessageText) {
        alert('No text to copy');
        return;
    }
    navigator.clipboard.writeText(currentMessageText).then(function() {
        const btn = document.getElementById('copyMsgBtn');
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg me-1 text-success"></i> Copied!';
        setTimeout(() => btn.innerHTML = oldHtml, 2000);
    });
}

function openFullChatFromPreview() {
    if (!currentActiveChatId) {
        alert('No conversation ID associated with this message.');
        return;
    }
    // Hide preview modal and open full chat modal
    const previewModalEl = document.getElementById('messageDetailModal');
    if (previewModalEl) {
        const modal = bootstrap.Modal.getInstance(previewModalEl);
        if (modal) modal.hide();
    }
    openFullChatModal(currentActiveChatId);
}

function reloadCurrentChat() {
    if (currentActiveChatId) {
        openFullChatModal(currentActiveChatId, true);
    }
}

function openFullChatModal(chatId, forceReload = false) {
    currentActiveChatId = chatId;
    const modalEl = document.getElementById('fullChatModal');
    if (!modalEl) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    const loader = document.getElementById('fullChatLoader');
    const emptyState = document.getElementById('fullChatEmpty');
    const stream = document.getElementById('fullChatMessagesStream');
    const searchInput = document.getElementById('chatSearchInput');
    if (searchInput) searchInput.value = '';

    if (loader) loader.classList.remove('d-none');
    if (emptyState) emptyState.classList.add('d-none');
    if (stream) {
        stream.classList.add('d-none');
        stream.innerHTML = '';
    }

    fetch('/admin/activities/messages/conversation/' + encodeURIComponent(chatId), {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        }
    })
    .then(response => {
        if (!response.ok) throw new Error('Failed to load conversation history');
        return response.json();
    })
    .then(json => {
        if (loader) loader.classList.add('d-none');
        if (!json.success || !json.data) {
            if (emptyState) emptyState.classList.remove('d-none');
            return;
        }

        fullChatCachedData = json.data;
        renderFullChat(json.data);
    })
    .catch(err => {
        console.error('Conversation fetch error:', err);
        if (loader) loader.classList.add('d-none');
        if (emptyState) {
            emptyState.classList.remove('d-none');
            emptyState.querySelector('h6').textContent = 'Error Loading Chat';
            emptyState.querySelector('p').textContent = err.message || 'Unable to retrieve conversation messages.';
        }
    });
}

function renderFullChat(data) {
    const stream = document.getElementById('fullChatMessagesStream');
    const emptyState = document.getElementById('fullChatEmpty');
    const countBadge = document.getElementById('chatTotalCountBadge');
    const subTitle = document.getElementById('chatParticipantsSub');
    const u1Avatar = document.getElementById('chatU1Avatar');
    const u1Name = document.getElementById('chatU1Name');
    const u1Meta = document.getElementById('chatU1Meta');
    const u2Avatar = document.getElementById('chatU2Avatar');
    const u2Name = document.getElementById('chatU2Name');
    const u2Meta = document.getElementById('chatU2Meta');
    const scrollContainer = document.getElementById('fullChatScrollContainer');

    const u1 = data.user1 || {};
    const u2 = data.user2 || {};
    const messages = data.messages || [];

    if (countBadge) countBadge.textContent = (data.total_messages || messages.length) + ' Messages';
    if (subTitle) subTitle.textContent = (u1.name || 'User 1') + ' ↔ ' + (u2.name || 'User 2');

    if (u1Name) u1Name.textContent = u1.name || 'User 1';
    if (u1Meta) u1Meta.textContent = (u1.city ? u1.city + ' • ' : '') + (u1.email || '');
    if (u1Avatar) u1Avatar.textContent = (u1.name ? u1.name.substring(0, 2).toUpperCase() : 'U1');

    if (u2Name) u2Name.textContent = u2.name || 'User 2';
    if (u2Meta) u2Meta.textContent = (u2.city ? u2.city + ' • ' : '') + (u2.email || '');
    if (u2Avatar) u2Avatar.textContent = (u2.name ? u2.name.substring(0, 2).toUpperCase() : 'U2');

    if (!messages || messages.length === 0) {
        if (emptyState) emptyState.classList.remove('d-none');
        if (stream) stream.classList.add('d-none');
        return;
    }

    if (emptyState) emptyState.classList.add('d-none');
    if (stream) {
        stream.classList.remove('d-none');
        stream.innerHTML = '';
    }

    let lastDate = '';

    messages.forEach(msg => {
        // Date separator
        const msgDate = msg.date_formatted || '';
        if (msgDate && msgDate !== lastDate) {
            lastDate = msgDate;
            const dateSep = document.createElement('div');
            dateSep.className = 'text-center my-2';
            dateSep.innerHTML = `<span class="badge bg-white text-muted border shadow-2xs px-3 py-1 fw-semibold" style="border-radius: 9999px; font-size: 11px;">📅 ${msgDate}</span>`;
            stream.appendChild(dateSep);
        }

        const isU1 = msg.is_user1;
        const bubbleWrapper = document.createElement('div');
        bubbleWrapper.className = `d-flex align-items-end gap-2.5 chat-message-row ${isU1 ? 'justify-content-start' : 'justify-content-end'}`;
        bubbleWrapper.setAttribute('data-msg-text', (msg.content || '') + ' ' + (msg.sender_name || ''));

        const senderInitials = msg.sender_name ? msg.sender_name.substring(0, 2).toUpperCase() : (isU1 ? 'U1' : 'U2');
        const avatarBg = isU1 ? '#6366f1' : '#0d9488';

        let attachmentsHtml = '';
        if (msg.attachments && msg.attachments.length > 0) {
            attachmentsHtml += '<div class="mt-2 d-flex flex-wrap gap-2">';
            msg.attachments.forEach(att => {
                const fileUrl = att.url || (att.file_id ? '/api/v1/files/' + att.file_id : '#');
                const isImg = (att.kind === 'image') || (att.mime && att.mime.startsWith('image/')) || (att.name && att.name.match(/\.(jpg|jpeg|png|gif|webp)$/i));
                if (isImg) {
                    attachmentsHtml += `
                        <div class="border rounded-3 overflow-hidden shadow-2xs" style="max-width: 220px; max-height: 180px; background: #000;">
                            <a href="${fileUrl}" target="_blank" title="Click to view full image">
                                <img src="${fileUrl}" alt="Attachment" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.src='https://placehold.co/200x150/e2e8f0/475569?text=Image';">
                            </a>
                        </div>
                    `;
                } else {
                    attachmentsHtml += `
                        <a href="${fileUrl}" target="_blank" class="badge bg-light text-dark border p-2 d-inline-flex align-items-center gap-1.5 text-decoration-none">
                            <i class="bi bi-file-earmark-arrow-down text-primary"></i>
                            <span>${att.name || 'Attachment'}</span>
                        </a>
                    `;
                }
            });
            attachmentsHtml += '</div>';
        }

        const readTick = msg.is_read
            ? '<i class="bi bi-check2-all text-teal-600 ms-1" title="Read" style="color:#0d9488; font-size:12px;"></i>'
            : '<i class="bi bi-check text-muted ms-1" title="Sent" style="font-size:12px;"></i>';

        const bubbleCard = `
            <div class="chat-bubble shadow-sm p-3" style="
                max-width: 72%;
                border-radius: ${isU1 ? '16px 16px 16px 4px' : '16px 16px 4px 16px'};
                background: ${isU1 ? '#ffffff' : '#e6fffa'};
                border: 1px solid ${isU1 ? '#e2e8f0' : '#bbf7d0'};
                color: #1e293b;
            ">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                    <span class="fw-bold small" style="color: ${isU1 ? '#4f46e5' : '#0f766e'}; font-size: 11.5px;">${msg.sender_name || (isU1 ? u1.name : u2.name)}</span>
                    <span class="badge ${isU1 ? 'bg-indigo-subtle text-indigo-700' : 'bg-teal-subtle text-teal-700'} border-0 px-1.5 py-0.5" style="font-size: 9px;">${msg.sender_membership || 'Peer'}</span>
                </div>
                ${msg.content ? `<div class="chat-text" style="font-size: 13.5px; line-height: 1.5; white-space: pre-wrap; word-break: break-word;">${escapeHtml(msg.content)}</div>` : ''}
                ${attachmentsHtml}
                <div class="d-flex align-items-center justify-content-end gap-1 mt-1 text-muted" style="font-size: 10.5px;">
                    <span>${msg.time_formatted || ''}</span>
                    ${readTick}
                </div>
            </div>
        `;

        if (isU1) {
            bubbleWrapper.innerHTML = `
                <div class="avatar-circle flex-shrink-0" style="background: ${avatarBg}; width: 30px; height: 30px; font-size: 11px;">${senderInitials}</div>
                ${bubbleCard}
            `;
        } else {
            bubbleWrapper.innerHTML = `
                ${bubbleCard}
                <div class="avatar-circle flex-shrink-0" style="background: ${avatarBg}; width: 30px; height: 30px; font-size: 11px;">${senderInitials}</div>
            `;
        }

        stream.appendChild(bubbleWrapper);
    });

    // Auto-scroll to bottom of chat
    setTimeout(() => {
        if (scrollContainer) scrollContainer.scrollTop = scrollContainer.scrollHeight;
    }, 100);
}

function filterChatMessages(term) {
    const rows = document.querySelectorAll('.chat-message-row');
    if (!rows.length) return;
    const clean = (term || '').trim().toLowerCase();
    rows.forEach(row => {
        if (!clean) {
            row.classList.remove('d-none');
            return;
        }
        const text = (row.getAttribute('data-msg-text') || '').toLowerCase();
        if (text.includes(clean)) {
            row.classList.remove('d-none');
        } else {
            row.classList.add('d-none');
        }
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>
@endpush

