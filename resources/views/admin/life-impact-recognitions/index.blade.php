@extends('admin.layouts.app')

@section('title', 'Life Impact Recognitions')

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
            <i class="bi bi-heart-pulse-fill text-rose-500"></i>Life Impact Recognitions
        </h4>
        <p class="text-xs t3 m-0 mt-0.5">Track top life impact leaders, explore 12 recognition levels, preview Canva-designed graphics, and broadcast honours directly to Timeline.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.life-impact.index') }}" class="tab-pill px-4 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-2 transition no-underline surface border bs t2 hover:t1 hover:surface-2">
            <i class="bi bi-bar-chart-line-fill"></i>Life Impact Overview
        </a>
        <a href="{{ route('admin.life-impact-recognitions.index') }}" class="tab-pill px-4 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-2 transition no-underline {{ $activeTab === 'list' ? 'bg-indigo-600 text-white shadow-md' : 'surface border bs t2 hover:t1 hover:surface-2' }}">
            <i class="bi bi-people-fill"></i>Life Impact List <span class="badge {{ $activeTab === 'list' ? 'bg-white text-indigo-700' : 'bg-slate-200 text-slate-700' }} rounded-full px-2 py-0.5 text-[10px]">{{ $allPeers->count() }}</span>
        </a>
        <a href="{{ route('admin.life-impact-recognitions.index', ['tab' => 'creative']) }}" class="tab-pill px-4 py-2 rounded-xl text-xs font-semibold inline-flex items-center gap-2 transition no-underline {{ $activeTab === 'creative' ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-black font-bold shadow-md' : 'surface border bs t2 hover:t1 hover:surface-2' }}">
            <i class="bi bi-stars"></i>Creative
        </a>
    </div>
</div>

<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
    @if ($activeTab === 'list')
        {{-- ================= TAB 1: PEERS LIST ================= --}}

        {{-- Section A: Top 10 Life Impact Leaderboard --}}
        @if ($topPeers->isNotEmpty())
            <div class="rounded-xl border bs surface overflow-hidden mb-4">
                <div class="px-4 py-3 surface-2 border-b bs flex items-center justify-between">
                    <h6 class="font-display font-semibold text-xs text-rose-500 uppercase tracking-wider m-0 flex items-center gap-2">
                        <i class="bi bi-trophy text-amber-500"></i>Top 10 Life Impact Leaderboard
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
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Life Impacted</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Recognition Level</th>
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/50">
                            @foreach ($topPeers as $index => $peer)
                                @php
                                    $peerName = $peer->display_name ?: trim((($peer->first_name ?? '') . ' ' . ($peer->last_name ?? '')));
                                    $peerName = $peerName ?: ($peer->name ?? 'Peer Member');
                                    $peerAvatar = $peer->profile_photo_url ?? ($peer->profile_photo_file_id ? url('/api/v1/files/' . $peer->profile_photo_file_id) : null);
                                    
                                    $peerCityModel = $peer->getRelation('city') ?? $peer->cityRelation ?? null;
                                    $peerCityName = $peerCityModel->name ?? $peer->city ?? '';
                                    if (is_array($peerCityName)) {
                                        $peerCityName = $peerCityName['name'] ?? $peerCityName['label'] ?? '';
                                    }
                                    if (in_array(strtolower(trim((string)$peerCityName)), ['', 'no city', 'none', 'null'], true)) {
                                        $peerCityName = null;
                                    }

                                    $peerCompany = $peer->company_name ?? $peer->company ?? $peer->business_name ?? '';
                                    if (in_array(strtolower(trim((string)$peerCompany)), ['', 'no company', 'none', 'null', 'peers global'], true)) {
                                        $peerCompany = null;
                                    }

                                    $lifeCount = (int) ($peer->total_life_impacted_calc ?? $peer->life_impacted_count ?? 0);
                                    $generator = app(\App\Services\Creative\LifeImpactCreativeGenerator::class);
                                    $tierMeta = $generator->getRecognitionMeta($lifeCount >= 25 ? $lifeCount : 25);
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
                                                @if ($peerAvatar)
                                                    <img src="{{ $peerAvatar }}" alt="{{ $peerName }}" class="w-full h-full object-cover">
                                                @else
                                                    <div class="w-full h-full bg-rose-600 text-white font-bold flex items-center justify-center text-xs">
                                                        {{ strtoupper(substr($peerName !== '' ? $peerName : 'P', 0, 1)) }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="font-medium t1 text-[12.5px] whitespace-nowrap">
                                                @if(!empty($peer->id))
                                                    <a href="#" data-peer-id="{{ $peer->id }}" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $peer->id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                                        {{ $peerName !== '' ? $peerName : '-' }}
                                                    </a>
                                                @else
                                                    {{ $peerName !== '' ? $peerName : '-' }}
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        @if ($peerCompany)
                                            <span class="t1 inline-flex items-center gap-1 whitespace-nowrap text-[12.5px]">
                                                <i class="bi bi-building t3 text-xs"></i>{{ $peerCompany }}
                                            </span>
                                        @else
                                            <span class="t3">-</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5">
                                        @if ($peerCityName)
                                            <span class="t1 inline-flex items-center gap-1 whitespace-nowrap text-[12.5px]">
                                                <i class="bi bi-geo-alt t3 text-xs"></i>{{ $peerCityName }}
                                            </span>
                                        @else
                                            <span class="t3">-</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        <a href="{{ route('admin.life-impact.history', $peer->id) }}" class="no-underline" target="_blank">
                                            <span class="chip px-2.5 py-0.5 text-xs font-bold bg-rose-50 text-rose-700 border-rose-200 cursor-pointer hover:bg-rose-100 transition">
                                                {{ number_format($lifeCount) }}
                                            </span>
                                        </a>
                                    </td>
                                    <td class="px-3 py-2.5 text-center">
                                        @if ($lifeCount >= 25)
                                            <span class="chip px-2.5 py-0.5 text-xs font-bold bg-amber-500/15 text-amber-700 border-amber-300">
                                                <i class="bi bi-award-fill text-amber-500 me-1"></i>{{ $tierMeta['title'] }}
                                            </span>
                                        @else
                                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-600 border-slate-200">
                                                Aspiring Impact Leader
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-right">
                                        <div class="inline-flex items-center gap-1.5 justify-end">
                                            <a href="{{ route('admin.life-impact.history', $peer->id) }}" target="_blank" class="px-2.5 py-1 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition inline-flex items-center gap-1 no-underline">
                                                <i class="bi bi-clock-history text-indigo-500"></i>History
                                            </a>
                                            <button type="button" onclick="openCreativeModal('{{ $peer->id }}', '{{ addslashes($peerName) }}', {{ $lifeCount }})" class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition inline-flex items-center gap-1 shadow-sm">
                                                <i class="bi bi-stars"></i>Creative
                                            </button>
                                            <a href="{{ route('admin.life-impact-recognitions.index', ['tab' => 'creative', 'peer_id' => $peer->id]) }}" class="px-2 py-1 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1" title="Open in Studio">
                                                <i class="bi bi-palette text-amber-500"></i>Studio
                                            </a>
                                            @if ($canEditUsers)
                                                <a href="{{ route('admin.users.edit', $peer->id) }}" class="px-2 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1" title="Edit Profile">
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

        {{-- Section B: All Life Impact Peers Directory --}}
        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="px-4 py-3 surface-2 border-b bs flex items-center justify-between flex-wrap gap-2">
                <h6 class="font-display font-semibold text-xs text-rose-500 uppercase tracking-wider m-0 flex items-center gap-2">
                    <i class="bi bi-people-fill text-indigo-500"></i>All Life Impact Peers Directory
                </h6>
                <div class="text-xs t3">
                    Total: <span class="font-semibold t1">{{ $peers->total() }}</span> peers
                </div>
            </div>

            {{-- Filters Bar --}}
            <div class="p-4 surface-2 border-b bs">
                <form method="GET" action="{{ route('admin.life-impact-recognitions.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <input type="hidden" name="tab" value="list">
                    {{-- Search --}}
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-semibold t3 mb-1">Search Peer</label>
                        <div class="relative">
                            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 t3 text-xs"></i>
                            <input type="text" name="q" value="{{ $filters['search'] ?? '' }}" placeholder="Name, Email, Company, City..." class="w-full pl-8 pr-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" onkeydown="if (event.key === 'Enter') this.form.submit()">
                        </div>
                    </div>

                    {{-- Circle Filter --}}
                    <div>
                        <label class="block text-[11px] font-semibold t3 mb-1">Circle</label>
                        <select name="circle_id" class="w-full px-2.5 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" onchange="this.form.submit()">
                            <option value="all">All Circles</option>
                            @foreach ($circles as $circle)
                                <option value="{{ $circle->id }}" {{ ($filters['circle_id'] ?? 'all') === $circle->id ? 'selected' : '' }}>{{ $circle->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Membership Status --}}
                    <div>
                        <label class="block text-[11px] font-semibold t3 mb-1">Membership Status</label>
                        <select name="membership_status" class="w-full px-2.5 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            @foreach ($membershipStatusLabels as $val => $lbl)
                                <option value="{{ $val }}" {{ ($filters['membership_status'] ?? '') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Start Date --}}
                    <div>
                        <label class="block text-[11px] font-semibold t3 mb-1">Impact From</label>
                        <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" class="w-full px-2.5 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" onchange="this.form.submit()">
                    </div>

                    {{-- End Date --}}
                    <div>
                        <label class="block text-[11px] font-semibold t3 mb-1">Impact To</label>
                        <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" class="w-full px-2.5 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" onchange="this.form.submit()">
                    </div>

                    {{-- Submit & Clear Buttons --}}
                    <div class="sm:col-span-2 lg:col-span-6 flex justify-end gap-2 pt-1">
                        <button type="submit" class="px-4 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition">Filter Directory</button>
                        <a href="{{ route('admin.life-impact-recognitions.index') }}" class="px-4 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline">Clear</a>
                    </div>
                </form>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">
                                <a href="{{ route('admin.life-impact-recognitions.index', array_merge(request()->query(), ['sort' => 'display_name', 'dir' => ($filters['sort'] ?? '') === 'display_name' && ($filters['dir'] ?? '') === 'asc' ? 'desc' : 'asc'])) }}" class="no-underline t1 hover:text-indigo-600 inline-flex items-center gap-1">
                                    Peer Name
                                    @if (($filters['sort'] ?? '') === 'display_name')
                                        <i class="bi bi-arrow-{{ ($filters['dir'] ?? '') === 'asc' ? 'up' : 'down' }} text-indigo-600"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Circle</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">
                                <a href="{{ route('admin.life-impact-recognitions.index', array_merge(request()->query(), ['sort' => 'total_life_impacted', 'dir' => ($filters['sort'] ?? '') === 'total_life_impacted' && ($filters['dir'] ?? '') === 'asc' ? 'desc' : 'asc'])) }}" class="no-underline t1 hover:text-indigo-600 inline-flex items-center gap-1">
                                    Life Impacted
                                    @if (($filters['sort'] ?? '') === 'total_life_impacted')
                                        <i class="bi bi-arrow-{{ ($filters['dir'] ?? '') === 'asc' ? 'up' : 'down' }} text-indigo-600"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Recognition Level</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/50">
                        @forelse ($peers as $peer)
                            @php
                                $peerName = $peer->display_name ?: trim((($peer->first_name ?? '') . ' ' . ($peer->last_name ?? '')));
                                $peerName = $peerName ?: ($peer->name ?? 'Peer Member');
                                $peerAvatar = $peer->profile_photo_url ?? ($peer->profile_photo_file_id ? url('/api/v1/files/' . $peer->profile_photo_file_id) : null);
                                
                                $peerCityModel = $peer->getRelation('city') ?? $peer->cityRelation ?? null;
                                $peerCityName = $peerCityModel->name ?? $peer->city ?? '';
                                if (is_array($peerCityName)) {
                                    $peerCityName = $peerCityName['name'] ?? $peerCityName['label'] ?? '';
                                }
                                if (in_array(strtolower(trim((string)$peerCityName)), ['', 'no city', 'none', 'null'], true)) {
                                    $peerCityName = null;
                                }

                                $peerCompany = $peer->company_name ?? $peer->company ?? $peer->business_name ?? '';
                                if (in_array(strtolower(trim((string)$peerCompany)), ['', 'no company', 'none', 'null', 'peers global'], true)) {
                                    $peerCompany = null;
                                }

                                $userCircles = $peer->circleMembers->map(fn($cm) => optional($cm->circle)->name)->filter()->unique()->implode(', ');
                                $circleName = $userCircles !== '' ? $userCircles : '-';

                                $lifeCount = (int) ($peer->total_life_impacted_calc ?? $peer->life_impacted_count ?? 0);
                                $generator = app(\App\Services\Creative\LifeImpactCreativeGenerator::class);
                                $tierMeta = $generator->getRecognitionMeta($lifeCount >= 25 ? $lifeCount : 25);
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full overflow-hidden flex-none border bs">
                                            @if ($peerAvatar)
                                                <img src="{{ $peerAvatar }}" alt="{{ $peerName }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full bg-rose-600 text-white font-bold flex items-center justify-center text-xs">
                                                    {{ strtoupper(substr($peerName !== '' ? $peerName : 'P', 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="font-medium t1 text-[12.5px] whitespace-nowrap">
                                            @if(!empty($peer->id))
                                                <a href="#" data-peer-id="{{ $peer->id }}" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $peer->id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                                    {{ $peerName !== '' ? $peerName : '-' }}
                                                </a>
                                            @else
                                                {{ $peerName !== '' ? $peerName : '-' }}
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($peerCompany)
                                        <span class="t1 inline-flex items-center gap-1 whitespace-nowrap text-[12.5px]">
                                            <i class="bi bi-building t3 text-xs"></i>{{ $peerCompany }}
                                        </span>
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($peerCityName)
                                        <span class="t1 inline-flex items-center gap-1 whitespace-nowrap text-[12.5px]">
                                            <i class="bi bi-geo-alt t3 text-xs"></i>{{ $peerCityName }}
                                        </span>
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="t2 text-[12px] whitespace-nowrap">{{ $circleName }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <a href="{{ route('admin.life-impact.history', $peer->id) }}" class="no-underline" target="_blank">
                                        <span class="chip px-2.5 py-0.5 text-xs font-bold bg-rose-50 text-rose-700 border-rose-200 cursor-pointer hover:bg-rose-100 transition">
                                            {{ number_format($lifeCount) }}
                                        </span>
                                    </a>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @if ($lifeCount >= 25)
                                        <span class="chip px-2.5 py-0.5 text-xs font-bold bg-amber-500/15 text-amber-700 border-amber-300">
                                            <i class="bi bi-award-fill text-amber-500 me-1"></i>{{ $tierMeta['title'] }}
                                        </span>
                                    @else
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-600 border-slate-200">
                                            Aspiring Impact Leader
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <a href="{{ route('admin.life-impact.history', $peer->id) }}" target="_blank" class="px-2.5 py-1 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition inline-flex items-center gap-1 no-underline">
                                            <i class="bi bi-clock-history text-indigo-500"></i>History
                                        </a>
                                        <button type="button" onclick="openCreativeModal('{{ $peer->id }}', '{{ addslashes($peerName) }}', {{ $lifeCount }})" class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition inline-flex items-center gap-1 shadow-sm">
                                            <i class="bi bi-stars"></i>Creative
                                        </button>
                                        <a href="{{ route('admin.life-impact-recognitions.index', ['tab' => 'creative', 'peer_id' => $peer->id]) }}" class="px-2 py-1 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1" title="Open in Studio">
                                            <i class="bi bi-palette text-amber-500"></i>Studio
                                        </a>
                                        @if ($canEditUsers)
                                            <a href="{{ route('admin.users.edit', $peer->id) }}" class="px-2 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1" title="Edit Profile">
                                                <i class="bi bi-person"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-10 px-4">
                                    <div class="max-w-md mx-auto space-y-3">
                                        <div class="w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-500 flex items-center justify-center text-2xl mx-auto">
                                            <i class="bi bi-heart-pulse"></i>
                                        </div>
                                        <div>
                                            <h6 class="text-sm font-bold t1 m-0">No Life Impact Records Found</h6>
                                            <p class="text-xs t3 mt-1 m-0">You can still generate official Life Impact Recognition creatives for any peer and post them directly to the Timeline.</p>
                                        </div>
                                        <div class="pt-1">
                                            <a href="{{ route('admin.life-impact-recognitions.index', ['tab' => 'creative']) }}" class="btn btn-sm bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-black font-bold text-xs rounded-xl px-4 py-2 shadow inline-flex items-center gap-1.5 no-underline">
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
                    {{ $peers->links() }}
                </div>
                <div class="text-xs t3">
                    @if($peers->total() > 0)
                        Showing <span class="font-semibold t1">{{ $peers->firstItem() }}-{{ $peers->lastItem() }}</span> of <span class="font-semibold t1">{{ $peers->total() }}</span> records
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
                                    Life Impact Recognition Studio &amp; Timeline Publisher
                                </h5>
                                <p class="text-xs text-slate-300 m-0 mt-0.5">
                                    Official 12 Life Impact Recognition Tiers graphics engine — Canva layout rendering &amp; 1-click Timeline broadcasting
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="chip px-3 py-1 text-xs font-bold bg-amber-500/20 text-amber-300 border-amber-400/40 shadow-sm">
                            <i class="bi bi-shield-check me-1"></i>12 Recognition Levels (25 to 1,00,000 Lives)
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
                            <option value="">-- Choose Peer Member --</option>
                            @foreach($allPeers as $p)
                                @php
                                    $pName = $p->display_name ?: trim(($p->first_name ?? '').' '.($p->last_name ?? ''));
                                    $pName = $pName ?: ($p->name ?? 'Peer Member');
                                    $pCompany = $p->company_name ?? $p->company ?? $p->business_name ?? '';
                                    $pCity = $p->city->name ?? $p->city ?? '';
                                    $pSub = array_filter([$pCompany, $pCity]);
                                    $pSubStr = !empty($pSub) ? ' ('.implode(' • ', $pSub).')' : '';
                                    $isSelected = $selectedPeerId ? ($p->id === $selectedPeerId) : false;
                                @endphp
                                <option value="{{ $p->id }}" data-name="{{ addslashes($pName) }}" data-count="{{ $p->life_impacted_count ?? 0 }}" {{ $isSelected ? 'selected' : '' }}>
                                    {{ $pName }}{{ $pSubStr }} — {{ number_format((int)($p->life_impacted_count ?? 0)) }} Lives Impacted
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
                            @foreach($recognitionLevels as $threshold => $h)
                                <option value="{{ $threshold }}">{{ $h['title'] }} ({{ number_format($threshold) }} Lives)</option>
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
                                        <span id="studioPeerStatusBadge" class="chip px-2.5 py-0.5 text-[11px] font-bold bg-rose-500/20 text-rose-300 border-rose-500/40">
                                            Member
                                        </span>
                                    </div>
                                    <div class="space-y-1.5 text-xs text-slate-200">
                                        <div class="flex justify-between py-1 border-b border-slate-800">
                                            <span class="text-slate-400">Peer Name:</span>
                                            <span id="studioPeerName" class="font-bold text-white">-</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-slate-800">
                                            <span class="text-slate-400">Company Name:</span>
                                            <span id="studioPeerCompany" class="font-medium text-slate-200">-</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-slate-800">
                                            <span class="text-slate-400">City / Location:</span>
                                            <span id="studioPeerCity" class="font-medium text-slate-200">-</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-slate-800">
                                            <span class="text-slate-400">Designation / Role:</span>
                                            <span id="studioPeerDesignation" class="font-medium text-slate-200">-</span>
                                        </div>
                                        <div class="flex justify-between py-1">
                                            <span class="text-slate-400">Total Lives Impacted:</span>
                                            <span id="studioPeerCountBadge" class="badge bg-rose-500/20 text-rose-400 font-bold px-2 py-0.5 rounded-full text-xs">
                                                0 Lives
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Life Impact Recognition Level Banner Card --}}
                                <div class="p-4 rounded-xl bg-slate-900/90 border border-amber-500/30 space-y-2 shadow-inner">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <i class="bi bi-award-fill"></i>Life Impact Recognition Level
                                        </span>
                                        <span id="studioLevelBadgeTitle" class="chip px-3 py-1 text-xs font-black bg-amber-500/20 text-amber-300 border-amber-400">
                                            IMPACT CREATOR
                                        </span>
                                    </div>
                                    <p id="studioComplimentText" class="font-semibold text-xs text-slate-200 m-0 leading-relaxed italic">
                                        "Your contribution is making a lasting difference and supporting our mission of impacting 1 Million Entrepreneurs."
                                    </p>
                                </div>

                                {{-- 12-Tier Recognition Progression Grid --}}
                                <div class="p-4 rounded-xl bg-slate-900/90 border border-slate-800 space-y-3 shadow-inner">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <i class="bi bi-shield-check"></i>12 Recognition Levels Track
                                        </span>
                                        <span class="text-[10px] text-slate-400">1 Action = 1 Life Impacted</span>
                                    </div>
                                    <div id="studioProgressionList" class="space-y-1.5 max-h-56 overflow-y-auto pr-1">
                                        {{-- Dynamic rendered list items --}}
                                    </div>
                                </div>

                                {{-- Social Media Caption Box --}}
                                <div class="p-4 rounded-xl bg-slate-900/90 border border-slate-800 space-y-2 shadow-inner">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <i class="bi bi-chat-square-quote-fill"></i>Generated Social Caption
                                        </span>
                                        <button type="button" onclick="copyStudioCaption()" class="px-2.5 py-1 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 text-[11px] font-bold border border-amber-500/40 inline-flex items-center gap-1 transition">
                                            <i class="bi bi-clipboard"></i><span id="copyBtnText">Copy Caption</span>
                                        </button>
                                    </div>
                                    <textarea id="studioCaptionText" rows="6" readonly class="w-full p-2.5 rounded-lg bg-slate-950 text-slate-300 text-xs font-mono border border-slate-800 focus:outline-none resize-none"></textarea>
                                </div>
                            </div>

                            {{-- Broadcast Action Footer --}}
                            <div class="pt-2">
                                <button type="button" id="studioPostTimelineBtn" onclick="broadcastStudioCreativeToTimeline()" class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-amber-500 via-amber-600 to-amber-700 hover:from-amber-600 hover:to-amber-800 text-black font-extrabold text-sm shadow-xl inline-flex items-center justify-center gap-2 transition">
                                    <i class="bi bi-send-fill text-lg"></i>
                                    <span id="studioPostBtnLabel">Post Recognition to Timeline 🎉</span>
                                </button>
                                <div id="studioPostFeedback" class="mt-2 text-center text-xs hidden"></div>
                            </div>
                        </div>

                        {{-- Right Column: Live High-Resolution Graphic Preview --}}
                        <div class="lg:col-span-7 flex flex-col items-center justify-center">
                            <div class="w-full max-w-[480px] p-3 rounded-2xl bg-slate-950 border border-amber-500/30 shadow-2xl space-y-3">
                                <div class="flex items-center justify-between px-2 text-xs">
                                    <span class="text-amber-400 font-bold flex items-center gap-1.5">
                                        <i class="bi bi-image"></i>Live Rendered Graphic
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <a id="studioDownloadBtn" href="#" download="life_impact_recognition.webp" class="text-slate-400 hover:text-amber-300 transition no-underline text-xs inline-flex items-center gap-1" title="Download High-Res WebP">
                                            <i class="bi bi-download"></i>Download
                                        </a>
                                        <a id="studioOpenFullBtn" href="#" target="_blank" class="text-slate-400 hover:text-amber-300 transition no-underline text-xs inline-flex items-center gap-1" title="Open Full Size">
                                            <i class="bi bi-box-arrow-up-right"></i>Full Size
                                        </a>
                                    </div>
                                </div>
                                <div class="rounded-xl overflow-hidden bg-slate-900 border border-slate-800 shadow-inner flex items-center justify-center relative min-h-[500px]">
                                    <img id="studioPreviewImage" src="" alt="Recognition Creative Graphic" class="w-full h-auto object-contain rounded-lg transition-opacity duration-300">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Showcase: Life Impact Recognition Creatives (All 12 Levels Grid) --}}
            <div class="rounded-xl border bs surface overflow-hidden mt-6">
                <div class="px-5 py-4 surface-2 border-b bs flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h6 class="font-display font-semibold text-sm text-amber-500 uppercase tracking-wider m-0 flex items-center gap-2">
                            <i class="bi bi-award-fill text-amber-500 text-lg"></i>Life Impact Recognition Creatives — All 12 Levels
                        </h6>
                        <p class="text-xs t3 m-0 mt-1">Official Peers Global Life Impact Recognition System (Canva Design Layout) — All 12 Recognition Levels (Pages 14–25)</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="chip px-3 py-1 text-xs font-semibold bg-amber-500/10 text-amber-600 border-amber-300">
                            <i class="bi bi-palette me-1"></i>12 Canva Levels
                        </span>
                    </div>
                </div>

                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach ($recognitionLevels as $threshold => $honour)
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
                                    <img src="{{ $badgeSrc }}" alt="{{ $honour['title'] }}" class="w-full h-full object-cover" loading="lazy">
                                @endif
                                <div class="absolute top-3 right-3">
                                    <span class="chip px-2.5 py-1 text-[11px] font-bold bg-amber-500/90 text-black border-amber-400 shadow">
                                        {{ number_format($honour['required_count']) }} {{ $honour['required_count'] === 1 ? 'Life' : 'Lives' }}
                                    </span>
                                </div>
                            </div>

                            {{-- Info Bar & Action Button --}}
                            <div class="p-3 surface-2 border-t border-amber-500/20 text-center space-y-2">
                                <div class="text-xs font-bold t1 flex items-center justify-center gap-1.5">
                                    <span class="text-amber-500 font-extrabold">{{ $honour['title'] }}</span>
                                    <span class="t3 text-[11px]">({{ number_format($honour['required_count']) }} Lives)</span>
                                </div>
                                <button type="button" onclick="selectLevelInStudio({{ $honour['required_count'] }})" class="btn btn-sm btn-outline-warning text-xs font-semibold rounded-lg px-3 py-1.5 w-full">
                                    <i class="bi bi-stars me-1"></i> Preview {{ $honour['title'] }} Creative
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Quick Creative Modal (for Tab 1 List view) --}}
<div class="modal fade" id="quickCreativeModal" tabindex="-1" aria-labelledby="quickCreativeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-2xl rounded-2xl overflow-hidden bg-slate-950 text-white">
            <div class="modal-header border-b border-slate-800 p-4 bg-slate-900">
                <h5 class="modal-title font-bold text-sm text-amber-400 flex items-center gap-2" id="quickCreativeModalLabel">
                    <i class="bi bi-stars"></i>Life Impact Recognition Graphic Preview
                </h5>
                <button type="button" class="btn-close btn-close-white text-xs" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-6">
                <div id="modalLoading" class="text-center py-12">
                    <div class="spinner-border text-amber-400" role="status"></div>
                    <p class="mt-2 text-xs text-slate-300">Rendering graphic...</p>
                </div>
                <div id="modalContent" class="grid grid-cols-1 md:grid-cols-2 gap-6 hidden">
                    <div class="flex items-center justify-center">
                        <img id="modalGraphicImg" src="" alt="Creative Graphic" class="w-full max-w-[320px] rounded-xl shadow-lg border border-amber-500/30">
                    </div>
                    <div class="space-y-3.5 flex flex-col justify-between">
                        <div class="space-y-2.5">
                            <h6 id="modalPeerName" class="font-bold text-base text-amber-400 m-0"></h6>
                            <p id="modalPeerSub" class="text-xs text-slate-400 m-0"></p>
                            <div class="p-3 rounded-lg bg-slate-900 border border-slate-800 space-y-1.5 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Recognition Tier:</span>
                                    <span id="modalTierTitle" class="font-bold text-amber-300"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Total Lives Impacted:</span>
                                    <span id="modalLivesCount" class="font-bold text-white"></span>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Social Caption</label>
                                <textarea id="modalCaptionText" rows="6" readonly class="w-full p-2.5 rounded-lg bg-slate-900 text-slate-300 text-xs font-mono border border-slate-800 resize-none"></textarea>
                            </div>
                        </div>
                        <div class="space-y-2 pt-2">
                            <button type="button" onclick="copyModalCaption()" class="w-full py-2 px-3 rounded-xl border border-amber-500/40 bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 font-bold text-xs transition inline-flex items-center justify-center gap-1.5">
                                <i class="bi bi-clipboard"></i><span id="modalCopyBtnText">Copy Caption</span>
                            </button>
                            <button type="button" id="modalPostTimelineBtn" onclick="postModalCreativeToTimeline()" class="w-full py-2.5 px-3 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-black font-extrabold text-xs shadow inline-flex items-center justify-center gap-1.5 transition">
                                <i class="bi bi-send-fill"></i>Post to Timeline 🎉
                            </button>
                            <div id="modalPostFeedback" class="text-center text-xs hidden"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let currentStudioPeerId = "{{ $selectedPeerId ?? '' }}";
    let currentStudioThreshold = 0;
    let modalActivePeerId = null;

    document.addEventListener('DOMContentLoaded', function () {
        @if($activeTab === 'creative')
            if (currentStudioPeerId) {
                loadStudioCreative(currentStudioPeerId, currentStudioThreshold);
            } else {
                const select = document.getElementById('studioPeerSelect');
                if (select && select.options.length > 1) {
                    select.selectedIndex = 1;
                    currentStudioPeerId = select.value;
                    loadStudioCreative(currentStudioPeerId, currentStudioThreshold);
                }
            }
        @endif
    });

    function onStudioPeerChange(peerId) {
        currentStudioPeerId = peerId;
        if (peerId) {
            loadStudioCreative(peerId, currentStudioThreshold);
        }
    }

    function onStudioLevelChange(threshold) {
        currentStudioThreshold = parseInt(threshold) || 0;
        if (currentStudioPeerId) {
            loadStudioCreative(currentStudioPeerId, currentStudioThreshold);
        }
    }

    function refreshStudioCreative() {
        if (currentStudioPeerId) {
            loadStudioCreative(currentStudioPeerId, currentStudioThreshold);
        }
    }

    function selectLevelInStudio(threshold) {
        const select = document.getElementById('studioLevelSelect');
        if (select) {
            select.value = threshold;
            onStudioLevelChange(threshold);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function loadStudioCreative(peerId, threshold = 0) {
        const loading = document.getElementById('studioLoading');
        const workspace = document.getElementById('studioWorkspace');
        if (loading && workspace) {
            loading.classList.remove('hidden');
            workspace.classList.add('hidden');
        }

        const url = `/admin/life-impact-recognitions/${peerId}/creative-preview?threshold=${threshold}`;

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.error || 'Failed to load creative');
                return;
            }

            // Populate Peer Details
            document.getElementById('studioPeerName').textContent = data.peer.name || 'Peer Member';
            document.getElementById('studioPeerCompany').textContent = data.peer.company || 'Peers Global';
            document.getElementById('studioPeerCity').textContent = data.peer.city || 'Ahmedabad';
            document.getElementById('studioPeerDesignation').textContent = data.peer.designation || 'Member';
            document.getElementById('studioPeerCountBadge').textContent = `${(data.peer.life_impacted_count || 0).toLocaleString()} Lives`;
            document.getElementById('studioCaptionText').value = data.caption || '';

            // Populate Banner Level
            const levelBadge = document.getElementById('studioLevelBadgeTitle');
            if (levelBadge) levelBadge.textContent = data.meta.title || 'IMPACT CREATOR';
            const compText = document.getElementById('studioComplimentText');
            if (compText) compText.textContent = data.meta.quote ? `"${data.meta.quote}"` : '"Your contribution is making a lasting difference and supporting our mission of impacting 1 Million Entrepreneurs."';

            // Image Preview
            const img = document.getElementById('studioPreviewImage');
            img.src = data.preview_url + '&t=' + Date.now();
            document.getElementById('studioDownloadBtn').href = data.preview_url;
            document.getElementById('studioOpenFullBtn').href = data.preview_url;

            // Progression Checklist
            const progList = document.getElementById('studioProgressionList');
            progList.innerHTML = '';
            (data.peer_progression || []).forEach(tier => {
                const item = document.createElement('div');
                const isSelected = (tier.title === data.meta.title);
                item.className = `p-2 rounded-lg border text-xs flex items-center justify-between transition cursor-pointer ${isSelected ? 'bg-amber-500/20 border-amber-500/60 text-white font-bold' : (tier.is_unlocked ? 'bg-slate-950/80 border-slate-700 text-slate-200' : 'bg-slate-950/40 border-slate-800 text-slate-500 opacity-60')}`;
                
                item.onclick = function() {
                    document.getElementById('studioLevelSelect').value = tier.threshold;
                    onStudioLevelChange(tier.threshold);
                };

                const iconHtml = tier.is_unlocked
                    ? `<span class="badge bg-emerald-500 text-black text-[10px] font-extrabold px-1.5 py-0.5 rounded">✓ ${tier.threshold.toLocaleString()}</span>`
                    : `<span class="badge bg-slate-700 text-slate-300 text-[10px] px-1.5 py-0.5 rounded">🔒 ${tier.threshold.toLocaleString()}</span>`;

                const postTag = tier.posted_to_timeline
                    ? `<span class="chip px-1.5 py-0.5 text-[9px] font-bold bg-indigo-500/30 text-indigo-300 border-indigo-500/40">Posted</span>`
                    : '';

                item.innerHTML = `
                    <div class="flex items-center gap-2">
                        ${iconHtml}
                        <span>${tier.title}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        ${postTag}
                        ${isSelected ? '<i class="bi bi-check-circle-fill text-amber-400"></i>' : ''}
                    </div>
                `;
                progList.appendChild(item);
            });

            // Update Post Button Feedback
            const feedback = document.getElementById('studioPostFeedback');
            if (data.timeline_status && data.timeline_status.is_posted) {
                feedback.innerHTML = `<span class="text-emerald-400 font-semibold"><i class="bi bi-check-circle me-1"></i>Already posted to Timeline on ${data.timeline_status.posted_at} (<a href="${data.timeline_status.post_view_url}" target="_blank" class="text-amber-400 underline">View Post</a>)</span>`;
                feedback.classList.remove('hidden');
            } else {
                feedback.innerHTML = '';
                feedback.classList.add('hidden');
            }

            loading.classList.add('hidden');
            workspace.classList.remove('hidden');
        })
        .catch(err => {
            console.error(err);
            loading.classList.add('hidden');
            alert('Error loading creative preview graphic.');
        });
    }

    function copyStudioCaption() {
        const text = document.getElementById('studioCaptionText').value;
        navigator.clipboard.writeText(text).then(() => {
            const btn = document.getElementById('copyBtnText');
            btn.textContent = 'Copied! ✓';
            setTimeout(() => { btn.textContent = 'Copy Caption'; }, 2000);
        });
    }

    function broadcastStudioCreativeToTimeline() {
        if (!currentStudioPeerId) return;

        const btn = document.getElementById('studioPostTimelineBtn');
        const label = document.getElementById('studioPostBtnLabel');
        const feedback = document.getElementById('studioPostFeedback');

        btn.disabled = true;
        label.textContent = 'Broadcasting to Timeline...';

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch(`/admin/life-impact-recognitions/${currentStudioPeerId}/post-creative`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                threshold: currentStudioThreshold
            })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            label.textContent = 'Post Recognition to Timeline 🎉';

            if (data.success) {
                feedback.innerHTML = `<div class="p-2.5 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 font-bold">${data.message} <a href="${data.view_url}" target="_blank" class="text-white underline ml-1">View Post →</a></div>`;
                feedback.classList.remove('hidden');
                loadStudioCreative(currentStudioPeerId, currentStudioThreshold);
            } else {
                feedback.innerHTML = `<div class="p-2.5 rounded-xl bg-rose-500/20 border border-rose-500/40 text-rose-300 font-bold">${data.error || 'Failed to post'}</div>`;
                feedback.classList.remove('hidden');
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            label.textContent = 'Post Recognition to Timeline 🎉';
            alert('Failed to post creative to timeline.');
        });
    }

    // Modal Handlers (Tab 1)
    function openCreativeModal(peerId, peerName, count) {
        modalActivePeerId = peerId;
        const modal = new bootstrap.Modal(document.getElementById('quickCreativeModal'));
        modal.show();

        const loading = document.getElementById('modalLoading');
        const content = document.getElementById('modalContent');
        loading.classList.remove('hidden');
        content.classList.add('hidden');

        fetch(`/admin/life-impact-recognitions/${peerId}/creative-preview`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('modalGraphicImg').src = data.preview_url + '&t=' + Date.now();
            document.getElementById('modalPeerName').textContent = data.peer.name;
            document.getElementById('modalPeerSub').textContent = `${data.peer.company || 'Peers Global'} • ${data.peer.city || 'Ahmedabad'}`;
            document.getElementById('modalTierTitle').textContent = data.meta.title;
            document.getElementById('modalLivesCount').textContent = `${(data.peer.life_impacted_count || 0).toLocaleString()} Lives`;
            document.getElementById('modalCaptionText').value = data.caption;

            loading.classList.add('hidden');
            content.classList.remove('hidden');
        });
    }

    function copyModalCaption() {
        const text = document.getElementById('modalCaptionText').value;
        navigator.clipboard.writeText(text).then(() => {
            const btn = document.getElementById('modalCopyBtnText');
            btn.textContent = 'Copied! ✓';
            setTimeout(() => { btn.textContent = 'Copy Caption'; }, 2000);
        });
    }

    function postModalCreativeToTimeline() {
        if (!modalActivePeerId) return;

        const btn = document.getElementById('modalPostTimelineBtn');
        const feedback = document.getElementById('modalPostFeedback');
        btn.disabled = true;
        btn.textContent = 'Posting...';

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch(`/admin/life-impact-recognitions/${modalActivePeerId}/post-creative`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({})
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = 'Post to Timeline 🎉';

            if (data.success) {
                feedback.innerHTML = `<span class="text-emerald-400 font-bold">${data.message}</span>`;
                feedback.classList.remove('hidden');
            } else {
                feedback.innerHTML = `<span class="text-rose-400 font-bold">${data.error || 'Failed'}</span>`;
                feedback.classList.remove('hidden');
            }
        });
    }
</script>
@endpush
@endsection
