@extends('admin.layouts.app')

@section('title', 'Event WhatsApp QR Passes - Meta WhatsApp')

@push('styles')
<style>
/* Stat Cards */
.stat-widget {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 12px;
    text-decoration: none;
    display: block;
    cursor: pointer;
    background: #ffffff;
}
.stat-widget:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(0, 0, 0, 0.08);
}
.stat-widget.active-widget {
    box-shadow: 0 0 0 2px var(--bs-primary) !important;
}

.stat-card-border-primary { border-left: 4px solid #4f46e5 !important; }
.stat-card-border-success { border-left: 4px solid #10b981 !important; }
.stat-card-border-info { border-left: 4px solid #06b6d4 !important; }
.stat-card-border-danger { border-left: 4px solid #ef4444 !important; }

/* Filter Pills */
.preset-pill {
    padding: 4px 12px;
    border-radius: 9999px;
    font-size: 11.5px;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    text-decoration: none;
    transition: all 0.15s ease;
    display: inline-block;
}
.preset-pill:hover {
    background: #e2e8f0;
    color: #0f172a;
    border-color: #cbd5e1;
}
.preset-pill.active {
    background: #4f46e5;
    color: #ffffff;
    border-color: #4f46e5;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.3);
}

/* Avatar */
.avatar-circle {
    width: 36px;
    height: 36px;
    min-width: 36px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    flex-shrink: 0;
}

/* QR Code Thumbnail Preview Box */
.qr-thumb-box {
    width: 44px;
    height: 44px;
    min-width: 44px;
    padding: 2px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    overflow: hidden;
    position: relative;
    margin: 0 auto;
}
.qr-thumb-box:hover {
    transform: scale(1.08);
    box-shadow: 0 4px 10px rgba(0,0,0,0.12);
    border-color: #6366f1;
}
.qr-thumb-box svg {
    width: 38px !important;
    height: 38px !important;
    max-width: 38px !important;
    max-height: 38px !important;
    display: block !important;
    margin: 0 auto !important;
}
.qr-thumb-box img {
    width: 38px;
    height: 38px;
    object-fit: contain;
    display: block;
}

/* Table rules */
.event-passes-table {
    table-layout: auto;
    width: 100%;
}
.event-passes-table th {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #475569;
    background-color: #f8fafc;
    vertical-align: middle;
    padding: 0.75rem 0.85rem;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.event-passes-table td {
    padding: 0.75rem 0.85rem;
    vertical-align: middle;
    font-size: 13px;
    border-bottom: 1px solid #f1f5f9;
}

.bulk-floating-bar {
    position: sticky;
    bottom: 16px;
    z-index: 1020;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.6rem;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.2px;
    white-space: nowrap;
}
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Page Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small text-muted">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item">Meta WhatsApp</li>
                    <li class="breadcrumb-item active" aria-current="page">Event QR Passes</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-2">
                <h1 class="h3 fw-bold text-dark mb-0">Event WhatsApp QR Passes</h1>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5" style="font-size: 11px;">
                    <i class="bi bi-whatsapp me-1"></i>Meta Cloud API
                </span>
            </div>
            <p class="text-muted small mb-0 mt-1">Real-time WhatsApp QR Pass delivery, attendee confirmations, and delivery logs.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.whatsapp.event_passes.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-download"></i> Export CSV
            </a>
            <a href="{{ route('admin.events.total-registered') }}" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-calendar-check"></i> Total Registered
            </a>
            <a href="{{ route('admin.whatsapp.templates') }}" class="btn btn-outline-dark btn-sm d-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-gear"></i> WhatsApp Templates
            </a>
        </div>
    </div>

    {{-- KPI Metric Widgets Row --}}
    <div class="row g-3 mb-4">
        {{-- Total Passes --}}
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.whatsapp.event_passes.index', array_merge(request()->except(['status', 'page']))) }}" 
               class="stat-widget card shadow-sm border-0 stat-card-border-primary h-100 p-3 {{ empty($activeStatus) ? 'active-widget' : '' }}" 
               title="All Registered Event Passes">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small text-uppercase fw-semibold" style="font-size: 11px;">Total Passes</span>
                    <i class="bi bi-qr-code text-primary fs-5"></i>
                </div>
                <div class="h4 mb-0 fw-bold text-dark mt-2">{{ number_format($stats['total_passes'] ?? 0) }}</div>
                <div class="text-muted small mt-1" style="font-size: 11px;"><i class="bi bi-people me-1"></i>Eligible registrations</div>
            </a>
        </div>

        {{-- Delivered / Read --}}
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.whatsapp.event_passes.index', array_merge(request()->except(['status', 'page']), ['status' => 'delivered'])) }}" 
               class="stat-widget card shadow-sm border-0 stat-card-border-success h-100 p-3 {{ $activeStatus === 'delivered' ? 'active-widget' : '' }}" 
               title="Filter Delivered / Read passes">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-success small text-uppercase fw-semibold" style="font-size: 11px;">Delivered & Read</span>
                    <i class="bi bi-check2-all text-success fs-5"></i>
                </div>
                <div class="h4 mb-0 fw-bold text-success mt-2">{{ number_format($stats['total_delivered'] ?? 0) }}</div>
                <div class="text-success small mt-1" style="font-size: 11px;">
                    <i class="bi bi-graph-up-arrow me-1"></i>{{ $stats['delivery_rate'] ?? 0 }}% Delivery Success
                </div>
            </a>
        </div>

        {{-- Sent / In-Transit --}}
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.whatsapp.event_passes.index', array_merge(request()->except(['status', 'page']), ['status' => 'sent'])) }}" 
               class="stat-widget card shadow-sm border-0 stat-card-border-info h-100 p-3 {{ $activeStatus === 'sent' ? 'active-widget' : '' }}" 
               title="Filter In-Transit passes">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-info small text-uppercase fw-semibold" style="font-size: 11px;">Sent (In-Transit)</span>
                    <i class="bi bi-send text-info fs-5"></i>
                </div>
                <div class="h4 mb-0 fw-bold text-info mt-2">{{ number_format($stats['total_sent'] ?? 0) }}</div>
                <div class="text-muted small mt-1" style="font-size: 11px;"><i class="bi bi-clock-history me-1"></i>Dispatched to Meta</div>
            </a>
        </div>

        {{-- Pending / Failed --}}
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.whatsapp.event_passes.index', array_merge(request()->except(['status', 'page']), ['status' => 'failed'])) }}" 
               class="stat-widget card shadow-sm border-0 stat-card-border-danger h-100 p-3 {{ $activeStatus === 'failed' ? 'active-widget' : '' }}" 
               title="Filter Failed / Pending passes">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-danger small text-uppercase fw-semibold" style="font-size: 11px;">Failed / Needs Retry</span>
                    <i class="bi bi-exclamation-triangle text-danger fs-5"></i>
                </div>
                <div class="h4 mb-0 fw-bold text-danger mt-2">{{ number_format($stats['total_failed'] ?? 0) }}</div>
                <div class="text-danger small mt-1" style="font-size: 11px;">
                    <i class="bi bi-arrow-repeat me-1"></i>{{ number_format($stats['total_pending'] ?? 0) }} pending dispatch
                </div>
            </a>
        </div>
    </div>

    {{-- Event Distribution Cards --}}
    @if(isset($eventBreakdown) && $eventBreakdown->count() > 0)
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-2.5 px-3 d-flex justify-content-between align-items-center border-bottom">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-calendar-event text-primary"></i>
                <span class="fw-bold text-dark small text-uppercase" style="letter-spacing: 0.5px; font-size: 11.5px;">Active Events Activity</span>
            </div>
            <span class="text-muted small" style="font-size: 11px;">Top {{ $eventBreakdown->count() }} active events</span>
        </div>
        <div class="card-body p-3">
            <div class="row g-2">
                @foreach($eventBreakdown as $evt)
                    @php
                        $isCurrentEvt = request('event_id') == $evt->id;
                    @endphp
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="p-2.5 rounded-3 border d-flex justify-content-between align-items-center {{ $isCurrentEvt ? 'bg-primary-subtle border-primary' : 'bg-light' }}">
                            <div class="truncate me-2" style="max-width: 170px;">
                                <div class="small fw-bold text-dark truncate" title="{{ $evt->title }}">{{ $evt->title }}</div>
                                <div class="text-muted" style="font-size: 11px;">
                                    {{ optional($evt->start_at)->format('d M Y') ?: 'Scheduled' }}
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                <span class="badge bg-primary text-white" title="Total Registered" style="font-size: 10px;">
                                    {{ $evt->total_registrations }}
                                </span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" title="Delivered" style="font-size: 10px;">
                                    ✓ {{ $evt->delivered_count }}
                                </span>
                                @if($evt->failed_count > 0)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="Failed" style="font-size: 10px;">
                                        ✕ {{ $evt->failed_count }}
                                    </span>
                                @endif
                                <a href="{{ route('admin.whatsapp.event_passes.index', ['event_id' => $evt->id]) }}" 
                                   class="btn btn-sm {{ $isCurrentEvt ? 'btn-primary' : 'btn-outline-secondary' }} px-2 py-0.5" 
                                   style="font-size: 11px;" 
                                   title="Filter by this event">
                                    <i class="bi bi-funnel"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Filter Toolbar --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form id="eventPassFilterForm" method="GET" action="{{ route('admin.whatsapp.event_passes.index') }}">
                {{-- Quick Presets --}}
                <div class="d-flex flex-wrap align-items-center gap-2 pb-3 mb-3 border-bottom">
                    <span class="text-muted small fw-semibold text-uppercase me-1" style="font-size: 11px;">
                        <i class="bi bi-calendar-event me-1 text-primary"></i>Quick Range:
                    </span>
                    <a href="{{ route('admin.whatsapp.event_passes.index', array_merge(request()->except(['date_preset', 'quick', 'from_date', 'to_date', 'page']), ['date_preset' => ''])) }}"
                       class="preset-pill {{ $activePreset === '' ? 'active' : '' }}">All Time</a>
                    @foreach($presets as $key => $label)
                        <a href="{{ route('admin.whatsapp.event_passes.index', array_merge(request()->except(['date_preset', 'quick', 'from_date', 'to_date', 'page']), ['date_preset' => $key])) }}"
                           class="preset-pill {{ $activePreset === $key ? 'active' : '' }}">{{ $label }}</a>
                    @endforeach
                    <input type="hidden" name="date_preset" value="{{ $filters['date_preset'] ?? '' }}">
                </div>

                {{-- Filter Controls --}}
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 11px;">Search Registrant / Phone</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Name, phone, email, event..." value="{{ $filters['search'] ?? '' }}">
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 11px;">Event</label>
                        <select name="event_id" class="form-select form-select-sm">
                            <option value="">All Events</option>
                            @foreach($events as $event)
                                <option value="{{ $event->id }}" {{ ($filters['event_id'] ?? '') === $event->id ? 'selected' : '' }}>
                                    {{ $event->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 11px;">WhatsApp Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>All Statuses</option>
                            <option value="delivered" {{ ($filters['status'] ?? '') === 'delivered' ? 'selected' : '' }}>Delivered / Read</option>
                            <option value="read" {{ ($filters['status'] ?? '') === 'read' ? 'selected' : '' }}>Read (Blue Ticks)</option>
                            <option value="sent" {{ ($filters['status'] ?? '') === 'sent' ? 'selected' : '' }}>Sent (In Transit)</option>
                            <option value="failed" {{ ($filters['status'] ?? '') === 'failed' ? 'selected' : '' }}>Failed</option>
                            <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: 11px;">Registrant Type</label>
                        <select name="user_type" class="form-select form-select-sm">
                            <option value="all" {{ ($filters['user_type'] ?? 'all') === 'all' ? 'selected' : '' }}>All Types</option>
                            <option value="member" {{ ($filters['user_type'] ?? '') === 'member' ? 'selected' : '' }}>Peers Members</option>
                            <option value="visitor" {{ ($filters['user_type'] ?? '') === 'visitor' ? 'selected' : '' }}>Visitors</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        <a href="{{ route('admin.whatsapp.event_passes.index') }}" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Table Card --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 px-3 d-flex justify-content-between align-items-center border-bottom">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-table text-primary"></i>
                <span class="fw-bold text-dark">Attendee Event Passes</span>
                <span class="badge bg-secondary-subtle text-secondary border">{{ $registrations->total() }} total</span>
            </div>
            <div class="text-muted small">
                Showing {{ $registrations->firstItem() ?? 0 }}-{{ $registrations->lastItem() ?? 0 }} of {{ $registrations->total() }}
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 event-passes-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 38px;" class="text-center">
                            <input type="checkbox" class="form-check-input" id="selectAllCheckbox" title="Select all on this page">
                        </th>
                        <th style="min-width: 210px;">Registrant</th>
                        <th style="min-width: 170px;">Contact</th>
                        <th style="min-width: 220px;">Event & Date</th>
                        <th style="width: 80px;" class="text-center">QR Pass</th>
                        <th style="width: 130px;" class="text-center">WhatsApp Status</th>
                        <th style="min-width: 180px;">Meta Message ID</th>
                        <th style="width: 110px;" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody id="eventPassTableBody">
                    @forelse($registrations as $row)
                        @php
                            $isMember = ! empty($row->user_id);
                            $name = $isMember
                                ? ($row->user?->display_name ?: trim(($row->user?->first_name ?? '').' '.($row->user?->last_name ?? '')))
                                : ($row->visitor_name ?: 'Visitor');
                            $phone = $isMember ? ($row->user?->phone ?: '-') : ($row->visitor_phone ?: '-');
                            $email = $isMember ? ($row->user?->email ?: '-') : ($row->visitor_email ?: '-');
                            $initial = strtoupper(substr($name, 0, 1));
                            $wStatus = strtolower((string) ($row->whatsapp_status ?? ''));
                            
                            $rawSvg = (string) ($row->qr_code_svg ?? '');
                            $cleanedSvg = preg_replace('/<\?xml[^>]*\?>/i', '', $rawSvg);
                            $cleanedSvg = preg_replace('/<!DOCTYPE[^>]*>/i', '', $cleanedSvg);
                            $cleanedSvg = trim($cleanedSvg);

                            $qrUrl = $row->qr_code_url;
                            if (empty($qrUrl) && !empty($row->qr_code_path)) {
                                $qrUrl = app(\App\Services\Events\EventQrService::class)->url($row->qr_code_path);
                            }
                            $hasQr = !empty($qrUrl) || !empty($cleanedSvg);
                        @endphp
                        <tr id="row-{{ $row->id }}" data-id="{{ $row->id }}">
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input row-select" value="{{ $row->id }}">
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-circle {{ $isMember ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-purple-subtle text-purple border' }}" style="{{ ! $isMember ? 'background-color: #f3e8ff; color: #7e22ce; border-color: #e9d5ff;' : '' }}">
                                        {{ $initial }}
                                    </div>
                                    <div style="min-width: 0;">
                                        <div class="fw-bold text-dark text-truncate" style="max-width: 170px;" title="{{ $name }}">{{ $name }}</div>
                                        <div class="d-flex align-items-center gap-1 mt-0.5">
                                            <span class="badge {{ $isMember ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-purple-subtle text-purple border' }}" style="font-size: 10px; {{ ! $isMember ? 'background-color: #f3e8ff; color: #7e22ce;' : '' }}">
                                                {{ $isMember ? 'Peer Member' : 'Visitor' }}
                                            </span>
                                            @if(strtolower((string) $row->checkin_status) === 'checked_in')
                                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 10px;">
                                                    <i class="bi bi-check-circle-fill"></i> Checked In
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $phone) }}" target="_blank" class="text-decoration-none text-success" title="Direct WhatsApp Chat">
                                        <i class="bi bi-whatsapp me-1"></i>{{ $phone }}
                                    </a>
                                </div>
                                <div class="text-muted small text-truncate" style="font-size: 11px; max-width: 160px;" title="{{ $email }}">{{ $email }}</div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark text-truncate" style="max-width: 210px;" title="{{ $row->event?->title }}">
                                    {{ $row->event?->title ?? '-' }}
                                </div>
                                <div class="text-muted small mt-0.5" style="font-size: 11px;">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    {{ optional($row->occurrence?->start_at ?: $row->event?->start_at)->format('d M Y, h:i A') ?: 'Upcoming' }}
                                </div>
                                @if(!empty($row->event?->circle?->name))
                                    <span class="badge bg-light text-dark border mt-1 text-truncate" style="font-size: 10.5px; max-width: 200px;" title="{{ $row->event->circle->name }}">
                                        <i class="bi bi-circle me-1"></i>{{ $row->event->circle->name }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($hasQr)
                                    <div class="qr-thumb-box open-qr-modal" 
                                         data-id="{{ $row->id }}"
                                         data-name="{{ $name }}"
                                         data-event="{{ $row->event?->title ?? 'Event Entry Pass' }}"
                                         data-token="{{ $row->qr_token ?? $row->id }}"
                                         data-qr-url="{{ $qrUrl ?? '' }}"
                                         data-qr-svg="{{ htmlspecialchars($cleanedSvg, ENT_QUOTES) }}"
                                         title="Click to view full-size QR pass">
                                        @if(!empty($cleanedSvg))
                                            {!! $cleanedSvg !!}
                                        @elseif(!empty($qrUrl))
                                            <img src="{{ $qrUrl }}" alt="QR" onerror="this.style.display='none';">
                                        @else
                                            <i class="bi bi-qr-code text-muted"></i>
                                        @endif
                                    </div>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border" style="font-size: 10.5px;">No QR</span>
                                @endif
                            </td>
                            <td class="text-center" id="status-cell-{{ $row->id }}">
                                @if($wStatus === 'read')
                                    <span class="status-badge bg-primary-subtle text-primary border border-primary-subtle" title="Read by recipient (Blue double ticks)">
                                        <i class="bi bi-check2-all"></i> Read
                                    </span>
                                @elseif($wStatus === 'delivered')
                                    <span class="status-badge bg-success-subtle text-success border border-success-subtle" title="Delivered to phone (Double ticks)">
                                        <i class="bi bi-check2-all"></i> Delivered
                                    </span>
                                @elseif($wStatus === 'sent')
                                    <span class="status-badge bg-info-subtle text-info border border-info-subtle" title="Sent by Meta Server (Single tick)">
                                        <i class="bi bi-send"></i> Sent
                                    </span>
                                @elseif($wStatus === 'failed')
                                    <span class="status-badge bg-danger-subtle text-danger border border-danger-subtle" title="Delivery Failed">
                                        <i class="bi bi-exclamation-triangle-fill"></i> Failed
                                    </span>
                                @else
                                    <span class="status-badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" title="Pending Dispatch">
                                        <i class="bi bi-clock"></i> Pending
                                    </span>
                                @endif

                                @if($row->whatsapp_sent_at)
                                    <div class="text-muted mt-0.5" style="font-size: 10.5px;" title="{{ $row->whatsapp_sent_at }}">
                                        {{ \Carbon\Carbon::parse($row->whatsapp_sent_at)->format('d M Y, h:i A') }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if(!empty($row->whatsapp_provider_id))
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="font-monospace text-primary text-truncate" style="font-size: 11px; max-width: 140px;" title="{{ $row->whatsapp_provider_id }}">
                                            {{ $row->whatsapp_provider_id }}
                                        </span>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-muted copy-btn" data-text="{{ $row->whatsapp_provider_id }}" title="Copy WAMID">
                                            <i class="bi bi-clipboard" style="font-size: 11px;"></i>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-success resend-single-btn px-2 py-1" 
                                            data-id="{{ $row->id }}" 
                                            title="Resend WhatsApp QR Pass">
                                        <i class="bi bi-whatsapp me-1"></i><span class="btn-text">Resend</span>
                                    </button>
                                    <a href="{{ route('admin.whatsapp.event_passes.show', $row->id) }}" 
                                       class="btn btn-sm btn-outline-secondary px-2 py-1" 
                                       title="View Pass & Audit Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="py-4">
                                    <i class="bi bi-qr-code text-muted" style="font-size: 3rem;"></i>
                                    <h5 class="fw-bold text-dark mt-3">No Event Passes Found</h5>
                                    <p class="text-muted small">No event registrations matched your selected search and filter criteria.</p>
                                    <a href="{{ route('admin.whatsapp.event_passes.index') }}" class="btn btn-outline-primary btn-sm mt-2">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset All Filters
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($registrations->hasPages())
            <div class="card-footer bg-white py-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="text-muted small">
                    Showing {{ $registrations->firstItem() }} to {{ $registrations->lastItem() }} of {{ $registrations->total() }} passes
                </div>
                <div>
                    {{ $registrations->withQueryString()->links() }}
                </div>
            </div>
        @endif
    </div>

    {{-- Floating Bulk Actions Bar --}}
    <div id="bulkActionBar" class="bulk-floating-bar card bg-dark text-white border-0 py-2.5 px-3 rounded-4 d-none" style="max-width: 580px; margin: 0 auto;">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary px-2.5 py-1.5 fs-6" id="selectedCountBadge">0</span>
                <span class="small fw-semibold">passes selected</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-light" id="clearSelectionBtn">
                    Cancel
                </button>
                <button type="button" class="btn btn-sm btn-success fw-bold d-flex align-items-center gap-1.5 shadow" id="bulkResendBtn">
                    <i class="bi bi-whatsapp"></i>
                    <span>Resend Selected</span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Single Global QR Pass Preview Modal (outside table for 100% valid HTML) --}}
<div class="modal fade" id="eventQrPreviewModal" tabindex="-1" aria-labelledby="eventQrPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-lg border-0 text-center" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-dark text-white py-2.5 px-3">
                <h6 class="modal-title mb-0 fw-bold fs-6" id="eventQrPreviewModalLabel">
                    <i class="bi bi-qr-code me-1 text-success"></i>Event Entry Pass
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="bg-white p-3 rounded-3 shadow-sm d-inline-block border">
                    <div id="modalQrContainer" style="width: 200px; height: 200px; margin: 0 auto; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <!-- QR Injected via JS -->
                    </div>
                </div>
                <div class="mt-3 fw-bold text-dark fs-6" id="modalAttendeeName">-</div>
                <div class="text-muted small text-truncate px-2" id="modalEventTitle">-</div>
                <div class="text-muted font-monospace mt-1" style="font-size: 11px;" id="modalQrToken">-</div>

                <div class="mt-3 d-flex justify-content-center gap-2">
                    <a href="#" id="modalDownloadPngBtn" download="EventPass.png" target="_blank" class="btn btn-sm btn-outline-primary" style="display: none;">
                        <i class="bi bi-download me-1"></i>Download PNG
                    </a>
                    <button type="button" class="btn btn-sm btn-success resend-single-btn" id="modalResendBtn" data-id="">
                        <i class="bi bi-whatsapp me-1"></i>Send Now
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    // 1. Checkbox and Bulk Bar logic
    const selectAll = document.getElementById('selectAllCheckbox');
    const rowCheckboxes = document.querySelectorAll('.row-select');
    const bulkBar = document.getElementById('bulkActionBar');
    const selectedBadge = document.getElementById('selectedCountBadge');
    const clearSelectionBtn = document.getElementById('clearSelectionBtn');
    const bulkResendBtn = document.getElementById('bulkResendBtn');

    function updateBulkBar() {
        const checked = document.querySelectorAll('.row-select:checked');
        const count = checked.length;
        if (count > 0) {
            selectedBadge.textContent = count;
            bulkBar.classList.remove('d-none');
        } else {
            bulkBar.classList.add('d-none');
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowCheckboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkBar();
        });
    }

    rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            if (!this.checked && selectAll) selectAll.checked = false;
            updateBulkBar();
        });
    });

    if (clearSelectionBtn) {
        clearSelectionBtn.addEventListener('click', function () {
            rowCheckboxes.forEach(cb => cb.checked = false);
            if (selectAll) selectAll.checked = false;
            updateBulkBar();
        });
    }

    // 2. Interactive QR Modal Preview
    const previewModalEl = document.getElementById('eventQrPreviewModal');
    let previewModalInstance = null;

    document.querySelectorAll('.open-qr-modal').forEach(thumb => {
        thumb.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const event = this.getAttribute('data-event');
            const token = this.getAttribute('data-token');
            const qrUrl = this.getAttribute('data-qr-url');
            const qrSvg = this.getAttribute('data-qr-svg');

            document.getElementById('modalAttendeeName').textContent = name || 'Attendee';
            document.getElementById('modalEventTitle').textContent = event || 'Event Pass';
            document.getElementById('modalQrToken').textContent = token ? `Token: ${token}` : '';
            document.getElementById('modalResendBtn').setAttribute('data-id', id);

            const container = document.getElementById('modalQrContainer');
            container.innerHTML = '';

            const dlBtn = document.getElementById('modalDownloadPngBtn');
            if (qrSvg && qrSvg.trim() !== '') {
                container.innerHTML = qrSvg;
                const svgEl = container.querySelector('svg');
                if (svgEl) {
                    svgEl.style.width = '100%';
                    svgEl.style.height = '100%';
                    svgEl.style.maxWidth = '200px';
                    svgEl.style.maxHeight = '200px';
                    svgEl.style.display = 'block';
                }
                if (qrUrl && qrUrl !== '') {
                    dlBtn.href = qrUrl;
                    dlBtn.download = `Pass_${id}.png`;
                    dlBtn.style.display = 'inline-block';
                } else {
                    dlBtn.style.display = 'none';
                }
            } else if (qrUrl && qrUrl !== '') {
                const img = document.createElement('img');
                img.src = qrUrl;
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'contain';
                container.appendChild(img);

                dlBtn.href = qrUrl;
                dlBtn.download = `Pass_${id}.png`;
                dlBtn.style.display = 'inline-block';
            } else {
                container.innerHTML = '<span class="text-muted small">No QR Available</span>';
                dlBtn.style.display = 'none';
            }

            if (!previewModalInstance && previewModalEl) {
                previewModalInstance = new bootstrap.Modal(previewModalEl);
            }
            if (previewModalInstance) {
                previewModalInstance.show();
            }
        });
    });

    // 3. Single Resend via AJAX
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.resend-single-btn');
        if (!btn) return;

        e.preventDefault();
        const regId = btn.getAttribute('data-id');
        if (!regId) return;

        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Sending...';

        try {
            const res = await fetch(`{{ url('/admin/whatsapp/event-passes') }}/${regId}/resend`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({}),
            });

            const data = await res.json();

            if (data.success) {
                btn.classList.remove('btn-outline-success', 'btn-success');
                btn.classList.add('btn-primary');
                btn.innerHTML = '<i class="bi bi-check2"></i> Resent!';

                // Update status badge dynamically
                const statusCell = document.getElementById(`status-cell-${regId}`);
                if (statusCell) {
                    statusCell.innerHTML = `
                        <span class="status-badge bg-info-subtle text-info border border-info-subtle">
                            <i class="bi bi-send"></i> Sent
                        </span>
                        <div class="text-muted mt-0.5" style="font-size: 10.5px;">Just now</div>
                    `;
                }

                setTimeout(() => {
                    btn.disabled = false;
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-success');
                    btn.innerHTML = originalHtml;
                }, 3000);

                showToast(data.message || 'WhatsApp pass sent successfully!', 'success');
            } else {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                showToast(data.message || 'Failed to send WhatsApp message.', 'danger');
            }
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            showToast('Network error while dispatching WhatsApp pass.', 'danger');
        }
    });

    // 4. Bulk Resend via AJAX
    if (bulkResendBtn) {
        bulkResendBtn.addEventListener('click', async function () {
            const checked = Array.from(document.querySelectorAll('.row-select:checked')).map(cb => cb.value);
            if (checked.length === 0) return;

            if (!confirm(`Are you sure you want to resend WhatsApp QR passes to ${checked.length} selected attendees?`)) {
                return;
            }

            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Dispatching...';

            try {
                const res = await fetch(`{{ route('admin.whatsapp.event_passes.bulk_resend') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ids: checked }),
                });

                const data = await res.json();

                this.disabled = false;
                this.innerHTML = originalHtml;

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(data.message || 'Bulk dispatch failed.', 'danger');
                }
            } catch (err) {
                this.disabled = false;
                this.innerHTML = originalHtml;
                showToast('Network error during bulk dispatch.', 'danger');
            }
        });
    }

    // 5. Copy-to-clipboard for provider IDs
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const text = this.getAttribute('data-text');
            if (text) {
                navigator.clipboard.writeText(text);
                const icon = this.querySelector('i');
                if (icon) {
                    icon.classList.remove('bi-clipboard');
                    icon.classList.add('bi-check2');
                    setTimeout(() => {
                        icon.classList.remove('bi-check2');
                        icon.classList.add('bi-clipboard');
                    }, 2000);
                }
            }
        });
    });

    // Helper Toast notification function
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('toastContainer') || createToastContainer();
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-white bg-${type === 'danger' ? 'danger' : 'success'} border-0 show shadow-lg mb-2`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${type === 'danger' ? 'exclamation-circle' : 'check-circle'}-fill me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        toastContainer.appendChild(toastEl);
        setTimeout(() => {
            toastEl.remove();
        }, 5000);
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }
});
</script>
@endpush
