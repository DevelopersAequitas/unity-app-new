@extends('admin.layouts.app')

@section('title', 'Circles')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Circles</h2>
            <p class="text-xs t3 m-0 mt-0.5">Community circles overview and management.</p>
        </div>
        <a href="{{ route('admin.circles.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1">
            ➕ Create Circle
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    <form id="circleFiltersForm" method="GET" action="{{ route('admin.circles.index') }}">
        <div class="border bs rounded-xl p-3.5 mb-4 surface-2">
            <div class="flex justify-between items-center flex-wrap gap-2">
                <div>
                    <button class="chip !py-1 text-xs" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFiltersCollapse" aria-expanded="false" aria-controls="advancedFiltersCollapse">
                        <i class="bi bi-funnel"></i>Advanced Filters
                    </button>
                </div>
                <div class="text-xs t3">
                    @if($circles->total() > 0)
                        Showing <span class="font-semibold t1">{{ $circles->firstItem() }}-{{ $circles->lastItem() }}</span> of <span class="font-semibold t1">{{ $circles->total() }}</span> circles
                    @else
                        No circles
                    @endif
                </div>
            </div>

            <div class="collapse mt-3" id="advancedFiltersCollapse">
                <div class="p-3 surface rounded-lg border bs">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="block text-[11px] t3 mb-1 font-medium">Country</label>
                            <select name="country" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                                <option value="">All Countries</option>
                                @foreach ($countryOptions as $country)
                                    <option value="{{ $country }}" @selected($filters['country'] === $country)>{{ $country }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="block text-[11px] t3 mb-1 font-medium">Meeting Mode</label>
                            <select name="meeting_mode" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                                <option value="">All Modes</option>
                                @foreach ($meetingModeOptions as $meetingMode)
                                    <option value="{{ $meetingMode }}" @selected($filters['meeting_mode'] === $meetingMode)>{{ \Illuminate\Support\Str::headline($meetingMode) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="block text-[11px] t3 mb-1 font-medium">Meeting Frequency</label>
                            <select name="meeting_frequency" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                                <option value="">All Frequencies</option>
                                @foreach ($meetingFrequencyOptions as $meetingFrequency)
                                    <option value="{{ $meetingFrequency }}" @selected($filters['meeting_frequency'] === $meetingFrequency)>{{ \Illuminate\Support\Str::headline($meetingFrequency) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="block text-[11px] t3 mb-1 font-medium">Launch Date</label>
                            <input type="date" name="launch_date" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['launch_date'] }}">
                        </div>
                        <div class="col-md-4">
                            <label class="block text-[11px] t3 mb-1 font-medium">Director</label>
                            <input type="text" name="director" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['director'] }}" placeholder="Director">
                        </div>
                        <div class="col-md-4">
                            <label class="block text-[11px] t3 mb-1 font-medium">Circle Stage</label>
                            <select id="circleStageFilter" name="circle_stage" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                                <option value="">All Stages</option>
                                @foreach ($circleStageOptions as $circleStage)
                                    <option value="{{ $circleStage }}" @selected($filters['circle_stage'] === $circleStage)>{{ $circleStage }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="block text-[11px] t3 mb-1 font-medium">Industry Director</label>
                            <input type="text" name="industry_director" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['industry_director'] }}" placeholder="Industry Director">
                        </div>
                        <div class="col-md-4">
                            <label class="block text-[11px] t3 mb-1 font-medium">DED</label>
                            <input type="text" name="ded" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['ded'] }}" placeholder="DED">
                        </div>
                        <div class="col-md-4">
                            <label class="block text-[11px] t3 mb-1 font-medium">Industry Tags</label>
                            <input type="text" name="industry_tags" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['industry_tags'] }}" placeholder="Industry Tags">
                        </div>
                    </div>
                    <div class="flex justify-end mt-3">
                        <button type="button" onclick="clearAdminFilters(event, 'circleFiltersForm')" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center">Clear</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 170px;">Circle</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 150px;">Founder</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 140px;">City</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 130px;">Type</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 90px;">Peers</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 140px;">Rank</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 130px;">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right" style="min-width: 150px;">Actions</th>
                        </tr>
                        <tr class="surface-2 border-b bs filter-row">
                            <th class="px-2 py-1">
                                <select name="circle_name" class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" style="min-width: 150px;">
                                    <option value="">All Circles</option>
                                    @foreach ($circleNames as $circleName)
                                        <option value="{{ $circleName }}" @selected($filters['circle_name'] === $circleName)>{{ $circleName }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="px-3 py-2">
                                <input type="text" name="founder" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal" value="{{ $filters['founder'] }}" placeholder="Founder" style="min-width: 130px;">
                            </th>
                            <th class="px-3 py-2">
                                <select name="city_id" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal js-no-select2" style="min-width: 120px;">
                                    <option value="any" @selected(($filters['city_id'] ?? 'any') === 'any')>All Cities</option>
                                    @foreach ($cities as $c)
                                        <option value="{{ $c->id }}" @selected(($filters['city_id'] ?? '') === (string) $c->id)>{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="px-3 py-2">
                                <select name="type" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal js-no-select2" style="min-width: 110px;">
                                    <option value="">All Types</option>
                                    @foreach ($typeOptions as $type)
                                        <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ \Illuminate\Support\Str::headline($type) }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="px-3 py-2">
                                <input type="text" name="peers" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal" value="{{ $filters['peers'] }}" placeholder="Peers" style="min-width: 90px;">
                            </th>
                            <th class="px-3 py-2">
                                <select id="circleRankFilter" name="rank" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal js-no-select2" style="min-width: 120px;">
                                    <option value="">All Ranks</option>
                                    @foreach ($rankOptions as $rank)
                                        <option value="{{ $rank }}" @selected(($filters['rank'] ?? '') === $rank)>{{ $rank }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="px-3 py-2">
                                <select name="status" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal js-no-select2" style="min-width: 110px;">
                                    <option value="">All Status</option>
                                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                                    <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                                </select>
                            </th>
                            <th class="px-3 py-2 text-right">
                                <div class="flex justify-end">
                                    <button type="button" onclick="clearAdminFilters(event, 'circleFiltersForm')" class="px-3 py-1 rounded-md border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition">Clear</button>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
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
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg overflow-hidden flex-none border bs bg-gray-100 flex items-center justify-center">
                                            @if ($circle->cover_image_url)
                                                <img src="{{ $circle->cover_image_url }}" alt="{{ $circle->name }}" class="w-full h-full object-cover">
                                            @else
                                                <i class="bi bi-people t3 text-xs"></i>
                                            @endif
                                        </div>
                                        <div class="font-medium t1 text-[12.5px] whitespace-nowrap">{{ $circle->name ?? '-' }}</div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($circle->founder)
                                        <span class="t1 inline-flex items-center gap-1 whitespace-nowrap text-[12.5px]">
                                            <i class="bi bi-person t3 text-xs"></i>{{ $circle->founder->display_name }}
                                        </span>
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($circle->city_name)
                                        <span class="t1 inline-flex items-center gap-1 whitespace-nowrap text-[12.5px]">
                                            <i class="bi bi-geo-alt t3 text-xs"></i>{{ $circle->city_name }}
                                        </span>
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($circle->type)
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200 uppercase">
                                            {{ $circle->type }}
                                        </span>
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="text-indigo-600 font-semibold inline-flex items-center gap-1 whitespace-nowrap text-[12.5px]">
                                        <i class="bi bi-people text-indigo-500 text-xs"></i>{{ $circle->members_count ?? 0 }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5">
                                    @php($rankingData = $circle->getCircleRanking())
                                    <div class="flex flex-col">
                                        <span class="font-semibold t1 text-[12.5px] inline-flex items-center gap-1">
                                            <i class="bi bi-trophy text-amber-500 text-xs"></i>{{ $rankingData['rank'] }}
                                        </span>
                                        <span class="t3 text-[10px]">{{ $rankingData['title'] }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($isActive)
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200 inline-flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Active
                                        </span>
                                    @else
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200 inline-flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>{{ ucfirst($circle->status ?? 'Inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.circles.edit', $circle) }}" class="px-2.5 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1" target="_blank" rel="noopener">
                                            <i class="bi bi-pencil"></i>Edit
                                        </a>
                                        <button class="px-2.5 py-1 rounded-lg border bs text-xs font-medium text-indigo-600 hover:text-indigo-700 surface-2 transition btn-details-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $detailsId }}" aria-expanded="false" aria-controls="{{ $detailsId }}">
                                            Details<i class="bi bi-chevron-down details-chevron ms-1"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="collapse-row">
                                <td colspan="8" class="p-0 border-0">
                                    <div class="collapse" id="{{ $detailsId }}">
                                        <div class="p-4 surface-2 border-t border-b bs">
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
                                                        <table class="table table-sm mb-0 bg-transparent border-0">
                                                            @foreach ($chunk as $field)
                                                                @if ($field['value'] !== null)
                                                                    <tr>
                                                                        <th class="w-50 t3 border-0 bg-transparent py-1 text-xs font-medium">{{ $field['label'] }}</th>
                                                                        <td class="text-break border-0 bg-transparent py-1 text-xs t1">
                                                                            @if (($field['label'] ?? null) === 'Peers Count' && ! empty($field['circle_id']))
                                                                                <div class="flex items-center gap-2">
                                                                                    <span>{{ $field['value'] }}</span>
                                                                                    <a href="{{ route('admin.users.index', ['circle_id' => $field['circle_id']]) }}" class="px-2 py-0.5 rounded border bs text-[11px] text-indigo-600 font-medium no-underline">View Members</a>
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
                                            <div class="flex justify-between items-center mt-3 pt-2 border-t bs">
                                                <span class="t3 text-xs">Created At: {{ optional($circle->created_at)->format('d-m-Y') ?? '-' }}</span>
                                                <div class="inline-flex gap-2">
                                                    <a class="px-2.5 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 transition no-underline" href="{{ route('admin.circles.show', $circle) }}">View</a>
                                                    <button type="button" class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 text-xs font-semibold btn-delete-circle" data-url="{{ route('admin.circles.destroy', $circle) }}" data-id="{{ $circle->id }}" data-name="{{ $circle->name }}" data-members="{{ $circle->members_count ?? 0 }}">Delete</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-8 text-xs t3">No circles found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Bottom Toolbar & Pagination --}}
            <div class="flex justify-between items-center p-3 flex-wrap gap-2 border-t bs">
                <div>
                    {{ $circles->links() }}
                </div>
                <div class="text-xs t3">
                    @if($circles->total() > 0)
                        Showing <span class="font-semibold t1">{{ $circles->firstItem() }}-{{ $circles->lastItem() }}</span> of <span class="font-semibold t1">{{ $circles->total() }}</span> circles
                    @else
                        No circles
                    @endif
                </div>
            </div>
        </div>
    </form>
</div>


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
