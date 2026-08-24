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

{{-- Page Top Header & Tab Navigation --}}
<div class="flex items-center justify-between flex-wrap gap-4 mb-4">
    <div>
        <h4 class="font-display font-bold text-lg t1 m-0 flex items-center gap-2">
            <i class="bi bi-person-check-fill text-indigo-600"></i>Member Introducers
        </h4>
        <p class="text-xs t3 m-0 mt-0.5">Track top member introducers, view introduced peers list, preview high-resolution recognition creatives, and post honours directly to Timeline.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.member-introducers.index') }}" class="tab-pill px-4 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-2 transition no-underline {{ $activeTab === 'list' ? 'bg-indigo-600 text-white shadow-md' : 'surface border bs t2 hover:t1 hover:surface-2' }}">
            <i class="bi bi-people-fill"></i>Introducers List <span class="badge {{ $activeTab === 'list' ? 'bg-white text-indigo-700' : 'bg-slate-200 text-slate-700' }} rounded-full px-2 py-0.5 text-[10px]">{{ $allIntroducers->count() }}</span>
        </a>
        <a href="{{ route('admin.member-introducers.index', ['tab' => 'creative']) }}" class="tab-pill px-4 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-2 transition no-underline {{ $activeTab === 'creative' ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-black font-bold shadow-md' : 'surface border bs t2 hover:t1 hover:surface-2' }}">
            <i class="bi bi-stars"></i>Peers Creative Post in Timeline
        </a>
    </div>
</div>

<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
    @if ($activeTab === 'list')
        {{-- ================= TAB 1: INTRODUCERS LIST ================= --}}

        {{-- Section A: Top 10 Member Introducers --}}
        @if ($topIntroducers->isNotEmpty())
            <div class="rounded-xl border bs surface overflow-hidden mb-4">
                <div class="px-4 py-3 surface-2 border-b bs flex items-center justify-between">
                    <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0 flex items-center gap-2">
                        <i class="bi bi-trophy text-amber-500"></i>Top 10 Member Introducers Leaderboard
                    </h6>
                    <span class="chip px-2.5 py-0.5 text-[11px] font-semibold bg-amber-500/10 text-amber-600 border-amber-300">
                        Top Rank
                    </span>
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
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/50">
                            @foreach ($topIntroducers as $index => $introducer)
                                @php
                                    $introducerName = $introducer->name ?? trim((($introducer->first_name ?? '') . ' ' . ($introducer->last_name ?? '')));
                                    $introducerAvatar = $introducer->profile_photo_url ?? ($introducer->profile_photo_file_id ? url('/api/v1/files/' . $introducer->profile_photo_file_id) : null);
                                    
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
                                    <td class="px-3 py-2.5 font-semibold t1">
                                        @if($index === 0)
                                            <span class="badge bg-amber-500 text-black font-extrabold px-2 py-0.5 rounded-full text-xs">🥇 #1</span>
                                        @elseif($index === 1)
                                            <span class="badge bg-slate-300 text-slate-800 font-extrabold px-2 py-0.5 rounded-full text-xs">🥈 #2</span>
                                        @elseif($index === 2)
                                            <span class="badge bg-amber-700 text-white font-extrabold px-2 py-0.5 rounded-full text-xs">🥉 #3</span>
                                        @else
                                            <span class="text-xs font-bold t2">#{{ $index + 1 }}</span>
                                        @endif
                                    </td>
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
                                                                     <td class="px-3 py-2.5 text-right">
                                        <div class="inline-flex items-center gap-1.5 justify-end">
                                            <button type="button" onclick="openIntroducedPeersModal('{{ $introducer->id }}', '{{ addslashes($introducerName) }}')" class="px-2.5 py-1 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition inline-flex items-center gap-1">
                                                <i class="bi bi-people-fill text-indigo-500"></i>Introduced List
                                            </button>
                                            <button type="button" onclick="openCreativeModal('{{ $introducer->id }}', '{{ addslashes($introducerName) }}', {{ $introducedCount }})" class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition inline-flex items-center gap-1 shadow-sm">
                                                <i class="bi bi-stars"></i>Creative
                                            </button>
                                            <a href="{{ route('admin.member-introducers.index', ['tab' => 'creative', 'peer_id' => $introducer->id]) }}" class="px-2 py-1 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1" title="Open in Studio">
                                                <i class="bi bi-palette text-amber-500"></i>Studio
                                            </a>     </a>
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

        {{-- Section B: All Member Introducers Directory --}}
        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="px-4 py-3 surface-2 border-b bs flex items-center justify-between flex-wrap gap-2">
                <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0 flex items-center gap-2">
                    <i class="bi bi-people-fill text-indigo-500"></i>All Member Introducers Directory
                </h6>
                <div class="text-xs t3">
                    Total: <span class="font-semibold t1">{{ $introducers->total() }}</span> introducers
                </div>
            </div>

            {{-- Filters Bar --}}
            <div class="p-4 surface-2 border-b bs">
                <form method="GET" action="{{ route('admin.member-introducers.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <input type="hidden" name="tab" value="list">
                    {{-- Search --}}
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-semibold t3 mb-1">Search Introducer</label>
                        <div class="relative">
                            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 t3 text-xs"></i>
                            <input type="text" name="q" value="{{ $filters['search'] ?? '' }}" placeholder="Name, Email, Company, City..." class="w-full pl-8 pr-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                        </div>
                    </div>

                    {{-- Membership Status --}}
                    <div>
                        <label class="block text-[11px] font-semibold t3 mb-1">Membership Status</label>
                        <select name="membership_status" class="w-full px-2.5 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                            <option value="">All Statuses</option>
                            @foreach ($membershipStatusLabels as $val => $lbl)
                                <option value="{{ $val }}" {{ ($filters['membership_status'] ?? '') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Start Date --}}
                    <div>
                        <label class="block text-[11px] font-semibold t3 mb-1">Introduced From</label>
                        <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" class="w-full px-2.5 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                    </div>

                    {{-- End Date --}}
                    <div>
                        <label class="block text-[11px] font-semibold t3 mb-1">Introduced To</label>
                        <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" class="w-full px-2.5 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                    </div>

                    {{-- Submit & Clear Buttons --}}
                    <div class="flex items-end gap-2">
                        <button type="submit" class="w-full px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition">Filter</button>
                        <a href="{{ route('admin.member-introducers.index') }}" class="w-full px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline">Clear</a>
                    </div>
                </form>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">
                                <a href="{{ route('admin.member-introducers.index', array_merge(request()->query(), ['sort' => 'display_name', 'dir' => ($filters['sort'] ?? '') === 'display_name' && ($filters['dir'] ?? '') === 'asc' ? 'desc' : 'asc'])) }}" class="no-underline t1 hover:text-indigo-600 inline-flex items-center gap-1">
                                    Peer Name
                                    @if (($filters['sort'] ?? '') === 'display_name')
                                        <i class="bi bi-arrow-{{ ($filters['dir'] ?? '') === 'asc' ? 'up' : 'down' }} text-indigo-600"></i>
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
                                        <i class="bi bi-arrow-{{ ($filters['dir'] ?? '') === 'asc' ? 'up' : 'down' }} text-indigo-600"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/50">
                        @forelse ($introducers as $introducer)
                            @php
                                $introducerName = $introducer->name ?? trim((($introducer->first_name ?? '') . ' ' . ($introducer->last_name ?? '')));
                                $introducerAvatar = $introducer->profile_photo_url ?? ($introducer->profile_photo_file_id ? url('/api/v1/files/' . $introducer->profile_photo_file_id) : null);
                                
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
                                        <button type="button" onclick="openCreativeModal('{{ $introducer->id }}', '{{ addslashes($introducerName) }}', {{ $introducedCount }})" class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition inline-flex items-center gap-1 shadow-sm">
                                            <i class="bi bi-stars"></i>Creative
                                        </button>
                                        <a href="{{ route('admin.member-introducers.index', ['tab' => 'creative', 'peer_id' => $introducer->id]) }}" class="px-2 py-1 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1" title="Open in Studio">
                                            <i class="bi bi-palette text-amber-500"></i>Studio
                                        </a>
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
                                <td colspan="6" class="text-center py-10 px-4">
                                    <div class="max-w-md mx-auto space-y-3">
                                        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-2xl mx-auto">
                                            <i class="bi bi-stars"></i>
                                        </div>
                                        <div>
                                            <h6 class="text-sm font-bold t1 m-0">No Member Introducers Recorded Yet</h6>
                                            <p class="text-xs t3 mt-1 m-0">No members have introduced others yet. You can still generate official Growth Honours &amp; recognition creatives for any peer and post them directly to the Timeline.</p>
                                        </div>
                                        <div class="pt-1">
                                            <a href="{{ route('admin.member-introducers.index', ['tab' => 'creative']) }}" class="btn btn-sm bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-black font-bold text-xs rounded-xl px-4 py-2 shadow inline-flex items-center gap-1.5 no-underline">
                                                <i class="bi bi-stars"></i>Open Creative Studio &amp; Timeline Publisher
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Bottom Toolbar & Pagination --}}
            <div id="grid-pagination" class="flex justify-between items-center p-4 flex-wrap gap-2 border-t bs">
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

    @else
        {{-- ================= TAB 2: PEERS CREATIVE POST IN TIMELINE STUDIO ================= --}}
        <div class="space-y-6">
            {{-- Interactive Studio Card --}}
            <div class="rounded-2xl border border-amber-500/30 surface overflow-hidden shadow-xl" style="background-color: #070D1A;">
                {{-- Studio Header & Control Bar --}}
                <div class="p-5 border-b border-amber-500/20 bg-gradient-to-r from-amber-500/15 via-slate-900 to-indigo-900/20 flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <span class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-lg border border-amber-500/30">
                                <i class="bi bi-stars"></i>
                            </span>
                            <div>
                                <h5 class="font-display font-extrabold text-base text-white m-0 tracking-wide">
                                    Peers Recognition Creative Studio &amp; Timeline Publisher
                                </h5>
                                <p class="text-xs text-slate-300 m-0 mt-0.5">
                                    Official Track 1 Growth Honours recognition graphics engine — Canva layout rendering &amp; 1-click Timeline broadcasting
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="chip px-3 py-1 text-xs font-bold bg-amber-500/20 text-amber-300 border-amber-400/40 shadow-sm">
                            <i class="bi bi-shield-check me-1"></i>12 Recognition Levels
                        </span>
                    </div>
                </div>

                {{-- Interactive Dropdown Selection Bar --}}
                <div class="p-4 bg-slate-950/80 border-b border-amber-500/20 flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-2 flex-1 min-w-[280px]">
                        <label for="studioPeerSelect" class="text-xs font-bold text-amber-400 whitespace-nowrap flex items-center gap-1.5">
                            <i class="bi bi-person-fill"></i>Select Peer:
                        </label>
                        <select id="studioPeerSelect" onchange="onStudioPeerChange(this.value)" class="form-select form-select-sm text-xs rounded-xl bg-slate-900 text-white border-slate-700 py-2 px-3 focus:border-amber-500 flex-1">
                            <option value="">-- Choose Peer / Member Introducer --</option>
                            @foreach($allIntroducers as $peer)
                                @php
                                    $pName = $peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));
                                    $pName = $pName ?: ($peer->name ?? 'Peer Member');
                                    $pCompany = $peer->company_name ?? $peer->company ?? $peer->business_name ?? '';
                                    $pCity = $peer->city->name ?? $peer->city ?? '';
                                    $pSub = array_filter([$pCompany, $pCity]);
                                    $pSubStr = !empty($pSub) ? ' ('.implode(' • ', $pSub).')' : '';
                                    $isSelected = $selectedPeerId ? ($peer->id === $selectedPeerId) : false;
                                @endphp
                                <option value="{{ $peer->id }}" data-name="{{ addslashes($pName) }}" data-count="{{ $peer->introduced_members_count ?? 1 }}" {{ $isSelected ? 'selected' : '' }}>
                                    {{ $pName }}{{ $pSubStr }} — {{ $peer->introduced_members_count }} {{ $peer->introduced_members_count === 1 ? 'Peer' : 'Peers' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <label for="studioLevelSelect" class="text-xs font-bold text-amber-400 whitespace-nowrap flex items-center gap-1.5">
                            <i class="bi bi-trophy-fill"></i>Recognition Level:
                        </label>
                        <select id="studioLevelSelect" onchange="onStudioLevelChange(this.value)" class="form-select form-select-sm text-xs rounded-xl bg-slate-900 text-white border-slate-700 py-2 px-3 focus:border-amber-500 w-auto">
                            <option value="0">⭐ Auto (Earned Level)</option>
                            @foreach($growthHonours as $threshold => $h)
                                <option value="{{ $threshold }}">{{ $h['title'] }} ({{ $threshold }} {{ $threshold === 1 ? 'Peer' : 'Peers' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <button type="button" onclick="refreshStudioCreative()" class="btn btn-sm bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-black font-bold text-xs rounded-xl px-4 py-2 shadow-lg inline-flex items-center gap-1.5 transition">
                            <i class="bi bi-arrow-clockwise"></i>Refresh Creative
                        </button>
                    </div>
                </div>

                {{-- Live Studio Two-Column Workspace --}}
                <div class="p-6">
                    <div id="studioLoading" class="p-16 text-center">
                        <div class="spinner-border text-amber-400" role="status"></div>
                        <p class="mt-3 text-xs text-slate-300">Generating live recognition creative graphic...</p>
                    </div>

                    <div id="studioWorkspace" class="grid grid-cols-1 lg:grid-cols-12 gap-8 hidden">
                        {{-- Left Column: Peer Profile Details & Controls --}}
                        <div class="lg:col-span-5 space-y-4 flex flex-col justify-between">
                            <div class="space-y-3.5">
                                {{-- Peer Profile Details Card --}}
                                <div class="p-4 rounded-xl bg-slate-900/90 border border-slate-800 space-y-3 shadow-inner">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <i class="bi bi-person-check-fill"></i>Peer Member Profile Details
                                        </span>
                                        <span id="studioPeerStatusBadge" class="chip px-2.5 py-0.5 text-[11px] font-bold bg-indigo-500/20 text-indigo-300 border-indigo-500/40">
                                            Member
                                        </span>
                                    </div>
                                    <div class="space-y-2 text-xs divide-y divide-slate-800">
                                        <div class="flex justify-between items-center pt-1">
                                            <span class="text-slate-400">Full Name:</span>
                                            <span id="studioPeerNameVal" class="font-bold text-white text-sm"></span>
                                        </div>
                                        <div class="flex justify-between items-center pt-2">
                                            <span class="text-slate-400">Company &amp; City:</span>
                                            <span id="studioPeerCompanyCityVal" class="font-semibold text-slate-200 text-right"></span>
                                        </div>
                                        <div class="flex justify-between items-center pt-2">
                                            <span class="text-slate-400">Designation / Role:</span>
                                            <span id="studioPeerDesignationVal" class="font-medium text-slate-300 text-right"></span>
                                        </div>
                                        <div class="flex justify-between items-center pt-2">
                                            <span class="text-slate-400">Total Introduced Peers:</span>
                                            <span id="studioPeerCountVal" class="font-extrabold text-amber-400 text-sm"></span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Growth Honour Level Banner Card --}}
                                <div class="p-4 rounded-xl bg-slate-900/90 border border-amber-500/30 space-y-2 shadow-inner">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <i class="bi bi-award-fill"></i>Track 1 Growth Honour Level
                                        </span>
                                        <span id="studioLevelBadgeTitle" class="chip px-3 py-1 text-xs font-black bg-amber-500/20 text-amber-300 border-amber-400">
                                            CONNECTOR
                                        </span>
                                    </div>
                                    <p id="studioComplimentText" class="font-semibold text-xs text-slate-200 m-0 leading-relaxed italic">
                                        "Every movement begins with one connection."
                                    </p>
                                </div>

                                {{-- Caption Textbox --}}
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <label class="text-xs font-bold text-slate-300 m-0 flex items-center gap-1">
                                            <i class="bi bi-chat-quote"></i>Timeline &amp; Social Media Caption
                                        </label>
                                        <button type="button" onclick="copyStudioCaption()" class="text-xs text-amber-400 hover:text-amber-300 font-semibold no-underline inline-flex items-center gap-1">
                                            <i class="bi bi-clipboard"></i>Copy Text
                                        </button>
                                    </div>
                                    <textarea id="studioCaptionText" rows="4" readonly class="w-full p-3 rounded-xl bg-slate-950 text-slate-200 border border-slate-800 text-xs outline-none resize-none font-mono leading-relaxed focus:border-amber-500"></textarea>
                                </div>

                                {{-- Peer Growth Honours & Timeline History --}}
                                <div id="studioPeerHonoursContainer"></div>
                            </div>

                            {{-- Action Buttons --}}
                            <div class="space-y-2.5 pt-3 border-t border-slate-800">
                                <button type="button" id="btnStudioPostToTimeline" onclick="postStudioCreativeToTimeline()" class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white font-bold text-sm transition shadow-xl inline-flex items-center justify-center gap-2 border border-indigo-400/30">
                                    <i class="bi bi-broadcast text-lg"></i>
                                    <span>Post Creative to Timeline</span>
                                </button>
                                <a id="btnStudioDownloadCreative" href="#" download="growth_honour_creative.webp" target="_blank" class="w-full py-2.5 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition inline-flex items-center justify-center gap-2 border border-slate-700 no-underline">
                                    <i class="bi bi-download"></i>Download High-Res Graphic
                                </a>
                                <div id="studioPostStatus" class="mt-2 text-center text-xs hidden"></div>
                            </div>
                        </div>

                        {{-- Right Column: Live High-Res Image Graphic Preview --}}
                        <div class="lg:col-span-7 flex flex-col items-center justify-center bg-slate-950/70 rounded-2xl p-5 border border-slate-800 overflow-hidden min-h-[480px]">
                            <div class="text-[11px] text-slate-400 font-bold uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <i class="bi bi-image text-amber-400"></i>Live Canva Design Rendering (1080x1350)
                            </div>
                            <img id="studioPreviewImg" src="" alt="Creative Graphic Preview" class="max-h-[580px] w-auto object-contain rounded-xl shadow-2xl transition hover:scale-[1.01] border border-amber-500/20">
                        </div>
                    </div>

                    <div id="studioEmptyPrompt" class="p-16 text-center text-slate-400 hidden">
                        <div class="text-4xl text-amber-500 mb-2"><i class="bi bi-person-badge"></i></div>
                        <h6 class="text-sm font-bold text-white">No Peer Selected</h6>
                        <p class="text-xs text-slate-400 max-w-md mx-auto mt-1">Please select a peer member from the dropdown above to render and post their official recognition creative.</p>
                    </div>
                </div>
            </div>

            {{-- Showcase: Track 1 Growth Honours Recognition Creatives (12 Levels Grid) --}}
            <div class="rounded-xl border bs surface overflow-hidden mt-6">
                <div class="px-5 py-4 surface-2 border-b bs flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h6 class="font-display font-semibold text-sm text-amber-500 uppercase tracking-wider m-0 flex items-center gap-2">
                            <i class="bi bi-award-fill text-amber-500 text-lg"></i>Track 1 Growth Honours Recognition Creatives — All 12 Levels
                        </h6>
                        <p class="text-xs t3 m-0 mt-1">Official Peers Global Growth Honour System (Canva Design Layout) — All 12 Recognition Levels</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="chip px-3 py-1 text-xs font-semibold bg-amber-500/10 text-amber-600 border-amber-300">
                            <i class="bi bi-palette me-1"></i>12 Canva Levels
                        </span>
                    </div>
                </div>

                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach ($growthHonours as $threshold => $honour)
                        <div class="rounded-2xl border border-amber-500/30 overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 flex flex-col justify-between" style="background-color: #070D1A;">
                            {{-- Canva Graphic Preview Image --}}
                            <div class="relative overflow-hidden bg-slate-900 aspect-[4/5] flex items-center justify-center border-b border-amber-500/20">
                                @php
                                    $badgeRel = $honour['badge_image'] ?? '';
                                    $badgeSrc = !empty($badgeRel) ? asset($badgeRel) : '';
                                    if (!empty($badgeRel) && !file_exists(public_path($badgeRel)) && file_exists(storage_path('app/public/'.$badgeRel))) {
                                        $badgeSrc = asset('storage/'.$badgeRel);
                                    }
                                @endphp
                                @if(!empty($badgeSrc))
                                    <img src="{{ $badgeSrc }}" alt="{{ $honour['title'] }}" class="w-full h-full object-cover" loading="lazy" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                @endif
                                <div class="p-6 text-center" style="{{ !empty($badgeSrc) ? 'display: none;' : '' }}">
                                    <div class="text-xs font-black text-amber-400 uppercase tracking-widest">BIG CONGRATULATIONS</div>
                                    <div class="text-2xl font-black text-white uppercase my-2">{{ $honour['title'] }}</div>
                                    <div class="text-xs text-slate-300 italic">"{{ $honour['compliment'] }}"</div>
                                </div>
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
    @endif
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
                                <th class="px-4 py-3 text-left">Company &amp; Designation</th>
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
                <span class="text-xs t3">Peers Global Introduced Members Directory</span>
                <button type="button" class="px-4 py-2 rounded-xl border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL 2: Member Introducer Creative & Timeline Post Modal --}}
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
                                {{ $pName }} ({{ $peer->introduced_members_count }} {{ $peer->introduced_members_count === 1 ? 'Peer' : 'Peers' }})
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
                                <textarea id="creativeCaptionText" rows="4" readonly class="w-full p-3 rounded-xl border bs surface t1 text-xs outline-none resize-none font-mono leading-relaxed"></textarea>
                            </div>

                            {{-- Peer Growth Honours & Timeline History --}}
                            <div id="modalPeerHonoursContainer"></div>
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

// Studio state
let studioActivePeerId = null;
let studioActivePeerName = '';
let studioActiveCount = 0;

document.addEventListener('DOMContentLoaded', function() {
    @if ($activeTab === 'creative')
        initStudioCreative();
    @endif
});

function initStudioCreative() {
    const peerSelect = document.getElementById('studioPeerSelect');
    if (!peerSelect) return;
    
    if (peerSelect.value) {
        onStudioPeerChange(peerSelect.value);
    } else if (peerSelect.options.length > 1) {
        peerSelect.selectedIndex = 1;
        onStudioPeerChange(peerSelect.value);
    } else {
        document.getElementById('studioLoading')?.classList.add('hidden');
        document.getElementById('studioWorkspace')?.classList.add('hidden');
        document.getElementById('studioEmptyPrompt')?.classList.remove('hidden');
    }
}

function onStudioPeerChange(peerId) {
    if (!peerId) {
        document.getElementById('studioLoading')?.classList.add('hidden');
        document.getElementById('studioWorkspace')?.classList.add('hidden');
        document.getElementById('studioEmptyPrompt')?.classList.remove('hidden');
        return;
    }

    const peerSelect = document.getElementById('studioPeerSelect');
    const selectedOption = peerSelect.options[peerSelect.selectedIndex];
    studioActivePeerId = peerId;
    studioActivePeerName = selectedOption.getAttribute('data-name') || selectedOption.text;
    studioActiveCount = parseInt(document.getElementById('studioLevelSelect')?.value) || 0;

    refreshStudioCreative();
}

function onStudioLevelChange(levelVal) {
    studioActiveCount = parseInt(levelVal) || 0;
    refreshStudioCreative();
}

function refreshStudioCreative() {
    if (!studioActivePeerId) {
        const peerSelect = document.getElementById('studioPeerSelect');
        if (peerSelect && peerSelect.value) {
            studioActivePeerId = peerSelect.value;
            const selectedOption = peerSelect.options[peerSelect.selectedIndex];
            studioActivePeerName = selectedOption.getAttribute('data-name') || selectedOption.text;
        } else {
            return;
        }
    }

    const loadingEl = document.getElementById('studioLoading');
    const workspaceEl = document.getElementById('studioWorkspace');
    const emptyPromptEl = document.getElementById('studioEmptyPrompt');
    const postStatusEl = document.getElementById('studioPostStatus');

    loadingEl?.classList.remove('hidden');
    workspaceEl?.classList.add('hidden');
    emptyPromptEl?.classList.add('hidden');
    postStatusEl?.classList.add('hidden');

    const previewUrl = `/admin/member-introducers/${studioActivePeerId}/creative-preview` + (studioActiveCount ? `?count=${studioActiveCount}` : '');

    fetch(previewUrl, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        loadingEl?.classList.add('hidden');
        if (data.success) {
            document.getElementById('studioLevelBadgeTitle').textContent = data.meta.title;
            document.getElementById('studioComplimentText').textContent = `"${data.meta.compliment}"`;
            document.getElementById('studioCaptionText').value = data.caption;

            if (data.peer) {
                document.getElementById('studioPeerNameVal').textContent = data.peer.name || studioActivePeerName;
                const compCity = [data.peer.company, data.peer.city].filter(Boolean).join(' • ');
                document.getElementById('studioPeerCompanyCityVal').textContent = compCity || '-';
                document.getElementById('studioPeerDesignationVal').textContent = data.peer.designation || 'Peers Global Member';
                document.getElementById('studioPeerCountVal').textContent = `${data.peer.introduced_count} ${data.peer.introduced_count === 1 ? 'Peer' : 'Peers'}`;
                document.getElementById('studioPeerStatusBadge').textContent = (data.peer.membership_status || 'Member').replace(/_/g, ' ');
            }

            renderPeerHonoursList(data, true);

            const imgEl = document.getElementById('studioPreviewImg');
            imgEl.src = data.preview_url;

            const btnDownload = document.getElementById('btnStudioDownloadCreative');
            btnDownload.href = data.preview_url;
            btnDownload.download = `Growth_Honour_${studioActivePeerName.replace(/\s+/g, '_')}.webp`;

            const btnPost = document.getElementById('btnStudioPostToTimeline');
            if (btnPost && data.timeline_status) {
                const postBtnText = btnPost.querySelector('span');
                if (postBtnText) {
                    postBtnText.textContent = data.timeline_status.is_posted ? 'Re-post Creative to Timeline' : 'Post Creative to Timeline';
                }
            }

            workspaceEl?.classList.remove('hidden');
        }
    })
    .catch(err => {
        loadingEl?.classList.add('hidden');
        alert('Failed loading studio creative: ' + err.message);
    });
}

function copyStudioCaption() {
    const captionEl = document.getElementById('studioCaptionText');
    captionEl.select();
    navigator.clipboard.writeText(captionEl.value).then(() => {
        alert('Caption copied to clipboard! 📋');
    });
}

function postStudioCreativeToTimeline() {
    if (!studioActivePeerId) return;

    const btn = document.getElementById('btnStudioPostToTimeline');
    const statusEl = document.getElementById('studioPostStatus');
    const originalText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Posting to Timeline...`;
    statusEl.classList.add('hidden');

    fetch(`/admin/member-introducers/${studioActivePeerId}/post-creative`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            count: studioActiveCount
        })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalText;

        if (data.success) {
            statusEl.className = 'mt-2 text-center text-xs font-bold text-emerald-400 bg-emerald-950/80 p-3 rounded-xl border border-emerald-500/50 block shadow-lg space-y-2';
            statusEl.innerHTML = `
                <div>🎉 ${data.message}</div>
                <div class="flex items-center justify-center gap-2 pt-1 flex-wrap">
                    <a href="${data.view_url || '/admin/posts/' + data.post_id}" target="_blank" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs no-underline inline-flex items-center gap-1">
                        <i class="bi bi-eye"></i> View Post Details
                    </a>
                    <a href="${data.timeline_url || '/admin/posts'}" target="_blank" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs no-underline inline-flex items-center gap-1 border border-slate-600">
                        <i class="bi bi-collection"></i> View All Timeline Posts
                    </a>
                </div>
            `;
        } else {
            statusEl.className = 'mt-2 text-center text-xs font-bold text-rose-400 bg-rose-950/80 p-3 rounded-xl border border-rose-500/50 block';
            statusEl.textContent = '❌ ' + (data.error || 'Failed to post creative to timeline.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        statusEl.className = 'mt-2 text-center text-xs font-bold text-rose-400 bg-rose-950/80 p-3 rounded-xl border border-rose-500/50 block';
        statusEl.textContent = '❌ Error: ' + err.message;
    });
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

            renderPeerHonoursList(data, false);

            const imgEl = document.getElementById('creativePreviewImg');
            imgEl.src = data.preview_url;
            
            const btnDownload = document.getElementById('btnDownloadCreative');
            btnDownload.href = data.preview_url;
            btnDownload.download = `Growth_Honour_${introducerName.replace(/\s+/g, '_')}.webp`;

            const btnPost = document.getElementById('btnPostToTimeline');
            if (btnPost && data.timeline_status) {
                const postBtnText = btnPost.querySelector('span');
                if (postBtnText) {
                    postBtnText.textContent = data.timeline_status.is_posted ? 'Re-post Creative to Timeline' : 'Post Creative to Timeline';
                }
            }

            document.getElementById('creativeLoading').classList.add('hidden');
            document.getElementById('creativeContent').classList.remove('hidden');
        }
    })
    .catch(err => {
        document.getElementById('creativeLoading').innerHTML = `<p class="text-red-500 text-xs">Failed generating creative: ${err.message}</p>`;
    });
}

function renderPeerHonoursList(data, isStudio = false) {
    const containerId = isStudio ? 'studioPeerHonoursContainer' : 'modalPeerHonoursContainer';
    const container = document.getElementById(containerId);
    if (!container) return;

    const unlockedHonours = (data.peer_honours || []).filter(h => h.is_unlocked);

    if (unlockedHonours.length === 0) {
        container.innerHTML = '';
        return;
    }

    const cardBg = isStudio ? 'bg-slate-900/90 border-slate-800' : 'surface-2 border bs';

    let html = `
        <div class="p-4 rounded-xl ${cardBg} border space-y-3 shadow-sm">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <span class="text-[11px] font-bold text-amber-500 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="bi bi-award-fill"></i>Earned Recognition Creatives (${unlockedHonours.length} Available)
                </span>
                ${data.timeline_status && data.timeline_status.total_timeline_posts > 0 ? 
                    `<span class="chip px-2.5 py-0.5 text-[11px] font-bold bg-emerald-500/20 text-emerald-400 border-emerald-500/40">
                        <i class="bi bi-broadcast me-1"></i>${data.timeline_status.total_timeline_posts} Posted in Timeline
                     </span>` : ''}
            </div>
            <div class="flex flex-wrap gap-1.5 pt-0.5">
    `;

    unlockedHonours.forEach(h => {
        let badgeClass = '';
        let icon = '';
        let labelExtra = '';

        if (h.posted_to_timeline) {
            labelExtra += ` <span class="text-[10px] font-extrabold text-emerald-400 ms-1" title="Already posted to Timeline">✓ Posted</span>`;
        }

        if (h.is_current) {
            badgeClass = 'bg-gradient-to-r from-amber-500 to-amber-600 text-black font-extrabold border-amber-300 shadow-md ring-2 ring-amber-400/50';
            icon = '🏆 ';
        } else {
            badgeClass = isStudio ? 'bg-emerald-950/80 text-emerald-300 font-bold border-emerald-500/50 hover:bg-emerald-900' : 'bg-emerald-50 text-emerald-700 font-bold border-emerald-200 hover:bg-emerald-100';
            icon = '✅ ';
        }

        const clickFn = isStudio ? `onStudioLevelChange(${h.threshold})` : `onModalLevelSwitch(${h.threshold})`;

        html += `
            <button type="button" onclick="${clickFn}" class="chip px-2.5 py-1 text-xs rounded-xl border transition cursor-pointer inline-flex items-center gap-1 ${badgeClass}" title="${h.title} (${h.threshold} Peers) — ${h.compliment}">
                <span>${icon}${h.title}</span>
                <span class="text-[10px] opacity-80">(${h.threshold})</span>
                ${labelExtra}
            </button>
        `;
    });

    html += `</div>`;

    if (data.timeline_status && data.timeline_status.is_posted) {
        html += `
            <div class="mt-2.5 p-2.5 rounded-xl ${isStudio ? 'bg-indigo-950/70 border border-indigo-500/40 text-indigo-200' : 'bg-indigo-50 border border-indigo-200 text-indigo-900'} flex items-center justify-between text-xs flex-wrap gap-2">
                <span class="font-semibold flex items-center gap-1.5">
                    <i class="bi bi-check-circle-fill text-emerald-400"></i>
                    Creative published on Timeline ${data.timeline_status.posted_at ? '(' + data.timeline_status.posted_at + ')' : ''}
                </span>
                <a href="${data.timeline_status.post_view_url}" target="_blank" class="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[11px] no-underline inline-flex items-center gap-1 shadow">
                    <i class="bi bi-eye"></i>View Post in Timeline
                </a>
            </div>
        `;
    }

    html += `</div>`;
    container.innerHTML = html;
}

function copyCaptionToClipboard() {
    const captionEl = document.getElementById('creativeCaptionText');
    captionEl.select();
    navigator.clipboard.writeText(captionEl.value).then(() => {
        alert('Caption copied to clipboard! 📋');
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
            statusEl.className = 'mt-2 text-center text-xs font-semibold text-emerald-600 bg-emerald-50 p-3 rounded-xl border border-emerald-200 block space-y-2';
            statusEl.innerHTML = `
                <div>🎉 ${data.message}</div>
                <div class="flex items-center justify-center gap-2 pt-1 flex-wrap">
                    <a href="${data.view_url || '/admin/posts/' + data.post_id}" target="_blank" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs no-underline inline-flex items-center gap-1">
                        <i class="bi bi-eye"></i> View Post Details
                    </a>
                    <a href="${data.timeline_url || '/admin/posts'}" target="_blank" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs no-underline inline-flex items-center gap-1 border border-slate-600">
                        <i class="bi bi-collection"></i> View All Timeline Posts
                    </a>
                </div>
            `;
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
