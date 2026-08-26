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
                <a href="{{ route('admin.brand-partners.dashboard') }}" class="px-3 py-1 text-xs font-semibold rounded-full border bs t2 hover:t1 hover:surface-2 transition no-underline">Dashboard</a>
                <a href="{{ route('admin.brand-partners.categories.index') }}" class="px-3 py-1 text-xs font-semibold rounded-full border bs t2 hover:t1 hover:surface-2 transition no-underline">Categories</a>
                <a href="{{ route('admin.brand-partners.offers') }}" class="px-3 py-1 text-xs font-semibold rounded-full border bs t2 hover:t1 hover:surface-2 transition no-underline">Offers</a>
                @if(auth('admin')->user() && in_array(auth('admin')->user()->roles->pluck('key')->first(), ['global_admin', 'marketing_team', 'content_team']))
                    <a href="{{ route('admin.brand-partners.create') }}" class="px-3 py-1 rounded-full bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1">
                        <i class="bi bi-plus-lg admin-icon me-1" aria-hidden="true"></i>Add Partner
                    </a>
                @endif
            </div>
        </div>

        <!-- Filters & Search Card -->
        <div class="p-3 rounded-lg border bs surface-2">
            <form method="GET" action="{{ route('admin.brand-partners.index') }}" class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-7 gap-2.5 items-end">
                <div class="md:col-span-2">
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                    <input type="text" name="q" value="{{ $search }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Search by name, slug, or offer..." onkeydown="if (event.key === 'Enter') this.form.submit()">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Category</label>
                    <select name="category_id" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected($categoryId == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                    <select name="status" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="active" @selected($status == 'active')>Active</option>
                        <option value="inactive" @selected($status == 'inactive')>Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Featured</label>
                    <select name="featured" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onchange="this.form.submit()">
                        <option value="">Any</option>
                        <option value="1" @selected($featured == '1')>Yes</option>
                        <option value="0" @selected($featured == '0')>No</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Sponsored</label>
                    <select name="sponsored" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onchange="this.form.submit()">
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
                                    <div class="font-semibold t1">
                                        <a href="{{ route('admin.brand-partners.show', $partner) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                            {{ $partner->name }}
                                        </a>
                                    </div>
                                    <div class="t3 text-[11px]">/{{ $partner->slug }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    @if($partner->category)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold border bg-indigo-50 text-indigo-700 border-indigo-200">
                                            <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $partner->category->color ?: '#6366f1' }}"></span>
                                            <span>{{ $partner->category->name }}</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold border bg-slate-100 text-slate-700 border-slate-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                            <span>Uncategorized</span>
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs">
                                    <form method="POST" action="{{ route('admin.brand-partners.toggle-featured', $partner) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="border-0 bg-transparent cursor-pointer p-1 rounded-full hover:bg-slate-100 transition" title="Toggle Featured">
                                            <i class="bi {{ $partner->is_featured ? 'bi-star-fill text-amber-500' : 'bi-star text-slate-400' }} admin-icon" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs">
                                    <form method="POST" action="{{ route('admin.brand-partners.toggle-sponsored', $partner) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="border-0 bg-transparent cursor-pointer p-1 rounded-full hover:bg-slate-100 transition" title="Toggle Sponsored">
                                            <i class="bi {{ $partner->is_sponsored ? 'bi-award-fill text-indigo-500' : 'bi-circle text-slate-400' }} admin-icon" aria-hidden="true"></i>
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
                                <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    @if($partner->is_active)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>Active</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            <span>Inactive</span>
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs font-medium t1">{{ number_format($partner->views_count) }}</td>
                                <td class="px-3 py-2.5 text-center text-xs font-medium t1">{{ number_format($partner->clicks_count) }}</td>
                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-1.5 items-center">
                                        <a href="{{ route('admin.brand-partners.show', $partner) }}" class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 hover:border-indigo-300 transition shadow-xs no-underline">
                                            <i class="bi bi-eye text-[11px]" aria-hidden="true"></i>
                                            <span>View</span>
                                        </a>
                                        @if(auth('admin')->user() && in_array(auth('admin')->user()->roles->pluck('key')->first(), ['global_admin', 'marketing_team', 'content_team']))
                                            <a href="{{ route('admin.brand-partners.edit', $partner) }}" class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition shadow-xs no-underline">
                                                <i class="bi bi-pencil text-[11px]" aria-hidden="true"></i>
                                                <span>Edit</span>
                                            </a>
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

