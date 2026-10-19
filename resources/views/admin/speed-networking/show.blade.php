@extends('admin.layouts.app')

@section('title', $event->title . ' - Control Room')

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

    <!-- Event Header & Control Actions -->
    <div class="flex flex-wrap justify-between items-center gap-4 pb-3 border-b border-slate-200">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <a href="{{ route('admin.speed-networking.index') }}" class="text-xs text-indigo-600 hover:underline flex items-center gap-1 font-semibold">
            <i class="bi bi-arrow-left"></i> All Events
          </a>
          <span class="text-slate-300">/</span>
          <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Control Room</span>
        </div>
        <h1 class="text-lg font-bold text-slate-800 m-0 flex items-center gap-2">
          <i class="bi bi-broadcast text-rose-500"></i>{{ $event->title }}
        </h1>
        <div class="flex items-center gap-3 text-xs text-slate-500 mt-1">
          <span><i class="bi bi-stopwatch"></i> {{ $event->round_duration_seconds }}s round</span>
          <span>&bull;</span>
          <span><i class="bi bi-cup-hot"></i> {{ $event->break_duration_seconds }}s break</span>
          <span>&bull;</span>
          <span><i class="bi bi-layers"></i> Total {{ $event->total_rounds }} rounds</span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2">
        <button onclick="window.location.reload()" class="px-3 py-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold flex items-center gap-1.5 cursor-pointer shadow-2xs">
          <i class="bi bi-arrow-clockwise"></i> Refresh Live
        </button>

        @if($event->status !== 'in_progress' && $event->status !== 'completed')
          <form method="POST" action="{{ route('admin.speed-networking.start', $event->id) }}" onsubmit="return confirm('Start this speed networking event now and generate Round 1 pairings?')" class="m-0">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold flex items-center gap-1.5 cursor-pointer border-0 shadow-2xs">
              <i class="bi bi-play-fill text-base"></i> Start Event (Round 1)
            </button>
          </form>
        @endif

        @if($event->status === 'in_progress')
          <form method="POST" action="{{ route('admin.speed-networking.next-round', $event->id) }}" onsubmit="return confirm('Advance all participants to the next round?')" class="m-0">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold flex items-center gap-1.5 cursor-pointer border-0 shadow-2xs">
              <i class="bi bi-fast-forward-fill"></i> Next Round
            </button>
          </form>

          <form method="POST" action="{{ route('admin.speed-networking.end', $event->id) }}" onsubmit="return confirm('Conclude and end this event now?')" class="m-0">
            @csrf
            <button type="submit" class="px-3 py-2 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold flex items-center gap-1.5 cursor-pointer border border-rose-200">
              <i class="bi bi-stop-circle"></i> End Event
            </button>
          </form>
        @endif
      </div>
    </div>

    <!-- Live Status Overview Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="p-3 rounded-xl border border-slate-200 bg-white">
        <div class="text-xs text-slate-500">Current Status</div>
        <div class="mt-1 flex items-center gap-2">
          @if($event->status === 'in_progress')
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
            <span class="text-sm font-bold text-emerald-600 uppercase">Live (Round {{ $event->current_round }})</span>
          @elseif($event->status === 'completed')
            <span class="text-sm font-bold text-slate-600 uppercase">Completed</span>
          @elseif($event->status === 'lobby_open')
            <span class="text-sm font-bold text-amber-600 uppercase">Lobby Open</span>
          @else
            <span class="text-sm font-bold text-blue-600 uppercase">Scheduled</span>
          @endif
        </div>
      </div>

      <div class="p-3 rounded-xl border border-slate-200 bg-white">
        <div class="text-xs text-slate-500">Active Participants</div>
        <div class="text-lg font-bold text-slate-800 mt-1">
          {{ $stats['active_participants'] }} <span class="text-xs font-normal text-slate-400">/ {{ $stats['total_participants'] }} joined</span>
        </div>
      </div>

      <div class="p-3 rounded-xl border border-slate-200 bg-white">
        <div class="text-xs text-slate-500">Active Tables (Now)</div>
        <div class="text-lg font-bold text-indigo-600 mt-1">
          {{ count($liveData['tables']) }} <span class="text-xs font-normal text-slate-400">tables active</span>
        </div>
      </div>

      <div class="p-3 rounded-xl border border-slate-200 bg-white">
        <div class="text-xs text-slate-500">Total Matchups Generated</div>
        <div class="text-lg font-bold text-slate-800 mt-1">
          {{ $stats['total_matches'] }}
        </div>
      </div>
    </div>

    <!-- Main Grid: Live Tables & Participant Roster -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      
      <!-- Live Tables Section (2 cols) -->
      <div class="lg:col-span-2 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 m-0 flex items-center gap-2">
            <i class="bi bi-grid-fill text-indigo-600"></i> Active Round Tables (Round {{ $event->current_round }})
          </h3>
          <span class="text-[11px] text-slate-400">Auto-assigned 1-on-1 Agora Rooms</span>
        </div>

        @if(empty($liveData['tables']))
          <div class="p-8 rounded-xl border border-dashed border-slate-300 bg-white text-center text-slate-400 text-xs">
            <i class="bi bi-people text-3xl text-slate-300 block mb-2"></i>
            @if($event->status === 'in_progress')
              No tables generated for this round yet.
            @else
              The event has not started yet. Participants are in the lobby. Click "Start Event" to generate Round 1 tables.
            @endif
          </div>
        @else
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($liveData['tables'] as $table)
              <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-2xs space-y-2.5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                  <div class="flex items-center gap-1.5">
                    @if($table['is_break_round'])
                      <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[11px] font-bold">Networking Lounge (Break)</span>
                    @else
                      <span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 text-[11px] font-bold">Table #{{ $table['table_number'] }}</span>
                    @endif
                  </div>
                  @if($table['channel_id'])
                    <span class="font-mono text-[10px] text-slate-400 truncate max-w-[140px]" title="{{ $table['channel_id'] }}">{{ $table['channel_id'] }}</span>
                  @endif
                </div>

                <!-- Peer Pair Visualizer -->
                <div class="flex items-center justify-between gap-2 py-1">
                  <!-- User A -->
                  <div class="flex-1 text-center">
                    <div class="w-10 h-10 mx-auto rounded-full bg-indigo-50 border border-indigo-200 overflow-hidden flex items-center justify-center text-indigo-600 font-bold text-xs mb-1">
                      @if(!empty($table['user_a']['avatar_url']))
                        <img src="{{ $table['user_a']['avatar_url'] }}" alt="" class="w-full h-full object-cover">
                      @else
                        {{ strtoupper(substr($table['user_a']['name'] ?? 'A', 0, 1)) }}
                      @endif
                    </div>
                    <div class="font-bold text-slate-800 text-xs truncate">{{ $table['user_a']['name'] ?? 'Peer' }}</div>
                    <div class="text-slate-400 text-[10px] truncate">{{ $table['user_a']['company'] ?? '' }}</div>
                  </div>

                  <!-- Connection Icon -->
                  <div class="text-slate-300 px-1">
                    @if($table['is_break_round'])
                      <i class="bi bi-cup-hot text-amber-500 text-sm"></i>
                    @else
                      <i class="bi bi-arrow-left-right text-indigo-500 text-sm"></i>
                    @endif
                  </div>

                  <!-- User B -->
                  <div class="flex-1 text-center">
                    @if($table['user_b'])
                      <div class="w-10 h-10 mx-auto rounded-full bg-indigo-50 border border-indigo-200 overflow-hidden flex items-center justify-center text-indigo-600 font-bold text-xs mb-1">
                        @if(!empty($table['user_b']['avatar_url']))
                          <img src="{{ $table['user_b']['avatar_url'] }}" alt="" class="w-full h-full object-cover">
                        @else
                          {{ strtoupper(substr($table['user_b']['name'] ?? 'B', 0, 1)) }}
                        @endif
                      </div>
                      <div class="font-bold text-slate-800 text-xs truncate">{{ $table['user_b']['name'] ?? 'Peer' }}</div>
                      <div class="text-slate-400 text-[10px] truncate">{{ $table['user_b']['company'] ?? '' }}</div>
                    @else
                      <div class="w-10 h-10 mx-auto rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600 text-xs mb-1">
                        <i class="bi bi-pause"></i>
                      </div>
                      <div class="font-medium text-amber-700 text-xs">Waiting / Bye</div>
                      <div class="text-slate-400 text-[10px]">Auto-prioritized next round</div>
                    @endif
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        @endif
      </div>

      <!-- Live Participant Roster Section (1 col) -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 m-0 flex items-center gap-2">
            <i class="bi bi-people-fill text-indigo-600"></i> Event Roster ({{ count($participants) }})
          </h3>
          <span class="text-[11px] text-slate-400">Live Heartbeat Status</span>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-2xs">
          <ul class="divide-y divide-slate-100 max-h-[500px] overflow-y-auto p-0 m-0 list-none text-xs">
            @forelse($participants as $p)
              <li class="p-3 flex items-center justify-between hover:bg-slate-50 transition">
                <div class="flex items-center gap-2.5">
                  <div class="relative">
                    <div class="w-8 h-8 rounded-full bg-slate-100 overflow-hidden flex items-center justify-center text-xs font-bold text-slate-600">
                      @if(!empty($p['avatar_url']))
                        <img src="{{ $p['avatar_url'] }}" alt="" class="w-full h-full object-cover">
                      @else
                        {{ strtoupper(substr($p['name'], 0, 1)) }}
                      @endif
                    </div>
                    @if($p['is_online'])
                      <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-500 border-2 border-white" title="Active"></span>
                    @else
                      <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-slate-300 border-2 border-white" title="Idle / Offline"></span>
                    @endif
                  </div>
                  <div>
                    <div class="font-bold text-slate-800 text-xs">{{ $p['name'] }}</div>
                    <div class="text-slate-400 text-[10px]">{{ $p['company'] }} &bull; {{ $p['city'] }}</div>
                  </div>
                </div>

                <div>
                  @if($p['status'] === 'in_call')
                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-semibold">In Call</span>
                  @elseif($p['status'] === 'lobby')
                    <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[10px] font-semibold">In Lobby</span>
                  @elseif($p['status'] === 'break')
                    <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-semibold">In Break</span>
                  @else
                    <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 text-[10px]">Left</span>
                  @endif
                </div>
              </li>
            @empty
              <li class="p-6 text-center text-slate-400 text-xs">
                No participants have joined this event yet.
              </li>
            @endforelse
          </ul>
        </div>
      </div>

    </div>

</div>
@endsection
