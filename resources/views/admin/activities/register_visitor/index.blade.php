@extends('admin.layouts.app')

@section('title', 'Registered Visitor')

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
        @include('admin.activities.partials.header', ['title' => 'Registered Visitor'])

        <!-- Metrics Cards -->
        <div class="activities-stats-grid">
            <div class="activity-metric-card">
                <div class="metric-icon bg-primary-subtle text-primary">
                    <i class="bi bi-person-vcard-fill"></i>
                </div>
                <div>
                    <div class="metric-val">{{ number_format($items->total()) }}</div>
                    <div class="metric-label">Total Registered Visitors</div>
                </div>
            </div>

            <div class="activity-metric-card">
                <div class="metric-icon bg-success-subtle text-success">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div>
                    <div class="metric-val">
                        {{ number_format($items->filter(fn($item) => strtolower((string)$item->status) === 'approved' || strtolower((string)$item->status) === 'attended')->count()) }}
                    </div>
                    <div class="metric-label">Approved / Attended Visitors (Page)</div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <form id="adminactivitiesregister-visitorindexFiltersForm" method="GET" action="{{ route('admin.activities.register-visitor.index') }}" class="space-y-4">
            @include('admin.components.activity-filter-bar-v2', [
                'actionUrl' => route('admin.activities.register-visitor.index'),
                'resetUrl' => route('admin.activities.register-visitor.index'),
                'filters' => $filters,
                'circles' => $circles ?? collect(),
                'showExport' => false,
                'renderFormTag' => false,
                'formId' => 'adminactivitiesregister-visitorindexFiltersForm',
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
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Event Details</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Visitor Details</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Coins Awarded</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                            </tr>
                            <tr class="surface-2 border-b bs filter-row">
                                <th class="px-2 py-1 text-center t3">—</th>
                                <th class="px-2 py-1">
                                    <input type="text" name="peer_name" value="{{ $filters['peer_name'] ?? '' }}" placeholder="Peer Name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                </th>
                                <th class="px-2 py-1"><input type="text" name="peer_phone" value="{{ $filters['peer_phone'] ?? '' }}" placeholder="Phone" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1">
                                    <input type="text" name="event_type" value="{{ $filters['event_type'] ?? '' }}" placeholder="Event type" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring mb-1">
                                    <input type="text" name="event_name" value="{{ $filters['event_name'] ?? '' }}" placeholder="Event name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring mb-1">
                                    <input type="date" name="event_date" value="{{ $filters['event_date'] ?? '' }}" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                </th>
                                <th class="px-2 py-1">
                                    <input type="text" name="visitor_name" value="{{ $filters['visitor_name'] ?? '' }}" placeholder="Visitor name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring mb-1">
                                    <input type="text" name="visitor_mobile" value="{{ $filters['visitor_mobile'] ?? '' }}" placeholder="Mobile" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring mb-1">
                                    <input type="text" name="visitor_city" value="{{ $filters['visitor_city'] ?? '' }}" placeholder="City" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring mb-1">
                                    <input type="text" name="visitor_business" value="{{ $filters['visitor_business'] ?? '' }}" placeholder="Business" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                                </th>
                                <th class="px-2 py-1"><input type="text" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="Status" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1"><input type="number" name="coins_awarded" value="{{ $filters['coins_awarded'] ?? '' }}" placeholder="Coins" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1">
                                    <div class="flex justify-end">
                                        <button type="button" onclick="clearAdminFilters(event, 'adminactivitiesregister-visitorindexFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">Clear</button>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="grid-body" class="divide-y divide-gray-200/50">
                            @forelse ($items as $item)
                                @php
                                    $peerName = $item->peer_name ?? '—';
                                    $visitorSearch = $item->visitor_mobile ? ['search' => $item->visitor_mobile] : [];
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
                                        <div class="font-semibold t1 text-[12px]">{{ $item->event_name ?? '—' }}</div>
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200 mt-0.5">{{ ucfirst($item->event_type ?? '—') }}</span>
                                        @if($item->event_date)
                                            <div class="t3 text-[10px] mt-0.5">{{ $formatDate($item->event_date) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-xs t2">
                                        <div class="font-semibold t1 text-[12px]">{{ $item->visitor_full_name ?? '—' }}</div>
                                        <div class="t3 text-[10px]">{{ $item->visitor_mobile ?? '—' }}</div>
                                        <div class="t3 text-[10px]">{{ $item->visitor_business ?? '—' }} ({{ $item->visitor_city ?? '—' }})</div>
                                    </td>
                                    <td class="px-3 py-2.5 text-xs">
                                        @if(strtolower((string)$item->status) === 'approved' || strtolower((string)$item->status) === 'attended')
                                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">{{ ucfirst($item->status ?? '—') }}</span>
                                        @else
                                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200">{{ ucfirst($item->status ?? '—') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-xs">
                                        @if($item->coins_awarded)
                                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">Yes</span>
                                        @else
                                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">No</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                        @if ($item->visitor_mobile)
                                            <a class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline" href="{{ route('admin.visitor-registrations.index', $visitorSearch) }}">
                                                Open Approval
                                            </a>
                                        @else
                                            <span class="t3">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-8 text-xs t3">No registered visitors found.</td>
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
