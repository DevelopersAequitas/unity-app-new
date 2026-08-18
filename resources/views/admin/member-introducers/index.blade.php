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
                    <tbody class="divide-y divide-gray-200/50">
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
                                        <div class="font-medium t1 text-[12.5px] whitespace-nowrap">
                                            @if(!empty($introducer->id))
                                                <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $introducer->id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                                    {{ $introducerName !== '' ? $introducerName : '-' }}
                                                </a>
                                            @else
                                                {{ $introducerName !== '' ? $introducerName : '-' }}
                                            @endif
                                        </div>
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
                                            <a href="#" data-peer-id="{{ $parentIntroducer->id }}" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $parentIntroducer->id }}', event);" class="text-indigo-600 hover:text-indigo-800 font-medium text-[12.5px] no-underline hover:underline whitespace-nowrap">
                                                {{ $parentIntroducerName }}
                                            </a>
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
                                            <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $introducer->id }}', event);" class="no-underline">
                                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">{{ $introducedCount }}</span>
                                            </a>
                                        @endif
                                    @else
                                        <span class="t3">0</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <button type="button" onclick="openIntroducedPeersModal('{{ $introducer->id }}', '{{ addslashes($introducerName) }}')" class="px-2.5 py-1 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition inline-flex items-center gap-1">
                                            <i class="bi bi-people-fill text-indigo-500"></i>Introduced List
                                        </button>
                                        <button type="button" onclick="openCreativeModal('{{ $introducer->id }}', '{{ addslashes($introducerName) }}')" class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition inline-flex items-center gap-1 shadow-sm">
                                            <i class="bi bi-stars"></i>Creative
                                        </button>
                                        @if ($canEditUsers)
                                            <a href="{{ route('admin.users.edit', $introducer->id) }}#introduced-tab" class="px-2 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1" title="View Profile">
                                                <i class="bi bi-person"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
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
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
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
                                        <div class="font-medium t1 text-[12.5px] whitespace-nowrap">
                                            @if(!empty($introducer->id))
                                                <a href="#" data-peer-id="{{ $introducer->id }}" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $introducer->id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                                    {{ $introducerName !== '' ? $introducerName : '-' }}
                                                </a>
                                            @else
                                                {{ $introducerName !== '' ? $introducerName : '-' }}
                                            @endif
                                        </div>
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
                                            <a href="#" data-peer-id="{{ $parentIntroducer->id }}" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $parentIntroducer->id }}', event);" class="text-indigo-600 hover:text-indigo-800 font-medium text-[12.5px] no-underline hover:underline whitespace-nowrap">
                                                {{ $parentIntroducerName }}
                                            </a>
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
                                        <button type="button" onclick="openIntroducedPeersModal('{{ $introducer->id }}', '{{ addslashes($introducerName) }}')" class="no-underline border-0 bg-transparent p-0">
                                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200 cursor-pointer hover:bg-indigo-100 transition">{{ $introducedCount }}</span>
                                        </button>
                                    @else
                                        <span class="t3">0</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <button type="button" onclick="openIntroducedPeersModal('{{ $introducer->id }}', '{{ addslashes($introducerName) }}')" class="px-2.5 py-1 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition inline-flex items-center gap-1">
                                            <i class="bi bi-people-fill text-indigo-500"></i>Introduced List
                                        </button>
                                        <button type="button" onclick="openCreativeModal('{{ $introducer->id }}', '{{ addslashes($introducerName) }}')" class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition inline-flex items-center gap-1 shadow-sm">
                                            <i class="bi bi-stars"></i>Creative
                                        </button>
                                        @if ($canEditUsers)
                                            <a href="{{ route('admin.users.edit', $introducer->id) }}#introduced-tab" class="px-2 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1" title="View Profile">
                                                <i class="bi bi-person"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
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

        {{-- Section C: Track 1 Growth Honours Recognition Creatives Showcase --}}
        <div class="rounded-xl border bs surface overflow-hidden mb-4 mt-6">
            <div class="px-5 py-4 surface-2 border-b bs flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h6 class="font-display font-semibold text-sm text-amber-500 uppercase tracking-wider m-0 flex items-center gap-2">
                        <i class="bi bi-award-fill text-amber-500 text-lg"></i>Track 1 Growth Honours Recognition Creatives
                    </h6>
                    <p class="text-xs t3 m-0 mt-1">Official Peers Global Growth Honour System (Canva Design Layout) — All 12 Recognition Levels</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="chip px-3 py-1 text-xs font-semibold bg-amber-500/10 text-amber-600 border-amber-300">
                        <i class="bi bi-palette me-1"></i>12 Canva Levels
                    </span>
                </div>
            </div>

            {{-- Peer Creative Generator Dropdown Control Bar --}}
            <div class="px-5 py-3.5 bg-gradient-to-r from-amber-500/10 via-surface-2 to-amber-500/5 border-b border-amber-500/20 flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-500 flex items-center justify-center font-bold text-sm">
                        <i class="bi bi-person-badge-fill"></i>
                    </span>
                    <div>
                        <div class="text-xs font-bold t1">Generate &amp; Post Peer Recognition Creative</div>
                        <div class="text-[11px] t3">Select any peer from the dropdown to preview their creative, view details, and post to Timeline</div>
                    </div>
                </div>
                <div class="flex items-center gap-2.5 flex-wrap flex-1 max-w-2xl justify-end">
                    {{-- Peer Dropdown --}}
                    <select id="sectionCPeerSelect" class="form-select form-select-sm text-xs rounded-xl surface border bs t1 flex-1 min-w-[220px] py-2 px-3 focus:border-amber-500">
                        <option value="">-- Select Member / Peer --</option>
                        @foreach($allIntroducers as $peer)
                            @php
                                $pName = $peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));
                                $pName = $pName ?: ($peer->name ?? 'Peer Member');
                                $pCompany = $peer->company_name ?? $peer->company ?? $peer->business_name ?? '';
                                $pCity = $peer->city->name ?? $peer->city ?? '';
                                $pSub = array_filter([$pCompany, $pCity]);
                                $pSubStr = !empty($pSub) ? ' ('.implode(' • ', $pSub).')' : '';
                            @endphp
                            <option value="{{ $peer->id }}" data-name="{{ addslashes($pName) }}" data-count="{{ $peer->introduced_members_count ?? 1 }}">
                                {{ $pName }}{{ $pSubStr }} — {{ $peer->introduced_members_count }} {{ $peer->introduced_members_count === 1 ? 'Peer' : 'Peers' }}
                            </option>
                        @endforeach
                    </select>

                    {{-- Level Dropdown --}}
                    <select id="sectionCLevelSelect" class="form-select form-select-sm text-xs rounded-xl surface border bs t1 w-auto py-2 px-3 focus:border-amber-500">
                        <option value="0">⭐ Auto (Earned Level)</option>
                        @foreach($growthHonours as $threshold => $h)
                            <option value="{{ $threshold }}">{{ $h['title'] }} ({{ $threshold }} {{ $threshold === 1 ? 'Peer' : 'Peers' }})</option>
                        @endforeach
                    </select>

                    {{-- Action Button --}}
                    <button type="button" onclick="generateFromSectionCDropdown()" class="btn btn-sm bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-black font-bold text-xs rounded-xl px-4 py-2 shadow-lg inline-flex items-center gap-1.5 transition">
                        <i class="bi bi-stars"></i> Generate Creative &amp; Post
                    </button>
                </div>
            </div>

            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @foreach ($growthHonours as $threshold => $honour)
                    <div class="rounded-2xl border border-amber-500/30 overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 flex flex-col justify-between" style="background-color: #070D1A;">
                        {{-- Canva Graphic Preview Image --}}
                        <div class="relative overflow-hidden bg-slate-900 aspect-[4/5] flex items-center justify-center border-b border-amber-500/20">
                            @if(!empty($honour['badge_image']) && file_exists(public_path($honour['badge_image'])))
                                <img src="{{ asset($honour['badge_image']) }}" alt="{{ $honour['title'] }}" class="w-full h-full object-cover">
                            @else
                                <div class="p-6 text-center">
                                    <div class="text-xs font-black text-amber-400 uppercase tracking-widest">BIG CONGRATULATIONS</div>
                                    <div class="text-2xl font-black text-white uppercase my-2">{{ $honour['title'] }}</div>
                                    <div class="text-xs text-slate-300 italic">"{{ $honour['compliment'] }}"</div>
                                </div>
                            @endif
                            <div class="absolute top-3 right-3">
                                <span class="chip px-2.5 py-1 text-[11px] font-bold bg-amber-500/90 text-black border-amber-400 shadow">
                                    {{ $honour['required_count'] }} {{ $honour['required_count'] === 1 ? 'Peer' : 'Peers' }}
                                </span>
                            </div>
                        </div>

                        {{-- Info Bar & Action Button --}}
                        <div class="p-3 surface-2 border-t border-amber-500/20 text-center space-y-2">
                            <div class="text-xs font-bold t1 flex items-center justify-center gap-1.5">
                                <span class="text-amber-500 font-extrabold">{{ $honour['title'] }}</span>
                                <span class="t3 text-[11px]">({{ $honour['required_count'] }} {{ $honour['required_count'] === 1 ? 'Introduced' : 'Introduced' }})</span>
                            </div>
                            <button type="button" onclick="openCanvaLevelModal('{{ $honour['title'] }}', '{{ asset($honour['badge_image']) }}', '{{ addslashes($honour['compliment']) }}', '{{ addslashes($honour['caption_template'] ?? '') }}', '{{ $honour['hashtag'] ?? '' }}', {{ $honour['required_count'] }})" class="btn btn-sm btn-outline-warning text-xs font-semibold rounded-lg px-3 py-1.5 w-full">
                                <i class="bi bi-image me-1"></i> Preview {{ $honour['title'] }} Creative
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- MODAL 1: Introduced Peers Full Data List --}}
<div class="modal fade" id="introducedPeersModal" tabindex="-1" aria-labelledby="introducedPeersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-2xl border-0 shadow-2xl overflow-hidden surface">
            <div class="modal-header px-6 py-4 surface-2 border-b bs flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600/10 text-indigo-600 flex items-center justify-center text-lg font-bold">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-display font-bold text-base t1 m-0" id="introducedPeersModalLabel">
                            Introduced Peers List
                        </h5>
                        <p class="text-xs t3 m-0" id="introducedPeersModalSub">
                            Showing all peers introduced by <span id="introducerModalName" class="font-semibold text-indigo-600"></span>
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close focus:outline-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-6 space-y-4">
                {{-- Search inside Modal --}}
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div class="relative flex-grow max-w-md">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 t3 text-xs"></i>
                        <input type="text" id="modalPeersSearchInput" class="w-full pl-9 pr-3 py-2 rounded-xl border bs surface t1 text-xs outline-none focus-ring" placeholder="Filter introduced peers by name, email, company, city...">
                    </div>
                    <div id="modalPeersBadgeCount" class="chip px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">
                        Total: 0 Peers
                    </div>
                </div>

                {{-- Table of Introduced Peers --}}
                <div class="overflow-x-auto rounded-xl border bs surface">
                    <table class="min-w-full border-collapse text-[13px]">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                                <th class="px-4 py-3 text-left">Peer Member</th>
                                <th class="px-4 py-3 text-left">Company & Designation</th>
                                <th class="px-4 py-3 text-left">City</th>
                                <th class="px-4 py-3 text-left">Contact Info</th>
                                <th class="px-4 py-3 text-center">Membership Status</th>
                                <th class="px-4 py-3 text-center">Joined Date</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="modalPeersTableBody" class="divide-y divide-gray-200/50">
                            {{-- Dynamically populated via JS --}}
                        </tbody>
                    </table>
                    <div id="modalPeersLoading" class="p-8 text-center hidden">
                        <div class="spinner-border text-indigo-600 spinner-border-sm" role="status"></div>
                        <span class="ms-2 text-xs t3">Loading introduced peers data...</span>
                    </div>
                    <div id="modalPeersEmpty" class="p-8 text-center text-xs t3 hidden">
                        No introduced peers found.
                    </div>
                </div>
            </div>
            <div class="modal-footer px-6 py-3 surface-2 border-t bs flex justify-between items-center">
                <span class="text-xs t3">Peers Global Introduced Members Data</span>
                <button type="button" class="px-4 py-2 rounded-xl border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL 2: Member Introducer Creative & Timeline Post --}}
<div class="modal fade" id="creativeModal" tabindex="-1" aria-labelledby="creativeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-2xl border-0 shadow-2xl overflow-hidden surface">
            <div class="modal-header px-6 py-4 surface-2 border-b bs flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-lg font-bold">
                        <i class="bi bi-stars"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-display font-bold text-base t1 m-0" id="creativeModalLabel">
                            Member Introducer Recognition Creative &amp; Timeline Post
                        </h5>
                        <p class="text-xs t3 m-0">
                            Growth Honour Recognition for <span id="creativeIntroducerName" class="font-semibold text-indigo-600"></span>
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close focus:outline-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Modal Top Switcher Dropdowns Bar --}}
            <div id="modalDropdownsBar" class="px-6 py-3 surface-2 border-b bs flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-2 flex-1 min-w-[240px]">
                    <label for="modalPeerSwitcher" class="text-xs font-semibold t3 m-0 whitespace-nowrap"><i class="bi bi-person-fill text-indigo-500 me-1"></i>Switch Peer:</label>
                    <select id="modalPeerSwitcher" onchange="onModalPeerSwitch(this.value)" class="form-select form-select-sm text-xs rounded-xl surface border bs t1 py-1.5 px-3">
                        @foreach($allIntroducers as $peer)
                            @php
                                $pName = $peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));
                                $pName = $pName ?: ($peer->name ?? 'Peer Member');
                            @endphp
                            <option value="{{ $peer->id }}" data-name="{{ addslashes($pName) }}" data-count="{{ $peer->introduced_members_count ?? 1 }}">
                                {{ $pName }} ({{ $peer->introduced_members_count }} {{ $peer->introduced_members_count === 1 ? 'introduced' : 'introduced' }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <label for="modalLevelSwitcher" class="text-xs font-semibold t3 m-0 whitespace-nowrap"><i class="bi bi-trophy-fill text-amber-500 me-1"></i>Recognition Level:</label>
                    <select id="modalLevelSwitcher" onchange="onModalLevelSwitch(this.value)" class="form-select form-select-sm text-xs rounded-xl surface border bs t1 py-1.5 px-3">
                        <option value="0">⭐ Auto (Earned Level)</option>
                        @foreach($growthHonours as $threshold => $h)
                            <option value="{{ $threshold }}">{{ $h['title'] }} ({{ $threshold }} {{ $threshold === 1 ? 'Peer' : 'Peers' }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="modal-body p-6">
                <div id="creativeLoading" class="p-12 text-center">
                    <div class="spinner-border text-indigo-600" role="status"></div>
                    <p class="mt-3 text-xs t3">Generating high-resolution recognition creative...</p>
                </div>
                
                <div id="creativeContent" class="grid grid-cols-1 lg:grid-cols-12 gap-6 hidden">
                    {{-- Left Column: Peer Details Card, Level Header, & Caption --}}
                    <div class="lg:col-span-5 space-y-4 flex flex-col justify-between">
                        <div class="space-y-3">
                            {{-- Peer Profile Details Card --}}
                            <div id="modalPeerDetailsCard" class="p-4 rounded-xl surface-2 border bs space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-bold text-indigo-600 uppercase tracking-wider flex items-center gap-1">
                                        <i class="bi bi-person-check-fill"></i>Peer Member Details
                                    </span>
                                    <span id="modalPeerStatusBadge" class="chip px-2.5 py-0.5 text-[11px] font-bold bg-indigo-500/10 text-indigo-600 border-indigo-300">
                                        Member
                                    </span>
                                </div>
                                <div class="space-y-1 text-xs">
                                    <div class="flex justify-between items-center">
                                        <span class="t3">Peer Name:</span>
                                        <span id="modalPeerNameVal" class="font-bold t1"></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="t3">Company &amp; City:</span>
                                        <span id="modalPeerCompanyCityVal" class="font-semibold t1 text-right"></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="t3">Designation / Category:</span>
                                        <span id="modalPeerDesignationVal" class="font-medium t2 text-right"></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="t3">Total Peers Introduced:</span>
                                        <span id="modalPeerCountVal" class="font-bold text-amber-500"></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Growth Honour Level Header Card --}}
                            <div class="p-4 rounded-xl surface-2 border bs space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-bold text-amber-500 uppercase tracking-wider flex items-center gap-1">
                                        <i class="bi bi-trophy-fill"></i>Track 1 Growth Honour
                                    </span>
                                    <span id="creativeLevelTitle" class="chip px-3 py-1 text-xs font-bold bg-amber-500/10 text-amber-600 border-amber-300">
                                        CONNECTOR
                                    </span>
                                </div>
                                <h6 id="creativeCompliment" class="font-semibold text-sm t1 m-0 leading-snug">
                                    "Every movement begins with one connection."
                                </h6>
                            </div>

                            {{-- Social Media Caption Box --}}
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-semibold t1 m-0">Social Media &amp; Timeline Caption</label>
                                    <button type="button" onclick="copyCaptionToClipboard()" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold no-underline inline-flex items-center gap-1">
                                        <i class="bi bi-clipboard"></i>Copy Text
                                    </button>
                                </div>
                                <textarea id="creativeCaptionText" rows="6" readonly class="w-full p-3 rounded-xl border bs surface t1 text-xs outline-none resize-none font-mono leading-relaxed"></textarea>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="space-y-2.5 pt-3 border-t bs">
                            <button type="button" id="btnPostToTimeline" onclick="postCreativeToTimeline()" class="w-full py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm transition shadow-lg inline-flex items-center justify-center gap-2">
                                <i class="bi bi-broadcast"></i>
                                <span>Post Creative to Timeline</span>
                            </button>
                            <a id="btnDownloadCreative" href="#" download="growth_honour_creative.webp" target="_blank" class="w-full py-2.5 px-4 rounded-xl border bs surface hover:surface-2 t1 font-semibold text-xs transition inline-flex items-center justify-center gap-2 no-underline">
                                <i class="bi bi-download"></i>Download High-Res Image
                            </a>
                            <div id="timelinePostStatus" class="mt-2 text-center text-xs hidden"></div>
                        </div>
                    </div>

                    {{-- Right Column: Live Creative Image Preview --}}
                    <div class="lg:col-span-7 flex items-center justify-center bg-slate-950/40 rounded-2xl p-4 border bs overflow-hidden min-h-[450px]">
                        <img id="creativePreviewImg" src="" alt="Creative Preview" class="max-h-[580px] w-auto object-contain rounded-xl shadow-2xl transition hover:scale-[1.01]">
                    </div>
                </div>
            </div>
            <div class="modal-footer px-6 py-3 surface-2 border-t bs flex justify-between items-center">
                <span class="text-xs t3">Peers Global 1 Million Mission Recognition System</span>
                <button type="button" class="px-4 py-2 rounded-xl border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentActiveIntroducerId = null;
let currentActiveIntroducerName = '';
let currentActiveCount = 0;

function generateFromSectionCDropdown() {
    const peerSelect = document.getElementById('sectionCPeerSelect');
    const levelSelect = document.getElementById('sectionCLevelSelect');
    
    const introducerId = peerSelect.value;
    if (!introducerId) {
        alert('Please select a peer from the dropdown first.');
        peerSelect.focus();
        return;
    }
    
    const selectedOption = peerSelect.options[peerSelect.selectedIndex];
    const introducerName = selectedOption.getAttribute('data-name') || selectedOption.text;
    const count = parseInt(levelSelect.value) || 0;
    
    openCreativeModal(introducerId, introducerName, count);
}

function onModalPeerSwitch(newPeerId) {
    if (!newPeerId) return;
    const peerSelect = document.getElementById('modalPeerSwitcher');
    const selectedOption = peerSelect.options[peerSelect.selectedIndex];
    const introducerName = selectedOption.getAttribute('data-name') || selectedOption.text;
    const count = parseInt(document.getElementById('modalLevelSwitcher').value) || 0;
    
    openCreativeModal(newPeerId, introducerName, count);
}

function onModalLevelSwitch(newCount) {
    if (!currentActiveIntroducerId) return;
    const count = parseInt(newCount) || 0;
    openCreativeModal(currentActiveIntroducerId, currentActiveIntroducerName, count);
}

function openIntroducedPeersModal(introducerId, introducerName) {
    currentActiveIntroducerId = introducerId;
    currentActiveIntroducerName = introducerName;
    
    document.getElementById('introducerModalName').textContent = introducerName;
    document.getElementById('modalPeersSearchInput').value = '';
    
    const modalEl = document.getElementById('introducedPeersModal');
    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();
    
    fetchIntroducedPeers(introducerId, '');
}

document.getElementById('modalPeersSearchInput')?.addEventListener('input', (e) => {
    if (currentActiveIntroducerId) {
        fetchIntroducedPeers(currentActiveIntroducerId, e.target.value.trim());
    }
});

function fetchIntroducedPeers(introducerId, search) {
    const tableBody = document.getElementById('modalPeersTableBody');
    const loadingEl = document.getElementById('modalPeersLoading');
    const emptyEl = document.getElementById('modalPeersEmpty');
    const badgeCountEl = document.getElementById('modalPeersBadgeCount');

    tableBody.innerHTML = '';
    loadingEl.classList.remove('hidden');
    emptyEl.classList.add('hidden');

    const url = `/admin/member-introducers/${introducerId}/introduced-peers?q=${encodeURIComponent(search)}`;

    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        loadingEl.classList.add('hidden');
        if (!data.success || !data.introduced_peers || data.introduced_peers.length === 0) {
            emptyEl.classList.remove('hidden');
            badgeCountEl.textContent = 'Total: 0 Peers';
            return;
        }

        badgeCountEl.textContent = `Total: ${data.introduced_peers.length} Peers`;

        data.introduced_peers.forEach(peer => {
            const tr = document.createElement('tr');
            tr.className = 'hover:surface-2 transition border-b bs';

            const avatarHtml = peer.avatar 
                ? `<img src="${peer.avatar}" alt="${peer.name}" class="w-full h-full object-cover">`
                : `<div class="w-full h-full bg-indigo-600 text-white font-bold flex items-center justify-center text-xs">${(peer.name || 'P').charAt(0).toUpperCase()}</div>`;

            tr.innerHTML = `
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full overflow-hidden flex-none border bs">
                            ${avatarHtml}
                        </div>
                        <div>
                            <div class="font-semibold t1 text-xs">${peer.name}</div>
                            <div class="text-[11px] t3">${peer.email || '-'}</div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <div class="font-medium t1 text-xs">${peer.company || '-'}</div>
                    <div class="text-[11px] t3">${peer.designation || '-'}</div>
                </td>
                <td class="px-4 py-3 text-xs t1">${peer.city || '-'}</td>
                <td class="px-4 py-3 text-xs t1">${peer.phone || '-'}</td>
                <td class="px-4 py-3 text-center">
                    <span class="chip px-2.5 py-0.5 text-[11px] font-semibold bg-slate-100 text-slate-700 border-slate-200 capitalize">${(peer.membership_status || 'peer').replace(/_/g, ' ')}</span>
                </td>
                <td class="px-4 py-3 text-center text-xs t3">${peer.joined_at}</td>
                <td class="px-4 py-3 text-right">
                    <a href="/admin/users/${peer.id}/edit" target="_blank" class="px-2.5 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1">
                        <i class="bi bi-eye"></i>Profile
                    </a>
                </td>
            `;
            tableBody.appendChild(tr);
        });
    })
    .catch(err => {
        loadingEl.classList.add('hidden');
        emptyEl.textContent = 'Error loading data: ' + err.message;
        emptyEl.classList.remove('hidden');
    });
}

function openCanvaLevelModal(title, imageUrl, compliment, captionTemplate, hashtag, count) {
    currentActiveIntroducerId = null;
    currentActiveIntroducerName = '';
    currentActiveCount = count;

    document.getElementById('modalDropdownsBar')?.classList.add('hidden');
    document.getElementById('modalPeerDetailsCard')?.classList.add('hidden');

    document.getElementById('creativeIntroducerName').textContent = 'Track 1 Growth Honour Template';
    document.getElementById('creativeLevelTitle').textContent = title;
    document.getElementById('creativeCompliment').textContent = `"${compliment}"`;

    let caption = `Congratulations to [Member Name], [Company], on being recognised as a Peers Global ${title}. Proud to have you contributing to the Peers Global mission of impacting 1 Million Entrepreneurs.\n\n#PeersGlobal ${hashtag} #CommunityOfCollaboration #1MillionEntrepreneurs`;
    if (captionTemplate) {
        caption = captionTemplate.replace('{name}', '[Member Name]').replace('{company}', '[Company]') + `\n\n#PeersGlobal ${hashtag} #CommunityOfCollaboration #1MillionEntrepreneurs`;
    }
    document.getElementById('creativeCaptionText').value = caption;

    const imgEl = document.getElementById('creativePreviewImg');
    imgEl.src = imageUrl;

    const btnDownload = document.getElementById('btnDownloadCreative');
    btnDownload.href = imageUrl;
    btnDownload.download = `${title}_Recognition_Creative.png`;

    document.getElementById('btnPostToTimeline').classList.add('hidden');
    document.getElementById('timelinePostStatus').classList.add('hidden');

    document.getElementById('creativeLoading').classList.add('hidden');
    document.getElementById('creativeContent').classList.remove('hidden');

    const modalEl = document.getElementById('creativeModal');
    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();
}

function openCreativeModal(introducerId, introducerName, count = 0) {
    currentActiveIntroducerId = introducerId;
    currentActiveIntroducerName = introducerName;
    currentActiveCount = count;

    // Show and sync dropdowns
    document.getElementById('modalDropdownsBar')?.classList.remove('hidden');
    document.getElementById('modalPeerDetailsCard')?.classList.remove('hidden');

    const modalPeerSelect = document.getElementById('modalPeerSwitcher');
    if (modalPeerSelect) {
        modalPeerSelect.value = introducerId;
    }
    const modalLevelSelect = document.getElementById('modalLevelSwitcher');
    if (modalLevelSelect) {
        modalLevelSelect.value = count;
    }

    document.getElementById('creativeIntroducerName').textContent = introducerName;
    document.getElementById('creativeLoading').classList.remove('hidden');
    document.getElementById('creativeContent').classList.add('hidden');
    document.getElementById('timelinePostStatus').classList.add('hidden');
    document.getElementById('btnPostToTimeline').classList.remove('hidden');

    const modalEl = document.getElementById('creativeModal');
    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();

    const previewUrl = `/admin/member-introducers/${introducerId}/creative-preview` + (count ? `?count=${count}` : '');

    fetch(previewUrl, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('creativeLevelTitle').textContent = data.meta.title;
            document.getElementById('creativeCompliment').textContent = `"${data.meta.compliment}"`;
            document.getElementById('creativeCaptionText').value = data.caption;
            
            // Populate Peer Details Card
            if (data.peer) {
                document.getElementById('modalPeerNameVal').textContent = data.peer.name || introducerName;
                const compCity = [data.peer.company, data.peer.city].filter(Boolean).join(' • ');
                document.getElementById('modalPeerCompanyCityVal').textContent = compCity || '-';
                document.getElementById('modalPeerDesignationVal').textContent = data.peer.designation || 'Peers Global Member';
                document.getElementById('modalPeerCountVal').textContent = `${data.peer.introduced_count} ${data.peer.introduced_count === 1 ? 'Peer' : 'Peers'}`;
                document.getElementById('modalPeerStatusBadge').textContent = (data.peer.membership_status || 'Member').replace(/_/g, ' ');
            }

            const imgEl = document.getElementById('creativePreviewImg');
            imgEl.src = data.preview_url;
            
            const btnDownload = document.getElementById('btnDownloadCreative');
            btnDownload.href = data.preview_url;
            btnDownload.download = `Growth_Honour_${introducerName.replace(/\s+/g, '_')}.webp`;

            document.getElementById('creativeLoading').classList.add('hidden');
            document.getElementById('creativeContent').classList.remove('hidden');
        }
    })
    .catch(err => {
        document.getElementById('creativeLoading').innerHTML = `<p class="text-red-500 text-xs">Failed generating creative: ${err.message}</p>`;
    });
}

function copyCaptionToClipboard() {
    const captionEl = document.getElementById('creativeCaptionText');
    captionEl.select();
    navigator.clipboard.writeText(captionEl.value).then(() => {
        alert('Caption copied to clipboard!');
    });
}

function postCreativeToTimeline() {
    if (!currentActiveIntroducerId) return;

    const btn = document.getElementById('btnPostToTimeline');
    const statusEl = document.getElementById('timelinePostStatus');
    const originalText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Posting to Timeline...`;
    statusEl.classList.add('hidden');

    fetch(`/admin/member-introducers/${currentActiveIntroducerId}/post-creative`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            count: currentActiveCount
        })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;

        if (data.success) {
            statusEl.className = 'mt-2 text-center text-xs font-semibold text-emerald-600 bg-emerald-50 p-2 rounded-xl border border-emerald-200 block';
            statusEl.textContent = '🎉 ' + data.message;
        } else {
            statusEl.className = 'mt-2 text-center text-xs font-semibold text-rose-600 bg-rose-50 p-2 rounded-xl border border-rose-200 block';
            statusEl.textContent = '❌ ' + (data.error || 'Failed to post creative to timeline.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        statusEl.className = 'mt-2 text-center text-xs font-semibold text-rose-600 bg-rose-50 p-2 rounded-xl border border-rose-200 block';
        statusEl.textContent = '❌ Error: ' + err.message;
    });
}
</script>
@endpush


