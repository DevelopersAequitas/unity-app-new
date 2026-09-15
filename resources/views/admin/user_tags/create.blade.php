@extends('admin.layouts.app')

@section('title', 'Add New User Tag')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 md:p-5 relative admin-grid-card space-y-4 max-w-2xl mx-auto w-full">
    {{-- Header --}}
    <div class="flex items-center justify-between pb-2 border-b bs">
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 border border-indigo-100">
                <i class="bi bi-tag-fill text-base"></i>
            </span>
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider m-0">Add New User Tag</h2>
                <p class="text-xs t3 m-0 mt-0.5">Create a new user tag for classification and assignment.</p>
            </div>
        </div>
        <a href="{{ route('admin.user-tags.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1.5 bg-white shadow-xs">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

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
    <form method="POST" action="{{ route('admin.user-tags.store') }}" class="space-y-4 m-0">
        @csrf

        {{-- Tag Name --}}
        <div>
            <label for="name" class="block text-xs font-semibold t1 mb-1">
                Tag Name <span class="text-rose-500">*</span>
            </label>
            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name') }}"
                required
                maxlength="255"
                placeholder="e.g. Developer, Tester, Demo Account, Partner"
                class="w-full px-3 py-2 rounded-lg border bs surface t1 text-xs outline-none focus-ring shadow-xs"
                oninput="autoGenerateSlug(this.value)"
            >
            <p class="text-[11px] t3 mt-1">Display name for the tag shown throughout the admin panel.</p>
        </div>

        {{-- Slug --}}
        <div>
            <label for="slug" class="block text-xs font-semibold t1 mb-1">
                Tag Slug <span class="text-rose-500">*</span>
            </label>
            <input
                type="text"
                id="slug"
                name="slug"
                value="{{ old('slug') }}"
                maxlength="255"
                placeholder="e.g. developer, tester, demo_account"
                class="w-full px-3 py-2 rounded-lg border bs surface t1 text-xs font-mono outline-none focus-ring shadow-xs"
            >
            <p class="text-[11px] t3 mt-1">Unique identifier used by backend systems (lowercase letters, numbers, and underscores).</p>
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
                placeholder="Explain the purpose of this tag..."
                class="w-full px-3 py-2 rounded-lg border bs surface t1 text-xs outline-none focus-ring shadow-xs"
            >{{ old('description') }}</textarea>
        </div>

        {{-- Status Checkbox --}}
        <div class="pt-2">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    {{ old('is_active', true) ? 'checked' : '' }}
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                >
                <span class="text-xs font-semibold t1">Active</span>
            </label>
            <p class="text-[11px] t3 mt-0.5 ml-6">When inactive, tag logic and filters are temporarily bypassed.</p>
        </div>

        {{-- Submit Buttons --}}
        <div class="flex items-center justify-end gap-2 pt-4 border-t bs">
            <a href="{{ route('admin.user-tags.index') }}" class="px-4 py-2 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline bg-white">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring flex items-center gap-1.5 shadow-sm">
                <i class="bi bi-check-lg"></i> Create Tag
            </button>
        </div>
    </form>
</div>

<script>
let manualSlugEdited = false;
document.getElementById('slug')?.addEventListener('input', function() {
    manualSlugEdited = this.value.trim() !== '';
});

function autoGenerateSlug(text) {
    if (manualSlugEdited) return;
    const slug = text.toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
    document.getElementById('slug').value = slug;
}
</script>
@endsection
