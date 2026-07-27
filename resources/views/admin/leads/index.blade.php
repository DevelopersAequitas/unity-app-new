@extends('admin.layouts.app')

@section('title', $resource['title'])

@include('admin.partials.grid-head')

@section('content')
    @php
        $formatValue = function ($value, string $column, bool $isLongText = false): string {
            if ($value === null || $value === '') {
                return '—';
            }

            if (in_array($column, ['created_at', 'updated_at'], true)) {
                return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i');
            }

            if (is_array($value) || is_object($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
            }

            $stringValue = (string) $value;

            if ($isLongText) {
                return \Illuminate\Support\Str::limit($stringValue, 70);
            }

            return $stringValue;
        };

        $statusBadgeClass = static function (string $status): string {
            return match (strtolower(trim($status))) {
                'approved', 'active', 'completed' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200',
                'rejected', 'failed', 'inactive' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200',
                'pending', 'in_review' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200',
                'new' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200',
                default => 'chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200',
            };
        };

        $scrollableColumns = [
            'full_name',
            'first_name',
            'last_name',
            'contact_no',
            'contact_number',
            'phone',
            'mobile_number',
            'city',
            'email',
            'email_id',
            'business_name',
            'brand_or_company_name',
            'company_name',
        ];

        $columnFilterMap = [
            'full_name' => 'name',
            'first_name' => 'name',
            'contact_no' => 'phone',
            'contact_number' => 'phone',
            'phone' => 'phone',
            'city' => 'city',
            'email' => 'email',
            'email_id' => 'email',
            'business_name' => 'company',
            'brand_or_company_name' => 'company',
            'company_name' => 'company',
        ];

        $columnFilterLabels = [
            'name' => 'Name',
            'phone' => 'Mobile / Phone',
            'city' => 'City',
            'email' => 'Email',
            'company' => 'Company Name',
        ];

        $renderedFilterKeys = [];
    @endphp

    <form id="leadFiltersForm" method="GET" action="{{ route($resource['index_route']) }}"></form>

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">{{ $resource['menu_label'] }}</h2>
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Total: {{ number_format($items->total()) }}</span>
        </div>

        <!-- Filter Card -->
        <div class="p-3 rounded-lg border bs surface-2">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                    <input type="text" name="search" form="leadFiltersForm" value="{{ $filters['search'] }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Search by keyword">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                    <select name="status" form="leadFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="all" @selected($filters['status'] === 'all')>All</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst((string) $status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">From Date</label>
                    <input type="date" name="from_date" form="leadFiltersForm" value="{{ $filters['from_date'] }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">To Date</label>
                    <input type="date" name="to_date" form="leadFiltersForm" value="{{ $filters['to_date'] }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                </div>
            </div>
            <div class="flex justify-end mt-2.5">
                <button type="button" onclick="clearAdminFilters(event, 'leadFiltersForm')" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center">Clear</button>
            </div>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            @foreach ($resource['columns'] as $column)
                                <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" @if (in_array($column, $scrollableColumns, true)) style="min-width:150px;" @endif>{{ str_replace('_', ' ', ucfirst($column)) }}</th>
                            @endforeach
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Action</th>
                        </tr>
                        <tr class="surface-2 border-b bs filter-row">
                            @foreach ($resource['columns'] as $column)
                                @php
                                    $filterKey = $columnFilterMap[$column] ?? null;
                                    $canRenderFilter = $filterKey && array_key_exists($filterKey, $filters) && ! in_array($filterKey, $renderedFilterKeys, true);
                                @endphp
                                <th class="px-2 py-1">
                                    @if ($canRenderFilter)
                                        @php $renderedFilterKeys[] = $filterKey; @endphp
                                        <input
                                            type="text"
                                            name="{{ $filterKey }}"
                                            form="leadFiltersForm"
                                            class="px-2 py-1 text-xs rounded border bs surface t1 w-full outline-none focus-ring"
                                            placeholder="{{ $columnFilterLabels[$filterKey] ?? 'Filter' }}"
                                            value="{{ $filters[$filterKey] ?? '' }}"
                                        >
                                    @else
                                        <span class="text-center t3 block">—</span>
                                    @endif
                                </th>
                            @endforeach
                            <th class="px-2 py-1">
                                <div class="flex justify-end">
                                    <button type="button" onclick="clearAdminFilters(event, 'leadFiltersForm')" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">Clear</button>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($items as $item)
                            <tr class="hover:surface-2 transition border-b bs">
                                @foreach ($resource['columns'] as $column)
                                    @php
                                        $isLongText = in_array($column, $resource['long_text_columns'], true);
                                        $value = data_get($item, $column);
                                    @endphp
                                    <td class="px-3 py-2.5 text-xs">
                                        @if ($column === 'status')
                                            <span class="{{ $statusBadgeClass((string) $value) }}">{{ $formatValue($value, $column) }}</span>
                                        @elseif ($column === 'id')
                                            <span class="font-mono text-xs t3">{{ $formatValue($value, $column) }}</span>
                                        @else
                                            <span class="t1" @if($isLongText) title="{{ (string) $value }}" @endif>{{ $formatValue($value, $column, $isLongText) }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <a href="{{ route($resource['show_route'], $item->id) }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($resource['columns']) + 1 }}" class="text-center py-8 text-xs t3">No records found.</td>
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

