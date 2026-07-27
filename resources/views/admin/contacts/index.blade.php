@extends('admin.layouts.app')

@section('title', 'Unity Contacts')

@include('admin.partials.grid-head')

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Unity Contacts</h2>
            <p class="text-xs t3 m-0 mt-0.5">Manage imported and submitted contact details.</p>
        </div>
    </div>

    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="p-4">
            {{-- Filter Form --}}
            <form method="GET" action="{{ route('admin.contacts.index') }}" class="border bs rounded-xl p-3.5 mb-4 surface-2">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="block text-[11px] t3 mb-1 font-medium" for="search">Search</label>
                        <input type="text" id="search" name="search" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['search'] ?? '' }}" placeholder="Name/Email/Phone/Company">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="block text-[11px] t3 mb-1 font-medium" for="company">Company</label>
                        <select id="company" name="company" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                            <option value="">All Companies</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company }}" @selected(($filters['company'] ?? '') === $company)>{{ $company }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="block text-[11px] t3 mb-1 font-medium" for="job_title">Job Title</label>
                        <select id="job_title" name="job_title" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                            <option value="">All Job Titles</option>
                            @foreach ($jobTitles as $jobTitle)
                                <option value="{{ $jobTitle }}" @selected(($filters['job_title'] ?? '') === $jobTitle)>{{ $jobTitle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-1.5">
                        <label class="block text-[11px] t3 mb-1 font-medium" for="from_date">Date From</label>
                        <input type="date" id="from_date" name="from_date" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['from_date'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-1.5">
                        <label class="block text-[11px] t3 mb-1 font-medium" for="to_date">Date To</label>
                        <input type="date" id="to_date" name="to_date" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" value="{{ $filters['to_date'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="block text-[11px] t3 mb-1 font-medium" for="quick">Quick</label>
                        <select id="quick" name="quick" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                            <option value="any" @selected(($filters['quick'] ?? 'any') === 'any')>Any</option>
                            <option value="today" @selected(($filters['quick'] ?? 'any') === 'today')>Today</option>
                            <option value="this_week" @selected(($filters['quick'] ?? 'any') === 'this_week')>This Week</option>
                            <option value="this_month" @selected(($filters['quick'] ?? 'any') === 'this_month')>This Month</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-auto d-flex justify-content-end ms-auto">
                        <a href="{{ route('admin.contacts.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline">Clear</a>
                    </div>
                </div>
            </form>

            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Contact Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Phone</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 180px;">Company</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width: 180px;">Job Title</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Email</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Total Contacts</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Latest Date</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($contactPosts as $contactPost)
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 font-semibold t1 text-[12.5px] whitespace-nowrap">
                                    {{ $contactPost->full_name ?: trim(collect([$contactPost->first_name, $contactPost->middle_name, $contactPost->last_name])->filter()->implode(' ')) ?: '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-[12.5px] whitespace-nowrap">
                                    {{ $contactPost->phone ?: '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-[12.5px]">
                                    <span class="t1">{{ $contactPost->company ?: '-' }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-[12.5px]">
                                    <span class="t2">{{ $contactPost->job_title ?: '-' }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-[12.5px]">
                                    <span class="t2">{{ $contactPost->email ?: '-' }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">
                                        {{ number_format($contactPost->total_contacts ?? 0) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                    {{ optional($contactPost->latest_created_at ? \Illuminate\Support\Carbon::parse($contactPost->latest_created_at) : null)->format('d M Y, h:i A') ?: '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.contacts.user-details', $contactPost->user_id) }}" class="px-2.5 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-8 text-xs t3">No contacts found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Bottom Toolbar & Pagination --}}
            <div id="grid-pagination" class="flex justify-between items-center mt-4 flex-wrap gap-2 pt-3 border-t bs">
                <div>
                    {{ $contactPosts->links() }}
                </div>
                <div class="text-xs t3">
                    @if($contactPosts->total() > 0)
                        Showing <span class="font-semibold t1">{{ $contactPosts->firstItem() }}-{{ $contactPosts->lastItem() }}</span> of <span class="font-semibold t1">{{ $contactPosts->total() }}</span> records
                    @else
                        No records
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

