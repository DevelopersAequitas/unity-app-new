@extends('admin.layouts.app')

@section('title', 'Award Recognitions WhatsApp - Peers Global Unity')

@section('content')
<div class="container-fluid px-0">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-award-fill text-warning me-2"></i>Meta WhatsApp Award Recognitions
            </h1>
            <p class="text-muted small mb-0">Automated WhatsApp award deliveries for Track 1 (Growth) & Track 2 (Life Impact) with personalized dynamic creatives.</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <span class="badge bg-warning-subtle text-dark border border-warning-subtle py-2 px-3 small d-inline-flex align-items-center">
                <i class="bi bi-trophy-fill text-warning me-2"></i>
                24 Meta Templates (en_IN)
            </span>
            <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#testAwardModal">
                <i class="bi bi-send-plus me-1"></i>Test Send Award
            </button>
            <form action="{{ route('admin.whatsapp.awards.sync_statuses') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-success btn-sm" title="Sync live delivery and read receipts from Meta">
                    <i class="bi bi-arrow-repeat me-1"></i>Sync Status
                </button>
            </form>
            <a href="{{ route('admin.whatsapp.journey.index') }}" class="btn btn-outline-info btn-sm">
                <i class="bi bi-calendar-check me-1"></i>30-Day Journey
            </a>
            <a href="{{ route('admin.whatsapp.templates') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-file-earmark-text me-1"></i>Templates
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()" title="Refresh Page">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <!-- Alert Flash Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <style>
        .award-metric-card {
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            cursor: pointer;
            text-decoration: none !important;
            color: inherit;
        }
        .award-metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.12)!important;
        }
        .creative-thumb {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            cursor: pointer;
            transition: transform 0.15s ease;
        }
        .creative-thumb:hover {
            transform: scale(1.1);
        }
    </style>

    <!-- Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.awards.index', ['tab' => 'peers']) }}" class="card shadow-sm border-0 border-start border-primary border-4 h-100 award-metric-card {{ $tab === 'peers' ? 'bg-primary-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-semibold">Total Peers</span>
                        <i class="bi bi-people text-primary"></i>
                    </div>
                    <div class="h4 mb-0 fw-bold text-gray-800">{{ number_format($stats['total_peers'] ?? 0) }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.awards.index', ['tab' => 'deliveries']) }}" class="card shadow-sm border-0 border-start border-dark border-4 h-100 award-metric-card {{ $tab === 'deliveries' && $status === '' && $track === '' ? 'bg-secondary-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-semibold">Total Awards</span>
                        <i class="bi bi-trophy text-warning"></i>
                    </div>
                    <div class="h4 mb-0 fw-bold text-gray-800">{{ number_format($stats['total_awards']) }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.awards.index', ['tab' => 'deliveries', 'track' => 'GROWTH']) }}" class="card shadow-sm border-0 border-start border-success border-4 h-100 award-metric-card {{ $track === 'GROWTH' ? 'bg-success-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-semibold">Track 1: Growth</span>
                        <i class="bi bi-graph-up-arrow text-success"></i>
                    </div>
                    <div class="h4 mb-0 fw-bold text-success">{{ number_format($stats['total_growth']) }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.awards.index', ['tab' => 'deliveries', 'track' => 'LIFE_IMPACT']) }}" class="card shadow-sm border-0 border-start border-info border-4 h-100 award-metric-card {{ $track === 'LIFE_IMPACT' ? 'bg-info-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-semibold">Track 2: Impact</span>
                        <i class="bi bi-heart-pulse-fill text-info"></i>
                    </div>
                    <div class="h4 mb-0 fw-bold text-info">{{ number_format($stats['total_impact']) }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.awards.index', ['tab' => 'deliveries', 'status' => 'sent_group']) }}" class="card shadow-sm border-0 border-start border-success border-4 h-100 award-metric-card {{ in_array($status, ['sent', 'delivered', 'read', 'sent_group']) ? 'bg-success-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-semibold">Delivered</span>
                        <i class="bi bi-check2-all text-success"></i>
                    </div>
                    <div class="h4 mb-0 fw-bold text-success">{{ number_format($stats['total_delivered']) }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.awards.index', ['tab' => 'deliveries', 'status' => 'read']) }}" class="card shadow-sm border-0 border-start border-primary border-4 h-100 award-metric-card {{ $status === 'read' ? 'bg-primary-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-semibold">Read</span>
                        <i class="bi bi-eye-fill text-primary"></i>
                    </div>
                    <div class="h4 mb-0 fw-bold text-primary">{{ number_format($stats['total_read']) }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.awards.index', ['tab' => 'deliveries', 'status' => 'failed']) }}" class="card shadow-sm border-0 border-start border-danger border-4 h-100 award-metric-card {{ $status === 'failed' ? 'bg-danger-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-semibold">Failed</span>
                        <i class="bi bi-exclamation-octagon text-danger"></i>
                    </div>
                    <div class="h4 mb-0 fw-bold text-danger">{{ number_format($stats['total_failed']) }}</div>
                </div>
            </a>
        </div>
    </div>

    <!-- Navigation Tab Switcher: Peers View vs Flat Deliveries Log -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <ul class="nav nav-pills bg-white p-1 rounded-3 shadow-sm border">
            <li class="nav-item">
                <a class="nav-link fw-semibold px-3 py-1.5 {{ $tab !== 'deliveries' ? 'active bg-primary' : 'text-dark' }}" href="{{ route('admin.whatsapp.awards.index', array_merge(request()->except('tab', 'members_page'), ['tab' => 'peers'])) }}">
                    <i class="bi bi-people-fill me-1.5"></i>Peer Award Timelines & Progress
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link fw-semibold px-3 py-1.5 {{ $tab === 'deliveries' ? 'active bg-primary' : 'text-dark' }}" href="{{ route('admin.whatsapp.awards.index', array_merge(request()->except('tab', 'sends_page'), ['tab' => 'deliveries'])) }}">
                    <i class="bi bi-list-check me-1.5"></i>All Deliveries Stream Log
                    <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $stats['total_awards'] }}</span>
                </a>
            </li>
        </ul>
        <span class="text-muted small">
            @if($tab !== 'deliveries')
                Showing members & award timelines (Click Timeline for full 24-award schedule)
            @else
                Showing flat message dispatch logs
            @endif
        </span>
    </div>

    @if($tab !== 'deliveries')
        {{-- ======================================================== --}}
        {{-- TAB 1: PEERS AWARD LIST (Matching 30-Day Journey Image 2) --}}
        {{-- ======================================================== --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <form action="{{ route('admin.whatsapp.awards.index') }}" method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="peers">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search by member name, phone (+91...), or email..." value="{{ $search }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="peer_filter" class="form-select form-select-sm">
                            <option value="all" {{ $peerFilter === 'all' ? 'selected' : '' }}>All Peers ({{ $stats['total_peers'] }})</option>
                            <option value="has_awards" {{ $peerFilter === 'has_awards' ? 'selected' : '' }}>Peers With Awards Sent</option>
                            <option value="no_awards" {{ $peerFilter === 'no_awards' ? 'selected' : '' }}>Peers Without Awards</option>
                            <option value="track1" {{ $peerFilter === 'track1' ? 'selected' : '' }}>Track 1: Growth Awards</option>
                            <option value="track2" {{ $peerFilter === 'track2' ? 'selected' : '' }}>Track 2: Life Impact Awards</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        @if($search || $peerFilter !== 'all')
                            <a href="{{ route('admin.whatsapp.awards.index', ['tab' => 'peers']) }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                                <i class="bi bi-x-circle"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 fw-bold text-gray-800 d-flex align-items-center gap-2">
                    <i class="bi bi-people text-primary"></i>Member Award Timelines & Progress
                </h6>
                <span class="text-muted small">Showing {{ $members->firstItem() ?? 0 }} to {{ $members->lastItem() ?? 0 }} of {{ number_format($members->total()) }} members</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="min-width: 200px;">Member / Peer</th>
                            <th style="min-width: 150px;">WhatsApp Phone</th>
                            <th>Registered</th>
                            <th style="min-width: 160px;">Progress (24 Awards)</th>
                            <th class="text-center" style="width: 110px;">Track 1 (12)</th>
                            <th class="text-center" style="width: 110px;">Track 2 (12)</th>
                            <th class="text-center" style="width: 90px;">Delivered</th>
                            <th class="text-end pe-3" style="min-width: 130px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($members as $member)
                            @php
                                $totalSent = (int) ($member->total_awards_count ?? 0);
                                $growthSent = (int) ($member->total_growth_count ?? 0);
                                $impactSent = (int) ($member->total_impact_count ?? 0);
                                $delivered = (int) ($member->total_delivered_count ?? 0);
                                $percent = min(100, round(($totalSent / 24) * 100));

                                $rawPhone = trim((string) ($member->phone ?? ''));
                                $displayPhone = $rawPhone;
                                if ($rawPhone !== '') {
                                    $digits = preg_replace('/\D+/', '', $rawPhone) ?? '';
                                    if (strlen($digits) === 10) {
                                        $displayPhone = '+91 ' . $digits;
                                    } elseif (str_starts_with($rawPhone, '+')) {
                                        $displayPhone = '+' . $digits;
                                    }
                                }
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold me-2" style="width: 38px; height: 38px; min-width: 38px;">
                                            {{ strtoupper(substr($member->first_name ?: ($member->name ?: 'P'), 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark">{{ $member->display_name ?: trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')) }}</div>
                                            <div class="small text-muted">{{ $member->email ?: 'No email' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if(!empty($displayPhone))
                                        <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace py-1 px-2">
                                            <i class="bi bi-whatsapp me-1"></i>{{ $displayPhone }}
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger">Missing Phone</span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    {{ $member->created_at ? $member->created_at->format('d M Y') : '—' }}
                                </td>
                                <td>
                                    <div class="d-flex justify-content-between align-items-center mb-1 small">
                                        <span class="fw-bold text-primary">{{ $percent }}%</span>
                                        <span class="text-muted" style="font-size: 11px;">{{ $totalSent }}/24 Sent</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percent }}%"></div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $growthSent > 0 ? 'bg-success text-white' : 'bg-light text-muted border' }}">
                                        {{ $growthSent }} / 12
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $impactSent > 0 ? 'bg-info text-dark' : 'bg-light text-muted border' }}">
                                        {{ $impactSent }} / 12
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        ✓ {{ $delivered }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('admin.whatsapp.awards.user_timeline', $member->id) }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 shadow-sm">
                                        <i class="bi bi-calendar-range"></i> Timeline
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                                    No members found matching your search.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($members->hasPages())
                <div class="card-footer bg-white py-3 border-0">
                    {{ $members->links() }}
                </div>
            @endif
        </div>

    @else
        {{-- ======================================================== --}}
        {{-- TAB 2: FLAT DELIVERIES LOG (Existing Table from Image 1)  --}}
        {{-- ======================================================== --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3">
                <form action="{{ route('admin.whatsapp.awards.index') }}" method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="tab" value="deliveries">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search by member, phone, template..." value="{{ $search }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="track" class="form-select form-select-sm">
                            <option value="">All Award Tracks</option>
                            <option value="GROWTH" {{ $track === 'GROWTH' ? 'selected' : '' }}>Track 1: Growth (Membership Introductions)</option>
                            <option value="LIFE_IMPACT" {{ $track === 'LIFE_IMPACT' ? 'selected' : '' }}>Track 2: Life Impact</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="sent_group" {{ $status === 'sent_group' ? 'selected' : '' }}>All Successful (Sent / Delivered / Read)</option>
                            <option value="delivered" {{ $status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                            <option value="read" {{ $status === 'read' ? 'selected' : '' }}>Read</option>
                            <option value="sent" {{ $status === 'sent' ? 'selected' : '' }}>Sent (Unconfirmed)</option>
                            <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        @if($search || $track || $status)
                            <a href="{{ route('admin.whatsapp.awards.index', ['tab' => 'deliveries']) }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                                <i class="bi bi-x-circle"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 fw-bold text-gray-800 d-flex align-items-center gap-2">
                    <i class="bi bi-list-task text-primary"></i>Award Recognition Deliveries Log
                </h6>
                <span class="text-muted small">Showing {{ $sends->firstItem() ?? 0 }} to {{ $sends->lastItem() ?? 0 }} of {{ number_format($sends->total()) }} records</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="min-width: 170px;">Member</th>
                            <th style="min-width: 190px;">Award / Milestone</th>
                            <th style="width: 80px;">Creative</th>
                            <th>Template (Meta)</th>
                            <th>Status</th>
                            <th>Sent / Time</th>
                            <th class="text-end pe-3" style="width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sends as $item)
                            <tr>
                                <td class="ps-3">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold me-2" style="width: 38px; height: 38px; min-width: 38px;">
                                            {{ strtoupper(substr($item->user?->first_name ?: ($item->user?->name ?: 'P'), 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark text-truncate" style="max-width: 150px;">
                                                {{ $item->user?->display_name ?: (($item->user?->first_name ?? '') . ' ' . ($item->user?->last_name ?? '')) ?: 'Peer Member' }}
                                            </div>
                                            <div class="small text-muted font-monospace">
                                                <i class="bi bi-whatsapp text-success me-1"></i>{{ $item->phone_number ?: ($item->user?->phone ?? 'N/A') }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($item->award_track === 'GROWTH')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle mb-1">
                                            <i class="bi bi-graph-up me-1"></i>Track 1: Growth
                                        </span>
                                    @else
                                        <span class="badge bg-info-subtle text-info border border-info-subtle mb-1">
                                            <i class="bi bi-heart-pulse me-1"></i>Track 2: Impact
                                        </span>
                                    @endif
                                    <div class="fw-bold text-dark">
                                        {{ $item->award_title }}
                                        <span class="badge bg-light text-secondary border ms-1">{{ $item->milestone_count }}</span>
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $imgUrl = $item->creative_image_url;
                                        if (!empty($imgUrl) && !str_starts_with($imgUrl, 'http')) {
                                            $imgUrl = asset(ltrim($imgUrl, '/'));
                                        }
                                    @endphp
                                    @if(!empty($imgUrl))
                                        <a href="{!! $imgUrl !!}" target="_blank" title="View Full Creative Image">
                                            <img src="{!! $imgUrl !!}" alt="Creative" class="creative-thumb shadow-sm" referrerpolicy="no-referrer" onerror="this.onerror=null; this.src='{{ asset('images/member_introduce_creatives/Connector.png') }}';">
                                        </a>
                                    @else
                                        <span class="badge bg-secondary-subtle text-muted">No Image</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="font-monospace small text-primary fw-semibold">{{ $item->template_name }}</div>
                                    <div class="text-muted small">
                                        <span class="badge bg-light text-muted border">{{ $item->language }}</span>
                                        @if($item->is_test)
                                            <span class="badge bg-warning-subtle text-dark border">Test Send</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if($item->status === 'sent')
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            <i class="bi bi-check me-1"></i>Sent
                                        </span>
                                    @elseif($item->status === 'delivered')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check2-all me-1"></i>Delivered
                                        </span>
                                    @elseif($item->status === 'read')
                                        <span class="badge bg-info text-white">
                                            <i class="bi bi-eye me-1"></i>Read
                                        </span>
                                    @elseif($item->status === 'failed')
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="{{ $item->error_message }}">
                                            <i class="bi bi-x-circle me-1"></i>Failed
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                            <i class="bi bi-hourglass-split me-1"></i>{{ ucfirst($item->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($item->sent_at)
                                        <div class="small fw-semibold text-dark">{{ $item->sent_at->format('d M Y, h:i A') }}</div>
                                        <div class="text-muted small">{{ $item->sent_at->diffForHumans() }}</div>
                                    @else
                                        <span class="text-muted small">{{ $item->created_at?->format('d M Y, h:i A') ?? 'N/A' }}</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailModal{{ $item->id }}" title="View Payload & Details">
                                            <i class="bi bi-info-circle"></i>
                                        </button>
                                        <form action="{{ route('admin.whatsapp.awards.resend', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Resend this award WhatsApp template?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Resend Template">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Details Modal -->
                                    <div class="modal fade" id="detailModal{{ $item->id }}" tabindex="-1" aria-hidden="true" style="text-align: left;">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="bi bi-trophy-fill text-warning me-2"></i>{{ $item->award_title }} ({{ $item->award_track }})
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row g-3 mb-3">
                                                        <div class="col-md-6">
                                                            <div class="p-3 border rounded bg-light">
                                                                <div class="text-muted small fw-bold">RECIPIENT</div>
                                                                <div class="fw-bold fs-6">{{ $item->user?->display_name ?: 'Unknown' }}</div>
                                                                <div class="font-monospace text-success small">{{ $item->phone_number }}</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="p-3 border rounded bg-light">
                                                                <div class="text-muted small fw-bold">DELIVERY STATUS</div>
                                                                <div>
                                                                    <span class="badge {{ $item->status === 'delivered' ? 'bg-success' : ($item->status === 'failed' ? 'bg-danger' : 'bg-primary') }}">
                                                                        {{ ucfirst($item->status) }}
                                                                    </span>
                                                                </div>
                                                                <div class="text-muted small mt-1">Sent: {{ $item->sent_at?->format('d M Y, h:i:s A') ?? '-' }}</div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <div class="small fw-bold text-muted mb-1">Provider Message ID (Meta wamid):</div>
                                                        <div class="p-2 border rounded font-monospace small bg-light">{{ $item->provider_message_id ?: 'N/A' }}</div>
                                                    </div>

                                                    @if($item->error_message)
                                                        <div class="alert alert-danger py-2 px-3 small">
                                                            <strong>Error:</strong> {{ $item->error_message }}
                                                        </div>
                                                    @endif

                                                    <div class="mb-3">
                                                        <div class="small fw-bold text-muted mb-1">Provider Response JSON:</div>
                                                        <pre class="bg-dark text-light p-3 rounded small" style="max-height: 250px; overflow-y: auto;">{{ json_encode($item->provider_response_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                                    No award delivery records found matching your filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($sends->hasPages())
                <div class="card-footer bg-white py-3 border-0">
                    {{ $sends->links() }}
                </div>
            @endif
        </div>
    @endif
</div>

<!-- Test Send Award Modal -->
<div class="modal fade" id="testAwardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-send-plus me-2"></i>Test Send Award WhatsApp Template
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('admin.whatsapp.awards.test_send') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Recipient Mobile Number <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" placeholder="+919824193431" value="{{ config('whatsapp.testing.recipient', '+919824193431') }}" required>
                        <div class="form-text small">Must include country code (e.g. +91). Only configured test recipients are allowed in test mode.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Member Name (Dynamic @{{1}} & @{{2}})</label>
                        <input type="text" name="member_name" class="form-control" placeholder="Urvashi Chavda" value="Urvashi Chavda">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Award Track <span class="text-danger">*</span></label>
                        <select name="award_type" id="awardTrackSelect" class="form-select" onchange="updateAwardOptions()" required>
                            <option value="growth">Track 1: Growth (Membership Introductions)</option>
                            <option value="impact">Track 2: Life Impact</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Award <span class="text-danger">*</span></label>
                        <select name="award_key" id="awardKeySelect" class="form-select" required>
                            <!-- Populated via JavaScript below -->
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Creative Image URL (Optional Header Media)</label>
                        <input type="url" name="image_url" class="form-control" placeholder="https://..." value="https://scontent.whatsapp.net/v/t61.29466-34/731396378_1612351793587795_7709524562134913649_n.png?ccb=1-7&_nc_sid=8b1bef&_nc_ohc=KEuhDDjqRU0Q7kNvwEPJLTk&_nc_oc=AdqjuSaIlt9p0TxmSq_iRc-vYTK0fABaAcVmju1W5XTkAyYJZRKbe5rLDSLqegiEIQwmtOcnzVnWlau_hsTz2bMr&_nc_zt=3&_nc_ht=scontent.whatsapp.net&edm=AH51TzQEAAAA&_nc_gid=EXECS-gkv4ojCa7C5RH12Q&_nc_tpa=Q5bMBQKE5qalSsuHMemryC20Q99KWs67o1Rjuteigs83mp_oskICHR7tmsErdmGo7l9wz-sdPzQVUj9i&oh=01_Q5Aa5wGzs5m38OZYGYbK21Xzl7YWjKqLYmJT3Dbgwo_2L4hk9g&oe=6AF54371">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-send-fill me-1"></i>Dispatch WhatsApp Message
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const growthAwards = @json($growthAwards);
const impactAwards = @json($impactAwards);

function updateAwardOptions() {
    const track = document.getElementById('awardTrackSelect').value;
    const select = document.getElementById('awardKeySelect');
    if (!select) return;
    select.innerHTML = '';

    const list = track === 'growth' ? growthAwards : impactAwards;
    for (const [key, val] of Object.entries(list)) {
        const opt = document.createElement('option');
        opt.value = key;
        opt.textContent = `${val.title} (${key}) — Template: ${val.template}`;
        select.appendChild(opt);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateAwardOptions();
});
</script>
@endsection
