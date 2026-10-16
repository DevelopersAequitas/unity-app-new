@extends('admin.layouts.app')

@section('title', 'Award Recognition Timeline - ' . ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))))

@section('content')
<div class="container-fluid px-0">
    <!-- Breadcrumb & Top Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.awards.index') }}">Award Recognitions</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $user->display_name ?: 'Member Details' }}</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">
                <i class="bi bi-award-fill text-warning me-2"></i>{{ $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) }}
            </h1>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.whatsapp.awards.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Awards List
            </a>
            <form action="{{ route('admin.whatsapp.awards.sync_statuses') }}" method="POST" class="d-inline">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <button type="submit" class="btn btn-outline-success btn-sm" title="Sync live delivery and read receipts from Meta">
                    <i class="bi bi-arrow-repeat me-1"></i>Sync Delivery Status
                </button>
            </form>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()" title="Refresh Page">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Flash Alerts -->
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

    <!-- Member Overview & Stats Card -->
    <div class="row g-3 mb-4">
        <!-- Member Profile Info -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold text-gray-800">
                        <i class="bi bi-person-badge me-2 text-primary"></i>Member Information
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3">
                            @if(!empty($user->profile_photo_url))
                                <img src="{{ $user->profile_photo_url }}" class="rounded-circle border" width="56" height="56" alt="Avatar" style="object-fit: cover;">
                            @else
                                <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 56px; height: 56px; font-size: 1.25rem;">
                                    {{ strtoupper(substr($user->first_name ?: $user->name ?: 'P', 0, 1)) }}
                                </div>
                            @endif
                        </div>
                        <div>
                            <div class="fw-bold fs-5 text-dark">{{ $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) }}</div>
                            <div class="text-muted small">
                                Name: <span class="badge bg-light text-dark border font-monospace">{{ trim($user->first_name ?: explode(' ', $user->display_name ?? 'Peer')[0]) }}</span>
                            </div>
                        </div>
                    </div>

                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <th class="text-muted ps-0" style="width: 140px;">WhatsApp Phone:</th>
                                <td>
                                    @if(!empty($userPhone))
                                        <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace">
                                            <i class="bi bi-whatsapp me-1"></i>{{ $userPhone }}
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger">Missing Phone</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted ps-0">Email:</th>
                                <td class="text-dark">{{ $user->email ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted ps-0">Designation / Role:</th>
                                <td class="text-dark">{{ $user->designation ?: '—' }}</td>
                            </tr>
                            <tr>
                                <th class="text-muted ps-0">Registered On:</th>
                                <td class="text-dark">{{ $user->created_at ? $user->created_at->format('d M Y, h:i A') : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Awards Progress Stats -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold text-gray-800">
                        <i class="bi bi-trophy-fill me-2 text-warning"></i>Award Recognition Summary
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-sm-3">
                            <div class="p-2.5 rounded-3 border bg-light text-center">
                                <div class="text-muted small text-uppercase fw-semibold" style="font-size: 11px;">Total Templates</div>
                                <div class="h4 mb-0 fw-bold text-dark mt-1">24</div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2.5 rounded-3 border bg-primary-subtle text-center">
                                <div class="text-primary small text-uppercase fw-semibold" style="font-size: 11px;">Dispatched</div>
                                <div class="h4 mb-0 fw-bold text-primary mt-1">{{ $sentCount }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2.5 rounded-3 border bg-success-subtle text-center">
                                <div class="text-success small text-uppercase fw-semibold" style="font-size: 11px;">Delivered</div>
                                <div class="h4 mb-0 fw-bold text-success mt-1">{{ $deliveredCount }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <div class="p-2.5 rounded-3 border bg-danger-subtle text-center">
                                <div class="text-danger small text-uppercase fw-semibold" style="font-size: 11px;">Failed</div>
                                <div class="h4 mb-0 fw-bold text-danger mt-1">{{ $failedCount }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border text-muted small">
                        <div class="d-flex align-items-center mb-1 text-dark fw-bold">
                            <i class="bi bi-info-circle text-primary me-2"></i>2 Award Tracks Supported:
                        </div>
                        <div class="mb-1">&bull; <strong>Track 1: Member Introduction (12 Milestones):</strong> Connector, Catalyst, Influencer, Ambassador, Rainmaker, Trailblazer, Vanguard, Luminary, Movement Maker, Community Titan, Network Architect, Global Icon.</div>
                        <div>&bull; <strong>Track 2: Life Impact (12 Milestones):</strong> Impact Creator (25), Change Maker (50), Life Changer (100), Impact Builder (250)... up to Peers Global Legend (100,000).</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Award Recognitions Timeline Table Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h6 class="mb-0 fw-bold text-gray-800 d-flex align-items-center gap-2">
                    <i class="bi bi-award text-warning"></i>Award Recognition Delivery Schedule
                </h6>
                <span class="text-muted small">Language: en_IN &bull; Meta Approved Award Templates</span>
            </div>

            <!-- 3 Sections Tabs: 1) All 2) Member Introduction 3) Impact -->
            <div class="d-flex gap-1 bg-light p-1 rounded-3 border">
                <button type="button" class="btn btn-sm btn-primary px-3 filter-tab active" data-filter="all" onclick="filterAwards('all', this)">
                    <i class="bi bi-grid-fill me-1"></i>All Awards (24)
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary px-3 filter-tab border-0" data-filter="growth" onclick="filterAwards('growth', this)">
                    <i class="bi bi-person-check-fill me-1 text-success"></i>Member Introduction (12)
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary px-3 filter-tab border-0" data-filter="impact" onclick="filterAwards('impact', this)">
                    <i class="bi bi-heart-pulse-fill me-1 text-info"></i>Impact (12)
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="awardsTimelineTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 130px;">Milestone</th>
                        <th style="min-width: 220px;">Template & Award Title</th>
                        <th style="width: 90px;">Creative</th>
                        <th>Status</th>
                        <th style="min-width: 170px;">Delivery Info</th>
                        <th class="text-end pe-3" style="min-width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($milestones as $item)
                        @php
                            $send = $item['send'];
                            $isTrack1 = $item['track'] === 'GROWTH';
                            $filterClass = $isTrack1 ? 'award-row-growth' : 'award-row-impact';
                        @endphp
                        <tr class="award-row {{ $filterClass }}">
                            <td class="ps-3">
                                <span class="badge {{ $isTrack1 ? 'bg-success text-white' : 'bg-info text-dark' }} rounded-pill px-2.5 py-1">
                                    {{ $item['milestone_label'] }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark fs-6">{{ $item['title'] }}</div>
                                <div class="d-flex align-items-center gap-1.5 mt-0.5">
                                    <span class="badge {{ $isTrack1 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-info-subtle text-info border border-info-subtle' }}" style="font-size: 10px;">
                                        {{ $item['track_badge'] }}
                                    </span>
                                    <span class="font-monospace text-muted small" style="font-size: 11px;">
                                        {{ $item['template_name'] }}
                                    </span>
                                    <span class="badge bg-light text-secondary border" style="font-size: 10px;">{{ $item['language'] }}</span>
                                </div>
                            </td>
                            <td>
                                @php
                                    $imgUrl = $item['creative_image_url'];
                                    if (!empty($imgUrl) && !str_starts_with($imgUrl, 'http')) {
                                        $imgUrl = asset(ltrim($imgUrl, '/'));
                                    }
                                @endphp
                                @if(!empty($imgUrl))
                                    <a href="{!! $imgUrl !!}" target="_blank" title="View Full Award Creative">
                                        <img src="{!! $imgUrl !!}" alt="Creative" class="rounded shadow-sm border" style="width: 44px; height: 44px; object-fit: cover;" onerror="this.onerror=null; this.src='{{ asset('images/member_introduce_creatives/Connector.png') }}';">
                                    </a>
                                @else
                                    <span class="badge bg-light text-muted border" style="font-size: 10.5px;">No image</span>
                                @endif
                            </td>
                            <td>
                                @if($item['status'] === 'delivered')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="bi bi-check2-all me-1"></i>Delivered
                                    </span>
                                @elseif($item['status'] === 'read')
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                        <i class="bi bi-eye me-1"></i>Read
                                    </span>
                                @elseif($item['status'] === 'sent')
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                                        <i class="bi bi-check me-1"></i>Sent
                                    </span>
                                @elseif($item['status'] === 'failed')
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" title="{{ $item['error_message'] }}">
                                        <i class="bi bi-x-circle me-1"></i>Failed
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border px-2 py-1">
                                        <i class="bi bi-clock me-1"></i>Not Sent
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($send && in_array($send->status, ['sent', 'delivered', 'read']))
                                    <div class="small {{ in_array($send->status, ['delivered', 'read']) ? 'text-success fw-bold' : 'text-primary' }}">
                                        {{ $send->sent_at ? $send->sent_at->format('d M Y, h:i A') : ucfirst($send->status) }}
                                    </div>
                                    @if($send->provider_message_id)
                                        <div class="font-monospace text-muted small mt-0.5" style="font-size: 0.72rem;" title="{{ $send->provider_message_id }}">
                                            {{ Str::limit($send->provider_message_id, 18) }}
                                        </div>
                                    @endif
                                @elseif($send && $send->status === 'failed')
                                    <div class="small text-danger fw-semibold" style="max-width: 200px;">
                                        {{ Str::limit($send->error_message ?? 'Delivery Failed', 40) }}
                                    </div>
                                    @if($send->sent_at)
                                        <div class="text-muted small" style="font-size: 10.5px;">{{ $send->sent_at->format('d M, h:i A') }}</div>
                                    @endif
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex gap-1 align-items-center">
                                    @if($send)
                                        <!-- Resend Button -->
                                        <form action="{{ route('admin.whatsapp.awards.resend', $send->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Resend WhatsApp award {{ $item['title'] }} to {{ $userPhone ?: 'member' }}?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Resend Award WhatsApp Template">
                                                <i class="bi bi-arrow-repeat me-1"></i>Resend
                                            </button>
                                        </form>

                                        <!-- View Details Button -->
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#awardDetailModal{{ $send->id }}" title="View Payload & Details">
                                            <i class="bi bi-code-slash"></i>
                                        </button>

                                        <!-- Details Modal -->
                                        <div class="modal fade text-start" id="awardDetailModal{{ $send->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-light">
                                                        <h5 class="modal-title fw-bold">
                                                            <i class="bi bi-trophy-fill text-warning me-2"></i>{{ $item['title'] }} ({{ $item['track_badge'] }})
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-6">
                                                                <div class="p-3 border rounded bg-light">
                                                                    <div class="text-muted small fw-bold">RECIPIENT</div>
                                                                    <div class="fw-bold fs-6 text-dark">{{ $user->display_name }}</div>
                                                                    <div class="font-monospace text-success small">{{ $send->phone_number }}</div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="p-3 border rounded bg-light">
                                                                    <div class="text-muted small fw-bold">DELIVERY STATUS</div>
                                                                    <div>
                                                                        <span class="badge {{ $send->status === 'delivered' ? 'bg-success' : ($send->status === 'failed' ? 'bg-danger' : 'bg-primary') }}">
                                                                            {{ ucfirst($send->status) }}
                                                                        </span>
                                                                    </div>
                                                                    <div class="text-muted small mt-1">Sent: {{ $send->sent_at?->format('d M Y, h:i:s A') ?? '-' }}</div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <div class="small fw-bold text-muted mb-1">Provider Message ID (Meta wamid):</div>
                                                            <div class="p-2 border rounded font-monospace small bg-light">{{ $send->provider_message_id ?: 'N/A' }}</div>
                                                        </div>

                                                        @if($send->error_message)
                                                            <div class="alert alert-danger py-2 px-3 small">
                                                                <strong>Error Log:</strong> {{ $send->error_message }}
                                                            </div>
                                                        @endif

                                                        <div class="mb-3">
                                                            <div class="small fw-bold text-muted mb-1">Provider Response JSON:</div>
                                                            <pre class="bg-dark text-light p-3 rounded small" style="max-height: 250px; overflow-y: auto;">{{ json_encode($send->provider_response_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <!-- Send Award Now Button -->
                                        <form action="{{ route('admin.whatsapp.awards.send_user_milestone', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Send WhatsApp award template {{ $item['title'] }} to {{ $userPhone ?: 'member' }}?');">
                                            @csrf
                                            <input type="hidden" name="track" value="{{ $item['track'] }}">
                                            <input type="hidden" name="milestone_count" value="{{ $item['milestone_count'] }}">
                                            <button type="submit" class="btn btn-sm btn-primary" title="Dispatch Award WhatsApp message to member now">
                                                <i class="bi bi-send me-1"></i>Send Now
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
function filterAwards(filter, btn) {
    // Update active tab buttons
    document.querySelectorAll('.filter-tab').forEach(el => {
        el.classList.remove('btn-primary', 'active');
        el.classList.add('btn-outline-secondary', 'border-0');
    });
    btn.classList.remove('btn-outline-secondary', 'border-0');
    btn.classList.add('btn-primary', 'active');

    // Filter table rows
    const rows = document.querySelectorAll('#awardsTimelineTable tbody tr.award-row');
    rows.forEach(row => {
        if (filter === 'all') {
            row.style.display = '';
        } else if (filter === 'growth') {
            row.style.display = row.classList.contains('award-row-growth') ? '' : 'none';
        } else if (filter === 'impact') {
            row.style.display = row.classList.contains('award-row-impact') ? '' : 'none';
        }
    });
}
</script>
@endpush
@endsection
