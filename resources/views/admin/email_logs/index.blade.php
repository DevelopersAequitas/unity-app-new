@extends('admin.layouts.app')

@section('title', 'Email Logs')

@include('admin.partials.grid-head')

@section('content')
    @php
        $statusBadgeClass = static function (?string $status): string {
            return match (strtolower((string) $status)) {
                'sent' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200',
                'failed' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200',
                'queued', 'pending' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200',
                default => 'chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200',
            };
        };
    @endphp

    <form id="emailLogsFiltersForm" method="GET" action="{{ route('admin.email-logs.index') }}"></form>

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Email Logs</h2>
                <p class="text-xs t3 m-0 mt-0.5">Review outgoing email delivery attempts and stored email content.</p>
            </div>
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Total: {{ number_format($emailLogs->total()) }}</span>
        </div>

        <!-- Filter Card -->
        <div class="p-3 rounded-lg border bs surface-2">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-7 gap-2.5 items-end">
                <div class="lg:col-span-2">
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                    <input type="text" name="search" form="emailLogsFiltersForm" value="{{ $filters['search'] }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Email, name, subject, module">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                    <select name="status" form="emailLogsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="all" @selected($filters['status'] === 'all')>All</option>
                        <option value="sent" @selected($filters['status'] === 'sent')>Sent</option>
                        <option value="failed" @selected($filters['status'] === 'failed')>Failed</option>
                        <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                        <option value="queued" @selected($filters['status'] === 'queued')>Queued</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Module</label>
                    <select name="source_module" form="emailLogsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="">All</option>
                        @foreach ($sourceModules as $sourceModuleOption)
                            <option value="{{ $sourceModuleOption }}" @selected($filters['source_module'] === $sourceModuleOption)>{{ $sourceModuleOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Template</label>
                    <select name="template_key" form="emailLogsFiltersForm" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="">All</option>
                        @foreach ($templateKeys as $templateKeyOption)
                            <option value="{{ $templateKeyOption }}" @selected($filters['template_key'] === $templateKeyOption)>{{ $templateKeyOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">From</label>
                    <input type="date" name="date_from" form="emailLogsFiltersForm" value="{{ $filters['date_from'] }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">To</label>
                    <input type="date" name="date_to" form="emailLogsFiltersForm" value="{{ $filters['date_to'] }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                </div>
            </div>
            <div class="flex justify-end mt-2.5">
                <a href="{{ route('admin.email-logs.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center no-underline">Clear</a>
            </div>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created At / Date Time</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Recipient Email</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Recipient Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Subject</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Template Key</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Source Module</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($emailLogs as $emailLog)
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ optional($emailLog->created_at)->format('Y-m-d H:i:s') ?? optional($emailLog->sent_at)->format('Y-m-d H:i:s') ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-xs font-semibold t1">{{ $emailLog->to_email }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $emailLog->to_name ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-xs t2 max-w-[280px] truncate" title="{{ $emailLog->subject }}">{{ $emailLog->subject ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $emailLog->template_key ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $emailLog->source_module ?: '—' }}</td>
                                <td class="px-3 py-2.5 text-xs">
                                    <span class="{{ $statusBadgeClass($emailLog->status) }}">{{ ucfirst((string) $emailLog->status) }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <a href="{{ route('admin.email-logs.show', $emailLog->id) }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-8 text-xs t3">No email logs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $emailLogs->links() }}
            </div>
        </div>
    </div>
@endsection

