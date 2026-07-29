@extends('admin.layouts.app')

@section('title', 'All Ads')

@include('admin.partials.grid-head')

@section('content')
    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
            <ul class="mb-0 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">All Ads</h2>
                <p class="text-xs t3 m-0 mt-0.5">Manage system banner advertisements, popups, and placements.</p>
            </div>
            <a href="{{ route('admin.ads.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1">
                ➕ Add Ad
            </a>
        </div>

        <!-- Filters & Search Card -->
        <div class="p-3 rounded-lg border bs surface-2">
            <form method="GET" action="{{ route('admin.ads.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-2.5 items-end">
                <div class="md:col-span-2">
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                    <input type="text" name="q" value="{{ $search }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Search ads by title, subtitle...">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Placement</label>
                    <select name="placement" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="">All Placements</option>
                        <option value="timeline" @selected(($placement ?? '') == 'timeline')>Timeline</option>
                        <option value="dashboard" @selected(($placement ?? '') == 'dashboard')>Dashboard</option>
                        <option value="home" @selected(($placement ?? '') == 'home')>Home</option>
                        <option value="banner" @selected(($placement ?? '') == 'banner')>Banner</option>
                        <option value="popup" @selected(($placement ?? '') == 'popup')>Popup</option>
                        <option value="sidebar" @selected(($placement ?? '') == 'sidebar')>Sidebar</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                    <select name="status" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="">All Statuses</option>
                        <option value="active" @selected(($status ?? '') == 'active')>Active</option>
                        <option value="inactive" @selected(($status ?? '') == 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-span-full flex justify-end">
                    <a href="{{ route('admin.ads.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center no-underline">Clear</a>
                </div>
            </form>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left w-16">Image</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Ad Title</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Placement</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Views</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Clicks</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse($ads as $ad)
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5">
                                    @if($ad->image_url)
                                        <img src="{{ $ad->image_url }}" alt="{{ $ad->title }}" class="w-10 h-10 rounded object-cover border bs">
                                    @else
                                        <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center font-bold t3 border bs text-xs">
                                            📢
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    <div class="font-semibold t1">
                                        <a href="{{ route('admin.ads.show', $ad->id) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                            {{ $ad->title }}
                                        </a>
                                    </div>
                                    @if($ad->subtitle)
                                        <div class="t3 text-[11px] mt-0.5">{{ $ad->subtitle }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">
                                        {{ ucfirst($ad->placement ?? 'unassigned') }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if($ad->is_active)
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Active</span>
                                    @else
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs font-medium t1">{{ number_format($ad->views_count ?? 0) }}</td>
                                <td class="px-3 py-2.5 text-center text-xs font-medium t1">{{ number_format($ad->clicks_count ?? 0) }}</td>
                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-1.5 items-center">
                                        <a href="{{ route('admin.ads.show', $ad) }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">View</a>
                                        <a href="{{ route('admin.ads.edit', $ad) }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">Edit</a>
                                        <form method="POST" action="{{ route('admin.ads.toggle-status', $ad) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="px-2 py-0.5 text-xs font-semibold rounded border bs t2 hover:t1 transition">
                                                {{ $ad->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.ads.destroy', $ad) }}" class="inline" onsubmit="return confirm('Delete this ad permanently? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2 py-0.5 text-xs font-semibold rounded bg-rose-600 hover:bg-rose-500 text-white transition focus-ring">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-8 text-xs t3">No ads found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $ads->links() }}
            </div>
        </div>
    </div>
@endsection

