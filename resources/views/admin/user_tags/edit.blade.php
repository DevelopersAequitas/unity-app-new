@extends('admin.layouts.app')

@section('title', 'Edit User Tag - ' . $tag->name)

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 md:p-5 relative admin-grid-card space-y-4 max-w-2xl mx-auto w-full">
    {{-- Header --}}
    <div class="flex items-center justify-between pb-2 border-b bs">
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 border border-indigo-100">
                <i class="bi bi-pencil-square text-base"></i>
            </span>
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider m-0">Edit User Tag</h2>
                <p class="text-xs t3 m-0 mt-0.5">Modify tag settings and status.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.user-tags.show', $tag) }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold text-indigo-600 bg-indigo-50/50 hover:bg-indigo-50 transition no-underline inline-flex items-center gap-1.5 shadow-xs">
                <i class="bi bi-people"></i> Manage Users
            </a>
            <a href="{{ route('admin.user-tags.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1.5 bg-white shadow-xs">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>

    {{-- System Tag Notice --}}
    @if($tag->isSystemTag())
        <div class="p-3.5 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800 flex items-start gap-2 shadow-xs">
            <i class="bi bi-shield-exclamation text-amber-600 text-sm mt-0.5 flex-shrink-0"></i>
            <div>
                <strong class="font-semibold text-amber-950">System Tag Protection:</strong>
                This is a protected system tag. The unique slug <code>{{ $tag->slug }}</code> cannot be changed or deleted because it is required by leaderboard exclusion logic.
            </div>
        </div>
    @endif

    {{-- Validation Errors --}}
    @if($errors->any())
        <div class="p-3.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 shadow-xs">
            <div class="font-semibold mb-1 flex items-center gap-1.5">
                <i class="bi bi-exclamation-octagon-fill text-rose-600"></i> Please correct the following errors:
            </div>
            <ul class="mb-0 list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form --}}
    <form method="POST" action="{{ route('admin.user-tags.update', $tag) }}" class="space-y-4 m-0">
        @csrf
        @method('PUT')

        {{-- Tag Name --}}
        <div>
            <label for="name" class="block text-xs font-semibold t1 mb-1">
                Tag Name <span class="text-rose-500">*</span>
            </label>
            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name', $tag->name) }}"
                required
                maxlength="255"
                class="w-full px-3 py-2 rounded-lg border bs surface t1 text-xs outline-none focus-ring shadow-xs"
            >
            <p class="text-[11px] t3 mt-1">Display name for the tag shown throughout the admin panel.</p>
        </div>

        {{-- Slug --}}
        <div>
            <label for="slug" class="block text-xs font-semibold t1 mb-1">
                Tag Slug <span class="text-rose-500">*</span>
            </label>
            @if($tag->isSystemTag())
                <input
                    type="text"
                    id="slug"
                    value="{{ $tag->slug }}"
                    disabled
                    readonly
                    class="w-full px-3 py-2 rounded-lg border bs bg-gray-100 text-gray-500 font-mono text-xs cursor-not-allowed shadow-xs"
                >
                <p class="text-[11px] text-amber-700 mt-1 flex items-center gap-1">
                    <i class="bi bi-lock-fill"></i> System tag slug is locked to prevent breaking backend leaderboard logic.
                </p>
            @else
                <input
                    type="text"
                    id="slug"
                    name="slug"
                    value="{{ old('slug', $tag->slug) }}"
                    required
                    maxlength="255"
                    class="w-full px-3 py-2 rounded-lg border bs surface t1 text-xs font-mono outline-none focus-ring shadow-xs"
                >
                <p class="text-[11px] t3 mt-1">Unique identifier used by backend systems.</p>
            @endif
        </div>

        {{-- Description --}}
        <div>
            <label for="description" class="block text-xs font-semibold t1 mb-1">
                Description <span class="text-gray-400 font-normal">(Optional)</span>
            </label>
            <textarea
                id="description"
                name="description"
                rows="3"
                maxlength="1000"
                class="w-full px-3 py-2 rounded-lg border bs surface t1 text-xs outline-none focus-ring shadow-xs"
            >{{ old('description', $tag->description) }}</textarea>
        </div>

        {{-- Status Checkbox --}}
        <div class="pt-2">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    {{ old('is_active', $tag->is_active) ? 'checked' : '' }}
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                >
                <span class="text-xs font-semibold t1">Active</span>
            </label>
            @if($tag->slug === 'team_member')
                <p class="text-[11px] text-amber-700 mt-0.5 ml-6">
                    ⚠️ Deactivating this tag will cause team members to reappear on public leaderboards.
                </p>
            @else
                <p class="text-[11px] t3 mt-0.5 ml-6">When inactive, tag logic and filters are temporarily bypassed.</p>
            @endif
        </div>

        {{-- Submit Buttons --}}
        <div class="flex items-center justify-end gap-2 pt-4 border-t bs">
            <a href="{{ route('admin.user-tags.index') }}" class="px-4 py-2 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline bg-white">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring flex items-center gap-1.5 shadow-sm">
                <i class="bi bi-check-lg"></i> Update Tag
            </button>
        </div>
    </form>
</div>
@endsection
