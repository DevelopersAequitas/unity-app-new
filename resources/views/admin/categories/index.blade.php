@extends('admin.layouts.app')

@section('title', 'Circle Categories')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 md:p-5 relative admin-grid-card space-y-4 w-full">
    {{-- Header & Action Buttons --}}
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pb-1">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider m-0">Circle Categories</h2>
            <p class="text-xs t3 m-0 mt-0.5">Manage community circle classification categories.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.categories.export', request()->query()) }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline flex items-center gap-1.5 shadow-sm">
                <i class="bi bi-download" aria-hidden="true"></i> Export
            </a>

            <form action="{{ route('admin.categories.import') }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-1.5 m-0">
                @csrf
                <input type="file" name="file" required class="px-2.5 py-1 rounded-lg border bs surface t1 text-xs outline-none focus-ring w-48 file:mr-2 file:py-0.5 file:px-2 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring flex items-center gap-1 shadow-sm">
                    <i class="bi bi-upload"></i> Import
                </button>
            </form>

            <a href="{{ route('admin.categories.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1.5 shadow-sm">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Add Category
            </a>
        </div>
    </div>

    {{-- Notification Alerts --}}
    @if(session('error'))
        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center gap-2">
            <i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}
        </div>
    @endif

    @if(session('success'))
        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700 flex items-center gap-2">
            <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
            <ul class="mb-0 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Search & Filter Toolbar --}}
    <div class="border bs rounded-xl p-3.5 surface-2">
        <form method="GET" action="{{ route('admin.categories.index') }}" class="flex items-center gap-2">
            <input type="text" name="q" value="{{ $search }}" class="px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring max-w-sm w-full" placeholder="Search category name">
            <button type="submit" class="px-3.5 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition">Search</button>
            @if($search !== '')
                <a href="{{ route('admin.categories.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline">Clear</a>
            @endif
        </form>
    </div>

    {{-- Full Width Categories Table --}}
    <div class="rounded-xl border bs surface overflow-hidden w-full shadow-sm">
        <div class="overflow-x-auto relative w-full">
            <table class="w-full min-w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-4 py-3 text-left font-semibold" style="width: 100px;">ID</th>
                        <th class="th-cell surface-2 border-b bs px-4 py-3 text-left font-semibold">Category Name</th>
                        <th class="th-cell surface-2 border-b bs px-4 py-3 text-right font-semibold" style="width: 240px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/50">
                    @forelse ($categories as $category)
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-4 py-3 font-mono text-xs t3">
                                <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 font-mono text-[11px] font-medium border bs">#{{ $category->id }}</span>
                            </td>
                            <td class="px-4 py-3 font-medium t1 text-[13px]">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0 text-xs border border-indigo-100">
                                        <i class="bi bi-tag"></i>
                                    </div>
                                    <span class="font-semibold t1"><x-admin-grid-text :text="$category->name" /></span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.categories.view', $category) }}" class="px-2.5 py-1 rounded-lg border bs text-xs font-semibold text-indigo-600 hover:text-indigo-700 bg-indigo-50/50 hover:bg-indigo-50 transition no-underline inline-flex items-center gap-1">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <a href="{{ route('admin.categories.edit', $category) }}" class="px-2.5 py-1 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')" class="inline m-0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-semibold transition inline-flex items-center gap-1">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-12 text-xs t3">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <i class="bi bi-folder2-open text-3xl text-gray-300"></i>
                                    <p class="m-0 font-medium">No categories found.</p>
                                    @if($search !== '')
                                        <a href="{{ route('admin.categories.index') }}" class="text-indigo-600 text-xs hover:underline mt-1">Clear search filter</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="grid-pagination" class="p-3.5 border-t bs flex flex-col sm:flex-row justify-between items-center gap-3 surface-2">
            <div class="text-xs t3">
                @if($categories->total() > 0)
                    Showing <span class="font-semibold t1">{{ $categories->firstItem() }}</span> to <span class="font-semibold t1">{{ $categories->lastItem() }}</span> of <span class="font-semibold t1">{{ $categories->total() }}</span> categories
                @endif
            </div>
            <div>
                {{ $categories->links() }}
            </div>
        </div>
    </div>
</div>
@endsection


