@extends('admin.layouts.app')

@section('title', 'Coins')

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
@endphp

@section('content')
    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Coins Management</h2>
                <p class="text-xs t3 m-0 mt-0.5">Overview of peer coin balances and reward activity metrics.</p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <div class="flex items-center gap-1.5">
                    <label for="perPage" class="text-xs t3 m-0 font-medium">Rows per page:</label>
                    <select id="perPage" name="per_page" form="coinsFiltersForm" class="px-2.5 py-1 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                        @foreach ([10, 20, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" id="coinsExportBtn" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold text-indigo-600 hover:text-indigo-700 surface-2 transition">
                    Export
                </button>
                <a href="{{ route('admin.coins.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1">
                    ➕ Add Coins
                </a>
            </div>
        </div>

        <form id="coinsFiltersForm" method="GET" action="{{ route('admin.coins.index') }}" class="admin-filter-form space-y-4">
            <div class="rounded-xl border bs surface overflow-hidden">
                <div class="overflow-x-auto relative w-full">
                    <table class="w-full min-w-[920px] border-collapse text-[13px]">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left sticky left-0 z-10" style="min-width: 180px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">Peer Name</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Circle</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width: 130px;">Total Coins</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width: 100px;">Testimonials</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width: 100px;">Referrals</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width: 110px;">Business Deals</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width: 110px;">P2P Meetings</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width: 110px;">Requirements</th>
                            </tr>

                            <tr class="surface-2 border-b bs align-middle filter-row">
                                <th class="px-3 py-2 text-left sticky left-0 z-10 surface-2" style="box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">
                                    <input
                                        id="coinsQ"
                                        type="text"
                                        name="q"
                                        class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal"
                                        placeholder="Search peer, company, city"
                                        value="{{ $filters['q'] }}"
                                    >
                                </th>
                                <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" disabled placeholder="-"></th>
                                <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" disabled placeholder="-"></th>
                                <th class="px-3 py-2 text-left">
                                    <select id="coinsCircle" name="circle_id" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal">
                                        <option value="all">All Circles</option>
                                        @foreach ($circles as $circle)
                                            <option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? 'all') == $circle->id)>{{ $circle->name }}</option>
                                        @endforeach
                                    </select>
                                </th>
                                <th class="text-center t3 text-xs">-</th>
                                <th class="text-center t3 text-xs">-</th>
                                <th class="text-center t3 text-xs">-</th>
                                <th class="text-center t3 text-xs">-</th>
                                <th class="text-center t3 text-xs">-</th>
                                <th class="px-3 py-2 text-center">
                                    <button type="button" onclick="clearAdminFilters(event, 'coinsFiltersForm')" class="px-3 py-1 rounded-md border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition">Clear</button>
                                </th>
                            </tr>
                        </thead>

                        <tbody id="grid-body" class="divide-y divide-gray-200/50">
                            @forelse ($members as $member)
                                @php
                                    $stats = $activityStats[$member->id] ?? null;
                                    $totalCoins = (int) ($member->coins_balance ?? 0);
                                    $testimonialCount = (int) ($stats->testimonial_count ?? 0);
                                    $referralCount = (int) ($stats->referral_count ?? 0);
                                    $businessDealCount = (int) ($stats->business_deal_count ?? 0);
                                    $p2pMeetingCount = (int) ($stats->p2p_meeting_count ?? 0);
                                    $requirementCount = (int) ($stats->requirement_count ?? 0);

                                    $memberName = $member ? ($member->display_name ?: trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''))) : '—';
                                    $company = $member->company_name ?? $member->company ?? $member->business_name ?? '—';
                                    $city = $member->city ?? '—';
                                    $userCircles = $member ? $member->circleMembers->map(fn($cm) => optional($cm->circle)->name)->filter()->unique()->implode(', ') : '';
                                    $circleName = $userCircles !== '' ? $userCircles : '—';
                                @endphp
                                <tr class="hover:surface-2 transition border-b bs">
                                    <td class="px-3 py-2.5 text-left align-middle sticky left-0 z-10 surface" style="min-width:180px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.10);">
                                        @if ($member)
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($memberName) }}">
                                                    {{ $getInitials($memberName) }}
                                                </div>
                                                <a href="{{ route('admin.users.show', $member->id) }}" class="text-indigo-600 font-semibold hover:underline no-underline">
                                                    {{ $memberName }}
                                                </a>
                                            </div>
                                        @else
                                            <span class="t3">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-xs t2 align-middle">{{ $company }}</td>
                                    <td class="px-3 py-2.5 text-xs t2 align-middle">{{ $city }}</td>
                                    <td class="px-3 py-2.5 text-xs t2 align-middle">{{ $circleName }}</td>
                                    <td class="px-3 py-2.5 text-center align-middle whitespace-nowrap">
                                        <a href="{{ route('admin.coins.ledger', $member) }}" class="chip px-2.5 py-1 text-xs font-semibold text-indigo-600 hover:text-indigo-700 no-underline inline-block" target="_blank" rel="noopener">{{ number_format($totalCoins) }}</a>
                                    </td>
                                    <td class="px-3 py-2.5 text-center align-middle whitespace-nowrap">
                                        <a href="{{ route('admin.coins.ledger.type', [$member, 'testimonial']) }}" class="chip px-2.5 py-1 text-xs font-semibold t2 hover:t1 no-underline inline-block" target="_blank" rel="noopener">{{ number_format($testimonialCount) }}</a>
                                    </td>
                                    <td class="px-3 py-2.5 text-center align-middle whitespace-nowrap">
                                        <a href="{{ route('admin.coins.ledger.type', [$member, 'referral']) }}" class="chip px-2.5 py-1 text-xs font-semibold t2 hover:t1 no-underline inline-block" target="_blank" rel="noopener">{{ number_format($referralCount) }}</a>
                                    </td>
                                    <td class="px-3 py-2.5 text-center align-middle whitespace-nowrap">
                                        <a href="{{ route('admin.coins.ledger.type', [$member, 'business_deal']) }}" class="chip px-2.5 py-1 text-xs font-semibold t2 hover:t1 no-underline inline-block" target="_blank" rel="noopener">{{ number_format($businessDealCount) }}</a>
                                    </td>
                                    <td class="px-3 py-2.5 text-center align-middle whitespace-nowrap">
                                        <a href="{{ route('admin.coins.ledger.type', [$member, 'p2p_meeting']) }}" class="chip px-2.5 py-1 text-xs font-semibold t2 hover:t1 no-underline inline-block" target="_blank" rel="noopener">{{ number_format($p2pMeetingCount) }}</a>
                                    </td>
                                    <td class="px-3 py-2.5 text-center align-middle whitespace-nowrap">
                                        <a href="{{ route('admin.coins.ledger.type', [$member, 'requirement']) }}" class="chip px-2.5 py-1 text-xs font-semibold t2 hover:t1 no-underline inline-block" target="_blank" rel="noopener">{{ number_format($requirementCount) }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-8 text-xs t3">No members found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                    {{ $members->links() }}
                </div>
            </div>
        </form>
        <form id="coinsExportForm" method="GET" action="{{ route('admin.coins.export') }}" class="d-none"></form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const perPage = document.getElementById('perPage');
                const form = document.getElementById('coinsFiltersForm');
                const exportForm = document.getElementById('coinsExportForm');
                const exportBtn = document.getElementById('coinsExportBtn');

                if (perPage && form) {
                    perPage.addEventListener('change', function () {
                        form.submit();
                    });
                }

                const submitOnEnter = function (event) {
                    if (event.key === 'Enter' && form) {
                        event.preventDefault();
                        form.submit();
                    }
                };

                const enterSubmitFields = [
                    document.getElementById('coinsQ'),
                    document.getElementById('coinsCircle'),
                ];

                enterSubmitFields.forEach(function (field) {
                    if (!field) {
                        return;
                    }

                    field.addEventListener('keydown', submitOnEnter);
                });

                const appendHiddenInput = function (targetForm, name, value) {
                    if (value === null || value === undefined || value === '') {
                        return;
                    }

                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    targetForm.appendChild(input);
                };

                if (exportBtn && exportForm) {
                    exportBtn.addEventListener('click', function (event) {
                        event.preventDefault();

                        exportForm.innerHTML = '';

                        const searchValue = document.getElementById('coinsQ')?.value ?? '';
                        const circleValue = document.getElementById('coinsCircle')?.value ?? 'all';
                        const perPageValue = document.getElementById('perPage')?.value ?? '20';

                        appendHiddenInput(exportForm, 'q', searchValue);
                        appendHiddenInput(exportForm, 'circle_id', circleValue);
                        appendHiddenInput(exportForm, 'per_page', perPageValue);

                        exportForm.submit();
                    });
                }
            });
        </script>
    @endpush
@endsection

