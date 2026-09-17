@extends('admin.layouts.app')

@section('title', 'Referral Report')

@include('admin.partials.grid-head')

@section('content')
@php
    $getInitials = function($name) {
        $words = explode(' ', trim($name));
        $initials = '';
        foreach ($words as $w) {
            if(!empty($w)) $initials .= strtoupper(substr($w, 0, 1));
        }
        return substr($initials, 0, 2) ?: 'P';
    };
    $getAvatarBg = function($name) {
        $colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
        $hash = crc32($name);
        return $colors[abs($hash) % count($colors)];
    };
@endphp

<style>
    /* Referral Report Specific Modern Styles */
    .ref-peer-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px 16px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        transition: all 0.2s ease;
    }
    .ref-peer-card:hover {
        border-color: #a5b4fc;
        box-shadow: 0 4px 14px -2px rgba(99, 102, 241, 0.12);
        transform: translateY(-1px);
    }
    .ref-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #ffffff;
        font-size: 14px;
        flex-shrink: 0;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .ref-peer-info {
        flex: 1;
        min-width: 0;
    }
    .ref-peer-name {
        font-size: 14px;
        font-weight: 700;
        color: #1e293b;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .ref-peer-name:hover {
        color: #4f46e5;
        text-decoration: underline;
    }
    .ref-meta-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
        font-size: 12px;
        color: #64748b;
        margin-top: 4px;
    }
    .ref-meta-item {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .ref-contact-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 14px;
        font-size: 11.5px;
        color: #64748b;
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px dashed #f1f5f9;
    }
    .ref-right-col {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 6px;
        flex-shrink: 0;
        min-width: 140px;
    }
    .ref-badge-coins {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
        font-size: 11px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }
    .ref-badge-status {
        font-size: 10.5px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 6px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        white-space: nowrap;
    }
    .ref-badge-status-granted {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }
    .ref-badge-status-pending {
        background: #fffbeb;
        color: #b45309;
        border: 1px solid #fde68a;
    }
    .ref-badge-status-failed {
        background: #fff1f2;
        color: #be123c;
        border: 1px solid #fecdd3;
    }
    .ref-code-pill {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 10.5px;
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #cbd5e1;
        padding: 1px 6px;
        border-radius: 4px;
        font-weight: 600;
    }
    .ref-date-text {
        font-size: 11px;
        color: #94a3b8;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }
</style>

<form id="referralReportFilters" method="GET" action="{{ route('admin.referral-report.index') }}"></form>

<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Referral Report</h2>
            <p class="text-xs t3 m-0 mt-0.5">See which peer referred how many users and referral coins granted.</p>
        </div>
        <div class="flex gap-2 items-center">
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Total Referrers: {{ number_format($records->total()) }}</span>
            <a href="{{ route('admin.referral-report.export', request()->query()) }}" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold transition focus-ring no-underline">
                Export CSV
            </a>
        </div>
    </div>

    <div class="p-3 rounded-lg border bs surface-2">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                <input type="text" name="q" form="referralReportFilters" value="{{ $filters['q'] ?? '' }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Name, email, phone, code">
            </div>
            <div class="w-40">
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">From Date</label>
                <input type="date" name="from" form="referralReportFilters" value="{{ $filters['from'] ?? '' }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
            </div>
            <div class="w-40">
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">To Date</label>
                <input type="date" name="to" form="referralReportFilters" value="{{ $filters['to'] ?? '' }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" form="referralReportFilters" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition shadow-sm cursor-pointer">Filter</button>
                <a href="{{ route('admin.referral-report.index') }}" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg border bs surface t2 hover:t1 hover:surface-3 transition text-center no-underline shadow-sm">Clear</a>
            </div>
        </div>
    </div>

    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="overflow-x-auto relative">
            <table class="min-w-[1000px] w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left sticky left-0 z-10" style="min-width:200px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">Referrer Name</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left">Company</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left">City</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left">Phone Number</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left">Referral Code</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-center" style="min-width: 150px;">Referred Peers</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-center">Coins Granted</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left">Last Referral Date</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/50">
                    @forelse ($records as $record)
                        @php
                            $refAvatar = $record->referrer_profile_photo_url ?? ($record->referrer_profile_photo_file_id ? url('/api/v1/files/' . $record->referrer_profile_photo_file_id) : null);
                            $referredUsers = $referredUsersByReferrer->get((string) $record->referrer_user_id, collect());
                            $jsonData = [
                                'referrer_id' => (string) $record->referrer_user_id,
                                'referrer_name' => (string) ($record->referrer_name ?: 'Deleted / Unknown User'),
                                'referrer_company' => (string) ($record->referrer_company ?: ''),
                                'referrer_city' => (string) ($record->referrer_city ?: ''),
                                'referrer_phone' => (string) ($record->referrer_phone ?: ''),
                                'referral_code' => (string) ($record->referral_codes ?: ''),
                                'total_referred_users' => (int) $record->total_referred_users,
                                'total_coins_granted' => (int) $record->total_coins_granted,
                                'show_url' => $record->referrer_user_id ? route('admin.referral-report.show', $record->referrer_user_id) : '#',
                                'users' => $referredUsers->map(function($u) {
                                    return [
                                        'user_id' => (string) ($u->referred_user_id ?? $u->user_id ?? ''),
                                        'name' => (string) ($u->referred_name ?: 'Unknown User'),
                                        'email' => (string) ($u->referred_email ?: ''),
                                        'phone' => (string) ($u->referred_phone ?: ''),
                                        'company' => (string) ($u->company_name ?: ''),
                                        'city' => (string) ($u->city ?: ''),
                                        'referral_code' => (string) ($u->referral_code ?: ''),
                                        'coins' => (int) ($u->coins ?? 0),
                                        'reward_status' => (string) ($u->reward_status ?: 'pending'),
                                        'used_at' => !empty($u->used_at) ? \Illuminate\Support\Carbon::parse($u->used_at)->format('d M Y, h:i A') : '—',
                                    ];
                                })->values()->all(),
                            ];
                        @endphp
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-3 py-2.5 text-xs sticky left-0 z-10 surface" style="min-width:200px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.10);">
                                <div class="flex items-center gap-2.5">
                                    @if($refAvatar)
                                        <img src="{{ $refAvatar }}" alt="{{ $record->referrer_name }}" class="w-8 h-8 rounded-full object-cover border bs flex-shrink-0" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                        <div class="w-8 h-8 rounded-full text-white font-bold items-center justify-center text-xs flex-shrink-0 hidden" style="background-color: {{ $getAvatarBg($record->referrer_name ?? '') }}">
                                            {{ $getInitials($record->referrer_name ?? '') }}
                                        </div>
                                    @else
                                        <div class="w-8 h-8 rounded-full text-white font-bold flex items-center justify-center text-xs flex-shrink-0" style="background-color: {{ $getAvatarBg($record->referrer_name ?? '') }}">
                                            {{ $getInitials($record->referrer_name ?? '') }}
                                        </div>
                                    @endif
                                    <div class="font-semibold t1">
                                        @if(!empty($record->referrer_user_id))
                                            <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $record->referrer_user_id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                                {{ $record->referrer_name ?: 'Deleted / Unknown User' }}
                                            </a>
                                        @else
                                            {{ $record->referrer_name ?: 'Deleted / Unknown User' }}
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-xs t2">{{ $record->referrer_company ?: '—' }}</td>
                            <td class="px-3 py-2.5 text-xs t2">{{ $record->referrer_city ?: '—' }}</td>
                            <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $record->referrer_phone ?: '—' }}</td>
                            <td class="px-3 py-2.5 text-xs">
                                <code class="text-[11px] font-mono bg-gray-100 px-1.5 py-0.5 rounded border bs">{{ $record->referral_codes ?: '—' }}</code>
                            </td>
                            <td class="px-3 py-2.5 text-center text-xs">
                                @if((int) $record->total_referred_users > 0)
                                    <button type="button"
                                            onclick="openReferredPeersModal('{{ $record->referrer_user_id }}', event)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 shadow-xs hover:shadow transition-all cursor-pointer group"
                                            title="Click to view referred peers in popup">
                                        <i class="bi bi-people-fill text-indigo-500 group-hover:scale-110 transition-transform"></i>
                                        <span>{{ number_format((int) $record->total_referred_users) }} {{ (int) $record->total_referred_users === 1 ? 'Peer' : 'Peers' }}</span>
                                        <i class="bi bi-box-arrow-up-right text-[10px] text-indigo-400 group-hover:translate-x-0.5 transition-transform"></i>
                                    </button>
                                    <script id="ref-data-{{ $record->referrer_user_id }}" type="application/json">
                                        {!! json_encode($jsonData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
                                    </script>
                                @else
                                    <span class="chip px-2.5 py-0.5 text-xs text-gray-400 bg-gray-50 border-gray-200 font-medium">0 Peers</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center text-xs">
                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200">
                                    {{ number_format((int) $record->total_coins_granted) }} coins
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $record->last_referral_date ? \Illuminate\Support\Carbon::parse($record->last_referral_date)->format('d-m-Y h:i A') : '—' }}</td>
                            <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                @if($record->referrer_user_id)
                                    <a href="{{ route('admin.referral-report.show', $record->referrer_user_id) }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">
                                        View Details
                                    </a>
                                @else
                                    <span class="t3">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-8 text-xs t3">No referral records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
            {{ $records->links() }}
        </div>
    </div>
</div>

{{-- Referred Peers Popup Modal --}}
<div class="modal fade" id="referredPeersModal" tabindex="-1" aria-labelledby="referredPeersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-xl border-0 overflow-hidden" style="border-radius: 1rem; border: none;">
            
            {{-- Modal Header --}}
            <div class="modal-header border-0 px-4 py-3 position-relative"
                 style="background: linear-gradient(135deg, #1e1b4b 0%, #1e293b 60%, #1e1b4b 100%);">
                <div class="d-flex align-items-center gap-3 w-100">
                    <div class="d-flex align-items-center justify-content-center flex-shrink-0 text-white"
                         style="width:44px; height:44px; border-radius:50%; background:rgba(99,102,241,0.3); border:2px solid rgba(255,255,255,0.2);">
                        <i class="bi bi-people-fill fs-5 text-indigo-200"></i>
                    </div>
                    <div class="flex-grow-1 overflow-hidden pe-4">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="modal-title fw-bold text-white m-0" style="font-size: 15px;" id="referredPeersModalLabel">
                                Referred Peers
                            </h5>
                            <span id="rpmBadgeCount" class="badge" style="background:rgba(99,102,241,0.35); border:1px solid rgba(99,102,241,0.4); font-size:11px; border-radius:999px; padding:3px 10px; color:#c7d2fe;">
                                0 Peers
                            </span>
                        </div>
                        <div class="mt-1 d-flex align-items-center flex-wrap gap-2 text-slate-300" style="font-size:12px;">
                            <span>Referrer: <strong id="rpmReferrerName" class="text-white"></strong></span>
                            <span id="rpmCompanyCity" class="text-slate-400"></span>
                            <span id="rpmCodeBadge" class="ref-code-pill d-none"></span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Modal Body --}}
            <div class="modal-body p-4 position-relative" style="background:#f8fafc; max-height: 60vh; overflow-y: auto;">
                
                {{-- Quick Filter Inside Modal --}}
                <div class="mb-3">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 13px;"></i>
                        <input type="text" id="rpmSearchInput" oninput="filterReferredPeersList(this.value)" class="form-control ps-5 bg-white border" placeholder="Search referred peer by name, company, city, phone, code..." style="border-radius: 8px; font-size: 12.5px; height: 38px; border-color: #cbd5e1;">
                    </div>
                </div>

                {{-- List Container --}}
                <div id="rpmListContainer" style="display: flex; flex-direction: column; gap: 10px;">
                    {{-- Dynamically populated via JavaScript --}}
                </div>

                {{-- Empty State --}}
                <div id="rpmEmptyState" class="d-none text-center py-5 bg-white rounded-3 border" style="border-color: #e2e8f0 !important;">
                    <i class="bi bi-people text-muted mb-2 d-block" style="font-size: 2rem;"></i>
                    <p class="text-muted small m-0">No referred peers found matching your search.</p>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="modal-footer border-top bg-white px-4 py-3 d-flex justify-content-between align-items-center" style="border-color:#e2e8f0 !important;">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small fw-medium">Total Coins Granted:</span>
                    <span id="rpmTotalCoins" class="ref-badge-coins" style="font-size: 12px; padding: 4px 10px;">0 coins</span>
                </div>
                <div class="d-flex gap-2">
                    <a id="rpmViewFullLink" href="#" class="btn btn-outline-primary btn-sm fw-semibold px-3 d-inline-flex align-items-center gap-1.5" style="border-radius:8px; font-size:12px;">
                        <span>View Full Details</span>
                        <i class="bi bi-arrow-right" style="font-size: 11px;"></i>
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm fw-semibold px-4" data-bs-dismiss="modal" style="border-radius:8px; font-size:12px;">Close</button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    let currentReferralData = null;

    function getAvatarColor(name) {
        const colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
        let hash = 0;
        for (let i = 0; i < (name || '').length; i++) {
            hash = name.charCodeAt(i) + ((hash << 5) - hash);
        }
        return colors[Math.abs(hash) % colors.length];
    }

    function getInitials(name) {
        if (!name) return 'P';
        const parts = name.trim().split(/\s+/);
        let inits = '';
        for (let p of parts) {
            if (p) inits += p[0].toUpperCase();
            if (inits.length >= 2) break;
        }
        return inits || 'P';
    }

    window.openReferredPeersModal = function(referrerId, event) {
        if (event) {
            if (event.preventDefault) event.preventDefault();
            if (event.stopPropagation) event.stopPropagation();
        }

        const dataScript = document.getElementById('ref-data-' + referrerId);
        if (!dataScript) {
            console.error('Referral data script not found for ID:', referrerId);
            return;
        }

        try {
            currentReferralData = JSON.parse(dataScript.textContent);
        } catch(e) {
            console.error('Error parsing referral data:', e);
            return;
        }

        const count = currentReferralData.total_referred_users || 0;
        document.getElementById('rpmReferrerName').textContent = currentReferralData.referrer_name || 'Referrer';
        document.getElementById('rpmBadgeCount').textContent = count + ' ' + (count === 1 ? 'Peer' : 'Peers');
        
        let metaParts = [];
        if (currentReferralData.referrer_company) metaParts.push(currentReferralData.referrer_company);
        if (currentReferralData.referrer_city) metaParts.push(currentReferralData.referrer_city);
        document.getElementById('rpmCompanyCity').textContent = metaParts.length > 0 ? '• ' + metaParts.join(', ') : '';

        const codeBadge = document.getElementById('rpmCodeBadge');
        if (currentReferralData.referral_code) {
            codeBadge.textContent = 'Code: ' + currentReferralData.referral_code;
            codeBadge.classList.remove('d-none');
        } else {
            codeBadge.classList.add('d-none');
        }

        document.getElementById('rpmTotalCoins').innerHTML = '<i class="bi bi-coin" style="color:#d97706;"></i> ' + Number(currentReferralData.total_coins_granted || 0).toLocaleString() + ' coins';
        
        const fullLink = document.getElementById('rpmViewFullLink');
        fullLink.href = currentReferralData.show_url || '#';

        // Clear search input
        const searchInput = document.getElementById('rpmSearchInput');
        if (searchInput) searchInput.value = '';

        // Render Users List
        renderReferredUsers(currentReferralData.users || []);

        // Open Modal
        const modalEl = document.getElementById('referredPeersModal');
        const modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modalInstance.show();
    };

    function renderReferredUsers(users) {
        const container = document.getElementById('rpmListContainer');
        const emptyState = document.getElementById('rpmEmptyState');
        container.innerHTML = '';

        if (!users || users.length === 0) {
            emptyState.classList.remove('d-none');
            return;
        }

        emptyState.classList.add('d-none');

        users.forEach(u => {
            const card = document.createElement('div');
            card.className = 'ref-peer-card';

            const avatarBg = getAvatarColor(u.name);
            const initials = getInitials(u.name);

            const st = (u.reward_status || 'pending').toLowerCase();
            let statusClass = 'ref-badge-status-pending';
            if (st === 'granted') {
                statusClass = 'ref-badge-status-granted';
            } else if (st === 'failed') {
                statusClass = 'ref-badge-status-failed';
            }

            const nameHtml = u.user_id 
                ? `<a href="#" onclick="event.preventDefault(); openActivityPeerModal('${u.user_id}', event);" class="ref-peer-name">${escapeHtml(u.name || 'Unknown User')}</a>`
                : `<span class="ref-peer-name" style="color:#1e293b;">${escapeHtml(u.name || 'Unknown User')}</span>`;

            let metaHtml = '';
            if (u.company || u.city) {
                metaHtml = `
                    <div class="ref-meta-row">
                        ${u.company ? `<span class="ref-meta-item"><i class="bi bi-building text-slate-400"></i> ${escapeHtml(u.company)}</span>` : ''}
                        ${u.city ? `<span class="ref-meta-item"><i class="bi bi-geo-alt text-slate-400"></i> ${escapeHtml(u.city)}</span>` : ''}
                    </div>
                `;
            }

            let contactHtml = '';
            if (u.phone || u.email) {
                contactHtml = `
                    <div class="ref-contact-row">
                        ${u.phone ? `<span class="ref-meta-item"><i class="bi bi-telephone text-slate-400"></i> ${escapeHtml(u.phone)}</span>` : ''}
                        ${u.email ? `<span class="ref-meta-item"><i class="bi bi-envelope text-slate-400"></i> ${escapeHtml(u.email)}</span>` : ''}
                    </div>
                `;
            }

            card.innerHTML = `
                <div style="display: flex; align-items: flex-start; gap: 14px; flex: 1; min-width: 0;">
                    <div class="ref-avatar" style="background-color: ${avatarBg};">
                        ${initials}
                    </div>
                    <div class="ref-peer-info">
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            ${nameHtml}
                            ${u.referral_code ? `<span class="ref-code-pill">${escapeHtml(u.referral_code)}</span>` : ''}
                        </div>
                        ${metaHtml}
                        ${contactHtml}
                    </div>
                </div>
                <div class="ref-right-col">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span class="ref-badge-coins">
                            <i class="bi bi-coin" style="color: #d97706;"></i> ${Number(u.coins || 0).toLocaleString()} coins
                        </span>
                        <span class="ref-badge-status ${statusClass}">
                            ${escapeHtml(u.reward_status || 'PENDING')}
                        </span>
                    </div>
                    ${u.used_at && u.used_at !== '—' ? `<div class="ref-date-text"><i class="bi bi-calendar3"></i> ${escapeHtml(u.used_at)}</div>` : ''}
                </div>
            `;

            container.appendChild(card);
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    window.filterReferredPeersList = function(query) {
        if (!currentReferralData || !currentReferralData.users) return;
        const q = (query || '').toLowerCase().trim();
        if (!q) {
            renderReferredUsers(currentReferralData.users);
            return;
        }

        const filtered = currentReferralData.users.filter(u => {
            return (u.name && u.name.toLowerCase().includes(q)) ||
                   (u.company && u.company.toLowerCase().includes(q)) ||
                   (u.city && u.city.toLowerCase().includes(q)) ||
                   (u.phone && u.phone.toLowerCase().includes(q)) ||
                   (u.email && u.email.toLowerCase().includes(q)) ||
                   (u.referral_code && u.referral_code.toLowerCase().includes(q));
        });

        renderReferredUsers(filtered);
    };
</script>
@endsection
