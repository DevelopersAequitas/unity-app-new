@extends('admin.layouts.app')

@section('title', 'Coins')

@include('admin.partials.grid-head')

@section('content')
    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">
        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
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
                <a href="{{ route('admin.coins.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1">
                    ➕ Add Coins
                </a>
            </div>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 280px;">Peer Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width: 110px;">Total Coins</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width: 110px;">Testimonials</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width: 110px;">Referrals</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width: 130px;">Business Deals</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width: 130px;">P2P Meetings</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width: 130px;">Requirements</th>
                        </tr>

                        <tr class="surface-2 border-b bs align-middle">
                            <th class="px-3 py-2">
                                <div class="flex flex-col gap-1.5">
                                    <input
                                        id="coinsQ"
                                        type="text"
                                        name="q"
                                        form="coinsFiltersForm"
                                        class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal"
                                        placeholder="Peer / Company / City"
                                        value="{{ $filters['q'] }}"
                                    >
                                    <select id="coinsCircle" name="circle_id" form="coinsFiltersForm" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal">
                                        <option value="all">All Circles</option>
                                        @foreach ($circles as $circle)
                                            <option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? 'all') == $circle->id)>{{ $circle->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </th>
                            <th class="text-center t3 text-xs">-</th>
                            <th class="text-center t3 text-xs">-</th>
                            <th class="text-center t3 text-xs">-</th>
                            <th class="text-center t3 text-xs">-</th>
                            <th class="text-center t3 text-xs">-</th>
                            <th class="px-3 py-2 text-right">
                                <form id="coinsFiltersForm" method="GET" class="flex items-center justify-end gap-1.5 flex-nowrap">
                                    <a href="{{ route('admin.coins.index') }}" class="px-3 py-1 rounded-md border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline">Clear</a>
                                    <button type="button" id="coinsExportBtn" class="px-3 py-1 rounded-md border bs text-xs font-semibold text-indigo-600 hover:text-indigo-700 surface-2 transition">Export</button>
                                </form>
                                <form id="coinsExportForm" method="GET" action="{{ route('admin.coins.export') }}" class="d-none"></form>
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
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5">
                                    @include('admin.shared.peer_card', ['user' => $member])
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <a href="{{ route('admin.coins.ledger', $member) }}" class="chip px-2.5 py-1 text-xs font-semibold text-indigo-600 hover:text-indigo-700 no-underline" target="_blank" rel="noopener">{{ $totalCoins }}</a>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <a href="{{ route('admin.coins.ledger.type', [$member, 'testimonial']) }}" class="chip px-2.5 py-1 text-xs font-semibold t2 hover:t1 no-underline" target="_blank" rel="noopener">{{ $testimonialCount }}</a>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <a href="{{ route('admin.coins.ledger.type', [$member, 'referral']) }}" class="chip px-2.5 py-1 text-xs font-semibold t2 hover:t1 no-underline" target="_blank" rel="noopener">{{ $referralCount }}</a>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <a href="{{ route('admin.coins.ledger.type', [$member, 'business_deal']) }}" class="chip px-2.5 py-1 text-xs font-semibold t2 hover:t1 no-underline" target="_blank" rel="noopener">{{ $businessDealCount }}</a>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <a href="{{ route('admin.coins.ledger.type', [$member, 'p2p_meeting']) }}" class="chip px-2.5 py-1 text-xs font-semibold t2 hover:t1 no-underline" target="_blank" rel="noopener">{{ $p2pMeetingCount }}</a>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <a href="{{ route('admin.coins.ledger.type', [$member, 'requirement']) }}" class="chip px-2.5 py-1 text-xs font-semibold t2 hover:t1 no-underline" target="_blank" rel="noopener">{{ $requirementCount }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-8 text-xs t3">No members found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $members->links() }}
            </div>
        </div>
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

