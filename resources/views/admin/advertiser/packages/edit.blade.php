@extends('admin.layouts.app')

@section('title', 'Edit Package: ' . $package->name)

@include('admin.partials.grid-head')

@section('content')
    <div class="max-w-4xl mx-auto rounded-xl border bs p-5 bg-white shadow-sm space-y-5">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
                <h1 class="text-xl font-bold t1 flex items-center gap-2">
                    <i class="bi bi-pencil-square text-indigo-600"></i>
                    Edit Package: {{ $package->name }}
                </h1>
                <p class="text-xs t3 mt-0.5">Note: Updating pricing will not affect already submitted or paid applications.</p>
            </div>
            <a href="{{ route('admin.advertiser.packages.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition no-underline">
                &larr; Back to Packages
            </a>
        </div>

        @if($errors->any())
            <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.advertiser.packages.update', $package) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold t2 mb-1">Package Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $package->name) }}" required class="w-full px-3 py-2 text-xs rounded-lg border bs focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold t2 mb-1">Duration <span class="text-rose-500">*</span></label>
                    <select name="duration_months" required class="w-full px-3 py-2 text-xs rounded-lg border bs focus:ring-1 focus:ring-indigo-500 outline-none bg-white">
                        <option value="1" {{ old('duration_months', $package->duration_months) == 1 ? 'selected' : '' }}>1 Month</option>
                        <option value="6" {{ old('duration_months', $package->duration_months) == 6 ? 'selected' : '' }}>6 Months</option>
                        <option value="12" {{ old('duration_months', $package->duration_months) == 12 ? 'selected' : '' }}>12 Months (Annual)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold t2 mb-1">Price (INR) <span class="text-rose-500">*</span></label>
                    <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $package->price) }}" required class="w-full px-3 py-2 text-xs rounded-lg border bs focus:ring-1 focus:ring-indigo-500 outline-none font-mono">
                </div>

                <div>
                    <label class="block text-xs font-semibold t2 mb-1">Currency</label>
                    <input type="text" name="currency" value="{{ old('currency', $package->currency) }}" readonly class="w-full px-3 py-2 text-xs rounded-lg border bs bg-slate-50 text-slate-600 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-semibold t2 mb-1">Banner Placement Location</label>
                    <input type="text" name="banner_placement" value="{{ old('banner_placement', $package->banner_placement) }}" class="w-full px-3 py-2 text-xs rounded-lg border bs focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold t2 mb-1">Max Creatives / Banner Assets</label>
                    <input type="number" min="1" max="20" name="max_banner_assets" value="{{ old('max_banner_assets', $package->max_banner_assets) }}" class="w-full px-3 py-2 text-xs rounded-lg border bs focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold t2 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $package->sort_order) }}" class="w-full px-3 py-2 text-xs rounded-lg border bs focus:ring-1 focus:ring-indigo-500 outline-none">
                </div>

                <div class="flex items-center gap-2 pt-5">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $package->is_active) ? 'checked' : '' }} class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="is_active" class="text-xs font-semibold t1 cursor-pointer">Active & Available for Public Selection</label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold t2 mb-1">Package Description</label>
                <textarea name="description" rows="3" class="w-full px-3 py-2 text-xs rounded-lg border bs focus:ring-1 focus:ring-indigo-500 outline-none">{{ old('description', $package->description) }}</textarea>
            </div>

            @php
                $featuresText = is_array($package->features) ? implode("\n", $package->features) : '';
            @endphp
            <div>
                <label class="block text-xs font-semibold t2 mb-1">Features (One feature per line)</label>
                <textarea name="features_text" rows="4" class="w-full px-3 py-2 text-xs rounded-lg border bs focus:ring-1 focus:ring-indigo-500 outline-none font-mono">{{ old('features_text', $featuresText) }}</textarea>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <a href="{{ route('admin.advertiser.packages.index') }}" class="px-4 py-2 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition no-underline">Cancel</a>
                <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition shadow-sm border-0 cursor-pointer">Update Package</button>
            </div>
        </form>
    </div>
@endsection
