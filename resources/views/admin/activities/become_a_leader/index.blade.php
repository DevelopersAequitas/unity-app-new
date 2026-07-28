@extends('admin.layouts.app')

@section('title', 'Leadership Requests')

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

        $formatRoles = function ($roles): string {
            if (! $roles) {
                return '—';
            }
            $list = is_array($roles) ? $roles : (array) $roles;
            $list = array_filter($list);
            return $list ? implode(', ', $list) : '—';
        };

        $truncate = function ($value, int $limit = 80): string {
            return $value ? \Illuminate\Support\Str::limit($value, $limit) : '—';
        };
    @endphp

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <!-- Header Component -->
        @include('admin.activities.partials.header', ['title' => 'Leadership Requests'])

        <!-- Metrics Cards -->
        <div class="activities-stats-grid">
            <div class="activity-metric-card">
                <div class="metric-icon bg-primary-subtle text-primary">
                    <i class="bi bi-award-fill"></i>
                </div>
                <div>
                    <div class="metric-val">{{ number_format($items->total()) }}</div>
                    <div class="metric-label">Total Submissions</div>
                </div>
            </div>

            <div class="activity-metric-card">
                <div class="metric-icon bg-success-subtle text-success">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div>
                    <div class="metric-val">
                        {{ number_format($items->filter(fn($item) => $item->created_at >= now()->subDays(30))->count()) }}
                    </div>
                    <div class="metric-label">Recent Submissions (30 Days)</div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <form id="adminactivitiesbecome-a-leaderindexFiltersForm" method="GET" action="{{ route('admin.activities.become-a-leader.index') }}" class="space-y-4">
            @include('admin.components.activity-filter-bar-v2', [
                'actionUrl' => route('admin.activities.become-a-leader.index'),
                'resetUrl' => route('admin.activities.become-a-leader.index'),
                'filters' => $filters,
                'circles' => $circles ?? collect(),
                'showExport' => false,
                'renderFormTag' => false,
                'formId' => 'adminactivitiesbecome-a-leaderindexFiltersForm',
            ])

            <!-- Table Card -->
            <div class="rounded-xl border bs surface overflow-hidden">
                <div class="overflow-x-auto relative">
                    <table class="min-w-full border-collapse text-[13px]">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Submitted At</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer Details</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer Phone</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Applying For</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Referred Details</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Leadership Roles</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City / Region</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Primary Domain</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Why Interested</th>
                            </tr>
                            <tr class="surface-2 border-b bs filter-row">
                                <th class="px-2 py-1 text-center t3">—</th>
                                <th class="px-2 py-1">
                                    <input type="text" name="peer_name" value="{{ $filters['peer_name'] ?? '' }}" placeholder="Peer Name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                </th>
                                <th class="px-2 py-1"><input type="text" name="peer_phone" value="{{ $filters['peer_phone'] ?? '' }}" placeholder="Phone" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1"><input type="text" name="applying_for" value="{{ $filters['applying_for'] ?? '' }}" placeholder="Applying For" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1">
                                    <input type="text" name="referred_name" value="{{ $filters['referred_name'] ?? '' }}" placeholder="Referred Name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring mb-1">
                                    <input type="text" name="referred_mobile" value="{{ $filters['referred_mobile'] ?? '' }}" placeholder="Mobile" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                </th>
                                <th class="px-2 py-1"><input type="text" name="leadership_roles" value="{{ $filters['leadership_roles'] ?? '' }}" placeholder="Roles" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1">
                                    <input type="text" name="city_region" value="{{ $filters['city_region'] ?? '' }}" placeholder="City" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                </th>
                                <th class="px-2 py-1"><input type="text" name="primary_domain" value="{{ $filters['primary_domain'] ?? '' }}" placeholder="Domain" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1">
                                    <div class="flex justify-end">
                                        <button type="button" onclick="clearAdminFilters(event, 'adminactivitiesbecome-a-leaderindexFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">Clear</button>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="grid-body" class="divide-y divide-gray-200/50">
                            @forelse ($items as $item)
                                @php
                                    $peerName = $item->peer_name ?? '—';
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
                                                    @if($item->peer_company) <span>{{ $item->peer_company }}</span> @endif
                                                    @if($item->peer_city) &bull; <span>{{ $item->peer_city }}</span> @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-xs t2">{{ $item->peer_phone ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-xs">
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">{{ $item->applying_for ?? '—' }}</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-xs">
                                        @if($item->referred_name)
                                            <div class="font-semibold t1">{{ $item->referred_name }}</div>
                                            <div class="t3 text-[10px]">{{ $item->referred_mobile ?: '—' }}</div>
                                        @else
                                            <span class="t3">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-xs t2">{{ $formatRoles($item->leadership_roles ?? null) }}</td>
                                    <td class="px-3 py-2.5 text-xs t2">{{ $item->contribute_city ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-xs t2">{{ $item->primary_domain ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-xs t2 max-w-[220px] truncate" title="{{ $item->why_interested }}">
                                        {{ $item->why_interested ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-8 text-xs t3">No submissions found.</td>
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

