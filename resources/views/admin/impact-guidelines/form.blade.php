@extends('admin.layouts.app')

@section('title', $mode === 'create' ? 'Add Impact Guideline' : 'Edit Impact Guideline')

@section('content')
<div class="container-fluid max-w-4xl py-4">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h4 class="text-lg font-bold text-gray-900 m-0 flex items-center gap-2">
                <span>❤️</span> {{ $mode === 'create' ? 'Add New Impact Guideline' : 'Edit Impact Guideline' }}
            </h4>
            <p class="text-xs text-gray-500 m-0 mt-0.5">Configure action description, category, impact value, unit, and display order.</p>
        </div>
        <a href="{{ route('admin.impact-guidelines.index') }}" class="btn btn-sm btn-outline-secondary text-xs">
            <i class="bi bi-arrow-left me-1"></i> Back to Listing
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mb-4 p-3 text-xs rounded-xl">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-xl overflow-hidden">
        <form method="POST" action="{{ $mode === 'create' ? route('admin.impact-guidelines.store') : route('admin.impact-guidelines.update', $guideline->id) }}" enctype="multipart/form-data">
            @csrf
            @if ($mode === 'edit')
                @method('PUT')
            @endif

            <div class="card-body p-4 space-y-4">
                {{-- Action Description --}}
                <div>
                    <label class="form-label text-xs font-semibold text-gray-700">
                        Action Description <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="action" class="form-control text-xs" placeholder="e.g. Closed a business deal through Peers Global" value="{{ old('action', $guideline->action) }}" required>
                    <div class="form-text text-[11px] text-gray-400">The specific impact-generating action taken by a member.</div>
                </div>

                {{-- Category --}}
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-xs font-semibold text-gray-700">
                            Category <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="category" list="categoryOptions" class="form-control text-xs" placeholder="e.g. Business & Growth, Trust & Visibility" value="{{ old('category', $guideline->category) }}" required>
                        <datalist id="categoryOptions">
                            @foreach ($categories as $cat)
                                <option value="{{ $cat }}"></option>
                            @endforeach
                        </datalist>
                        <div class="form-text text-[11px] text-gray-400">Select or type a category (e.g. Business & Growth, Trust & Visibility).</div>
                    </div>

                    {{-- Impact Value & Unit --}}
                    <div class="col-md-3">
                        <label class="form-label text-xs font-semibold text-gray-700">
                            Impact Value <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-rose-50 text-rose-700 font-bold">+</span>
                            <input type="number" name="impact_value" class="form-control text-xs font-mono font-bold" min="0" step="1" placeholder="5" value="{{ old('impact_value', $guideline->impact_value) }}" required>
                        </div>
                        <div class="form-text text-[11px] text-gray-400">Numeric value (e.g. 5 or 1).</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-xs font-semibold text-gray-700">
                            Impact Unit <span class="text-danger">*</span>
                        </label>
                        <select name="impact_unit" class="form-select form-select-sm text-xs" required>
                            <option value="Lives" @selected(old('impact_unit', $guideline->impact_unit) === 'Lives')>Lives</option>
                            <option value="Life" @selected(old('impact_unit', $guideline->impact_unit) === 'Life')>Life</option>
                        </select>
                        <div class="form-text text-[11px] text-gray-400">Unit name (e.g. Lives / Life).</div>
                    </div>
                </div>

                <hr class="my-3 text-gray-200">

                {{-- Display Order & Status --}}
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-xs font-semibold text-gray-700">
                            Display Order <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="display_order" class="form-control text-xs font-mono" min="1" step="1" value="{{ old('display_order', $guideline->display_order) }}" required>
                        <div class="form-text text-[11px] text-gray-400">Controls order in mobile app (1 appears first, 2 second, etc.).</div>
                    </div>

                    <div class="col-md-6 flex items-center pt-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActiveSwitch" @checked(old('is_active', $guideline->is_active ?? true))>
                            <label class="form-check-label text-xs font-semibold text-gray-700" for="isActiveSwitch">
                                Active Status (Visible in Mobile App)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-gray-50 p-3 border-t flex justify-end gap-2">
                <a href="{{ route('admin.impact-guidelines.index') }}" class="btn btn-sm btn-secondary text-xs">
                    Cancel
                </a>
                <button type="submit" class="btn btn-sm btn-primary text-xs font-semibold px-4">
                    <i class="bi bi-check-lg me-1"></i> {{ $mode === 'create' ? 'Create Guideline' : 'Update Guideline' }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
