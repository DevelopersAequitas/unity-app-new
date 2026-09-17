@extends('admin.layouts.app')

@section('title', 'Coin Guidelines')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-6">
    {{-- Top Header Section --}}
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="font-display font-semibold text-xs text-amber-500 uppercase tracking-wider m-0 flex items-center gap-1.5">
                <span>🪙</span> Coin Guidelines Management
            </h2>
            <p class="text-xs t3 m-0 mt-0.5">Manage activities, rewards, header details, and ordering for the Flutter app's Coin Guidelines screen.</p>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">
            <button type="button" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold text-indigo-600 hover:text-indigo-700 bg-white shadow-sm transition flex items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#editHeaderModal">
                <i class="bi bi-gear-fill"></i> Edit Header Information
            </button>
            <a href="{{ route('admin.coin-guidelines.create') }}" class="btn btn-sm text-xs font-semibold text-white no-underline flex items-center gap-1.5 shadow-sm" style="background-color: #d97706; border-color: #d97706;" onmouseover="this.style.backgroundColor='#b45309';this.style.borderColor='#b45309'" onmouseout="this.style.backgroundColor='#d97706';this.style.borderColor='#d97706'">
                <i class="bi bi-plus-lg"></i> Add New Guideline
            </a>
        </div>
    </div>

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="bi bi-check-circle-fill text-emerald-600"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="btn-close text-xs" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-rose-600"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" class="btn-close text-xs" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Stats Cards & Live Header Banner --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="p-3.5 rounded-xl border bs bg-white shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-lg font-bold">
                🪙
            </div>
            <div>
                <div class="text-[11px] t3 font-medium uppercase tracking-wider">Total Guidelines</div>
                <div class="text-lg font-bold text-gray-800">{{ $stats['total'] }}</div>
            </div>
        </div>
        <div class="p-3.5 rounded-xl border bs bg-white shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                ✅
            </div>
            <div>
                <div class="text-[11px] t3 font-medium uppercase tracking-wider">Active in Mobile App</div>
                <div class="text-lg font-bold text-emerald-600">{{ $stats['active'] }}</div>
            </div>
        </div>
        <div class="p-3.5 rounded-xl border bs bg-white shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg font-bold">
                ⭐
            </div>
            <div>
                <div class="text-[11px] t3 font-medium uppercase tracking-wider">Total Reward Value</div>
                <div class="text-lg font-bold text-indigo-600">{{ number_format($stats['total_coins']) }} Coins</div>
            </div>
        </div>
        <div class="p-3.5 rounded-xl border bs bg-amber-50/50 flex flex-col justify-center">
            <div class="text-[11px] font-semibold text-amber-800">Mobile API Preview</div>
            <div class="text-[11px] text-amber-700 truncate mt-0.5"><code>GET /api/v1/coin-guidelines</code></div>
        </div>
    </div>

    {{-- Header Preview Card --}}
    <div class="p-4 rounded-xl border bs bg-gradient-to-r from-amber-50/70 via-white to-amber-50/30 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-start gap-3">
            <div class="w-12 h-12 rounded-xl bg-amber-100 border border-amber-200 flex items-center justify-center text-2xl flex-shrink-0">
                @if(!empty($icon))
                    <img src="{{ $icon }}" alt="Icon" class="w-8 h-8 object-contain">
                @else
                    🪙
                @endif
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-amber-200/60 text-amber-900">Current Header Config</span>
                    <h3 class="text-sm font-bold text-gray-900 m-0">{{ $title }}</h3>
                </div>
                <p class="text-xs text-gray-600 m-0 mt-1 max-w-3xl leading-relaxed">{{ $description }}</p>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-warning text-xs font-semibold px-3 py-1 rounded-lg flex-shrink-0" data-bs-toggle="modal" data-bs-target="#editHeaderModal">
            <i class="bi bi-pencil-square me-1"></i> Edit Header
        </button>
    </div>

    {{-- Filter Bar --}}
    <div class="p-3 rounded-xl border bs bg-white shadow-sm">
        <form method="GET" action="{{ route('admin.coin-guidelines.index') }}" class="flex flex-wrap items-center gap-3">
            <div class="flex-grow min-w-[220px]">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control text-xs" placeholder="Search by activity name..." value="{{ $filters['search'] ?? '' }}">
                </div>
            </div>
            <div class="w-40">
                <select name="status" class="form-select form-select-sm text-xs">
                    <option value="">All Statuses</option>
                    <option value="1" @selected(($filters['status'] ?? '') === '1')>Active Only</option>
                    <option value="0" @selected(($filters['status'] ?? '') === '0')>Inactive Only</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-sm btn-primary text-xs font-semibold px-3">Filter</button>
                <a href="{{ route('admin.coin-guidelines.index') }}" class="btn btn-sm btn-outline-secondary text-xs px-3">Reset</a>
            </div>
        </form>
    </div>

    {{-- Guidelines Table --}}
    <div class="rounded-xl border bs bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto w-full">
            <table class="w-full border-collapse text-xs">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider text-gray-500 font-semibold bg-gray-50 border-b bs">
                        <th class="py-3 px-4 text-center" style="width: 70px;">Order</th>
                        <th class="py-3 px-4 text-left">Activity</th>
                        <th class="py-3 px-4 text-center" style="width: 140px;">Earn Coins</th>
                        <th class="py-3 px-4 text-center" style="width: 120px;">Status</th>
                        <th class="py-3 px-4 text-right" style="width: 160px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="coinGuidelinesTableBody" class="divide-y divide-gray-100">
                    @forelse ($guidelines as $guideline)
                        <tr class="hover:bg-amber-50/30 transition-colors" data-id="{{ $guideline->id }}">
                            <td class="py-3 px-4 text-center font-mono font-semibold text-gray-700">
                                <span class="badge bg-light text-dark border px-2 py-1 rounded">
                                    #{{ $guideline->display_order }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-gray-900 text-sm">{{ $guideline->activity }}</div>
                                <div class="text-[11px] text-gray-400 font-mono">ID: {{ $guideline->id }}</div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                    🪙 {{ number_format($guideline->coins) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <form method="POST" action="{{ route('admin.coin-guidelines.toggle-status', $guideline->id) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    @if ($guideline->is_active)
                                        <button type="submit" class="badge bg-emerald-100 text-emerald-700 border border-emerald-300 px-2.5 py-1 rounded-full hover:bg-emerald-200 transition" title="Click to Deactivate">
                                            🟢 Active
                                        </button>
                                    @else
                                        <button type="submit" class="badge bg-gray-100 text-gray-600 border border-gray-300 px-2.5 py-1 rounded-full hover:bg-gray-200 transition" title="Click to Activate">
                                            ⚪ Inactive
                                        </button>
                                    @endif
                                </form>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.coin-guidelines.edit', $guideline->id) }}" class="btn btn-sm btn-light border text-primary p-1.5 rounded hover:bg-primary hover:text-white transition" title="Edit Guideline">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.coin-guidelines.destroy', $guideline->id) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this coin guideline?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light border text-danger p-1.5 rounded hover:bg-danger hover:text-white transition" title="Delete Guideline">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-gray-400">
                                <i class="bi bi-inbox text-3xl d-block mb-2 text-gray-300"></i>
                                No coin guidelines found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($guidelines->hasPages())
            <div class="p-3 border-t bs bg-gray-50 flex justify-between items-center">
                {{ $guidelines->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Header Edit Modal --}}
<div class="modal fade" id="editHeaderModal" tabindex="-1" aria-labelledby="editHeaderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-xl">
            <form method="POST" action="{{ route('admin.coin-guidelines.update-header') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-light border-b px-4 py-3">
                    <h5 class="modal-title text-sm font-bold text-gray-800" id="editHeaderModalLabel">
                        🪙 Edit Coin Guidelines Header
                    </h5>
                    <button type="button" class="btn-close text-xs" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 space-y-3">
                    <div>
                        <label class="form-label text-xs font-semibold text-gray-700">Screen Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control text-xs" value="{{ old('title', $title) }}" required>
                        <span class="text-[11px] text-muted">Example: The Coin Reward System</span>
                    </div>

                    <div>
                        <label class="form-label text-xs font-semibold text-gray-700">Header Description <span class="text-danger">*</span></label>
                        <textarea name="description" rows="3" class="form-control text-xs" required>{{ old('description', $description) }}</textarea>
                    </div>

                    <div>
                        <label class="form-label text-xs font-semibold text-gray-700">Header Icon URL / Image</label>
                        <input type="text" name="icon_url" class="form-control text-xs mb-2" placeholder="https://example.com/icon.png" value="{{ old('icon_url', $icon) }}">
                        <input type="file" name="icon_file" class="form-control text-xs" accept="image/*">
                        <span class="text-[11px] text-muted">Upload an image or enter a direct image URL</span>
                    </div>
                </div>
                <div class="modal-footer bg-light border-t px-4 py-2 flex justify-end gap-2">
                    <button type="button" class="btn btn-sm btn-secondary text-xs" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary text-xs font-semibold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
