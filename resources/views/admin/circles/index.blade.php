@extends('admin.layouts.app')

@section('title', 'Circles')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0">Circles</h5>
        <small class="text-muted">Community circles overview</small>
    </div>
    <a href="{{ route('admin.circles.create') }}" class="btn btn-primary btn-sm">Create Circle</a>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form id="circleFiltersForm" method="GET" action="{{ route('admin.circles.index') }}">
    <div class="card p-3 border-0 shadow-sm mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <button class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFiltersCollapse" aria-expanded="false" aria-controls="advancedFiltersCollapse">
                    <i class="bi bi-funnel"></i>Advanced Filters
                </button>
            </div>
            <div class="small text-muted text-end">
                @if($circles->total() > 0)
                    Showing {{ $circles->firstItem() }}-{{ $circles->lastItem() }} of {{ $circles->total() }} circles
                @else
                    No circles
                @endif
            </div>
        </div>

        <div class="collapse mt-3" id="advancedFiltersCollapse">
            <div class="p-3 bg-light rounded border-0">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Country</label>
                        <select name="country" class="form-select form-select-sm">
                            <option value="">All Countries</option>
                            @foreach ($countryOptions as $country)
                                <option value="{{ $country }}" @selected($filters['country'] === $country)>{{ $country }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Meeting Mode</label>
                        <select name="meeting_mode" class="form-select form-select-sm">
                            <option value="">All Modes</option>
                            @foreach ($meetingModeOptions as $meetingMode)
                                <option value="{{ $meetingMode }}" @selected($filters['meeting_mode'] === $meetingMode)>{{ \Illuminate\Support\Str::headline($meetingMode) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Meeting Frequency</label>
                        <select name="meeting_frequency" class="form-select form-select-sm">
                            <option value="">All Frequencies</option>
                            @foreach ($meetingFrequencyOptions as $meetingFrequency)
                                <option value="{{ $meetingFrequency }}" @selected($filters['meeting_frequency'] === $meetingFrequency)>{{ \Illuminate\Support\Str::headline($meetingFrequency) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Launch Date</label>
                        <input type="date" name="launch_date" class="form-control form-control-sm" value="{{ $filters['launch_date'] }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Director</label>
                        <input type="text" name="director" class="form-control form-control-sm" value="{{ $filters['director'] }}" placeholder="Director">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Circle Stage</label>
                        <select id="circleStageFilter" name="circle_stage" class="form-select form-select-sm">
                            <option value="">All Stages</option>
                            @foreach ($circleStageOptions as $circleStage)
                                <option value="{{ $circleStage }}" @selected($filters['circle_stage'] === $circleStage)>{{ $circleStage }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Industry Director</label>
                        <input type="text" name="industry_director" class="form-control form-control-sm" value="{{ $filters['industry_director'] }}" placeholder="Industry Director">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">DED</label>
                        <input type="text" name="ded" class="form-control form-control-sm" value="{{ $filters['ded'] }}" placeholder="DED">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted mb-1">Industry Tags</label>
                        <input type="text" name="industry_tags" class="form-control form-control-sm" value="{{ $filters['industry_tags'] }}" placeholder="Industry Tags">
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.circles.index') }}">Reset</a>
                    <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive premium-table-card">
        <table class="table premium-table align-middle">
            <thead>
                <tr>
                    <th style="padding-left: 20px !important; min-width: 170px;">Circle</th>
                    <th style="min-width: 150px;">Founder</th>
                    <th style="min-width: 140px;">City</th>
                    <th style="min-width: 130px;">Type</th>
                    <th style="min-width: 90px;">Peers</th>
                    <th style="min-width: 140px;">Rank</th>
                    <th style="min-width: 130px;">Status</th>
                    <th class="text-end" style="padding-right: 20px !important; min-width: 150px;">Actions</th>
                </tr>
                <tr class="bg-light align-middle">
                    <th style="padding-left: 20px !important;">
                        <select name="circle_name" class="form-select form-select-sm" style="min-width: 150px;">
                            <option value="">All Circles</option>
                            @foreach ($circleNames as $circleName)
                                <option value="{{ $circleName }}" @selected($filters['circle_name'] === $circleName)>{{ $circleName }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th>
                        <input type="text" name="founder" class="form-control form-control-sm" value="{{ $filters['founder'] }}" placeholder="Founder" style="min-width: 130px;">
                    </th>
                    <th>
                        <select name="city_id" class="form-select form-select-sm" style="min-width: 120px;">
                            <option value="any" @selected(($filters['city_id'] ?? 'any') === 'any')>All Cities</option>
                            @foreach ($cities as $c)
                                <option value="{{ $c->id }}" @selected(($filters['city_id'] ?? '') === (string) $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th>
                        <select name="type" class="form-select form-select-sm" style="min-width: 110px;">
                            <option value="">All Types</option>
                            @foreach ($typeOptions as $type)
                                <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ \Illuminate\Support\Str::headline($type) }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th>
                        <input type="text" name="peers" class="form-control form-control-sm" value="{{ $filters['peers'] }}" placeholder="Peers" style="min-width: 90px;">
                    </th>
                    <th>
                        <select id="circleRankFilter" name="rank" class="form-select form-select-sm js-searchable-select" style="min-width: 120px;">
                            <option value="">All Ranks</option>
                            @foreach ($rankOptions as $rank)
                                <option value="{{ $rank }}" @selected(($filters['rank'] ?? '') === $rank)>{{ $rank }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th>
                        <select name="status" class="form-select form-select-sm" style="min-width: 110px;">
                            <option value="">All Status</option>
                            @foreach ($statusOptions as $status)
                                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                            @endforeach
                        </select>
                    </th>
                    <th class="text-end" style="padding-right: 20px !important;">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.circles.index') }}">Reset</a>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse ($circles as $circle)
                    @php
                        $industryTags = $circle->industry_tags;
                        if (is_array($industryTags)) {
                            $industryTagsText = implode(', ', array_filter($industryTags));
                        } else {
                            $industryTagsText = trim((string) $industryTags);
                        }
                        $statusValue = strtolower($circle->status ?? 'active');
                        $isActive = $statusValue === 'active';
                        $detailsId = 'circle-details-' . $circle->id;
                    @endphp
                    <tr>
                        <td style="padding-left: 20px !important;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="circle-cover-wrapper">
                                    @if ($circle->cover_image_url)
                                        <img src="{{ $circle->cover_image_url }}" alt="{{ $circle->name }}" class="circle-cover-image">
                                    @else
                                        <div class="circle-cover-placeholder">
                                            <i class="bi bi-people text-muted" style="font-size: 0.95rem;"></i>
                                        </div>
                                    @endif
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-dark text-nowrap" style="font-size: 0.92rem;">{{ $circle->name ?? '—' }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if ($circle->founder)
                                <span class="text-dark d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                    <i class="bi bi-person text-muted small"></i>{{ $circle->founder->display_name }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($circle->city_name)
                                <span class="text-dark d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                    <i class="bi bi-geo-alt text-muted small"></i>{{ $circle->city_name }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($circle->city_name)
                                <span class="text-dark d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                    <i class="bi bi-geo-alt text-muted small"></i>{{ $circle->city_name }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($circle->type)
                                <span class="badge-type-custom text-uppercase">
                                    {{ $circle->type }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="fw-semibold text-primary d-inline-flex align-items-center gap-1 text-nowrap" style="font-size: 0.85rem;">
                                <i class="bi bi-people text-primary small"></i>{{ $circle->members_count ?? 0 }}
                            </span>
                        </td>
                        <td>
                            @php($rankingData = $circle->getCircleRanking())
                            <div class="d-flex flex-column">
                                <span class="fw-semibold text-dark" style="font-size: 0.85rem;">
                                    <i class="bi bi-trophy text-warning small me-1"></i>{{ $rankingData['rank'] }}
                                </span>
                                <span class="small text-muted" style="font-size: 0.72rem;">{{ $rankingData['title'] }}</span>
                            </div>
                        </td>
                        <td>
                            @if ($isActive)
                                <span class="badge-status-active">
                                    <span class="status-pulse-dot"></span>Active
                                </span>
                            @else
                                <span class="badge-status-inactive">
                                    <span class="status-pulse-dot"></span>{{ ucfirst($circle->status ?? 'Inactive') }}
                                </span>
                                </span>
                            @else
                                <span class="badge-status-inactive">
                                    <span class="status-pulse-dot"></span>{{ ucfirst($circle->status ?? 'Inactive') }}
                                </span>
                            @endif
                        </td>
                        <td class="text-end" style="padding-right: 20px !important;">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('admin.circles.edit', $circle) }}" class="btn btn-outline-secondary btn-action-custom" target="_blank" rel="noopener">
                                    <i class="bi bi-pencil"></i>Edit
                                </a>
                                <button class="btn btn-outline-primary btn-action-custom btn-details-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $detailsId }}" aria-expanded="false" aria-controls="{{ $detailsId }}">
                                <button class="btn btn-outline-primary btn-action-custom btn-details-toggle" type="button" data-bs-target="#{{ $detailsId }}" aria-expanded="false" aria-controls="{{ $detailsId }}">
                                    Details<i class="bi bi-chevron-down details-chevron ms-1"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr class="collapse-row">
                        <td colspan="8" class="p-0 border-0">
                            <div class="collapse" id="{{ $detailsId }}">
                                <div class="p-3 bg-light border-top">
                                    <?php
                                        $detailFields = [
                                            ['label' => 'Director', 'value' => $circle->director?->display_name],
                                            ['label' => 'Circle Stage', 'value' => $circle->circle_stage],
                                            ['label' => 'Country', 'value' => $circle->country ?? $circle->city?->country],
                                            ['label' => 'Meeting Mode', 'value' => !empty($circle->meeting_mode) ? ucfirst(strtolower($circle->meeting_mode)) : null],
                                            ['label' => 'Meeting Frequency', 'value' => !empty($circle->meeting_frequency) ? ucfirst(strtolower($circle->meeting_frequency)) : null],
                                            ['label' => 'Launch Date', 'value' => !empty($circle->launch_date) ? \Carbon\Carbon::parse($circle->launch_date)->format('d-m-Y') : null],
                                            ['label' => 'Industry Tags', 'value' => $industryTagsText !== '' ? $industryTagsText : null],
                                            ['label' => 'Peers Count', 'value' => ($circle->members_count ?? 0) . ' members', 'circle_id' => $circle->id],
                                            ['label' => 'Created At', 'value' => optional($circle->created_at)->format('d M Y') ?? null],
                                        ];
                                        
                                        $chunks = array_chunk($detailFields, (int) ceil(count($detailFields) / 3));
                                    ?>
                                    <div class="row g-3">
                                        @foreach ($chunks as $chunk)
                                            <div class="col-md-4">
                                                <table class="table table-sm mb-0 bg-transparent">
                                                    @foreach ($chunk as $field)
                                                        @if ($field['value'] !== null)
                                                            <tr>
                                                                <th class="w-50 text-muted border-0 bg-transparent py-1" style="font-size: 0.82rem;">{{ $field['label'] }}</th>
                                                                <td class="text-break border-0 bg-transparent py-1" style="font-size: 0.82rem;">
                                                                    @if (($field['label'] ?? null) === 'Peers Count' && ! empty($field['circle_id']))
                                                                        <div class="d-flex align-items-center gap-2">
                                                                            <span>{{ $field['value'] }}</span>
                                                                            <a href="{{ route('admin.users.index', ['circle_id' => $field['circle_id']]) }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.72rem;">View Members</a>
                                                                        </div>
                                                                    @else
                                                                        {{ $field['value'] }}
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endif
                                                    @endforeach
                                                </table>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                        <span class="text-muted small">Created At: {{ optional($circle->created_at)->format('d-m-Y') ?? '—' }}</span>
                                        <div class="d-inline-flex gap-1">
                                            <a class="btn btn-sm btn-light" href="{{ route('admin.circles.show', $circle) }}">View</a>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-circle" data-url="{{ route('admin.circles.destroy', $circle) }}" data-id="{{ $circle->id }}" data-name="{{ $circle->name }}" data-members="{{ $circle->members_count ?? 0 }}">Delete</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No circles found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-2">
        {{ $circles->links() }}
    </div>
</form>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCircleModal" tabindex="-1" aria-labelledby="deleteCircleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="deleteCircleForm" method="POST" action="">
            @csrf
            @method('DELETE')
            <div class="modal-content text-start" style="white-space: normal;">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteCircleModalLabel">Delete Circle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Are you sure you want to delete the circle "<strong id="deleteCircleName"></strong>"?</p>
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Members Count
                            <span class="badge bg-secondary rounded-pill" id="deleteMembersCount">0</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Meetings Count
                            <span class="badge bg-secondary rounded-pill" id="deleteMeetingsCount">Loading...</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Related Records (Posts, Messages, etc.)
                            <span class="badge bg-secondary rounded-pill" id="deleteRelatedCount">Loading...</span>
                        </li>
                    </ul>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i> This will delete the circle and cascade cleanup related records.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Delete</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('circleFiltersForm');

        if (form) {
            const enterSubmitFields = [
                document.getElementById('circleStageFilter'),
                document.getElementById('circleRankFilter'),
            ];

            enterSubmitFields.forEach(function (field) {
                if (!field) {
                    return;
                }

                field.addEventListener('keydown', function (event) {
                    if (event.key !== 'Enter') {
                        return;
                    }

                    event.preventDefault();
                    form.submit();
                });
            });
        }

        const deleteButtons = document.querySelectorAll('.btn-delete-circle');
        const deleteModalEl = document.getElementById('deleteCircleModal');
        
        if (deleteButtons.length && deleteModalEl) {
            const deleteModal = new bootstrap.Modal(deleteModalEl);
            const deleteForm = document.getElementById('deleteCircleForm');
            const nameEl = document.getElementById('deleteCircleName');
            const membersEl = document.getElementById('deleteMembersCount');
            const meetingsEl = document.getElementById('deleteMeetingsCount');
            const relatedEl = document.getElementById('deleteRelatedCount');

            deleteButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const url = this.getAttribute('data-url');
                    const name = this.getAttribute('data-name');
                    const members = this.getAttribute('data-members');
                    const id = this.getAttribute('data-id');

                    deleteForm.setAttribute('action', url);
                    nameEl.textContent = name;
                    membersEl.textContent = members;
                    meetingsEl.textContent = 'Loading...';
                    relatedEl.textContent = 'Loading...';

                    deleteModal.show();

                    // Fetch stats via AJAX
                    fetch(`/admin/circles/${id}/delete-stats`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                meetingsEl.textContent = data.meetings_count;
                                relatedEl.textContent = data.related_count;
                            } else {
                                meetingsEl.textContent = 'Error';
                                relatedEl.textContent = 'Error';
                            }
                        })
                        .catch(err => {
                            meetingsEl.textContent = 'Error';
                            relatedEl.textContent = 'Error';
                        });
                });
            });
        }

        // Handle details toggle manual fallback to ensure it opens and closes properly
        document.querySelectorAll('.btn-details-toggle').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-bs-target');
                const target = document.querySelector(targetId);
                if (target) {
                    const isVisible = target.classList.contains('show');
                    if (isVisible) {
                        target.classList.remove('show');
                        this.setAttribute('aria-expanded', 'false');
                    } else {
                        target.classList.add('show');
                        this.setAttribute('aria-expanded', 'true');
                    }
                }
            });
        });
    });

    function confirmDeleteCircle(actionUrl, circleName) {
        if (confirm('Are you sure you want to delete the circle "' + circleName + '"? This is a soft delete and can be restored by admin.')) {
            const form = document.getElementById('deleteCircleForm');
            form.action = actionUrl;
            form.submit();
        }
    }
</script>
@endpush

<form id="deleteCircleForm" action="" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

@endsection
