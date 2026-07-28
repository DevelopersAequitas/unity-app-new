@extends('admin.layouts.app')

@section('title', 'Recommended Peers')

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
        <!-- Header Component -->
        @include('admin.activities.partials.header', ['title' => 'Recommended Peers'])

        <!-- Metrics Cards -->
        <div class="activities-stats-grid">
            <div class="activity-metric-card">
                <div class="metric-icon bg-primary-subtle text-primary">
                    <i class="bi bi-hand-thumbs-up-fill"></i>
                </div>
                <div>
                    <div class="metric-val">{{ number_format($items->total()) }}</div>
                    <div class="metric-label">Total Recommendations</div>
                </div>
            </div>

            <div class="activity-metric-card">
                <div class="metric-icon bg-success-subtle text-success">
                    <i class="bi bi-person-fill-check"></i>
                </div>
                <div>
                    <div class="metric-val">
                        {{ number_format($items->filter(fn($item) => $item->is_aware)->count()) }}
                    </div>
                    <div class="metric-label">Peers Aware (Page)</div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <form id="adminactivitiesrecommend-peerindexFiltersForm" method="GET" action="{{ route('admin.activities.recommend-peer.index') }}" class="space-y-4">
            @include('admin.components.activity-filter-bar-v2', [
                'actionUrl' => route('admin.activities.recommend-peer.index'),
                'resetUrl' => route('admin.activities.recommend-peer.index'),
                'filters' => $filters,
                'circles' => $circles ?? collect(),
                'showExport' => false,
                'renderFormTag' => false,
                'formId' => 'adminactivitiesrecommend-peerindexFiltersForm',
            ])

            <!-- Table Card -->
            <div class="rounded-xl border bs surface overflow-hidden">
                <div class="overflow-x-auto relative">
                    <table class="min-w-full border-collapse text-[13px]">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Submitted At</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Recommender Details</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Recommender Phone</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Recommended Peer Name</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Recommended Peer Mobile</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">How Well Known</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Is Aware</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Coins Awarded</th>
                            </tr>
                            <tr class="surface-2 border-b bs filter-row">
                                <th class="px-2 py-1 text-center t3">—</th>
                                <th class="px-2 py-1">
                                    <input type="text" name="peer_name" value="{{ $filters['peer_name'] ?? '' }}" placeholder="Peer Name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                </th>
                                <th class="px-2 py-1"><input type="text" name="peer_phone" value="{{ $filters['peer_phone'] ?? '' }}" placeholder="Phone" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1"><input type="text" name="recommended_peer_name" value="{{ $filters['recommended_peer_name'] ?? '' }}" placeholder="Rec Peer Name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1"><input type="text" name="recommended_peer_mobile" value="{{ $filters['recommended_peer_mobile'] ?? '' }}" placeholder="Mobile" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1"><input type="text" name="how_well_known" value="{{ $filters['how_well_known'] ?? '' }}" placeholder="Known" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1">
                                    <select name="is_aware" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                        <option value="">Any</option>
                                        <option value="yes" @selected(($filters['is_aware'] ?? '')==='yes')>Yes</option>
                                        <option value="no" @selected(($filters['is_aware'] ?? '')==='no')>No</option>
                                    </select>
                                </th>
                                <th class="px-2 py-1">
                                    <div class="flex justify-end">
                                        <button type="button" onclick="clearAdminFilters(event, 'adminactivitiesrecommend-peerindexFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">Clear</button>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="grid-body" class="divide-y divide-gray-200/50">
                            @forelse ($items as $item)
                                @php
                                    $peerName = $item->from_user_name ?? '—';
                                @endphp
                                <tr class="hover:surface-2 transition border-b bs">
                                    <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $formatDateTime($item->created_at ?? null) }}</td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($peerName) }}">
                                                {{ $getInitials($peerName) }}
                                            </div>
                                            <div>
                                                <div class="font-semibold t1 text-[12.5px]">{{ $peerName }}</div>
                                                <div class="t3 text-[10px]">
                                                    @if($item->from_company) <span>{{ $item->from_company }}</span> @endif
                                                    @if($item->from_city) &bull; <span>{{ $item->from_city }}</span> @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-xs t2">{{ $item->from_phone ?? '—' }}</td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($item->peer_name ?? '') }}">
                                                {{ $getInitials($item->peer_name ?? '') }}
                                            </div>
                                            <div>
                                                <div class="font-semibold t1 text-[12.5px]">{{ $item->peer_name ?? '—' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-xs t1 font-medium">{{ $item->peer_mobile ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-xs t2">{{ $item->how_well_known ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-xs">
                                        @if($item->is_aware)
                                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Yes</span>
                                        @else
                                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200">No</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-xs">
                                        @if($item->coins_awarded)
                                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">Awarded</span>
                                        @else
                                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-8 text-xs t3">No entries found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                    {{ $items->links() }}
                </div>
            </div>
        </form>
    </div>
@endsection

