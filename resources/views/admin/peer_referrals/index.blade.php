@extends('admin.layouts.app')

@section('title', 'Peer Referrals')

@include('admin.partials.grid-head')

@php
    $getInitials = function (?string $name): string {
        if (! $name) return 'P';
        $words = explode(' ', trim($name));
        $initials = '';
        foreach ($words as $w) {
            if (! empty($w)) $initials .= strtoupper(substr($w, 0, 1));
        }
        return substr($initials, 0, 2) ?: 'P';
    };

    $getAvatarBg = function (?string $name): string {
        if (! $name) return '#6366f1';
        $colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
        $hash = crc32($name);
        return $colors[abs($hash) % count($colors)];
    };

    $getStatusBadgeClass = function (string $status): string {
        return match ($status) {
            'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
            'contacted' => 'bg-blue-50 text-blue-700 border-blue-200',
            'accepted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
            'converted' => 'bg-purple-50 text-purple-700 border-purple-200',
            default => 'bg-gray-50 text-gray-700 border-gray-200',
        };
    };
@endphp

@section('content')
    <form id="peerReferralsFiltersForm" method="GET" action="{{ route('admin.peer-referrals.index') }}"></form>

    @if (session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Peer Referrals</h2>
                <p class="text-xs t3 m-0 mt-0.5">Manage and track peer referrals for open categories.</p>
            </div>
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">
                Total: {{ number_format($peerReferrals->total()) }}
            </span>
        </div>

        <div class="p-3 rounded-lg border bs surface-2">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-2.5 items-end">
                <div class="md:col-span-2">
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                    <input type="text" name="search" form="peerReferralsFiltersForm" value="{{ $filters['search'] ?? '' }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Search peer name, phone, email, business or referrer">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                    <select name="status" form="peerReferralsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="">All Statuses</option>
                        @foreach(['pending', 'contacted', 'accepted', 'rejected', 'converted'] as $opt)
                            <option value="{{ $opt }}" {{ ($filters['status'] ?? '') === $opt ? 'selected' : '' }}>{{ ucfirst($opt) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Circle</label>
                    <select name="main_circle_id" form="peerReferralsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="">All Circles</option>
                        @foreach($circles as $circle)
                            <option value="{{ $circle->id }}" {{ ($filters['main_circle_id'] ?? '') === $circle->id ? 'selected' : '' }}>{{ $circle->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="submit" form="peerReferralsFiltersForm" class="px-3 py-1.5 text-xs font-semibold rounded bg-indigo-600 hover:bg-indigo-500 text-white transition focus-ring w-full text-center">Filter</button>
                    <a href="{{ route('admin.peer-referrals.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center no-underline w-full">Clear</a>
                </div>
            </div>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Referrer Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Referred Peer</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Contact Info</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company &amp; Role</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Parent Circle</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Category</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created At</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($peerReferrals as $referral)
                            @php
                                $refUser = $referral->referrer;
                                $refUserName = $refUser ? ($refUser->display_name ?: trim(($refUser->first_name ?? '') . ' ' . ($refUser->last_name ?? ''))) : '—';
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs">
                                    @if ($refUser)
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($refUserName) }}">
                                                {{ $getInitials($refUserName) }}
                                            </div>
                                            <a href="#" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $refUser->id }}', event);" class="text-indigo-600 font-semibold hover:underline no-underline">
                                                {{ $refUserName }}
                                            </a>
                                        </div>
                                    @else
                                        <span class="t3">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs font-semibold t1">{{ $referral->referred_name }}</td>
                                <td class="px-3 py-2.5 text-xs t2">
                                    <div>{{ $referral->referred_phone }}</div>
                                    @if($referral->referred_email)
                                        <div class="text-[11px] t3">{{ $referral->referred_email }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t2">
                                    <div>{{ $referral->referred_company_name ?? '—' }}</div>
                                    @if($referral->referred_designation)
                                        <div class="text-[11px] t3">{{ $referral->referred_designation }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $referral->mainCircle?->name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $referral->category?->name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-xs">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold border {{ $getStatusBadgeClass($referral->status) }}">
                                        {{ ucfirst($referral->status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                    {{ $referral->created_at?->format('d M Y, h:i A') ?? '—' }}
                                </td>
                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <a href="{{ route('admin.peer-referrals.show', $referral->id) }}" class="px-2.5 py-1 text-xs font-semibold rounded bg-indigo-600 hover:bg-indigo-500 text-white transition no-underline inline-block focus-ring">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-8 text-xs t3">No peer referrals found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $peerReferrals->links() }}
            </div>
        </div>
    </div>
@endsection
