@extends('admin.layouts.app')

@section('title', 'Coins')

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="d-flex flex-wrap justify-content-between align-items-center p-3 gap-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <label for="perPage" class="form-label mb-0 small text-muted">Rows per page:</label>
                <select id="perPage" name="per_page" form="coinsFiltersForm" class="form-select form-select-sm" style="width: 90px;">
                    @foreach ([10, 20, 25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <a href="{{ route('admin.coins.create') }}" class="btn btn-sm btn-primary">Add Coins</a>
        </div>

        <div class="table-responsive">
            <table class="table mb-0 align-middle table-hover" style="table-layout: fixed; width: 100%;">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 44px;">
                            <input type="checkbox" class="form-check-input" id="coins-select-all">
                        </th>
                        <th style="width: 28%;">Peer Name</th>
                        <th class="text-center" style="width: 10%;">Total Coins</th>
                        <th class="text-center" style="width: 10%;">Testimonials</th>
                        <th class="text-center" style="width: 10%;">Referrals</th>
                        <th class="text-center" style="width: 10%;">Business Deals</th>
                        <th class="text-center" style="width: 10%;">P2P Meetings</th>
                        <th class="text-center" style="width: 10%;">Requirements</th>
                        <th class="text-end" style="width: 220px;">Actions</th>
                    </tr>

                    <tr class="align-middle">
                        <th></th>
                        <th>
                            <div class="d-flex flex-column gap-2">
                                <input
                                    id="coinsQ"
                                    type="text"
                                    name="q"
                                    form="coinsFiltersForm"
                                    class="form-control form-control-sm"
                                    placeholder="Peer/Company/City"
                                    value="{{ $filters['q'] }}"
                                >
                                <select id="coinsCircle" name="circle_id" form="coinsFiltersForm" class="form-select form-select-sm">
                                    <option value="all">All Circles</option>
                                    @foreach ($circles as $circle)
                                        <option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? 'all') == $circle->id)>{{ $circle->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </th>
                        <th class="text-center text-muted small">—</th>
                        <th class="text-center text-muted small">—</th>
                        <th class="text-center text-muted small">—</th>
                        <th class="text-center text-muted small">—</th>
                        <th class="text-center text-muted small">—</th>
                        <th class="text-center text-muted small">—</th>
                        <th class="text-end">
                            <form id="coinsFiltersForm" method="GET" class="d-inline-flex flex-wrap justify-content-end gap-2">
                                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                                <a href="{{ route('admin.coins.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                                <button type="button" id="coinsExportBtn" class="btn btn-sm btn-outline-primary">Export</button>
                            </form>
                            <form id="coinsExportForm" method="GET" action="{{ route('admin.coins.export') }}" class="d-none"></form>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($members as $member)
                        @php
                            $stats = $activityStats[$member->id] ?? null;
                            $totalCoins = (int) ($stats->total_coins ?? 0);
                            $testimonialCount = (int) ($stats->testimonial_count ?? 0);
                            $referralCount = (int) ($stats->referral_count ?? 0);
                            $businessDealCount = (int) ($stats->business_deal_count ?? 0);
                            $p2pMeetingCount = (int) ($stats->p2p_meeting_count ?? 0);
                            $requirementCount = (int) ($stats->requirement_count ?? 0);
                        @endphp
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input coins-row-checkbox" value="{{ $member->id }}">
                            </td>
                            <td>
                                @include('admin.shared.peer_card', ['user' => $member])
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.coins.ledger', $member) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $totalCoins }}</a>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.coins.ledger.type', [$member, 'testimonial']) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $testimonialCount }}</a>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.coins.ledger.type', [$member, 'referral']) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $referralCount }}</a>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.coins.ledger.type', [$member, 'business_deal']) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $businessDealCount }}</a>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.coins.ledger.type', [$member, 'p2p_meeting']) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $p2pMeetingCount }}</a>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.coins.ledger.type', [$member, 'requirement']) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">{{ $requirementCount }}</a>
                            </td>
                            <td class="text-end text-muted">—</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No members found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3">
            {{ $members->links() }}
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const perPage = document.getElementById('perPage');
                const form = document.getElementById('coinsFiltersForm');
                const exportForm = document.getElementById('coinsExportForm');
                const exportBtn = document.getElementById('coinsExportBtn');
                const selectAll = document.getElementById('coins-select-all');
                const rowCheckboxes = document.querySelectorAll('.coins-row-checkbox');

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

                if (form) {
                    form.querySelectorAll('input, select').forEach(function (field) {
                        field.addEventListener('keydown', submitOnEnter);
                    });
                }

                if (selectAll) {
                    selectAll.addEventListener('change', function () {
                        rowCheckboxes.forEach(function (checkbox) {
                            checkbox.checked = selectAll.checked;
                        });
                    });
                }

                rowCheckboxes.forEach(function (checkbox) {
                    checkbox.addEventListener('change', function () {
                        if (!selectAll) {
                            return;
                        }

                        const allChecked = Array.from(rowCheckboxes).every(function (row) {
                            return row.checked;
                        });

                        selectAll.checked = allChecked;
                    });
                });

                if (exportBtn && form && exportForm) {
                    exportBtn.addEventListener('click', function () {
                        exportForm.innerHTML = '';

                        const fields = form.querySelectorAll('input[name], select[name]');
                        fields.forEach(function (field) {
                            if (field.name === '' || field.disabled || field.type === 'button' || field.type === 'submit') {
                                return;
                            }

                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = field.name;
                            input.value = field.value;
                            exportForm.appendChild(input);
                        });

                        rowCheckboxes.forEach(function (checkbox) {
                            if (!checkbox.checked) {
                                return;
                            }

                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'selected_user_ids[]';
                            input.value = checkbox.value;
                            exportForm.appendChild(input);
                        });

                        exportForm.submit();
                    });
                }
            });
        </script>
    @endpush
@endsection
