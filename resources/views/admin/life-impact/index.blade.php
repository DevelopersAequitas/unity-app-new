@extends('admin.layouts.app')

@section('title', 'Life Impact')

@include('admin.partials.grid-head')

@section('content')
    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

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

        $historyDateParams = array_filter([
            'from' => $filters['from'] ?? '',
            'to' => $filters['to'] ?? '',
        ], fn ($value) => filled($value));
        $historyQueryString = $historyDateParams ? '?' . http_build_query($historyDateParams) : '';
        $summaryCards = [
            ['label' => 'Total Life Impacted', 'value' => $summary['total_life_impacted'] ?? 0, 'color' => 'indigo'],
            ['label' => 'Business Deals', 'value' => $summary['business_deals'] ?? 0, 'color' => 'emerald'],
            ['label' => 'Referrals', 'value' => $summary['referrals'] ?? 0, 'color' => 'blue'],
            ['label' => 'Testimonials', 'value' => $summary['testimonials'] ?? 0, 'color' => 'amber'],
            ['label' => 'Other Impact Activities', 'value' => $summary['other_impact_activities'] ?? 0, 'color' => 'purple'],
        ];
    @endphp

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-6">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Life Impact Overview</h2>
            <p class="text-xs t3 m-0 mt-0.5">Track peer community contributions, business deals, referrals, and overall life impact metrics.</p>
        </div>

        {{-- Summary Cards Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            @foreach ($summaryCards as $card)
                <div class="rounded-xl border bs surface p-3.5 space-y-1">
                    <div class="text-[11px] t3 uppercase font-semibold tracking-wider">{{ $card['label'] }}</div>
                    <div class="text-xl font-bold font-display text-{{ $card['color'] }}-600">{{ number_format((int) $card['value']) }}</div>
                </div>
            @endforeach
        </div>

        <form id="lifeImpactFiltersForm" method="GET" action="{{ route('admin.life-impact.index') }}" class="admin-filter-form space-y-4">
            <div class="border bs rounded-xl p-3.5 surface-2">
                <div class="flex flex-wrap justify-between items-center gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-1.5">
                            <label for="perPage" class="text-xs t3 m-0 font-medium">Rows per page:</label>
                            <select id="perPage" name="per_page" class="px-2.5 py-1 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                                @foreach ([10, 20, 25, 50, 100] as $size)
                                    <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="date" id="lifeImpactFrom" name="from" value="{{ $filters['from'] ?? '' }}" class="px-2.5 py-1 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                            <span class="t3 text-xs">to</span>
                            <input type="date" id="lifeImpactTo" name="to" value="{{ $filters['to'] ?? '' }}" class="px-2.5 py-1 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                        </div>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($quickDateRanges as $key => $range)
                                <a
                                    href="{{ route('admin.life-impact.index', array_filter(['q' => $filters['q'] ?? '', 'circle_id' => $filters['circle_id'] ?? 'all', 'per_page' => $filters['per_page'] ?? 20, 'quick_date' => $key])) }}"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold no-underline transition {{ ($filters['quick_date'] ?? '') === $key ? 'bg-indigo-600 text-white' : 'border bs surface t2 hover:t1' }}"
                                >{{ $range['label'] }}</a>
                            @endforeach
                        </div>
                        <div class="flex gap-2">
                            <button type="button" onclick="clearAdminFilters(event, 'lifeImpactFiltersForm')" class="px-3 py-1 rounded-md border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition">Clear</button>
                            <button type="button" class="px-3 py-1 rounded-md border bs text-xs font-semibold text-indigo-600 hover:text-indigo-700 surface-2 transition js-life-impact-export">Export</button>
                        </div>
                    </div>
                    <div class="text-xs t3">
                        @if($members->total() > 0)
                            Showing <span class="font-semibold t1">{{ $members->firstItem() }}-{{ $members->lastItem() }}</span> of <span class="font-semibold t1">{{ $members->total() }}</span> records
                        @else
                            No records found
                        @endif
                        @if($dateFilterActive)
                            <span class="chip px-2 py-0.5 text-[10px] font-semibold bg-amber-50 text-amber-700 border-amber-200 ml-1">Date filtered</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="rounded-xl border bs surface overflow-hidden">
                <div class="overflow-x-auto relative w-full">
                    <table class="w-full min-w-[900px] border-collapse text-[13px]">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left sticky left-0 z-10" style="min-width: 180px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">Peer Name</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Circle</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width: 140px;">Total Life Impacted</th>
                                @foreach ($categories as $category)
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width: 120px;">
                                        {{ $category['label'] }}
                                    </th>
                                @endforeach
                            </tr>

                            <tr class="surface-2 border-b bs align-middle filter-row">
                                <th class="px-3 py-2 text-left sticky left-0 z-10 surface-2" style="box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">
                                    <input
                                        id="lifeImpactQ"
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
                                    <select id="lifeImpactCircle" name="circle_id" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal js-searchable-select" data-placeholder="All Circles">
                                        <option value="all">All Circles</option>
                                        @foreach ($circles as $circle)
                                            <option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? 'all') == $circle->id)>{{ $circle->name }}</option>
                                        @endforeach
                                    </select>
                                </th>
                                <th class="text-center t3 text-xs">-</th>
                                @foreach (range(1, count($categories) - 1) as $index)
                                    <th class="text-center t3 text-xs">-</th>
                                @endforeach
                                <th class="px-3 py-2 text-center">
                                    <button type="button" onclick="clearAdminFilters(event, 'lifeImpactFiltersForm')" class="px-3 py-1 rounded-md border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition">Clear</button>
                                </th>
                            </tr>
                        </thead>

                        <tbody id="grid-body" class="divide-y divide-gray-200/50">
                            @forelse ($members as $member)
                                @php
                                    $stats = $impactStats[(string) $member->id] ?? [];
                                    $totalLifeImpacted = $dateFilterActive
                                        ? (int) ($stats['total_life_impacted'] ?? 0)
                                        : (int) ($member->life_impacted_count ?? 0);
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
                                        <a href="{{ route('admin.life-impact.history', $member) . $historyQueryString }}" class="chip px-2.5 py-1 text-xs font-semibold text-indigo-600 hover:text-indigo-700 no-underline inline-block" target="_blank" rel="noopener">{{ number_format($totalLifeImpacted) }}</a>
                                    </td>
                                    @foreach (array_keys($categories) as $key)
                                        <td class="px-3 py-2.5 text-center align-middle whitespace-nowrap">
                                            <a href="{{ route('admin.life-impact.history.category', [$member, $key]) . $historyQueryString }}" class="chip px-2.5 py-1 text-xs font-semibold t2 hover:t1 no-underline inline-block" target="_blank" rel="noopener">{{ number_format((int) ($stats[$key] ?? 0)) }}</a>
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($categories) + 5 }}" class="text-center py-8 text-xs t3">No members found.</td>
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
        <form id="lifeImpactExportForm" method="GET" action="{{ route('admin.life-impact.export') }}" class="d-none"></form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const perPage = document.getElementById('perPage');
                const form = document.getElementById('lifeImpactFiltersForm');
                const exportForm = document.getElementById('lifeImpactExportForm');
                const exportBtns = document.querySelectorAll('.js-life-impact-export');

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

                [document.getElementById('lifeImpactQ'), document.getElementById('lifeImpactCircle')].forEach(function (field) {
                    if (field) {
                        field.addEventListener('keydown', submitOnEnter);
                    }
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

                if (exportBtns.length && exportForm) {
                    exportBtns.forEach(function (exportBtn) {
                        exportBtn.addEventListener('click', function (event) {
                        event.preventDefault();
                        exportForm.innerHTML = '';

                        appendHiddenInput(exportForm, 'q', document.getElementById('lifeImpactQ')?.value ?? '');
                        appendHiddenInput(exportForm, 'circle_id', document.getElementById('lifeImpactCircle')?.value ?? 'all');
                        appendHiddenInput(exportForm, 'from', document.getElementById('lifeImpactFrom')?.value ?? '');
                        appendHiddenInput(exportForm, 'to', document.getElementById('lifeImpactTo')?.value ?? '');

                        exportForm.submit();
                        });
                    });
                }
            });
        </script>
    @endpush
@endsection

