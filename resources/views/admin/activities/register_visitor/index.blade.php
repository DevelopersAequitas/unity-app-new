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
                    <table class="min-w-full w-full border-collapse text-[13px] align-middle">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs whitespace-nowrap">
                                <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap" style="min-width: 130px;">Submitted At</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left sticky left-0 z-10 whitespace-nowrap" style="min-width: 170px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">Peer Name</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap" style="min-width: 130px;">Company</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap" style="min-width: 100px;">City</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap" style="min-width: 120px;">Peer Phone</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap" style="min-width: 160px;">Event Name</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap" style="min-width: 110px;">Event Type</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap" style="min-width: 110px;">Event Date</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap" style="min-width: 140px;">Visitor Name</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap" style="min-width: 115px;">Mobile</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap" style="min-width: 140px;">Business</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap" style="min-width: 100px;">City</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap" style="min-width: 110px;">Status</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-center whitespace-nowrap" style="min-width: 110px;">Coins Awarded</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-right whitespace-nowrap" style="min-width: 120px;">Actions</th>
                            </tr>
                            <tr class="surface-2 border-b bs filter-row whitespace-nowrap">
                                <th class="px-2 py-1.5 text-center t3 whitespace-nowrap">—</th>
                                <th class="px-2 py-1.5 sticky left-0 z-10 surface-2 whitespace-nowrap" style="box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);"><input type="text" name="peer_name" value="{{ $filters['peer_name'] ?? '' }}" placeholder="Peer Name" class="px-2 py-1 text-xs rounded-lg border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1.5 whitespace-nowrap"><input type="text" class="px-2 py-1 text-xs rounded-lg border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                                <th class="px-2 py-1.5 whitespace-nowrap"><input type="text" class="px-2 py-1 text-xs rounded-lg border bs surface t1 w-full outline-none focus-ring" disabled placeholder="-"></th>
                                <th class="px-2 py-1.5 whitespace-nowrap"><input type="text" name="peer_phone" value="{{ $filters['peer_phone'] ?? '' }}" placeholder="Phone" class="px-2 py-1 text-xs rounded-lg border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1.5 whitespace-nowrap"><input type="text" name="event_name" value="{{ $filters['event_name'] ?? '' }}" placeholder="Event Name" class="px-2 py-1 text-xs rounded-lg border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1.5 whitespace-nowrap"><input type="text" name="event_type" value="{{ $filters['event_type'] ?? '' }}" placeholder="Type" class="px-2 py-1 text-xs rounded-lg border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1.5 whitespace-nowrap"><input type="date" name="event_date" value="{{ $filters['event_date'] ?? '' }}" class="px-2 py-1 text-xs rounded-lg border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1.5 whitespace-nowrap"><input type="text" name="visitor_name" value="{{ $filters['visitor_name'] ?? '' }}" placeholder="Visitor" class="px-2 py-1 text-xs rounded-lg border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1.5 whitespace-nowrap"><input type="text" name="visitor_mobile" value="{{ $filters['visitor_mobile'] ?? '' }}" placeholder="Mobile" class="px-2 py-1 text-xs rounded-lg border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1.5 whitespace-nowrap"><input type="text" name="visitor_business" value="{{ $filters['visitor_business'] ?? '' }}" placeholder="Business" class="px-2 py-1 text-xs rounded-lg border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1.5 whitespace-nowrap"><input type="text" name="visitor_city" value="{{ $filters['visitor_city'] ?? '' }}" placeholder="City" class="px-2 py-1 text-xs rounded-lg border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1.5 whitespace-nowrap"><input type="text" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="Status" class="px-2 py-1 text-xs rounded-lg border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1.5 whitespace-nowrap"><input type="number" name="coins_awarded" value="{{ $filters['coins_awarded'] ?? '' }}" placeholder="Coins" class="px-2 py-1 text-xs rounded-lg border bs surface t1 w-full outline-none focus-ring"></th>
                                <th class="px-2 py-1.5 whitespace-nowrap">
                                    <div class="flex justify-end">
                                        <button type="button" onclick="clearAdminFilters(event, 'adminactivitiesregister-visitorindexFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition">Clear</button>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="grid-body" class="divide-y divide-gray-200/50">
                            @forelse ($items as $item)
                                @php
                                    $peerName = $item->peer_name ?? '—';
                                    $visitorSearch = $item->visitor_mobile ? ['search' => $item->visitor_mobile] : [];
                                    $evType = strtolower((string)($item->event_type ?? ''));
                                    $st = strtolower((string)($item->status ?? ''));
                                @endphp
                                <tr class="hover:surface-2 transition border-b bs whitespace-nowrap">
                                    <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap font-mono">{{ $formatDateTime($item->created_at ?? null) }}</td>
                                    <td class="px-3 py-2.5 sticky left-0 z-10 surface whitespace-nowrap" style="min-width:170px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.10);">
                                        <div class="flex items-center gap-2 whitespace-nowrap">
                                            <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($peerName) }}">
                                                {{ $getInitials($peerName) }}
                                            </div>
                                            <div class="font-semibold t1 text-[12.5px] whitespace-nowrap">
                                                @if(!empty($item->user_id ?? $item->actor_id))
                                                    <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $item->user_id ?? $item->actor_id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline whitespace-nowrap">
                                                        {{ $peerName }}
                                                    </a>
                                                @else
                                                    <span class="whitespace-nowrap">{{ $peerName }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $item->peer_company ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $item->peer_city ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap font-mono">{{ $item->peer_phone ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-xs font-semibold t1 whitespace-nowrap">
                                        @if(!empty($item->event_id))
                                            <a href="{{ route('admin.events.show', $item->event_id) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline whitespace-nowrap">
                                                {{ $item->event_name ?? '—' }}
                                            </a>
                                        @else
                                            <span class="whitespace-nowrap">{{ $item->event_name ?? '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                        @if(in_array($evType, ['physical', 'offline'], true))
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>{{ ucfirst($item->event_type ?? 'Physical') }}
                                            </span>
                                        @elseif(in_array($evType, ['virtual', 'online'], true))
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 whitespace-nowrap">
                                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>{{ ucfirst($item->event_type ?? 'Virtual') }}
                                            </span>
                                        @elseif(in_array($evType, ['hybrid'], true))
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200 whitespace-nowrap">
                                                <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>{{ ucfirst($item->event_type ?? 'Hybrid') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200 whitespace-nowrap">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>{{ ucfirst($item->event_type ?? '—') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap font-mono">
                                        {{ $item->event_date ? $formatDate($item->event_date) : '—' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-xs font-semibold t1 whitespace-nowrap">
                                        {{ $item->visitor_full_name ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap font-mono">{{ $item->visitor_mobile ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $item->visitor_business ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $item->visitor_city ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                        @if($st === 'approved' || $st === 'attended')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>{{ ucfirst($item->status ?? 'Approved') }}
                                            </span>
                                        @elseif($st === 'rejected' || $st === 'cancelled')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-rose-50 text-rose-700 border border-rose-200 whitespace-nowrap">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>{{ ucfirst($item->status ?? 'Rejected') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-amber-50 text-amber-700 border border-amber-200 whitespace-nowrap">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>{{ ucfirst($item->status ?? 'Pending') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                        @if($item->coins_awarded)
                                            <span class="inline-flex items-center gap-1 text-[11.5px] font-semibold text-amber-600 whitespace-nowrap">
                                                <i class="bi bi-coin text-amber-500 text-[11px]"></i> Yes
                                            </span>
                                        @else
                                            <span class="text-[11.5px] text-slate-400 font-medium whitespace-nowrap">
                                                No
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap" style="min-width:130px;">
                                        @if ($item->visitor_mobile)
                                            <a class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-md bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm hover:shadow transition no-underline whitespace-nowrap" href="{{ route('admin.visitor-registrations.index', $visitorSearch) }}">
                                                <i class="bi bi-box-arrow-up-right text-[10px]"></i> Open Approval
                                            </a>
                                        @else
                                            <span class="t3 whitespace-nowrap">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="15" class="text-center py-8 text-xs t3">No registered visitors found.</td>
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
