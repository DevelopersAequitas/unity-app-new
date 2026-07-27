@extends('admin.layouts.app')

@section('title', 'Attendance - '.$event->title)

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-6">
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Event Attendance</h2>
            <p class="text-xs t1 font-medium m-0 mt-0.5">{{ $event->title }}</p>
        </div>
        <a href="{{ route('admin.events.show', $event->id) }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline">Back to Event</a>
    </div>

    {{-- Summary Metrics Grid --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        @foreach($report['summary'] as $label => $value)
            <div class="rounded-xl border bs surface p-3 text-center space-y-1">
                <div class="text-lg font-bold font-display text-indigo-600">{{ $value }}</div>
                <div class="text-[11px] t3 uppercase font-semibold tracking-wider">{{ str_replace('_',' ', $label) }}</div>
            </div>
        @endforeach
    </div>

    <form method="GET" class="border bs rounded-xl p-3.5 surface-2">
        <div class="row g-3 align-items-center">
            <div class="col-md-3">
                <input class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="search" value="{{ request('search') }}" placeholder="Search name / email / phone">
            </div>
            <div class="col-md-2">
                <select class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="attendee_type">
                    <option value="">All Types</option>
                    <option value="member" @selected(request('attendee_type')==='member')>Member</option>
                    <option value="visitor" @selected(request('attendee_type')==='visitor')>Visitor</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" name="checkin_status">
                    <option value="">Any Check-in</option>
                    <option value="pending" @selected(request('checkin_status')==='pending')>Pending</option>
                    <option value="checked_in" @selected(request('checkin_status')==='checked_in')>Checked In</option>
                </select>
            </div>
            <div class="col-md-2 d-flex justify-content-end">
                <a href="{{ route('admin.events.attendance', $event->id) }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline">Clear</a>
            </div>
        </div>
    </form>

    <div class="rounded-xl border bs surface overflow-hidden space-y-4">
        <div class="px-4 py-3 surface-2 border-b bs">
            <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Attendee Registrations</h6>
        </div>
        <div class="overflow-x-auto relative">
            <table class="min-w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Name</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Type</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Phone / Email</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company / City</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Payment</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Razorpay</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Invoice</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Check-in</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Registered</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Checked In</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">QR</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/50">
                    @forelse($report['items'] as $item)
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-3 py-2.5 font-semibold t1 text-[12.5px] whitespace-nowrap">{{ $item['user']['name'] ?? $item['visitor']['name'] ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-xs">
                                <span class="chip px-2.5 py-0.5 text-xs font-semibold uppercase bg-gray-100 text-gray-700 border-gray-200">{{ $item['attendee_type'] }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-xs t2">
                                {{ $item['user']['phone'] ?? $item['visitor']['phone'] ?? '-' }}
                                <div class="t3 text-[10px]">{{ $item['user']['email'] ?? $item['visitor']['email'] ?? '' }}</div>
                            </td>
                            <td class="px-3 py-2.5 text-xs t2">
                                {{ $item['user']['company_name'] ?? $item['visitor']['company'] ?? '-' }}
                                <div class="t3 text-[10px]">{{ $item['user']['city'] ?? $item['visitor']['city'] ?? '' }}</div>
                            </td>
                            <td class="px-3 py-2.5 text-xs font-medium t1">{{ $item['status'] }}</td>
                            <td class="px-3 py-2.5 text-xs t2">{{ $item['payment_status'] ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-xs t3 font-mono">{{ $item['razorpay_payment_id'] ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-xs">
                                @if(!empty($item['invoice_pdf_url']) || !empty($item['invoice_url']))
                                    <a href="{{ $item['invoice_pdf_url'] ?? $item['invoice_url'] }}" target="_blank" class="text-indigo-600 font-semibold no-underline">{{ $item['zoho_invoice_number'] ?? 'Invoice' }}</a>
                                @else 
                                    <span class="t3">{{ $item['invoice_sync_status'] ?? '-' }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs">
                                <span class="chip px-2.5 py-0.5 text-xs font-semibold {{ $item['checkin_status'] === 'checked_in' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200' }}">{{ $item['checkin_status'] }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $item['registered_at'] }}</td>
                            <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ $item['checked_in_at'] ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-center text-xs">
                                @if($item['qr_code_url'])
                                    <a href="{{ $item['qr_code_url'] }}" target="_blank" class="text-indigo-600 font-semibold no-underline">Open QR</a>
                                @else 
                                    <span class="t3">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center py-8 text-xs t3">No registrations found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border bs surface overflow-hidden space-y-4">
        <div class="px-4 py-3 surface-2 border-b bs">
            <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Scan History</h6>
        </div>
        <div class="overflow-x-auto relative">
            <table class="min-w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Scanned User</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Scanner Person</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Hotel</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Scanned Time</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Device Info</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/50">
                    @forelse(($scanLogs ?? collect()) as $log)
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-3 py-2.5 font-semibold t1 text-[12.5px] whitespace-nowrap">{{ $log->user?->display_name ?? $log->user?->email ?? data_get($log->meta, 'registration_id', '-') }}</td>
                            <td class="px-3 py-2.5 text-xs t2">{{ $log->scanner?->name ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-xs t2">{{ $log->scanner?->hotel_name ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-xs">
                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">{{ $log->scan_status }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ optional($log->scanned_at)->toDateTimeString() ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-xs t3 font-mono truncate max-w-[200px]" title="{{ $log->device_info ? json_encode($log->device_info) : '-' }}">{{ $log->device_info ? json_encode($log->device_info) : '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-8 text-xs t3">No scan history found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

