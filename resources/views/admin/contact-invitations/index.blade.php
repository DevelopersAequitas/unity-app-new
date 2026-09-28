@extends('admin.layouts.app')

@section('title', 'Invitation Contacts')

@include('admin.partials.grid-head')

@push('styles')
<style>
.metric-card-link {
    display: block;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}
.metric-card-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -2px rgba(0, 0, 0, 0.08);
}
.metric-card-link.active-card {
    position: relative;
}
.metric-card-link.active-card-indigo {
    border-color: #6366f1 !important;
    background: linear-gradient(180deg, rgba(99, 102, 241, 0.08) 0%, rgba(99, 102, 241, 0.02) 100%) !important;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.3) !important;
}
.metric-card-link.active-card-emerald {
    border-color: #10b981 !important;
    background: linear-gradient(180deg, rgba(16, 185, 129, 0.08) 0%, rgba(16, 185, 129, 0.02) 100%) !important;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3) !important;
}
.metric-card-link.active-card-rose {
    border-color: #f43f5e !important;
    background: linear-gradient(180deg, rgba(244, 63, 94, 0.08) 0%, rgba(244, 63, 94, 0.02) 100%) !important;
    box-shadow: 0 0 0 2px rgba(244, 63, 94, 0.3) !important;
}

.filter-section {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 16px 18px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
}
.preset-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
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
</style>
@endpush

@section('content')
@if(session('success'))
    <div class="alert alert-success mb-3">{{ session('success') }}</div>
@endif

<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">
    {{-- Header with Export --}}
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Invitation Send Contacts</h2>
            <p class="text-xs t3 m-0 mt-0.5">Track and monitor peer referral invitations, WhatsApp message delivery status, and timestamps.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.contact-invitations.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </div>
    </div>

    {{-- Metric Stat Cards (Clickable to Filter) --}}
    <div class="row g-3 mb-4">
        {{-- Total Invitations --}}
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.contact-invitations.index', array_merge(request()->except(['status', 'page']), ['status' => ''])) }}" 
               class="metric-card-link border bs rounded-xl p-3 surface-2 {{ $activeStatus === '' ? 'active-card active-card-indigo' : '' }}" 
               title="Click to view all invitations">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-[11px] t3 font-medium uppercase tracking-wider">Total Invitations</div>
                    @if($activeStatus === '')
                        <span class="badge bg-indigo-500/10 text-indigo-600 text-[10px] px-1.5 py-0.5 rounded">Active</span>
                    @endif
                </div>
                <div class="text-xl font-bold t1 mt-1">{{ number_format($stats['total_invitations'] ?? 0) }}</div>
                <div class="text-[11px] t3 mt-0.5"><i class="bi bi-send text-indigo-400 me-1"></i>All selected contacts</div>
            </a>
        </div>

        {{-- WhatsApp Completed --}}
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.contact-invitations.index', array_merge(request()->except(['status', 'page']), ['status' => 'completed'])) }}" 
               class="metric-card-link border bs rounded-xl p-3 surface-2 {{ $activeStatus === 'completed' ? 'active-card active-card-emerald' : '' }}" 
               title="Click to filter WhatsApp completed">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-[11px] text-emerald-500 font-medium uppercase tracking-wider">WhatsApp Completed</div>
                    @if($activeStatus === 'completed')
                        <span class="badge bg-emerald-500/20 text-emerald-600 text-[10px] px-1.5 py-0.5 rounded">Active Filter</span>
                    @endif
                </div>
                <div class="text-xl font-bold text-emerald-500 mt-1">{{ number_format($stats['total_completed'] ?? 0) }}</div>
                <div class="text-[11px] text-emerald-500/80 mt-0.5"><i class="bi bi-check2-circle me-1"></i>Delivered successfully</div>
            </a>
        </div>

        {{-- Not Completed / Failed --}}
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.contact-invitations.index', array_merge(request()->except(['status', 'page']), ['status' => 'not_completed'])) }}" 
               class="metric-card-link border bs rounded-xl p-3 surface-2 {{ ($activeStatus === 'not_completed' || $activeStatus === 'failed') ? 'active-card active-card-rose' : '' }}" 
               title="Click to filter pending or failed deliveries">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-[11px] text-rose-500 font-medium uppercase tracking-wider">Not Completed / Failed</div>
                    @if($activeStatus === 'not_completed' || $activeStatus === 'failed')
                        <span class="badge bg-rose-500/20 text-rose-600 text-[10px] px-1.5 py-0.5 rounded">Active Filter</span>
                    @endif
                </div>
                <div class="text-xl font-bold text-rose-500 mt-1">{{ number_format($stats['total_not_completed'] ?? 0) }}</div>
                <div class="text-[11px] text-rose-500/80 mt-0.5"><i class="bi bi-exclamation-triangle me-1"></i>Pending / Not sent</div>
            </a>
        </div>

        {{-- Referring Peers --}}
        <div class="col-6 col-md-3">
            <a href="{{ route('admin.contact-invitations.index', array_merge(request()->except(['user_id', 'status', 'page']))) }}" 
               class="metric-card-link border bs rounded-xl p-3 surface-2" 
               title="View all unique referring peers">
                <div class="text-[11px] text-indigo-400 font-medium uppercase tracking-wider">Referring Peers</div>
                <div class="text-xl font-bold text-indigo-500 mt-1">{{ number_format($stats['total_referrers'] ?? 0) }}</div>
                <div class="text-[11px] t3 mt-0.5"><i class="bi bi-people me-1"></i>Unique active referrers</div>
            </a>
        </div>
    </div>

    {{-- Main Table Container --}}
    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="p-4">
            {{-- Filter Section with Quick Date Range & Advanced Filters --}}
            <div class="filter-section border bs rounded-xl p-4 mb-4 surface-2">
                <form id="contactInvitationFilterForm" method="GET" action="{{ route('admin.contact-invitations.index') }}">
                    {{-- 1. Quick Date Range Preset Pills (Matching Image 2) --}}
                    <div class="mb-3 pb-3 border-b bs">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-muted mb-2 d-flex align-items-center">
                            <i class="bi bi-calendar-event me-1.5 text-indigo-500"></i> Quick Date Range:
                        </div>
                        <div class="preset-pills">
                            <a href="{{ route('admin.contact-invitations.index', array_merge(request()->except(['date_preset', 'quick', 'from_date', 'to_date', 'page']), ['date_preset' => ''])) }}"
                               class="preset-pill {{ $activePreset === '' ? 'active' : '' }}">All Time</a>
                            @foreach($presets as $key => $label)
                                <a href="{{ route('admin.contact-invitations.index', array_merge(request()->except(['date_preset', 'quick', 'from_date', 'to_date', 'page']), ['date_preset' => $key])) }}"
                                   class="preset-pill {{ $activePreset === $key ? 'active' : '' }}">{{ $label }}</a>
                            @endforeach
                        </div>
                        <input type="hidden" name="date_preset" value="{{ $filters['date_preset'] ?? '' }}">
                    </div>

                    {{-- 2. Date Range Inputs & Action Buttons --}}
                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-12 col-md-3">
                            <label class="block text-[11px] t3 mb-1 font-semibold uppercase tracking-wider" for="from_date">From Date</label>
                            <input type="date" id="from_date" name="from_date" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['from_date'] ?? '' }}">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="block text-[11px] t3 mb-1 font-semibold uppercase tracking-wider" for="to_date">To Date</label>
                            <input type="date" id="to_date" name="to_date" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['to_date'] ?? '' }}">
                        </div>
                        <div class="col-12 col-md-6 d-flex gap-2">
                            <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition border-0 d-inline-flex align-items-center gap-1.5">
                                <i class="bi bi-funnel"></i> Apply Filter
                            </button>
                            <a href="{{ route('admin.contact-invitations.index') }}" class="px-3 py-2 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline d-inline-flex align-items-center gap-1.5">
                                <i class="bi bi-x-circle"></i> Clear
                            </a>
                        </div>
                    </div>

                    {{-- 3. Advanced Filters --}}
                    <div class="border-t bs pt-3">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-muted mb-2 d-flex align-items-center">
                            <i class="bi bi-sliders me-1.5 text-indigo-500"></i> Advanced Filters:
                        </div>
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-4">
                                <label class="block text-[11px] t3 mb-1 font-medium" for="search">Global Search</label>
                                <input type="text" id="search" name="search" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['search'] ?? '' }}" placeholder="Sender / Contact Name / Phone / Email">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="block text-[11px] t3 mb-1 font-medium" for="status">WhatsApp Status</label>
                                <select id="status" name="status" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" onchange="this.form.submit()">
                                    <option value="" @selected(($filters['status'] ?? '') === '')>Any Status (All)</option>
                                    <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>✓ WhatsApp Completed</option>
                                    <option value="not_completed" @selected(($filters['status'] ?? '') === 'not_completed')>✕ Not Completed / Failed</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="block text-[11px] t3 mb-1 font-medium" for="user_id">Sender Peer</label>
                                <select id="user_id" name="user_id" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" onchange="this.form.submit()">
                                    <option value="">All Referrers</option>
                                    @foreach ($users as $u)
                                        <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? '') == $u->id)>{{ $u->adminDisplayName() }} ({{ $u->phone ?: 'No Phone' }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Sender Peer</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Invited Contact</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Contact Mobile</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Contact Email</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">WhatsApp Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">WhatsApp Sent At</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created Date</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($invitations as $invitation)
                            <tr class="hover:surface-2 transition border-b bs">
                                {{-- Sender User --}}
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    <div class="font-semibold t1 text-[12.5px]">
                                        {{ $invitation->user?->adminDisplayName() ?? 'Unknown Peer' }}
                                    </div>
                                    <div class="text-[11px] t3">
                                        {{ $invitation->user?->phone ?? $invitation->user?->email ?? '-' }}
                                    </div>
                                    @if($invitation->user?->adminCircleLabel() && $invitation->user->adminCircleLabel() !== 'No Circle')
                                        <span class="badge bg-secondary bg-opacity-25 text-secondary text-[10px] mt-0.5">
                                            {{ $invitation->user->adminCircleLabel() }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Invited Contact Name --}}
                                <td class="px-3 py-2.5 font-medium t1 text-[12.5px] whitespace-nowrap">
                                    <div class="d-flex align-items-center gap-1.5">
                                        <i class="bi bi-person-fill text-indigo-400"></i>
                                        <span>{{ $invitation->contact_name }}</span>
                                    </div>
                                    @if($invitation->contact_post_id)
                                        <span class="badge bg-info bg-opacity-10 text-info text-[10px]" title="Matched with Synced Contact Post">
                                            <i class="bi bi-link-45deg"></i> Synced Contact
                                        </span>
                                    @endif
                                </td>

                                {{-- Phone --}}
                                <td class="px-3 py-2.5 text-[12.5px] whitespace-nowrap">
                                    <span class="font-mono text-xs">{{ $invitation->contact_phone }}</span>
                                    @if($invitation->mobile_normalized && $invitation->mobile_normalized !== $invitation->contact_phone)
                                        <div class="text-[10px] t3 font-mono">Normalized: {{ $invitation->mobile_normalized }}</div>
                                    @endif
                                </td>

                                {{-- Email --}}
                                <td class="px-3 py-2.5 text-[12.5px] text-muted whitespace-nowrap">
                                    {{ $invitation->contact_email ?: '—' }}
                                </td>

                                {{-- WhatsApp Status --}}
                                <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                    @if ($invitation->whatsapp_status === 'completed')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            <i class="bi bi-check-circle-fill"></i> Completed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20" title="{{ $invitation->error_message ?? 'Not Sent' }}">
                                            <i class="bi bi-x-circle-fill"></i> Not Completed
                                        </span>
                                    @endif
                                </td>

                                {{-- Sent At --}}
                                <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    @if ($invitation->whatsapp_sent_at)
                                        <span class="t1 font-medium">{{ $invitation->whatsapp_sent_at->format('d M Y') }}</span>
                                        <div class="text-[11px] t3">{{ $invitation->whatsapp_sent_at->format('h:i:s A') }}</div>
                                    @else
                                        <span class="t3">—</span>
                                    @endif
                                </td>

                                {{-- Created At --}}
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                    {{ $invitation->created_at ? $invitation->created_at->format('d M Y, h:i A') : '—' }}
                                </td>

                                {{-- Actions --}}
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    <button type="button" 
                                            class="px-2.5 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 hover:surface-2 transition inline-flex items-center gap-1"
                                            onclick="openInvitationDetailModal({{ json_encode([
                                                'id' => $invitation->id,
                                                'sender_name' => $invitation->user?->adminDisplayName() ?? '—',
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
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-10 text-xs t3">
                                    <i class="bi bi-inbox fs-3 d-block mb-2 text-muted"></i>
                                    No invitation contacts found matching the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Bottom Toolbar & Pagination --}}
            <div id="grid-pagination" class="flex justify-between items-center mt-4 flex-wrap gap-2 pt-3 border-t bs">
                <div>
                    {{ $invitations->links() }}
                </div>
                <div class="text-xs t3">
                    @if($invitations->total() > 0)
                        Showing <span class="font-semibold t1">{{ $invitations->firstItem() }}-{{ $invitations->lastItem() }}</span> of <span class="font-semibold t1">{{ $invitations->total() }}</span> records
                    @else
                        No records
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Detail Modal --}}
<div class="modal fade" id="invitationDetailModal" tabindex="-1" aria-labelledby="invitationDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content surface border bs rounded-xl">
            <div class="modal-header border-b bs py-3 px-4">
                <h5 class="modal-title text-sm font-semibold t1 d-flex align-items-center gap-2" id="invitationDetailModalLabel">
                    <i class="bi bi-whatsapp text-emerald-400"></i> Referral Invitation Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="invitationModalContent">
                {{-- Injected dynamically by Javascript --}}
            </div>
            <div class="modal-footer border-t bs py-2 px-4">
                <button type="button" class="btn btn-sm btn-secondary text-xs rounded-lg" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openInvitationDetailModal(data) {
    const isCompleted = data.whatsapp_status === 'completed';
    const statusBadge = isCompleted 
        ? '<span class="badge bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i> Completed (Sent)</span>'
        : '<span class="badge bg-rose-500/20 text-rose-400 border border-rose-500/30 px-2 py-1"><i class="bi bi-x-circle-fill me-1"></i> Not Completed</span>';

    let errorHtml = '';
    if (data.error_message) {
        errorHtml = `
            <div class="mt-3 p-3 rounded-lg border border-rose-500/30 bg-rose-500/10 text-rose-300 text-xs">
                <div class="font-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Delivery / Error Log:</div>
                <div class="font-mono text-[11px]">${escapeHtml(data.error_message)}</div>
            </div>
        `;
    }

    const html = `
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <div class="p-3 rounded-xl border bs surface-2">
                    <div class="text-[11px] text-indigo-400 font-semibold uppercase tracking-wider mb-2">
                        <i class="bi bi-person-badge me-1"></i> Sender Peer Info
                    </div>
                    <div class="text-sm font-bold t1">${escapeHtml(data.sender_name)}</div>
                    <div class="text-xs t2 mt-1"><i class="bi bi-telephone me-1 text-muted"></i>${escapeHtml(data.sender_phone)}</div>
                    <div class="text-xs t2 mt-0.5"><i class="bi bi-envelope me-1 text-muted"></i>${escapeHtml(data.sender_email)}</div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="p-3 rounded-xl border bs surface-2">
                    <div class="text-[11px] text-indigo-400 font-semibold uppercase tracking-wider mb-2">
                        <i class="bi bi-person-lines-fill me-1"></i> Invited Contact Info
                    </div>
                    <div class="text-sm font-bold t1">${escapeHtml(data.contact_name)}</div>
                    <div class="text-xs t2 mt-1"><i class="bi bi-phone me-1 text-muted"></i>${escapeHtml(data.contact_phone)}</div>
                    <div class="text-xs t2 mt-0.5"><i class="bi bi-globe me-1 text-muted"></i>Normalized: ${escapeHtml(data.mobile_normalized)}</div>
                    <div class="text-xs t2 mt-0.5"><i class="bi bi-envelope me-1 text-muted"></i>${escapeHtml(data.contact_email)}</div>
                </div>
            </div>
            <div class="col-12">
                <div class="p-3 rounded-xl border bs surface-2">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-[11px] text-indigo-400 font-semibold uppercase tracking-wider">
                            <i class="bi bi-chat-left-dots me-1"></i> Delivery & Timeline
                        </div>
                        <div>${statusBadge}</div>
                    </div>
                    <div class="row g-2 text-xs t2">
                        <div class="col-6"><strong>Sent At:</strong> ${escapeHtml(data.whatsapp_sent_at)}</div>
                        <div class="col-6"><strong>Created At:</strong> ${escapeHtml(data.created_at)}</div>
                    </div>
                    <div class="mt-3">
                        <div class="text-[11px] t3 font-medium mb-1">Invitation Message Text:</div>
                        <div class="p-2.5 rounded-lg border bs surface text-xs t1 bg-opacity-50">
                            ${escapeHtml(data.invitation_message)}
                        </div>
                    </div>
                    ${errorHtml}
                </div>
            </div>
        </div>
    `;

    document.getElementById('invitationModalContent').innerHTML = html;
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
</script>
@endpush
@endsection
