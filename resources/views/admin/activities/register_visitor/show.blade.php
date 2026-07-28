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
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Submitted At</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Event Details</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Visitor Details</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Coins Awarded</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($items as $item)
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $formatDateTime($item->created_at ?? null) }}</td>
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

