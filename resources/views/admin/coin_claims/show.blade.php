@extends('admin.layouts.app')

@section('title', 'Coin Claim Details')

@include('admin.partials.grid-head')

@section('content')
    @php
        $user = $claim->user;
        $peerName = $user ? ($user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))) : '—';
        $fields = (array) data_get($claim->payload, 'fields', []);
        $files = (array) data_get($claim->payload, 'files', []);
    @endphp

    <div id="grid-root-container" class="light rounded-xl border bs p-6 relative admin-grid-card space-y-6">
        <!-- Header & Top Actions -->
        <div class="flex flex-wrap justify-between items-center gap-4 border-b bs pb-4">
            <div class="flex items-center gap-3 flex-wrap">
                <h2 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider m-0">
                    Coin Claim Details
                </h2>
                @if(strtolower((string)$claim->status) === 'approved')
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Approved
                    </span>
                @elseif(strtolower((string)$claim->status) === 'rejected')
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md bg-rose-50 text-rose-700 border border-rose-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Rejected
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md bg-amber-50 text-amber-700 border border-amber-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>{{ ucfirst($claim->status ?? 'Pending') }}
                    </span>
                @endif
            </div>

            <a href="{{ route('admin.coin-claims.index') }}" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition no-underline flex items-center gap-1.5 shadow-sm">
                <span>←</span> Back
            </a>
        </div>

        <!-- 4-Column Main Details Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-4 rounded-xl border bs surface-2 shadow-sm">
                <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Peer</span>
                <span class="text-sm font-bold t1">{{ $peerName }}</span>
            </div>

            <div class="p-4 rounded-xl border bs surface-2 shadow-sm">
                <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Phone</span>
                <span class="text-sm font-semibold font-mono t1">{{ $user->phone ?? '—' }}</span>
            </div>

            <div class="p-4 rounded-xl border bs surface-2 shadow-sm">
                <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Activity</span>
                <span class="text-sm font-semibold text-indigo-600">{{ data_get($activity, 'label', $claim->activity_code) }}</span>
            </div>

            <div class="p-4 rounded-xl border bs surface-2 shadow-sm">
                <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</span>
                <span class="text-sm font-semibold t1">{{ ucfirst($claim->status ?? 'Pending') }}</span>
            </div>
        </div>

        <!-- Submission Fields -->
        <div class="p-5 rounded-xl border bs surface space-y-3 shadow-sm">
            <h3 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider m-0">Fields</h3>
            @if(count($fields) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    @foreach ($fields as $key => $value)
                        <div class="flex items-center justify-between p-3 rounded-lg border bs surface-2 text-xs">
                            <span class="font-bold t1">{{ $key }}</span>
                            <span class="font-mono t2">{{ is_scalar($value) ? $value : json_encode($value) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-xs t3 py-2">—</div>
            @endif
        </div>

        <!-- Attached Files -->
        <div class="p-5 rounded-xl border bs surface space-y-3 shadow-sm">
            <h3 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider m-0">Files</h3>
            @if(count($files) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    @foreach ($files as $key => $fileId)
                        <div class="flex items-center justify-between p-3 rounded-lg border bs surface-2 text-xs">
                            <span class="font-bold t1">{{ $key }}</span>
                            <a href="{{ url('/api/v1/files/' . $fileId) }}" target="_blank" class="px-3 py-1 text-xs font-semibold rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition no-underline inline-flex items-center gap-1">
                                View File
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-xs t3 py-2">—</div>
            @endif
        </div>

        <!-- Action Form (if pending) -->
        @if ($claim->status === 'pending')
            <div class="p-5 rounded-xl border bs surface space-y-4 shadow-sm">
                <h3 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider m-0">Actions</h3>
                <div class="flex flex-wrap items-center justify-between gap-4 pt-1">
                    <form method="POST" action="{{ route('admin.coin-claims.approve', $claim->id) }}" class="inline">
                        @csrf
                        <button type="submit" class="px-5 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-sm cursor-pointer" onclick="return confirm('Are you sure you want to approve this coin claim?')">
                            Approve
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.coin-claims.reject', $claim->id) }}" class="flex items-center gap-2 flex-wrap">
                        @csrf
                        <input type="text" name="admin_notes" class="px-3 py-2 text-xs rounded-lg border bs surface t1 outline-none focus-ring w-64" placeholder="Reason (optional)">
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg border border-rose-300 bg-white text-rose-600 hover:bg-rose-50 transition shadow-sm cursor-pointer" onclick="return confirm('Are you sure you want to reject this coin claim?')">
                            Reject
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection


