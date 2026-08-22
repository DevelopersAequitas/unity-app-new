@extends('admin.layouts.app')

@section('title', 'Peers')

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif


<form id="exportCsvForm" method="POST" action="{{ route('admin.users.export.csv') }}" class="d-none">
    @csrf
    <input type="hidden" name="q" value="{{ $filters['search'] ?? '' }}">
    <input type="hidden" name="membership_status" value="{{ $filters['membership_status'] ?? '' }}">
    <input type="hidden" name="circle_id" value="{{ $filters['circle_id'] ?? 'all' }}">
    <input type="hidden" name="joined_filter" value="{{ $filters['joined_filter'] ?? '' }}">
    <input type="hidden" name="approve_filter" value="{{ $filters['approve_filter'] ?? 'all' }}">
    <input type="hidden" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
    <input type="hidden" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
    <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'created_at' }}">
    <input type="hidden" name="dir" value="{{ $filters['dir'] ?? 'desc' }}">
</form>

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
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
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
  #grid-root-container .font-mono { font-family: 'JetBrains Mono', monospace; }
  #grid-root-container .t1 { color: var(--text-1); }
  #grid-root-container .t2 { color: var(--text-2); }
  #grid-root-container .t3 { color: var(--text-3); }
  #grid-root-container .bg-accent, .bg-accent { background-color: var(--accent) !important; }
  #grid-root-container .text-accent, .text-accent { color: var(--accent) !important; }
  #grid-root-container .accent, .accent { color: var(--accent) !important; }
  #grid-root-container .surface { background-color: var(--surface) !important; }
  #grid-root-container .surface-2 { background-color: var(--surface-2) !important; }
  #grid-root-container .surface-3 { background-color: var(--surface-3) !important; }
  #grid-root-container .border { border-color: var(--border); }
  #grid-root-container .bs { border-color: var(--border-soft); }
  
  #grid-root-container table { border-color: var(--border-soft) !important; }
  #grid-root-container th { border-color: var(--border-soft) !important; }
  #grid-root-container td { border-color: var(--border-soft) !important; }
  
  #grid-root-container input[type="text"], 
  #grid-root-container input[type="email"], 
  #grid-root-container input[type="date"], 
  #grid-root-container select, 
  #grid-root-container textarea {
    background-color: var(--surface-2) !important;
    border-color: var(--border) !important;
    color: var(--text-1) !important;
  }
  
  .scrim { backdrop-filter: blur(4px); transition: all 0.3s ease; }
  .drawer { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
  .drawer-hidden { transform: translateX(100%); }

  #drawer, #edit-modal .surface {
    --bg:#F8FAFC; --surface:#FFFFFF; --surface-2:#F1F5F9; --surface-3:#E2E8F0;
    --border:#E2E8F0; --border-soft:#F1F5F9;
    --text-1:#000000; --text-2:#1E293B; --text-3:#475569;
    background-color: var(--surface) !important;
    color: var(--text-1) !important;
    opacity: 1 !important;
  }
  
  #grid-root-container.dark ~ #drawer,
  #grid-root-container.dark ~ #edit-modal .surface,
  #grid-root-container.dark ~ #drawer-scrim ~ #drawer,
  #grid-root-container.dark ~ form ~ #approveMembershipDatesModal .modal-content {
    --bg:#0A0E17; --surface:#10141F; --surface-2:#141926; --surface-3:#1A2030;
    --border:#232A3B; --border-soft:#1B2130;
    --text-1:#EEF0F5; --text-2:#9096A8; --text-3:#5C6478;
    background-color: var(--surface) !important;
    color: var(--text-1) !important;
  }

  #drawer-body .surface-2, #edit-modal .surface-2 {
    background-color: var(--surface-2) !important;
  }

  #drawer-body .t1, #edit-modal .t1 { color: var(--text-1) !important; }
  #drawer-body .t2, #edit-modal .t2 { color: var(--text-2) !important; }
  #drawer-body .t3, #edit-modal .t3 { color: var(--text-3) !important; }
  
  #drawer .text-white, #edit-modal .text-white {
    color: #ffffff !important;
  }
  
  .col-sticky { position: sticky; left: 0; z-index: 10; border-right-width: 1px; }
  .col-sticky-head { position: sticky; left: 0; z-index: 20; border-right-width: 1px; }
  .col-sticky-name { position: sticky; left: 44px; z-index: 10; border-right-width: 1px; }
  .col-sticky-head-name { position: sticky; left: 44px; z-index: 20; border-right-width: 1px; }
  
  #grid-root-container .kbd {
    background: var(--surface-3);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 1px 5px;
    font-size: 10px;
    font-weight: 600;
  }
  
  #grid-root-container .badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 500;
    line-height: 1.25;
  }
  #grid-root-container .badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
  }
  
  #grid-root-container .quick-filter-strip {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 8px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 6px;
  }
  #grid-root-container .chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 14px;
    border-radius: 9999px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    outline: none;
    font-family: inherit;
    transition: all 0.15s ease-in-out;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    white-space: nowrap;
  }
  .dark #grid-root-container .chip {
    border-color: var(--border);
    background: var(--surface);
    color: var(--text-2);
  }
  #grid-root-container .chip:hover {
    color: #1e293b;
    background: #ffffff;
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.05);
  }
  .dark #grid-root-container .chip:hover {
    color: var(--text-1);
    background: var(--surface-2);
    border-color: var(--accent);
  }
  #grid-root-container .chip-active {
    color: #4338ca !important;
    background: #eef2ff !important;
    border-color: #6366f1 !important;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25) !important;
    font-weight: 600 !important;
  }
  .dark #grid-root-container .chip-active {
    color: #ffffff !important;
    background: var(--surface-3) !important;
    border-color: var(--accent) !important;
    box-shadow: 0 0 0 2px var(--accent) !important;
  }
  #grid-root-container .date-input-pill {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 4px 10px;
    font-size: 12px;
    color: #475569;
    outline: none;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    transition: all 0.15s ease-in-out;
  }
  .dark #grid-root-container .date-input-pill {
    background: var(--surface);
    border-color: var(--border);
    color: var(--text-1);
  }
  #grid-root-container .date-input-pill:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
  }
  
  #grid-root-container .focus-ring:focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 2px;
  }

  /* KPI Summary Cards */
  .kpi-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 8px 12px;
    transition: all .15s ease-in-out;
    display: block;
    width: 100%;
    text-align: left;
    cursor: pointer;
    outline: none;
    font-family: inherit;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
  }
  .kpi-card:hover {
    border-color: var(--accent);
    transform: translateY(-1.5px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
  }
  .kpi-card.active-kpi {
    border-color: var(--accent) !important;
    background: var(--surface-2) !important;
    box-shadow: 0 0 0 2px var(--accent) !important;
  }
  .kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 5px;
  }
  .kpi-icon {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: none;
  }
  .kpi-trend {
    font-size: 10px;
    font-weight: 700;
    padding: 1.5px 6px;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    line-height: 1.3;
  }
  .kpi-trend.up {
    color: #16a34a;
    background: #dcfce7;
  }
  .kpi-trend.down {
    color: #dc2626;
    background: #fee2e2;
  }
  .kpi-trend.flat {
    color: #64748b;
    background: #f1f5f9;
  }
  .kpi-num {
    font-family: 'Lexend', inherit, sans-serif;
    font-weight: 700;
    font-size: 17px;
    line-height: 1.2;
    color: var(--text-1);
    font-variant-numeric: tabular-nums;
  }
  .kpi-title {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-2);
    margin-top: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .kpi-sub {
    font-size: 10px;
    color: var(--text-3);
    margin-top: 1px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* Compact Mini Widgets */
  .mini-widget {
    flex: none;
    min-width: 150px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 6px 12px;
    cursor: pointer;
    text-align: left;
    outline: none;
    font-family: inherit;
    transition: all .15s ease-in-out;
  }
  .mini-widget:hover {
    border-color: var(--accent);
    background: var(--surface-3);
  }
  .mini-widget.active-kpi {
    border-color: var(--accent) !important;
    background: var(--surface-3) !important;
    box-shadow: 0 0 0 2px var(--accent) !important;
  }
  .mini-label {
    font-size: 10px;
    color: var(--text-3);
    font-weight: 600;
    white-space: nowrap;
  }
  .mini-value {
    font-size: 12px;
    color: var(--text-1);
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .tab-underline { position: relative; border: none; background: transparent; cursor: pointer; }
  .tab-underline.active::after { content: ''; position: absolute; left: 10px; right: 10px; bottom: -9px; height: 2px; background: var(--accent); border-radius: 2px; }

  #grid-root-container th button {
    background: transparent !important;
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 0 6px 0 !important;
    cursor: pointer !important;
  }

  /* Sticky columns: Checkbox (left:0), Member ID (left:44px), Member Name (left:174px) */
  .col-sticky-head,
  .col-sticky {
    position: sticky !important;
    left: 0 !important;
    z-index: 10 !important;
    background-color: var(--surface) !important;
    box-shadow: none !important;
  }
  .col-sticky-head {
    background-color: var(--surface-2) !important;
  }
  .col-sticky-head-mid,
  .col-sticky-mid {
    position: sticky !important;
    left: 44px !important;
    z-index: 10 !important;
    background-color: var(--surface) !important;
    box-shadow: none !important;
  }
  .col-sticky-head-mid {
    background-color: var(--surface-2) !important;
  }
  .col-sticky-head-name,
  .col-sticky-name {
    position: sticky !important;
    left: 174px !important;
    z-index: 10 !important;
    background-color: var(--surface) !important;
    box-shadow: 2px 0 6px -2px rgba(0,0,0,0.14) !important;
  }
  .col-sticky-head-name {
    background-color: var(--surface-2) !important;
  }
</style>
@endpush

<!-- Advanced Grid root container -->
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative">

  <!-- Top Action Row -->
  <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
    <div>
      <h2 class="text-sm font-semibold tracking-wider uppercase text-indigo-400 font-display m-0">Peers Command Center</h2>
      <p class="text-xs t3 m-0 mt-0.5">Manage directory details, status controls, and user details</p>
    </div>
    <div class="flex items-center gap-2">
      @if (app(\App\Services\Admin\PermissionService::class)->can(Auth::guard('admin')->user(), 'admin.users.index', 'import') || app(\App\Services\Admin\PermissionService::class)->can(Auth::guard('admin')->user(), 'admin.users.import', 'import'))
      <a href="{{ route('admin.users.import') }}" class="px-3 py-1.5 rounded-lg border bs text-[12px] t2 hover:t1 hover:surface-2 transition font-medium focus-ring no-underline flex items-center gap-1.5">
        <i class="bi bi-download" aria-hidden="true"></i> Import
      </a>
      @endif
      @if (app(\App\Services\Admin\PermissionService::class)->can(Auth::guard('admin')->user(), 'admin.users.index', 'export') || app(\App\Services\Admin\PermissionService::class)->can(Auth::guard('admin')->user(), 'admin.users.export.csv', 'export'))
      <div class="relative">
        <button type="button" onclick="toggleExportMenu()" class="px-3 py-1.5 rounded-lg border bs text-[12px] t2 hover:t1 hover:surface-2 transition font-medium focus-ring flex items-center gap-1.5">
          <i class="bi bi-upload" aria-hidden="true"></i> Export <svg class="w-2.5 h-2.5 ml-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div id="export-menu" class="hidden absolute right-0 mt-2 w-44 rounded-xl border bs surface shadow-2xl py-1.5 z-40 fade-in">
          <button onclick="exportData('csv')" class="w-full text-left px-3 py-2 text-[12.5px] t2 hover:surface-3 hover:t1 flex items-center gap-2 border-none bg-transparent cursor-pointer"><i class="bi bi-file-earmark-text admin-icon w-4" aria-hidden="true"></i>Export as CSV</button>
          <button onclick="exportData('xlsx')" class="w-full text-left px-3 py-2 text-[12.5px] t2 hover:surface-3 hover:t1 flex items-center gap-2 border-none bg-transparent cursor-pointer"><i class="bi bi-file-earmark-excel admin-icon w-4" aria-hidden="true"></i>Export as Excel</button>
        </div>
      </div>
      @endif
      @if (app(\App\Services\Admin\PermissionService::class)->can(Auth::guard('admin')->user(), 'admin.users.index', 'create') || app(\App\Services\Admin\PermissionService::class)->can(Auth::guard('admin')->user(), 'admin.users.create', 'create'))
      <a href="{{ route('admin.users.create') }}" class="px-3 py-1.5 rounded-lg bg-accent hover:bg-opacity-95 text-white text-[12px] font-semibold transition focus-ring no-underline flex items-center gap-1">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Add Peer
      </a>
      @endif
    </div>
  </div>

  <!-- Saved views tab strip -->
  <div class="flex-none flex items-center gap-1 pb-2 border-b bs surface overflow-x-auto mb-4">
    <button data-view="all" onclick="setView(this)" class="tab-underline active px-3 py-1.5 text-[12.5px] font-medium t1 whitespace-nowrap">All Members</button>
    <button data-view="unity" onclick="setView(this)" class="tab-underline px-3 py-1.5 text-[12.5px] font-medium t3 hover:t1 whitespace-nowrap transition"><i class="bi bi-people-fill admin-icon me-1" aria-hidden="true"></i>Only Unity Members</button>
    <button data-view="circles" onclick="setView(this)" class="tab-underline px-3 py-1.5 text-[12.5px] font-medium t3 hover:t1 whitespace-nowrap transition"><i class="bi bi-record-circle-fill admin-icon me-1" aria-hidden="true"></i>Circles Peers</button>
    <button data-view="multiple" onclick="setView(this)" class="tab-underline px-3 py-1.5 text-[12.5px] font-medium t3 hover:t1 whitespace-nowrap transition"><i class="bi bi-diagram-3-fill admin-icon me-1" aria-hidden="true"></i>Multiple Circle Peers</button>
    <button data-view="free" onclick="setView(this)" class="tab-underline px-3 py-1.5 text-[12.5px] font-medium t3 hover:t1 whitespace-nowrap transition"><i class="bi bi-person admin-icon me-1" aria-hidden="true"></i>Free Peers</button>
    <button data-view="vip" onclick="setView(this)" class="tab-underline px-3 py-1.5 text-[12.5px] font-medium t3 hover:t1 whitespace-nowrap transition"><i class="bi bi-star-fill admin-icon me-1" aria-hidden="true"></i>VIP Circle</button>
    <button data-view="pending" onclick="setView(this)" class="tab-underline px-3 py-1.5 text-[12.5px] font-medium t3 hover:t1 whitespace-nowrap transition">Awaiting Review</button>
    <button data-view="expiring" onclick="setView(this)" class="tab-underline px-3 py-1.5 text-[12.5px] font-medium t3 hover:t1 whitespace-nowrap transition">Expiring Soon</button>
    <button data-view="new" onclick="setView(this)" class="tab-underline px-3 py-1.5 text-[12.5px] font-medium t3 hover:t1 whitespace-nowrap transition">New This Month</button>
  </div>

  <!-- Management Summary Collapsible Section -->
  <div class="flex-none pb-4 border-b bs mb-4">
    <div class="flex items-center justify-between mb-2">
      <span class="text-[10.5px] font-semibold t3 uppercase tracking-wider">Management Summary</span>
      <button onclick="toggleSummary()" id="summary-toggle-btn" class="text-[11.5px] t3 hover:t1 flex items-center gap-1 border-none bg-transparent cursor-pointer">
        <span id="summary-toggle-label">Collapse</span>
        <svg id="summary-toggle-icon" class="w-3 h-3 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
      </button>
    </div>

    <div id="kpi-summary">
      <!-- KPI Row 1 -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2 mb-2">
        <button type="button" onclick="filterByKpi('total')" id="kpi-card-total" class="kpi-card text-left" title="Click to show all members">
          <div class="kpi-top">
            <span class="kpi-icon" style="background:var(--accent-soft); color:var(--accent)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-8.13a4 4 0 110 8 4 4 0 010-8z"/></svg>
            </span>
            <span class="kpi-trend up">+12%</span>
          </div>
          <div class="kpi-num" id="kpi-total-num">0</div>
          <div class="kpi-title">Total Members</div>
          <div class="kpi-sub">Registered directory</div>
        </button>

        <button type="button" onclick="filterByKpi('active')" id="kpi-card-active" class="kpi-card text-left" title="Click to filter by Active Members">
          <div class="kpi-top">
            <span class="kpi-icon" style="background:var(--success-soft); color:var(--success)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </span>
            <span class="kpi-trend up">+4%</span>
          </div>
          <div class="kpi-num" id="kpi-active-num">0</div>
          <div class="kpi-title">Active Members</div>
          <div class="kpi-sub">Currently active</div>
        </button>

        <button type="button" onclick="filterByKpi('newtoday')" id="kpi-card-newtoday" class="kpi-card text-left" title="Click to filter by New Today">
          <div class="kpi-top">
            <span class="kpi-icon" style="background:var(--info-soft); color:var(--info)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            </span>
            <span class="kpi-trend up" id="kpi-newtoday-trend">+0</span>
          </div>
          <div class="kpi-num" id="kpi-newtoday-num">0</div>
          <div class="kpi-title">New Today</div>
          <div class="kpi-sub">Joined today</div>
        </button>

        <button type="button" onclick="filterByKpi('newmonth')" id="kpi-card-newmonth" class="kpi-card text-left" title="Click to filter by New This Month">
          <div class="kpi-top">
            <span class="kpi-icon" style="background:var(--info-soft); color:var(--info)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </span>
            <span class="kpi-trend up">+18%</span>
          </div>
          <div class="kpi-num" id="kpi-newmonth-num">0</div>
          <div class="kpi-title">New This Month</div>
          <div class="kpi-sub">Joined this month</div>
        </button>

        <button type="button" onclick="filterByKpi('renewtoday')" id="kpi-card-renewtoday" class="kpi-card text-left" title="Click to filter by Renewed Today">
          <div class="kpi-top">
            <span class="kpi-icon" style="background:var(--accent-soft); color:var(--accent-2)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </span>
            <span class="kpi-trend flat">0</span>
          </div>
          <div class="kpi-num" id="kpi-renewtoday-num">0</div>
          <div class="kpi-title">Renewed Today</div>
          <div class="kpi-sub">Memberships</div>
        </button>

        <button type="button" onclick="filterByKpi('renewmonth')" id="kpi-card-renewmonth" class="kpi-card text-left" title="Click to filter by Renewed This Month">
          <div class="kpi-top">
            <span class="kpi-icon" style="background:var(--accent-soft); color:var(--accent-2)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </span>
            <span class="kpi-trend up">+9%</span>
          </div>
          <div class="kpi-num" id="kpi-renewmonth-num">0</div>
          <div class="kpi-title">Renewed This Month</div>
          <div class="kpi-sub">This month</div>
        </button>
      </div>

      <!-- KPI Row 2 -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2 mb-3">
        <button type="button" onclick="filterByKpi('expiring7')" id="kpi-card-expiring7" class="kpi-card text-left" title="Click to filter by Expiring in 7 Days">
          <div class="kpi-top">
            <span class="kpi-icon" style="background:var(--warning-soft); color:var(--warning)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <span class="kpi-trend down">Urgent</span>
          </div>
          <div class="kpi-num" id="kpi-expiring7-num">0</div>
          <div class="kpi-title">Expiring in 7 Days</div>
          <div class="kpi-sub">Needs renewal</div>
        </button>

        <button type="button" onclick="filterByKpi('expired')" id="kpi-card-expired" class="kpi-card text-left" title="Click to filter by Expired Members">
          <div class="kpi-top">
            <span class="kpi-icon" style="background:var(--danger-soft); color:var(--danger)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </span>
            <span class="kpi-trend down">-3%</span>
          </div>
          <div class="kpi-num" id="kpi-expired-num">0</div>
          <div class="kpi-title">Expired</div>
          <div class="kpi-sub">Memberships</div>
        </button>

        <button type="button" onclick="filterByKpi('pendingpay')" id="kpi-card-pendingpay" class="kpi-card text-left" title="Click to filter by Pending Payments">
          <div class="kpi-top">
            <span class="kpi-icon" style="background:var(--warning-soft); color:var(--warning)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </span>
            <span class="kpi-trend down">Due</span>
          </div>
          <div class="kpi-num" id="kpi-pendingpay-num">₹0</div>
          <div class="kpi-title">Pending Payments</div>
          <div class="kpi-sub">Outstanding</div>
        </button>

        <button type="button" onclick="filterByKpi('revenue')" id="kpi-card-revenue" class="kpi-card text-left" title="Click to filter by Paid / Revenue Members">
          <div class="kpi-top">
            <span class="kpi-icon" style="background:var(--success-soft); color:var(--success)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-2c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <span class="kpi-trend up">+6%</span>
          </div>
          <div class="kpi-num" id="kpi-revenue-num">₹0</div>
          <div class="kpi-title">Revenue (Estimated)</div>
          <div class="kpi-sub">Total collected</div>
        </button>

        <button type="button" onclick="filterByKpi('churn')" id="kpi-card-churn" class="kpi-card text-left" title="Click to filter by Churned / Left Members">
          <div class="kpi-top">
            <span class="kpi-icon" style="background:var(--danger-soft); color:var(--danger)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 17h8m0 0v-8m0 8L13 9l-4 4-6-6"/></svg>
            </span>
            <span class="kpi-trend down">-2%</span>
          </div>
          <div class="kpi-num" id="kpi-churn-num">0</div>
          <div class="kpi-title">Left (Last Month)</div>
          <div class="kpi-sub">Churned members</div>
        </button>

        <button type="button" onclick="filterByKpi('approvals')" id="kpi-card-approvals" class="kpi-card text-left" title="Click to filter by Pending Approvals">
          <div class="kpi-top">
            <span class="kpi-icon" style="background:var(--info-soft); color:var(--info)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <span class="kpi-trend down">Review</span>
          </div>
          <div class="kpi-num" id="kpi-approvals-num">0</div>
          <div class="kpi-title">Pending Approvals</div>
          <div class="kpi-sub">Awaiting review</div>
        </button>
      </div>

      <!-- Compact widget strip -->
      <div class="flex items-stretch gap-2 pb-2 overflow-x-auto whitespace-nowrap mb-2.5" style="scrollbar-width: thin;">
        <button type="button" onclick="filterByKpi('birthdays')" id="widget-card-birthdays" class="mini-widget text-left" title="Click to filter members having Birthday today"><span class="mini-label"><i class="bi bi-cake2-fill admin-icon me-1" aria-hidden="true"></i>Birthdays Today</span><span class="mini-value" id="widget-birthdays">-</span></button>
        <a href="{{ route('admin.events.index') }}" class="mini-widget text-decoration-none text-left" title="View Upcoming Events"><span class="mini-label"><i class="bi bi-calendar-event-fill admin-icon me-1" aria-hidden="true"></i>Upcoming Events</span><span class="mini-value" id="widget-events">-</span></a>
        <button type="button" onclick="filterByKpi('recent')" id="widget-card-recent" class="mini-widget text-left" title="Click to filter by Recently Joined peers"><span class="mini-label"><i class="bi bi-person-lines-fill admin-icon me-1" aria-hidden="true"></i>Recently Joined</span><span class="mini-value" id="widget-recent">-</span></button>
        <button type="button" onclick="filterByKpi('topcircle')" id="widget-card-topcircle" class="mini-widget text-left" title="Click to filter by Top Circle"><span class="mini-label"><i class="bi bi-trophy-fill admin-icon me-1" aria-hidden="true"></i>Top Circle</span><span class="mini-value" id="widget-top-circle">-</span></button>
        <button type="button" onclick="filterByKpi('topindustry')" id="widget-card-topindustry" class="mini-widget text-left" title="Click to filter by Top Industry"><span class="mini-label"><i class="bi bi-building-fill admin-icon me-1" aria-hidden="true"></i>Top Industry</span><span class="mini-value" id="widget-top-industry">-</span></button>
        <button type="button" onclick="filterByKpi('approvals')" id="widget-card-approvals" class="mini-widget text-left" title="Click to filter by Pending Approvals"><span class="mini-label"><i class="bi bi-clock-history admin-icon me-1" aria-hidden="true"></i>Pending Approvals</span><span class="mini-value" id="widget-pending-approvals">0</span></button>
      </div>

      <!-- Quick Filters Inside Management Summary -->
      <div class="quick-filter-strip">
        <div class="flex items-center gap-2 overflow-x-auto whitespace-nowrap flex-1" style="scrollbar-width:thin;">
          <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mr-1 flex-none flex items-center gap-1.5"><i class="bi bi-funnel-fill text-indigo-400"></i> QUICK:</span>
          <button type="button" onclick="quickFilter('expiring')" id="qf-expiring" class="chip flex-none"><span class="badge-dot" style="background:#f59e0b"></span>Expiring in 30d</button>
          <button type="button" onclick="quickFilter('new7')" id="qf-new7" class="chip flex-none"><span class="badge-dot" style="background:#10b981"></span>Joined last 7d</button>
          <button type="button" onclick="quickFilter('nopayment')" id="qf-nopayment" class="chip flex-none"><span class="badge-dot" style="background:#ef4444"></span>Payment overdue</button>
          <button type="button" onclick="quickFilter('inactive')" id="qf-inactive" class="chip flex-none"><span class="badge-dot" style="background:#94a3b8"></span>Inactive 30d+</button>

          <div class="ml-auto flex items-center gap-2 flex-none">
            <span class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">JOINED:</span>
            <input type="date" id="f-joined-start" onchange="applyFilters(true)" class="date-input-pill"/>
            <span class="text-slate-400 text-[13px] font-medium">→</span>
            <input type="date" id="f-joined-end" onchange="applyFilters(true)" class="date-input-pill"/>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Membership Approval Bar -->
  <div class="mb-4 p-4 border bs rounded-xl surface-2">
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-3">
      <div>
        <div class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider">Membership Approval</div>
        <div class="text-[11.5px] t3 mt-0.5">Select peers from the grid below and approve their membership as Global Peer.</div>
      </div>
      <div class="flex flex-wrap items-end gap-3 w-full xl:w-auto xl:justify-end">
        <div class="flex-1 min-w-[120px] xl:flex-none">
          <label class="block text-[11px] t3 mb-1 font-medium">Starts At</label>
          <input id="approvalMembershipStartsAt" type="date" class="w-full px-2.5 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring"/>
        </div>
        <div class="flex-1 min-w-[120px] xl:flex-none">
          <label class="block text-[11px] t3 mb-1 font-medium">Ends At</label>
          <input id="approvalMembershipEndsAt" type="date" class="w-full px-2.5 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring"/>
        </div>
        <button type="button" id="openApproveMembershipModal" class="px-3.5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold flex items-center gap-1.5 shadow-md transition focus-ring">
          <i class="bi bi-check-circle"></i>
          Approve Selected
        </button>
      </div>
    </div>
  </div>

  <!-- Toolbar: Search + Utility Controls -->
  <div class="flex-none py-2 border-b bs mb-4">
    <div class="flex items-center justify-between gap-2.5 flex-wrap">
      <div class="flex items-center gap-2.5 flex-1 min-w-[280px]">
        <div class="relative flex-1 min-w-[240px] max-w-[380px]">
          <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
          <input id="search-input" oninput="applyFilters(true)" type="text" placeholder="Global Search (Name, Email, ID, Company)..." class="w-full pl-9 pr-10 py-2 rounded-lg border bs surface-2 text-[13px] t1 placeholder:t3 focus-ring outline-none"/>
          <span class="kbd absolute right-2.5 top-1/2 -translate-y-1/2">/</span>
        </div>

        <button onclick="clearFilters()" class="chip !text-[12.5px] border border-current" title="Clear all filters">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          Clear
        </button>
      </div>

      <div class="ml-auto flex items-center gap-2.5">
        <span id="result-count" class="flex-none text-[12.5px] t3 tnum mr-1">Showing <span id="result-num" class="font-semibold t1">0</span> of <span class="font-semibold t1" id="result-total">0</span> members</span>
        <div class="relative">
          <button onclick="toggleSavedFilters()" class="chip">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-4-7 4V5z"/></svg>
            Saved filters
          </button>
          <div id="saved-filters-menu" class="hidden absolute right-0 mt-2 w-52 rounded-xl border bs surface shadow-2xl py-1.5 z-40 fade-in">
            <button onclick="applySavedFilter('gold')" class="w-full text-left px-3 py-2 text-[12.5px] t2 hover:surface-3 hover:t1 border-none bg-transparent"><i class="bi bi-fire admin-icon me-1.5" aria-hidden="true"></i>Gold members</button>
            <button onclick="applySavedFilter('expiring')" class="w-full text-left px-3 py-2 text-[12.5px] t2 hover:surface-3 hover:t1 border-none bg-transparent"><i class="bi bi-hourglass-split admin-icon me-1.5" aria-hidden="true"></i>Expiring next 30 days</button>
            <button onclick="applySavedFilter('inactive')" class="w-full text-left px-3 py-2 text-[12.5px] t2 hover:surface-3 hover:t1 border-none bg-transparent"><i class="bi bi-moon-stars admin-icon me-1.5" aria-hidden="true"></i>Inactive 30d+</button>
          </div>
        </div>
        <div class="relative">
          <button onclick="toggleColumnsMenu()" class="chip">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3H5a2 2 0 00-2 2v14a2 2 0 002 2h4m0-18h10a2 2 0 012 2v14a2 2 0 01-2 2H9m0-18v18"/></svg>
            Columns
          </button>
          <div id="columns-menu" class="hidden absolute right-0 mt-2 w-56 rounded-xl border bs surface shadow-2xl py-2 z-40 fade-in max-h-80 overflow-y-auto">
            <div class="px-3 pb-1.5 text-[10.5px] font-semibold t3 uppercase tracking-wider">Toggle columns</div>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('email', this)"> Email</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('mobile', this)"> Mobile</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('company', this)"> Company</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('industry', this)"> Industry</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('circle', this)"> Circle</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('city', this)"> City</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('country', this)"> Country</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('role', this)"> Role</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('membership', this)"> Membership</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('status', this)"> Status</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('payment', this)"> Payment</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('activity', this)"> Activity Score</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('coins', this)"> Coins</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('lastlogin', this)"> Last Login</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('lastpayment', this)"> Last Payment</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('renewals', this)"> Renewals</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('referralcol', this)"> Referrals</label>
            <label class="flex items-center gap-2 px-3 py-1.5 text-[12.5px] t2 hover:surface-3 cursor-pointer"><input type="checkbox" checked class="accent-indigo-500 font-normal" onchange="toggleColumn('pendingamt', this)"> Pending Amount</label>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bulk Actions Bar -->
  <div id="bulk-bar" class="hidden flex-none items-center gap-3 px-6 py-2.5 border-b bs mb-4 rounded-xl" style="background:var(--accent-soft)">
    <span class="text-[12.5px] font-semibold accent"><span id="bulk-count">0</span> selected</span>
    <div class="h-4 w-px" style="background:var(--border)"></div>
    <button onclick="bulkApproveTrigger()" class="flex items-center gap-1.5 text-[12.5px] t2 hover:t1 border-none bg-transparent cursor-pointer font-medium"><i class="bi bi-check-circle"></i> Approve</button>
    <button onclick="clearSelection()" class="ml-auto text-[12.5px] t3 hover:t1 border-none bg-transparent cursor-pointer font-medium">Clear selection ✕</button>
  </div>

  <!-- Top Horizontal Scrollbar (Synchronized) -->
  <div id="top-scroll-wrapper" class="overflow-x-auto overflow-y-hidden rounded-t-lg border-t border-l border-r bs surface-2" style="height: 12px; margin-bottom: 0px; display: none;">
    <div id="top-scroll-content" style="height: 1px;"></div>
  </div>

  <div class="overflow-x-auto relative pb-20" id="table-scroll">
        <table class="min-w-full border-collapse text-[13px]" id="main-table">
          <thead>
            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold">
              <th class="th-cell col-sticky-head surface-2 border-b border-r bs px-3 py-3 text-left" style="width:44px; min-width:44px; max-width:44px;">
                <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)" class="accent-indigo-500 w-4 h-4 rounded"/>
              </th>
              <th data-colgrp="mid" class="th-cell col-sticky-head-mid surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:130px;">
                <button onclick="sortBy('mid')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Member ID <svg class="w-3 h-3 sort-icon" data-col="mid" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-mid" placeholder="Search ID..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('mid', event)" oninput="filterSearchDropdown('mid', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-mid" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="name" class="th-cell col-sticky-head-name surface-2 border-b border-r bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:160px;">
                <button onclick="sortBy('name')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Member <svg class="w-3 h-3 sort-icon" data-col="name" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-name" placeholder="Search Name..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('name', event)" oninput="filterSearchDropdown('name', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-name" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="mobile" class="th-cell surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:130px;">
                <button onclick="sortBy('mobile')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Mobile <svg class="w-3 h-3 sort-icon" data-col="mobile" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-mobile" placeholder="Search Mob..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('mobile', event)" oninput="filterSearchDropdown('mobile', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-mobile" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="email" class="th-cell surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:160px;">
                <button onclick="sortBy('email')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Email <svg class="w-3 h-3 sort-icon" data-col="email" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-email" placeholder="Search Email..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('email', event)" oninput="filterSearchDropdown('email', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-email" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="company" class="th-cell surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:150px;">
                <button onclick="sortBy('company')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Company <svg class="w-3 h-3 sort-icon" data-col="company" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-company" placeholder="Search Comp..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('company', event)" oninput="filterSearchDropdown('company', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-company" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="industry" class="th-cell surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:140px;">
                <button onclick="sortBy('industry')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Industry <svg class="w-3 h-3 sort-icon" data-col="industry" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-industry" placeholder="Search Ind..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('industry', event)" oninput="filterSearchDropdown('industry', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-industry" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="city" class="th-cell surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:120px;">
                <button onclick="sortBy('city')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">City <svg class="w-3 h-3 sort-icon" data-col="city" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-city" placeholder="Search City..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('city', event)" oninput="filterSearchDropdown('city', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-city" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="country" class="th-cell surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:120px;">
                <button onclick="sortBy('country')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Country <svg class="w-3 h-3 sort-icon" data-col="country" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-country" placeholder="Search Ctr..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('country', event)" oninput="filterSearchDropdown('country', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-country" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="circle" class="th-cell surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:150px;">
                <button onclick="sortBy('circle')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Circle <svg class="w-3 h-3 sort-icon" data-col="circle" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-circle" placeholder="Search Cir..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('circle', event)" oninput="filterSearchDropdown('circle', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-circle" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="role" class="th-cell surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:130px;">
                <button onclick="sortBy('role')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Role <svg class="w-3 h-3 sort-icon" data-col="role" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-role" placeholder="Search Role..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('role', event)" oninput="filterSearchDropdown('role', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-role" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="membership" class="th-cell surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:140px;">
                <button onclick="sortBy('membership')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Membership <svg class="w-3 h-3 sort-icon" data-col="membership" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-membership" placeholder="Search Mem..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('membership', event)" oninput="filterSearchDropdown('membership', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-membership" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="status" class="th-cell surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:120px;">
                <button onclick="sortBy('status')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Status <svg class="w-3 h-3 sort-icon" data-col="status" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-status" placeholder="Search Status..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('status', event)" oninput="filterSearchDropdown('status', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-status" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="payment" class="th-cell surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:130px;">
                <button onclick="sortBy('payment')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Payment <svg class="w-3 h-3 sort-icon" data-col="payment" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-payment" placeholder="Search Pay..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('payment', event)" oninput="filterSearchDropdown('payment', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-payment" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="activity" class="th-cell surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:140px;">
                <button onclick="sortBy('activity')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Activity <svg class="w-3 h-3 sort-icon" data-col="activity" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-activity" placeholder="Search Act..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('activity', event)" oninput="filterSearchDropdown('activity', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-activity" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="coins" class="th-cell surface-2 border-b bs px-3 py-2 text-right relative header-dropdown-container" style="min-width:110px;">
                <button onclick="sortBy('coins')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5 ml-auto">Coins <svg class="w-3 h-3 sort-icon" data-col="coins" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-coins" placeholder="Search Coins..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case text-right" onfocus="showSearchDropdown('coins', event)" oninput="filterSearchDropdown('coins', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-coins" class="header-dropdown-menu hidden absolute right-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="lastlogin" class="th-cell surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:130px;">
                <button onclick="sortBy('lastLogin')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Last Login <svg class="w-3 h-3 sort-icon" data-col="lastLogin" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-lastlogin" placeholder="Search Login..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('lastlogin', event)" oninput="filterSearchDropdown('lastlogin', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-lastlogin" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="lastpayment" class="th-cell surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:140px;">
                <button onclick="sortBy('lastPaymentDate')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Last Payment <svg class="w-3 h-3 sort-icon" data-col="lastPaymentDate" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-lastpayment" placeholder="Search Pay..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('lastpayment', event)" oninput="filterSearchDropdown('lastpayment', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-lastpayment" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="renewals" class="th-cell surface-2 border-b bs px-3 py-2 text-right relative header-dropdown-container" style="min-width:110px;">
                <button onclick="sortBy('renewalCount')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5 ml-auto">Renewals <svg class="w-3 h-3 sort-icon" data-col="renewalCount" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-renewals" placeholder="Search..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case text-right" onfocus="showSearchDropdown('renewals', event)" oninput="filterSearchDropdown('renewals', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-renewals" class="header-dropdown-menu hidden absolute right-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="referralcol" class="th-cell surface-2 border-b bs px-3 py-2 text-right relative header-dropdown-container" style="min-width:110px;">
                <button onclick="sortBy('referrals')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5 ml-auto">Referrals <svg class="w-3 h-3 sort-icon" data-col="referrals" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-referralcol" placeholder="Search..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case text-right" onfocus="showSearchDropdown('referralcol', event)" oninput="filterSearchDropdown('referralcol', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-referralcol" class="header-dropdown-menu hidden absolute right-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="pendingamt" class="th-cell surface-2 border-b bs px-3 py-2 text-right relative header-dropdown-container" style="min-width:125px;">
                <button onclick="sortBy('pendingAmount')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5 ml-auto">Pending Amt <svg class="w-3 h-3 sort-icon" data-col="pendingAmount" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-pendingamt" placeholder="Search..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case text-right" onfocus="showSearchDropdown('pendingamt', event)" oninput="filterSearchDropdown('pendingamt', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-pendingamt" class="header-dropdown-menu hidden absolute right-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th data-colgrp="lastevent" class="th-cell surface-2 border-b bs px-3 py-2 text-left relative header-dropdown-container" style="min-width:170px;">
                <button onclick="sortBy('lastEvent')" class="flex items-center gap-1 hover:t1 font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Last Event Joined <svg class="w-3 h-3 sort-icon" data-col="lastEvent" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg></button>
                <div class="relative">
                  <svg class="w-3 h-3 absolute left-2 top-1/2 -translate-y-1/2 t3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                  <input type="text" id="h-search-lastevent" placeholder="Search Event..." class="w-full pl-6 pr-2 py-1 rounded border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal normal-case" onfocus="showSearchDropdown('lastevent', event)" oninput="filterSearchDropdown('lastevent', this.value)" onclick="event.stopPropagation()"/>
                  <div id="h-menu-lastevent" class="header-dropdown-menu hidden absolute left-0 top-full mt-1 w-56 rounded-lg border bs surface shadow-2xl p-2 z-50 text-left normal-case font-normal max-h-40 overflow-y-auto space-y-0.5"></div>
                </div>
                <div class="col-resize-handle"></div>
              </th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-center" style="min-width:100px;">
                <div class="font-semibold uppercase tracking-wider text-[11px] t3 mb-1.5">Actions</div>
                <div>
                  <button type="button" onclick="clearFilters()" class="w-full px-2.5 py-1 text-[11px] font-semibold rounded border bs t2 hover:t1 hover:surface-3 transition bg-transparent cursor-pointer">
                    Clear
                  </button>
                </div>
              </th>
            </tr>
          </thead>
          <tbody id="table-body"><!-- rows injected by JS --></tbody>
        </table>
  
        <!-- Loading skeleton -->
        <div id="loading-state" class="hidden px-6 py-4 space-y-3">
          <div class="skeleton h-10 rounded-lg" style="width:100%;"></div>
          <div class="skeleton h-10 rounded-lg" style="width:100%;"></div>
          <div class="skeleton h-10 rounded-lg" style="width:100%;"></div>
        </div>
  
        <!-- Empty state -->
        <div id="empty-state" class="hidden flex flex-col items-center justify-center py-20 text-center w-full my-6">
          <div class="w-16 h-16 rounded-2xl surface-2 border bs flex items-center justify-center mb-3 shadow-sm mx-auto"><svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-8.13a4 4 0 110 8 4 4 0 010-8z"/></svg></div>
          <div class="font-display font-semibold text-[15px] t1 mb-1">No members yet</div>
          <div class="text-[13px] t3 max-w-sm mb-4 mx-auto">Add your first member or import a directory to populate this table.</div>
          <a href="{{ route('admin.users.create') }}" class="px-4 py-2 rounded-lg bg-accent text-white text-[12.5px] font-medium text-decoration-none shadow-sm hover:opacity-90 transition">Add Member</a>
        </div>
  
        <!-- No results state -->
        <div id="noresults-state" class="hidden flex flex-col items-center justify-center py-20 text-center w-full my-6">
          <div class="w-16 h-16 rounded-2xl surface-2 border bs flex items-center justify-center mb-3 shadow-sm mx-auto"><svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg></div>
          <div class="font-display font-semibold text-[15px] t1 mb-1">No members match your filters</div>
          <div class="text-[13px] t3 max-w-sm mb-4 mx-auto">Try removing a filter or searching a different term.</div>
          <button type="button" onclick="clearFilters()" class="px-4 py-2 rounded-lg border bs text-[12.5px] font-semibold t1 hover:surface-2 transition shadow-sm bg-surface cursor-pointer">Clear all filters</button>
        </div>
      </div>
  
      <!-- Footer: pagination sticky to bottom of root container, or fixed bottom matching sidebar width of 264px -->
      <div class="fixed bottom-0 right-0 left-0 lg:left-[264px] flex items-center justify-between px-5 py-1 border-t bs surface flex-wrap gap-1.5 z-30 shadow-[0_-4px_12px_rgba(0,0,0,0.15)]">
        <div class="flex items-center gap-2 text-[12px] t2">
          <span>Rows</span>
          <select id="rows-per-page-select" class="px-1.5 py-0.5 rounded-md border bs surface-2 t1 focus-ring outline-none" onchange="changeRowsPerPage(this.value)"><option value="25">25</option><option value="50">50</option><option value="100">100</option></select>
          <label class="flex items-center gap-1 ml-2 cursor-pointer select-none">
            <input type="checkbox" id="infinite-toggle" class="accent-indigo-500 w-3 h-3" onchange="toggleInfinite(this)"/>
            Infinite
          </label>
          <button onclick="toggleTheme()" class="flex items-center gap-1.5 ml-2 px-2 py-0.5 rounded-md border bs surface-2 text-[11px] t2 hover:t1 transition focus-ring">
            <svg id="theme-icon" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            <span id="theme-label">Dark Mode</span>
          </button>
        </div>
        <div class="flex items-center gap-1" id="pagination-controls-container">
          <!-- Filled by JS -->
        </div>
        <div class="hidden xl:flex items-center gap-3 text-[11px] t3">
          <span><span class="kbd">/</span> search</span>
          <span><span class="kbd">J</span><span class="kbd">K</span> navigate rows</span>
          <span><span class="kbd">X</span> select row</span>
          <span><span class="kbd">Esc</span> close panel</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============ EDIT PEER MODAL ============ -->
<div id="edit-modal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="surface border bs rounded-xl w-full max-w-4xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden text-left">
    <!-- Modal Header -->
    <div class="px-6 py-4 border-b bs flex items-center justify-between flex-none">
      <div>
        <h2 class="font-display font-bold text-[15px] t1 flex items-center gap-2">
          <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          Edit Peer
        </h2>
        <p class="text-[11px] t3 mt-0.5" id="edit-peer-id">ID: —</p>
      </div>
      <button onclick="closeEditModal()" class="w-8 h-8 rounded-lg hover:surface-2 flex items-center justify-center t3 hover:t1 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    
    <!-- Modal Tabs -->
    <div class="px-6 py-2 border-b bs surface-2 flex flex-wrap gap-1 flex-none">
      <button onclick="setEditTab(1)" id="edit-tab-btn-1" class="px-3 py-1.5 rounded-lg text-[11.5px] font-medium transition flex items-center gap-1.5 bg-accent text-white border-none cursor-pointer">
        <i class="bi bi-person-fill me-1" aria-hidden="true"></i>1. Personal Profile
      </button>
      <button onclick="setEditTab(2)" id="edit-tab-btn-2" class="px-3 py-1.5 rounded-lg text-[11.5px] font-medium transition flex items-center gap-1.5 t2 hover:surface-3 hover:t1 border-none bg-transparent cursor-pointer">
        <i class="bi bi-briefcase-fill me-1" aria-hidden="true"></i>2. Business Details
      </button>
      <button onclick="setEditTab(3)" id="edit-tab-btn-3" class="px-3 py-1.5 rounded-lg text-[11.5px] font-medium transition flex items-center gap-1.5 t2 hover:surface-3 hover:t1 border-none bg-transparent cursor-pointer">
        <i class="bi bi-credit-card-2-front-fill me-1" aria-hidden="true"></i>3. Membership & Coins
      </button>
      <button onclick="setEditTab(4)" id="edit-tab-btn-4" class="px-3 py-1.5 rounded-lg text-[11.5px] font-medium transition flex items-center gap-1.5 t2 hover:surface-3 hover:t1 border-none bg-transparent cursor-pointer">
        <i class="bi bi-record-circle-fill me-1" aria-hidden="true"></i>4. Circles & Admin
      </button>
      <button onclick="setEditTab(5)" id="edit-tab-btn-5" class="px-3 py-1.5 rounded-lg text-[11.5px] font-medium transition flex items-center gap-1.5 t2 hover:surface-3 hover:t1 border-none bg-transparent cursor-pointer">
        <i class="bi bi-file-earmark-richtext-fill me-1" aria-hidden="true"></i>5. Story Submissions (0)
      </button>
      <button onclick="setEditTab(6)" id="edit-tab-btn-6" class="px-3 py-1.5 rounded-lg text-[11.5px] font-medium transition flex items-center gap-1.5 t2 hover:surface-3 hover:t1 border-none bg-transparent cursor-pointer">
        <i class="bi bi-people-fill me-1" aria-hidden="true"></i>6. Introduced Members (0)
      </button>
    </div>
    
    <!-- Modal Form Body -->
    <div class="flex-1 overflow-y-auto p-6 space-y-6">
      <form id="edit-peer-form" onsubmit="savePeerChanges(event)">
        <!-- Tab 1: Personal Profile -->
        <div id="edit-tab-content-1" class="space-y-6">
          <div class="border-b bs pb-2.5">
            <h3 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider flex items-center gap-1.5"><i class="bi bi-card-heading me-1" aria-hidden="true"></i>Personal Identification</h3>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">First Name</label>
              <input type="text" id="edit-first-name" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 placeholder:t3 focus-ring outline-none text-xs"/>
            </div>
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Last Name</label>
              <input type="text" id="edit-last-name" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 placeholder:t3 focus-ring outline-none text-xs"/>
            </div>
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Display Name</label>
              <input type="text" id="edit-display-name" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 placeholder:t3 focus-ring outline-none text-xs"/>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Email Address</label>
              <input type="email" id="edit-email" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 placeholder:t3 focus-ring outline-none text-xs"/>
            </div>
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Phone / Mobile</label>
              <input type="text" id="edit-phone" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 placeholder:t3 focus-ring outline-none text-xs"/>
            </div>
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Designation</label>
              <input type="text" id="edit-designation" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 placeholder:t3 focus-ring outline-none text-xs"/>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Gender</label>
              <select id="edit-gender" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 focus-ring outline-none text-xs">
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Date of Birth</label>
              <input type="date" id="edit-dob" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 focus-ring outline-none text-xs"/>
            </div>
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Experience (Years)</label>
              <input type="text" id="edit-experience" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 focus-ring outline-none text-xs"/>
            </div>
          </div>
          <div>
            <label class="block text-xs t3 mb-1.5 font-medium">Short Biography</label>
            <textarea id="edit-bio" rows="3" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 placeholder:t3 focus-ring outline-none text-xs"></textarea>
          </div>
        </div>
        
        <!-- Tab 2: Business Details -->
        <div id="edit-tab-content-2" class="hidden space-y-6">
          <div class="border-b bs pb-2.5">
            <h3 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider flex items-center gap-1.5"><i class="bi bi-building-fill me-1" aria-hidden="true"></i>Business & Organization Details</h3>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Company Name</label>
              <input type="text" id="edit-company" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 placeholder:t3 focus-ring outline-none text-xs"/>
            </div>
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Industry</label>
              <input type="text" id="edit-industry" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 placeholder:t3 focus-ring outline-none text-xs"/>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">City</label>
              <input type="text" id="edit-city" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 placeholder:t3 focus-ring outline-none text-xs"/>
            </div>
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Country</label>
              <input type="text" id="edit-country" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 placeholder:t3 focus-ring outline-none text-xs"/>
            </div>
          </div>
        </div>
        
        <!-- Tab 3: Membership & Coins -->
        <div id="edit-tab-content-3" class="hidden space-y-6">
          <div class="border-b bs pb-2.5">
            <h3 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider flex items-center gap-1.5"><i class="bi bi-wallet2 me-1" aria-hidden="true"></i>Membership Status & Wallet</h3>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Membership Tier</label>
              <select id="edit-membership" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 focus-ring outline-none text-xs">
                <option value="free_peer">Free Peer</option>
                <option value="free_trial_peer">Free Trial Peer</option>
                <option value="Only Unity Peer">Global Peer</option>
                <option value="Circle Peer">Circle Peer</option>
                <option value="Multi Circle Peer">Multi Circle Peer</option>
              </select>
            </div>
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Status</label>
              <select id="edit-status" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 focus-ring outline-none text-xs">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Membership Start Date</label>
              <input type="date" id="edit-membership-starts-at" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 focus-ring outline-none text-xs"/>
            </div>
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Membership Expiry Date</label>
              <input type="date" id="edit-membership-ends-at" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 focus-ring outline-none text-xs"/>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
              <label class="block text-xs t3 mb-1.5 font-medium">Membership Expiry Remark</label>
              <input type="text" id="edit-membership-remark" placeholder="Write remark explaining why membership status or expiry date was updated" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 placeholder:t3 focus-ring outline-none text-xs"/>
            </div>
            <div class="flex items-center pt-4">
              <label class="flex items-center gap-2 cursor-pointer select-none text-xs t1">
                <input type="checkbox" id="edit-is-sponsored" class="accent-indigo-500 w-4 h-4 rounded"/>
                <span class="font-medium">Is Sponsored Member</span>
              </label>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Coins Balance</label>
              <input type="text" id="edit-coins" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 placeholder:t3 focus-ring outline-none text-xs"/>
            </div>
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Engagement Score</label>
              <input type="text" id="edit-activity" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 placeholder:t3 focus-ring outline-none text-xs"/>
            </div>
          </div>
        </div>
        
        <!-- Tab 4: Circles & Admin -->
        <div id="edit-tab-content-4" class="hidden space-y-6">
          <div class="border-b bs pb-2.5">
            <h3 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider flex items-center gap-1.5"><i class="bi bi-diagram-3-fill me-1" aria-hidden="true"></i>Circle Assignments & Roles</h3>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">Assigned Circle</label>
              <input type="text" id="edit-circle" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 placeholder:t3 focus-ring outline-none text-xs"/>
            </div>
            <div>
              <label class="block text-xs t3 mb-1.5 font-medium">System Role</label>
              <select id="edit-role" class="w-full px-3 py-2 rounded-lg border bs surface-2 t1 focus-ring outline-none text-xs">
                <option value="Member">Member</option>
                <option value="Admin">Admin</option>
                <option value="Moderator">Moderator</option>
                <option value="Circle Lead">Circle Lead</option>
              </select>
            </div>
          </div>
        </div>
        
        <!-- Tab 5: Story Submissions -->
        <div id="edit-tab-content-5" class="hidden space-y-6">
          <div class="border-b bs pb-2.5">
            <h3 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider flex items-center gap-1.5"><i class="bi bi-journal-text me-1" aria-hidden="true"></i>Stories & Vyapaar Submissions</h3>
          </div>
          <div class="text-center py-8 text-xs t3">No story submissions recorded for this peer.</div>
        </div>
        
        <!-- Tab 6: Introduced Members -->
        <div id="edit-tab-content-6" class="hidden space-y-6">
          <div class="border-b bs pb-2.5">
            <h3 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider flex items-center gap-1.5"><i class="bi bi-person-plus-fill me-1" aria-hidden="true"></i>Introduced Peers & Referrals</h3>
          </div>
          <div class="text-center py-8 text-xs t3">No introduced peers found.</div>
        </div>
      </form>
    </div>
    
    <!-- Modal Footer -->
    <div class="px-6 py-4 border-t bs surface-2 flex items-center justify-end gap-3 flex-none">
      <button onclick="closeEditModal()" class="px-4 py-2 rounded-lg border bs text-xs font-medium t1 hover:surface-3 transition">Cancel</button>
      <button onclick="submitEditForm()" class="px-4 py-2 rounded-lg bg-accent text-white text-xs font-medium hover:bg-accent/90 transition shadow-lg">Save Changes</button>
    </div>
  </div>
</div>

<!-- ============ QUICK PREVIEW DRAWER ============ -->
<div id="drawer-scrim" onclick="closeDrawer()" class="scrim hidden fixed inset-0 bg-black/50 z-40"></div>
<aside id="drawer" class="drawer drawer-hidden fixed top-0 right-0 h-full w-full sm:w-[420px] bg-white border-l border-slate-200 z-50 flex flex-col shadow-2xl">
  <div class="flex items-center justify-between px-5 h-16 border-b border-slate-200 flex-none bg-white">
    <span class="font-display font-semibold text-[15px] text-slate-900">Member profile</span>
    <button onclick="closeDrawer()" class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="flex-1 overflow-y-auto p-5 space-y-5 bg-white" id="drawer-body">
    <!-- filled by JS -->
  </div>
  <div class="flex-none p-4 border-t border-slate-200 bg-white flex gap-2.5">
    <button id="view-full-profile-btn" class="flex-1 py-2.5 rounded-xl bg-[#00bcd4] hover:bg-[#00acc1] text-white text-[12.5px] font-semibold transition shadow-sm text-center border-0 cursor-pointer">View full profile</button>
    <button onclick="openEditModal(window.currentDrawerPeerId)" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[12.5px] font-semibold transition cursor-pointer">Quick edit</button>
  </div>
</aside>

<form id="bulkApproveMembershipDatesForm" method="POST" action="{{ route('admin.users.bulk-approve-membership') }}">
    @csrf
    <div class="modal fade" id="approveMembershipDatesModal" tabindex="-1" aria-labelledby="approveMembershipDatesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 560px;">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="approveMembershipDatesModalLabel">Approve Selected Peers</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="alert alert-success-subtle border-success-subtle mb-3">
                        <div class="fw-semibold">Selected peers: <span id="selectedPeersCount">0</span></div>
                        <div class="small text-muted">Membership Upgrade: <strong>Global Peer</strong></div>
                    </div>
                    <div class="border rounded-3 p-3 bg-light-subtle mb-3">
                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span class="text-muted">Membership Starts At:</span>
                            <strong class="text-end" id="modalMembershipStartsAtText">—</strong>
                        </div>
                        <div class="d-flex justify-content-between gap-3">
                            <span class="text-muted">Membership Ends At:</span>
                            <strong class="text-end" id="modalMembershipEndsAtText">—</strong>
                        </div>
                    </div>
                    <p class="mb-0">Are you sure you want to approve the selected peers?</p>
                    <input type="hidden" name="membership_starts_at" id="modalMembershipStartsAt">
                    <input type="hidden" name="membership_ends_at" id="modalMembershipEndsAt">
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve</button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
    // Real database users list passed from controller
    const members = @json($allUsersJson);
    const upcomingEventsCount = {{ (int) ($upcomingEventsCount ?? 0) }};
    const nextUpcomingEventTitle = @json($nextUpcomingEventTitle ?? '');
    
    function initials(n){
      if (!n || typeof n !== 'string') return '?';
      const clean = n.trim();
      if (!clean) return '?';
      const parts = clean.split(/\s+/).filter(Boolean);
      if (parts.length === 0) return '?';
      if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function parseCustomDate(dStr) {
      if (!dStr || dStr === '—' || dStr === '-' || dStr === 'Never') return null;
      if (/^\d{4}-\d{2}-\d{2}$/.test(dStr)) {
        const parts = dStr.split('-');
        return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
      }
      const m = String(dStr).match(/^(\d{1,2})\s+([A-Za-z]{3})\s+(\d{4})/);
      if (m) {
        const months = {jan:0, feb:1, mar:2, apr:3, may:4, jun:5, jul:6, aug:7, sep:8, oct:9, nov:10, dec:11};
        const mon = months[m[2].toLowerCase()];
        if (mon !== undefined) {
          return new Date(parseInt(m[3], 10), mon, parseInt(m[1], 10));
        }
      }
      const parsed = new Date(dStr);
      if (!isNaN(parsed.getTime())) return parsed;
      return null;
    }

    function renderAvatar(m, sizeClass = 'w-8 h-8 text-[11px]') {
      const avatarUrl = (m && m.avatar && typeof m.avatar === 'string') ? m.avatar.trim() : '';
      const initialText = initials(m ? m.name : '');
      const bgColor = (m && m.color) ? m.color : '#6366F1';

      if (!avatarUrl) {
        return `<div class="avatar ${sizeClass} rounded-full font-bold flex items-center justify-center text-white flex-none" style="background:${bgColor}">${initialText}</div>`;
      }

      const safeUrl = avatarUrl.replace(/"/g, '&quot;');
      const safeName = String((m && m.name) || '').replace(/"/g, '&quot;');

      return `<img src="${safeUrl}" alt="${safeName}" class="${sizeClass} rounded-full object-cover flex-none" onerror="this.onerror=null; this.outerHTML='<div class=\\'avatar ${sizeClass} rounded-full font-bold flex items-center justify-center text-white flex-none\\' style=\\'background:${bgColor}\\'>${initialText}</div>';" />`;
    }
    
    let selected = new Set();
    let sortState = {col:null, dir:1};
    let visibleCols = new Set(['email','mobile','company','industry','circle','city','country','role','membership','status','payment','activity','coins','lastlogin','lastpayment','renewals','referralcol','pendingamt','lastevent']);
    
    const colToPropMap = {
      mid: 'mid',
      name: 'name',
      mobile: 'mobile',
      email: 'email',
      company: 'company',
      industry: 'industry',
      city: 'city',
      country: 'country',
      circle: 'circle',
      role: 'role',
      membership: 'membership',
      status: 'status',
      payment: 'payment',
      activity: 'activity',
      coins: 'coins',
      lastlogin: 'lastLogin',
      lastpayment: 'lastPaymentDate',
      renewals: 'renewalCount',
      referralcol: 'referrals',
      pendingamt: 'pendingAmount',
      lastevent: 'lastEvent'
    };

    const propToColMap = {
      mid: 'mid',
      name: 'name',
      mobile: 'mobile',
      email: 'email',
      company: 'company',
      industry: 'industry',
      city: 'city',
      country: 'country',
      circle: 'circle',
      role: 'role',
      membership: 'membership',
      status: 'status',
      payment: 'payment',
      activity: 'activity',
      coins: 'coins',
      lastLogin: 'lastlogin',
      lastPaymentDate: 'lastpayment',
      renewalCount: 'renewals',
      referrals: 'referralcol',
      pendingAmount: 'pendingamt',
      lastEvent: 'lastevent'
    };
    
    let currentFilters = {
      kpi: '',
      globalSearch: '',
      industry: '',
      city: '',
      country: '',
      circle: '',
      role: '',
      membership: '',
      status: '',
      payment: '',
      view: 'all',
      quick: '',
      dateStart: '',
      dateEnd: '',
      mid: '',
      name: '',
      mobile: '',
      email: '',
      company: '',
      activity: '',
      coins: '',
      lastLogin: '',
      lastPaymentDate: '',
      renewalCount: '',
      referrals: '',
      pendingAmount: '',
      lastEvent: ''
    };
    
    // Pagination settings
    let currentPage = 1;
    let rowsPerPage = 25;
    let isInfinite = false;

    function scrollToResults() {
      const el = document.getElementById('search-input') || document.getElementById('main-table');
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    function formatCompactCurrency(val) {
      const num = Number(val) || 0;
      if (num >= 10000000) return '₹' + (num / 10000000).toFixed(2) + 'Cr';
      if (num >= 100000) return '₹' + (num / 100000).toFixed(2) + 'L';
      if (num >= 1000) return '₹' + (num / 1000).toFixed(1) + 'K';
      return '₹' + num.toLocaleString();
    }

    function filterByKpi(kpiType) {
      if (kpiType === 'total' || currentFilters.kpi === kpiType) {
        currentFilters.kpi = '';
      } else {
        currentFilters.kpi = kpiType;
      }
      // Clear quick filter chips when selecting a KPI
      currentFilters.quick = '';
      document.querySelectorAll('.chip').forEach(c => c.classList.remove('chip-active'));

      document.querySelectorAll('.kpi-card, .mini-widget').forEach(el => el.classList.remove('active-kpi'));
      
      if (currentFilters.kpi) {
        document.getElementById(`kpi-card-${currentFilters.kpi}`)?.classList.add('active-kpi');
        document.getElementById(`widget-card-${currentFilters.kpi}`)?.classList.add('active-kpi');
      } else {
        document.getElementById('kpi-card-total')?.classList.add('active-kpi');
      }

      currentPage = 1;
      applyFilters();
      scrollToResults();
    }

    function toggleSummary() {
      const wrapper = document.getElementById('kpi-summary');
      const icon = document.getElementById('summary-toggle-icon');
      const label = document.getElementById('summary-toggle-label');
      if (wrapper && icon && label) {
        const isCollapsed = wrapper.classList.toggle('hidden');
        label.textContent = isCollapsed ? 'Expand' : 'Collapse';
        icon.style.transform = isCollapsed ? 'rotate(180deg)' : 'rotate(0deg)';
      }
    }

    function toggleSavedFilters() {
      document.getElementById('saved-filters-menu')?.classList.toggle('hidden');
    }
    
    function toggleColumnsMenu() {
      document.getElementById('columns-menu')?.classList.toggle('hidden');
    }

    function setView(btn) {
      document.querySelectorAll('.tab-underline').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentFilters.view = btn.getAttribute('data-view');
      currentFilters.kpi = '';
      currentFilters.quick = '';
      document.querySelectorAll('.chip').forEach(c => c.classList.remove('chip-active'));
      document.querySelectorAll('.kpi-card, .mini-widget').forEach(el => el.classList.remove('active-kpi'));
      currentPage = 1;
      applyFilters();
      scrollToResults();
    }

    function quickFilter(type) {
      const chip = document.getElementById(`qf-${type}`);
      if (chip) {
        const isActive = chip.classList.toggle('chip-active');
        currentFilters.quick = isActive ? type : '';
        currentFilters.kpi = '';
        document.querySelectorAll('.kpi-card, .mini-widget').forEach(el => el.classList.remove('active-kpi'));
        ['expiring', 'new7', 'nopayment', 'inactive'].forEach(t => {
          if (t !== type) {
            document.getElementById(`qf-${t}`)?.classList.remove('chip-active');
          }
        });
      }
      currentPage = 1;
      applyFilters();
      scrollToResults();
    }

    function applySavedFilter(type) {
      clearFilters();
      if (type === 'gold') {
        currentFilters.membership = 'Gold';
      } else if (type === 'expiring') {
        currentFilters.quick = 'expiring';
        document.getElementById('qf-expiring')?.classList.add('chip-active');
      } else if (type === 'inactive') {
        currentFilters.quick = 'inactive';
        document.getElementById('qf-inactive')?.classList.add('chip-active');
      } else if (type === 'nopayment') {
        currentFilters.quick = 'nopayment';
        document.getElementById('qf-nopayment')?.classList.add('chip-active');
      } else if (type === 'new7') {
        currentFilters.quick = 'new7';
        document.getElementById('qf-new7')?.classList.add('chip-active');
      }
      document.getElementById('saved-filters-menu')?.classList.add('hidden');
      applyFilters();
      scrollToResults();
    }

    function formatDateDMY(date) {
      const day = String(date.getDate()).padStart(2, '0');
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      const month = months[date.getMonth()];
      const year = date.getFullYear();
      return `${day} ${month} ${year}`;
    }

    function formatMonthMY(date) {
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      const month = months[date.getMonth()];
      const year = date.getFullYear();
      return `${month} ${year}`;
    }

    function isRecentJoiner(m) {
      return isRecentJoinerDays(m, 30);
    }

    function isRecentJoinerDays(m, days = 7) {
      if (m.isJoinedLast7 && days === 7) return true;
      if (m.isJoinedLast30 && days === 30) return true;
      if (!m.joinedRaw && (!m.joined || m.joined === '—' || m.joined === '-')) {
        return false;
      }
      if (m.daysSinceJoined !== undefined && m.daysSinceJoined !== null) {
        return m.daysSinceJoined >= 0 && m.daysSinceJoined <= days;
      }
      if (m.joinedRaw) {
        const d = new Date(m.joinedRaw + 'T00:00:00');
        if (!isNaN(d.getTime())) {
          const cutoff = new Date();
          cutoff.setDate(cutoff.getDate() - days);
          return d >= cutoff;
        }
      }
      const joinedDate = parseCustomDate(m.joined);
      if (!joinedDate) return false;
      const cutoff = new Date();
      cutoff.setDate(cutoff.getDate() - days);
      return joinedDate >= cutoff;
    }

    function isMemberInactive30(m) {
      if (m.isInactive30 !== undefined) {
        return m.isInactive30;
      }
      if (m.status && m.status.n && m.status.n.toLowerCase() === 'inactive') return true;
      const thirtyDaysAgo = new Date();
      thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

      if (m.daysSinceLastLogin !== undefined && m.daysSinceLastLogin !== null) {
        return m.daysSinceLastLogin >= 30;
      }
      if (m.lastLoginRaw) {
        const loginDate = new Date(m.lastLoginRaw + 'T00:00:00');
        if (!isNaN(loginDate.getTime())) {
          return loginDate <= thirtyDaysAgo;
        }
      } else if (m.lastLogin && m.lastLogin !== '—' && m.lastLogin !== 'Never') {
        const loginDate = parseCustomDate(m.lastLogin);
        if (loginDate) {
          return loginDate <= thirtyDaysAgo;
        }
      }

      // If user never logged in, only inactive if registered 30+ days ago
      if (m.daysSinceJoined !== undefined && m.daysSinceJoined !== null) {
        return m.daysSinceJoined >= 30;
      }
      if (m.joinedRaw) {
        const joinedDate = new Date(m.joinedRaw + 'T00:00:00');
        if (!isNaN(joinedDate.getTime())) {
          return joinedDate <= thirtyDaysAgo;
        }
      }
      const joinedDate = parseCustomDate(m.joined);
      if (joinedDate) {
        return joinedDate <= thirtyDaysAgo;
      }
      return false;
    }

    function updateSummaryMetrics() {
      const totalNum = members.length;
      const totalNumEl = document.getElementById('kpi-total-num');
      const resultTotalEl = document.getElementById('result-total');
      if (totalNumEl) totalNumEl.textContent = totalNum;
      if (resultTotalEl) resultTotalEl.textContent = totalNum;

      const activeNum = members.filter(m => (m.status?.n || '').toLowerCase() === 'active').length;
      const activeNumEl = document.getElementById('kpi-active-num');
      if (activeNumEl) activeNumEl.textContent = activeNum;

      // New Today / Month
      const todayStr = formatDateDMY(new Date());
      const thisMonthStr = formatMonthMY(new Date());
      const today = new Date();
      
      const newToday = members.filter(m => {
        const joinedDate = parseCustomDate(m.joinedRaw || m.joined);
        return joinedDate && joinedDate.getDate() === today.getDate() && joinedDate.getMonth() === today.getMonth() && joinedDate.getFullYear() === today.getFullYear();
      }).length;
      
      const newMonth = members.filter(m => {
        const joinedDate = parseCustomDate(m.joinedRaw || m.joined);
        return joinedDate && joinedDate.getMonth() === today.getMonth() && joinedDate.getFullYear() === today.getFullYear();
      }).length;
      
      const newTodayEl = document.getElementById('kpi-newtoday-num');
      const newTodayTrendEl = document.getElementById('kpi-newtoday-trend');
      const newMonthEl = document.getElementById('kpi-newmonth-num');
      const newMonthTrendEl = document.getElementById('kpi-newmonth-trend');
      if (newTodayEl) newTodayEl.textContent = newToday;
      if (newTodayTrendEl) newTodayTrendEl.textContent = `+${newToday}`;
      if (newMonthEl) newMonthEl.textContent = newMonth;
      if (newMonthTrendEl) newMonthTrendEl.textContent = `+${newMonth}`;
      
      // Renewals
      const renewedToday = members.filter(m => (m.lastPaymentDate && m.lastPaymentDate === todayStr) || (m.renewalCount > 0 && m.lastPaymentDate === todayStr)).length;
      const renewedMonth = members.filter(m => (m.lastPaymentDate && m.lastPaymentDate.endsWith(thisMonthStr)) || ((m.payment?.n || '').toLowerCase() === 'paid' && m.lastPaymentDate && m.lastPaymentDate.endsWith(thisMonthStr))).length;
      const renewTodayEl = document.getElementById('kpi-renewtoday-num');
      const renewMonthEl = document.getElementById('kpi-renewmonth-num');
      if (renewTodayEl) renewTodayEl.textContent = renewedToday;
      if (renewMonthEl) renewMonthEl.textContent = renewedMonth;

      // Expiring in 7 Days
      const expiring7 = members.filter(m => m.isExpiring7 || (typeof m.expiryDays === 'number' && m.expiryDays >= 0 && m.expiryDays <= 7)).length;
      const expiring7El = document.getElementById('kpi-expiring7-num');
      if (expiring7El) expiring7El.textContent = expiring7;

      // Expired
      const expired = members.filter(m => m.isExpired || (m.status?.n || '').toLowerCase() === 'expired' || (typeof m.expiryDays === 'number' && m.expiryDays < 0)).length;
      const expiredEl = document.getElementById('kpi-expired-num');
      if (expiredEl) expiredEl.textContent = expired;

      // Pending approvals
      const pending = members.filter(m => {
        const s = (m.status?.n || '').toLowerCase();
        return s.includes('pending') || s.includes('awaiting');
      }).length;
      const approvalsKpiEl = document.getElementById('kpi-approvals-num');
      const approvalsWidgetEl = document.getElementById('widget-pending-approvals');
      if (approvalsKpiEl) approvalsKpiEl.textContent = pending;
      if (approvalsWidgetEl) approvalsWidgetEl.textContent = pending;

      // Pending Amount Outstanding
      const pendingAmt = members.reduce((sum, m) => sum + (parseFloat(m.pendingAmount) || 0), 0);
      const pendingPayEl = document.getElementById('kpi-pendingpay-num');
      if (pendingPayEl) pendingPayEl.textContent = formatCompactCurrency(pendingAmt);

      // Estimated Revenue
      const totalRevenue = members.filter(m => (m.payment?.n || '').toLowerCase() === 'paid').length * 15000;
      const revenueEl = document.getElementById('kpi-revenue-num');
      if (revenueEl) revenueEl.textContent = formatCompactCurrency(totalRevenue);

      // Churned
      const churnEl = document.getElementById('kpi-churn-num');
      if (churnEl) churnEl.textContent = expired;

      // Birthdays Today (evaluated in local timezone)
      const now = new Date();
      const localMD = String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
      const bdays = members.filter(m => m.dob && m.dob.slice(5, 10) === localMD).map(m => m.name);
      const totalDobListed = members.filter(m => m.dob).length;
      const bdayEl = document.getElementById('widget-birthdays');
      if (bdayEl) {
        if (bdays.length > 0) {
          bdayEl.textContent = bdays.length <= 2 ? bdays.join(', ') : `${bdays.length} today (${bdays.slice(0, 2).join(', ')}...)`;
        } else {
          bdayEl.textContent = totalDobListed > 0 ? `0 today (${totalDobListed} listed)` : 'No birthdays today';
        }
      }

      // Upcoming Events
      const eventsEl = document.getElementById('widget-events');
      if (eventsEl) {
        if (typeof upcomingEventsCount === 'number' && upcomingEventsCount > 0) {
          eventsEl.textContent = nextUpcomingEventTitle ? `${upcomingEventsCount} scheduled (${nextUpcomingEventTitle})` : `${upcomingEventsCount} scheduled`;
        } else {
          eventsEl.textContent = 'No upcoming events';
        }
      }

      // Recently Joined Count (30d)
      const recent30List = members.filter(m => (m.joinedRaw || (m.joined && m.joined !== '—')) && (m.isJoinedLast30 || m.isJoinedLast7 || isRecentJoinerDays(m, 30)));
      const recentEl = document.getElementById('widget-recent');
      if (recentEl) {
        recentEl.textContent = recent30List.length > 0 ? `${recent30List.length} joined (30d)` : '0 joined (30d)';
      }

      // Top Circle
      const circleCounts = {};
      members.forEach(m => {
        const c = (m.circle || '').trim();
        if (c && c !== '—') circleCounts[c] = (circleCounts[c] || 0) + 1;
      });
      const topCircles = Object.entries(circleCounts).sort((a, b) => b[1] - a[1]);
      const topCircleEl = document.getElementById('widget-top-circle');
      if (topCircleEl) {
        topCircleEl.textContent = topCircles.length > 0 ? `${topCircles[0][0]} (${topCircles[0][1]})` : 'None';
      }

      // Top Industry
      const indCounts = {};
      members.forEach(m => {
        const ind = (m.industry || '').trim();
        if (ind && ind !== '—' && ind !== '-' && ind.toLowerCase() !== 'none') {
          ind.split(',').forEach(item => {
            const clean = item.trim();
            if (clean && clean !== '—' && clean !== '-' && clean.toLowerCase() !== 'none') {
              indCounts[clean] = (indCounts[clean] || 0) + 1;
            }
          });
        }
      });
      const topInds = Object.entries(indCounts).sort((a, b) => b[1] - a[1]);
      const topIndEl = document.getElementById('widget-top-industry');
      const topIndCard = document.getElementById('widget-card-topindustry');
      if (topIndCard) {
        if (topInds.length > 0) {
          topIndCard.classList.remove('hidden');
          if (topIndEl) topIndEl.textContent = `${topInds[0][0]} (${topInds[0][1]})`;
        } else {
          topIndCard.classList.add('hidden');
          if (topIndEl) topIndEl.textContent = 'None';
        }
      }
    }

    function buildHeaderDropdowns() {
      buildSearchableHeaderDropdown('mid', 'mid', 'Member ID');
      buildSearchableHeaderDropdown('name', 'name', 'Member');
      buildSearchableHeaderDropdown('mobile', 'mobile', 'Mobile');
      buildSearchableHeaderDropdown('email', 'email', 'Email');
      buildSearchableHeaderDropdown('company', 'company', 'Company');
      buildSearchableHeaderDropdown('industry', 'industry', 'Industry');
      buildSearchableHeaderDropdown('city', 'city', 'City');
      buildSearchableHeaderDropdown('country', 'country', 'Country');
      buildSearchableHeaderDropdown('circle', 'circle', 'Circle');
      buildSearchableHeaderDropdown('role', 'role', 'Role');
      buildSearchableHeaderDropdown('membership', 'membership', 'Membership');
      buildSearchableHeaderDropdown('status', 'status', 'Status');
      buildSearchableHeaderDropdown('payment', 'payment', 'Payment');
      buildSearchableHeaderDropdown('activity', 'activity', 'Activity');
      buildSearchableHeaderDropdown('coins', 'coins', 'Coins');
      buildSearchableHeaderDropdown('lastlogin', 'lastLogin', 'Last Login');
      buildSearchableHeaderDropdown('lastpayment', 'lastPaymentDate', 'Last Payment');
      buildSearchableHeaderDropdown('renewals', 'renewalCount', 'Renewals');
      buildSearchableHeaderDropdown('referralcol', 'referrals', 'Referrals');
      buildSearchableHeaderDropdown('pendingamt', 'pendingAmount', 'Pending Amt');
      buildSearchableHeaderDropdown('lastevent', 'lastEvent', 'Last Event Joined');
    }
    
    function buildSearchableHeaderDropdown(colId, propName, label) {
      let uniqueValues;
      if (propName === 'status') {
        uniqueValues = [...new Set(members.map(m => m.status.n))].sort();
      } else if (propName === 'payment') {
        uniqueValues = [...new Set(members.map(m => m.payment.n))].sort();
      } else if (propName === 'lastLogin') {
        uniqueValues = [...new Set(members.map(m => m.lastLogin))].sort();
      } else if (propName === 'lastPaymentDate') {
        uniqueValues = [...new Set(members.map(m => m.lastPaymentDate))].sort();
      } else if (propName === 'renewalCount') {
        uniqueValues = [...new Set(members.map(m => m.renewalCount))].sort((a,b)=>a-b);
      } else if (propName === 'referrals') {
        uniqueValues = [...new Set(members.map(m => m.referrals))].sort((a,b)=>a-b);
      } else if (propName === 'pendingAmount') {
        uniqueValues = [...new Set(members.map(m => m.pendingAmount))].sort((a,b)=>a-b);
      } else if (propName === 'lastEvent') {
        uniqueValues = [...new Set(members.map(m => m.lastEvent))].sort();
      } else if (propName === 'activity' || propName === 'coins') {
        uniqueValues = [...new Set(members.map(m => m[propName]))].sort((a,b)=>a-b);
      } else {
        uniqueValues = [...new Set(members.map(m => m[propName] || ''))].sort();
      }
      uniqueValues = uniqueValues.filter(Boolean).map(v => String(v));
      populateSearchableDropdown(colId, propName, uniqueValues, label);
    }
    
    function populateSearchableDropdown(colId, propName, list, label) {
      const container = document.getElementById(`h-menu-${colId}`);
      if (!container) return;
      
      const generateOptionsHTML = (items) => {
        let html = `<button onclick="selectHeaderOption('${propName}', '')" class="w-full text-left px-2 py-1 rounded text-[11.5px] t2 hover:surface-3 hover:t1 flex items-center justify-between border-none bg-transparent cursor-pointer">
          <span>All ${label}</span>
          ${!currentFilters[propName] ? '<span class="text-indigo-500 font-bold">✓</span>' : ''}
        </button>`;
        
        html += items.map(val => {
          const isSelected = currentFilters[propName] === val;
          return `<button onclick="selectHeaderOption('${propName}', '${val.replace(/'/g, "\\'")}')" class="w-full text-left px-2 py-1 rounded text-[11.5px] t2 hover:surface-3 hover:t1 flex items-center justify-between border-none bg-transparent cursor-pointer">
            <span class="truncate">${val}</span>
            ${isSelected ? '<span class="text-indigo-500 font-bold">✓</span>' : ''}
          </button>`;
        }).join('');
        return html;
      };
      
      container.innerHTML = generateOptionsHTML(list);
      if (!window.headerDropdownLists) window.headerDropdownLists = {};
      window.headerDropdownLists[propName] = { list, label, containerId: `h-menu-${colId}` };
    }
    
    function filterHeaderSearch(colId, query) {
      const propName = colToPropMap[colId] || colId;
      const data = window.headerDropdownLists[propName];
      if (!data) return;
      const filtered = data.list.filter(val => val.toLowerCase().includes(query.toLowerCase()));
      const container = document.getElementById(data.containerId);
      if (!container) return;
      
      let html = `<button onclick="selectHeaderOption('${propName}', '')" class="w-full text-left px-2 py-1 rounded text-[11.5px] t2 hover:surface-3 hover:t1 flex items-center justify-between border-none bg-transparent cursor-pointer">
        <span>All ${data.label}</span>
        ${!currentFilters[propName] ? '<span class="text-indigo-500 font-bold">✓</span>' : ''}
      </button>`;
      
      html += filtered.map(val => {
        const isSelected = currentFilters[propName] === val;
        return `<button onclick="selectHeaderOption('${propName}', '${val.replace(/'/g, "\\'")}')" class="w-full text-left px-2 py-1 rounded text-[11.5px] t2 hover:surface-3 hover:t1 flex items-center justify-between border-none bg-transparent cursor-pointer">
          <span class="truncate">${val}</span>
          ${isSelected ? '<span class="text-indigo-500 font-bold">✓</span>' : ''}
        </button>`;
      }).join('');
      container.innerHTML = html;
    }
    
    function selectHeaderOption(propName, value) {
      const colId = propToColMap[propName] || propName;
      const input = document.getElementById(`h-search-${colId}`);
      if (input) {
        input.value = value;
      }
      currentFilters[propName] = value;
      applyFilters(true);
      buildHeaderDropdowns();
      document.querySelectorAll('.header-dropdown-menu').forEach(el => el.classList.add('hidden'));
    }
    
    function showSearchDropdown(colId, event) {
      event.stopPropagation();
      document.querySelectorAll('.header-dropdown-menu').forEach(el => {
        if (el.id !== `h-menu-${colId}`) el.classList.add('hidden');
      });
      const menu = document.getElementById(`h-menu-${colId}`);
      if (menu) {
        menu.classList.remove('hidden');
        const inputVal = document.getElementById(`h-search-${colId}`).value;
        filterHeaderSearch(colId, inputVal);
      }
    }
    
    function filterSearchDropdown(colId, value) {
      const propName = colToPropMap[colId] || colId;
      currentFilters[propName] = value;
      applyFilters(true);
      filterHeaderSearch(colId, value);
    }
    
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.header-dropdown-container') && !e.target.closest('input[id^="h-search-"]')) {
        document.querySelectorAll('.header-dropdown-menu').forEach(el => el.classList.add('hidden'));
      }
      if (!e.target.closest('.relative') && !e.target.closest('.chip')) {
        document.getElementById('saved-filters-menu')?.classList.add('hidden');
        document.getElementById('columns-menu')?.classList.add('hidden');
        document.getElementById('export-menu')?.classList.add('hidden');
      }
    });
    
    function statusBadge(s){
      const map = {success:'var(--success)', warning:'var(--warning)', danger:'var(--danger)', 'text-3':'var(--text-3)'};
      const soft = {success:'var(--success-soft)', warning:'var(--warning-soft)', danger:'var(--danger-soft)', 'text-3':'var(--surface-3)'};
      return `<span class="badge" style="background:${soft[s.c]}; color:${map[s.c]}"><span class="badge-dot" style="background:${map[s.c]}"></span>${s.n}</span>`;
    }
    function membershipBadge(m){
      const colors = {Gold:'#F59E0B', Platinum:'#8B5CF6', Silver:'#94A3B8', Standard:'#6366F1'};
      const color = colors[m] || '#6366F1';
      return `<span class="badge" style="background:${color}22; color:${color}">${m}</span>`;
    }
    function activityBar(v){
      const color = v>70?'var(--success)': v>40? 'var(--warning)':'var(--danger)';
      return `<div class="flex items-center gap-2"><div class="w-16 h-1.5 rounded-full surface-3 overflow-hidden"><div class="h-full rounded-full" style="width:${v}%; background:${color}"></div></div><span class="text-[11.5px] font-mono t2 tnum">${v}</span></div>`;
    }
    
    function rowHTML(m){
      return `
      <tr class="row-anim data-row cursor-pointer ${selected.has(m.id)?'selected':''}" data-id="${m.id}" onclick="openDrawer('${m.id}')">
        <td class="col-sticky surface border-b border-r bs px-3 py-2.5 align-top" style="width:44px; min-width:44px; max-width:44px;" onclick="event.stopPropagation()">
          <input type="checkbox" class="row-check accent-indigo-500 w-4 h-4 rounded mt-1" ${selected.has(m.id)?'checked':''} onchange="toggleRow('${m.id}', this)"/>
        </td>
        <td class="col-sticky-mid surface border-b bs px-3 py-2.5 align-top font-mono font-medium text-[12.5px] t1">
          ${m.mid}
        </td>
        <td class="col-sticky-name surface border-b border-r bs px-3 py-2.5 align-top">
          <div class="flex items-center gap-2.5">
            ${renderAvatar(m, 'w-8 h-8 text-[11px]')}
            <div class="font-display font-medium text-indigo-500 hover:text-indigo-700 hover:underline transition">${m.name}</div>
          </div>
        </td>
        <td class="border-b bs px-3 py-2.5 align-top font-mono text-[12.5px] t2">${m.mobile || '—'}</td>
        <td class="border-b bs px-3 py-2.5 align-top t2"><div class="admin-grid-text-clamp">${m.email || '—'}</div></td>
        <td class="border-b bs px-3 py-2.5 align-top t2"><div class="admin-grid-text-clamp">${m.company || '—'}</div></td>
        <td class="border-b bs px-3 py-2.5 align-top t2"><div class="admin-grid-text-clamp">${m.industry || '—'}</div></td>
        <td class="border-b bs px-3 py-2.5 align-top t2"><div class="admin-grid-text-clamp">${m.city || '—'}</div></td>
        <td class="border-b bs px-3 py-2.5 align-top t2"><div class="admin-grid-text-clamp">${m.country || '—'}</div></td>
        <td class="border-b bs px-3 py-2.5 align-top t2"><div class="admin-grid-text-clamp">${m.circle || '—'}</div></td>
        <td class="border-b bs px-3 py-2.5 align-top t2"><div class="admin-grid-text-clamp">${m.role || '—'}</div></td>
        <td class="border-b bs px-3 py-2.5 align-top">${membershipBadge(m.membership)}</td>
        <td class="border-b bs px-3 py-2.5 align-top">${statusBadge(m.status)}</td>
        <td class="border-b bs px-3 py-2.5 align-top">${statusBadge(m.payment)}</td>
        <td class="border-b bs px-3 py-2.5 align-top">${activityBar(m.activity)}</td>
        <td class="border-b bs px-3 py-2.5 align-top text-right font-mono font-medium t1">${m.coins.toLocaleString()}</td>
        <td class="border-b bs px-3 py-2.5 align-top t2">${m.lastLogin}</td>
        <td class="border-b bs px-3 py-2.5 align-top t2">${m.lastPaymentDate}</td>
        <td class="border-b bs px-3 py-2.5 align-top text-right font-mono t2">${m.renewalCount}</td>
        <td class="border-b bs px-3 py-2.5 align-top text-right font-mono t2">${m.referrals}</td>
        <td class="border-b bs px-3 py-2.5 align-top text-right font-mono text-rose-500">${m.pendingAmount > 0 ? '₹' + m.pendingAmount.toLocaleString() : '—'}</td>
        <td class="border-b bs px-3 py-2.5 align-top t2"><div class="admin-grid-text-clamp">${m.lastEvent}</div></td>
        <td class="border-b bs px-3 py-2.5 text-center align-top" onclick="event.stopPropagation()">
          <div class="flex items-center justify-center gap-1.5">
            <a href="/admin/users/${m.id}/edit" class="px-2 py-1 rounded hover:surface-3 t2 hover:t1 transition border bs text-xs text-decoration-none">Edit</a>
          </div>
        </td>
      </tr>`;
    }
    
    function render(list){
      const tbody = document.getElementById('table-body');
      if (!tbody) return;
      
      // Client-side pagination slice
      const start = (currentPage - 1) * rowsPerPage;
      const end = isInfinite ? list.length : start + rowsPerPage;
      const paginatedList = isInfinite ? list : list.slice(start, end);
      
      tbody.innerHTML = paginatedList.map(rowHTML).join('');
      document.getElementById('result-num').textContent = list.length;
      
      document.querySelectorAll('#empty-state,#noresults-state,#loading-state').forEach(e=>e.classList.add('hidden'));
      document.getElementById('main-table').classList.remove('hidden');
      if(list.length===0){ document.getElementById('noresults-state').classList.remove('hidden'); document.getElementById('main-table').classList.add('hidden'); }
      
      updatePaginationControls(list.length);
    }
    
    function updatePaginationControls(totalItems) {
      const container = document.getElementById('pagination-controls-container');
      if (!container) return;
      
      if (isInfinite) {
        container.innerHTML = `<span class="text-xs t3">Showing all ${totalItems} records (Infinite Scroll)</span>`;
        return;
      }
      
      const totalPages = Math.ceil(totalItems / rowsPerPage) || 1;
      let html = `<button onclick="changePage(${currentPage - 1})" class="w-7 h-7 flex items-center justify-center rounded-md border bs t2 hover:t1 disabled:opacity-40" ${currentPage === 1 ? 'disabled' : ''}><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></button>`;
      
      for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
          html += `<button onclick="changePage(${i})" class="w-7 h-7 flex items-center justify-center rounded-md text-[11.5px] ${currentPage === i ? 'bg-accent text-white font-medium' : 'border bs t2 hover:t1'}">${i}</button>`;
        } else if (i === 2 || i === totalPages - 1) {
          html += `<span class="px-0.5 t3 text-xs">…</span>`;
        }
      }
      
      html += `<button onclick="changePage(${currentPage + 1})" class="w-7 h-7 flex items-center justify-center rounded-md border bs t2 hover:t1 disabled:opacity-40" ${currentPage === totalPages ? 'disabled' : ''}><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>`;
      container.innerHTML = html;
    }
    
    function changePage(page) {
      currentPage = page;
      applyFilters();
    }
    
    function changeRowsPerPage(val) {
      rowsPerPage = parseInt(val);
      currentPage = 1;
      applyFilters();
    }
    
    function toggleInfinite(checkbox) {
      isInfinite = checkbox.checked;
      currentPage = 1;
      applyFilters();
    }
    
    function applyFilters(resetPage = false){
      if (resetPage) {
        currentPage = 1;
      }
      const q = document.getElementById('search-input')?.value.toLowerCase() || '';
      
      let list = members.filter(m => {
        // Global search
        if(q && !(m.name.toLowerCase().includes(q) || m.email.toLowerCase().includes(q) || m.mid.toLowerCase().includes(q) || m.company.toLowerCase().includes(q) || m.city.toLowerCase().includes(q) || m.country.toLowerCase().includes(q) || m.circle.toLowerCase().includes(q) || m.industry.toLowerCase().includes(q))) return false;
        
        // KPI Summary Card Filters
        if (currentFilters.kpi) {
          const todayStr = formatDateDMY(new Date());
          const thisMonthStr = formatMonthMY(new Date());
          const joinedDate = parseCustomDate(m.joinedRaw || m.joined);
          const today = new Date();

          if (currentFilters.kpi === 'active') {
            if ((m.status?.n || '').toLowerCase() !== 'active') return false;
          } else if (currentFilters.kpi === 'newtoday') {
            const isToday = joinedDate && joinedDate.getDate() === today.getDate() && joinedDate.getMonth() === today.getMonth() && joinedDate.getFullYear() === today.getFullYear();
            if (!isToday) return false;
          } else if (currentFilters.kpi === 'newmonth') {
            const isThisMonth = joinedDate && joinedDate.getMonth() === today.getMonth() && joinedDate.getFullYear() === today.getFullYear();
            if (!isThisMonth) return false;
          } else if (currentFilters.kpi === 'renewtoday') {
            if (!m.lastPaymentDate || m.lastPaymentDate !== todayStr) return false;
          } else if (currentFilters.kpi === 'renewmonth') {
            const isRenewed = (m.lastPaymentDate && m.lastPaymentDate.endsWith(thisMonthStr)) || ((m.payment?.n || '').toLowerCase() === 'paid' && m.lastPaymentDate && m.lastPaymentDate.endsWith(thisMonthStr));
            if (!isRenewed) return false;
          } else if (currentFilters.kpi === 'expiring7') {
            if (!(m.isExpiring7 || (typeof m.expiryDays === 'number' && m.expiryDays >= 0 && m.expiryDays <= 7))) return false;
          } else if (currentFilters.kpi === 'expired') {
            if (!(m.isExpired || (m.status?.n || '').toLowerCase() === 'expired' || (typeof m.expiryDays === 'number' && m.expiryDays < 0))) return false;
          } else if (currentFilters.kpi === 'pendingpay') {
            const isDue = m.isPaymentOverdue || ['overdue', 'due', 'unpaid'].includes((m.payment?.n || '').toLowerCase()) || (parseFloat(m.pendingAmount) || 0) > 0;
            if (!isDue) return false;
          } else if (currentFilters.kpi === 'revenue') {
            if ((m.payment?.n || '').toLowerCase() !== 'paid' && (m.coins || 0) <= 0) return false;
          } else if (currentFilters.kpi === 'churn') {
            if (!(m.isExpired || (m.status?.n || '').toLowerCase() === 'expired' || (m.status?.n || '').toLowerCase() === 'inactive' || (typeof m.expiryDays === 'number' && m.expiryDays < 0))) return false;
          } else if (currentFilters.kpi === 'approvals') {
            const s = (m.status?.n || '').toLowerCase();
            if (!s.includes('pending') && !s.includes('awaiting')) return false;
          } else if (currentFilters.kpi === 'birthdays') {
            const now = new Date();
            const localMD = String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            const hasBdayToday = members.some(x => x.dob && x.dob.slice(5, 10) === localMD);
            if (hasBdayToday) {
              if (!m.dob || m.dob.slice(5, 10) !== localMD) return false;
            } else {
              if (!m.dob) return false;
            }
          } else if (currentFilters.kpi === 'recent') {
            if (!m.joinedRaw && (!m.joined || m.joined === '—')) return false;
            if (!m.isJoinedLast30 && !m.isJoinedLast7 && !isRecentJoinerDays(m, 30)) return false;
          } else if (currentFilters.kpi === 'topcircle') {
            const topCircleText = document.getElementById('widget-top-circle')?.textContent.trim() || '';
            const topCircleName = topCircleText.replace(/\s*\(\d+.*?\)$/, '').trim();
            if (!topCircleName || topCircleName === 'None' || topCircleName === '-') {
              return false;
            } else {
              if (!m.circle || m.circle.trim().toLowerCase() !== topCircleName.toLowerCase()) return false;
            }
          } else if (currentFilters.kpi === 'topindustry') {
            const topIndText = document.getElementById('widget-top-industry')?.textContent.trim() || '';
            const topIndName = topIndText.replace(/\s*\(\d+.*?\)$/, '').trim();
            if (!topIndName || topIndName === 'None' || topIndName === '-') {
              return false;
            } else {
              if (!m.industry || m.industry === '—' || m.industry === '-' || m.industry.toLowerCase() === 'none') return false;
              const userInds = m.industry.split(',').map(s => s.trim().toLowerCase());
              if (!userInds.includes(topIndName.toLowerCase()) && !m.industry.toLowerCase().includes(topIndName.toLowerCase())) return false;
            }
          }
        }

        // Saved view tabs
        if (currentFilters.view === 'unity' && m.membership !== 'Only Unity Peer' && m.membership !== 'Global Peer' && m.membership !== 'Gold') return false;
        if (currentFilters.view === 'circles' && m.memberType !== 'circle_peer') return false;
        if (currentFilters.view === 'multiple' && !m.membership.toLowerCase().includes('multi circle')) return false;
        if (currentFilters.view === 'free' && m.memberType !== 'free') return false;
        if (currentFilters.view === 'vip' && m.membership !== 'Platinum') return false;
        if (currentFilters.view === 'pending' && !((m.status?.n || '').toLowerCase().includes('pending') || (m.status?.n || '').toLowerCase().includes('awaiting'))) return false;
        if (currentFilters.view === 'expiring' && !(m.isExpiring30 || (typeof m.expiryDays === 'number' && m.expiryDays >= 0 && m.expiryDays <= 60))) return false;
        if (currentFilters.view === 'new') {
          const joinedDate = parseCustomDate(m.joinedRaw || m.joined);
          const isThisMonth = joinedDate && joinedDate.getMonth() === today.getMonth() && joinedDate.getFullYear() === today.getFullYear();
          if (!isThisMonth) return false;
        }

        // Quick filter chips
        if (currentFilters.quick === 'expiring') {
          if (!(m.isExpiring30 || (typeof m.expiryDays === 'number' && m.expiryDays >= 0 && m.expiryDays <= 30))) return false;
        }
        if (currentFilters.quick === 'new7') {
          const joinedDate = parseCustomDate(m.joinedRaw || m.joined);
          if (!joinedDate) return false;
          const sevenDaysAgo = new Date();
          sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
          sevenDaysAgo.setHours(0, 0, 0, 0);
          if (joinedDate < sevenDaysAgo) return false;
        }
        if (currentFilters.quick === 'nopayment') {
          const payName = (m.payment?.n || '').toLowerCase();
          const isOverdue = m.isPaymentOverdue === true || ['overdue', 'due', 'unpaid', 'pending'].includes(payName) || (parseFloat(m.pendingAmount) || 0) > 0;
          if (!isOverdue) return false;
        }
        if (currentFilters.quick === 'inactive') {
          if (!m.isInactive30 && !isMemberInactive30(m)) return false;
        }

        // Joined dates filter inputs
        const joinedStart = document.getElementById('f-joined-start')?.value;
        const joinedEnd = document.getElementById('f-joined-end')?.value;
        if (joinedStart || joinedEnd) {
          const joinedDate = parseCustomDate(m.joinedRaw || m.joined);
          if (!joinedDate) return false;
          joinedDate.setHours(0,0,0,0);
          if (joinedStart) {
            const startDate = parseCustomDate(joinedStart);
            if (startDate) {
              startDate.setHours(0,0,0,0);
              if (joinedDate < startDate) return false;
            }
          }
          if (joinedEnd) {
            const endDate = parseCustomDate(joinedEnd);
            if (endDate) {
              endDate.setHours(23,59,59,999);
              if (joinedDate > endDate) return false;
            }
          }
        }

        // Header column filters
        if(currentFilters.industry && !m.industry.toLowerCase().includes(currentFilters.industry.toLowerCase())) return false;
        if(currentFilters.city && !m.city.toLowerCase().includes(currentFilters.city.toLowerCase())) return false;
        if(currentFilters.country && !m.country.toLowerCase().includes(currentFilters.country.toLowerCase())) return false;
        if(currentFilters.circle && !m.circle.toLowerCase().includes(currentFilters.circle.toLowerCase())) return false;
        if(currentFilters.role && !m.role.toLowerCase().includes(currentFilters.role.toLowerCase())) return false;
        if(currentFilters.membership && !m.membership.toLowerCase().includes(currentFilters.membership.toLowerCase())) return false;
        if(currentFilters.status && !m.status.n.toLowerCase().includes(currentFilters.status.toLowerCase())) return false;
        if(currentFilters.payment && !m.payment.n.toLowerCase().includes(currentFilters.payment.toLowerCase())) return false;
    
        if(currentFilters.mid && !m.mid.toLowerCase().includes(currentFilters.mid.toLowerCase())) return false;
        if(currentFilters.name && !m.name.toLowerCase().includes(currentFilters.name.toLowerCase())) return false;
        if(currentFilters.mobile && !m.mobile.toLowerCase().includes(currentFilters.mobile.toLowerCase())) return false;
        if(currentFilters.email && !m.email.toLowerCase().includes(currentFilters.email.toLowerCase())) return false;
        if(currentFilters.company && !m.company.toLowerCase().includes(currentFilters.company.toLowerCase())) return false;
        if(currentFilters.activity && !String(m.activity).toLowerCase().includes(currentFilters.activity.toLowerCase())) return false;
        if(currentFilters.coins && !String(m.coins).toLowerCase().includes(currentFilters.coins.toLowerCase())) return false;
        if(currentFilters.lastLogin && !m.lastLogin.toLowerCase().includes(currentFilters.lastLogin.toLowerCase())) return false;
        if(currentFilters.lastPaymentDate && !m.lastPaymentDate.toLowerCase().includes(currentFilters.lastPaymentDate.toLowerCase())) return false;
        if(currentFilters.renewalCount && !String(m.renewalCount).toLowerCase().includes(currentFilters.renewalCount.toLowerCase())) return false;
        if(currentFilters.referrals && !String(m.referrals).toLowerCase().includes(currentFilters.referrals.toLowerCase())) return false;
        if(currentFilters.pendingAmount && !String(m.pendingAmount).toLowerCase().includes(currentFilters.pendingAmount.toLowerCase())) return false;
        if(currentFilters.lastEvent && !m.lastEvent.toLowerCase().includes(currentFilters.lastEvent.toLowerCase())) return false;
    
        return true;
      });
      
      if(sortState.col){
        list = list.slice().sort((a,b)=>{
          let va=a[sortState.col], vb=b[sortState.col];
          if(typeof va==='string') return va.localeCompare(vb)*sortState.dir;
          return (va-vb)*sortState.dir;
        });
      } else if (currentFilters.kpi === 'recent') {
        list = list.slice().sort((a, b) => {
          if (a.joinedRaw && b.joinedRaw) return b.joinedRaw.localeCompare(a.joinedRaw);
          return (a.daysSinceJoined ?? 999) - (b.daysSinceJoined ?? 999);
        });
      }
      render(list);
    }
    
    function sortBy(col){
      sortState.dir = (sortState.col===col) ? -sortState.dir : 1;
      sortState.col = col;
      applyFilters();
    }
    
    function clearFilters(){
      const globalSearch = document.getElementById('search-input');
      if (globalSearch) globalSearch.value='';
      
      const startInput = document.getElementById('f-joined-start');
      const endInput = document.getElementById('f-joined-end');
      if (startInput) startInput.value = '';
      if (endInput) endInput.value = '';

      ['mid', 'name', 'mobile', 'email', 'company', 'industry', 'city', 'country', 'circle', 'role', 'membership', 'status', 'payment', 'activity', 'coins', 'lastlogin', 'lastpayment', 'renewals', 'referralcol', 'pendingamt', 'lastevent'].forEach(col => {
        const input = document.getElementById(`h-search-${col}`);
        if (input) input.value = '';
      });

      document.querySelectorAll('.chip-active').forEach(chip => chip.classList.remove('chip-active'));
      document.querySelectorAll('.kpi-card, .mini-widget').forEach(el => el.classList.remove('active-kpi'));
      
      currentFilters = {
        kpi: '',
        globalSearch: '',
        industry: '',
        city: '',
        country: '',
        circle: '',
        role: '',
        membership: '',
        status: '',
        payment: '',
        view: 'all',
        quick: '',
        dateStart: '',
        dateEnd: '',
        mid: '',
        name: '',
        mobile: '',
        email: '',
        company: '',
        activity: '',
        coins: '',
        lastLogin: '',
        lastPaymentDate: '',
        renewalCount: '',
        referrals: '',
        pendingAmount: '',
        lastEvent: ''
      };
    
      currentPage = 1;
      applyFilters();
    }
    
    function toggleRow(id, el){
      if(el.checked) selected.add(id); else selected.delete(id);
      updateBulkBar();
      el.closest('tr').classList.toggle('selected', el.checked);
    }
    
    function toggleSelectAll(el){
      document.querySelectorAll('.row-check').forEach(cb=>{ cb.checked = el.checked; const id=cb.closest('tr').dataset.id; if(el.checked) selected.add(id); else selected.delete(id); cb.closest('tr').classList.toggle('selected', el.checked); });
      updateBulkBar();
    }
    
    function clearSelection(){ selected.clear(); document.querySelectorAll('.row-check').forEach(cb=>{cb.checked=false; cb.closest('tr').classList.remove('selected');}); document.getElementById('select-all').checked=false; updateBulkBar(); }
    
    function updateBulkBar(){
      const bulkBar = document.getElementById('bulk-bar');
      const countLabel = document.getElementById('bulk-count');
      const selectedCountSpan = document.getElementById('selectedPeersCount');

      if (bulkBar && countLabel) {
        if (selected.size > 0) {
          bulkBar.classList.remove('hidden');
          bulkBar.classList.add('flex');
          countLabel.textContent = selected.size;
        } else {
          bulkBar.classList.add('hidden');
          bulkBar.classList.remove('flex');
        }
      }

      if (selectedCountSpan) {
        selectedCountSpan.textContent = selected.size;
      }
    }

    function bulkApproveTrigger() {
      document.getElementById('openApproveMembershipModal').click();
    }

    function bulkExportTrigger() {
      // Create hidden export inputs
      const exportForm = document.getElementById('exportCsvForm');
      if (exportForm) {
        exportForm.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
        selected.forEach(id => {
          const hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = 'ids[]';
          hidden.value = id;
          exportForm.appendChild(hidden);
        });
        exportForm.submit();
      }
    }
    
    function openDrawer(id){
      window.currentDrawerPeerId = id;
      const m = members.find(x=>x.id===id);
      if (!m) return;
      
      document.getElementById('view-full-profile-btn').onclick = () => {
        window.open(`/admin/users/${m.id}`, '_blank');
      };
      
      const memberTypeLabel = m.memberType === 'unity' ? 'Unity' : (m.memberType === 'circle_peer' ? 'Circle Peer' : (m.memberType === 'free' ? 'Free' : (m.memberType || 'Free')));

      document.getElementById('drawer-body').innerHTML = `
        <div class="flex items-center gap-3.5 mb-2">
          ${renderAvatar(m, 'w-14 h-14 text-[16px]')}
          <div>
            <div class="font-display font-semibold text-[17px] text-slate-900">${m.name}</div>
            <div class="text-[12px] text-slate-400 font-mono mt-0.5">${m.mid}</div>
          </div>
        </div>
        <div class="flex items-center gap-2 mb-6">
          ${statusBadge(m.status)}
          ${membershipBadge(m.membership)}
        </div>
        
        <div class="space-y-5 text-[12.5px] pb-4">
          <div>
            <div class="font-display font-semibold text-[11px] uppercase tracking-wider text-indigo-600 mb-2 flex items-center gap-1.5">
              <i class="bi bi-person-circle" aria-hidden="true"></i> MEMBER INFO GROUP
            </div>
            <div class="space-y-2.5 border border-slate-200/80 rounded-xl p-3.5 bg-[#f8fafc]">
              <div class="flex justify-between gap-4"><span class="text-slate-400">Email</span><span class="text-slate-800 truncate max-w-[210px] text-right font-medium" title="${m.email || ''}">${m.email || '—'}</span></div>
              <div class="flex justify-between"><span class="text-slate-400">Mobile</span><span class="text-slate-800 font-mono font-medium">${m.mobile || '—'}</span></div>
              <div class="flex justify-between"><span class="text-slate-400">Company</span><span class="text-slate-800 font-medium">${m.company || '—'}</span></div>
              <div class="flex justify-between"><span class="text-slate-400">Industry</span><span class="text-slate-800 font-medium">${m.industry || '—'}</span></div>
              <div class="flex justify-between"><span class="text-slate-400">Role</span><span class="text-slate-800 font-medium">${m.role || 'Member'}</span></div>
              <div class="flex justify-between"><span class="text-slate-400">MemberType</span><span class="text-slate-800 font-medium">${memberTypeLabel}</span></div>
            </div>
          </div>
    
          <div>
            <div class="font-display font-semibold text-[11px] uppercase tracking-wider text-indigo-600 mb-2 flex items-center gap-1.5">
              <i class="bi bi-globe" aria-hidden="true"></i> REGION & BUSINESS GROUP
            </div>
            <div class="space-y-2.5 border border-slate-200/80 rounded-xl p-3.5 bg-[#f8fafc]">
              <div class="flex justify-between"><span class="text-slate-400">City</span><span class="text-slate-800 font-medium">${m.city || '—'}</span></div>
              <div class="flex justify-between"><span class="text-slate-400">Country</span><span class="text-slate-800 font-medium">${m.country || 'India'}</span></div>
              <div class="flex justify-between"><span class="text-slate-400">Circle</span><span class="text-slate-800 font-medium">${m.circle || '—'}</span></div>
            </div>
          </div>
    
          <div>
            <div class="font-display font-semibold text-[11px] uppercase tracking-wider text-amber-500 mb-2 flex items-center gap-1.5">
              <i class="bi bi-star-fill" aria-hidden="true"></i> MEMBERSHIP & RENEWAL
            </div>
            <div class="space-y-2.5 border border-slate-200/80 rounded-xl p-3.5 bg-[#f8fafc]">
              <div class="flex justify-between"><span class="text-slate-400">Joined Date</span><span class="text-slate-800 font-medium">${m.joined || '—'}</span></div>
              <div class="flex justify-between"><span class="text-slate-400">Membership Ends</span><span class="text-slate-800 font-medium">${m.membership_ends_at || '—'} ${typeof m.expiryDays === 'number' ? `(${m.expiryDays >= 0 ? `${m.expiryDays}d left` : 'expired'})` : ''}</span></div>
              ${m.membership_expiry_date_remark ? `<div class="flex justify-between"><span class="text-slate-400">Remark</span><span class="text-slate-800 font-medium">${m.membership_expiry_date_remark}</span></div>` : ''}
            </div>
          </div>
        </div>
      `;
      document.getElementById('drawer').classList.remove('drawer-hidden');
      document.getElementById('drawer-scrim').classList.remove('hidden');
    }
    
    function closeDrawer(){
      document.getElementById('drawer').classList.add('drawer-hidden');
      document.getElementById('drawer-scrim').classList.add('hidden');
    }
    
    function toggleTheme(){
      const container = document.getElementById('grid-root-container');
      const isLight = container.classList.toggle('light');
      container.classList.toggle('dark', !isLight);
      document.getElementById('theme-label').textContent = isLight ? 'Dark Mode' : 'Light Mode';
    }
    
    function toggleExportMenu() {
      document.getElementById('export-menu')?.classList.toggle('hidden');
    }
    
    // ===================== EDIT PEER CONTROLLER =====================
    let currentEditPeerId = null;
    let currentEditTab = 1;
    
    function openEditModal(id) {
      closeDrawer();
      const m = members.find(x => x.id === id);
      if (!m) return;
      
      currentEditPeerId = id;
      document.getElementById('edit-peer-id').textContent = `ID: ${m.mid}`;
      
      const nameParts = m.name.split(' ');
      const firstName = nameParts[0] || '';
      const lastName = nameParts.slice(1).join(' ') || '';
      
      document.getElementById('edit-first-name').value = firstName;
      document.getElementById('edit-last-name').value = lastName;
      document.getElementById('edit-display-name').value = m.name;
      document.getElementById('edit-email').value = m.email;
      document.getElementById('edit-phone').value = m.mobile;
      document.getElementById('edit-designation').value = m.role;
      document.getElementById('edit-gender').value = 'other';
      document.getElementById('edit-dob').value = '1990-01-01';
      document.getElementById('edit-experience').value = 5;
      document.getElementById('edit-bio').value = 'Experienced business leader and active community member.';
      
      document.getElementById('edit-company').value = m.company;
      document.getElementById('edit-industry').value = m.industry;
      document.getElementById('edit-city').value = m.city;
      document.getElementById('edit-country').value = m.country;
      
      const membershipLabelToKey = {
        'Free Peer': 'free_peer',
        'Free Trial Peer': 'free_trial_peer',
        'Global Peer': 'Only Unity Peer',
        'Only Unity Peer': 'Only Unity Peer',
        'Circle Peer': 'Circle Peer',
        'Multi Circle Peer': 'Multi Circle Peer'
      };
      const membershipKey = membershipLabelToKey[m.membership] || 'free_peer';
      document.getElementById('edit-membership').value = membershipKey;
      document.getElementById('edit-status').value = m.status.n.toLowerCase();
      document.getElementById('edit-membership-starts-at').value = m.membership_starts_at || '';
      document.getElementById('edit-membership-ends-at').value = m.membership_ends_at || '';
      document.getElementById('edit-membership-remark').value = m.membership_expiry_date_remark || '';
      document.getElementById('edit-is-sponsored').checked = !!m.is_sponsored_member;
      document.getElementById('edit-coins').value = m.coins;
      document.getElementById('edit-activity').value = m.activity;
      
      document.getElementById('edit-circle').value = m.circle;
      document.getElementById('edit-role').value = m.role === 'Admin' ? 'Admin' : 'Member';
      
      setEditTab(1);
      document.getElementById('edit-modal').classList.remove('hidden');
    }
    
    function closeEditModal() {
      document.getElementById('edit-modal').classList.add('hidden');
    }
    
    function setEditTab(tabIndex) {
      currentEditTab = tabIndex;
      for (let i = 1; i <= 6; i++) {
        document.getElementById(`edit-tab-content-${i}`).classList.add('hidden');
        const btn = document.getElementById(`edit-tab-btn-${i}`);
        if (btn) {
          btn.className = "px-3 py-1.5 rounded-lg text-[11.5px] font-medium transition flex items-center gap-1.5 t2 hover:surface-3 hover:t1 border-none bg-transparent cursor-pointer";
        }
      }
      document.getElementById(`edit-tab-content-${tabIndex}`).classList.remove('hidden');
      const activeBtn = document.getElementById(`edit-tab-btn-${tabIndex}`);
      if (activeBtn) {
        activeBtn.className = "px-3 py-1.5 rounded-lg text-[11.5px] font-medium transition flex items-center gap-1.5 bg-accent text-white border-none cursor-pointer";
      }
    }
    
    function submitEditForm() {
      const m = members.find(x => x.id === currentEditPeerId);
      if (!m) return;
      
      const formData = new FormData();
      formData.append('_token', '{{ csrf_token() }}');
      formData.append('_method', 'PUT');
      
      const firstName = document.getElementById('edit-first-name').value;
      const lastName = document.getElementById('edit-last-name').value;
      formData.append('first_name', firstName);
      formData.append('last_name', lastName);
      formData.append('display_name', document.getElementById('edit-display-name').value);
      formData.append('email', document.getElementById('edit-email').value);
      formData.append('phone', document.getElementById('edit-phone').value);
      formData.append('designation', document.getElementById('edit-designation').value || 'Managing Director');
      formData.append('company_name', document.getElementById('edit-company').value || 'Aequitas Infotech');
      formData.append('city', document.getElementById('edit-city').value);
      
      formData.append('membership_status', document.getElementById('edit-membership').value);
      formData.append('status', document.getElementById('edit-status').value);
      formData.append('membership_starts_at', document.getElementById('edit-membership-starts-at').value);
      formData.append('membership_ends_at', document.getElementById('edit-membership-ends-at').value);
      formData.append('membership_expiry_date_remark', document.getElementById('edit-membership-remark').value);
      formData.append('is_sponsored_member', document.getElementById('edit-is-sponsored').checked ? '1' : '0');
      formData.append('coins_balance', document.getElementById('edit-coins').value);
      formData.append('activity_score', document.getElementById('edit-activity').value);
      formData.append('life_impacted_count', m.lifeImpacted || 0);
      
      fetch(`/admin/users/${currentEditPeerId}`, {
        method: 'POST',
        body: formData,
        headers: {
          'Accept': 'application/json'
        }
      })
      .then(res => {
        if (res.ok) {
          m.name = (firstName + ' ' + lastName).trim();
          m.email = document.getElementById('edit-email').value;
          m.mobile = document.getElementById('edit-phone').value;
          m.company = document.getElementById('edit-company').value;
          m.city = document.getElementById('edit-city').value;
          m.membership_starts_at = document.getElementById('edit-membership-starts-at').value;
          m.membership_ends_at = document.getElementById('edit-membership-ends-at').value;
          m.membership_expiry_date_remark = document.getElementById('edit-membership-remark').value;
          m.is_sponsored_member = document.getElementById('edit-is-sponsored').checked;
          m.coins = parseInt(document.getElementById('edit-coins').value) || 0;
          m.activity = parseInt(document.getElementById('edit-activity').value) || 0;
          
          if (m.membership_ends_at) {
            const expDate = new Date(m.membership_ends_at + 'T00:00:00');
            const today = new Date();
            today.setHours(0,0,0,0);
            const diffMs = expDate - today;
            m.expiryDays = Math.max(0, Math.ceil(diffMs / (1000 * 60 * 60 * 24)));
          } else {
            m.expiryDays = 0;
          }
          
          m.status.n = document.getElementById('edit-status').value === 'active' ? 'Active' : 'Inactive';
          m.status.c = document.getElementById('edit-status').value === 'active' ? 'success' : 'text-3';
          
          const membershipKeyToLabel = {
            'free_peer': 'Free Peer',
            'free_trial_peer': 'Free Trial Peer',
            'Only Unity Peer': 'Global Peer',
            'Circle Peer': 'Circle Peer',
            'Multi Circle Peer': 'Multi Circle Peer'
          };
          m.membership = membershipKeyToLabel[document.getElementById('edit-membership').value] || 'Free Peer';
          
          const membershipVal = document.getElementById('edit-membership').value;
          m.memberType = membershipVal.toLowerCase().includes('unity') ? 'unity' : (membershipVal.toLowerCase().includes('circle') ? 'circle_peer' : 'free');
          
          applyFilters();
          closeEditModal();
          closeDrawer();
          alert('Peer details updated successfully!');
        } else {
          res.json().then(data => {
            alert('Failed to update: ' + (data.message || 'Validation error. Please use the Full Edit page instead.'));
          });
        }
      })
      .catch(err => {
        alert('Error updating peer details: ' + err);
      });
    }
    
    function savePeerChanges(event) {
      event.preventDefault();
      submitEditForm();
    }
    
    function toggleColumn(colName, cb) {
      if (cb.checked) {
        visibleCols.add(colName);
      } else {
        visibleCols.delete(colName);
      }
      
      // Update TH/TD visibility
      const ths = document.querySelectorAll(`#main-table th[data-colgrp="${colName}"]`);
      ths.forEach(th => th.style.display = cb.checked ? '' : 'none');
      
      // Since rows are dynamically generated, applyFilters will handle visibility of TDs
      applyFilters();
    }

    function exportData(format = 'csv') {
      const exportForm = document.getElementById('exportCsvForm');
      if (!exportForm) return;
      
      // Clear previous IDs
      exportForm.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
      
      // Determine which IDs to export
      let idsToExport = [];
      if (selected.size > 0) {
        // Export only checked/selected rows
        idsToExport = Array.from(selected);
      } else {
        // Export all currently filtered/visible rows
        const q = document.getElementById('search-input')?.value.toLowerCase() || '';
        const filteredMembers = members.filter(m => {
          if(q && !(m.name.toLowerCase().includes(q) || m.email.toLowerCase().includes(q) || m.mid.toLowerCase().includes(q) || m.company.toLowerCase().includes(q) || m.city.toLowerCase().includes(q) || m.country.toLowerCase().includes(q) || m.circle.toLowerCase().includes(q) || m.industry.toLowerCase().includes(q))) return false;
          
          if (currentFilters.view === 'unity' && m.membership !== 'Only Unity Peer' && m.membership !== 'Global Peer' && m.membership !== 'Gold') return false;
          if (currentFilters.view === 'circles' && (!m.circle || m.circle === '—')) return false;
          if (currentFilters.view === 'vip' && m.membership !== 'Platinum') return false;
          if (currentFilters.view === 'pending' && !(m.status.n.toLowerCase().includes('pending') || m.status.n.toLowerCase().includes('awaiting'))) return false;
          if (currentFilters.view === 'expiring' && (m.expiryDays < 0 || m.expiryDays > 30)) return false;
          if (currentFilters.view === 'new') {
            const joinedDate = parseCustomDate(m.joinedRaw || m.joined);
            const isThisMonth = joinedDate && joinedDate.getMonth() === (new Date()).getMonth() && joinedDate.getFullYear() === (new Date()).getFullYear();
            if (!isThisMonth) return false;
          }

          if (currentFilters.quick === 'expiring') {
            if (m.isExpiring30 !== undefined ? !m.isExpiring30 : (typeof m.expiryDays !== 'number' || m.expiryDays < 0 || m.expiryDays > 30)) return false;
          }
          if (currentFilters.quick === 'new7') {
            if (m.isJoinedLast7 !== undefined ? !m.isJoinedLast7 : !isRecentJoinerDays(m, 7)) return false;
          }
          if (currentFilters.quick === 'nopayment') {
            if (m.isPaymentOverdue !== undefined ? !m.isPaymentOverdue : (!['overdue', 'due'].includes(m.payment?.n?.toLowerCase()) && (parseFloat(m.pendingAmount) || 0) <= 0)) return false;
          }
          if (currentFilters.quick === 'inactive') {
            if (m.isInactive30 !== undefined ? !m.isInactive30 : !isMemberInactive30(m)) return false;
          }

          const joinedStart = document.getElementById('f-joined-start')?.value;
          const joinedEnd = document.getElementById('f-joined-end')?.value;
          if (m.joined && m.joined !== '—') {
            const joinedDate = new Date(m.joined);
            joinedDate.setHours(0,0,0,0);
            if (joinedStart) {
              const startDate = new Date(joinedStart);
              startDate.setHours(0,0,0,0);
              if (joinedDate < startDate) return false;
            }
            if (joinedEnd) {
              const endDate = new Date(joinedEnd);
              endDate.setHours(0,0,0,0);
              if (joinedDate > endDate) return false;
            }
          } else if (joinedStart || joinedEnd) {
            return false;
          }

          if(currentFilters.industry && !m.industry.toLowerCase().includes(currentFilters.industry.toLowerCase())) return false;
          if(currentFilters.city && !m.city.toLowerCase().includes(currentFilters.city.toLowerCase())) return false;
          if(currentFilters.country && !m.country.toLowerCase().includes(currentFilters.country.toLowerCase())) return false;
          if(currentFilters.circle && !m.circle.toLowerCase().includes(currentFilters.circle.toLowerCase())) return false;
          if(currentFilters.role && !m.role.toLowerCase().includes(currentFilters.role.toLowerCase())) return false;
          if(currentFilters.membership && !m.membership.toLowerCase().includes(currentFilters.membership.toLowerCase())) return false;
          if(currentFilters.status && !m.status.n.toLowerCase().includes(currentFilters.status.toLowerCase())) return false;
          if(currentFilters.payment && !m.payment.n.toLowerCase().includes(currentFilters.payment.toLowerCase())) return false;
      
          if(currentFilters.mid && !m.mid.toLowerCase().includes(currentFilters.mid.toLowerCase())) return false;
          if(currentFilters.name && !m.name.toLowerCase().includes(currentFilters.name.toLowerCase())) return false;
          if(currentFilters.mobile && !m.mobile.toLowerCase().includes(currentFilters.mobile.toLowerCase())) return false;
          if(currentFilters.email && !m.email.toLowerCase().includes(currentFilters.email.toLowerCase())) return false;
          if(currentFilters.company && !m.company.toLowerCase().includes(currentFilters.company.toLowerCase())) return false;
          if(currentFilters.activity && !String(m.activity).toLowerCase().includes(currentFilters.activity.toLowerCase())) return false;
          if(currentFilters.coins && !String(m.coins).toLowerCase().includes(currentFilters.coins.toLowerCase())) return false;
          if(currentFilters.lastLogin && !m.lastLogin.toLowerCase().includes(currentFilters.lastLogin.toLowerCase())) return false;
          if(currentFilters.lastPaymentDate && !m.lastPaymentDate.toLowerCase().includes(currentFilters.lastPaymentDate.toLowerCase())) return false;
          if(currentFilters.renewalCount && !String(m.renewalCount).toLowerCase().includes(currentFilters.renewalCount.toLowerCase())) return false;
          if(currentFilters.referrals && !String(m.referrals).toLowerCase().includes(currentFilters.referrals.toLowerCase())) return false;
          if(currentFilters.pendingAmount && !String(m.pendingAmount).toLowerCase().includes(currentFilters.pendingAmount.toLowerCase())) return false;
          if(currentFilters.lastEvent && !m.lastEvent.toLowerCase().includes(currentFilters.lastEvent.toLowerCase())) return false;
      
          return true;
        });
        idsToExport = filteredMembers.map(m => m.id);
      }
      
      // Append IDs to the form
      idsToExport.forEach(id => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'ids[]';
        hidden.value = id;
        exportForm.appendChild(hidden);
      });
      
      exportForm.submit();
      toggleExportMenu();
    }

    document.addEventListener('DOMContentLoaded', () => {
        buildHeaderDropdowns();
        updateSummaryMetrics();
        applyFilters();
        
        // Connect Bulk Approve button action
        const approveSelectedPeersBtn = document.getElementById('openApproveMembershipModal');
        const bulkApproveDatesForm = document.getElementById('bulkApproveMembershipDatesForm');
        const membershipStartDate = document.getElementById('approvalMembershipStartsAt');
        const membershipEndDate = document.getElementById('approvalMembershipEndsAt');
        const modalMembershipStartsAt = document.getElementById('modalMembershipStartsAt');
        const modalMembershipEndsAt = document.getElementById('modalMembershipEndsAt');
        const modalMembershipStartsAtText = document.getElementById('modalMembershipStartsAtText');
        const modalMembershipEndsAtText = document.getElementById('modalMembershipEndsAtText');
        
        const myModalEl = document.getElementById('approveMembershipDatesModal');
        const modalInstance = myModalEl ? new bootstrap.Modal(myModalEl) : null;
        
        approveSelectedPeersBtn?.addEventListener('click', () => {
            if (selected.size === 0) {
                alert('Please select at least one peer.');
                return;
            }
            
            let startsAt = membershipStartDate?.value || '';
            let endsAt = membershipEndDate?.value || '';
            
            if (!startsAt) {
                const today = new Date();
                startsAt = today.toISOString().slice(0, 10);
            }
            if (!endsAt) {
                const date = new Date(`${startsAt}T00:00:00`);
                date.setFullYear(date.getFullYear() + 1);
                endsAt = date.toISOString().slice(0, 10);
            }
            
            if (endsAt < startsAt) {
                alert('Membership Ends At must be same or after Membership Starts At.');
                return;
            }
            
            // Clear previous IDs
            bulkApproveDatesForm.querySelectorAll('input[name="user_ids[]"]').forEach(el => el.remove());
            selected.forEach(id => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'user_ids[]';
                hidden.value = id;
                bulkApproveDatesForm.appendChild(hidden);
            });
            
            if (modalMembershipStartsAt) modalMembershipStartsAt.value = startsAt;
            if (modalMembershipEndsAt) modalMembershipEndsAt.value = endsAt;
            if (modalMembershipStartsAtText) modalMembershipStartsAtText.textContent = startsAt;
            if (modalMembershipEndsAtText) modalMembershipEndsAtText.textContent = endsAt;
            
            modalInstance?.show();
        });

        // Initialize Top Synchronized Horizontal Scrollbar
        (function initTopScrollbarSync() {
            const tableScroll = document.getElementById('table-scroll');
            const topScrollWrapper = document.getElementById('top-scroll-wrapper');
            const topScrollContent = document.getElementById('top-scroll-content');
            const mainTable = document.getElementById('main-table');

            if (!tableScroll || !topScrollWrapper || !topScrollContent || !mainTable) return;

            function syncWidth() {
                const scrollWidth = mainTable.scrollWidth;
                const clientWidth = tableScroll.clientWidth;
                topScrollContent.style.width = scrollWidth + 'px';
                if (scrollWidth > clientWidth + 10) {
                    topScrollWrapper.style.display = 'block';
                } else {
                    topScrollWrapper.style.display = 'none';
                }
            }

            syncWidth();
            window.addEventListener('resize', syncWidth);
            if (window.ResizeObserver) {
                new ResizeObserver(syncWidth).observe(mainTable);
            }

            let isSyncingTop = false;
            let isSyncingTable = false;

            topScrollWrapper.addEventListener('scroll', () => {
                if (isSyncingTop) {
                    isSyncingTop = false;
                    return;
                }
                isSyncingTable = true;
                tableScroll.scrollLeft = topScrollWrapper.scrollLeft;
            });

            tableScroll.addEventListener('scroll', () => {
                if (isSyncingTable) {
                    isSyncingTable = false;
                    return;
                }
                isSyncingTop = true;
                topScrollWrapper.scrollLeft = tableScroll.scrollLeft;
            });
        })();
    });
</script>
@endpush
@endsection
