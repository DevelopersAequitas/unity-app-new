@extends('admin.layouts.app')

@section('title', 'Register A Visitor - Peer Activity')

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

        $peerName = $displayName($peer->display_name ?? null, $peer->first_name ?? null, $peer->last_name ?? null);
    @endphp

    <!-- Activities Hub Header -->
    @include('admin.activities.partials.header', ['title' => 'Registered Visitor'])

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Register A Visitor Submissions</h2>
                <p class="text-xs t1 font-medium m-0 mt-0.5">{{ $peerName }} • {{ $peer->email ?? '-' }}</p>
            </div>
            <a href="{{ route('admin.activities.register-visitor.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline">
                Back to List
            </a>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full w-full border-collapse text-[13px] align-middle">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs whitespace-nowrap">
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Submitted At</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Event Details</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Visitor Details</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-left whitespace-nowrap">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2.5 text-center whitespace-nowrap">Coins Awarded</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($items as $item)
                            @php
                                $evType = strtolower((string)($item->event_type ?? ''));
                                $st = strtolower((string)($item->status ?? ''));
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs whitespace-nowrap">
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap font-mono">{{ $formatDateTime($item->created_at ?? null) }}</td>
                                <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    <div class="font-semibold t1 text-[12px] whitespace-nowrap">{{ $item->event_name ?? '—' }}</div>
                                    <div class="flex items-center gap-2 mt-1 whitespace-nowrap">
                                        @if(in_array($evType, ['physical', 'offline'], true))
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>{{ ucfirst($item->event_type ?? 'Physical') }}
                                            </span>
                                        @elseif(in_array($evType, ['virtual', 'online'], true))
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 whitespace-nowrap">
                                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>{{ ucfirst($item->event_type ?? 'Virtual') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200 whitespace-nowrap">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>{{ ucfirst($item->event_type ?? '—') }}
                                            </span>
                                        @endif
                                        @if($item->event_date)
                                            <span class="t3 text-[11px] font-mono whitespace-nowrap">{{ $formatDate($item->event_date) }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">
                                    <div class="font-semibold t1 text-[12px] whitespace-nowrap">{{ $item->visitor_full_name ?? '—' }}</div>
                                    <div class="t3 text-[11px] font-mono whitespace-nowrap">{{ $item->visitor_mobile ?? '—' }}</div>
                                    <div class="t3 text-[11px] whitespace-nowrap">{{ $item->visitor_business ?? '—' }} ({{ $item->visitor_city ?? '—' }})</div>
                                </td>
                                <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    @if($st === 'approved' || $st === 'attended')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>{{ ucfirst($item->status ?? 'Approved') }}
                                        </span>
                                    @elseif($st === 'rejected' || $st === 'cancelled')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 whitespace-nowrap">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>{{ ucfirst($item->status ?? 'Rejected') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 whitespace-nowrap">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>{{ ucfirst($item->status ?? 'Pending') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                    @if($item->coins_awarded)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 whitespace-nowrap">
                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>Yes
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200 whitespace-nowrap">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>No
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-8 text-xs t3">No entries found.</td>
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
@endsection

