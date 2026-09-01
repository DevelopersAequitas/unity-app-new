@extends('admin.layouts.app')

@section('title', 'Membership Plans')

@include('admin.partials.grid-head')

@section('content')
    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">{{ session('success') }}</div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Membership Plans</h2>
                <p class="text-xs t3 m-0 mt-0.5">Manage membership plan pricing, tax calculation, and access tiers.</p>
            </div>
            <div class="flex gap-2 items-center">
                @if (! $canEdit)
                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-600 border-gray-200">View only</span>
                @endif
                @if ($canEdit)
                    <a href="{{ route('admin.unity-peers-plans.create') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1">
                        <i class="bi bi-plus-lg admin-icon me-1" aria-hidden="true"></i>Create Plan
                    </a>
                @endif
            </div>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Slug</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Base Price</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">GST %</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">GST Amount</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Total Amount</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Duration (Days)</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Duration (Months)</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Is Free</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Is Active</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">Sort Order</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created At</th>
                            @if ($canEdit)
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($plans as $plan)
                            @php
                                $price = (float) $plan->price;
                                $gstPercent = (float) $plan->gst_percent;
                                $gstAmount = round($price * ($gstPercent / 100), 2);
                                $totalAmount = round($price + $gstAmount, 2);
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 font-semibold t1 text-[12.5px] whitespace-nowrap">
                                    <a href="{{ route('admin.unity-peers-plans.edit', $plan) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                        {{ $plan->name }}
                                    </a>
                                </td>
                                <td class="px-3 py-2.5 text-xs font-mono text-indigo-600">{{ $plan->slug }}</td>
                                <td class="px-3 py-2.5 text-xs t1 font-medium">₹{{ number_format($price, 2) }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ number_format($gstPercent, 2) }}%</td>
                                <td class="px-3 py-2.5 text-xs t2">₹{{ number_format($gstAmount, 2) }}</td>
                                <td class="px-3 py-2.5 text-xs font-semibold t1">₹{{ number_format($totalAmount, 2) }}</td>
                                <td class="px-3 py-2.5 text-center text-xs t2">{{ $plan->duration_days ?? 0 }}</td>
                                <td class="px-3 py-2.5 text-center text-xs t2">{{ $plan->duration_months ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-center text-xs t2">{{ $plan->is_free ? 'Yes' : 'No' }}</td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if ($plan->is_active)
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Active</span>
                                    @else
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center text-xs t2">{{ $plan->sort_order ?? 0 }}</td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $plan->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                @if ($canEdit)
                                    <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                        <a class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline" href="{{ route('admin.unity-peers-plans.edit', $plan) }}">Edit</a>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canEdit ? 13 : 12 }}" class="text-center py-8 text-xs t3">No plans found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

