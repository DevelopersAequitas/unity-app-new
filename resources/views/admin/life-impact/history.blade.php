@extends('admin.layouts.app')

@section('title', 'Life Impact History')

@include('admin.partials.grid-head')

@section('content')
    @php
        $memberName = $member->display_name ?? trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
        $heading = $memberName ? $memberName . ' Life Impact History' : 'Life Impact History';
        $resetUrl = $activeCategory
            ? route('admin.life-impact.history.category', [$member, $activeCategory])
            : route('admin.life-impact.history', $member);
        $formatDate = static function ($value): string {
            if (! $value) {
                return '-';
            }

            try {
                return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i');
            } catch (\Throwable $throwable) {
                return (string) $value;
            }
        };
        $clean = static function ($value): string {
            $value = trim((string) ($value ?? ''));
            return $value !== '' ? $value : '-';
        };
        $categoryLabel = static function ($item) use ($categories, $clean): string {
            $actionKey = trim((string) ($item->action_key ?? ''));
            $impactCategory = trim((string) ($item->impact_category ?? ''));
            $activityType = trim((string) ($item->activity_type ?? ''));
            $actionLabel = trim((string) ($item->action_label ?? ''));

            if ($actionKey === 'admin_adjustment' || $impactCategory === 'admin_adjustment' || $activityType === 'admin_adjustment') {
                return 'Admin Adjustment';
            }

            foreach ($categories as $category) {
                $aliases = $category['aliases'] ?? [];
                foreach ([$actionKey, $impactCategory, $activityType] as $token) {
                    $normalized = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($token)), '_');
                    if ($normalized !== '' && in_array($normalized, $aliases, true)) {
                        return $category['label'];
                    }
                }
            }

            return $actionLabel !== '' ? $actionLabel : $clean($impactCategory ?: $activityType ?: $actionKey);
        };
    @endphp

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">
        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <div class="flex items-center gap-2 flex-wrap">
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">{{ $heading }}</h2>
                <span class="text-xs t3">• {{ $member->adminDisplayInlineLabel() }}</span>
                @if ($activeCategoryLabel)
                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">Category: {{ $activeCategoryLabel }}</span>
                @else
                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">All History</span>
                @endif
            </div>
            <a href="{{ route('admin.life-impact.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline">Back to Life Impact</a>
        </div>

        <div class="border bs rounded-xl p-3.5 mb-4 surface-2">
            <form method="GET" class="flex flex-wrap gap-3 items-end" id="lifeImpactHistoryFilterForm">
                <div>
                    <label class="block text-[11px] t3 mb-1 font-medium">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                </div>
                <div>
                    <label class="block text-[11px] t3 mb-1 font-medium">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                </div>
                <div class="flex-1" style="min-width: 240px;">
                    <label class="block text-[11px] t3 mb-1 font-medium">Search</label>
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs placeholder:t3 outline-none focus-ring" placeholder="Title / description / remarks">
                </div>
                <div class="flex justify-end">
                    <a href="{{ $resetUrl }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline">Clear</a>
                </div>
            </form>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Date</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Impact Value</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Total After</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Category / Action</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Title</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Description</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Remarks</th>
                        </tr>
                        <tr class="surface-2 border-b bs align-middle">
                            <th class="px-3 py-2">
                                <input
                                    type="date"
                                    name="date"
                                    form="lifeImpactHistoryFilterForm"
                                    value="{{ $filters['date'] ?? '' }}"
                                    class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal"
                                    placeholder="Date"
                                >
                            </th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" placeholder="-" disabled></th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" placeholder="-" disabled></th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" placeholder="-" disabled></th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" placeholder="-" disabled></th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" placeholder="-" disabled></th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" placeholder="-" disabled></th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($items as $item)
                            @php
                                $isAdminAdjustment = ($item->action_key ?? null) === 'admin_adjustment'
                                    || ($item->impact_category ?? null) === 'admin_adjustment'
                                    || ($item->activity_type ?? null) === 'admin_adjustment';
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $formatDate($item->created_at ?? null) }}</td>
                                <td class="px-3 py-2.5 font-semibold text-indigo-600 text-[12.5px]">{{ number_format((int) ($item->impact_value ?? 0)) }}</td>
                                <td class="px-3 py-2.5 text-[12.5px] t1 font-medium">{{ isset($item->life_impacted) ? number_format((int) $item->life_impacted) : '-' }}</td>
                                <td class="px-3 py-2.5">
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold {{ $isAdminAdjustment ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-indigo-50 text-indigo-700 border-indigo-200' }}">
                                        {{ $categoryLabel($item) }}
                                    </span>
                                    @if (! empty($item->action_key) && $item->action_key !== 'admin_adjustment')
                                        <div class="t3 text-[10px] mt-0.5">{{ $item->action_key }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t1 max-w-[220px] truncate" title="{{ $clean($item->title ?? '') }}">{{ $clean($item->title ?? '') }}</td>
                                <td class="px-3 py-2.5 text-xs t2 max-w-[280px] truncate" title="{{ $clean($item->description ?? '') }}">{{ $clean($item->description ?? '') }}</td>
                                <td class="px-3 py-2.5 text-xs t3 max-w-[240px] truncate" title="{{ $clean($item->remarks ?? '') }}">{{ $clean($item->remarks ?? '') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-8 text-xs t3">No life impact history entries found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $items->links() }}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('lifeImpactHistoryFilterForm');

            if (!form) {
                return;
            }

            const inputs = form.querySelectorAll('input, select');

            inputs.forEach(function (input) {
                input.addEventListener('keypress', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        form.submit();
                    }
                });
            });
        });
    </script>
@endpush

