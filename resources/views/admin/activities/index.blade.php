@extends('admin.layouts.app')

@section('title', 'Activities Summary')

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
        $makePeerPayload = function($p) use ($getInitials, $getAvatarBg) {
            $score = (int) ($p->performance_score ?? (
                ($p->testimonials_count ?? 0) +
                ($p->referrals_count ?? 0) +
                ($p->business_deals_count ?? 0) +
                ($p->p2p_completed_count ?? 0) +
                ($p->requirements_count ?? 0) +
                ($p->become_leader_count ?? 0) +
                ($p->recommend_peer_count ?? 0) +
                ($p->register_visitor_count ?? 0)
            ));
            return json_encode([
                'id' => $p->id,
                'name' => $p->peer_name ?: 'Peer',
                'company' => $p->company_name ?: '',
                'city' => $p->city_name ?: '',
                'circle' => $p->circle_name ?: '',
                'email' => $p->email ?: '',
                'phone' => $p->phone ?: '',
                'designation' => $p->designation ?: 'Member',
                'initials' => $getInitials($p->peer_name),
                'avatarBg' => $getAvatarBg($p->peer_name),
                'testimonials' => (int) ($p->testimonials_count ?? 0),
                'testimonialsUrl' => route('admin.activities.testimonials', $p->id),
                'referrals' => (int) ($p->referrals_count ?? 0),
                'referralsUrl' => route('admin.activities.referrals', $p->id),
                'deals' => (int) ($p->business_deals_count ?? 0),
                'dealsUrl' => route('admin.activities.business-deals', $p->id),
                'p2p' => (int) ($p->p2p_completed_count ?? 0),
                'p2pUrl' => route('admin.activities.p2p-meetings', $p->id),
                'requirements' => (int) ($p->requirements_count ?? 0),
                'requirementsUrl' => route('admin.activities.requirements', $p->id),
                'leadership' => (int) ($p->become_leader_count ?? 0),
                'leadershipUrl' => route('admin.activities.become-a-leader.show', $p->id),
                'recommendations' => (int) ($p->recommend_peer_count ?? 0),
                'recommendationsUrl' => route('admin.activities.recommend-peer.show', $p->id),
                'visitors' => (int) ($p->register_visitor_count ?? 0),
                'visitorsUrl' => route('admin.activities.register-visitor.show', $p->id),
                'score' => $score,
            ]);
        };
    @endphp

    <!-- Activities Hub Header -->
    @include('admin.activities.partials.header', ['title' => 'Summary'])

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-6 mt-4">
        <!-- Top District Peers Card -->
        <div class="rounded-xl border bs surface overflow-hidden space-y-3">
            <div class="px-4 py-3 surface-2 border-b bs flex justify-between items-center">
                <span class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider">🏆 Top 5 District Peers</span>
                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Ranked by combined performance</span>
            </div>
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="width: 80px;">Rank</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Total Activity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/50">
                        @forelse (($topDistrictPeers ?? collect()) as $rank => $peer)
                            <tr class="hover:surface-2 transition border-b bs cursor-pointer" data-peer="{{ $makePeerPayload($peer) }}" onclick="openActivityPeerModal(this, event)">
                                <td class="px-3 py-2.5">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold {{ $rank == 0 ? 'bg-amber-100 text-amber-800 border border-amber-300' : ($rank == 1 ? 'bg-slate-200 text-slate-800' : ($rank == 2 ? 'bg-amber-900 text-white' : 'bg-gray-100 text-gray-700 border border-gray-200')) }}">
                                        {{ $rank + 1 }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center" style="background-color: {{ $getAvatarBg($peer->peer_name) }}">
                                            {{ $getInitials($peer->peer_name) }}
                                        </div>
                                        <span class="font-semibold text-xs t1">{{ $peer->peer_name }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $peer->company_name ?: '-' }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $peer->city_name ?: '-' }}</td>
                                <td class="px-3 py-2.5 text-right font-bold text-indigo-600 text-xs">{{ number_format((int) ($peer->performance_score ?? 0)) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-8 text-xs t3">No district peer performance found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Main List Card -->
        <div class="rounded-xl border bs surface overflow-hidden">
            <form id="activitiesFiltersForm" method="GET" action="{{ route('admin.activities.index') }}"></form>

            <div class="flex flex-wrap justify-between items-center p-3.5 gap-3 border-b bs surface-2">
                <div class="flex items-center gap-2">
                    <label for="perPage" class="text-xs t3 font-medium">Rows per page:</label>
                    <select id="perPage" name="per_page" form="activitiesFiltersForm" class="px-2.5 py-1 rounded-md border bs surface text-xs t1 outline-none focus-ring">
                        @foreach ([10, 20, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2 items-center flex-wrap">
                    <input
                        type="datetime-local"
                        name="from"
                        form="activitiesFiltersForm"
                        value="{{ $filters['from'] ?? '' }}"
                        class="px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring"
                        placeholder="From"
                        title="From"
                    >
                    <input
                        type="datetime-local"
                        name="to"
                        form="activitiesFiltersForm"
                        value="{{ $filters['to'] ?? '' }}"
                        class="px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring"
                        placeholder="To"
                        title="To"
                    >
                </div>
            </div>

            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="width: 40px;">
                                <input type="checkbox" class="form-check-input" id="select-all-members">
                            </th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer Details</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Testimonials</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Referrals</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Business Deals</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">P2P Meetings</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Requirements</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Leadership Requests</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Recommended Peers</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Registered Visitor</th>
                        </tr>
                        <tr class="surface-2 border-b bs align-middle">
                            <th class="px-3 py-2"></th>
                            <th class="px-3 py-2">
                                <div class="flex flex-col gap-1.5 py-1">
                                    <input
                                        type="text"
                                        name="q"
                                        form="activitiesFiltersForm"
                                        class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal"
                                        placeholder="Name, company, city"
                                        value="{{ $filters['q'] ?? '' }}"
                                    >
                                    <select name="circle_id" form="activitiesFiltersForm" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal">
                                        <option value="any">All Circles</option>
                                        @foreach ($circles as $circle)
                                            <option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? '') === $circle->id)>{{ $circle->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" disabled placeholder="-"></th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" disabled placeholder="-"></th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" disabled placeholder="-"></th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" disabled placeholder="-"></th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" disabled placeholder="-"></th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" disabled placeholder="-"></th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" disabled placeholder="-"></th>
                            <th class="px-3 py-2">
                                <div class="flex gap-1.5 justify-end items-center">
                                    <a href="{{ route('admin.activities.index') }}" class="px-3 py-1 rounded-md border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline">Clear</a>
                                    <button type="button" class="px-3 py-1 rounded-md border bs text-xs font-semibold text-indigo-600 hover:text-indigo-700 surface-2 transition" data-bs-toggle="modal" data-bs-target="#activitiesExportModal">Export</button>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($members as $member)
                            <tr class="hover:surface-2 transition border-b bs cursor-pointer" data-peer="{{ $makePeerPayload($member) }}" onclick="openActivityPeerModal(this, event)">
                                <td class="px-3 py-2.5 text-center"><input type="checkbox" class="form-check-input member-checkbox" value="{{ $member->id }}"></td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($member->peer_name) }}">
                                            {{ $getInitials($member->peer_name) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold t1 text-[12.5px]">{{ $member->peer_name }}</div>
                                            <div class="t3 text-[10px]">
                                                @if($member->company_name) <span class="font-medium t2">{{ $member->company_name }}</span> @endif
                                                @if($member->city_name) &bull; <span>{{ $member->city_name }}</span> @endif
                                                @if($member->circle_name) &bull; <span class="text-indigo-600 font-medium">{{ $member->circle_name }}</span> @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @if ($member->testimonials_count > 0)
                                        <a href="{{ route('admin.activities.testimonials', $member->id) }}" class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200 no-underline" target="_blank">
                                            💬 {{ $member->testimonials_count }}
                                        </a>
                                    @else
                                        <span class="t3 text-xs">0</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @if ($member->referrals_count > 0)
                                        <a href="{{ route('admin.activities.referrals', $member->id) }}" class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200 no-underline" target="_blank">
                                            👤 {{ $member->referrals_count }}
                                        </a>
                                    @else
                                        <span class="t3 text-xs">0</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @if ($member->business_deals_count > 0)
                                        <a href="{{ route('admin.activities.business-deals', $member->id) }}" class="chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200 no-underline" target="_blank">
                                            💼 {{ $member->business_deals_count }}
                                        </a>
                                    @else
                                        <span class="t3 text-xs">0</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @if ($member->p2p_completed_count > 0)
                                        <a href="{{ route('admin.activities.p2p-meetings', $member->id) }}" class="chip px-2.5 py-0.5 text-xs font-semibold bg-sky-50 text-sky-700 border-sky-200 no-underline" target="_blank">
                                            🤝 {{ $member->p2p_completed_count }}
                                        </a>
                                    @else
                                        <span class="t3 text-xs">0</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @if ($member->requirements_count > 0)
                                        <a href="{{ route('admin.activities.requirements', $member->id) }}" class="chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200 no-underline" target="_blank">
                                            📄 {{ $member->requirements_count }}
                                        </a>
                                    @else
                                        <span class="t3 text-xs">0</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @if ($member->become_leader_count > 0)
                                        <a href="{{ route('admin.activities.become-a-leader.show', $member->id) }}" class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-800 border-gray-300 no-underline" target="_blank">
                                            🏅 {{ $member->become_leader_count }}
                                        </a>
                                    @else
                                        <span class="t3 text-xs">0</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @if ($member->recommend_peer_count > 0)
                                        <a href="{{ route('admin.activities.recommend-peer.show', $member->id) }}" class="chip px-2.5 py-0.5 text-xs font-semibold bg-violet-50 text-violet-700 border-violet-200 no-underline" target="_blank">
                                            👍 {{ $member->recommend_peer_count }}
                                        </a>
                                    @else
                                        <span class="t3 text-xs">0</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @if ($member->register_visitor_count > 0)
                                        <a href="{{ route('admin.activities.register-visitor.show', $member->id) }}" class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200 no-underline" target="_blank">
                                            🪪 {{ $member->register_visitor_count }}
                                        </a>
                                    @else
                                        <span class="t3 text-xs">0</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center py-8 text-xs t3">No peers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $members->links() }}
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal fade" id="activitiesExportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-download me-2 text-primary"></i>Export Activities Summary</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('admin.activities.export') }}" id="activitiesExportForm">
                    @csrf
                    <input type="hidden" name="activity_type" value="summary">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Scope</label>
                            <div class="form-check p-3 bg-light rounded border mb-2">
                                <input class="form-check-input" type="radio" name="scope" id="scopeSelected" value="selected" checked>
                                <label class="form-check-label fw-medium text-dark ms-1" for="scopeSelected">Selected peers only</label>
                            </div>
                            <div class="form-check p-3 bg-light rounded border">
                                <input class="form-check-input" type="radio" name="scope" id="scopeAll" value="all">
                                <label class="form-check-label fw-medium text-dark ms-1" for="scopeAll">All peers (current filters)</label>
                            </div>
                        </div>
                        <input type="hidden" name="q" value="{{ $filters['q'] }}">
                        <input type="hidden" name="search" value="{{ $filters['q'] }}">
                        <input type="hidden" name="circle_id" value="{{ $filters['circle_id'] }}">
                        <input type="hidden" name="from" value="{{ $filters['from'] }}">
                        <input type="hidden" name="to" value="{{ $filters['to'] }}">
                        <div id="selectedMemberIdsContainer"></div>
                        <div class="text-danger small d-none" id="exportSelectionError">Please select at least one peer.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Export CSV</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('admin.activities.partials.peer-modal')

    <div class="mt-3">
        {{ $members->links() }}
    </div>
@endsection

@push('scripts')

    document.addEventListener('DOMContentLoaded', () => {
        const selectAll = document.getElementById('select-all-members');
        const checkboxes = document.querySelectorAll('.member-checkbox');
        const exportForm = document.getElementById('activitiesExportForm');
        const selectedContainer = document.getElementById('selectedMemberIdsContainer');
        const selectionError = document.getElementById('exportSelectionError');
        const scopeSelected = document.getElementById('scopeSelected');

        if (selectAll) {
            selectAll.addEventListener('change', () => {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = selectAll.checked;
                });
            });
        }

        if (exportForm) {
            exportForm.addEventListener('submit', (event) => {
                selectionError.classList.add('d-none');
                selectedContainer.innerHTML = '';
                const selectedIds = Array.from(checkboxes)
                    .filter((checkbox) => checkbox.checked)
                    .map((checkbox) => checkbox.value);

                if (scopeSelected.checked && selectedIds.length === 0) {
                    event.preventDefault();
                    selectionError.classList.remove('d-none');
                    return;
                }

                selectedIds.forEach((id) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_member_ids[]';
                    input.value = id;
                    selectedContainer.appendChild(input);
                });
            });
        }
    });
</script>
@endpush
