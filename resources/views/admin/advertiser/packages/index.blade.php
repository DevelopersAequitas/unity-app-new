@extends('admin.layouts.app')

@section('title', 'Advertiser Packages')

@include('admin.partials.grid-head')

@section('content')
    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="bi bi-check-circle-fill text-emerald-600"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="text-emerald-700 hover:text-emerald-900 border-0 bg-transparent cursor-pointer text-sm" onclick="this.parentElement.remove()">&times;</button>
        </div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        {{-- Header Bar --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
                <h1 class="text-xl font-bold t1 flex items-center gap-2">
                    <i class="bi bi-box-seam text-indigo-600"></i>
                    Advertiser Packages
                </h1>
                <p class="text-xs t3 mt-0.5">Manage subscription tiers, pricing in INR, duration and creative limits.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.advertiser.applications.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition no-underline">
                    <i class="bi bi-file-earmark-person me-1"></i> Applications
                </a>
                <a href="{{ route('admin.advertiser.packages.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition no-underline flex items-center gap-1.5 shadow-sm">
                    <i class="bi bi-plus-lg"></i> Create Package
                </a>
            </div>
        </div>

        {{-- Clickable KPI Tabs with Rich Color Themes & Effects --}}
        @php
            $currentPkgStatus = request('status');
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
            {{-- All Packages --}}
            <a href="{{ route('admin.advertiser.packages.index') }}" 
               class="group p-4 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1.5 hover:shadow-xl relative overflow-hidden {{ !$currentPkgStatus ? 'bg-gradient-to-br from-indigo-100/80 via-white to-purple-50 border-indigo-500 shadow-lg shadow-indigo-100/50 ring-2 ring-indigo-400/30' : 'bg-gradient-to-br from-indigo-50/40 via-white to-slate-50/30 border-slate-200 hover:border-indigo-400 hover:shadow-md' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold tracking-wider uppercase {{ !$currentPkgStatus ? 'text-indigo-900' : 'text-slate-600' }}">All Packages</span>
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center text-sm shadow-md shadow-indigo-200 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-boxes"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-slate-900 mt-2">{{ $counts['total'] }}</div>
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-100/80">
                    <span class="text-[11px] font-medium text-slate-500">Total configured plans</span>
                    @if(!$currentPkgStatus)
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-indigo-700 bg-indigo-100/80 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-600 animate-pulse"></span> Active View
                        </span>
                    @else
                        <span class="text-[10px] text-slate-400 group-hover:text-indigo-600 transition">Click to view &rarr;</span>
                    @endif
                </div>
            </a>

            {{-- Active Packages --}}
            <a href="{{ route('admin.advertiser.packages.index', ['status' => 'active']) }}" 
               class="group p-4 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1.5 hover:shadow-xl relative overflow-hidden {{ $currentPkgStatus === 'active' ? 'bg-gradient-to-br from-emerald-100/80 via-white to-teal-50 border-emerald-500 shadow-lg shadow-emerald-100/50 ring-2 ring-emerald-400/30' : 'bg-gradient-to-br from-emerald-50/40 via-white to-slate-50/30 border-slate-200 hover:border-emerald-400 hover:shadow-md' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold tracking-wider uppercase {{ $currentPkgStatus === 'active' ? 'text-emerald-900' : 'text-emerald-700' }}">Active Packages</span>
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-600 text-white flex items-center justify-center text-sm shadow-md shadow-emerald-200 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-shield-check"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-emerald-600 mt-2">{{ $counts['active'] }}</div>
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-emerald-100/50">
                    <span class="text-[11px] font-medium text-emerald-700/80">Publicly visible</span>
                    @if($currentPkgStatus === 'active')
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span> Active View
                        </span>
                    @else
                        <span class="text-[10px] text-emerald-600/70 group-hover:text-emerald-700 transition">Click to view &rarr;</span>
                    @endif
                </div>
            </a>

            {{-- Inactive Packages --}}
            <a href="{{ route('admin.advertiser.packages.index', ['status' => 'inactive']) }}" 
               class="group p-4 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1.5 hover:shadow-xl relative overflow-hidden {{ $currentPkgStatus === 'inactive' ? 'bg-gradient-to-br from-slate-100 via-white to-slate-100/60 border-slate-500 shadow-lg ring-2 ring-slate-400/30' : 'bg-gradient-to-br from-slate-50/40 via-white to-slate-50/30 border-slate-200 hover:border-slate-400 hover:shadow-md' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-extrabold tracking-wider uppercase {{ $currentPkgStatus === 'inactive' ? 'text-slate-800' : 'text-slate-500' }}">Inactive Packages</span>
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-slate-500 to-slate-700 text-white flex items-center justify-center text-sm shadow-md shadow-slate-200 group-hover:scale-110 transition-transform duration-300">
                        <i class="bi bi-slash-circle"></i>
                    </div>
                </div>
                <div class="text-3xl font-black text-slate-600 mt-2">{{ $counts['inactive'] }}</div>
                <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-100">
                    <span class="text-[11px] font-medium text-slate-500">Hidden from applicants</span>
                    @if($currentPkgStatus === 'inactive')
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-700 bg-slate-200 px-2 py-0.5 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-600 animate-pulse"></span> Active View
                        </span>
                    @else
                        <span class="text-[10px] text-slate-400 group-hover:text-slate-600 transition">Click to view &rarr;</span>
                    @endif
                </div>
            </a>
        </div>

        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('admin.advertiser.packages.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-2 pt-1">
            <div class="md:col-span-3">
                <div class="relative">
                    <i class="bi bi-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search package by name or description..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border bs bg-surface focus:outline-none focus:ring-1 focus:ring-indigo-500">
                </div>
            </div>
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition shadow-sm border-0 cursor-pointer">
                    Filter
                </button>
                <a href="{{ route('admin.advertiser.packages.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition text-center no-underline">
                    Clear
                </a>
            </div>
        </form>

        {{-- Table --}}
        <div class="overflow-x-auto rounded-lg border bs">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-50 border-b bs font-semibold t2">
                    <tr>
                        <th class="p-3">Order</th>
                        <th class="p-3">Package Name</th>
                        <th class="p-3">Duration</th>
                        <th class="p-3">Price</th>
                        <th class="p-3">Placement</th>
                        <th class="p-3">Features</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($packages as $pkg)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="p-3 font-mono text-slate-400 font-semibold">{{ $pkg->sort_order }}</td>
                            <td class="p-3">
                                <div class="font-bold t1 text-sm">{{ $pkg->name }}</div>
                                <div class="text-slate-400 text-xs font-mono">{{ $pkg->slug }}</div>
                            </td>
                            <td class="p-3 font-semibold">{{ $pkg->duration_months }} Month(s)</td>
                            <td class="p-3 font-bold text-emerald-600 text-sm">
                                {{ $pkg->currency }} {{ number_format((float) $pkg->price, 2) }}
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-xs font-medium">
                                    {{ $pkg->banner_placement ?: 'Standard' }}
                                </span>
                            </td>
                            <td class="p-3 text-slate-500">
                                @if(is_array($pkg->features) && count($pkg->features) > 0)
                                    <span class="text-xs">{{ count($pkg->features) }} feature(s)</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="p-3">
                                @if($pkg->is_active)
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold">Active</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200 text-xs font-semibold">Inactive</span>
                                @endif
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <form method="POST" action="{{ route('admin.advertiser.packages.toggle-status', $pkg) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="px-2.5 py-1 text-xs rounded border bs hover:bg-slate-100 transition">
                                            {{ $pkg->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.advertiser.packages.edit', $pkg) }}" class="px-2.5 py-1 text-xs rounded bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition no-underline font-semibold">
                                        Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-slate-400">
                                <i class="bi bi-inbox text-3xl block mb-2"></i>
                                No packages found. Click "Create Package" to get started.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($packages->hasPages())
            <div class="pt-2">
                {{ $packages->links() }}
            </div>
        @endif
    </div>
@endsection
