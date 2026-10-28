@extends('admin.layouts.app')

@section('title', $event->title . ' - Live Speed Networking')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 md:p-6 relative admin-grid-card space-y-6 max-w-full min-w-0 bg-slate-50/50">

    @if(session('success'))
      <div class="px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-2">
          <i class="bi bi-check-circle-fill text-emerald-600 text-sm"></i>
          <span>{{ session('success') }}</span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 border-0 bg-transparent cursor-pointer text-sm font-bold">&times;</button>
      </div>
    @endif

    @if(session('error'))
      <div class="px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-2">
          <i class="bi bi-exclamation-triangle-fill text-rose-600 text-sm"></i>
          <span>{{ session('error') }}</span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-900 border-0 bg-transparent cursor-pointer text-sm font-bold">&times;</button>
      </div>
    @endif

    <!-- 1. Top Bar / Session Header -->
    <div class="flex flex-wrap justify-between items-center gap-4">
      <div class="flex items-center gap-3">
        <a href="{{ route('admin.speed-networking.index') }}" class="px-3.5 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 text-xs font-semibold flex items-center gap-1.5 transition no-underline shadow-2xs">
          <i class="bi bi-arrow-left"></i> All Sessions
        </a>
        <div>
          <div class="flex items-center gap-2.5 flex-wrap">
            <h1 class="text-xl md:text-2xl font-black text-slate-900 m-0 tracking-tight">
              {{ $event->title }}
            </h1>
            @if(!empty($event->is_paid) && (float) ($event->ticket_price ?? 0) > 0)
              <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                ₹{{ number_format((float) $event->ticket_price, 2) }}
              </span>
            @else
              <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                Free Event
              </span>
            @endif
          </div>
          <p class="text-xs text-slate-500 m-0 mt-0.5">Live real-time table assignments, pairings ledger, and round orchestration.</p>
        </div>
      </div>

      <!-- Action Pills on Right -->
      <div class="flex items-center gap-2">
        <button type="button" onclick="toggleRosterDrawer()" class="px-3.5 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold flex items-center gap-2 cursor-pointer shadow-2xs transition">
          <i class="bi bi-people text-slate-500"></i> Participant Roster ({{ count($participants) }})
        </button>

        <button type="button" onclick="window.location.reload()" class="px-3.5 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold flex items-center gap-2 cursor-pointer shadow-2xs transition">
          <i class="bi bi-arrow-clockwise text-slate-500"></i> Refresh Tables
        </button>

        <a href="http://localhost:5173/speed-networking/live?eventId={{ $event->id }}" target="_blank" class="px-3.5 py-2 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold border border-indigo-200 no-underline flex items-center gap-1.5 shadow-2xs transition" title="Open in React SPA Controller">
          <i class="bi bi-box-arrow-up-right"></i> Open SPA
        </a>
      </div>
    </div>

    <!-- Metadata Badges Strip -->
    <div class="p-3 rounded-xl border border-slate-200 bg-white shadow-2xs flex flex-wrap items-center justify-between gap-3 text-xs text-slate-600">
      <div class="flex items-center gap-4 flex-wrap">
        <div class="flex items-center gap-1.5">
          <i class="bi bi-calendar-check text-indigo-500"></i>
          <span class="font-semibold text-slate-700">Schedule:</span>
          <span>
            @if(!empty($event->scheduled_start_time))
              {{ is_string($event->scheduled_start_time) ? \Carbon\Carbon::parse($event->scheduled_start_time)->format('M d, Y h:i A') : $event->scheduled_start_time->format('M d, Y h:i A') }}
              @if(!empty($event->scheduled_end_time))
                to {{ is_string($event->scheduled_end_time) ? \Carbon\Carbon::parse($event->scheduled_end_time)->format('h:i A') : $event->scheduled_end_time->format('h:i A') }}
              @endif
            @else
              Manual Start / Open Schedule
            @endif
          </span>
        </div>

        <div class="flex items-center gap-1.5">
          <i class="bi bi-shield-check text-indigo-500"></i>
          <span class="font-semibold text-slate-700">Access:</span>
          <span>{{ !empty($event->circle_restricted) ? 'Circle Restricted' : 'Open to All Members' }}</span>
        </div>

        <div class="flex items-center gap-1.5">
          <i class="bi bi-stopwatch text-indigo-500"></i>
          <span class="font-semibold text-slate-700">Rounds:</span>
          <span>{{ $event->total_rounds ?? 5 }} Total &bull; {{ ceil(($event->round_duration_seconds ?? 180) / 60) }}m / round &bull; {{ $event->break_duration_seconds ?? 30 }}s break</span>
        </div>
      </div>

      <div class="text-[11px] text-slate-400 font-mono">
        Event ID: {{ $event->id }}
      </div>
    </div>

    <!-- 2. Hero Controller & Circular Timer Card -->
    <div class="p-6 rounded-2xl border border-slate-200 bg-white shadow-xs flex flex-wrap items-center justify-between gap-6">
      
      <!-- Left: Timer + Round Progress -->
      <div class="flex items-center gap-6 flex-wrap md:flex-nowrap">
        <!-- Circular Progress Timer -->
        <div class="relative w-28 h-28 flex items-center justify-center shrink-0">
          <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
            <!-- Background circle track -->
            <circle cx="50" cy="50" r="42" stroke="#E2E8F0" stroke-width="6" fill="transparent" />
            <!-- Animated Progress circle -->
            <circle id="timer-ring" cx="50" cy="50" r="42" stroke="{{ $event->status === 'paused' ? '#F59E0B' : '#3B82F6' }}" stroke-width="6" stroke-linecap="round" fill="transparent" stroke-dasharray="263.89" stroke-dashoffset="0" class="transition-all duration-1000 ease-linear" />
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
            <i class="bi {{ $event->status === 'paused' ? 'bi-pause-circle text-amber-500' : 'bi-clock text-blue-500' }} text-xs mb-0.5"></i>
            <span id="timer-display" class="text-xl font-black font-mono {{ $event->status === 'paused' ? 'text-amber-600' : 'text-blue-600' }} tracking-tight leading-none">
              {{ sprintf('%02d:%02d', floor($remainingSeconds / 60), $remainingSeconds % 60) }}
            </span>
            <span class="text-[9px] uppercase tracking-wider font-bold text-slate-400 mt-1">
              {{ $event->status === 'paused' ? 'PAUSED' : 'REMAINING' }}
            </span>
          </div>
        </div>

        <!-- Middle: Status Badges & Round Progress -->
        <div class="space-y-2">
          <div class="flex items-center gap-2.5">
            @if($event->status === 'in_progress')
              <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 text-xs font-black tracking-wide uppercase flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> ROUND {{ $event->current_round }} LIVE
              </span>
            @elseif($event->status === 'paused')
              <span class="px-3 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-black tracking-wide uppercase flex items-center gap-1.5">
                <i class="bi bi-pause-circle-fill text-amber-600"></i> SESSION PAUSED
              </span>
            @elseif($event->status === 'completed')
              <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-black tracking-wide uppercase">
                SESSION COMPLETED
              </span>
            @else
              <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-800 text-xs font-black tracking-wide uppercase">
                LOBBY OPEN
              </span>
            @endif

            <span class="text-sm font-bold text-slate-800">
              @if(!empty($event->total_rounds) && (int) $event->total_rounds > 0)
                Round {{ max(1, $event->current_round) }} of {{ $event->total_rounds }}
              @else
                Round {{ max(1, $event->current_round) }}
              @endif
            </span>
          </div>

          <!-- Progress Bar -->
          @if(!empty($event->total_rounds) && (int) $event->total_rounds > 0)
            <div class="w-64 md:w-80 h-2 bg-slate-100 rounded-full overflow-hidden">
              @php
                $roundPct = min(100, max(5, ($event->current_round / max(1, (int) $event->total_rounds)) * 100));
              @endphp
              <div class="h-full {{ $event->status === 'paused' ? 'bg-amber-500' : 'bg-blue-500' }} rounded-full transition-all duration-500" style="width: {{ $roundPct }}%"></div>
            </div>
          @endif

          <div class="text-xs text-slate-500 font-medium">
            Round duration: {{ ceil(($event->round_duration_seconds ?? 180) / 60) }} min &bull; Break: {{ $event->break_duration_seconds ?? 30 }}s
          </div>
        </div>
      </div>

      <!-- Right: Primary Controls -->
      <div class="flex items-center gap-2 flex-wrap">
        @if($event->status !== 'in_progress' && $event->status !== 'paused' && $event->status !== 'completed')
          <form method="POST" action="{{ route('admin.speed-networking.start', $event->id) }}" onsubmit="return confirm('Start this live session and generate table pairings?')" class="m-0">
            @csrf
            <button type="submit" class="px-6 py-3 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-sm flex items-center gap-2 cursor-pointer border-0 shadow-md transition">
              <i class="bi bi-play-fill text-xl"></i> Start Live Session
            </button>
          </form>
        @endif

        @if($event->status === 'in_progress')
          <!-- Pause Live Session -->
          <form method="POST" action="{{ route('admin.speed-networking.pause', $event->id) }}" onsubmit="return confirm('Pause this live speed networking session?')" class="m-0">
            @csrf
            <button type="submit" class="px-4 py-2.5 rounded-xl border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 font-semibold text-xs flex items-center gap-1.5 cursor-pointer shadow-2xs transition">
              <i class="bi bi-pause-fill"></i> Pause Session
            </button>
          </form>

          <!-- Rematch Current Round -->
          <form method="POST" action="{{ route('admin.speed-networking.rematch', $event->id) }}" onsubmit="return confirm('Shuffle and re-match all online participants for the current round?')" class="m-0">
            @csrf
            <button type="submit" class="px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs flex items-center gap-1.5 cursor-pointer shadow-2xs transition" title="Re-shuffle pairings for current round">
              <i class="bi bi-shuffle text-indigo-600"></i> Rematch
            </button>
          </form>

          <!-- Advance Next Round -->
          <form method="POST" action="{{ route('admin.speed-networking.next-round', $event->id) }}" onsubmit="return confirm('Advance to the next round now?')" class="m-0">
            @csrf
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center gap-2 cursor-pointer border-0 shadow-2xs transition">
              <i class="bi bi-fast-forward-fill"></i> Next Round
            </button>
          </form>

          <!-- End Session -->
          <form method="POST" action="{{ route('admin.speed-networking.end', $event->id) }}" onsubmit="return confirm('End this live networking session completely?')" class="m-0">
            @csrf
            <button type="submit" class="px-4 py-2.5 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold text-xs flex items-center gap-1.5 cursor-pointer transition">
              <i class="bi bi-stop-circle"></i> End Session
            </button>
          </form>
        @endif

        @if($event->status === 'paused')
          <!-- Resume Session -->
          <form method="POST" action="{{ route('admin.speed-networking.resume', $event->id) }}" onsubmit="return confirm('Resume this live networking session?')" class="m-0">
            @csrf
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-2 cursor-pointer border-0 shadow-2xs transition">
              <i class="bi bi-play-fill text-base"></i> Resume Session
            </button>
          </form>

          <!-- Rematch Current Round -->
          <form method="POST" action="{{ route('admin.speed-networking.rematch', $event->id) }}" onsubmit="return confirm('Shuffle and re-match online participants?')" class="m-0">
            @csrf
            <button type="submit" class="px-4 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs flex items-center gap-1.5 cursor-pointer shadow-2xs transition">
              <i class="bi bi-shuffle text-indigo-600"></i> Rematch
            </button>
          </form>

          <!-- Advance Next Round -->
          <form method="POST" action="{{ route('admin.speed-networking.next-round', $event->id) }}" onsubmit="return confirm('Advance to the next round now?')" class="m-0">
            @csrf
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center gap-2 cursor-pointer border-0 shadow-2xs transition">
              <i class="bi bi-fast-forward-fill"></i> Next Round
            </button>
          </form>

          <!-- End Session -->
          <form method="POST" action="{{ route('admin.speed-networking.end', $event->id) }}" onsubmit="return confirm('End this live networking session completely?')" class="m-0">
            @csrf
            <button type="submit" class="px-4 py-2.5 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold text-xs flex items-center gap-1.5 cursor-pointer transition">
              <i class="bi bi-stop-circle"></i> End Session
            </button>
          </form>
        @endif
      </div>
    </div>

    <!-- 3. Session End / Connects Summary Statistics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="p-4 rounded-xl border border-slate-200 bg-white shadow-2xs">
        <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
          <span>Total Joined</span>
          <i class="bi bi-people-fill text-indigo-600"></i>
        </div>
        <div class="text-2xl font-black text-slate-900">{{ count($participants) }}</div>
        <div class="text-[11px] text-slate-400 mt-0.5">Unique attendees registered</div>
      </div>

      <div class="p-4 rounded-xl border border-slate-200 bg-white shadow-2xs">
        <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
          <span>Unique Pairs Connected</span>
          <i class="bi bi-person-check-fill text-emerald-600"></i>
        </div>
        <div class="text-2xl font-black text-emerald-600">{{ $matchesData['summary']['unique_pairs_count'] ?? 0 }}</div>
        <div class="text-[11px] text-slate-400 mt-0.5">Distinct 1-on-1 pairings</div>
      </div>

      <div class="p-4 rounded-xl border border-slate-200 bg-white shadow-2xs">
        <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
          <span>Total Matches Formed</span>
          <i class="bi bi-lightning-charge-fill text-amber-500"></i>
        </div>
        <div class="text-2xl font-black text-amber-600">{{ $matchesData['summary']['total_matches'] ?? 0 }}</div>
        <div class="text-[11px] text-slate-400 mt-0.5">Across all session rounds</div>
      </div>

      <div class="p-4 rounded-xl border border-slate-200 bg-white shadow-2xs">
        <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
          <span>Completed Calls</span>
          <i class="bi bi-telephone-fill text-blue-600"></i>
        </div>
        <div class="text-2xl font-black text-blue-600">{{ $matchesData['summary']['completed_matches'] ?? 0 }}</div>
        <div class="text-[11px] text-slate-400 mt-0.5">Successful video meetups</div>
      </div>
    </div>

    <!-- 4. Active Virtual Tables Section -->
    <div class="space-y-3">
      <div class="flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900 m-0 flex items-center gap-2">
          <i class="bi bi-grid-fill text-indigo-600"></i>
          Active Virtual Tables ({{ count($liveData['tables']) }})
        </h2>
        <span class="text-xs text-slate-500">
          Automated pair rotation via Peer circle & industry matching algorithm
        </span>
      </div>

      @if(empty($liveData['tables']))
        <div class="p-12 rounded-2xl border border-dashed border-slate-300 bg-white text-center text-slate-400">
          <i class="bi bi-people text-4xl text-slate-300 block mb-3"></i>
          <p class="text-sm font-semibold text-slate-600 m-0">No active tables right now</p>
          <p class="text-xs text-slate-400 m-0 mt-1">Participants are waiting in the lobby or round is not yet active. Click "Start Live Session" to pair attendees into 1-on-1 virtual tables.</p>
        </div>
      @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          @foreach($liveData['tables'] as $table)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs space-y-4 hover:shadow-md transition">
              
              <!-- Card Header -->
              <div class="flex items-center justify-between">
                <span class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-xs font-bold">
                  #{{ $table['table_number'] }} Table {{ $table['table_number'] }}
                </span>
                
                <span class="text-xs font-semibold flex items-center gap-1.5 {{ $table['status'] === 'active' ? 'text-emerald-600' : 'text-slate-400' }}">
                  <span class="w-2 h-2 rounded-full {{ $table['status'] === 'active' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                  {{ $table['status'] === 'active' ? 'IN CALL' : 'WAITING' }}
                </span>
              </div>

              <!-- Matchup Visualizer (User A vs User B) -->
              <div class="flex items-center justify-between gap-3 pt-1">
                <!-- User A -->
                <div class="flex-1 text-center">
                  <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 border-2 border-indigo-100 overflow-hidden flex items-center justify-center font-bold text-slate-700 text-sm mb-2 shadow-2xs">
                    @if(!empty($table['user_a']['avatar_url']))
                      <img src="{{ $table['user_a']['avatar_url'] }}" alt="{{ $table['user_a']['name'] ?? 'User' }}" class="w-full h-full object-cover">
                    @else
                      {{ strtoupper(substr($table['user_a']['name'] ?? 'A', 0, 1)) }}
                    @endif
                  </div>
                  <div class="font-bold text-slate-900 text-xs truncate max-w-[130px] mx-auto" title="{{ $table['user_a']['name'] ?? 'Peer' }}">
                    {{ $table['user_a']['name'] ?? 'Peer' }}
                  </div>
                  <div class="text-[11px] text-slate-400 truncate max-w-[130px] mx-auto" title="{{ $table['user_a']['company'] ?? '' }}">
                    {{ $table['user_a']['company'] ?? '' }}
                  </div>
                </div>

                <!-- VS Pill -->
                <div class="shrink-0 px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 font-bold text-[10px] tracking-wider uppercase">
                  vs
                </div>

                <!-- User B -->
                <div class="flex-1 text-center">
                  @if(!empty($table['user_b']))
                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 border-2 border-indigo-100 overflow-hidden flex items-center justify-center font-bold text-slate-700 text-sm mb-2 shadow-2xs">
                      @if(!empty($table['user_b']['avatar_url']))
                        <img src="{{ $table['user_b']['avatar_url'] }}" alt="{{ $table['user_b']['name'] ?? 'User' }}" class="w-full h-full object-cover">
                      @else
                        {{ strtoupper(substr($table['user_b']['name'] ?? 'B', 0, 1)) }}
                      @endif
                    </div>
                    <div class="font-bold text-slate-900 text-xs truncate max-w-[130px] mx-auto" title="{{ $table['user_b']['name'] ?? 'Peer' }}">
                      {{ $table['user_b']['name'] ?? 'Peer' }}
                    </div>
                    <div class="text-[11px] text-slate-400 truncate max-w-[130px] mx-auto" title="{{ $table['user_b']['company'] ?? '' }}">
                      {{ $table['user_b']['company'] ?? '' }}
                    </div>
                  @else
                    <div class="w-12 h-12 mx-auto rounded-full bg-amber-50 border-2 border-amber-200 flex items-center justify-center text-amber-600 text-base mb-2">
                      <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="font-bold text-amber-700 text-xs truncate">Waiting / Bye</div>
                    <div class="text-[10px] text-slate-400">Next round priority</div>
                  @endif
                </div>
              </div>

              <!-- Card Footer: Room ID & Encryption Badge -->
              <div class="border-t border-slate-100 pt-3 flex items-center justify-between text-xs">
                <span class="font-mono text-[10px] text-slate-400 truncate max-w-[180px]" title="{{ $table['channel_id'] }}">
                  {{ $table['channel_id'] }}
                </span>
                <span class="text-[11px] text-slate-500 flex items-center gap-1 shrink-0 font-medium">
                  <i class="bi bi-camera-video text-indigo-500 text-xs"></i> Agora / LiveKit Channel
                </span>
              </div>

            </div>
          @endforeach
        </div>
      @endif
    </div>

    <!-- 5. Connects & Pairing History Ledger (All Rounds) -->
    <div class="space-y-3">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base font-bold text-slate-900 m-0 flex items-center gap-2">
            <i class="bi bi-clock-history text-indigo-600"></i>
            Connects & Pairing History ({{ count($matchesData['data'] ?? []) }})
          </h2>
          <p class="text-xs text-slate-500 m-0 mt-0.5">Historical ledger of all 1-on-1 pairings, meeting durations, and connection outcomes.</p>
        </div>
      </div>

      <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-xs">
        <table class="w-full text-left text-xs text-slate-600">
          <thead class="bg-slate-100 text-slate-700 uppercase font-semibold text-[11px] tracking-wider border-b border-slate-200">
            <tr>
              <th class="p-3">Round</th>
              <th class="p-3">Peer A</th>
              <th class="p-3 text-center">Matchup</th>
              <th class="p-3">Peer B</th>
              <th class="p-3">Duration</th>
              <th class="p-3">Status</th>
              <th class="p-3">Channel / Timestamps</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            @forelse($matchesData['data'] ?? [] as $match)
              <tr class="hover:bg-slate-50/80 transition">
                <td class="p-3 font-bold text-slate-800">
                  <span class="px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-700 font-semibold border border-indigo-100 text-[11px]">
                    Round {{ $match['round_number'] ?? 1 }}
                  </span>
                </td>
                
                <!-- Peer A -->
                <td class="p-3">
                  <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-full bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center font-bold text-slate-700 text-xs shrink-0">
                      @if(!empty($match['user_a']['avatar_url']))
                        <img src="{{ $match['user_a']['avatar_url'] }}" alt="{{ $match['user_a']['name'] ?? 'User' }}" class="w-full h-full object-cover">
                      @else
                        {{ strtoupper(substr($match['user_a']['name'] ?? 'A', 0, 1)) }}
                      @endif
                    </div>
                    <div>
                      <div class="font-bold text-slate-800 text-xs">{{ $match['user_a']['name'] ?? 'Unknown' }}</div>
                      <div class="text-[11px] text-slate-400 truncate max-w-[150px]">{{ $match['user_a']['company'] ?? '' }}</div>
                    </div>
                  </div>
                </td>

                <!-- Arrow -->
                <td class="p-3 text-center text-slate-400">
                  <i class="bi bi-arrow-left-right text-xs"></i>
                </td>

                <!-- Peer B -->
                <td class="p-3">
                  @if(!empty($match['user_b']))
                    <div class="flex items-center gap-2.5">
                      <div class="w-8 h-8 rounded-full bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center font-bold text-slate-700 text-xs shrink-0">
                        @if(!empty($match['user_b']['avatar_url']))
                          <img src="{{ $match['user_b']['avatar_url'] }}" alt="{{ $match['user_b']['name'] ?? 'User' }}" class="w-full h-full object-cover">
                        @else
                          {{ strtoupper(substr($match['user_b']['name'] ?? 'B', 0, 1)) }}
                        @endif
                      </div>
                      <div>
                        <div class="font-bold text-slate-800 text-xs">{{ $match['user_b']['name'] ?? 'Unknown' }}</div>
                        <div class="text-[11px] text-slate-400 truncate max-w-[150px]">{{ $match['user_b']['company'] ?? '' }}</div>
                      </div>
                    </div>
                  @else
                    <span class="text-amber-600 font-semibold text-xs italic">Waiting / Bye</span>
                  @endif
                </td>

                <!-- Duration -->
                <td class="p-3 font-medium text-slate-700">
                  @php
                    $dur = (int) ($match['duration_seconds'] ?? 0);
                  @endphp
                  @if($dur > 0)
                    {{ sprintf('%02dm %02ds', floor($dur / 60), $dur % 60) }}
                  @else
                    <span class="text-slate-400 text-xs">&mdash;</span>
                  @endif
                </td>

                <!-- Status -->
                <td class="p-3">
                  @if(($match['status'] ?? '') === 'completed')
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Completed</span>
                  @elseif(($match['status'] ?? '') === 'active')
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800 animate-pulse">In Call</span>
                  @elseif(($match['status'] ?? '') === 'missed')
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">Missed</span>
                  @else
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">{{ ucfirst($match['status'] ?? 'pending') }}</span>
                  @endif
                </td>

                <!-- Channel / Timestamps -->
                <td class="p-3 text-[11px] text-slate-500">
                  <div class="font-mono text-[10px] text-slate-400 truncate max-w-[180px]" title="{{ $match['call_channel_id'] ?? '' }}">
                    {{ $match['call_channel_id'] ?? 'N/A' }}
                  </div>
                  @if(!empty($match['created_at']))
                    <div class="text-[10px] text-slate-400 mt-0.5">
                      {{ is_string($match['created_at']) ? \Carbon\Carbon::parse($match['created_at'])->format('h:i:s A') : $match['created_at']->format('h:i:s A') }}
                    </div>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="p-8 text-center text-slate-400 text-xs">
                  <i class="bi bi-chat-square-dots text-3xl text-slate-300 block mb-2"></i>
                  No speed networking match pairings recorded yet.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

</div>

<!-- 6. Participant Roster Slide-Over Drawer -->
<div id="rosterDrawerBackdrop" onclick="toggleRosterDrawer()" class="hidden fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-xs transition-opacity"></div>
<div id="rosterDrawer" class="fixed top-0 right-0 z-50 h-full w-full max-w-md bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
  <!-- Drawer Header -->
  <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
    <div>
      <h3 class="text-sm font-bold text-slate-900 m-0 flex items-center gap-2">
        <i class="bi bi-people-fill text-indigo-600"></i> Participant Roster ({{ count($participants) }})
      </h3>
      <p class="text-[11px] text-slate-500 m-0 mt-0.5">Live heartbeat and table assignment status</p>
    </div>
    <button type="button" onclick="toggleRosterDrawer()" class="text-slate-400 hover:text-slate-700 border-0 bg-transparent text-xl cursor-pointer p-1">&times;</button>
  </div>

  <!-- Drawer Body: Participant List -->
  <div class="flex-1 overflow-y-auto p-4 space-y-2">
    @forelse($participants as $p)
      <div class="p-3 rounded-xl border border-slate-200 bg-white flex items-center justify-between hover:bg-slate-50 transition shadow-2xs">
        <div class="flex items-center gap-3">
          <div class="relative shrink-0">
            <div class="w-10 h-10 rounded-full bg-slate-100 overflow-hidden flex items-center justify-center font-bold text-slate-700 text-xs border border-slate-200">
              @if(!empty($p['avatar_url']))
                <img src="{{ $p['avatar_url'] }}" alt="{{ $p['name'] }}" class="w-full h-full object-cover">
              @else
                {{ strtoupper(substr($p['name'] ?? 'P', 0, 1)) }}
              @endif
            </div>
            @if(!empty($p['is_online']))
              <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-500 border-2 border-white" title="Active"></span>
            @endif
          </div>
          <div>
            <div class="font-bold text-slate-800 text-xs">{{ $p['name'] ?? 'Participant' }}</div>
            <div class="text-[11px] text-slate-400 truncate max-w-[180px]">
              {{ $p['company'] ?? 'Peers Member' }} {{ !empty($p['city']) ? '• ' . $p['city'] : '' }}
            </div>
          </div>
        </div>

        <div>
          @if(($p['status'] ?? '') === 'in_call')
            <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">In Call</span>
          @elseif(($p['status'] ?? '') === 'break')
            <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold">Break</span>
          @else
            <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[10px] font-bold">In Lobby</span>
          @endif
        </div>
      </div>
    @empty
      <div class="p-8 text-center text-slate-400 text-xs">
        No participants have joined this event yet.
      </div>
    @endforelse
  </div>
</div>

<script>
  // Real-Time Countdown Timer & Progress Circle
  let remainingSeconds = {{ (int) $remainingSeconds }};
  const totalRoundSeconds = {{ (int) ($event->round_duration_seconds ?? 180) }};
  let isTimerPaused = {{ $event->status === 'paused' ? 'true' : 'false' }};
  const timerRing = document.getElementById('timer-ring');
  const timerDisplay = document.getElementById('timer-display');
  const circumference = 2 * Math.PI * 42; // ~263.89

  function updateTimerRing() {
    if (!timerRing || !timerDisplay) return;
    const minutes = Math.floor(remainingSeconds / 60);
    const seconds = remainingSeconds % 60;
    timerDisplay.innerText = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

    if (totalRoundSeconds > 0) {
      const fraction = remainingSeconds / totalRoundSeconds;
      const offset = circumference * (1 - fraction);
      timerRing.style.strokeDashoffset = offset;
    }
  }

  updateTimerRing();

  @if($event->status === 'in_progress')
    const timerInterval = setInterval(function() {
      if (isTimerPaused) return;
      if (remainingSeconds > 0) {
        remainingSeconds--;
        updateTimerRing();
      } else {
        clearInterval(timerInterval);
        timerDisplay.innerText = '00:00';
        if (timerRing) timerRing.style.stroke = '#EF4444'; // Red on expiry
      }
    }, 1000);
  @endif

  function toggleRosterDrawer() {
    const drawer = document.getElementById('rosterDrawer');
    const backdrop = document.getElementById('rosterDrawerBackdrop');
    if (!drawer || !backdrop) return;
    const isHidden = drawer.classList.contains('translate-x-full');
    if (isHidden) {
      drawer.classList.remove('translate-x-full');
      backdrop.classList.remove('hidden');
    } else {
      drawer.classList.add('translate-x-full');
      backdrop.classList.add('hidden');
    }
  }
</script>
@endsection
