@extends('admin.layouts.app')

@section('title', 'Circle Categories')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Circle Categories</h2>
            <p class="text-xs t3 m-0 mt-0.5">Manage community circle classification categories.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.categories.export', request()->query()) }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline flex items-center gap-1.5">
                <i class="bi bi-download" aria-hidden="true"></i> Export
            </a>

            <form action="{{ route('admin.categories.import') }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                @csrf
                <input type="file" name="file" required class="px-2.5 py-1 rounded-lg border bs surface t1 text-xs outline-none focus-ring w-48">
                <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring">Import</button>
            </form>

            <a href="{{ route('admin.categories.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1.5">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Add Category
            </a>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif

    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="border bs rounded-xl p-3.5 mb-4 surface-2">
        <form method="GET" action="{{ route('admin.categories.index') }}" class="flex items-center gap-2">
            <input type="text" name="q" value="{{ $search }}" class="px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring max-w-sm w-full" placeholder="Search category name">
            <button class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition">Search</button>
        </form>
    </div>

    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="overflow-x-auto relative">
            <table class="min-w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="width: 100px;">ID</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Category Name</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-right" style="width: 210px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/50">
                    @forelse ($categories as $category)
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-3 py-2.5 font-mono text-xs t3">{{ $category->id }}</td>
                            <td class="px-3 py-2.5 font-semibold t1 text-[12.5px]"><x-admin-grid-text :text="$category->name" /></td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.categories.view', $category) }}" class="px-2.5 py-1 rounded-lg border bs text-xs font-medium text-indigo-600 hover:text-indigo-700 surface-2 transition no-underline">View</a>
                                    <a href="{{ route('admin.categories.edit', $category) }}" class="px-2.5 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 transition no-underline">Edit</a>
                                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 text-xs font-semibold">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-8 text-xs t3">No categories found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
            {{ $categories->links() }}
        </div>
    </div>
</div>
@endsection

