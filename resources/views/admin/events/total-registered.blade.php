@extends('admin.layouts.app')

@section('title', 'Total Registered')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4 max-w-full min-w-0">

    <!-- Top Action Row -->
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
      <div>
        <h2 class="text-base font-bold tracking-wider uppercase text-indigo-600 font-display m-0">Total Registered</h2>
        <p class="text-xs t3 m-0 mt-0.5">
          @if(request('event_id') && ($selectedEvt = $events->firstWhere('id', request('event_id'))))
            Showing registrations for event: <span class="font-semibold text-indigo-600">{{ $selectedEvt->title }}</span>
          @else
            Overview of all member and visitor registrations across all scheduled events
          @endif
        </p>
      </div>
      <div class="flex items-center gap-2">
        <a href="{{ route('admin.events.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold surface hover:surface-2 transition text-slate-700 no-underline flex items-center gap-1.5">
          <i class="bi bi-calendar-event admin-icon" aria-hidden="true"></i> All Events
        </a>
      </div>
    </div>

    <!-- KPI Summary Cards Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-title">Total Registered</div>
          <div class="kpi-icon"><i class="bi bi-people admin-icon" aria-hidden="true"></i></div>
        </div>
        <div class="kpi-num">{{ number_format($summary['total_registered'] ?? 0) }}</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-title">Paid Registrations</div>
          <div class="kpi-icon"><i class="bi bi-cash-stack admin-icon" aria-hidden="true"></i></div>
        </div>
        <div class="kpi-num">{{ number_format($summary['paid'] ?? 0) }}</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-title">Free / Included</div>
          <div class="kpi-icon"><i class="bi bi-gift admin-icon" aria-hidden="true"></i></div>
        </div>
        <div class="kpi-num">{{ number_format($summary['free'] ?? 0) }}</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-title">Checked-in</div>
          <div class="kpi-icon"><i class="bi bi-person-check admin-icon" aria-hidden="true"></i></div>
        </div>
        <div class="kpi-num">{{ number_format($summary['checked_in'] ?? 0) }}</div>
      </div>
    </div>

    <!-- Search & Filters Toolbar -->
    <form method="GET" action="{{ route('admin.events.total-registered') }}" class="surface-2 rounded-xl border bs p-3.5 mb-4 admin-filter-form">
      <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <div class="md:col-span-3">
          <label class="block text-[11px] font-semibold t3 uppercase tracking-wider mb-1">Search</label>
          <input type="text" class="w-full px-3 py-1.5 text-xs surface border bs rounded-lg outline-none focus-ring" name="search" value="{{ request('search') }}" placeholder="Search registrant name, email, phone, event...">
        </div>
        <div class="md:col-span-3">
          <label class="block text-[11px] font-semibold t3 uppercase tracking-wider mb-1">Event</label>
          <select class="w-full px-3 py-1.5 text-xs surface border bs rounded-lg outline-none focus-ring" name="event_id">
            <option value="">All Events</option>
            @foreach($events as $evt)
              <option value="{{ $evt->id }}" @selected(request('event_id') == $evt->id)>{{ $evt->title }}</option>
            @endforeach
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-[11px] font-semibold t3 uppercase tracking-wider mb-1">Circle</label>
          <select class="w-full px-3 py-1.5 text-xs surface border bs rounded-lg outline-none focus-ring" name="circle_id">
            <option value="">All Circles</option>
            @foreach($circles as $circle)
              <option value="{{ $circle->id }}" @selected(request('circle_id') == $circle->id)>{{ $circle->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="md:col-span-2">
          <label class="block text-[11px] font-semibold t3 uppercase tracking-wider mb-1">Payment</label>
          <select class="w-full px-3 py-1.5 text-xs surface border bs rounded-lg outline-none focus-ring" name="payment_status">
            <option value="">All Payment Statuses</option>
            <option value="paid" @selected(request('payment_status') === 'paid')>Paid</option>
            <option value="free" @selected(request('payment_status') === 'free')>Free</option>
            <option value="pending" @selected(request('payment_status') === 'pending')>Pending</option>
          </select>
        </div>
        <div class="md:col-span-2 flex items-center gap-1.5">
          <button type="submit" class="w-full py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-medium transition focus-ring">Filter</button>
          <a href="{{ route('admin.events.total-registered') }}" class="py-1.5 px-3 surface hover:surface-3 t2 rounded-lg text-xs font-medium border bs transition text-center no-underline">Clear</a>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mt-3 pt-3 border-t bs items-end">
        <div class="md:col-span-3 flex items-center gap-2">
          <label class="text-[11px] t3 font-semibold uppercase tracking-wider whitespace-nowrap min-w-[40px]">From:</label>
          <input type="date" class="w-full px-3 py-1.5 text-xs surface border bs rounded-lg outline-none focus-ring" name="date_from" value="{{ request('date_from') }}">
        </div>
        <div class="md:col-span-3 flex items-center gap-2">
          <label class="text-[11px] t3 font-semibold uppercase tracking-wider whitespace-nowrap min-w-[30px]">To:</label>
          <input type="date" class="w-full px-3 py-1.5 text-xs surface border bs rounded-lg outline-none focus-ring" name="date_to" value="{{ request('date_to') }}">
        </div>
      </div>
    </form>

    <!-- Table Section -->
    <div class="surface rounded-xl border bs overflow-hidden">
      <div class="overflow-x-auto relative">
        <table class="min-w-[1500px] w-full border-collapse text-[13px]">
          <thead>
            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left sticky left-0 z-10" style="width:160px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.12);">Registrant</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="width:100px;">Phone</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="width:180px;">Email</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="width:160px;">Event</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="width:130px;">Event Date</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="width:160px;">Circle</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="width:90px;">Payment</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="width:120px;">Check-in Status</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="width:100px;">QR Status</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="width:90px;">QR Code</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="width:100px;">QR Download</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="width:130px;">Registered At</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-right" style="width:100px;">Actions</th>
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
                <td class="px-3 py-2.5 font-semibold text-slate-900 t1 whitespace-nowrap sticky left-0 z-10 surface align-middle" style="width:160px; box-shadow: 2px 0 6px -2px rgba(0,0,0,0.10);">
                  <div class="inline-flex items-center gap-1.5 flex-wrap">
                    @if($isMember && !empty($row->user_id))
                      <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $row->user_id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                        {{ $name }}
                      </a>
                    @else
                      <span>{{ $name }}</span>
                    @endif
                    <span class="chip px-2 py-0.5 text-[10px] font-semibold inline-flex items-center align-middle {{ $isMember ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-purple-50 text-purple-700 border-purple-200' }}">
                      {{ $isMember ? 'Member' : 'Visitor' }}
                    </span>
                  </div>
                </td>
                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap align-middle" style="width:100px;">{{ $phone }}</td>
                <td class="px-3 py-2.5 text-xs t2 align-middle">
                  <x-admin-grid-text :text="$email" :lines="1" />
                </td>
                <td class="px-3 py-2.5 text-xs align-middle">
                  <x-admin-grid-text :lines="2">
                    <a href="{{ route('admin.events.show', $row->event_id) }}" class="text-indigo-600 hover:text-indigo-800 font-medium no-underline">
                      {{ $row->event?->title ?? 'Event #'.$row->event_id }}
                    </a>
                  </x-admin-grid-text>
                </td>
                <td class="px-3 py-2.5 text-xs t2 align-middle">
                  {{ optional($row->occurrence?->start_at)->format('d M Y') ?: '—' }}
                </td>
                <td class="px-3 py-2.5 text-xs t2 align-middle">
                  <x-admin-grid-text :lines="2">
                    @if(!empty($row->event?->circle?->id))
                      <a href="{{ route('admin.circles.show', $row->event->circle->id) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline font-medium no-underline">
                        {{ $row->event->circle->name }}
                      </a>
                    @else
                      {{ $row->event?->circle?->name ?? '-' }}
                    @endif
                  </x-admin-grid-text>
                </td>
                <td class="px-3 py-2.5 text-xs whitespace-nowrap align-middle">
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
                <td class="px-3 py-2.5 text-xs whitespace-nowrap align-middle">
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
                @php
                  $paymentStatus = strtolower((string) ($row->payment_status ?? ''));
                  $isEligible = (! $row->payment_required || in_array($paymentStatus, ['paid', 'success', 'completed'], true));
                  if ($isEligible && (empty($row->qr_code_svg) || empty($row->qr_token) || ! str_contains((string) $row->qr_code_svg, (string) $row->qr_token))) {
                      $row = app(\App\Services\Events\EventRegistrationQrService::class)->ensureQrGenerated($row);
                  }
                  $hasQr = !empty($row->qr_code_url) || !empty($row->qr_code_path) || !empty($row->qr_code_svg);
                  $qrAvailable = $hasQr && $isEligible;
                  $inlineSvg = $qrAvailable ? $row->qr_code_svg : null;
                  $qrUrl = $qrAvailable ? app(\App\Services\Events\EventRegistrationQrService::class)->qrCodeUrl($row) : null;
                @endphp
                <!-- QR Status -->
                <td class="px-3 py-2.5 text-center align-middle">
                  @if($row->qr_status === 'expired')
                      <span class="badge bg-danger d-inline-block">Expired</span>
                  @else
                      <span class="badge bg-{{ $qrAvailable ? 'success' : 'secondary' }} d-inline-block">{{ $qrAvailable ? 'Generated' : 'Pending' }}</span>
                  @endif
                </td>
                <!-- QR Code Thumbnail -->
                <td class="px-3 py-2.5 text-center align-middle">
                  @if($inlineSvg)
                      <button type="button" class="btn p-0 border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#qrModal_{{ $row->id }}" title="Click to view full size QR Code">
                          <div style="background: #ffffff; padding: 4px; border-radius: 6px; border: 1px solid #cbd5e1; display: inline-block; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer;">
                              <div style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                  {!! $inlineSvg !!}
                              </div>
                          </div>
                      </button>

                      <!-- High-Res QR Code Modal -->
                      <div class="modal fade" id="qrModal_{{ $row->id }}" tabindex="-1" aria-hidden="true">
                          <div class="modal-dialog modal-dialog-centered modal-sm">
                              <div class="modal-content text-center" style="border-radius: 12px; overflow: hidden;">
                                  <div class="modal-header bg-dark text-white py-2">
                                      <h6 class="modal-title mb-0 fs-6">QR Code Pass</h6>
                                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                  </div>
                                  <div class="modal-body p-4 bg-light">
                                      <div style="background: #ffffff; padding: 16px; border-radius: 12px; display: inline-block; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                          <div style="width: 220px; height: 220px; margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                                              {!! $inlineSvg !!}
                                          </div>
                                      </div>
                                      @if($qrUrl)
                                          <div class="mt-2">
                                              <a href="{{ $qrUrl }}" download="QR_{{ $row->id }}.png" target="_blank" class="btn btn-sm btn-primary">Download QR Pass</a>
                                          </div>
                                      @endif
                                      <div class="mt-3 text-muted small fw-semibold">
                                          {{ $name }}
                                      </div>
                                      <div class="text-muted" style="font-size: 11px;">
                                          Registration ID: {{ $row->id }}
                                      </div>
                                  </div>
                              </div>
                          </div>
                      </div>
                  @elseif($qrUrl)
                      <a href="{{ $qrUrl }}" target="_blank" rel="noopener" title="Click to open QR Code image">
                          <img src="{{ $qrUrl }}" alt="QR Code" style="width: 50px; height: 50px; object-fit: contain; border: 1px solid #dee2e6; border-radius: 4px; padding: 2px; background: #fff;" />
                      </a>
                  @else
                      —
                  @endif
                </td>
                <!-- QR Download Option -->
                <td class="px-3 py-2.5 text-center align-middle">
                  @if($qrUrl)
                      <a href="{{ $qrUrl }}" download="QR_{{ $row->id }}.png" target="_blank" class="btn btn-xs btn-outline-primary" style="font-size: 11px; padding: 3px 8px;">Download</a>
                  @else
                      —
                  @endif
                </td>
                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap align-middle">
                  {{ optional($row->created_at)->format('d M Y, h:i A') ?? '-' }}
                </td>
                <td class="px-3 py-2.5 text-right text-xs whitespace-nowrap align-middle" style="min-width:110px;">
                  <a href="{{ route('admin.events.show', $row->event_id) }}#registrations-section" class="inline-flex items-center justify-center px-2.5 py-1 rounded bg-indigo-50 text-indigo-700 border border-indigo-200 font-semibold no-underline hover:bg-indigo-100 transition whitespace-nowrap">
                    View Details
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="10" class="px-4 py-8 text-center t3 text-xs">
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

@include('admin.activities.partials.peer-modal')
@endsection
