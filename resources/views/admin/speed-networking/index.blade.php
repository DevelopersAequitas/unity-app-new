@extends('admin.layouts.app')

@section('title', 'Speed Networking Events')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4 max-w-full min-w-0">

    @if(session('success'))
      <div class="px-3.5 py-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
          <i class="bi bi-check-circle-fill text-emerald-600 text-sm"></i>
          <span>{{ session('success') }}</span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 border-0 bg-transparent cursor-pointer text-xs">&times;</button>
      </div>
    @endif

    @if(session('error'))
      <div class="px-3.5 py-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs font-medium flex items-center justify-between shadow-2xs">
        <div class="flex items-center gap-2">
          <i class="bi bi-exclamation-triangle-fill text-rose-600 text-sm"></i>
          <span>{{ session('error') }}</span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-900 border-0 bg-transparent cursor-pointer text-xs">&times;</button>
      </div>
    @endif

    <!-- Top Action Row -->
    <div class="flex flex-wrap justify-between items-center gap-3 mb-2">
      <div>
        <h2 class="text-base font-bold tracking-wider uppercase text-indigo-600 font-display m-0 flex items-center gap-2">
          <i class="bi bi-lightning-charge-fill text-lg text-amber-500"></i>Speed Networking
        </h2>
        <p class="text-xs text-slate-500 m-0 mt-0.5">Automated 1-on-1 round-robin video networking with dynamic Agora rooms</p>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" onclick="document.getElementById('createEventModal').classList.remove('hidden')" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1.5 shadow-2xs cursor-pointer border-0">
          <i class="bi bi-plus-lg"></i> Create Speed Event
        </button>
      </div>
    </div>

    <!-- KPI Summary Cards Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <a href="{{ route('admin.speed-networking.index') }}" class="kpi-card no-underline p-3 rounded-xl border border-slate-200 bg-white block">
        <div class="flex justify-between items-center text-xs text-slate-500">
          <span>Total Events</span>
          <span class="p-1.5 rounded-lg bg-indigo-50 text-indigo-600"><i class="bi bi-calendar-event"></i></span>
        </div>
        <div class="text-xl font-bold text-slate-800 mt-1">{{ $totalEvents }}</div>
      </a>
      <a href="{{ route('admin.speed-networking.index', ['status' => 'in_progress']) }}" class="kpi-card no-underline p-3 rounded-xl border border-slate-200 bg-white block {{ $currentStatus === 'in_progress' ? 'ring-2 ring-emerald-500' : '' }}">
        <div class="flex justify-between items-center text-xs text-slate-500">
          <span>Live / In Progress</span>
          <span class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600"><i class="bi bi-broadcast"></i></span>
        </div>
        <div class="text-xl font-bold text-emerald-600 mt-1">{{ $inProgressEvents }}</div>
      </a>
      <a href="{{ route('admin.speed-networking.index', ['status' => 'scheduled']) }}" class="kpi-card no-underline p-3 rounded-xl border border-slate-200 bg-white block {{ $currentStatus === 'scheduled' ? 'ring-2 ring-indigo-500' : '' }}">
        <div class="flex justify-between items-center text-xs text-slate-500">
          <span>Scheduled / Upcoming</span>
          <span class="p-1.5 rounded-lg bg-blue-50 text-blue-600"><i class="bi bi-clock-history"></i></span>
        </div>
        <div class="text-xl font-bold text-blue-600 mt-1">{{ $scheduledEvents }}</div>
      </a>
      <a href="{{ route('admin.speed-networking.index', ['status' => 'completed']) }}" class="kpi-card no-underline p-3 rounded-xl border border-slate-200 bg-white block {{ $currentStatus === 'completed' ? 'ring-2 ring-slate-400' : '' }}">
        <div class="flex justify-between items-center text-xs text-slate-500">
          <span>Completed</span>
          <span class="p-1.5 rounded-lg bg-slate-100 text-slate-600"><i class="bi bi-check2-circle"></i></span>
        </div>
        <div class="text-xl font-bold text-slate-600 mt-1">{{ $completedEvents }}</div>
      </a>
    </div>

    <!-- Filters Row -->
    <div class="flex flex-wrap items-center justify-between gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
      <form method="GET" action="{{ route('admin.speed-networking.index') }}" class="flex flex-wrap items-center gap-2 m-0 flex-1">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search event title..." class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-xs w-64 focus:outline-none focus:ring-1 focus:ring-indigo-500">
        
        <select name="status" class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500">
          <option value="">All Statuses</option>
          <option value="draft" {{ $currentStatus === 'draft' ? 'selected' : '' }}>Draft</option>
          <option value="scheduled" {{ $currentStatus === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
          <option value="lobby_open" {{ $currentStatus === 'lobby_open' ? 'selected' : '' }}>Lobby Open</option>
          <option value="in_progress" {{ $currentStatus === 'in_progress' ? 'selected' : '' }}>In Progress</option>
          <option value="completed" {{ $currentStatus === 'completed' ? 'selected' : '' }}>Completed</option>
        </select>

        <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition border-0 cursor-pointer">
          <i class="bi bi-funnel"></i> Filter
        </button>

        @if($search || $currentStatus)
          <a href="{{ route('admin.speed-networking.index') }}" class="px-2.5 py-1.5 rounded-lg bg-slate-200 text-slate-700 hover:bg-slate-300 transition no-underline">Clear</a>
        @endif
      </form>
    </div>

    <!-- Events Grid / Table -->
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
      <table class="w-full text-left text-xs text-slate-600">
        <thead class="bg-slate-100 text-slate-700 uppercase font-semibold text-[11px] tracking-wider border-b border-slate-200">
          <tr>
            <th class="p-3">Event Details</th>
            <th class="p-3">Status</th>
            <th class="p-3">Rounds & Timers</th>
            <th class="p-3">Pricing</th>
            <th class="p-3">Participants</th>
            <th class="p-3">Schedule (Start / End)</th>
            <th class="p-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          @forelse($events as $event)
            @php
              $event = is_array($event) ? (object) $event : $event;
            @endphp
            <tr class="hover:bg-slate-50 transition">
              <td class="p-3">
                <div class="font-bold text-slate-800 text-sm">{{ $event->title ?? 'Untitled Event' }}</div>
                @if(!empty($event->description))
                  <div class="text-slate-500 text-xs truncate max-w-xs">{{ $event->description }}</div>
                @endif
              </td>
              <td class="p-3">
                @if(($event->status ?? '') === 'in_progress')
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Round {{ $event->current_round ?? 1 }}
                  </span>
                @elseif(($event->status ?? '') === 'completed')
                  <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">Completed</span>
                @elseif(($event->status ?? '') === 'lobby_open')
                  <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Lobby Open</span>
                @elseif(($event->status ?? '') === 'scheduled')
                  <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">Scheduled</span>
                @elseif(($event->status ?? '') === 'paused')
                  <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Paused</span>
                @else
                  <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">Draft</span>
                @endif
              </td>
              <td class="p-3">
                <div><span class="font-semibold text-slate-700">{{ $event->round_duration_seconds ?? 180 }}s</span> / round</div>
                <div class="text-slate-500 text-[11px]">
                  {{ $event->break_duration_seconds ?? 20 }}s break &bull;
                  @if(!empty($event->total_rounds) && (int) $event->total_rounds > 0)
                    {{ $event->total_rounds }} rounds (Fixed)
                  @else
                    <span class="text-indigo-600 font-semibold">Auto-Scale</span>
                  @endif
                </div>
              </td>
              <td class="p-3">
                @if(!empty($event->is_paid) && (float) ($event->ticket_price ?? 0) > 0)
                  <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800">₹{{ number_format((float) $event->ticket_price, 2) }}</span>
                @else
                  <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">Free</span>
                @endif
              </td>
              <td class="p-3">
                <div class="font-semibold text-slate-800">{{ $event->active_participants_count ?? $event->participants_count ?? 0 }} joined</div>
                <div class="text-slate-500 text-[11px]">{{ $event->matches_count ?? 0 }} pairings</div>
              </td>
              <td class="p-3">
                @if(!empty($event->scheduled_start_time))
                  <div class="font-medium text-slate-700 text-xs">
                    {{ is_string($event->scheduled_start_time) ? \Carbon\Carbon::parse($event->scheduled_start_time)->format('d M, h:i A') : $event->scheduled_start_time->format('d M, h:i A') }}
                  </div>
                  @if(!empty($event->scheduled_end_time))
                    <div class="text-slate-400 text-[11px]">
                      to {{ is_string($event->scheduled_end_time) ? \Carbon\Carbon::parse($event->scheduled_end_time)->format('h:i A') : $event->scheduled_end_time->format('h:i A') }}
                    </div>
                  @endif
                @else
                  <span class="text-slate-400">Manual Start</span>
                @endif
              </td>
              <td class="p-3 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <a href="{{ route('admin.speed-networking.show', $event->id) }}" class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 font-semibold text-xs no-underline flex items-center gap-1 transition">
                    <i class="bi bi-display"></i> Control Room
                  </a>
                  <form method="POST" action="{{ route('admin.speed-networking.destroy', $event->id) }}" onsubmit="return confirm('Are you sure you want to delete this speed networking event?')" class="inline m-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 border-0 bg-transparent cursor-pointer" title="Delete">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="p-8 text-center text-slate-400 text-sm">
                <i class="bi bi-lightning text-3xl text-slate-300 block mb-2"></i>
                No Speed Networking events found. Click "Create Speed Event" above to create one.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>

      @if(!empty($pagination['last_page']) && $pagination['last_page'] > 1)
        <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
          <div>
            Showing page <span class="font-semibold text-slate-700">{{ $pagination['current_page'] }}</span> of <span class="font-semibold text-slate-700">{{ $pagination['last_page'] }}</span> ({{ $pagination['total'] }} total)
          </div>
          <div class="flex items-center gap-2">
            @if($pagination['current_page'] > 1)
              <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['current_page'] - 1]) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-medium no-underline">Previous</a>
            @endif
            @if($pagination['current_page'] < $pagination['last_page'])
              <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['current_page'] + 1]) }}" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-medium no-underline">Next</a>
            @endif
          </div>
        </div>
      @endif
    </div>

</div>

<!-- Create Event Modal -->
<div id="createEventModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg border border-slate-200 overflow-hidden max-h-[90vh] flex flex-col">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50 shrink-0">
      <h3 class="text-sm font-bold text-slate-800 m-0 flex items-center gap-2">
        <i class="bi bi-lightning-charge-fill text-amber-500"></i> Create Speed Networking Event
      </h3>
      <button type="button" onclick="document.getElementById('createEventModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 border-0 bg-transparent text-lg cursor-pointer">&times;</button>
    </div>
    <form method="POST" action="{{ route('admin.speed-networking.store') }}" class="p-6 space-y-4 m-0 overflow-y-auto">
      @csrf
      <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1">Event Title *</label>
        <input type="text" name="title" required placeholder="e.g., Global Founders 1-on-1 Speed Networking" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs focus:ring-1 focus:ring-indigo-500 focus:outline-none">
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-700 mb-1">Description</label>
        <textarea name="description" rows="2" placeholder="Brief event briefing for participants..." class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs focus:ring-1 focus:ring-indigo-500 focus:outline-none"></textarea>
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Round Time (s)</label>
          <input type="number" name="round_duration_seconds" value="180" min="10" max="3600" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs focus:ring-1 focus:ring-indigo-500 focus:outline-none">
          <span class="text-[10px] text-slate-400">180s = 3 min</span>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Break Time (s)</label>
          <input type="number" name="break_duration_seconds" value="20" min="5" max="600" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs focus:ring-1 focus:ring-indigo-500 focus:outline-none">
          <span class="text-[10px] text-slate-400">Intermission</span>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">
            Total Rounds <span class="text-slate-400 font-normal">(Optional)</span>
          </label>
          <input type="number" name="total_rounds" value="" min="1" max="100" placeholder="Auto-scale (Dynamic)" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs focus:ring-1 focus:ring-indigo-500 focus:outline-none">
          <span class="text-[10px] text-slate-400">Leave blank for Auto-Scale (N-1) or set fixed count</span>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Scheduled Start Time</label>
          <input type="datetime-local" name="scheduled_start_time" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs focus:ring-1 focus:ring-indigo-500 focus:outline-none">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Scheduled End Time</label>
          <input type="datetime-local" name="scheduled_end_time" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs focus:ring-1 focus:ring-indigo-500 focus:outline-none">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1">Event Access</label>
          <select name="is_free" onchange="document.getElementById('ticketPriceField').style.display = this.value === '0' ? 'block' : 'none'" class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-xs focus:ring-1 focus:ring-indigo-500 focus:outline-none">
            <option value="1" selected>Free Event</option>
            <option value="0">Paid Event</option>
          </select>
        </div>
        <div id="ticketPriceField" style="display: none;">
          <label class="block text-xs font-semibold text-slate-700 mb-1">Ticket Price (INR)</label>
          <input type="number" name="ticket_price" step="0.01" value="299.00" placeholder="299.00" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs focus:ring-1 focus:ring-indigo-500 focus:outline-none">
        </div>
      </div>

      <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100 shrink-0">
        <button type="button" onclick="document.getElementById('createEventModal').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-50 cursor-pointer bg-white">Cancel</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 cursor-pointer border-0 shadow-2xs">Create & Launch</button>
      </div>
    </form>
  </div>
</div>
@endsection
