@extends('admin.layouts.app')

@section('title', 'Business Deals')

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

        $formatDateTime = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : '-';
        };

        $formatDate = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : '-';
        };

        $peerName = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')) ?: $member->display_name ?: 'Unnamed Peer';
    @endphp

    <!-- Activities Hub Header -->
    @include('admin.activities.partials.header', ['title' => 'Business Deals'])

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Business Deals Log</h2>
                <p class="text-xs t1 font-medium m-0 mt-0.5">
                    @if(!empty($member->id))
                        <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $member->id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">{{ $peerName }}</a>
                    @else
                        {{ $peerName }}
                    @endif
                    • {{ $member->email ?? '-' }}
                </p>
            </div>
            <a href="{{ route('admin.activities.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline">
                Back to Activities
            </a>
        </div>

        <div class="border bs rounded-xl p-3.5 surface-2">
            <form method="GET" class="flex flex-wrap gap-3 items-center">
                <div>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" placeholder="From">
                </div>
                <div>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" placeholder="To">
                </div>
                <div class="flex justify-end">
                    <a href="{{ route('admin.activities.business-deals', $member) }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline">Clear</a>
                </div>
            </form>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:160px;">Target Peer</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:140px;">Company</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:100px;">City</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:110px;">Deal Date</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:120px;">Business Type</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:110px;">Amount</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:160px;">Comment</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:120px;">Created At</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($items as $deal)
                            @php
                                $targetUser = $deal->toUser ?? null;
                                $targetName = $targetUser ? ($targetUser->display_name ?: trim($targetUser->first_name . ' ' . $targetUser->last_name)) : ($deal->target_peer_name ?? '—');
                                $toCompany = $deal->toUser->company_name ?? $deal->toUser->company ?? null;
                                $toCity = $deal->toUser->city ?? null;
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($targetName) }}">
                                            {{ $getInitials($targetName) }}
                                        </div>
                                        <div class="font-semibold t1 text-[12.5px] whitespace-nowrap">
                                            @if(!empty($deal->toUser?->id))
                                                <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $deal->toUser->id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                                    {{ $targetName }}
                                                </a>
                                            @else
                                                {{ $targetName }}
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-xs text-slate-700 font-medium">
                                    @if($toCompany)
                                        <div class="flex items-center gap-1.5 text-slate-800 font-medium">
                                            <i class="bi bi-building text-slate-400 text-[11px]"></i>
                                            <span class="line-clamp-1" title="{{ $toCompany }}">{{ $toCompany }}</span>
                                        </div>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if($toCity)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                            <i class="bi bi-geo-alt text-slate-400 text-[10px]"></i> {{ $toCity }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap font-medium text-slate-700">
                                    {{ $deal->deal_date ? $formatDate($deal->deal_date) : '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    @php
                                        $bType = strtolower(trim((string)($deal->business_type ?? '')));
                                    @endphp
                                    @if($bType === 'new' || str_contains($bType, 'new'))
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>{{ ucfirst($deal->business_type ?: 'New') }}
                                        </span>
                                    @elseif($bType === 'repeat' || str_contains($bType, 'repeat'))
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200 whitespace-nowrap">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>{{ ucfirst($deal->business_type ?: 'Repeat') }}
                                        </span>
                                    @elseif(!empty($deal->business_type))
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-semibold bg-purple-50 text-purple-700 border border-purple-200 whitespace-nowrap">
                                            <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>{{ ucfirst($deal->business_type) }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 font-bold text-emerald-600 font-mono text-xs">
                                    ₹{{ $deal->deal_amount !== null ? number_format((float) $deal->deal_amount, 2) : '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-xs t2 max-w-[250px]">
                                    <x-admin-grid-text :text="$deal->comment ?? '-'" />
                                </td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap font-mono text-[11px]">
                                    {{ $formatDateTime($deal->created_at ?? null) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-8 text-xs t3">No business deals found.</td>
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

