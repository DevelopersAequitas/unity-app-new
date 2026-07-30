@extends('admin.layouts.app')

@section('title', 'Upcoming Birthdays & Anniversaries')

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    corePlugins: {
      preflight: false,
    }
  }
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  #grid-root-container {
    --bg:#0A0E17; --surface:#10141F; --surface-2:#141926; --surface-3:#1A2030;
    --border:#232A3B; --border-soft:#1B2130;
    --text-1:#EEF0F5; --text-2:#9096A8; --text-3:#5C6478;
    --accent:#6366F1; --accent-2:#8B5CF6; --accent-soft:#6366F11A;
    --success:#10B981; --success-soft:#10B9811A;
    --warning:#F59E0B; --warning-soft:#F59E0B1A;
    --danger:#F43F5E; --danger-soft:#F43F5E1A;
    --info:#0EA5E9; --info-soft:#0EA5E91A;
    background-color: var(--bg);
    color: var(--text-1);
    font-family: 'Inter', sans-serif;
  }
  #grid-root-container.light {
    --bg:#F8FAFC; --surface:#FFFFFF; --surface-2:#F1F5F9; --surface-3:#E2E8F0;
    --border:#E2E8F0; --border-soft:#F1F5F9;
    --text-1:#0F172A; --text-2:#475569; --text-3:#94A3B8;
  }
  #grid-root-container .font-display { font-family: 'Lexend', sans-serif; }
  #grid-root-container .t1 { color: var(--text-1); }
  #grid-root-container .t2 { color: var(--text-2); }
  #grid-root-container .t3 { color: var(--text-3); }
  #grid-root-container .bs { border-color: var(--border-soft); }
  #grid-root-container .surface { background-color: var(--surface) !important; }
  #grid-root-container .surface-2 { background-color: var(--surface-2) !important; }
  #grid-root-container .surface-3 { background-color: var(--surface-3) !important; }

  .event-card {
    background: var(--surface);
    border: 1px solid var(--border-soft);
    border-radius: 12px;
    transition: all 0.2s ease;
  }
  .event-card:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
  }
  .avatar-img {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border-soft);
  }
  .avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366F1, #8B5CF6);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
  }
  .tab-pill {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-2);
    background: var(--surface-2);
    border: 1px solid var(--border);
    transition: all 0.2s ease;
    text-decoration: none !important;
  }
  .tab-pill.active {
    color: #ffffff !important;
    background: var(--accent) !important;
    border-color: var(--accent) !important;
  }
</style>
@endpush

<div id="grid-root-container" class="light rounded-xl border bs p-4 relative mb-4">
  
  <!-- Breadcrumb & Header Row -->
  <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
    <div>
      <div class="flex items-center gap-2 text-xs t3 mb-1">
        <a href="{{ route('admin.users.index') }}" class="t3 hover:t1 text-decoration-none">Peers</a>
        <span>/</span>
        <span class="t1 font-medium">Upcoming Birthdays &amp; Anniversaries</span>
      </div>
      <h2 class="text-lg font-bold tracking-tight t1 font-display m-0">Upcoming Birthdays &amp; Anniversaries</h2>
      <p class="text-xs t3 m-0 mt-0.5">Monitor upcoming peer celebrations, birthdays, and milestone anniversaries within your organization.</p>
    </div>
  </div>

  <!-- Summary KPI Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
    <div class="p-3 rounded-lg surface-2 border bs">
      <div class="flex items-center justify-between">
        <span class="text-xs t3 font-semibold uppercase tracking-wider">Total Upcoming Events</span>
        <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-100 text-indigo-700">Next {{ $filters['period'] }} Days</span>
      </div>
      <div class="text-2xl font-bold font-display t1 mt-1">{{ $totalUpcomingCount }}</div>
      <div class="text-[11px] t3 mt-0.5">Combined birthdays &amp; anniversaries</div>
    </div>

    <div class="p-3 rounded-lg surface-2 border bs">
      <div class="flex items-center justify-between">
        <span class="text-xs t3 font-semibold uppercase tracking-wider">Upcoming Birthdays</span>
        <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-pink-100 text-pink-700">🎂 Birthdays</span>
      </div>
      <div class="text-2xl font-bold font-display t1 mt-1">{{ $birthdaysCount }}</div>
      <div class="text-[11px] t3 mt-0.5">Peers celebrating birthday</div>
    </div>

    <div class="p-3 rounded-lg surface-2 border bs">
      <div class="flex items-center justify-between">
        <span class="text-xs t3 font-semibold uppercase tracking-wider">Upcoming Anniversaries</span>
        <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-700">💍 Anniversaries</span>
      </div>
      <div class="text-2xl font-bold font-display t1 mt-1">{{ $anniversariesCount }}</div>
      <div class="text-[11px] t3 mt-0.5">Peers celebrating wedding anniversary</div>
    </div>
  </div>

  @php
    $hasActiveFilters = !empty($filters['search']) || $filters['event_type'] !== 'all' || (int)$filters['period'] !== 30;
  @endphp

  <!-- Auto-Apply Filter Bar -->
  <form id="upcomingEventsFilterForm" method="GET" action="{{ route('admin.users.upcoming-events') }}" class="surface p-3 rounded-lg border bs mb-4 admin-filter-form">
    <input type="hidden" name="tab" value="{{ $activeTab }}">
    
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 flex-1 min-w-[280px]">
        <!-- Search Input -->
        <div>
          <label class="block text-[11px] font-semibold t3 uppercase tracking-wider mb-1">Search Peer</label>
          <div class="relative">
            <input type="text" name="q" id="searchPeerInput" value="{{ $filters['search'] }}" placeholder="Search name, email, phone, company..." class="w-full px-3 py-1.5 rounded-lg border bs surface-2 text-xs t1 focus-ring outline-none"/>
          </div>
        </div>

        <!-- Event Type Filter -->
        <div>
          <label class="block text-[11px] font-semibold t3 uppercase tracking-wider mb-1">Event Type</label>
          <select name="event_type" id="eventTypeSelect" class="w-full px-3 py-1.5 rounded-lg border bs surface-2 text-xs t1 focus-ring outline-none">
            <option value="all" {{ $filters['event_type'] === 'all' ? 'selected' : '' }}>All Events</option>
            <option value="birthday" {{ $filters['event_type'] === 'birthday' ? 'selected' : '' }}>Birthdays Only</option>
            <option value="anniversary" {{ $filters['event_type'] === 'anniversary' ? 'selected' : '' }}>Anniversaries Only</option>
          </select>
        </div>

        <!-- Period Filter -->
        <div>
          <label class="block text-[11px] font-semibold t3 uppercase tracking-wider mb-1">Upcoming Period</label>
          <select name="period" id="periodSelect" class="w-full px-3 py-1.5 rounded-lg border bs surface-2 text-xs t1 focus-ring outline-none">
            <option value="7" {{ (int)$filters['period'] === 7 ? 'selected' : '' }}>Next 7 Days</option>
            <option value="15" {{ (int)$filters['period'] === 15 ? 'selected' : '' }}>Next 15 Days</option>
            <option value="30" {{ (int)$filters['period'] === 30 ? 'selected' : '' }}>Next 30 Days</option>
            <option value="60" {{ (int)$filters['period'] === 60 ? 'selected' : '' }}>Next 60 Days</option>
            <option value="90" {{ (int)$filters['period'] === 90 ? 'selected' : '' }}>Next 90 Days</option>
          </select>
        </div>
      </div>

      @if($hasActiveFilters)
        <div class="flex items-center">
          <a href="{{ route('admin.users.upcoming-events') }}" class="px-3 py-1.5 rounded-lg border bs surface-2 text-xs t2 hover:t1 hover:surface-3 transition font-medium focus-ring no-underline flex items-center gap-1">
            <span>Clear Filters</span> ✕
          </a>
        </div>
      @endif
    </div>
  </form>

  <!-- Section Tabs -->
  <div class="flex items-center gap-2 mb-4 border-b bs pb-3">
    <a href="{{ route('admin.users.upcoming-events', array_merge(request()->except('page'), ['tab' => 'birthdays'])) }}" class="tab-pill {{ $activeTab === 'birthdays' ? 'active' : '' }}">
      🎂 Upcoming Birthdays ({{ $birthdaysCount }})
    </a>
    <a href="{{ route('admin.users.upcoming-events', array_merge(request()->except('page'), ['tab' => 'anniversaries'])) }}" class="tab-pill {{ $activeTab === 'anniversaries' ? 'active' : '' }}">
      💍 Upcoming Anniversaries ({{ $anniversariesCount }})
    </a>
  </div>

  <!-- Content List / Grid -->
  @if($paginatedRecords->isEmpty())
    <div class="text-center py-12 surface rounded-lg border bs">
      <div class="text-4xl mb-2">🎉</div>
      <h3 class="text-base font-semibold t1 mb-1">No Upcoming {{ $activeTab === 'anniversaries' ? 'Anniversaries' : 'Birthdays' }} Found</h3>
      <p class="text-xs t3 max-w-sm mx-auto">There are no peer {{ $activeTab === 'anniversaries' ? 'anniversaries' : 'birthdays' }} occurring within the selected period ({{ $filters['period'] }} days) matching your filter criteria.</p>
    </div>
  @else
    @if($activeTab === 'birthdays')
      <!-- Birthdays Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
        @foreach($paginatedRecords as $item)
          @php
            $peer = $item['peer'];
            $days = $item['days_remaining'];
          @endphp
          <div class="event-card p-4 flex flex-col justify-between h-full">
            <div class="flex flex-col flex-grow">
              <!-- Card Header -->
              <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                  @if($item['avatar_url'])
                    <img src="{{ $item['avatar_url'] }}" alt="{{ $item['full_name'] }}" class="avatar-img flex-none" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'avatar-placeholder flex-none\'>{{ strtoupper(substr($item['full_name'], 0, 1)) }}</div>';"/>
                  @else
                    <div class="avatar-placeholder flex-none">{{ strtoupper(substr($item['full_name'], 0, 1)) }}</div>
                  @endif
                  <div class="min-w-0 flex-1">
                    <h4 class="text-sm font-semibold t1 m-0 truncate" title="{{ $item['full_name'] ?: ($item['peer']->display_name ?: trim(($item['peer']->first_name ?? '').' '.($item['peer']->last_name ?? ''))) }}">
                        {{ $item['full_name'] ?: ($item['peer']->display_name ?: trim(($item['peer']->first_name ?? '').' '.($item['peer']->last_name ?? ''))) ?: ($item['peer']->email ?? 'N/A') }}
                    </h4>
                    @if($item['company_name'])
                      <div class="text-xs t2 font-medium truncate" title="{{ $item['company_name'] }}">{{ $item['company_name'] }}</div>
                    @endif
                    @if($item['city_name'])
                      <div class="text-[11px] t3 flex items-center gap-1 truncate mt-0.5" title="{{ $item['city_name'] }}">
                        <span>📍</span> <span class="truncate">{{ $item['city_name'] }}</span>
                      </div>
                    @endif
                  </div>
                </div>

                <!-- Badges Column -->
                <div class="flex flex-col items-end gap-1.5 flex-none shrink-0">
                  @if($days === 0)
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500 text-white animate-pulse">Today! 🎈</span>
                  @elseif($days === 1)
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-500 text-white">Tomorrow</span>
                  @else
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-100 text-indigo-700">In {{ $days }} days</span>
                  @endif

                  <span class="px-2 py-0.5 rounded-full text-[10.5px] font-medium bg-pink-50 text-pink-700 border border-pink-200">🎂 Birthday</span>
                </div>
              </div>

              <!-- Card Body (Label/Value list) -->
              <div class="border-t bs pt-3 mt-1 space-y-2 text-xs flex-grow">
                <div class="flex justify-between items-center py-0.5">
                  <span class="t3 font-medium text-left">Date of Birth:</span>
                  <span class="t1 font-medium text-right truncate ml-2">{{ $item['original_date_formatted'] }}</span>
                </div>
                <div class="flex justify-between items-center py-0.5">
                  <span class="t3 font-medium text-left">Upcoming Birthday:</span>
                  <span class="t1 font-semibold text-indigo-600 text-right truncate ml-2">{{ $item['upcoming_date_formatted'] }}</span>
                </div>
                @if(!empty($item['turning_age']) || !empty($item['years_completed']))
                  <div class="flex justify-between items-center py-0.5">
                    <span class="t3 font-medium text-left">Completing Age:</span>
                    <span class="t1 font-bold text-amber-600 text-right truncate ml-2">{{ $item['turning_age'] ?? $item['years_completed'] }} Years</span>
                  </div>
                @endif
              </div>
            </div>

            <!-- Card Footer -->
            <div class="mt-auto pt-3 border-t bs flex justify-end items-center">
              <a href="{{ route('admin.users.show', $peer->id) }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-medium t2 hover:t1 hover:surface-2 transition text-decoration-none flex items-center gap-1">
                <span>View Peer</span> ➔
              </a>
            </div>
          </div>
        @endforeach
      </div>
    @else
      <!-- Anniversaries Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
        @foreach($paginatedRecords as $item)
          @php
            $peer = $item['peer'];
            $days = $item['days_remaining'];
          @endphp
          <div class="event-card p-4 flex flex-col justify-between h-full">
            <div class="flex flex-col flex-grow">
              <!-- Card Header -->
              <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                  @if($item['avatar_url'])
                    <img src="{{ $item['avatar_url'] }}" alt="{{ $item['full_name'] }}" class="avatar-img flex-none" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'avatar-placeholder flex-none\'>{{ strtoupper(substr($item['full_name'], 0, 1)) }}</div>';"/>
                  @else
                    <div class="avatar-placeholder flex-none">{{ strtoupper(substr($item['full_name'], 0, 1)) }}</div>
                  @endif
                  <div class="min-w-0 flex-1">
                    <h4 class="text-sm font-semibold t1 m-0 truncate" title="{{ $item['full_name'] ?: ($item['peer']->display_name ?: trim(($item['peer']->first_name ?? '').' '.($item['peer']->last_name ?? ''))) }}">
                        {{ $item['full_name'] ?: ($item['peer']->display_name ?: trim(($item['peer']->first_name ?? '').' '.($item['peer']->last_name ?? ''))) ?: ($item['peer']->email ?? 'N/A') }}
                    </h4>
                    @if($item['company_name'])
                      <div class="text-xs t2 font-medium truncate" title="{{ $item['company_name'] }}">{{ $item['company_name'] }}</div>
                    @endif
                    @if($item['city_name'])
                      <div class="text-[11px] t3 flex items-center gap-1 truncate mt-0.5" title="{{ $item['city_name'] }}">
                        <span>📍</span> <span class="truncate">{{ $item['city_name'] }}</span>
                      </div>
                    @endif
                  </div>
                </div>

                <!-- Badges Column -->
                <div class="flex flex-col items-end gap-1.5 flex-none shrink-0">
                  @if($days === 0)
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500 text-white animate-pulse">Today! 💐</span>
                  @elseif($days === 1)
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-500 text-white">Tomorrow</span>
                  @else
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-purple-100 text-purple-700">In {{ $days }} days</span>
                  @endif

                  <span class="px-2 py-0.5 rounded-full text-[10.5px] font-medium bg-amber-50 text-amber-700 border border-amber-200">💍 Anniversary</span>
                </div>
              </div>

              <!-- Card Body (Label/Value list) -->
              <div class="border-t bs pt-3 mt-1 space-y-2 text-xs flex-grow">
                <div class="flex justify-between items-center py-0.5">
                  <span class="t3 font-medium text-left">Anniversary Type:</span>
                  <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-amber-50 text-amber-700 border border-amber-200 text-right truncate ml-2">{{ $item['anniversary_label'] }}</span>
                </div>
                <div class="flex justify-between items-center py-0.5">
                  <span class="t3 font-medium text-left">Original Date:</span>
                  <span class="t1 font-medium text-right truncate ml-2">{{ $item['original_date_formatted'] }}</span>
                </div>
                <div class="flex justify-between items-center py-0.5">
                  <span class="t3 font-medium text-left">Upcoming Anniversary:</span>
                  <span class="t1 font-semibold text-purple-600 text-right truncate ml-2">{{ $item['upcoming_date_formatted'] }}</span>
                </div>
                @if($item['completed_years'])
                  <div class="flex justify-between items-center py-0.5">
                    <span class="t3 font-medium text-left">Completed Years:</span>
                    <span class="t1 font-bold text-emerald-600 text-right truncate ml-2">{{ $item['completed_years'] }} Years</span>
                  </div>
                @endif
              </div>
            </div>

            <!-- Card Footer -->
            <div class="mt-auto pt-3 border-t bs flex justify-end items-center">
              <a href="{{ route('admin.users.show', $peer->id) }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-medium t2 hover:t1 hover:surface-2 transition text-decoration-none flex items-center gap-1">
                <span>View Peer</span> ➔
              </a>
            </div>
          </div>
        @endforeach
      </div>
    @endif

    <!-- Pagination -->
    <div class="mt-4 flex flex-wrap justify-between items-center gap-3">
      <div class="text-xs t3">
        Showing {{ $paginatedRecords->firstItem() ?? 0 }} to {{ $paginatedRecords->lastItem() ?? 0 }} of {{ $paginatedRecords->total() }} entries
      </div>
      <div>
        {{ $paginatedRecords->appends(request()->query())->links() }}
      </div>
    </div>
  @endif

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('upcomingEventsFilterForm');
    if (!form) return;

    const searchInput = document.getElementById('searchPeerInput');
    const eventTypeSelect = document.getElementById('eventTypeSelect');
    const periodSelect = document.getElementById('periodSelect');

    let debounceTimer = null;

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                form.submit();
            }, 450);
        });
    }

    if (eventTypeSelect) {
        eventTypeSelect.addEventListener('change', function () {
            form.submit();
        });
    }

    if (periodSelect) {
        periodSelect.addEventListener('change', function () {
            form.submit();
        });
    }
});
</script>
@endpush

@endsection
