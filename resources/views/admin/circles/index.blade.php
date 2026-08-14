@extends('admin.layouts.app')

@section('title', 'Circles')

@include('admin.partials.grid-head')

@push('styles')
<style>
  .scrim { backdrop-filter: blur(4px); transition: all 0.3s ease; }
  .drawer { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
  .drawer-hidden { transform: translateX(100%); }
</style>
@endpush

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Circles</h2>
            <p class="text-xs t3 m-0 mt-0.5">Community circles overview and management.</p>
        </div>
        <a href="{{ route('admin.circles.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1">
            <i class="bi bi-plus-lg admin-icon me-1" aria-hidden="true"></i>Create Circle
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    @php
        $hasAdvancedFilters = !empty($filters['country'])
            || !empty($filters['meeting_mode'])
            || !empty($filters['meeting_frequency'])
            || !empty($filters['launch_date'])
            || !empty($filters['director'])
            || !empty($filters['circle_stage'])
            || !empty($filters['industry_director'])
            || !empty($filters['ded'])
            || !empty($filters['industry_tags']);
    @endphp

    <form id="circleFiltersForm" method="GET" action="{{ route('admin.circles.index') }}">
        <div class="border bs rounded-xl p-3.5 mb-4 surface-2">
            <div class="flex justify-between items-center flex-wrap gap-2">
                <div>
                    <button id="btnAdvancedFilters" class="chip !py-1 text-xs {{ $hasAdvancedFilters ? 'chip-active' : '' }}" type="button" onclick="toggleAdvancedFilters(event)" aria-expanded="{{ $hasAdvancedFilters ? 'true' : 'false' }}">
                        <i class="bi bi-funnel me-1"></i>Advanced Filters
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

            <div class="mt-3 {{ $hasAdvancedFilters ? '' : 'hidden' }}" id="advancedFiltersCollapse" style="{{ $hasAdvancedFilters ? 'display: block;' : 'display: none;' }}">
                <div class="p-3 surface rounded-lg border bs">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] t3 mb-1 font-medium">Country</label>
                            <select name="country" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                                <option value="">All Countries</option>
                                @foreach ($countryOptions as $country)
                                    <option value="{{ $country }}" @selected($filters['country'] === $country)>{{ $country }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] t3 mb-1 font-medium">Meeting Mode</label>
                            <select name="meeting_mode" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                                <option value="">All Modes</option>
                                @foreach ($meetingModeOptions as $meetingMode)
                                    <option value="{{ $meetingMode }}" @selected($filters['meeting_mode'] === $meetingMode)>{{ \Illuminate\Support\Str::headline($meetingMode) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] t3 mb-1 font-medium">Meeting Frequency</label>
                            <select name="meeting_frequency" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                                <option value="">All Frequencies</option>
                                @foreach ($meetingFrequencyOptions as $meetingFrequency)
                                    <option value="{{ $meetingFrequency }}" @selected($filters['meeting_frequency'] === $meetingFrequency)>{{ \Illuminate\Support\Str::headline($meetingFrequency) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] t3 mb-1 font-medium">Launch Date</label>
                            <input type="date" name="launch_date" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['launch_date'] }}">
                        </div>
                        <div>
                            <label class="block text-[11px] t3 mb-1 font-medium">Director</label>
                            <input type="text" name="director" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['director'] }}" placeholder="Director">
                        </div>
                        <div>
                            <label class="block text-[11px] t3 mb-1 font-medium">Circle Stage</label>
                            <select id="circleStageFilter" name="circle_stage" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                                <option value="">All Stages</option>
                                @foreach ($circleStageOptions as $circleStage)
                                    <option value="{{ $circleStage }}" @selected($filters['circle_stage'] === $circleStage)>{{ $circleStage }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] t3 mb-1 font-medium">Industry Director</label>
                            <input type="text" name="industry_director" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['industry_director'] }}" placeholder="Industry Director">
                        </div>
                        <div>
                            <label class="block text-[11px] t3 mb-1 font-medium">DED</label>
                            <input type="text" name="ded" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['ded'] }}" placeholder="DED">
                        </div>
                        <div>
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
                <table class="min-w-[1100px] w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left sticky left-0 z-10" style="min-width: 170px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">Circle</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 150px;">Founder</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 140px;">City</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 130px;">Type</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 90px;">Peers</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 140px;">Rank</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 130px;">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right" style="min-width: 150px;">Actions</th>
                        </tr>
                        <tr class="surface-2 border-b bs filter-row">
                            <th class="px-2 py-1 sticky left-0 z-10 surface-2" style="box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">
                                <select name="circle_name" class="admin-filter-dropdown px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring" style="min-width: 150px;">
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
                                @if (isset($isDed) && $isDed)
                                    <div class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t2 text-center font-medium" style="min-width: 120px; background-color: var(--surface-2, #f8f9fa);">
                                        {{ $dedDistrictName ?? 'District Scoped' }}
                                    </div>
                                @else
                                    <select name="city_id" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal js-no-select2" style="min-width: 120px;">
                                        <option value="any" @selected(($filters['city_id'] ?? 'any') === 'any')>All Cities</option>
                                        @foreach ($cities as $c)
                                            <option value="{{ $c->id }}" @selected(($filters['city_id'] ?? '') === (string) $c->id)>{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
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
                            <tr class="hover:surface-2 transition border-b bs cursor-pointer" onclick="openCircleDrawer('{{ $circle->id }}')">
                                <td class="px-3 py-2.5 sticky left-0 z-10 surface" style="min-width:170px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.10);">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg overflow-hidden flex-none border bs bg-gray-100 flex items-center justify-center">
                                            @if ($circle->cover_image_url)
                                                <img src="{{ $circle->cover_image_url }}" alt="{{ $circle->name }}" class="w-full h-full object-cover">
                                            @else
                                                <i class="bi bi-people t3 text-xs"></i>
                                            @endif
                                        </div>
                                        <div class="font-medium t1 text-[12.5px] whitespace-nowrap">
                                            <a href="{{ route('admin.circles.show', $circle->id) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline" onclick="event.stopPropagation();">
                                                {{ $circle->name ?? '-' }}
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($circle->founder)
                                        <span class="t1 inline-flex items-center gap-1 whitespace-nowrap text-[12.5px]">
                                            <a href="#" class="text-indigo-600 hover:text-indigo-800 hover:underline font-medium no-underline flex items-center gap-1" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $circle->founder->id }}', event);">
                                                <i class="bi bi-person t3 text-xs"></i>{{ $circle->founder->display_name }}
                                            </a>
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
                                <td class="px-3 py-2.5 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                                    <div class="flex justify-end gap-1.5">
                                        <a href="{{ route('admin.circles.edit', $circle) }}" class="px-2.5 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1" target="_blank" rel="noopener">
                                            <i class="bi bi-pencil"></i>Edit
                                        </a>
                                        <button class="px-2.5 py-1 rounded-lg border bs text-xs font-medium text-indigo-600 hover:text-indigo-700 surface-2 transition" type="button" onclick="event.stopPropagation(); openCircleDrawer('{{ $circle->id }}')">
                                            Details
                                        </button>
                                        <button type="button" class="px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-semibold transition inline-flex items-center gap-1 btn-delete-circle" data-url="{{ route('admin.circles.destroy', $circle) }}" data-id="{{ $circle->id }}" data-name="{{ $circle->name }}" data-members="{{ $circle->members_count ?? 0 }}" onclick="event.stopPropagation();">
                                            <i class="bi bi-trash"></i>Delete
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
                                                    ['label' => 'Meeting Mode', 'value' => ! empty($circle->meeting_mode) ? ucfirst(strtolower($circle->meeting_mode)) : null],
                                                    ['label' => 'Meeting Frequency', 'value' => ! empty($circle->meeting_frequency) ? ucfirst(strtolower($circle->meeting_frequency)) : null],
                                                    ['label' => 'Launch Date', 'value' => ! empty($circle->launch_date) ? \Illuminate\Support\Carbon::parse($circle->launch_date)->format('d-m-Y') : null],
                                                    ['label' => 'Industry Tags', 'value' => $industryTagsText !== '' ? $industryTagsText : null],
                                                    ['label' => 'Peers Count', 'value' => ($circle->members_count ?? 0).' members', 'circle_id' => $circle->id],
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

<?php
    $circlesJsonData = $circles->getCollection()->map(function ($c) {
        $rankingData = $c->getCircleRanking();
        $industryTags = $c->industry_tags;
        if (is_array($industryTags)) {
            $industryTagsText = implode(', ', array_filter($industryTags));
        } else {
            $industryTagsText = trim((string) $industryTags);
        }

        return [
            'id' => (string) $c->id,
            'name' => $c->name ?? '—',
            'cover_image_url' => $c->cover_image_url,
            'founder' => $c->founder?->display_name ?? '—',
            'city' => $c->city_name ?? '—',
            'country' => $c->country ?? $c->city?->country ?? 'India',
            'type' => strtoupper($c->type ?? 'PUBLIC'),
            'members_count' => $c->members_count ?? 0,
            'rank' => $rankingData['rank'] ?? 'Bronze',
            'rank_title' => $rankingData['title'] ?? 'Rising Circle',
            'status' => ucfirst(strtolower($c->status ?? 'active')),
            'director' => $c->director?->display_name ?? '—',
            'circle_stage' => $c->circle_stage ?? '—',
            'meeting_mode' => ! empty($c->meeting_mode) ? ucfirst(strtolower($c->meeting_mode)) : '—',
            'meeting_frequency' => ! empty($c->meeting_frequency) ? ucfirst(strtolower($c->meeting_frequency)) : '—',
            'launch_date' => ! empty($c->launch_date) ? \Illuminate\Support\Carbon::parse($c->launch_date)->format('d M Y') : '—',
            'industry_tags' => $industryTagsText !== '' ? $industryTagsText : '—',
            'created_at' => optional($c->created_at)->format('d M Y') ?? '—',
            'show_url' => route('admin.circles.show', $c),
            'edit_url' => route('admin.circles.edit', $c),
            'destroy_url' => route('admin.circles.destroy', $c),
            'members_url' => route('admin.users.index', ['circle_id' => $c->id]),
        ];
    })->values();
                                            ?>

<!-- ============ CIRCLE PREVIEW DRAWER ============ -->
<div id="circle-drawer-scrim" onclick="closeCircleDrawer()" class="scrim hidden fixed inset-0 bg-black/50 z-40"></div>
<aside id="circle-drawer" class="drawer drawer-hidden fixed top-0 right-0 h-full w-full sm:w-[420px] bg-white border-l border-slate-200 z-50 flex flex-col shadow-2xl">
  <div class="flex items-center justify-between px-5 h-16 border-b border-slate-200 flex-none bg-white">
    <span class="font-display font-semibold text-[15px] text-slate-900">Circle profile</span>
    <button onclick="closeCircleDrawer()" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="flex-1 overflow-y-auto p-5 space-y-5 bg-white" id="circle-drawer-body">
    <!-- filled by JS -->
  </div>
  <div class="flex-none p-4 border-t border-slate-200 bg-white flex gap-2">
    <a id="circle-view-full-btn" href="#" class="flex-1 py-2.5 rounded-xl bg-[#00bcd4] hover:bg-[#00acc1] text-white text-[12.5px] font-semibold transition shadow-sm text-center border-0 cursor-pointer no-underline">View full circle</a>
    <a id="circle-quick-edit-btn" href="#" class="px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[12.5px] font-semibold transition cursor-pointer no-underline">Quick edit</a>
    <button type="button" id="circle-drawer-delete-btn" class="px-3.5 py-2.5 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-[12.5px] font-semibold transition cursor-pointer flex items-center gap-1 border-0">Delete</button>
  </div>
</aside>

@push('scripts')
<script>
    const circlesData = @json($circlesJsonData);

    function triggerCircleDeleteModal(url, name, members, id) {
        const deleteModalEl = document.getElementById('deleteCircleModal');
        if (!deleteModalEl) return;
        const deleteModal = bootstrap.Modal.getInstance(deleteModalEl) || new bootstrap.Modal(deleteModalEl);
        const deleteForm = document.getElementById('deleteCircleForm');
        const nameEl = document.getElementById('deleteCircleName');
        const membersEl = document.getElementById('deleteMembersCount');
        const meetingsEl = document.getElementById('deleteMeetingsCount');
        const relatedEl = document.getElementById('deleteRelatedCount');

        deleteForm.setAttribute('action', url);
        nameEl.textContent = name;
        membersEl.textContent = members;
        meetingsEl.textContent = 'Loading...';
        relatedEl.textContent = 'Loading...';

        deleteModal.show();

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
    }

    function openCircleDrawer(id) {
        const c = circlesData.find(x => x.id === String(id));
        if (!c) return;

        document.getElementById('circle-view-full-btn').href = c.show_url;
        document.getElementById('circle-quick-edit-btn').href = c.edit_url;

        const deleteBtn = document.getElementById('circle-drawer-delete-btn');
        if (deleteBtn) {
            deleteBtn.onclick = function(e) {
                e.preventDefault();
                closeCircleDrawer();
                triggerCircleDeleteModal(c.destroy_url, c.name, c.members_count, c.id);
            };
        }

        const coverHtml = c.cover_image_url 
            ? '<img src="' + c.cover_image_url + '" alt="' + c.name + '" class="w-full h-full object-cover"/>' 
            : '<i class="bi bi-people text-slate-400 text-xl"></i>';

        const statusClass = c.status === 'Active' 
            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' 
            : 'bg-slate-100 text-slate-700 border border-slate-200';

        const statusDot = c.status === 'Active' ? '• ' : '';

        document.getElementById('circle-drawer-body').innerHTML = `
            <div class="flex items-center gap-3.5 mb-2">
                <div class="w-14 h-14 rounded-xl overflow-hidden flex-none border border-slate-200 bg-slate-100 flex items-center justify-center">
                    ${coverHtml}
                </div>
                <div>
                    <div class="font-display font-semibold text-[17px] text-slate-900">${c.name}</div>
                    <div class="text-[12px] text-slate-400 font-mono mt-0.5">${c.city} • ${c.country}</div>
                </div>
            </div>
            <div class="flex items-center gap-2 mb-6">
                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full ${statusClass}">
                    ${statusDot}${c.status}
                </span>
                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-600 border border-indigo-200 uppercase">
                    ${c.type}
                </span>
                <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 border border-amber-200 inline-flex items-center gap-1">
                    <i class="bi bi-trophy text-amber-500 text-xs"></i> ${c.rank}
                </span>
            </div>

            <div class="space-y-5 text-[12.5px] pb-4">
                <div>
                    <div class="font-display font-semibold text-[11px] uppercase tracking-wider text-indigo-600 mb-2 flex items-center gap-1.5">
                        <span>⭕</span> CIRCLE INFO GROUP
                    </div>
                    <div class="space-y-2.5 border border-slate-200/80 rounded-xl p-3.5 bg-[#f8fafc]">
                        <div class="flex justify-between gap-4"><span class="text-slate-400">Founder</span><span class="text-slate-800 font-medium">${c.founder}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">City</span><span class="text-slate-800 font-medium">${c.city}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Type</span><span class="text-slate-800 font-medium">${c.type}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Peers Count</span><a href="${c.members_url}" class="text-indigo-600 font-semibold no-underline">${c.members_count} members</a></div>
                        <div class="flex justify-between"><span class="text-slate-400">Meeting Mode</span><span class="text-slate-800 font-medium">${c.meeting_mode}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Meeting Frequency</span><span class="text-slate-800 font-medium">${c.meeting_frequency}</span></div>
                    </div>
                </div>

                <div>
                    <div class="font-display font-semibold text-[11px] uppercase tracking-wider text-indigo-600 mb-2 flex items-center gap-1.5">
                        <i class="bi bi-globe admin-icon me-1" aria-hidden="true"></i> LEADERSHIP & REGION
                    </div>
                    <div class="space-y-2.5 border border-slate-200/80 rounded-xl p-3.5 bg-[#f8fafc]">
                        <div class="flex justify-between"><span class="text-slate-400">Director</span><span class="text-slate-800 font-medium">${c.director}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Circle Stage</span><span class="text-slate-800 font-medium">${c.circle_stage}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Country</span><span class="text-slate-800 font-medium">${c.country}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Industry Tags</span><span class="text-slate-800 font-medium">${c.industry_tags}</span></div>
                    </div>
                </div>

                <div>
                    <div class="font-display font-semibold text-[11px] uppercase tracking-wider text-amber-500 mb-2 flex items-center gap-1.5">
                        <span>⭐</span> RANKING & DETAILS
                    </div>
                    <div class="space-y-2.5 border border-slate-200/80 rounded-xl p-3.5 bg-[#f8fafc]">
                        <div class="flex justify-between"><span class="text-slate-400">Rank</span><span class="text-slate-800 font-medium">${c.rank} (${c.rank_title})</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Launch Date</span><span class="text-slate-800 font-medium">${c.launch_date}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Created At</span><span class="text-slate-800 font-medium">${c.created_at}</span></div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('circle-drawer').classList.remove('drawer-hidden');
        document.getElementById('circle-drawer-scrim').classList.remove('hidden');
    }

    function closeCircleDrawer() {
        document.getElementById('circle-drawer').classList.add('drawer-hidden');
        document.getElementById('circle-drawer-scrim').classList.add('hidden');
    }

    function toggleAdvancedFilters(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        const container = document.getElementById('advancedFiltersCollapse');
        const btn = document.getElementById('btnAdvancedFilters');
        if (!container) return;

        const isHidden = container.classList.contains('hidden') || container.style.display === 'none' || getComputedStyle(container).display === 'none';

        if (isHidden) {
            container.classList.remove('hidden');
            container.style.display = 'block';
            if (btn) {
                btn.setAttribute('aria-expanded', 'true');
                btn.classList.add('chip-active');
            }
        } else {
            container.classList.add('hidden');
            container.style.display = 'none';
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
                btn.classList.remove('chip-active');
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const hasActiveFilters = @json($hasAdvancedFilters);
        if (hasActiveFilters) {
            const container = document.getElementById('advancedFiltersCollapse');
            const btn = document.getElementById('btnAdvancedFilters');
            if (container) {
                container.classList.remove('hidden');
                container.style.display = 'block';
            }
            if (btn) {
                btn.setAttribute('aria-expanded', 'true');
                btn.classList.add('chip-active');
            }
        }

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

        document.querySelectorAll('.btn-delete-circle').forEach(button => {
            button.addEventListener('click', function (e) {
                e.stopPropagation();
                const url = this.getAttribute('data-url');
                const name = this.getAttribute('data-name');
                const members = this.getAttribute('data-members');
                const id = this.getAttribute('data-id');
                triggerCircleDeleteModal(url, name, members, id);
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
