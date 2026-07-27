@extends('admin.layouts.app')

@section('title', 'Member Introducers')

@include('admin.partials.grid-head')

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
    {{-- Section A: Top 10 Member Introducers --}}
    @if ($topIntroducers->isNotEmpty())
        <div class="rounded-xl border bs surface overflow-hidden mb-4">
            <div class="px-4 py-3 surface-2 border-b bs flex items-center justify-between">
                <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0 flex items-center gap-2">
                    <i class="bi bi-trophy text-amber-500"></i>Top 10 Member Introducers
                </h6>
            </div>
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="width: 60px;">Rank</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Introduced By</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Members Introduced</th>
                            @if ($canEditUsers)
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @foreach ($topIntroducers as $index => $introducer)
                            @php
                                $introducerName = $introducer->name ?? trim((($introducer->first_name ?? '') . ' ' . ($introducer->last_name ?? '')));
                                $introducerAvatar = $introducer->profile_photo_url ?? ($introducer->profile_photo_file_id ? url('/api/v1/files/' . $introducer->profile_photo_file_id) : null);
                                $introducerGradientIndex = abs(crc32((string) $introducer->id)) % 5;

                                // Parse city
                                $introducerCityModel = $introducer->getRelation('city') ?? $introducer->cityRelation ?? null;
                                $introducerRawCity = $introducerCityModel->name ?? $introducer->city ?? '';
                                if (is_string($introducerRawCity)) {
                                    $introducerRawCity = trim($introducerRawCity);
                                    if (str_starts_with($introducerRawCity, '{')) {
                                        $decodedCity = json_decode($introducerRawCity, true);
                                        if (is_array($decodedCity)) {
                                            $introducerCityName = $decodedCity['name'] ?? $decodedCity['label'] ?? $introducerRawCity;
                                        } elseif (preg_match('/name:\s*([^,}]+)/', $introducerRawCity, $matches)) {
                                            $introducerCityName = trim($matches[1], " \t\n\r\0\x0B\"'");
                                        } else {
                                            $introducerCityName = $introducerRawCity;
                                        }
                                    } else {
                                        $introducerCityName = $introducerRawCity;
                                    }
                                } elseif (is_array($introducerRawCity)) {
                                    $introducerCityName = $introducerRawCity['name'] ?? $introducerRawCity['label'] ?? '';
                                } elseif (is_object($introducerRawCity)) {
                                    $introducerCityName = $introducerRawCity->name ?? $introducerRawCity->label ?? '';
                                } else {
                                    $introducerCityName = $introducerRawCity;
                                }
                                
                                if (in_array(strtolower(trim((string)$introducerCityName)), ['', 'no city', 'none', 'null', 'no_city'], true)) {
                                    $introducerCityName = null;
                                }
                                
                                // Parse company
                                $introducerCompany = $introducer->company_name ?? $introducer->company ?? $introducer->business_name ?? '';
                                if (in_array(strtolower(trim((string)$introducerCompany)), ['', 'no company', 'none', 'null', 'no_company', 'peers global'], true)) {
                                    $introducerCompany = null;
                                }
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 font-semibold t1">#{{ $index + 1 }}</td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full overflow-hidden flex-none border bs">
                                            @if ($introducerAvatar)
                                                <img src="{{ $introducerAvatar }}" alt="{{ $introducerName }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full bg-indigo-600 text-white font-bold flex items-center justify-center text-xs">
                                                    {{ strtoupper(substr($introducerName !== '' ? $introducerName : 'U', 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="font-medium t1 text-[12.5px] whitespace-nowrap">{{ $introducerName !== '' ? $introducerName : '-' }}</div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($introducerCompany)
                                        <span class="t1 inline-flex items-center gap-1 whitespace-nowrap text-[12.5px]">
                                            <i class="bi bi-building t3 text-xs"></i>{{ $introducerCompany }}
                                        </span>
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($introducerCityName)
                                        <span class="t1 inline-flex items-center gap-1 whitespace-nowrap text-[12.5px]">
                                            <i class="bi bi-geo-alt t3 text-xs"></i>{{ $introducerCityName }}
                                        </span>
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($introducer->introducedBy)
                                        @php
                                            $parentIntroducer = $introducer->introducedBy;
                                            $parentIntroducerName = $parentIntroducer->name ?? trim((($parentIntroducer->first_name ?? '') . ' ' . ($parentIntroducer->last_name ?? '')));
                                            $parentIntroducerAvatar = $parentIntroducer->profile_photo_url ?? ($parentIntroducer->profile_photo_file_id ? url('/api/v1/files/' . $parentIntroducer->profile_photo_file_id) : null);
                                        @endphp
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full overflow-hidden flex-none border bs">
                                                @if ($parentIntroducerAvatar)
                                                    <img src="{{ $parentIntroducerAvatar }}" alt="{{ $parentIntroducerName }}" class="w-full h-full object-cover">
                                                @else
                                                    <div class="w-full h-full bg-slate-600 text-white font-bold flex items-center justify-center text-[10px]">
                                                        {{ strtoupper(substr($parentIntroducerName !== '' ? $parentIntroducerName : 'U', 0, 1)) }}
                                                    </div>
                                                @endif
                                            </div>
                                            @if ($canEditUsers)
                                                <a href="{{ route('admin.users.edit', $parentIntroducer->id) }}#introduced-tab" class="text-indigo-600 hover:text-indigo-700 font-medium text-[12.5px] no-underline whitespace-nowrap">
                                                    {{ $parentIntroducerName }}
                                                </a>
                                            @else
                                                <a href="{{ route('admin.users.show', $parentIntroducer->id) }}#introduced-tab" class="text-indigo-600 hover:text-indigo-700 font-medium text-[12.5px] no-underline whitespace-nowrap">
                                                    {{ $parentIntroducerName }}
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @php
                                        $introducedCount = (int) $introducer->introduced_members_count;
                                    @endphp
                                    @if ($introducedCount > 0)
                                        @if ($canEditUsers)
                                            <a href="{{ route('admin.users.edit', $introducer->id) }}#introduced-tab" class="no-underline">
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">{{ $introducedCount }}</span>
                                            </a>
                                        @else
                                            <a href="{{ route('admin.users.show', $introducer->id) }}#introduced-tab" class="no-underline">
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">{{ $introducedCount }}</span>
                                            </a>
                                        @endif
                                    @else
                                        <span class="t3">0</span>
                                    @endif
                                </td>
                                @if ($canEditUsers)
                                    <td class="px-3 py-2.5 text-right">
                                        <a href="{{ route('admin.users.edit', $introducer->id) }}#introduced-tab" class="px-2.5 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1">
                                            <i class="bi bi-eye"></i>View
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Section B: All Member Introducers --}}
    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="px-4 py-3 surface-2 border-b bs flex items-center justify-between">
            <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0 flex items-center gap-2">
                <i class="bi bi-people-fill text-indigo-500"></i>All Member Introducers
            </h6>
        </div>
        <div class="p-4">
            {{-- Filter Form --}}
            <form id="introducersFiltersForm" method="GET" class="border bs rounded-xl p-3.5 mb-4 surface-2">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="block text-[11px] t3 mb-1 font-medium" for="peerSearch">Search</label>
                        <input type="text" id="peerSearch" name="q" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" placeholder="Search by name, email, company, city, designation..." value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="block text-[11px] t3 mb-1 font-medium" for="membershipFilter">Membership Status</label>
                        <select id="membershipFilter" name="membership_status" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                            <option value="">All</option>
                            @foreach ($membershipStatuses as $status)
                                <option value="{{ $status }}" @selected(($filters['membership_status'] ?? '') === $status)>{{ $membershipStatusLabels[$status] ?? \Illuminate\Support\Str::headline(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="block text-[11px] t3 mb-1 font-medium" for="startDateFilter">Introduced From</label>
                        <input id="startDateFilter" type="date" name="start_date" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['start_date'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="block text-[11px] t3 mb-1 font-medium" for="endDateFilter">Introduced To</label>
                        <input id="endDateFilter" type="date" name="end_date" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['end_date'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-1 d-flex justify-content-end">
                        <a href="{{ route('admin.member-introducers.index') }}" class="w-full px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline">Clear</a>
                    </div>
                </div>
            </form>

            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">
                                <a href="{{ route('admin.member-introducers.index', array_merge(request()->query(), ['sort' => 'display_name', 'dir' => ($filters['sort'] ?? '') === 'display_name' && ($filters['dir'] ?? '') === 'asc' ? 'desc' : 'asc'])) }}" class="no-underline t1 hover:text-indigo-600 inline-flex items-center gap-1">
                                    Peer Name
                                    @if (($filters['sort'] ?? '') === 'display_name')
                                        <i class="bi bi-arrow-{{ ($filters['dir'] ?? '') === 'asc' ? 'up' : 'down' }}-short text-xs"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Introduced By</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">
                                <a href="{{ route('admin.member-introducers.index', array_merge(request()->query(), ['sort' => 'introduced_members_count', 'dir' => ($filters['sort'] ?? '') === 'introduced_members_count' && ($filters['dir'] ?? '') === 'asc' ? 'desc' : 'asc'])) }}" class="no-underline t1 hover:text-indigo-600 inline-flex items-center gap-1">
                                    Members Introduced
                                    @if (($filters['sort'] ?? '') === 'introduced_members_count')
                                        <i class="bi bi-arrow-{{ ($filters['dir'] ?? '') === 'asc' ? 'up' : 'down' }}-short text-xs"></i>
                                    @endif
                                </a>
                            </th>
                            @if ($canEditUsers)
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($introducers as $introducer)
                            @php
                                $introducerName = $introducer->name ?? trim((($introducer->first_name ?? '') . ' ' . ($introducer->last_name ?? '')));
                                $introducerAvatar = $introducer->profile_photo_url ?? ($introducer->profile_photo_file_id ? url('/api/v1/files/' . $introducer->profile_photo_file_id) : null);
                                $introducerGradientIndex = abs(crc32((string) $introducer->id)) % 5;

                                // Parse city
                                $introducerCityModel = $introducer->getRelation('city') ?? $introducer->cityRelation ?? null;
                                $introducerRawCity = $introducerCityModel->name ?? $introducer->city ?? '';
                                if (is_string($introducerRawCity)) {
                                    $introducerRawCity = trim($introducerRawCity);
                                    if (str_starts_with($introducerRawCity, '{')) {
                                        $decodedCity = json_decode($introducerRawCity, true);
                                        if (is_array($decodedCity)) {
                                            $introducerCityName = $decodedCity['name'] ?? $decodedCity['label'] ?? $introducerRawCity;
                                        } elseif (preg_match('/name:\s*([^,}]+)/', $introducerRawCity, $matches)) {
                                            $introducerCityName = trim($matches[1], " \t\n\r\0\x0B\"'");
                                        } else {
                                            $introducerCityName = $introducerRawCity;
                                        }
                                    } else {
                                        $introducerCityName = $introducerRawCity;
                                    }
                                } elseif (is_array($introducerRawCity)) {
                                    $introducerCityName = $introducerRawCity['name'] ?? $introducerRawCity['label'] ?? '';
                                } elseif (is_object($introducerRawCity)) {
                                    $introducerCityName = $introducerRawCity->name ?? $introducerRawCity->label ?? '';
                                } else {
                                    $introducerCityName = $introducerRawCity;
                                }
                                
                                if (in_array(strtolower(trim((string)$introducerCityName)), ['', 'no city', 'none', 'null', 'no_city'], true)) {
                                    $introducerCityName = null;
                                }
                                
                                // Parse company
                                $introducerCompany = $introducer->company_name ?? $introducer->company ?? $introducer->business_name ?? '';
                                if (in_array(strtolower(trim((string)$introducerCompany)), ['', 'no company', 'none', 'null', 'no_company', 'peers global'], true)) {
                                    $introducerCompany = null;
                                }
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full overflow-hidden flex-none border bs">
                                            @if ($introducerAvatar)
                                                <img src="{{ $introducerAvatar }}" alt="{{ $introducerName }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full bg-indigo-600 text-white font-bold flex items-center justify-center text-xs">
                                                    {{ strtoupper(substr($introducerName !== '' ? $introducerName : 'U', 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="font-medium t1 text-[12.5px] whitespace-nowrap">{{ $introducerName !== '' ? $introducerName : '-' }}</div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($introducerCompany)
                                        <span class="t1 inline-flex items-center gap-1 whitespace-nowrap text-[12.5px]">
                                            <i class="bi bi-building t3 text-xs"></i>{{ $introducerCompany }}
                                        </span>
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($introducerCityName)
                                        <span class="t1 inline-flex items-center gap-1 whitespace-nowrap text-[12.5px]">
                                            <i class="bi bi-geo-alt t3 text-xs"></i>{{ $introducerCityName }}
                                        </span>
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($introducer->introducedBy)
                                        @php
                                            $parentIntroducer = $introducer->introducedBy;
                                            $parentIntroducerName = $parentIntroducer->name ?? trim((($parentIntroducer->first_name ?? '') . ' ' . ($parentIntroducer->last_name ?? '')));
                                            $parentIntroducerAvatar = $parentIntroducer->profile_photo_url ?? ($parentIntroducer->profile_photo_file_id ? url('/api/v1/files/' . $parentIntroducer->profile_photo_file_id) : null);
                                        @endphp
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full overflow-hidden flex-none border bs">
                                                @if ($parentIntroducerAvatar)
                                                    <img src="{{ $parentIntroducerAvatar }}" alt="{{ $parentIntroducerName }}" class="w-full h-full object-cover">
                                                @else
                                                    <div class="w-full h-full bg-slate-600 text-white font-bold flex items-center justify-center text-[10px]">
                                                        {{ strtoupper(substr($parentIntroducerName !== '' ? $parentIntroducerName : 'U', 0, 1)) }}
                                                    </div>
                                                @endif
                                            </div>
                                            @if ($canEditUsers)
                                                <a href="{{ route('admin.users.edit', $parentIntroducer->id) }}#introduced-tab" class="text-indigo-600 hover:text-indigo-700 font-medium text-[12.5px] no-underline whitespace-nowrap">
                                                    {{ $parentIntroducerName }}
                                                </a>
                                            @else
                                                <a href="{{ route('admin.users.show', $parentIntroducer->id) }}#introduced-tab" class="text-indigo-600 hover:text-indigo-700 font-medium text-[12.5px] no-underline whitespace-nowrap">
                                                    {{ $parentIntroducerName }}
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @php
                                        $introducedCount = (int) $introducer->introduced_members_count;
                                    @endphp
                                    @if ($introducedCount > 0)
                                        @if ($canEditUsers)
                                            <a href="{{ route('admin.users.edit', $introducer->id) }}#introduced-tab" class="no-underline">
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">{{ $introducedCount }}</span>
                                            </a>
                                        @else
                                            <a href="{{ route('admin.users.show', $introducer->id) }}#introduced-tab" class="no-underline">
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">{{ $introducedCount }}</span>
                                            </a>
                                        @endif
                                    @else
                                        <span class="t3">0</span>
                                    @endif
                                </td>
                                @if ($canEditUsers)
                                    <td class="px-3 py-2.5 text-right">
                                        <a href="{{ route('admin.users.edit', $introducer->id) }}#introduced-tab" class="px-2.5 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1">
                                            <i class="bi bi-eye"></i>View
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-8 text-xs t3">No introducers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Bottom Toolbar & Pagination --}}
            <div id="grid-pagination" class="flex justify-between items-center mt-4 flex-wrap gap-2 pt-3 border-t bs">
                <div>
                    {{ $introducers->links() }}
                </div>
                <div class="text-xs t3">
                    @if($introducers->total() > 0)
                        Showing <span class="font-semibold t1">{{ $introducers->firstItem() }}-{{ $introducers->lastItem() }}</span> of <span class="font-semibold t1">{{ $introducers->total() }}</span> records
                    @else
                        No records
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

