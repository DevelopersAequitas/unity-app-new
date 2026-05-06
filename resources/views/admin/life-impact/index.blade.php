@extends('admin.layouts.app')

@section('title', 'Life Impact')

@push('styles')
    <style>
        .life-impact-table-wrapper {
            width: 100%;
        }

        .life-impact-table-scroll {
            width: 100%;
            overflow-x: auto;
            overflow-y: visible;
        }

        .life-impact-table {
            width: max-content;
            min-width: 1900px;
        }

        .life-impact-table thead th {
            white-space: nowrap;
            word-break: keep-all;
        }

        .life-impact-filter-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
            white-space: nowrap;
        }

        .life-impact-filter-actions .btn {
            flex: 0 0 auto;
        }
    </style>
@endpush

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @php
        $historyDateParams = array_filter([
            'from' => $filters['from'] ?? '',
            'to' => $filters['to'] ?? '',
        ], fn ($value) => filled($value));
        $historyQueryString = $historyDateParams ? '?' . http_build_query($historyDateParams) : '';
        $summaryCards = [
            ['label' => 'Total Life Impacted', 'value' => $summary['total_life_impacted'] ?? 0, 'class' => 'text-primary'],
            ['label' => 'Business Deals', 'value' => $summary['business_deals'] ?? 0, 'class' => 'text-success'],
            ['label' => 'Referrals', 'value' => $summary['referrals'] ?? 0, 'class' => 'text-info'],
            ['label' => 'Testimonials', 'value' => $summary['testimonials'] ?? 0, 'class' => 'text-warning'],
            ['label' => 'Other Impact Activities', 'value' => $summary['other_impact_activities'] ?? 0, 'class' => 'text-secondary'],
        ];
    @endphp

    <div class="row g-3 mb-3">
        @foreach ($summaryCards as $card)
            <div class="col-12 col-sm-6 col-lg">
                <div class="card shadow-sm h-100">
                    <div class="card-body py-3">
                        <div class="small text-muted">{{ $card['label'] }}</div>
                        <div class="h4 mb-0 {{ $card['class'] }}">{{ number_format((int) $card['value']) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card shadow-sm">
        <div class="d-flex flex-wrap justify-content-between align-items-end p-3 gap-3 border-bottom">
            <div class="d-flex flex-wrap align-items-end gap-2">
                <div>
                    <label for="perPage" class="form-label mb-1 small text-muted">Rows per page</label>
                    <select id="perPage" name="per_page" form="lifeImpactFiltersForm" class="form-select form-select-sm" style="width: 90px;">
                        @foreach ([10, 20, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label mb-1 small text-muted">From Date</label>
                    <input type="date" id="lifeImpactFrom" name="from" form="lifeImpactFiltersForm" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div>
                    <label class="form-label mb-1 small text-muted">To Date</label>
                    <input type="date" id="lifeImpactTo" name="to" form="lifeImpactFiltersForm" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
                </div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach ($quickDateRanges as $key => $range)
                        <a
                            href="{{ route('admin.life-impact.index', array_filter(['q' => $filters['q'] ?? '', 'circle_id' => $filters['circle_id'] ?? 'all', 'per_page' => $filters['per_page'] ?? 20, 'quick_date' => $key])) }}"
                            class="btn btn-sm {{ ($filters['quick_date'] ?? '') === $key ? 'btn-primary' : 'btn-outline-primary' }}"
                        >{{ $range['label'] }}</a>
                    @endforeach
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" form="lifeImpactFiltersForm" class="btn btn-sm btn-primary">Apply</button>
                    <a href="{{ route('admin.life-impact.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    <button type="button" class="btn btn-sm btn-outline-primary js-life-impact-export">Export</button>
                </div>
            </div>
            <div class="small text-muted">
                @if($members->total() > 0)
                    Records {{ $members->firstItem() }} to {{ $members->lastItem() }} of {{ $members->total() }}
                @else
                    No records found
                @endif
                @if($dateFilterActive)
                    <span class="badge bg-light text-dark border ms-2">Date filtered</span>
                @endif
            </div>
        </div>

        <div class="life-impact-table-wrapper">
            <div class="life-impact-table-scroll">
                <table class="table mb-0 align-middle table-hover life-impact-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 300px; min-width: 300px;">Peer Name</th>
                            <th class="text-center" style="width: 150px; min-width: 150px;"><span class="d-inline-block">Total Life<br>Impacted</span></th>
                            @foreach ($categories as $category)
                                <th class="text-center" style="width: 130px; min-width: 130px;">
                                    <span class="d-inline-block">{!! str_replace(' ', '<br>', e($category['label'])) !!}</span>
                                </th>
                            @endforeach
                        </tr>

                        <tr class="align-middle">
                            <th>
                                <div class="d-flex flex-column gap-2">
                                    <input
                                        id="lifeImpactQ"
                                        type="text"
                                        name="q"
                                        form="lifeImpactFiltersForm"
                                        class="form-control form-control-sm"
                                        placeholder="Peer/Company/City"
                                        value="{{ $filters['q'] }}"
                                    >
                                    <select id="lifeImpactCircle" name="circle_id" form="lifeImpactFiltersForm" class="form-select form-select-sm js-searchable-select" data-placeholder="All Circles">
                                        <option value="all">All Circles</option>
                                        @foreach ($circles as $circle)
                                            <option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? 'all') == $circle->id)>{{ $circle->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </th>
                            @foreach (range(1, count($categories)) as $index)
                                <th class="text-center text-muted small">—</th>
                            @endforeach
                            <th class="text-end">
                                <form id="lifeImpactFiltersForm" method="GET" class="life-impact-filter-actions justify-content-end">
                                    <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                    <a href="{{ route('admin.life-impact.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                                    <button type="button" id="lifeImpactExportBtn" class="btn btn-sm btn-outline-primary js-life-impact-export">Export</button>
                                </form>
                                <form id="lifeImpactExportForm" method="GET" action="{{ route('admin.life-impact.export') }}" class="d-none"></form>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($members as $member)
                            @php
                                $stats = $impactStats[(string) $member->id] ?? [];
                                $totalLifeImpacted = $dateFilterActive
                                    ? (int) ($stats['total_life_impacted'] ?? 0)
                                    : (int) ($member->life_impacted_count ?? 0);
                            @endphp
                            <tr>
                                <td>
                                    @include('admin.shared.peer_card', ['user' => $member])
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('admin.life-impact.history', $member) . $historyQueryString }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ number_format($totalLifeImpacted) }}</a>
                                </td>
                                @foreach (array_keys($categories) as $key)
                                    <td class="text-center">
                                        <a href="{{ route('admin.life-impact.history.category', [$member, $key]) . $historyQueryString }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">{{ number_format((int) ($stats[$key] ?? 0)) }}</a>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($categories) + 2 }}" class="text-center text-muted py-4">No members found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="p-3">
            {{ $members->links() }}
        </div>
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
