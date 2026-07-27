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
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer Phone</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Activity</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Key Fields</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                        <tr class="surface-2 border-b bs filter-row">
                            <th class="px-2 py-1">
                                <input type="text" name="peer_q" form="coinClaimsFiltersForm" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Peer/Company/City" value="{{ $filters['peer_q'] }}">
                            </th>
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
                            <th class="px-2 py-1">
                                <div class="flex justify-end">
                                    <button type="button" onclick="clearAdminFilters(event, 'coinClaimsFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">Clear</button>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($claims as $claim)
                            @php
                                $user = $claim->user;
                                $company = $user->company_name ?? $user->company ?? $user->business_name ?? 'No Company';
                                $city = $user->city ?? 'No City';
                                $userCircles = $user
                                    ? $user->circleMembers->map(fn($cm) => optional($cm->circle)->name)->filter()->unique()->implode(', ')
                                    : '';
                                $circleName = $userCircles !== '' ? $userCircles : 'No Circle';
                                $keyFieldsRows = CoinClaimKeyFieldsFormatter::formatForAdminList(data_get($claim->payload, 'fields', data_get($claim->payload, 'key_fields')));
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs">
                                    @include('admin.partials.peer_identity', ['user' => $user, 'circleName' => $circleName])
                                </td>
                                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $user->phone ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-xs font-medium t1">{{ data_get($registry->get($claim->activity_code), 'label', $claim->activity_code) }}</td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if ($keyFieldsRows !== [])
                                        <div class="space-y-0.5 text-[11px]">
                                            @foreach ($keyFieldsRows as $row)
                                                <div><span class="font-semibold t1">{{ $row['label'] }}:</span> <span class="t2">{{ $row['value'] }}</span></div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="t3">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if($claim->status === 'approved')
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Approved</span>
                                    @elseif($claim->status === 'rejected')
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200">Rejected</span>
                                    @else
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200">Pending</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-1.5 items-center">
                                        <a href="{{ route('admin.coin-claims.show', $claim->id) }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">Details</a>
                                        @if ($claim->status === 'pending')
                                            <form method="POST" action="{{ route('admin.coin-claims.approve', $claim->id) }}" class="inline">@csrf
                                                <button class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring" onclick="return confirm('Approve this claim?')">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.coin-claims.reject', $claim->id) }}" class="inline">@csrf
                                                <input type="hidden" name="admin_notes" value="Rejected by admin">
                                                <button class="px-2 py-0.5 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring" onclick="return confirm('Reject this claim?')">Reject</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-8 text-xs t3">No coin claims found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $claims->links() }}
            </div>
        </div>
    </div>
@endsection

