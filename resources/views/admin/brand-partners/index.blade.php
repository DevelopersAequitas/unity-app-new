@extends('admin.layouts.app')

@section('title', 'Brand Partners')

@include('admin.partials.grid-head')

@section('content')
    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Brand Partners</h2>
                <p class="text-xs t3 m-0 mt-0.5">Manage partner listings, offers, analytics, and featured spots.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.brand-partners.dashboard') }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">Dashboard</a>
                <a href="{{ route('admin.brand-partners.categories.index') }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">Categories</a>
                <a href="{{ route('admin.brand-partners.offers') }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">Offers</a>
                @if(auth('admin')->user() && in_array(auth('admin')->user()->roles->pluck('key')->first(), ['global_admin', 'marketing_team', 'content_team']))
                    <a href="{{ route('admin.brand-partners.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1">
                        ➕ Add Partner
                    </a>
                @endif
            </div>
        </div>

        <!-- Filters & Search Card -->
        <div class="p-3 rounded-lg border bs surface-2">
            <form method="GET" action="{{ route('admin.brand-partners.index') }}" class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-7 gap-2.5 items-end">
                <div class="md:col-span-2">
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                    <input type="text" name="q" value="{{ $search }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Search by name, slug, or offer...">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Category</label>
                    <select name="category_id" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected($categoryId == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                    <select name="status" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="">All Statuses</option>
                        <option value="active" @selected($status == 'active')>Active</option>
                        <option value="inactive" @selected($status == 'inactive')>Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Featured</label>
                    <select name="featured" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="">Any</option>
                        <option value="1" @selected($featured == '1')>Yes</option>
                        <option value="0" @selected($featured == '0')>No</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Sponsored</label>
                    <select name="sponsored" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="">Any</option>
                        <option value="1" @selected($sponsored == '1')>Yes</option>
                        <option value="0" @selected($sponsored == '0')>No</option>
                    </select>
                </div>
                <div class="flex justify-end">
                    <a href="{{ route('admin.brand-partners.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center no-underline">Clear</a>
                </div>
            </form>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left w-14">Logo</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Brand Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Category</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Featured</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Sponsored</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Active Offer</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Views</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Clicks</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse($partners as $partner)
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5">
                                    @if($partner->logo_url)
                                        <img src="{{ $partner->logo_url }}" alt="{{ $partner->name }}" class="w-9 h-9 rounded-full object-cover border bs">
                                    @else
                                        <div class="w-9 h-9 rounded-full surface-2 text-indigo-600 font-bold flex items-center justify-center border bs text-xs">
                                            {{ Str::upper(Str::substr($partner->name, 0, 2)) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    <div class="font-semibold t1">{{ $partner->name }}</div>
                                    <div class="t3 text-[11px]">/{{ $partner->slug }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">
                                        {{ $partner->category?->name ?? 'Uncategorized' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs">
                                    <form method="POST" action="{{ route('admin.brand-partners.toggle-featured', $partner) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="border-0 bg-transparent cursor-pointer">
                                            {{ $partner->is_featured ? '⭐' : '☆' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs">
                                    <form method="POST" action="{{ route('admin.brand-partners.toggle-sponsored', $partner) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="border-0 bg-transparent cursor-pointer">
                                            {{ $partner->is_sponsored ? '🏆' : '⚪' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if($partner->offer_title)
                                        <div class="font-medium text-emerald-700">{{ $partner->offer_title }}</div>
                                        @if($partner->coupon_code)
                                            <code class="text-[10px] font-mono bg-gray-100 px-1.5 py-0.5 rounded border bs">{{ $partner->coupon_code }}</code>
                                        @endif
                                    @else
                                        <span class="t3">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if($partner->is_active)
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Active</span>
                                    @else
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs font-medium t1">{{ number_format($partner->views_count) }}</td>
                                <td class="px-3 py-2.5 text-center text-xs font-medium t1">{{ number_format($partner->clicks_count) }}</td>
                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-1.5 items-center">
                                        <a href="{{ route('admin.brand-partners.show', $partner) }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">View</a>
                                        @if(auth('admin')->user() && in_array(auth('admin')->user()->roles->pluck('key')->first(), ['global_admin', 'marketing_team', 'content_team']))
                                            <a href="{{ route('admin.brand-partners.edit', $partner) }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">Edit</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-8 text-xs t3">No brand partners found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $partners->links() }}
            </div>
        </div>
    </div>
@endsection

