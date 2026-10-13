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

    <!-- Metrics Cards (Clickable quick filters) -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.awards.index') }}" class="card shadow-sm border-0 border-start border-primary border-4 h-100 award-metric-card {{ $status === '' && $track === '' ? 'bg-primary-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-semibold">Total Awards</span>
                        <i class="bi bi-trophy text-primary"></i>
                    </div>
                    <div class="h4 mb-0 fw-bold text-gray-800">{{ number_format($stats['total_awards']) }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.awards.index', ['track' => 'GROWTH']) }}" class="card shadow-sm border-0 border-start border-success border-4 h-100 award-metric-card {{ $track === 'GROWTH' ? 'bg-success-subtle' : '' }}">
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
            <a href="{{ route('admin.whatsapp.awards.index', ['track' => 'LIFE_IMPACT']) }}" class="card shadow-sm border-0 border-start border-info border-4 h-100 award-metric-card {{ $track === 'LIFE_IMPACT' ? 'bg-info-subtle' : '' }}">
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
            <a href="{{ route('admin.whatsapp.awards.index', ['status' => 'sent_group']) }}" class="card shadow-sm border-0 border-start border-success border-4 h-100 award-metric-card {{ in_array($status, ['sent', 'delivered', 'read', 'sent_group']) ? 'bg-success-subtle' : '' }}">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small text-uppercase fw-semibold">Delivered</span>
                        <i class="bi bi-check2-all text-success"></i>
                    </div>
                    <div class="h4 mb-0 fw-bold text-success">{{ number_format($stats['total_sent']) }}</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.whatsapp.awards.index', ['status' => 'read']) }}" class="card shadow-sm border-0 border-start border-primary border-4 h-100 award-metric-card {{ $status === 'read' ? 'bg-primary-subtle' : '' }}">
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
            <a href="{{ route('admin.whatsapp.awards.index', ['status' => 'failed']) }}" class="card shadow-sm border-0 border-start border-danger border-4 h-100 award-metric-card {{ $status === 'failed' ? 'bg-danger-subtle' : '' }}">
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

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('admin.whatsapp.awards.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search by member, phone, template..." value="{{ $search }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="track" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Award Tracks</option>
                        <option value="GROWTH" {{ $track === 'GROWTH' ? 'selected' : '' }}>Track 1: Growth (Introductions)</option>
                        <option value="LIFE_IMPACT" {{ $track === 'LIFE_IMPACT' ? 'selected' : '' }}>Track 2: Life Impact</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="sent" {{ $status === 'sent' ? 'selected' : '' }}>Sent</option>
                        <option value="delivered" {{ $status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                        <option value="read" {{ $status === 'read' ? 'selected' : '' }}>Read</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Failed</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    @if($search || $track || $status)
                        <a href="{{ route('admin.whatsapp.awards.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Award Sends Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-gray-800">
                <i class="bi bi-list-task me-1 text-primary"></i>Award Recognition Deliveries
            </h6>
            <span class="text-muted small">Showing {{ $sends->firstItem() ?? 0 }} to {{ $sends->lastItem() ?? 0 }} of {{ $sends->total() }} records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 220px;">Member</th>
                        <th>Award / Milestone</th>
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
                                                        <div class="p-3 bg-light rounded border">
                                                            <div class="small text-muted mb-1">Recipient Member</div>
                                                            <div class="fw-bold">{{ $item->user?->display_name ?: $item->user?->first_name ?: 'Peer Member' }}</div>
                                                            <div class="font-monospace small text-primary">{{ $item->phone_number }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="p-3 bg-light rounded border">
                                                            <div class="small text-muted mb-1">Meta Details</div>
                                                            <div>Template: <span class="badge bg-secondary font-monospace">{{ $item->template_name }}</span></div>
                                                            <div class="small text-truncate mt-1">WAMID: <span class="font-monospace text-muted">{{ $item->provider_message_id ?? 'None' }}</span></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                @if(!empty($imgUrl))
                                                    <div class="mb-3 text-center p-3 bg-light rounded border">
                                                        <div class="small text-muted mb-2">Dynamic Header Creative</div>
                                                        <img src="{!! $imgUrl !!}" alt="Creative" class="img-fluid rounded shadow-sm" style="max-height: 250px;" referrerpolicy="no-referrer" onerror="this.onerror=null; this.src='{{ asset('images/member_introduce_creatives/Connector.png') }}';">
                                                    </div>
                                                @endif

                                                @if($item->error_message)
                                                    <div class="alert alert-danger mb-3">
                                                        <strong>Error Details:</strong> {{ $item->error_message }}
                                                    </div>
                                                @endif

                                                <div class="mb-2">
                                                    <label class="form-label small fw-bold text-muted">Sent Payload Components:</label>
                                                    <pre class="bg-dark text-light p-3 rounded font-monospace small" style="max-height: 180px; overflow-y: auto;">{{ json_encode($item->payload_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                </div>

                                                @if($item->provider_response_json)
                                                    <div>
                                                        <label class="form-label small fw-bold text-muted">Meta API Response:</label>
                                                        <pre class="bg-dark text-success p-3 rounded font-monospace small" style="max-height: 180px; overflow-y: auto;">{{ json_encode($item->provider_response_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </div>
                                                @endif
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
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                <strong>No Award Recognitions WhatsApp sends found.</strong>
                                <p class="small mb-0">Use the "Test Send Award" button above or trigger a milestone creative in the app.</p>
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
                        <label class="form-label fw-semibold">Member Name (Dynamic {{1}} & {{2}})</label>
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
