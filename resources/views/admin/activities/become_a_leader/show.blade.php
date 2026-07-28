@extends('admin.layouts.app')

@section('title', 'Become A Leader - Peer Activity')

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

        $peerName = $displayName($peer->display_name ?? null, $peer->first_name ?? null, $peer->last_name ?? null);
    @endphp

    <!-- Activities Hub Header -->
    @include('admin.activities.partials.header', ['title' => 'Leadership Requests'])

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Become A Leader Submissions</h2>
                <p class="text-xs t1 font-medium m-0 mt-0.5">{{ $peerName }} • {{ $peer->email ?? '-' }}</p>
            </div>
            <a href="{{ route('admin.activities.become-a-leader.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline">
                Back to List
            </a>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Submitted At</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Applying For</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Referred Details</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Leadership Roles</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City / Region</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Primary Domain</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Why Interested</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($items as $item)
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $formatDateTime($item->created_at ?? null) }}</td>
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
                                <td class="px-3 py-2.5 text-xs t2 max-w-[280px] truncate" title="{{ $item->why_interested }}">
                                    {{ $item->why_interested ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-8 text-xs t3">No entries found.</td>
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

