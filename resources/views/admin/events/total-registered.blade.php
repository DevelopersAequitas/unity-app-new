@extends('admin.layouts.app')

@section('title', 'Total Registered')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">

    <!-- Top Action Row -->
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
      <div>
        <h2 class="text-base font-bold tracking-wider uppercase text-indigo-600 font-display m-0">Total Registered</h2>
        <p class="text-xs t3 m-0 mt-0.5">Overview of all member and visitor registrations across all scheduled events</p>
      </div>
      <div class="flex items-center gap-2">
        <a href="{{ route('admin.events.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold surface hover:surface-2 transition text-slate-700 no-underline flex items-center gap-1.5">
          <i class="bi bi-calendar-event"></i> All Events
        </a>
      </div>
    </div>

    <!-- KPI Summary Cards Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-title">Total Registered</div>
          <div class="kpi-icon bg-purple-50 text-purple-600"><i class="bi bi-people"></i></div>
        </div>
        <div class="kpi-num">{{ number_format($summary['total_registered'] ?? 0) }}</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-title">Paid Registrations</div>
          <div class="kpi-icon bg-emerald-50 text-emerald-600"><i class="bi bi-cash-stack"></i></div>
        </div>
        <div class="kpi-num">{{ number_format($summary['paid'] ?? 0) }}</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-title">Free / Included</div>
          <div class="kpi-icon bg-blue-50 text-blue-600"><i class="bi bi-gift"></i></div>
        </div>
        <div class="kpi-num">{{ number_format($summary['free'] ?? 0) }}</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-title">Checked-in</div>
          <div class="kpi-icon bg-indigo-50 text-indigo-600"><i class="bi bi-person-check"></i></div>
        </div>
        <div class="kpi-num">{{ number_format($summary['checked_in'] ?? 0) }}</div>
      </div>
    </div>

    <!-- Search & Filters Toolbar -->
    <form method="GET" action="{{ route('admin.events.total-registered') }}" class="surface-2 rounded-xl border bs p-3 mb-4">
      <div class="grid grid-cols-1 md:grid-cols-6 gap-2.5 items-center">
        <div class="md:col-span-2">
          <input type="text" class="w-full px-3 py-1.5 text-xs rounded-lg border bs focus-ring" name="search" value="{{ request('search') }}" placeholder="🔍 Search registrant name, email, phone or event...">
        </div>
        <div>
          <select class="w-full px-3 py-1.5 text-xs rounded-lg border bs focus-ring" name="event_id">
            <option value="">All Events</option>
            @foreach($events as $evt)
              <option value="{{ $evt->id }}" @selected(request('event_id') == $evt->id)>{{ $evt->title }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <select class="w-full px-3 py-1.5 text-xs rounded-lg border bs focus-ring" name="circle_id">
            <option value="">All Circles</option>
            @foreach($circles as $circle)
              <option value="{{ $circle->id }}" @selected(request('circle_id') == $circle->id)>{{ $circle->name }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <select class="w-full px-3 py-1.5 text-xs rounded-lg border bs focus-ring" name="payment_status">
            <option value="">All Payment Statuses</option>
            <option value="paid" @selected(request('payment_status') === 'paid')>Paid</option>
            <option value="free" @selected(request('payment_status') === 'free')>Free</option>
            <option value="pending" @selected(request('payment_status') === 'pending')>Pending</option>
          </select>
        </div>
        <div class="flex items-center gap-1.5">
          <button type="submit" class="w-full py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-medium transition focus-ring">Filter</button>
          <a href="{{ route('admin.events.total-registered') }}" class="py-1.5 px-3 surface hover:surface-3 t2 rounded-lg text-xs font-medium border bs transition text-center no-underline">Clear</a>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-2.5 mt-2 pt-2 border-t bs">
        <div class="flex items-center gap-2">
          <span class="text-[11px] t3 font-medium whitespace-nowrap">From:</span>
          <input type="date" class="w-full px-2.5 py-1 text-xs rounded-lg border bs focus-ring" name="date_from" value="{{ request('date_from') }}">
        </div>
        <div class="flex items-center gap-2">
          <span class="text-[11px] t3 font-medium whitespace-nowrap">To:</span>
          <input type="date" class="w-full px-2.5 py-1 text-xs rounded-lg border bs focus-ring" name="date_to" value="{{ request('date_to') }}">
        </div>
      </div>
    </form>

    <!-- Table Section -->
    <div class="surface rounded-xl border bs overflow-hidden">
      <div class="overflow-x-auto relative">
        <table class="min-w-full border-collapse text-[13px]">
          <thead>
            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Registrant</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Contact Info</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Event</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Circle</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Payment</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Check-in Status</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Registered At</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody id="grid-body" class="divide-y divide-gray-200/50">
            @forelse($registrations as $row)
              @php
                $isMember = !empty($row->user_id);
                $name = $isMember ? ($row->user?->display_name ?: trim(($row->user?->first_name ?? '').' '.($row->user?->last_name ?? ''))) : ($row->visitor_name ?: 'Visitor');
                $email = $isMember ? ($row->user?->email ?: '-') : ($row->visitor_email ?: '-');
                $phone = $isMember ? ($row->user?->phone ?: '-') : ($row->visitor_phone ?: '-');
                $pStatus = strtolower((string) ($row->payment_status ?? ''));
                $isCheckedIn = strtolower((string) ($row->checkin_status ?? '')) === 'checked_in' || !empty($row->checked_in_at);
              @endphp
              <tr class="hover:surface-2 transition border-b bs">
                <td class="px-3 py-2.5 font-semibold text-slate-900 t1 whitespace-nowrap">
                  <div class="inline-flex items-center gap-1.5 flex-wrap">
                    <span>{{ $name }}</span>
                    <span class="chip px-2 py-0.5 text-[10px] font-semibold inline-flex items-center align-middle {{ $isMember ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-purple-50 text-purple-700 border-purple-200' }}">
                      {{ $isMember ? 'Member' : 'Visitor' }}
                    </span>
                  </div>
                </td>
                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">
                  <div>{{ $email }}</div>
                  @if($phone !== '-')<div class="text-[11px] text-gray-500">{{ $phone }}</div>@endif
                </td>
                <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                  <a href="{{ route('admin.events.show', $row->event_id) }}" class="text-indigo-600 hover:text-indigo-800 no-underline font-medium">
                    {{ $row->event?->title ?? 'Event #'.$row->event_id }}
                  </a>
                </td>
                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">
                  {{ $row->event?->circle?->name ?? '-' }}
                </td>
                <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                  @if(in_array($pStatus, ['paid', 'completed', 'success'], true))
                    <span class="chip inline-flex items-center justify-center px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">
                      Paid
                    </span>
                  @elseif($pStatus === 'free' || !(bool)($row->payment_required ?? false))
                    <span class="chip inline-flex items-center justify-center px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">
                      Free
                    </span>
                  @else
                    <span class="chip inline-flex items-center justify-center px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200">
                      {{ ucfirst($pStatus ?: 'Pending') }}
                    </span>
                  @endif
                </td>
                <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                  @if($isCheckedIn)
                    <span class="chip inline-flex items-center justify-center px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">
                      Checked In
                    </span>
                  @else
                    <span class="chip inline-flex items-center justify-center px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-600 border-gray-200">
                      Not Checked In
                    </span>
                  @endif
                </td>
                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                  {{ optional($row->created_at)->format('d M Y, h:i A') ?? '-' }}
                </td>
                <td class="px-3 py-2.5 text-right text-xs whitespace-nowrap">
                  <a href="{{ route('admin.events.show', $row->event_id) }}#registrations-section" class="inline-flex items-center justify-center px-2.5 py-1 rounded bg-indigo-50 text-indigo-700 border border-indigo-200 font-semibold no-underline hover:bg-indigo-100 transition whitespace-nowrap">
                    View Details
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="px-4 py-8 text-center t3 text-xs">
                  No registration records found.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($registrations->hasPages())
        <div class="p-3 border-t bs surface-2">
          {{ $registrations->links() }}
        </div>
      @endif
    </div>
</div>
@endsection
