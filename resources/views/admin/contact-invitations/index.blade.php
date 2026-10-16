@extends('admin.layouts.app')

@section('title', 'Contact Referral Invitations - Meta WhatsApp')

@push('styles')
<style>
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

.preset-pill {
    padding: 4px 14px;
    border-radius: 9999px;
    font-size: 12px;
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
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
}

.avatar-circle {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 13px;
    flex-shrink: 0;
}

.table > :not(caption) > * > * {
    padding: 0.75rem 0.85rem;
    vertical-align: middle;
}

.bulk-floating-bar {
    position: sticky;
    bottom: 16px;
    z-index: 1020;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
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

    {{-- Top Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded">
                    <i class="bi bi-whatsapp me-1"></i> Meta Cloud API
                </span>
                <span class="text-muted small">/ Peer Referral System</span>
            </div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-person-lines-fill text-success"></i> Contact Referral Invitations
            </h1>
            <p class="text-muted small mb-0">Track peer-to-peer invitation dispatches, delivery statuses, logs, and resend WhatsApp messages.</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()" title="Refresh Logs">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
            <a href="{{ route('admin.contact-invitations.export', request()->query()) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
            <a href="{{ route('admin.whatsapp.messages') }}" class="btn btn-outline-secondary btn-sm" title="View all Meta WhatsApp logs">
                <i class="bi bi-chat-dots me-1"></i>All WhatsApp Logs
            </a>
        </div>
    </div>

    {{-- Stats Cards (Clickable to Filter) --}}
    <div class="row g-3 mb-4">
        {{-- Total Invitations --}}
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.contact-invitations.index', array_merge(request()->except(['status', 'page']), ['status' => ''])) }}" 
               class="stat-widget card shadow-sm border-0 border-start border-primary border-4 h-100 p-3 {{ $activeStatus === '' ? 'active-widget' : '' }}" 
               title="Show all invitations">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small text-uppercase fw-semibold">Total Invitations</span>
                    @if($activeStatus === '')
                        <span class="badge bg-primary text-white" style="font-size: 10px;">Active</span>
                    @endif
                </div>
                <div class="h4 mb-0 fw-bold text-gray-800 mt-2">{{ number_format($stats['total_invitations'] ?? 0) }}</div>
                <div class="text-muted small mt-1"><i class="bi bi-send text-primary me-1"></i>All contact referrals</div>
            </a>
        </div>

        {{-- WhatsApp Completed --}}
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.contact-invitations.index', array_merge(request()->except(['status', 'page']), ['status' => 'completed'])) }}" 
               class="stat-widget card shadow-sm border-0 border-start border-success border-4 h-100 p-3 {{ $activeStatus === 'completed' ? 'active-widget' : '' }}" 
               title="Filter by WhatsApp Completed">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-success small text-uppercase fw-semibold">WhatsApp Completed</span>
                    @if($activeStatus === 'completed')
                        <span class="badge bg-success text-white" style="font-size: 10px;">Active Filter</span>
                    @endif
                </div>
                <div class="h4 mb-0 fw-bold text-success mt-2">{{ number_format($stats['total_completed'] ?? 0) }}</div>
                <div class="text-success small mt-1"><i class="bi bi-check2-circle me-1"></i>Delivered / Sent</div>
            </a>
        </div>

        {{-- Not Completed / Failed --}}
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.contact-invitations.index', array_merge(request()->except(['status', 'page']), ['status' => 'not_completed'])) }}" 
               class="stat-widget card shadow-sm border-0 border-start border-danger border-4 h-100 p-3 {{ ($activeStatus === 'not_completed' || $activeStatus === 'failed') ? 'active-widget' : '' }}" 
               title="Filter by Pending or Failed">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-danger small text-uppercase fw-semibold">Pending / Failed</span>
                    @if($activeStatus === 'not_completed' || $activeStatus === 'failed')
                        <span class="badge bg-danger text-white" style="font-size: 10px;">Active Filter</span>
                    @endif
                </div>
                <div class="h4 mb-0 fw-bold text-danger mt-2">{{ number_format($stats['total_not_completed'] ?? 0) }}</div>
                <div class="text-danger small mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Requires resend</div>
            </a>
        </div>

        {{-- Referring Peers --}}
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.contact-invitations.index', array_merge(request()->except(['user_id', 'status', 'page']))) }}" 
               class="stat-widget card shadow-sm border-0 border-start border-info border-4 h-100 p-3" 
               title="Unique Referring Peers">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-info small text-uppercase fw-semibold">Referring Peers</span>
                </div>
                <div class="h4 mb-0 fw-bold text-info mt-2">{{ number_format($stats['total_referrers'] ?? 0) }}</div>
                <div class="text-muted small mt-1"><i class="bi bi-people me-1"></i>Active app sharers</div>
            </a>
        </div>
    </div>

    {{-- Peer Sharing Activity --}}
    @if(isset($userShares) && $userShares->count() > 0)
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-2.5 px-3 d-flex justify-content-between align-items-center border-bottom">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-share-fill text-primary"></i>
                <span class="fw-bold text-dark small text-uppercase" style="letter-spacing: 0.5px;">Peer Sharing Activity</span>
            </div>
            <span class="text-muted small">Top {{ $userShares->count() }} active sharers</span>
        </div>
        <div class="card-body p-3">
            <div class="row g-2">
                @foreach($userShares as $share)
                    @php
                        $peerName = $share->user?->adminDisplayName() ?? 'Unknown Peer';
                        $initial = strtoupper(substr($peerName, 0, 1));
                        $isCurrentSelected = request('user_id') == $share->user_id;
                    @endphp
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="p-2.5 rounded-3 border d-flex justify-content-between align-items-center {{ $isCurrentSelected ? 'bg-primary-subtle border-primary' : 'bg-light' }}">
                            <div class="d-flex align-items-center gap-2 truncate me-2" style="max-width: 180px;">
                                <div class="avatar-circle bg-primary text-white">
                                    {{ $initial }}
                                </div>
                                <div class="truncate">
                                    <div class="small fw-bold text-dark truncate" title="{{ $peerName }}">{{ $peerName }}</div>
                                    <div class="text-muted" style="font-size: 11px;">{{ $share->user?->phone ?? $share->user?->email ?? '-' }}</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-1.5 flex-shrink-0">
                                <span class="badge bg-primary text-white" title="Total contacts shared">
                                    {{ $share->total_shared }} Shared
                                </span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" title="Sent / Completed">
                                    ✓ {{ $share->sent_count }}
                                </span>
                                @if($share->pending_failed_count > 0)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="Pending / Not Sent">
                                        ✕ {{ $share->pending_failed_count }}
                                    </span>
                                @endif
                                <a href="{{ route('admin.contact-invitations.index', ['user_id' => $share->user_id]) }}" 
                                   class="btn btn-sm {{ $isCurrentSelected ? 'btn-primary' : 'btn-outline-secondary' }} px-2 py-0.5" 
                                   style="font-size: 11px;" 
                                   title="Filter by this user">
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

    {{-- Filter Section --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form id="contactInvitationFilterForm" method="GET" action="{{ route('admin.contact-invitations.index') }}">
                {{-- Quick Presets --}}
                <div class="d-flex flex-wrap align-items-center gap-2 pb-3 mb-3 border-bottom">
                    <span class="text-muted small fw-semibold text-uppercase me-1" style="font-size: 11px;">
                        <i class="bi bi-calendar-event me-1 text-primary"></i>Quick Range:
                    </span>
                    <a href="{{ route('admin.contact-invitations.index', array_merge(request()->except(['date_preset', 'quick', 'from_date', 'to_date', 'page']), ['date_preset' => ''])) }}"
                       class="preset-pill {{ $activePreset === '' ? 'active' : '' }}">All Time</a>
                    @foreach($presets as $key => $label)
                        <a href="{{ route('admin.contact-invitations.index', array_merge(request()->except(['date_preset', 'quick', 'from_date', 'to_date', 'page']), ['date_preset' => $key])) }}"
                           class="preset-pill {{ $activePreset === $key ? 'active' : '' }}">{{ $label }}</a>
                    @endforeach
                    <input type="hidden" name="date_preset" value="{{ $filters['date_preset'] ?? '' }}">
                </div>

                {{-- Filter Controls --}}
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1" for="search">Search</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input type="text" id="search" name="search" class="form-control" placeholder="Sender, Contact, or Mobile..." value="{{ $filters['search'] ?? '' }}">
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold text-muted mb-1" for="status">WhatsApp Status</label>
                        <select id="status" name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>✓ Completed</option>
                            <option value="not_completed" @selected(($filters['status'] ?? '') === 'not_completed' || ($filters['status'] ?? '') === 'failed')>✕ Pending / Failed</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold text-muted mb-1" for="from_date">From Date</label>
                        <input type="date" id="from_date" name="from_date" class="form-control form-control-sm" value="{{ $filters['from_date'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold text-muted mb-1" for="to_date">To Date</label>
                        <input type="date" id="to_date" name="to_date" class="form-control form-control-sm" value="{{ $filters['to_date'] ?? '' }}">
                    </div>
                    <div class="col-6 col-md-3 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        <a href="{{ route('admin.contact-invitations.index') }}" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Invitations Table Card --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-2.5 px-3 d-flex justify-content-between align-items-center border-bottom flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="small fw-semibold text-muted text-uppercase" style="letter-spacing: 0.5px;">Invitation Dispatch Log</span>
                @if($invitations->total() > 0)
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle small py-1 px-2">
                        {{ number_format($invitations->total()) }} Records
                    </span>
                @endif
            </div>

            {{-- Bulk Actions Bar (visible when checked) --}}
            <div id="bulkActionsBar" class="d-none d-flex align-items-center gap-2">
                <span class="badge bg-dark text-white py-1.5 px-2.5 small">
                    <span id="selectedCount">0</span> selected
                </span>
                <button type="button" class="btn btn-sm btn-success py-1 px-2.5 d-inline-flex align-items-center gap-1 small" onclick="bulkResendSelected()">
                    <i class="bi bi-whatsapp"></i> Resend WhatsApp (Selected)
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 small" onclick="clearSelected()">
                    Deselect
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center ps-3" style="width: 42px;">
                            <input type="checkbox" id="selectAllCheckbox" class="form-check-input" onchange="toggleSelectAll(this)" title="Select all on this page">
                        </th>
                        <th style="min-width: 170px;">Sender Peer</th>
                        <th style="min-width: 200px;">Invited Contact</th>
                        <th style="min-width: 160px;">WhatsApp Delivery Status</th>
                        <th style="min-width: 130px;">Created Date</th>
                        <th class="text-end pe-3" style="min-width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invitations as $invitation)
                        @php
                            $senderName = $invitation->user?->adminDisplayName() ?? 'Unknown Peer';
                            $senderInitial = strtoupper(substr($senderName, 0, 1));
                            $isSent = $invitation->whatsapp_status === 'completed';
                        @endphp
                        <tr class="row-invite-{{ $invitation->id }}">
                            {{-- Checkbox --}}
                            <td class="text-center ps-3">
                                <input type="checkbox" class="form-check-input invite-checkbox" value="{{ $invitation->id }}" onchange="updateSelectedCount()">
                            </td>

                            {{-- Sender Peer --}}
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-circle bg-secondary-subtle text-secondary small">
                                        {{ $senderInitial }}
                                    </div>
                                    <div class="truncate" style="max-width: 170px;">
                                        <div class="fw-bold text-dark small truncate" title="{{ $senderName }}">
                                            {{ $senderName }}
                                        </div>
                                        <div class="text-muted" style="font-size: 11px;">
                                            {{ $invitation->user?->phone ?? $invitation->user?->email ?? '-' }}
                                        </div>
                                        @if($invitation->user?->adminCircleLabel() && $invitation->user->adminCircleLabel() !== 'No Circle')
                                            <span class="badge bg-light text-muted border py-0.5 px-1 mt-0.5" style="font-size: 10px;">
                                                {{ $invitation->user->adminCircleLabel() }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Invited Contact --}}
                            <td>
                                <div>
                                    <div class="fw-bold text-dark small d-flex align-items-center gap-1.5">
                                        <i class="bi bi-person text-primary"></i>
                                        <span>{{ $invitation->contact_name }}</span>
                                        @if($invitation->contact_post_id)
                                            <span class="badge bg-info-subtle text-info border border-info-subtle py-0.5 px-1" style="font-size: 9.5px;" title="Linked to Synced Unity Contact">
                                                Synced
                                            </span>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-1.5 mt-0.5">
                                        <i class="bi bi-whatsapp text-success" style="font-size: 11px;"></i>
                                        <span class="font-monospace fw-semibold text-dark small">{{ $invitation->contact_phone }}</span>
                                    </div>
                                    @if($invitation->contact_email)
                                        <div class="text-muted" style="font-size: 11px;">
                                            <i class="bi bi-envelope text-muted me-1"></i>{{ $invitation->contact_email }}
                                        </div>
                                    @endif
                                </div>
                            </td>

                            {{-- WhatsApp Status & Sent Time --}}
                            <td>
                                <div>
                                    @if ($isSent)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 small d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-check-circle-fill"></i> Completed
                                        </span>
                                        <div class="text-muted mt-1" style="font-size: 11px;">
                                            <i class="bi bi-clock me-1"></i>{{ $invitation->whatsapp_sent_at ? $invitation->whatsapp_sent_at->format('d M Y, h:i A') : '-' }}
                                        </div>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-1 px-2 small d-inline-flex align-items-center gap-1" title="{{ $invitation->error_message ?? 'Not Sent' }}">
                                            <i class="bi bi-x-circle-fill"></i> Not Completed
                                        </span>
                                        @if($invitation->error_message)
                                            <div class="text-danger truncate mt-1" style="font-size: 10.5px; max-width: 180px;" title="{{ $invitation->error_message }}">
                                                {{ Str::limit($invitation->error_message, 25) }}
                                            </div>
                                        @else
                                            <div class="text-muted mt-1" style="font-size: 11px;">Pending delivery</div>
                                        @endif
                                    @endif
                                </div>
                            </td>

                            {{-- Created Date --}}
                            <td class="small">
                                <div class="text-dark">{{ $invitation->created_at ? $invitation->created_at->format('d M Y') : '—' }}</div>
                                <div class="text-muted" style="font-size: 11px;">{{ $invitation->created_at ? $invitation->created_at->format('h:i A') : '' }}</div>
                            </td>

                            {{-- Actions --}}
                            <td class="text-end pe-3">
                                <div class="d-inline-flex align-items-center gap-1">
                                    {{-- Resend WhatsApp Button --}}
                                    <button type="button" 
                                            id="resendBtn-{{ $invitation->id }}"
                                            class="btn btn-sm btn-outline-success py-1 px-2.5 d-inline-flex align-items-center gap-1 small"
                                            title="Resend WhatsApp invitation"
                                            onclick="resendInvitation('{{ $invitation->id }}', this)">
                                        <i class="bi bi-whatsapp"></i> Resend
                                    </button>

                                    {{-- Details Button --}}
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-secondary py-1 px-2.5 d-inline-flex align-items-center gap-1 small"
                                            title="View full invitation details"
                                            onclick="openInvitationDetailModal({{ json_encode([
                                                'id' => $invitation->id,
                                                'sender_name' => $senderName,
                                                'sender_phone' => $invitation->user?->phone ?? '—',
                                                'sender_email' => $invitation->user?->email ?? '—',
                                                'contact_name' => $invitation->contact_name,
                                                'contact_phone' => $invitation->contact_phone,
                                                'mobile_normalized' => $invitation->mobile_normalized ?? '—',
                                                'contact_email' => $invitation->contact_email ?? '—',
                                                'status' => $invitation->status,
                                                'whatsapp_status' => $invitation->whatsapp_status,
                                                'whatsapp_sent_at' => $invitation->whatsapp_sent_at ? $invitation->whatsapp_sent_at->format('d M Y, h:i:s A') : 'Not sent yet',
                                                'created_at' => $invitation->created_at ? $invitation->created_at->format('d M Y, h:i:s A') : '—',
                                                'invitation_message' => $invitation->invitation_message ?? '—',
                                                'error_message' => $invitation->error_message ?? null,
                                            ]) }})">
                                        <i class="bi bi-eye"></i> Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted small">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                                No referral invitations found matching your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer --}}
        <div class="card-footer bg-white py-3 px-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                {{ $invitations->links() }}
            </div>
            <div class="small text-muted">
                @if($invitations->total() > 0)
                    Showing {{ $invitations->firstItem() }} to {{ $invitations->lastItem() }} of {{ number_format($invitations->total()) }} invitations
                @else
                    0 invitations
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Detail Modal --}}
<div class="modal fade" id="invitationDetailModal" tabindex="-1" aria-labelledby="invitationDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white py-2.5 px-3">
                <h5 class="modal-title small fw-bold d-flex align-items-center gap-2 mb-0" id="invitationDetailModalLabel">
                    <i class="bi bi-whatsapp"></i> WhatsApp Referral Invitation Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="invitationModalContent">
                {{-- Injected dynamically by Javascript --}}
            </div>
            <div class="modal-footer bg-light py-2 px-3 d-flex justify-content-between">
                <button type="button" id="modalResendBtn" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1.5 px-3">
                    <i class="bi bi-whatsapp"></i> Resend WhatsApp Message
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let currentModalInviteId = null;

function openInvitationDetailModal(data) {
    currentModalInviteId = data.id;
    const isCompleted = data.whatsapp_status === 'completed';
    const statusBadge = isCompleted 
        ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i> Completed (Delivered)</span>'
        : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="bi bi-x-circle-fill me-1"></i> Not Completed / Pending</span>';

    let errorHtml = '';
    if (data.error_message) {
        errorHtml = `
            <div class="mt-3 p-3 rounded-3 border border-danger-subtle bg-danger-subtle text-danger small">
                <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Error / Delivery Log:</div>
                <div class="font-monospace text-[11px]">${escapeHtml(data.error_message)}</div>
            </div>
        `;
    }

    const html = `
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <div class="p-3 rounded-3 border bg-light h-100">
                    <div class="small text-primary fw-bold text-uppercase mb-2">
                        <i class="bi bi-person-badge me-1"></i> Sender Peer Info
                    </div>
                    <div class="fw-bold text-dark fs-6">${escapeHtml(data.sender_name)}</div>
                    <div class="small text-muted mt-1"><i class="bi bi-telephone me-1"></i>${escapeHtml(data.sender_phone)}</div>
                    <div class="small text-muted mt-0.5"><i class="bi bi-envelope me-1"></i>${escapeHtml(data.sender_email)}</div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="p-3 rounded-3 border bg-light h-100">
                    <div class="small text-primary fw-bold text-uppercase mb-2">
                        <i class="bi bi-person-lines-fill me-1"></i> Invited Contact Info
                    </div>
                    <div class="fw-bold text-dark fs-6">${escapeHtml(data.contact_name)}</div>
                    <div class="small text-dark fw-semibold mt-1"><i class="bi bi-whatsapp text-success me-1"></i>${escapeHtml(data.contact_phone)}</div>
                    <div class="small text-muted mt-0.5"><i class="bi bi-globe me-1"></i>Normalized: ${escapeHtml(data.mobile_normalized)}</div>
                    <div class="small text-muted mt-0.5"><i class="bi bi-envelope me-1"></i>${escapeHtml(data.contact_email)}</div>
                </div>
            </div>
            <div class="col-12">
                <div class="p-3 rounded-3 border bg-white shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="small text-primary fw-bold text-uppercase">
                            <i class="bi bi-chat-left-dots me-1"></i> WhatsApp Status & Delivery
                        </div>
                        <div>${statusBadge}</div>
                    </div>
                    <div class="row g-2 small text-muted mb-2">
                        <div class="col-6"><strong>Sent At:</strong> ${escapeHtml(data.whatsapp_sent_at)}</div>
                        <div class="col-6"><strong>Created At:</strong> ${escapeHtml(data.created_at)}</div>
                    </div>
                    <div class="mt-3">
                        <div class="small fw-semibold text-dark mb-1">Message Text / Template:</div>
                        <div class="p-2.5 rounded-2 border bg-light font-monospace small text-dark">
                            ${escapeHtml(data.invitation_message)}
                        </div>
                    </div>
                    ${errorHtml}
                </div>
            </div>
        </div>
    `;

    document.getElementById('invitationModalContent').innerHTML = html;
    
    // Wire up modal resend button
    const modalResendBtn = document.getElementById('modalResendBtn');
    modalResendBtn.onclick = function() {
        resendInvitation(currentModalInviteId, this);
    };

    new bootstrap.Modal(document.getElementById('invitationDetailModal')).show();
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function toggleSelectAll(masterCheckbox) {
    const checkboxes = document.querySelectorAll('.invite-checkbox');
    checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const selected = document.querySelectorAll('.invite-checkbox:checked');
    const count = selected.length;
    const bar = document.getElementById('bulkActionsBar');
    const countSpan = document.getElementById('selectedCount');
    
    if (count > 0) {
        bar.classList.remove('d-none');
        countSpan.textContent = count;
    } else {
        bar.classList.add('d-none');
        document.getElementById('selectAllCheckbox').checked = false;
    }
}

function clearSelected() {
    document.querySelectorAll('.invite-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectedCount();
}

async function resendInvitation(id, btn) {
    if (!confirm('Are you sure you want to resend this WhatsApp invitation?')) {
        return;
    }

    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Sending...';

    try {
        const response = await fetch(`{{ url('admin/contact-invitations') }}/${id}/resend`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const result = await response.json();

        if (response.ok && result.success) {
            alert('✓ ' + result.message);
            window.location.reload();
        } else {
            alert('✕ ' + (result.message || 'Failed to resend invitation.'));
        }
    } catch (e) {
        alert('✕ Network error while resending: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

async function bulkResendSelected() {
    const selected = Array.from(document.querySelectorAll('.invite-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) {
        alert('Please select at least one invitation to resend.');
        return;
    }

    if (!confirm(`Are you sure you want to resend WhatsApp invitations to ${selected.length} selected contacts?`)) {
        return;
    }

    try {
        const response = await fetch(`{{ route('admin.contact-invitations.bulk-resend') }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ ids: selected })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            alert('✓ ' + result.message);
            window.location.reload();
        } else {
            alert('✕ ' + (result.message || 'Failed to process bulk resend.'));
        }
    } catch (e) {
        alert('✕ Network error while bulk resending: ' + e.message);
    }
}
</script>
@endpush
@endsection
