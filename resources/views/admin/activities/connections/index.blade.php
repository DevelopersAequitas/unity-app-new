@extends('admin.layouts.app')

@section('title', 'Connections')

@include('admin.partials.grid-head')

@section('content')
    @php
        $displayName = function (?string $display, ?string $first, ?string $last): string {
            if ($display) {
                return $display;
            }
            $name = trim(($first ?? '') . ' ' . ($last ?? ''));
            return $name !== '' ? $name : '—';
        };

        $formatDateTime = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : '—';
        };
    @endphp

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Connections Activity</h2>
                <p class="text-xs t1 font-medium m-0 mt-0.5">Total Connections: {{ number_format($total) }}</p>
            </div>
        </div>

        <form id="connectionsFiltersForm" method="GET" action="{{ route('admin.activities.connections.index') }}" class="space-y-4">
            @include('admin.components.activity-filter-bar-v2', [
                'actionUrl' => route('admin.activities.connections.index'),
                'resetUrl' => route('admin.activities.connections.index'),
                'filters' => $filters,
                'circles' => $circles ?? collect(),
                'showExport' => true,
                'exportUrl' => route('admin.activities.connections.export', request()->except(['content'])),
                'renderFormTag' => false,
                'formId' => 'connectionsFiltersForm',
            ])

            <div class="space-y-4">
                <!-- Top 5 Connected Peers Grid -->
                <div class="rounded-xl border bs surface overflow-hidden">
                    <div class="px-4 py-3 surface-2 border-b bs flex justify-between items-center">
                        <span class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider">Top 5 Connected Peers</span>
                    </div>
                    <div class="overflow-x-auto relative">
                        <table class="min-w-full border-collapse text-[13px]">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left w-16">Rank</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer Name</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Total Connections Initiated</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200/50">
                                @forelse ($topMembers as $index => $member)
                                    <tr class="hover:surface-2 transition border-b bs">
                                        <td class="px-3 py-2.5 text-xs font-semibold t3">#{{ $index + 1 }}</td>
                                        <td class="px-3 py-2.5">
                                            @include('admin.components.peer-card', [
                                                'name' => $member->peer_name ?? $displayName($member->display_name ?? null, $member->first_name ?? null, $member->last_name ?? null),
                                                'company' => $member->peer_company ?? '',
                                                'city' => $member->peer_city ?? '',
                                                'maxWidth' => 260,
                                            ])
                                        </td>
                                        <td class="px-3 py-2.5 text-xs font-bold text-indigo-600 text-right">{{ $member->total_count ?? 0 }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-xs t3">No data available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- All Connections Log Grid -->
                <div class="rounded-xl border bs surface overflow-hidden">
                    <div class="overflow-x-auto relative">
                        <table class="min-w-full border-collapse text-[13px]">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">From (Requester)</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">To (Addressee)</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Requested At</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Approved At</th>
                                </tr>
                                <tr class="surface-2 border-b bs filter-row">
                                    <th class="px-2 py-1">
                                        <input type="text" name="from_peer" value="{{ $tableFilters['from_peer'] ?? '' }}" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="From">
                                    </th>
                                    <th class="px-2 py-1">
                                        <input type="text" name="to_peer" value="{{ $tableFilters['to_peer'] ?? '' }}" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="To">
                                    </th>
                                    <th class="px-2 py-1">
                                        <select name="status" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                            <option value="" @selected(($tableFilters['status'] ?? '') === '')>Any</option>
                                            <option value="approved" @selected(($tableFilters['status'] ?? '') === 'approved')>Approved</option>
                                            <option value="pending" @selected(($tableFilters['status'] ?? '') === 'pending')>Pending</option>
                                        </select>
                                    </th>
                                    <th class="px-2 py-1"></th>
                                    <th class="px-2 py-1">
                                        <div class="flex justify-end">
                                            <button type="button" onclick="clearAdminFilters(event, 'connectionsFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">Clear</button>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="grid-body" class="divide-y divide-gray-200/50">
                                @forelse ($items as $item)
                                    @php
                                        $actorName = $displayName($item->actor_display_name ?? null, $item->actor_first_name ?? null, $item->actor_last_name ?? null);
                                        $peerName = $displayName($item->peer_display_name ?? null, $item->peer_first_name ?? null, $item->peer_last_name ?? null);
                                    @endphp
                                    <tr class="hover:surface-2 transition border-b bs">
                                        <td class="px-3 py-2.5">
                                            @include('admin.components.peer-card', [
                                                'name' => $item->from_user_name ?? $actorName,
                                                'company' => $item->from_company ?? '',
                                                'city' => $item->from_city ?? '',
                                                'userId' => $item->actor_id ?? $item->from_user_id ?? null,
                                            ])
                                        </td>
                                        <td class="px-3 py-2.5">
                                            @include('admin.components.peer-card', [
                                                'name' => $item->to_user_name ?? $peerName,
                                                'company' => $item->to_company ?? '',
                                                'city' => $item->to_city ?? '',
                                                'userId' => $item->user_id ?? $item->to_user_id ?? null,
                                            ])
                                        </td>
                                        <td class="px-3 py-2.5 text-xs">
                                            @if ($item->is_approved)
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Approved</span>
                                            @else
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200">Pending</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $formatDateTime($item->created_at ?? null) }}</td>
                                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $formatDateTime($item->approved_at ?? null) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-8 text-xs t3">No connections found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                        {{ $items->links() }}
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
