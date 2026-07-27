@extends('admin.layouts.app')
@section('title', 'Circulars')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Circulars</h2>
            <p class="text-xs t3 m-0 mt-0.5">Manage community announcements, priority broadcasts, and notifications.</p>
        </div>
        <a href="{{ route('admin.circulars.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1">
            ➕ Create Circular
        </a>
    </div>

    <form class="border bs rounded-xl p-3.5 mb-4 surface-2" method="GET">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="block text-[11px] t3 mb-1 font-medium">Search</label>
                <input class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="search" placeholder="Search title/summary" value="{{ $filters['search'] }}">
            </div>
            <div class="col-md-2">
                <label class="block text-[11px] t3 mb-1 font-medium">Category</label>
                <select class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="category"><option value="">Category</option>@foreach($categories as $i)<option value="{{ $i }}" @selected($filters['category']===$i)>{{ $i }}</option>@endforeach</select>
            </div>
            <div class="col-md-2">
                <label class="block text-[11px] t3 mb-1 font-medium">Priority</label>
                <select class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="priority"><option value="">Priority</option>@foreach($priorities as $i)<option value="{{ $i }}" @selected($filters['priority']===$i)>{{ $i }}</option>@endforeach</select>
            </div>
            <div class="col-md-2">
                <label class="block text-[11px] t3 mb-1 font-medium">Status</label>
                <select class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="status"><option value="">Status</option>@foreach($statuses as $i)<option value="{{ $i }}" @selected($filters['status']===$i)>{{ $i }}</option>@endforeach</select>
            </div>
            <div class="col-md-3">
                <label class="block text-[11px] t3 mb-1 font-medium">Audience</label>
                <select class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="audience_type"><option value="">Audience</option>@foreach($audiences as $i)<option value="{{ $i }}" @selected($filters['audience_type']===$i)>{{ $i }}</option>@endforeach</select>
            </div>
            <div class="col-md-3">
                <label class="block text-[11px] t3 mb-1 font-medium">City</label>
                <select class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="city_id"><option value="">City</option>@foreach($cities as $city)<option value="{{ $city->id }}" @selected($filters['city_id']===(string)$city->id)>{{ $city->name }}</option>@endforeach</select>
            </div>
            <div class="col-md-3">
                <label class="block text-[11px] t3 mb-1 font-medium">Circle</label>
                <select class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="circle_id"><option value="">Circle</option>@foreach($circles as $circle)<option value="{{ $circle->id }}" @selected($filters['circle_id']===(string)$circle->id)>{{ $circle->name }}</option>@endforeach</select>
            </div>
            <div class="col-md-2">
                <label class="block text-[11px] t3 mb-1 font-medium">Publish From</label>
                <input type="date" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="publish_date_from" value="{{ $filters['publish_date_from'] }}">
            </div>
            <div class="col-md-2">
                <label class="block text-[11px] t3 mb-1 font-medium">Publish To</label>
                <input type="date" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="publish_date_to" value="{{ $filters['publish_date_to'] }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring w-100">Apply</button>
                <a href="{{ route('admin.circulars.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline w-100">Clear</a>
            </div>
        </div>
    </form>

    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="overflow-x-auto relative">
            <table class="min-w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Image</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Title</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Category</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Priority</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Audience</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Publish</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Expiry</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Pinned</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Push</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/50">
                @forelse($circulars as $circular)
                    <tr class="hover:surface-2 transition border-b bs">
                        <td class="px-3 py-2.5">
                            @if($circular->featured_image_url)
                                <img src="{{ $circular->featured_image_url }}" class="w-10 h-10 object-cover rounded-lg border bs">
                            @else
                                <span class="t3">-</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 font-semibold t1 text-[12.5px] whitespace-nowrap">{{ $circular->title }}</td>
                        <td class="px-3 py-2.5 text-xs t2">{{ $circular->category }}</td>
                        <td class="px-3 py-2.5 text-xs">
                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">{{ $circular->priority }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-xs t2">{{ $circular->audience_type }}</td>
                        <td class="px-3 py-2.5 text-xs">
                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">{{ $circular->status }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ optional($circular->publish_date)->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ optional($circular->expiry_date)->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2.5 text-center text-xs t2">{{ $circular->is_pinned ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2.5 text-center text-xs t2">{{ $circular->send_push_notification ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ optional($circular->created_at)->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5">
                                <a class="px-2.5 py-1 rounded-lg border bs text-xs font-medium text-indigo-600 hover:text-indigo-700 surface-2 transition no-underline" href="{{ route('admin.circulars.show', $circular) }}">View</a>
                                <a class="px-2.5 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 transition no-underline" href="{{ route('admin.circulars.edit', $circular) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.circulars.destroy', $circular) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 text-xs font-semibold" onclick="return confirm('Delete this circular?')">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="text-center py-8 text-xs t3">No circulars found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
            {{ $circulars->links() }}
        </div>
    </div>
</div>
@endsection

