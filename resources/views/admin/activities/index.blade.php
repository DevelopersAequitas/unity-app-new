@extends('admin.layouts.app')

@section('title', 'Activities Summary')

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

    @if ($errors->any())
        <div class="alert alert-danger shadow-sm rounded-3">
            {{ $errors->first() }}
        </div>
    @endif

    <!-- Activities Hub Header -->
    @include('admin.activities.partials.header', ['title' => 'Summary'])

    <!-- Top Stats / District Peers Overview -->
    <div class="row g-3 mb-4">
        <!-- Top District Peers Card -->
        <div class="col-12">
            <div class="card-activities-wrapper">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark"><i class="bi bi-trophy text-warning me-2"></i>Top 5 District Peers</span>
                    <span class="badge bg-light text-muted fw-normal border">Ranked by combined performance</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-premium align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Rank</th>
                                <th>Peer Name</th>
                                <th>Company</th>
                                <th>City</th>
                                <th class="text-end">Performance Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse (($topDistrictPeers ?? collect()) as $rank => $peer)
                                <tr>
                                    <td>
                                        <span class="badge {{ $rank == 0 ? 'bg-warning text-dark' : ($rank == 1 ? 'bg-secondary text-white' : ($rank == 2 ? 'bg-dark text-white' : 'bg-light text-muted border')) }} rounded-circle p-2 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-weight: 700;">
                                            {{ $rank + 1 }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="peer-badge-wrapper">
                                            <div class="peer-badge-avatar" style="background-color: {{ $getAvatarBg($peer->peer_name) }}">
                                                {{ $getInitials($peer->peer_name) }}
                                            </div>
                                            <div class="peer-badge-info">
                                                <div class="peer-badge-name">{{ $peer->peer_name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $peer->company_name ?: '—' }}</td>
                                    <td>{{ $peer->city_name ?: '—' }}</td>
                                    <td class="text-end fw-bold text-primary">{{ number_format((int) ($peer->performance_score ?? 0)) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No district peer performance found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Main List Card -->
    <div class="card-activities-wrapper">
        <form id="activitiesFiltersForm" method="GET" action="{{ route('admin.activities.index') }}">
        </form>

        <div class="d-flex flex-wrap justify-content-between align-items-center p-3 gap-2 border-bottom bg-light">
            <div class="d-flex align-items-center gap-2">
                <label for="perPage" class="form-label mb-0 small text-muted">Rows per page:</label>
                <select id="perPage" name="per_page" form="activitiesFiltersForm" class="form-select form-select-sm" style="width: 90px;">
                    @foreach ([10, 20, 25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>

            <div class="d-flex gap-2 align-items-center">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-calendar-event"></i></span>
                    <input
                        type="datetime-local"
                        name="from"
                        form="activitiesFiltersForm"
                        value="{{ $filters['from'] ?? '' }}"
                        class="form-control"
                        placeholder="From"
                        title="From"
                    >
                </div>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white text-muted"><i class="bi bi-calendar-event"></i></span>
                    <input
                        type="datetime-local"
                        name="to"
                        form="activitiesFiltersForm"
                        value="{{ $filters['to'] ?? '' }}"
                        class="form-control"
                        placeholder="To"
                        title="To"
                    >
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-premium align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" class="form-check-input" id="select-all-members">
                        </th>
                        <th>Peer Details</th>
                        <th>Testimonials</th>
                        <th>Referrals</th>
                        <th>Business Deals</th>
                        <th>P2P Meetings</th>
                        <th>Requirements</th>
                        <th>Leadership Requests</th>
                        <th>Recommended Peers</th>
                        <th>Registered Visitor</th>
                    </tr>
                    <tr class="bg-light align-middle filter-row">
                        <th></th>
                        <th>
                            <div class="d-flex flex-column gap-2 py-1">
                                <input
                                    type="text"
                                    name="q"
                                    form="activitiesFiltersForm"
                                    class="form-control form-control-sm"
                                    placeholder="Name, company, city"
                                    value="{{ $filters['q'] ?? '' }}"
                                >
                                <select name="circle_id" form="activitiesFiltersForm" class="form-select form-select-sm">
                                    <option value="any">All Circles</option>
                                    @foreach ($circles as $circle)
                                        <option value="{{ $circle->id }}" @selected(($filters['circle_id'] ?? '') === $circle->id)>{{ $circle->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </th>
                        <th><input type="text" class="form-control form-control-sm" disabled placeholder="—"></th>
                        <th><input type="text" class="form-control form-control-sm" disabled placeholder="—"></th>
                        <th><input type="text" class="form-control form-control-sm" disabled placeholder="—"></th>
                        <th><input type="text" class="form-control form-control-sm" disabled placeholder="—"></th>
                        <th><input type="text" class="form-control form-control-sm" disabled placeholder="—"></th>
                        <th><input type="text" class="form-control form-control-sm" disabled placeholder="—"></th>
                        <th><input type="text" class="form-control form-control-sm" disabled placeholder="—"></th>
                        <th>
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="submit" form="activitiesFiltersForm" class="btn btn-sm btn-primary px-3">Apply</button>
                                <a href="{{ route('admin.activities.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#activitiesExportModal">Export</button>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        <tr>
                            <td><input type="checkbox" class="form-check-input member-checkbox" value="{{ $member->id }}"></td>
                            <td>
                                <div class="peer-badge-wrapper">
                                    <div class="peer-badge-avatar" style="background-color: {{ $getAvatarBg($member->peer_name) }}">
                                        {{ $getInitials($member->peer_name) }}
                                    </div>
                                    <div class="peer-badge-info">
                                        <div class="peer-badge-name">{{ $member->peer_name }}</div>
                                        <div class="peer-badge-meta">
                                            @if($member->company_name) <span class="fw-medium text-dark">{{ $member->company_name }}</span> @endif
                                            @if($member->city_name) &bull; <span>{{ $member->city_name }}</span> @endif
                                            @if($member->circle_name) &bull; <span class="text-primary">{{ $member->circle_name }}</span> @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($member->testimonials_count > 0)
                                    <a href="{{ route('admin.activities.testimonials', $member->id) }}" class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 text-decoration-none" target="_blank">
                                        <i class="bi bi-chat-quote-fill me-1"></i>{{ $member->testimonials_count }}
                                    </a>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td>
                                @if ($member->referrals_count > 0)
                                    <a href="{{ route('admin.activities.referrals', $member->id) }}" class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 text-decoration-none" target="_blank">
                                        <i class="bi bi-person-plus-fill me-1"></i>{{ $member->referrals_count }}
                                    </a>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td>
                                @if ($member->business_deals_count > 0)
                                    <a href="{{ route('admin.activities.business-deals', $member->id) }}" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1 text-decoration-none" target="_blank">
                                        <i class="bi bi-briefcase-fill me-1"></i>{{ $member->business_deals_count }}
                                    </a>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td>
                                @if ($member->p2p_completed_count > 0)
                                    <a href="{{ route('admin.activities.p2p-meetings', $member->id) }}" class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1 text-decoration-none" target="_blank">
                                        <i class="bi bi-people-fill me-1"></i>{{ $member->p2p_completed_count }}
                                    </a>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td>
                                @if ($member->requirements_count > 0)
                                    <a href="{{ route('admin.activities.requirements', $member->id) }}" class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 text-decoration-none" target="_blank">
                                        <i class="bi bi-file-earmark-text-fill me-1"></i>{{ $member->requirements_count }}
                                    </a>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td>
                                @if ($member->become_leader_count > 0)
                                    <a href="{{ route('admin.activities.become-a-leader.show', $member->id) }}" class="badge bg-dark-subtle text-dark border border-dark-subtle px-2 py-1 text-decoration-none" target="_blank">
                                        <i class="bi bi-award-fill me-1"></i>{{ $member->become_leader_count }}
                                    </a>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td>
                                @if ($member->recommend_peer_count > 0)
                                    <a href="{{ route('admin.activities.recommend-peer.show', $member->id) }}" class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle px-2 py-1 text-decoration-none" target="_blank">
                                        <i class="bi bi-hand-thumbs-up-fill me-1"></i>{{ $member->recommend_peer_count }}
                                    </a>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td>
                                @if ($member->register_visitor_count > 0)
                                    <a href="{{ route('admin.activities.register-visitor.show', $member->id) }}" class="badge bg-light text-dark border px-2 py-1 text-decoration-none" target="_blank">
                                        <i class="bi bi-person-vcard-fill me-1"></i>{{ $member->register_visitor_count }}
                                    </a>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">No peers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal fade" id="activitiesExportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-download me-2 text-primary"></i>Export Activities Summary</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('admin.activities.export') }}" id="activitiesExportForm">
                    @csrf
                    <input type="hidden" name="activity_type" value="summary">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Scope</label>
                            <div class="form-check p-3 bg-light rounded border mb-2">
                                <input class="form-check-input" type="radio" name="scope" id="scopeSelected" value="selected" checked>
                                <label class="form-check-label fw-medium text-dark ms-1" for="scopeSelected">Selected peers only</label>
                            </div>
                            <div class="form-check p-3 bg-light rounded border">
                                <input class="form-check-input" type="radio" name="scope" id="scopeAll" value="all">
                                <label class="form-check-label fw-medium text-dark ms-1" for="scopeAll">All peers (current filters)</label>
                            </div>
                        </div>
                        <input type="hidden" name="q" value="{{ $filters['q'] }}">
                        <input type="hidden" name="search" value="{{ $filters['q'] }}">
                        <input type="hidden" name="circle_id" value="{{ $filters['circle_id'] }}">
                        <input type="hidden" name="from" value="{{ $filters['from'] }}">
                        <input type="hidden" name="to" value="{{ $filters['to'] }}">
                        <div id="selectedMemberIdsContainer"></div>
                        <div class="text-danger small d-none" id="exportSelectionError">Please select at least one peer.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Export CSV</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $members->links() }}
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const selectAll = document.getElementById('select-all-members');
        const checkboxes = document.querySelectorAll('.member-checkbox');
        const exportForm = document.getElementById('activitiesExportForm');
        const selectedContainer = document.getElementById('selectedMemberIdsContainer');
        const selectionError = document.getElementById('exportSelectionError');
        const scopeSelected = document.getElementById('scopeSelected');

        if (selectAll) {
            selectAll.addEventListener('change', () => {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = selectAll.checked;
                });
            });
        }

        exportForm.addEventListener('submit', (event) => {
            selectionError.classList.add('d-none');
            selectedContainer.innerHTML = '';
            const selectedIds = Array.from(checkboxes)
                .filter((checkbox) => checkbox.checked)
                .map((checkbox) => checkbox.value);

            if (scopeSelected.checked && selectedIds.length === 0) {
                event.preventDefault();
                selectionError.classList.remove('d-none');
                return;
            }

            selectedIds.forEach((id) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_member_ids[]';
                input.value = id;
                selectedContainer.appendChild(input);
            });
        });
    });
</script>
@endpush
