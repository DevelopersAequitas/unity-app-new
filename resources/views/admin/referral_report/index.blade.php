@extends('admin.layouts.app')

@section('title', 'Referral Report')

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
@endphp

<form id="referralReportFilters" method="GET" action="{{ route('admin.referral-report.index') }}"></form>

<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Referral Report</h2>
            <p class="text-xs t3 m-0 mt-0.5">See which peer referred how many users and referral coins granted.</p>
        </div>
        <div class="flex gap-2 items-center">
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Total Referrers: {{ number_format($records->total()) }}</span>
            <a href="{{ route('admin.referral-report.export', request()->query()) }}" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold transition focus-ring no-underline">
                Export CSV
            </a>
        </div>
    </div>

    <div class="p-3 rounded-lg border bs surface-2">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                <input type="text" name="q" form="referralReportFilters" value="{{ $filters['q'] ?? '' }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Name, email, phone, code">
            </div>
            <div class="w-40">
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">From Date</label>
                <input type="date" name="from" form="referralReportFilters" value="{{ $filters['from'] ?? '' }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
            </div>
            <div class="w-40">
                <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">To Date</label>
                <input type="date" name="to" form="referralReportFilters" value="{{ $filters['to'] ?? '' }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" form="referralReportFilters" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition shadow-sm cursor-pointer">Filter</button>
                <a href="{{ route('admin.referral-report.index') }}" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg border bs surface t2 hover:t1 hover:surface-3 transition text-center no-underline shadow-sm">Clear</a>
            </div>
        </div>
    </div>

    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="overflow-x-auto relative">
            <table class="min-w-[1100px] w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left sticky left-0 z-10" style="min-width:180px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">Referrer Name</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Phone Number</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Referral Code</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 280px;">Referred Users</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Total Users</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Coins Granted</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Last Referral Date</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/50">
                    @forelse ($records as $record)
                        @php
                            $refAvatar = $record->referrer_profile_photo_url ?? ($record->referrer_profile_photo_file_id ? url('/api/v1/files/' . $record->referrer_profile_photo_file_id) : null);
                        @endphp
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-3 py-2.5 text-xs sticky left-0 z-10 surface" style="min-width:180px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.10);">
                                <div class="flex items-center gap-2.5">
                                    @if($refAvatar)
                                        <img src="{{ $refAvatar }}" alt="{{ $record->referrer_name }}" class="w-8 h-8 rounded-full object-cover border bs flex-shrink-0" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                        <div class="w-8 h-8 rounded-full text-white font-bold items-center justify-center text-xs flex-shrink-0 hidden" style="background-color: {{ $getAvatarBg($record->referrer_name ?? '') }}">
                                            {{ $getInitials($record->referrer_name ?? '') }}
                                        </div>
                                    @else
                                        <div class="w-8 h-8 rounded-full text-white font-bold flex items-center justify-center text-xs flex-shrink-0" style="background-color: {{ $getAvatarBg($record->referrer_name ?? '') }}">
                                            {{ $getInitials($record->referrer_name ?? '') }}
                                        </div>
                                    @endif
                                    <div class="font-semibold t1">
                                        @if(!empty($record->referrer_user_id))
                                            <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $record->referrer_user_id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                                {{ $record->referrer_name ?: 'Deleted / Unknown User' }}
                                            </a>
                                        @else
                                            {{ $record->referrer_name ?: 'Deleted / Unknown User' }}
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-xs t2">{{ $record->referrer_company ?: '—' }}</td>
                            <td class="px-3 py-2.5 text-xs t2">{{ $record->referrer_city ?: '—' }}</td>
                            <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $record->referrer_phone ?: '—' }}</td>
                            <td class="px-3 py-2.5 text-xs">
                                <code class="text-[11px] font-mono bg-gray-100 px-1.5 py-0.5 rounded border bs">{{ $record->referral_codes ?: '—' }}</code>
                            </td>
                            <td class="px-3 py-2.5 text-xs">
                                @php
                                    $referredUsers = $referredUsersByReferrer->get((string) $record->referrer_user_id, collect());
                                @endphp
                                @if($referredUsers->isNotEmpty())
                                    <div class="space-y-1.5 max-h-48 overflow-y-auto">
                                        @foreach($referredUsers as $referredUser)
                                            <div class="p-2 rounded border bs surface-2 text-xs">
                                                <div class="font-semibold t1">
                                                    @if(!empty($referredUser->user_id))
                                                        <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $referredUser->user_id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                                            {{ $referredUser->referred_name ?: 'Unknown' }}
                                                        </a>
                                                    @else
                                                        {{ $referredUser->referred_name ?: 'Unknown' }}
                                                    @endif
                                                </div>
                                                <div class="t3 text-[10px]">
                                                    @if($referredUser->company_name) <span>{{ $referredUser->company_name }}</span> @endif
                                                    @if($referredUser->city) &bull; <span>{{ $referredUser->city }}</span> @endif
                                                </div>
                                                <div class="flex gap-1.5 mt-1">
                                                    <span class="chip px-1.5 py-0.2 text-[10px] bg-amber-50 text-amber-700 border-amber-200">{{ number_format((int) $referredUser->coins) }} coins</span>
                                                    <span class="chip px-1.5 py-0.2 text-[10px] bg-gray-100 text-gray-600 border-gray-200">{{ $referredUser->reward_status ?: '—' }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="t3">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center text-xs font-semibold t1">{{ number_format((int) $record->total_referred_users) }}</td>
                            <td class="px-3 py-2.5 text-center text-xs">
                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200">
                                    {{ number_format((int) $record->total_coins_granted) }} coins
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $record->last_referral_date ? \Illuminate\Support\Carbon::parse($record->last_referral_date)->format('d-m-Y h:i A') : '—' }}</td>
                            <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                @if($record->referrer_user_id)
                                    <a href="{{ route('admin.referral-report.show', $record->referrer_user_id) }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">
                                        View Users
                                    </a>
                                @else
                                    <span class="t3">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-8 text-xs t3">No referral records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
            {{ $records->links() }}
        </div>
    </div>
</div>
@endsection
