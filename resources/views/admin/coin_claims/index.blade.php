@extends('admin.layouts.app')

@php
    use App\Support\CoinClaims\CoinClaimKeyFieldsFormatter;
@endphp

@section('title', 'Coin Claims')

@include('admin.partials.grid-head')

@section('content')
    @php
        $displayName = function ($user): string {
            if (! $user) {
                return '—';
            }

            if (! empty($user->name)) {
                return (string) $user->name;
            }

            if (! empty($user->display_name)) {
                return (string) $user->display_name;
            }

            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            return $name !== '' ? $name : '—';
        };

        $getInitials = function (?string $name): string {
            if (! $name) return 'P';
            $words = explode(' ', trim($name));
            $initials = '';
            foreach ($words as $w) {
                if (! empty($w)) $initials .= strtoupper(substr($w, 0, 1));
            }
            return substr($initials, 0, 2) ?: 'P';
        };

        $getAvatarBg = function (?string $name): string {
            if (! $name) return '#6366f1';
            $colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
            $hash = crc32($name);
            return $colors[abs($hash) % count($colors)];
        };
    @endphp

    <form id="coinClaimsFiltersForm" method="GET" action="{{ route('admin.coin-claims.index') }}"></form>

    @if (session('success')) <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">{{ session('error') }}</div> @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Coin Claims</h2>
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Total: {{ number_format($claims->total()) }}</span>
        </div>

        <!-- Filter Card -->
        <div class="p-3 rounded-lg border bs surface-2">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-2.5 items-end">
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                    <input type="text" name="q" form="coinClaimsFiltersForm" value="{{ $filters['q'] }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Search peer/activity/key fields">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                    <select name="status" form="coinClaimsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="all" @selected($filters['status'] === 'all')>All</option>
                        <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                        <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                        <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Circle</label>
                    <select name="circle_id" form="coinClaimsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="all">All Circles</option>
                        @foreach($circles as $circle)
                            <option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? 'all') == $circle->id)>{{ $circle->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end">
                    <button type="button" onclick="clearAdminFilters(event, 'coinClaimsFiltersForm')" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center w-full">Clear</button>
                </div>
            </div>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full w-full border-collapse text-[13px] align-middle">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs whitespace-nowrap">
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left sticky left-0 z-10 whitespace-nowrap" style="min-width:170px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">Peer Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Company</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">City</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Circle</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Peer Phone</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Activity</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Key Fields</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-center whitespace-nowrap">Actions</th>
                        </tr>
                        <tr class="surface-2 border-b bs filter-row whitespace-nowrap">
                            <th class="px-2 py-1 sticky left-0 z-10 surface-2 whitespace-nowrap" style="box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">
                                <input type="text" name="peer_q" form="coinClaimsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Peer Search" value="{{ $filters['peer_q'] }}">
                            </th>
                            <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                            <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                            <th class="px-2 py-1"><input type="text" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                            <th class="px-2 py-1">
                                <input type="text" name="peer_phone" form="coinClaimsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Peer Phone" value="{{ $filters['peer_phone'] }}">
                            </th>
                            <th class="px-2 py-1">
                                <input type="text" name="activity" form="coinClaimsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Activity" value="{{ $filters['activity'] }}">
                            </th>
                            <th class="px-2 py-1">
                                <input type="text" name="key_fields" form="coinClaimsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Search key fields" value="{{ $filters['key_fields'] }}">
                            </th>
                            <th class="px-2 py-1">
                                <select name="status" form="coinClaimsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    <option value="all" @selected($filters['status'] === 'all')>All</option>
                                    <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                                    <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                                    <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                                </select>
                            </th>
                            <th class="px-2 py-1 text-center">
                                <div class="flex justify-center">
                                    <button type="button" onclick="clearAdminFilters(event, 'coinClaimsFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition whitespace-nowrap">Clear</button>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($claims as $claim)
                            @php
                                $user = $claim->user;
                                $userName = $user ? ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))) : '—';
                                $company = $user->company_name ?? $user->company ?? $user->business_name ?? '—';
                                $city = $user->city ?? '—';
                                $userCircles = $user
                                    ? $user->circleMembers->map(fn($cm) => optional($cm->circle)->name)->filter()->unique()->implode(', ')
                                    : '';
                                $circleName = $userCircles !== '' ? $userCircles : '—';
                                $keyFieldsRows = CoinClaimKeyFieldsFormatter::formatForAdminList(data_get($claim->payload, 'fields', data_get($claim->payload, 'key_fields')));
                                $formattedKeyFieldsText = collect($keyFieldsRows)->map(fn($r) => $r['label'] . ': ' . $r['value'])->implode(' | ');
                                $activityLabel = (string) data_get($registry->get($claim->activity_code), 'label', $claim->activity_code);

                                $claimRowData = [
                                    'id' => $claim->id,
                                    'peerName' => $userName,
                                    'peerCompany' => $company,
                                    'peerCity' => $city,
                                    'peerCircle' => $circleName,
                                    'peerPhone' => $user->phone ?? '—',
                                    'activity' => $activityLabel,
                                    'keyFields' => $formattedKeyFieldsText !== '' ? $formattedKeyFieldsText : '—',
                                    'keyFieldsList' => $keyFieldsRows,
                                    'status' => $claim->status,
                                    'statusLabel' => ucfirst((string) $claim->status),
                                    'rejectionReason' => (string) ($claim->admin_notes ?? ''),
                                    'showUrl' => route('admin.coin-claims.show', $claim->id),
                                    'approveUrl' => route('admin.coin-claims.approve', $claim->id),
                                    'rejectUrl' => route('admin.coin-claims.reject', $claim->id),
                                    'isPending' => $claim->status === 'pending',
                                ];
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs whitespace-nowrap cursor-pointer" onclick="openCoinClaimRowModal({{ json_encode($claimRowData) }})" title="Click row to view full claim details">
                                <td class="px-3 py-2.5 text-xs sticky left-0 z-10 surface whitespace-nowrap" style="min-width:170px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.10);">
                                    @if ($user)
                                        <div class="flex items-center gap-2 whitespace-nowrap">
                                            <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($userName) }}">
                                                {{ $getInitials($userName) }}
                                            </div>
                                            <span class="text-indigo-600 font-semibold no-underline whitespace-nowrap">
                                                {{ $userName }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="t3 whitespace-nowrap">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap"><x-admin-grid-text :text="$company" /></td>
                                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap"><x-admin-grid-text :text="$city" /></td>
                                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap"><x-admin-grid-text :text="$circleName" /></td>
                                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap font-mono">{{ $user->phone ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-xs font-medium t1 whitespace-nowrap"><x-admin-grid-text :text="$activityLabel" /></td>
                                <td class="px-3 py-2.5 text-xs whitespace-nowrap" style="max-width: 280px;">
                                    @if ($keyFieldsRows !== [])
                                        <div class="space-y-0.5 text-[11px] admin-grid-text-clamp whitespace-nowrap" data-full-text="{{ $formattedKeyFieldsText }}">
                                            @foreach ($keyFieldsRows as $row)
                                                <div class="whitespace-nowrap"><span class="font-semibold t1">{{ $row['label'] }}:</span> <span class="t2">{{ $row['value'] }}</span></div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="t3 whitespace-nowrap">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    @if($claim->status === 'approved')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Approved
                                        </span>
                                    @elseif($claim->status === 'rejected')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-rose-50 text-rose-700 border border-rose-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Rejected
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-amber-50 text-amber-700 border border-amber-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Pending
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs text-center whitespace-nowrap" onclick="event.stopPropagation()">
                                    <div class="flex justify-center gap-1.5 items-center whitespace-nowrap">
                                        <a href="{{ route('admin.coin-claims.show', $claim->id) }}" class="px-2.5 py-1 text-xs font-semibold rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition no-underline whitespace-nowrap">Details</a>
                                        @if ($claim->status === 'pending')
                                            @if (app(\App\Services\Admin\PermissionService::class)->can(Auth::guard('admin')->user(), 'admin.coin-claims.index', 'approve'))
                                                <form method="POST" action="{{ route('admin.coin-claims.approve', $claim->id) }}" class="inline">@csrf
                                                    <button class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring" onclick="return confirm('Approve this claim?')">Approve</button>
                                                </form>
                                            @endif
                                            @if (app(\App\Services\Admin\PermissionService::class)->can(Auth::guard('admin')->user(), 'admin.coin-claims.index', 'reject') || app(\App\Services\Admin\PermissionService::class)->can(Auth::guard('admin')->user(), 'admin.coin-claims.index', 'approve'))
                                                <form method="POST" action="{{ route('admin.coin-claims.reject', $claim->id) }}" class="inline">@csrf
                                                    <input type="hidden" name="admin_notes" value="Rejected by admin">
                                                    <button class="px-2 py-0.5 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring" onclick="return confirm('Reject this claim?')">Reject</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('admin.coin-claims.approve', $claim->id) }}" class="inline">@csrf
                                                <button class="px-2.5 py-1 text-xs font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition whitespace-nowrap cursor-pointer" onclick="return confirm('Approve this claim?')">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.coin-claims.reject', $claim->id) }}" class="inline">@csrf
                                                <input type="hidden" name="admin_notes" value="Rejected by admin">
                                                <button class="px-2.5 py-1 text-xs font-semibold rounded-md bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 transition whitespace-nowrap cursor-pointer" onclick="return confirm('Reject this claim?')">Reject</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center py-8 text-xs t3 whitespace-nowrap">No coin claims found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $claims->links() }}
            </div>
        </div>
    </div>

    <!-- Coin Claim Details Popup Modal -->
    <div id="coinClaimRowDetailModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full p-6 relative border border-gray-200 space-y-4 max-h-[90vh] overflow-y-auto">
            <button type="button" onclick="closeCoinClaimRowModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-xl font-bold w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 transition cursor-pointer">&times;</button>
            
            <div class="border-b bs pb-3">
                <h3 id="modalClaimPeerName" class="font-bold text-base text-gray-900 m-0">Coin Claim</h3>
                <p id="modalClaimActivity" class="text-xs text-indigo-600 font-semibold m-0 mt-0.5">Activity Details</p>
            </div>

            <!-- Peer Details Card -->
            <div class="p-3.5 rounded-xl border bs bg-gray-50/70 space-y-1.5">
                <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500">Peer Information</span>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <span class="text-gray-500 text-[11px]">Company:</span>
                        <div id="modalClaimCompany" class="font-semibold text-gray-900">—</div>
                    </div>
                    <div>
                        <span class="text-gray-500 text-[11px]">City:</span>
                        <div id="modalClaimCity" class="font-semibold text-gray-900">—</div>
                    </div>
                    <div>
                        <span class="text-gray-500 text-[11px]">Circle:</span>
                        <div id="modalClaimCircle" class="font-semibold text-gray-900">—</div>
                    </div>
                    <div>
                        <span class="text-gray-500 text-[11px]">Phone:</span>
                        <div id="modalClaimPhone" class="font-semibold text-gray-900 font-mono">—</div>
                    </div>
                </div>
            </div>

            <!-- Status & Activity Info -->
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div class="p-3 rounded-lg border bs bg-gray-50/70">
                    <span class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Status</span>
                    <span id="modalClaimStatus" class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                </div>
                <div class="p-3 rounded-lg border bs bg-gray-50/70">
                    <span class="block text-[10px] uppercase tracking-wider font-semibold text-gray-500 mb-1">Activity Code</span>
                    <span id="modalClaimActivityCode" class="font-semibold text-indigo-600">—</span>
                </div>
            </div>

            <!-- Key Fields Breakdown -->
            <div class="p-3.5 rounded-xl border bs bg-gray-50/70 space-y-2 text-xs">
                <span class="block text-[11px] uppercase tracking-wider font-semibold text-gray-500">Key Fields & Submission Details</span>
                <div id="modalClaimKeyFieldsList" class="space-y-1.5">
                    <!-- Key fields injected dynamically -->
                </div>
            </div>

            <div id="modalClaimNotesContainer" class="p-3 rounded-lg border bs bg-rose-50/50 border-rose-200 text-xs hidden space-y-1">
                <span class="block text-[10px] uppercase tracking-wider font-semibold text-rose-700">Admin Notes / Rejection Reason</span>
                <p id="modalClaimNotes" class="text-rose-900 font-medium m-0">—</p>
            </div>

            <!-- Action Bar -->
            <div class="pt-3 border-t bs flex justify-between items-center gap-2 flex-wrap">
                <div class="flex items-center gap-2 flex-wrap">
                    <a id="modalClaimDetailsBtn" href="#" class="px-4 py-2 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition no-underline shadow-sm flex items-center gap-1.5">
                        Open Full Details
                    </a>

                    <!-- Dynamic Approve Form -->
                    <form id="modalClaimApproveForm" method="POST" action="" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-sm cursor-pointer flex items-center gap-1.5" onclick="return confirm('Approve this claim?')">
                            Approve
                        </button>
                    </form>

                    <!-- Dynamic Reject Form -->
                    <form id="modalClaimRejectForm" method="POST" action="" class="inline" onsubmit="const r = prompt('Enter rejection reason:'); if (!r || !r.trim()) { return false; } this.querySelector('input[name=admin_notes]').value = r.trim(); return true;">
                        @csrf
                        <input type="hidden" name="admin_notes" id="modalClaimRejectNotesInput" value="Rejected by admin">
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg border border-rose-300 bg-white text-rose-600 hover:bg-rose-50 transition shadow-sm cursor-pointer flex items-center gap-1.5">
                            Reject
                        </button>
                    </form>
                </div>

                <button type="button" onclick="closeCoinClaimRowModal()" class="px-4 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold transition cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        function openCoinClaimRowModal(data) {
            document.getElementById('modalClaimPeerName').textContent = data.peerName || 'Coin Claim';
            document.getElementById('modalClaimActivity').textContent = data.activity || 'Activity Details';
            document.getElementById('modalClaimCompany').textContent = data.peerCompany || '—';
            document.getElementById('modalClaimCity').textContent = data.peerCity || '—';
            document.getElementById('modalClaimCircle').textContent = data.peerCircle || '—';
            document.getElementById('modalClaimPhone').textContent = data.peerPhone || '—';
            document.getElementById('modalClaimActivityCode').textContent = data.activity || '—';

            // Key fields list
            const listContainer = document.getElementById('modalClaimKeyFieldsList');
            listContainer.innerHTML = '';
            if (data.keyFieldsList && data.keyFieldsList.length > 0) {
                data.keyFieldsList.forEach(function(row) {
                    const rowDiv = document.createElement('div');
                    rowDiv.className = 'flex justify-between items-start border-b border-gray-200/50 pb-1';
                    rowDiv.innerHTML = '<span class="font-semibold text-gray-700 text-[11px]">' + (row.label || '') + ':</span> <span class="text-gray-900 text-right">' + (row.value || '—') + '</span>';
                    listContainer.appendChild(rowDiv);
                });
            } else {
                listContainer.innerHTML = '<span class="text-gray-400">' + (data.keyFields || '—') + '</span>';
            }

            // Admin Notes
            const notesContainer = document.getElementById('modalClaimNotesContainer');
            const notesEl = document.getElementById('modalClaimNotes');
            if (data.rejectionReason && data.rejectionReason.trim()) {
                notesEl.textContent = data.rejectionReason;
                notesContainer.classList.remove('hidden');
            } else {
                notesContainer.classList.add('hidden');
            }

            // Status Badge
            const statusEl = document.getElementById('modalClaimStatus');
            statusEl.textContent = data.statusLabel || 'Pending';
            const st = (data.status || '').toLowerCase();
            if (st === 'approved') {
                statusEl.className = 'inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200';
            } else if (st === 'rejected') {
                statusEl.className = 'inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-rose-50 text-rose-700 border border-rose-200';
            } else {
                statusEl.className = 'inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-md bg-amber-50 text-amber-700 border border-amber-200';
            }

            // Full Details Link
            document.getElementById('modalClaimDetailsBtn').href = data.showUrl || '#';

            // Forms
            document.getElementById('modalClaimApproveForm').action = data.approveUrl;
            document.getElementById('modalClaimRejectForm').action = data.rejectUrl;

            document.getElementById('coinClaimRowDetailModal').classList.remove('hidden');
        }

        function closeCoinClaimRowModal() {
            document.getElementById('coinClaimRowDetailModal').classList.add('hidden');
        }
    </script>
@endsection

