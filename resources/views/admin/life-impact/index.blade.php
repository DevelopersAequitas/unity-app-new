@extends('admin.layouts.app')

@section('title', 'Life Impact')

@include('admin.partials.grid-head')

@push('styles')
<style>
.life-impact-kpi-card {
    border-radius: 16px;
    padding: 16px 18px;
    transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    cursor: pointer;
    text-decoration: none !important;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 104px;
    overflow: hidden;
}

.life-impact-kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: transparent;
    transition: all 0.2s ease;
}

.life-impact-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 4px 10px -2px rgba(15, 23, 42, 0.04);
}

/* Card Themes & Active States */
.card-theme-indigo:hover::before, .card-theme-indigo.active-filter::before { background: #6366f1; }
.card-theme-indigo.active-filter { border-color: #6366f1 !important; box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.3); background: linear-gradient(180deg, rgba(99, 102, 241, 0.06) 0%, #ffffff 100%); }

.card-theme-emerald:hover::before, .card-theme-emerald.active-filter::before { background: #10b981; }
.card-theme-emerald.active-filter { border-color: #10b981 !important; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3); background: linear-gradient(180deg, rgba(16, 185, 129, 0.06) 0%, #ffffff 100%); }

.card-theme-sky:hover::before, .card-theme-sky.active-filter::before { background: #0ea5e9; }
.card-theme-sky.active-filter { border-color: #0ea5e9 !important; box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.3); background: linear-gradient(180deg, rgba(14, 165, 233, 0.06) 0%, #ffffff 100%); }

.card-theme-amber:hover::before, .card-theme-amber.active-filter::before { background: #f59e0b; }
.card-theme-amber.active-filter { border-color: #f59e0b !important; box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.3); background: linear-gradient(180deg, rgba(245, 158, 11, 0.06) 0%, #ffffff 100%); }

.card-theme-purple:hover::before, .card-theme-purple.active-filter::before { background: #a855f7; }
.card-theme-purple.active-filter { border-color: #a855f7 !important; box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.3); background: linear-gradient(180deg, rgba(168, 85, 247, 0.06) 0%, #ffffff 100%); }

.active-column-highlight {
    background-color: rgba(99, 102, 241, 0.04) !important;
}
</style>
@endpush

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

        $activeCategory = $filters['category'] ?? 'all';

        $summaryCards = [
            [
                'key' => 'all',
                'label' => 'Total Life Impacted',
                'value' => $summary['total_life_impacted'] ?? 0,
                'color' => 'indigo',
                'theme' => 'card-theme-indigo',
                'text_color' => 'text-indigo-600',
                'icon' => 'bi-heart-pulse-fill',
            ],
            [
                'key' => 'business_deals',
                'label' => 'Business Deals',
                'value' => $summary['business_deals'] ?? 0,
                'color' => 'emerald',
                'theme' => 'card-theme-emerald',
                'text_color' => 'text-emerald-600',
                'icon' => 'bi-briefcase-fill',
            ],
            [
                'key' => 'referrals',
                'label' => 'Referrals',
                'value' => $summary['referrals'] ?? 0,
                'color' => 'sky',
                'theme' => 'card-theme-sky',
                'text_color' => 'text-sky-600',
                'icon' => 'bi-people-fill',
            ],
            [
                'key' => 'testimonials',
                'label' => 'Testimonials',
                'value' => $summary['testimonials'] ?? 0,
                'color' => 'amber',
                'theme' => 'card-theme-amber',
                'text_color' => 'text-amber-600',
                'icon' => 'bi-chat-square-quote-fill',
            ],
            [
                'key' => 'other',
                'label' => 'Other Impact Activities',
                'value' => $summary['other_impact_activities'] ?? 0,
                'color' => 'purple',
                'theme' => 'card-theme-purple',
                'text_color' => 'text-purple-600',
                'icon' => 'bi-grid-fill',
            ],
        ];

        $currentActiveCard = collect($summaryCards)->first(function ($card) use ($activeCategory) {
            return ($activeCategory === $card['key']) || ($card['key'] === 'all' && in_array($activeCategory, ['all', 'total_life_impacted'], true));
        });
    @endphp

    {{-- Page Top Header & Tab Navigation --}}
    <div class="flex items-center justify-between flex-wrap gap-4 mb-4">
        <div>
            <h4 class="font-display font-bold text-lg t1 m-0 flex items-center gap-2">
                <i class="bi bi-heart-pulse-fill text-rose-500"></i>Life Impact
            </h4>
            <p class="text-xs t3 m-0 mt-0.5">Track peer community contributions, business deals, referrals, and overall life impact metrics.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.life-impact.index') }}" class="tab-pill px-4 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-2 transition no-underline bg-indigo-600 text-white shadow-md">
                <i class="bi bi-bar-chart-line-fill"></i>Life Impact Overview
            </a>
            <a href="{{ route('admin.life-impact-recognitions.index') }}" class="tab-pill px-4 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-2 transition no-underline surface border bs t2 hover:t1 hover:surface-2">
                <i class="bi bi-people-fill"></i>Life Impact List
            </a>
            <a href="{{ route('admin.life-impact-recognitions.index', ['tab' => 'creative']) }}" class="tab-pill px-4 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-2 transition no-underline surface border bs t2 hover:t1 hover:surface-2">
                <i class="bi bi-stars"></i>Creative
            </a>
        </div>
    </div>

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-6">

        {{-- Interactive Summary Cards Grid (Click to filter) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
            @foreach ($summaryCards as $card)
                @php
                    $isCardActive = ($activeCategory === $card['key']) || ($card['key'] === 'all' && in_array($activeCategory, ['all', 'total_life_impacted'], true));
                    $targetCategory = ($isCardActive && $card['key'] !== 'all') ? 'all' : $card['key'];
                    $filterParams = array_filter(array_merge($filters, [
                        'category' => $targetCategory,
                    ]), fn ($value) => filled($value) && $value !== 'all');
                @endphp
                <a
                    href="{{ route('admin.life-impact.index', $filterParams) }}"
                    class="life-impact-kpi-card {{ $card['theme'] }} {{ $isCardActive ? 'active-filter' : 'border bs surface' }} group"
                    title="Click to filter by {{ $card['label'] }}"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] t3 uppercase font-bold tracking-wider group-hover:t1 transition-colors">
                            {{ $card['label'] }}
                        </span>
                        @if($isCardActive && $card['key'] !== 'all')
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-900 text-white shadow-sm">
                                <i class="bi bi-check2"></i> Active
                            </span>
                        @endif
                    </div>
                    <div class="text-2xl font-black font-display {{ $card['text_color'] }} my-1 tracking-tight">
                        {{ number_format((int) $card['value']) }}
                    </div>
                    <div class="text-[11px] t3 flex items-center gap-1.5 font-medium">
                        <i class="bi {{ $card['icon'] }} {{ $card['text_color'] }}"></i>
                        <span>{{ $isCardActive ? 'Viewing ' . strtolower($card['label']) : 'Click to filter' }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        <form id="lifeImpactFiltersForm" method="GET" action="{{ route('admin.life-impact.index') }}" class="admin-filter-form space-y-4">
            <input type="hidden" name="category" value="{{ $filters['category'] ?? 'all' }}">

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
                                @php
                                    $quickParams = array_filter(array_merge($filters, [
                                        'quick_date' => $key,
                                    ]), fn ($value) => filled($value) && $value !== 'all');
                                @endphp
                                <a
                                    href="{{ route('admin.life-impact.index', $quickParams) }}"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold no-underline transition {{ ($filters['quick_date'] ?? '') === $key ? 'bg-indigo-600 text-white' : 'border bs surface t2 hover:t1' }}"
                                >{{ $range['label'] }}</a>
                            @endforeach
                        </div>
                        <div class="flex gap-2">
                            <button type="button" onclick="clearAdminFilters(event, 'lifeImpactFiltersForm')" class="px-3 py-1 rounded-md border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition">Clear</button>
                            <button type="button" class="px-3 py-1 rounded-md border bs text-xs font-semibold text-indigo-600 hover:text-indigo-700 surface-2 transition js-life-impact-export">Export</button>
                        </div>
                    </div>
                    <div class="text-xs t3 flex items-center gap-2 flex-wrap">
                        @if(!empty($activeCategory) && !in_array($activeCategory, ['all', 'total_life_impacted'], true))
                            @php
                                $clearCatParams = array_filter(array_merge($filters, ['category' => 'all']), fn ($v) => filled($v) && $v !== 'all');
                            @endphp
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                <span>Filter: <strong>{{ $currentActiveCard['label'] ?? ucfirst($activeCategory) }}</strong></span>
                                <a href="{{ route('admin.life-impact.index', $clearCatParams) }}" class="text-indigo-600 hover:text-indigo-900 transition-colors" title="Remove category filter">
                                    <i class="bi bi-x-circle-fill"></i>
                                </a>
                            </span>
                        @endif

                        @if($members->total() > 0)
                            <span>Showing <span class="font-semibold t1">{{ $members->firstItem() }}-{{ $members->lastItem() }}</span> of <span class="font-semibold t1">{{ $members->total() }}</span> records</span>
                        @else
                            <span>No records found</span>
                        @endif
                        @if($dateFilterActive)
                            <span class="chip px-2 py-0.5 text-[10px] font-semibold bg-amber-50 text-amber-700 border-amber-200">Date filtered</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="rounded-xl border bs surface overflow-hidden">
                <div class="overflow-x-auto relative w-full">
                    <table class="w-full min-w-[1100px] border-collapse text-[13px]">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left sticky left-0 z-10" style="min-width: 180px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">Peer Name</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 160px;">Company</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 110px;">City</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 140px;">Circle</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-center {{ in_array($activeCategory, ['all', 'total_life_impacted'], true) ? 'active-column-highlight' : '' }}" style="min-width: 130px;">
                                    Total Life Impacted
                                </th>
                                @foreach ($categories as $catKey => $category)
                                    @php
                                        $isThisCatActive = ($activeCategory === $catKey);
                                    @endphp
                                    <th class="th-cell surface-2 border-b bs px-3 py-2 text-center {{ $isThisCatActive ? 'active-column-highlight font-bold text-indigo-600' : '' }}" style="min-width: 100px;">
                                        {{ $category['label'] }}
                                    </th>
                                @endforeach
                            </tr>

                            <tr class="surface-2 border-b bs align-middle filter-row">
                                <th class="px-3 py-2 text-left sticky left-0 z-10 surface-2" style="min-width: 180px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">
                                    <input
                                        id="lifeImpactQ"
                                        type="text"
                                        name="q"
                                        class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal"
                                        placeholder="Search peer, company, city"
                                        value="{{ $filters['q'] }}"
                                    >
                                </th>
                                <th class="px-3 py-2" style="min-width: 160px;"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" disabled placeholder="-"></th>
                                <th class="px-3 py-2" style="min-width: 110px;"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" disabled placeholder="-"></th>
                                <th class="px-3 py-2 text-left" style="min-width: 140px;">
                                    <select id="lifeImpactCircle" name="circle_id" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal js-searchable-select" data-placeholder="All Circles">
                                        <option value="all">All Circles</option>
                                        @foreach ($circles as $circle)
                                            <option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? 'all') == $circle->id)>{{ $circle->name }}</option>
                                        @endforeach
                                    </select>
                                </th>
                                <th class="text-center t3 text-xs {{ in_array($activeCategory, ['all', 'total_life_impacted'], true) ? 'active-column-highlight' : '' }}" style="min-width: 130px;">-</th>
                                @foreach (array_keys($categories) as $catKey)
                                    @php
                                        $isThisCatActive = ($activeCategory === $catKey);
                                    @endphp
                                    <th class="text-center t3 text-xs {{ $isThisCatActive ? 'active-column-highlight' : '' }}" style="min-width: 100px;">-</th>
                                @endforeach
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
                                            <div class="flex items-center gap-2 whitespace-nowrap">
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
                                    <td class="px-3 py-2.5 text-xs t2 align-middle" style="min-width: 160px;"><x-admin-grid-text :text="$company" :lines="2" /></td>
                                    <td class="px-3 py-2.5 text-xs t2 align-middle" style="min-width: 110px;"><x-admin-grid-text :text="$city" :lines="2" /></td>
                                    <td class="px-3 py-2.5 text-xs t2 align-middle" style="min-width: 140px;"><x-admin-grid-text :text="$circleName" :lines="2" /></td>
                                    <td class="px-3 py-2.5 text-center align-middle whitespace-nowrap {{ in_array($activeCategory, ['all', 'total_life_impacted'], true) ? 'active-column-highlight' : '' }}" style="min-width: 130px;">
                                        <a href="{{ route('admin.life-impact.history', $member) . $historyQueryString }}" class="chip px-2.5 py-1 text-xs font-semibold text-indigo-600 hover:text-indigo-700 no-underline inline-block" target="_blank" rel="noopener">{{ number_format($totalLifeImpacted) }}</a>
                                    </td>
                                    @foreach (array_keys($categories) as $key)
                                        @php
                                            $isThisCatActive = ($activeCategory === $key);
                                            $val = (int) ($stats[$key] ?? 0);
                                        @endphp
                                        <td class="px-3 py-2.5 text-center align-middle whitespace-nowrap {{ $isThisCatActive ? 'active-column-highlight' : '' }}" style="min-width: 100px;">
                                            <a href="{{ route('admin.life-impact.history.category', [$member, $key]) . $historyQueryString }}" class="chip px-2.5 py-1 text-xs font-semibold {{ $isThisCatActive ? 'text-indigo-700 font-bold bg-indigo-50 border-indigo-200' : 't2 hover:t1' }} no-underline inline-block" target="_blank" rel="noopener">
                                                {{ number_format($val) }}
                                            </a>
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($categories) + 5 }}" class="text-center py-8 text-xs t3">No members found matching this filter criteria.</td>
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
                            appendHiddenInput(exportForm, 'category', form?.querySelector('input[name="category"]')?.value ?? 'all');
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
