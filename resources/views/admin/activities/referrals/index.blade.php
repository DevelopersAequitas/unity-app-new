@extends('admin.layouts.app')

@section('title', 'Referrals')

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

        $formatDate = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : '—';
        };
    @endphp

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <!-- Header Component -->
        @include('admin.activities.partials.header', ['title' => 'Referrals'])

        <!-- Metrics Cards -->
        <div class="activities-stats-grid">
            <div class="activity-metric-card">
                <div class="metric-icon bg-primary-subtle text-primary">
                    <i class="bi bi-person-plus-fill"></i>
                </div>
                <div class="metric-val">{{ number_format($total) }}</div>
                <div class="metric-label">Total Referrals</div>
            </div>

            <div class="activity-metric-card">
                <div class="metric-icon bg-warning-subtle text-warning-emphasis">
                    <i class="bi bi-star-fill"></i>
                </div>
                <div class="metric-val">
                    @if(($topMembers ?? collect())->isNotEmpty())
                        {{ $topMembers->first()->total_count ?? 0 }}
                    @else
                        0
                    @endif
                </div>
                <div class="metric-label">Most Referrals by One Peer</div>
            </div>

            <div class="activity-metric-card">
                <div class="metric-icon bg-danger-subtle text-danger">
                    <i class="bi bi-fire"></i>
                </div>
                <div class="metric-val">
                    {{ number_format($items->where('hot_value', '>', 3)->count()) }}
                </div>
                <div class="metric-label">Hot Referrals (Page)</div>
            </div>
        </div>

        <!-- Filters Section -->
        <form id="referralsFiltersForm" method="GET" action="{{ route('admin.activities.referrals.index') }}" class="space-y-4">
            @include('admin.components.activity-filter-bar-v2', [
                'actionUrl' => route('admin.activities.referrals.index'),
                'resetUrl' => route('admin.activities.referrals.index'),
                'filters' => $filters,
                'circles' => $circles ?? collect(),
                'showExport' => true,
                'exportUrl' => route('admin.activities.referrals.export', request()->query()),
                'renderFormTag' => false,
                'formId' => 'referralsFiltersForm',
            ])

            <div class="space-y-4">
                <!-- Top 5 Peers Grid -->
                <div class="rounded-xl border bs surface overflow-hidden">
                    <div class="px-4 py-3 surface-2 border-b bs flex justify-between items-center">
                        <span class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider">Top 5 Peers</span>
                    </div>
                    <div class="overflow-x-auto relative">
                        <table class="min-w-full border-collapse text-[13px]">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left w-16">Rank</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer Name</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Total Referrals</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200/50">
                                @forelse ($topMembers as $index => $member)
                                    <tr class="hover:surface-2 transition border-b bs">
                                        <td class="px-3 py-2.5 text-xs font-semibold t3">#{{ $index + 1 }}</td>
                                        <td class="px-3 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($member->peer_name ?? '') }}">
                                                    {{ $getInitials($member->peer_name ?? '') }}
                                                </div>
                                                <div>
                                                    <div class="font-semibold t1 text-[12.5px]">{{ $member->peer_name ?? $displayName($member->display_name ?? null, $member->first_name ?? null, $member->last_name ?? null) }}</div>
                                                    <div class="t3 text-[10px]">{{ $member->peer_company ?? '' }}</div>
                                                </div>
                                            </div>
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

                <!-- All Logs Grid -->
                <div class="rounded-xl border bs surface overflow-hidden">
                    <div class="px-4 py-3 surface-2 border-b bs flex justify-between items-center">
                        <span class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider">Referrals Log</span>
                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Page count: {{ number_format(count($items)) }}</span>
                    </div>
                    <div class="overflow-x-auto relative">
                        <table class="min-w-full border-collapse text-[13px]">
                            <thead>
                                <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">From</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">To</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Referral Info</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Contact details</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Hot Value</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Remarks</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Media</th>
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created At</th>
                                </tr>
                                <tr class="surface-2 border-b bs filter-row">
                                    <th class="px-2 py-1"><input type="text" name="from_user" value="{{ $filters['from_user'] ?? '' }}" placeholder="From name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                    <th class="px-2 py-1"><input type="text" name="to_user" value="{{ $filters['to_user'] ?? '' }}" placeholder="To name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                    <th class="px-2 py-1">
                                        <input type="text" name="referral_of" value="{{ $filters['referral_of'] ?? '' }}" placeholder="Referral of" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring mb-1">
                                        <input type="text" name="referral_type" value="{{ $filters['referral_type'] ?? '' }}" placeholder="Type" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                    </th>
                                    <th class="px-2 py-1"><input type="text" name="phone" value="{{ $filters['phone'] ?? '' }}" placeholder="Phone/Email" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                    <th class="px-2 py-1"><input type="number" name="hot_value" value="{{ $filters['hot_value'] ?? '' }}" placeholder="Hot value" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                    <th class="px-2 py-1"><input type="text" name="remarks" value="{{ $filters['remarks'] ?? '' }}" placeholder="Remarks" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                    <th class="px-2 py-1">
                                        <select name="has_media" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                            <option value="">Any</option>
                                            <option value="1" @selected(($filters['has_media'] ?? '') === '1')>Yes</option>
                                            <option value="0" @selected(($filters['has_media'] ?? '') === '0')>No</option>
                                        </select>
                                    </th>
                                    <th class="px-2 py-1">
                                        <div class="flex justify-end">
                                            <button type="button" onclick="clearAdminFilters(event, 'referralsFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">Clear</button>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="grid-body" class="divide-y divide-gray-200/50">
                                @forelse ($items as $referral)
                                    @php
                                        $actorName = $displayName($referral->actor_display_name ?? null, $referral->actor_first_name ?? null, $referral->actor_last_name ?? null);
                                        $peerName = $displayName($referral->peer_display_name ?? null, $referral->peer_first_name ?? null, $referral->peer_last_name ?? null);

                                        $fromName = $referral->from_user_name ?? $actorName;
                                        $toName = $referral->to_user_name ?? $peerName;
                                    @endphp
                                    <tr class="hover:surface-2 transition border-b bs">
                                        <td class="px-3 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($fromName) }}">
                                                    {{ $getInitials($fromName) }}
                                                </div>
                                                <div>
                                                    <div class="font-semibold t1 text-[12.5px]">{{ $fromName }}</div>
                                                    <div class="t3 text-[10px]">
                                                        @if($referral->from_company) <span>{{ $referral->from_company }}</span> @endif
                                                        @if($referral->from_city) &bull; <span>{{ $referral->from_city }}</span> @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($toName) }}">
                                                    {{ $getInitials($toName) }}
                                                </div>
                                                <div>
                                                    <div class="font-semibold t1 text-[12.5px]">{{ $toName }}</div>
                                                    <div class="t3 text-[10px]">
                                                        @if($referral->to_company) <span>{{ $referral->to_company }}</span> @endif
                                                        @if($referral->to_city) &bull; <span>{{ $referral->to_city }}</span> @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs">
                                            <div class="font-semibold t1 text-[12px]">{{ $referral->referral_of ?? '—' }}</div>
                                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200 mt-0.5">{{ $referral->referral_type ?? '—' }}</span>
                                            @if($referral->referral_date)
                                                <div class="t3 text-[10px] mt-0.5">{{ $formatDate($referral->referral_date) }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-xs t2">
                                            <div>{{ $referral->phone ?? '—' }}</div>
                                            <div class="t3 text-[10px]">{{ $referral->email ?? '—' }}</div>
                                            <div class="t3 text-[10px] truncate max-w-[160px]">{{ $referral->address ?? '—' }}</div>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs">
                                            @if($referral->hot_value)
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200">
                                                    {{ $referral->hot_value }}
                                                </span>
                                            @else
                                                <span class="t3">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-xs t2 max-w-[150px] truncate" title="{{ $referral->remarks }}">
                                            {{ $referral->remarks ?? '—' }}
                                        </td>
                                        <td class="px-3 py-2.5 text-xs">
                                            @if ((int) ($referral->has_media ?? 0) === 1)
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">
                                                    Yes
                                                </span>
                                                @if (!empty($referral->media_reference))
                                                    @php
                                                        $mediaReference = (string) $referral->media_reference;
                                                        $mediaUrl = str_starts_with($mediaReference, 'http://') || str_starts_with($mediaReference, 'https://')
                                                            ? $mediaReference
                                                            : url('/api/v1/files/' . $mediaReference);
                                                    @endphp
                                                    <a href="{{ $mediaUrl }}" target="_blank" rel="noopener" class="px-2 py-0.5 text-xs font-semibold rounded border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition no-underline ms-1">View</a>
                                                @endif
                                            @else
                                                <span class="t3">No Media</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                            {{ $formatDateTime($referral->created_at ?? null) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No referrals found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="mt-3">
        {{ $items->links() }}
    </div>
@endsection
