@extends('admin.layouts.app')

@section('title', 'Advertiser Applications')

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
                    <i class="bi bi-file-earmark-person text-indigo-600"></i>
                    Advertiser Applications Review Queue
                </h1>
                <p class="text-xs t3 mt-0.5">Review submissions, approve campaigns, request creative revisions, and track onboarding.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.advertiser.packages.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition no-underline">
                    <i class="bi bi-box-seam me-1"></i> Packages
                </a>
                <a href="{{ route('admin.advertiser.subscriptions.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition no-underline">
                    <i class="bi bi-card-checklist me-1"></i> Subscriptions
                </a>
            </div>
        </div>

        {{-- KPI Quick Filters with Rich Colors & Effects --}}
        @php
            $currentAppStatus = request('status');
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
            {{-- All Apps --}}
            <a href="{{ route('admin.advertiser.applications.index') }}" 
               class="group p-3 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1 hover:shadow-lg relative overflow-hidden {{ !$currentAppStatus ? 'bg-gradient-to-br from-indigo-100/90 via-white to-purple-50 border-indigo-500 shadow-md ring-2 ring-indigo-400/30' : 'bg-gradient-to-br from-indigo-50/40 via-white to-slate-50/20 border-slate-200 hover:border-indigo-400' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider {{ !$currentAppStatus ? 'text-indigo-900' : 'text-slate-600' }}">All Apps</span>
                    <div class="w-7 h-7 rounded-lg bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center text-xs shadow-xs group-hover:scale-110 transition-transform">
                        <i class="bi bi-stack"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-slate-900 mt-1.5">{{ $counts['total'] }}</div>
                <div class="mt-1 text-[10px] font-semibold text-slate-400 group-hover:text-indigo-600 transition">
                    @if(!$currentAppStatus)
                        <span class="text-indigo-700 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-indigo-600 animate-pulse"></span> Active View</span>
                    @else
                        All Submissions
                    @endif
                </div>
            </a>

            {{-- Submitted --}}
            <a href="{{ route('admin.advertiser.applications.index', ['status' => 'submitted']) }}" 
               class="group p-3 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1 hover:shadow-lg relative overflow-hidden {{ $currentAppStatus === 'submitted' ? 'bg-gradient-to-br from-sky-100/90 via-white to-cyan-50 border-sky-500 shadow-md ring-2 ring-sky-400/30' : 'bg-gradient-to-br from-sky-50/40 via-white to-slate-50/20 border-slate-200 hover:border-sky-400' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider {{ $currentAppStatus === 'submitted' ? 'text-sky-900' : 'text-sky-700' }}">Submitted</span>
                    <div class="w-7 h-7 rounded-lg bg-gradient-to-tr from-sky-500 to-cyan-600 text-white flex items-center justify-center text-xs shadow-xs group-hover:scale-110 transition-transform">
                        <i class="bi bi-send-fill"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-sky-600 mt-1.5">{{ $counts['submitted'] }}</div>
                <div class="mt-1 text-[10px] font-semibold text-sky-600/80">
                    @if($currentAppStatus === 'submitted')
                        <span class="text-sky-800 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-sky-600 animate-pulse"></span> Active View</span>
                    @else
                        New Requests
                    @endif
                </div>
            </a>

            {{-- In Review --}}
            <a href="{{ route('admin.advertiser.applications.index', ['status' => 'under_review']) }}" 
               class="group p-3 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1 hover:shadow-lg relative overflow-hidden {{ $currentAppStatus === 'under_review' ? 'bg-gradient-to-br from-amber-100/90 via-white to-orange-50 border-amber-500 shadow-md ring-2 ring-amber-400/30' : 'bg-gradient-to-br from-amber-50/40 via-white to-slate-50/20 border-slate-200 hover:border-amber-400' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider {{ $currentAppStatus === 'under_review' ? 'text-amber-900' : 'text-amber-700' }}">In Review</span>
                    <div class="w-7 h-7 rounded-lg bg-gradient-to-tr from-amber-500 to-orange-500 text-white flex items-center justify-center text-xs shadow-xs group-hover:scale-110 transition-transform">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-amber-600 mt-1.5">{{ $counts['under_review'] }}</div>
                <div class="mt-1 text-[10px] font-semibold text-amber-600/80">
                    @if($currentAppStatus === 'under_review')
                        <span class="text-amber-800 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-amber-600 animate-pulse"></span> Active View</span>
                    @else
                        Under Check
                    @endif
                </div>
            </a>

            {{-- Changes Requested --}}
            <a href="{{ route('admin.advertiser.applications.index', ['status' => 'changes_requested']) }}" 
               class="group p-3 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1 hover:shadow-lg relative overflow-hidden {{ $currentAppStatus === 'changes_requested' ? 'bg-gradient-to-br from-purple-100/90 via-white to-pink-50 border-purple-500 shadow-md ring-2 ring-purple-400/30' : 'bg-gradient-to-br from-purple-50/40 via-white to-slate-50/20 border-slate-200 hover:border-purple-400' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider {{ $currentAppStatus === 'changes_requested' ? 'text-purple-900' : 'text-purple-700' }}">Changes Req.</span>
                    <div class="w-7 h-7 rounded-lg bg-gradient-to-tr from-purple-600 to-pink-600 text-white flex items-center justify-center text-xs shadow-xs group-hover:scale-110 transition-transform">
                        <i class="bi bi-arrow-repeat"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-purple-600 mt-1.5">{{ $counts['changes_requested'] }}</div>
                <div class="mt-1 text-[10px] font-semibold text-purple-600/80">
                    @if($currentAppStatus === 'changes_requested')
                        <span class="text-purple-800 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-purple-600 animate-pulse"></span> Active View</span>
                    @else
                        Fix Needed
                    @endif
                </div>
            </a>

            {{-- Approved --}}
            <a href="{{ route('admin.advertiser.applications.index', ['status' => 'approved']) }}" 
               class="group p-3 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1 hover:shadow-lg relative overflow-hidden {{ $currentAppStatus === 'approved' ? 'bg-gradient-to-br from-emerald-100/90 via-white to-teal-50 border-emerald-500 shadow-md ring-2 ring-emerald-400/30' : 'bg-gradient-to-br from-emerald-50/40 via-white to-slate-50/20 border-slate-200 hover:border-emerald-400' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider {{ $currentAppStatus === 'approved' ? 'text-emerald-900' : 'text-emerald-700' }}">Approved</span>
                    <div class="w-7 h-7 rounded-lg bg-gradient-to-tr from-emerald-500 to-teal-600 text-white flex items-center justify-center text-xs shadow-xs group-hover:scale-110 transition-transform">
                        <i class="bi bi-patch-check-fill"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-emerald-600 mt-1.5">{{ $counts['approved'] }}</div>
                <div class="mt-1 text-[10px] font-semibold text-emerald-600/80">
                    @if($currentAppStatus === 'approved')
                        <span class="text-emerald-800 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span> Active View</span>
                    @else
                        Ready / Paid
                    @endif
                </div>
            </a>

            {{-- Rejected --}}
            <a href="{{ route('admin.advertiser.applications.index', ['status' => 'rejected']) }}" 
               class="group p-3 rounded-2xl border-2 transition-all duration-300 no-underline block transform hover:-translate-y-1 hover:shadow-lg relative overflow-hidden {{ $currentAppStatus === 'rejected' ? 'bg-gradient-to-br from-rose-100/90 via-white to-red-50 border-rose-500 shadow-md ring-2 ring-rose-400/30' : 'bg-gradient-to-br from-rose-50/40 via-white to-slate-50/20 border-slate-200 hover:border-rose-400' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider {{ $currentAppStatus === 'rejected' ? 'text-rose-900' : 'text-rose-700' }}">Rejected</span>
                    <div class="w-7 h-7 rounded-lg bg-gradient-to-tr from-rose-500 to-red-600 text-white flex items-center justify-center text-xs shadow-xs group-hover:scale-110 transition-transform">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-rose-600 mt-1.5">{{ $counts['rejected'] }}</div>
                <div class="mt-1 text-[10px] font-semibold text-rose-600/80">
                    @if($currentAppStatus === 'rejected')
                        <span class="text-rose-800 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-rose-600 animate-pulse"></span> Active View</span>
                    @else
                        Declined
                    @endif
                </div>
            </a>
        </div>

        {{-- Filters Bar --}}
        <form method="GET" action="{{ route('admin.advertiser.applications.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-2 items-end">
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Search</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="App #, Company, Email, Name" class="w-full px-3 py-1.5 text-xs rounded-lg border bs focus:ring-1 focus:ring-indigo-500 outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Package</label>
                <select name="package_id" class="w-full px-3 py-1.5 text-xs rounded-lg border bs focus:ring-1 focus:ring-indigo-500 outline-none bg-white">
                    <option value="">All Packages</option>
                    @foreach($packages as $p)
                        <option value="{{ $p->id }}" {{ request('package_id') === $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Source</label>
                <select name="source" class="w-full px-3 py-1.5 text-xs rounded-lg border bs focus:ring-1 focus:ring-indigo-500 outline-none bg-white">
                    <option value="">All Sources</option>
                    <option value="app" {{ request('source') === 'app' ? 'selected' : '' }}>Mobile App</option>
                    <option value="website" {{ request('source') === 'website' ? 'selected' : '' }}>Website</option>
                    <option value="admin" {{ request('source') === 'admin' ? 'selected' : '' }}>Admin</option>
                </select>
            </div>

            <div class="flex items-center gap-1.5">
                <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition border-0 cursor-pointer">Filter</button>
                <a href="{{ route('admin.advertiser.applications.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition text-center no-underline">Clear</a>
            </div>
        </form>

        {{-- Applications Table --}}
        <div class="overflow-x-auto rounded-lg border bs">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-50 border-b bs font-semibold t2">
                    <tr>
                        <th class="p-3">App #</th>
                        <th class="p-3">Company & Applicant</th>
                        <th class="p-3">Package Snapshot</th>
                        <th class="p-3">Source</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Submitted At</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($applications as $app)
                        @php
                            $badgeColor = match($app->status) {
                                'submitted' => 'bg-sky-50 text-sky-700 border-sky-200',
                                'under_review' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'changes_requested' => 'bg-purple-50 text-purple-700 border-purple-200',
                                'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                                default => 'bg-slate-100 text-slate-700 border-slate-200',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="p-3 font-mono font-bold text-indigo-600">
                                <a href="{{ route('admin.advertiser.applications.show', $app) }}" class="no-underline hover:underline">
                                    {{ $app->application_number }}
                                </a>
                            </td>
                            <td class="p-3">
                                <div class="font-bold t1 text-sm">{{ $app->company_name }}</div>
                                <div class="text-slate-500 text-xs">{{ $app->applicant_name }} &bull; {{ $app->applicant_email }}</div>
                            </td>
                            <td class="p-3">
                                <div class="font-semibold text-slate-800">{{ $app->requested_package_name ?: 'Standard Package' }}</div>
                                <div class="text-emerald-600 font-bold font-mono">
                                    {{ $app->requested_currency }} {{ number_format((float)$app->requested_package_price, 2) }}
                                </div>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 rounded uppercase font-bold text-[10px] bg-slate-100 text-slate-600">
                                    {{ $app->source }}
                                </span>
                            </td>
                            <td class="p-3">
                                <span class="px-2.5 py-0.5 rounded-full border text-xs font-semibold capitalize {{ $badgeColor }}">
                                    {{ str_replace('_', ' ', $app->status) }}
                                </span>
                            </td>
                            <td class="p-3 text-slate-500">
                                {{ $app->created_at->format('M d, Y H:i') }}
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.advertiser.applications.show', $app) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs shadow-sm transition no-underline whitespace-nowrap">
                                    <i class="bi bi-eye"></i>
                                    <span>Review</span>
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400">
                                <i class="bi bi-file-earmark-x text-3xl block mb-2"></i>
                                No advertiser applications found matching current criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($applications->hasPages())
            <div class="pt-2">
                {{ $applications->links() }}
            </div>
        @endif
    </div>
@endsection
