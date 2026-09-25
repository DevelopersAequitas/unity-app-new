@extends('admin.layouts.app')

@section('title', 'Ask Flows & Option Configuration')

@include('admin.partials.grid-head')

@push('styles')
<style>
  .toggle-switch-btn {
      transition: all 0.2s ease;
      cursor: pointer;
      user-select: none;
  }
  .toggle-switch-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
  }
  .pill-active {
      background-color: #eef2ff;
      border-color: #c7d2fe;
      color: #3730a3;
  }
  .pill-inactive {
      background-color: #f1f5f9;
      border-color: #cbd5e1;
      color: #94a3b8;
      text-decoration: line-through;
      opacity: 0.75;
  }
  .pill-active:hover {
      background-color: #e0e7ff;
  }
  .pill-inactive:hover {
      background-color: #e2e8f0;
      opacity: 1;
  }
</style>
@endpush

@section('content')
    <div class="space-y-4">
        <!-- Toast Notification Container -->
        <div id="toastContainer" class="fixed top-20 right-6 z-50 flex flex-col gap-2 pointer-events-none"></div>

        <!-- Header -->
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.asks.index') }}" class="p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition" title="Back to Asks">
                    <i class="bi bi-arrow-left text-lg"></i>
                </a>
                <div>
                    <h1 class="text-xl font-bold text-slate-800 m-0">Flows &amp; Dynamic Option Configuration</h1>
                    <p class="text-xs text-slate-500 m-0 mt-0.5">Control which Discovery Flows, Categories, and Dynamic Options are visible (<strong class="text-emerald-700">ON</strong>) or hidden (<strong class="text-slate-500">OFF</strong>) across mobile &amp; web APIs.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-500 flex items-center gap-1.5 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                    <i class="bi bi-info-circle text-indigo-600"></i> Click any badge or switch to instantly toggle API visibility
                </span>
                <a href="{{ route('admin.asks.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 transition no-underline">
                    View All Asks
                </a>
            </div>
        </div>

        <!-- Section 1: Flows & Types -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                    <h2 class="text-sm font-bold text-slate-800 m-0">Discovery Flows &amp; Sub-Type Categories</h2>
                </div>
                <div class="text-[11px] text-slate-400">
                    Governs <code class="bg-slate-100 px-1 py-0.5 rounded text-[11px]">GET /api/asks/flows</code> &amp; <code class="bg-slate-100 px-1 py-0.5 rounded text-[11px]">GET /api/asks/flows/{flow}/types</code>
                </div>
            </div>
            <p class="text-xs text-slate-500 m-0">Turn flows or individual category pills ON or OFF. Items turned <strong>OFF</strong> are immediately filtered out from the mobile and web APIs.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                @forelse($flows as $flow)
                    <div class="rounded-xl border border-slate-200 bg-white p-4 space-y-3 shadow-xs transition" id="flow-card-{{ $flow->id }}">
                        <!-- Flow Header & Flow Toggle -->
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-100">
                                {{ $flow->code }}
                            </span>

                            <!-- Flow Switch Button -->
                            <button type="button" 
                                    onclick="toggleEntity('flow', '{{ $flow->id }}', this)" 
                                    class="toggle-switch-btn px-2.5 py-1 rounded-full text-[11px] font-semibold flex items-center gap-1.5 border transition {{ $flow->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-300 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500 border-slate-300 hover:bg-slate-200' }}"
                                    data-active="{{ $flow->is_active ? '1' : '0' }}"
                                    title="Click to toggle flow active state">
                                <span class="w-2 h-2 rounded-full {{ $flow->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                <span class="label-text">{{ $flow->is_active ? 'Flow Active' : 'Flow Inactive' }}</span>
                            </button>
                        </div>

                        <div>
                            <h3 class="font-bold text-base text-slate-800 m-0">{{ $flow->name }}</h3>
                            @if($flow->description)
                                <p class="text-xs text-slate-500 m-0 mt-1">{{ $flow->description }}</p>
                            @endif
                        </div>

                        <!-- Sub-Types (Clickable Pills) -->
                        <div class="pt-2 border-t border-slate-100">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[11px] font-bold uppercase text-slate-400 tracking-wider">
                                    Sub-Types / Categories ({{ $flow->types->count() }})
                                </span>
                                <span class="text-[10px] text-slate-400 italic">Click pill to toggle ON/OFF</span>
                            </div>

                            @if($flow->types->isNotEmpty())
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($flow->types as $type)
                                        <button type="button"
                                                onclick="toggleEntity('type', '{{ $type->id }}', this)"
                                                class="toggle-switch-btn px-2.5 py-1 rounded-md text-[11px] font-medium border flex items-center gap-1 transition {{ $type->is_active ? 'pill-active' : 'pill-inactive' }}"
                                                data-active="{{ $type->is_active ? '1' : '0' }}"
                                                title="{{ $type->is_active ? 'Visible in API. Click to turn OFF' : 'Hidden in API. Click to turn ON' }}">
                                            <i class="bi {{ $type->is_active ? 'bi-check-circle-fill text-emerald-600' : 'bi-dash-circle text-slate-400' }} text-[10px]"></i>
                                            <span>{{ $type->name }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-xs text-slate-400 italic">No sub-types assigned</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 text-center py-6 text-slate-400">
                        No flows configured.
                    </div>
                @endforelse
            </div>
        </div>

        @php
            $groupFlowMap = [
                'industry' => ['collaboration', 'help'],
                'geography' => ['collaboration', 'help'],
                'business_stage' => ['collaboration'],
                'timeline' => ['collaboration'],
                'collaboration_bring' => ['collaboration'],
                'collaboration_need' => ['collaboration'],
                'expected_outcome' => ['collaboration'],
                'referral_industry' => ['referral'],
                'referral_geography' => ['referral'],
                'who_to_meet' => ['referral'],
                'ideal_profile' => ['referral'],
                'referral_reason' => ['referral'],
                'what_i_offer' => ['referral'],
                'mentorship_subtype' => ['help'],
                'help_topic' => ['help'],
                'help_duration' => ['help'],
                'help_timing' => ['help'],
                'what_needed' => ['help'],
                'done_definition' => ['help'],
            ];
        @endphp

        <!-- Section 2: Option Groups & Options Matrix -->
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm space-y-3" id="optionsSection">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    <h2 class="text-sm font-bold text-slate-800 m-0">Dynamic Form Option Groups &amp; Options</h2>
                </div>
                <div class="text-[11px] text-slate-400">
                    Governs <code class="bg-slate-100 px-1 py-0.5 rounded text-[11px]">GET /api/asks/form-config</code>
                </div>
            </div>
            <p class="text-xs text-slate-500 m-0">Filter option groups by flow below, or turn option groups and individual options ON or OFF. Options marked <strong>OFF</strong> are immediately excluded from API responses.</p>

            <!-- Flow Filter Tabs for Option Groups -->
            <div class="flex flex-wrap items-center gap-2 pt-1 border-b border-slate-100 pb-2">
                <span class="text-xs font-semibold text-slate-500 mr-1"><i class="bi bi-funnel"></i> Show Flow Options:</span>
                <button type="button" onclick="filterOptionGroups('all', this)" id="tab-all" class="flow-tab-btn px-3 py-1 rounded-full text-xs font-semibold border bg-indigo-600 text-white border-indigo-600 transition shadow-xs">
                    All Groups ({{ $optionGroups->count() }})
                </button>
                <button type="button" onclick="filterOptionGroups('collaboration', this)" id="tab-collaboration" class="flow-tab-btn px-3 py-1 rounded-full text-xs font-semibold border bg-white text-slate-600 border-slate-300 hover:bg-slate-50 transition">
                    🤝 1. Find a Collaborator (Collaboration)
                </button>
                <button type="button" onclick="filterOptionGroups('referral', this)" id="tab-referral" class="flow-tab-btn px-3 py-1 rounded-full text-xs font-semibold border bg-white text-slate-600 border-slate-300 hover:bg-slate-50 transition">
                    📢 2. Ask for a Referral (Referral)
                </button>
                <button type="button" onclick="filterOptionGroups('help', this)" id="tab-help" class="flow-tab-btn px-3 py-1 rounded-full text-xs font-semibold border bg-white text-slate-600 border-slate-300 hover:bg-slate-50 transition">
                    🆘 3. Get Help (Help)
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pt-2" id="optionGroupsGrid">
                @forelse($optionGroups as $group)
                    @php
                        $flowsForGroup = $groupFlowMap[$group->code] ?? ['all'];
                        $flowsAttr = implode(',', $flowsForGroup);
                    @endphp
                    <div class="option-group-card rounded-xl border border-slate-200 bg-white p-4 space-y-3 shadow-xs flex flex-col justify-between transition" id="group-card-{{ $group->id }}" data-flows="{{ $flowsAttr }}">
                        <div>
                            <!-- Option Group Header & Group Toggle -->
                            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                <div>
                                    <h3 class="font-bold text-sm text-slate-800 m-0">{{ $group->name }}</h3>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">code: {{ $group->code }}</div>
                                </div>

                                <!-- Group Toggle Button -->
                                <button type="button"
                                        onclick="toggleEntity('group', '{{ $group->id }}', this)"
                                        class="toggle-switch-btn px-2.5 py-0.5 rounded-full text-[10px] font-semibold border flex items-center gap-1 transition {{ $group->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-300' : 'bg-slate-100 text-slate-400 border-slate-200' }}"
                                        data-active="{{ $group->is_active ? '1' : '0' }}"
                                        title="Click to toggle group active state">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $group->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                    <span class="label-text">{{ $group->is_active ? 'Group ON' : 'Group OFF' }}</span>
                                </button>
                            </div>

                            <!-- Options List with Individual Toggles -->
                            <div class="pt-2 space-y-1.5 max-h-72 overflow-y-auto pr-1">
                                @forelse($group->options as $option)
                                    <div class="flex items-center justify-between p-2 rounded-lg border text-xs transition {{ $option->is_active ? 'bg-slate-50/80 border-slate-200 text-slate-800' : 'bg-slate-100/50 border-slate-200 text-slate-400' }}" id="option-row-{{ $option->id }}">
                                        <div class="overflow-hidden pr-2">
                                            <div class="font-medium {{ $option->is_active ? 'text-slate-800' : 'line-through text-slate-400' }}">
                                                {{ $option->label }}
                                            </div>
                                            <span class="text-[10px] text-slate-400 font-mono">{{ $option->code }}</span>
                                        </div>

                                        <!-- Individual Option Toggle Button -->
                                        <button type="button"
                                                onclick="toggleEntity('option', '{{ $option->id }}', this)"
                                                class="toggle-switch-btn px-2 py-0.5 rounded text-[10px] font-semibold border flex items-center gap-1 transition flex-none {{ $option->is_active ? 'bg-white text-emerald-700 border-emerald-200 shadow-2xs hover:bg-emerald-50' : 'bg-slate-200 text-slate-500 border-slate-300 hover:bg-slate-300' }}"
                                                data-active="{{ $option->is_active ? '1' : '0' }}"
                                                title="{{ $option->is_active ? 'Visible in API. Click to turn OFF' : 'Hidden in API. Click to turn ON' }}">
                                            <i class="bi {{ $option->is_active ? 'bi-toggle-on text-emerald-600' : 'bi-toggle-off text-slate-400' }}"></i>
                                            <span class="label-text">{{ $option->is_active ? 'ON' : 'OFF' }}</span>
                                        </button>
                                    </div>
                                @empty
                                    <div class="text-xs text-slate-400 italic py-2">No options in this group.</div>
                                @endforelse
                            </div>
                        </div>

                        <div class="pt-2 border-t border-slate-100 text-[10px] text-slate-400 flex justify-between items-center">
                            <span>Total Options: {{ $group->options->count() }}</span>
                            <span class="text-emerald-600 font-medium">Active: {{ $group->options->where('is_active', true)->count() }}</span>
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 text-center py-6 text-slate-400">
                        No option groups configured.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const CSRF_TOKEN = '{{ csrf_token() }}';
        const TOGGLE_URL = '{{ route("admin.asks.config.toggle") }}';

        async function toggleEntity(type, id, btnElement) {
            const currentActive = btnElement.getAttribute('data-active') === '1';
            const targetActive = !currentActive;

            // Optimistic UI state or disabled during request
            btnElement.disabled = true;
            btnElement.style.opacity = '0.6';

            try {
                const response = await fetch(TOGGLE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        type: type,
                        id: id,
                        is_active: targetActive
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    const isActive = data.data.is_active;
                    btnElement.setAttribute('data-active', isActive ? '1' : '0');

                    // Update UI appearance based on entity type
                    if (type === 'type') {
                        // Category pill
                        if (isActive) {
                            btnElement.className = 'toggle-switch-btn px-2.5 py-1 rounded-md text-[11px] font-medium border flex items-center gap-1 transition pill-active';
                            btnElement.querySelector('i').className = 'bi bi-check-circle-fill text-emerald-600 text-[10px]';
                            btnElement.title = 'Visible in API. Click to turn OFF';
                        } else {
                            btnElement.className = 'toggle-switch-btn px-2.5 py-1 rounded-md text-[11px] font-medium border flex items-center gap-1 transition pill-inactive';
                            btnElement.querySelector('i').className = 'bi bi-dash-circle text-slate-400 text-[10px]';
                            btnElement.title = 'Hidden in API. Click to turn ON';
                        }
                    } else if (type === 'flow') {
                        // Flow button
                        const dot = btnElement.querySelector('span:first-child');
                        const label = btnElement.querySelector('.label-text');
                        if (isActive) {
                            btnElement.className = 'toggle-switch-btn px-2.5 py-1 rounded-full text-[11px] font-semibold flex items-center gap-1.5 border transition bg-emerald-50 text-emerald-700 border-emerald-300 hover:bg-emerald-100';
                            dot.className = 'w-2 h-2 rounded-full bg-emerald-500';
                            label.textContent = 'Flow Active';
                        } else {
                            btnElement.className = 'toggle-switch-btn px-2.5 py-1 rounded-full text-[11px] font-semibold flex items-center gap-1.5 border transition bg-slate-100 text-slate-500 border-slate-300 hover:bg-slate-200';
                            dot.className = 'w-2 h-2 rounded-full bg-slate-400';
                            label.textContent = 'Flow Inactive';
                        }
                    } else if (type === 'group') {
                        // Option Group switch
                        const dot = btnElement.querySelector('span:first-child');
                        const label = btnElement.querySelector('.label-text');
                        if (isActive) {
                            btnElement.className = 'toggle-switch-btn px-2.5 py-0.5 rounded-full text-[10px] font-semibold border flex items-center gap-1 transition bg-emerald-50 text-emerald-700 border-emerald-300';
                            dot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                            label.textContent = 'Group ON';
                        } else {
                            btnElement.className = 'toggle-switch-btn px-2.5 py-0.5 rounded-full text-[10px] font-semibold border flex items-center gap-1 transition bg-slate-100 text-slate-400 border-slate-200';
                            dot.className = 'w-1.5 h-1.5 rounded-full bg-slate-400';
                            label.textContent = 'Group OFF';
                        }
                    } else if (type === 'option') {
                        // Individual option toggle
                        const icon = btnElement.querySelector('i');
                        const label = btnElement.querySelector('.label-text');
                        const row = document.getElementById('option-row-' + id);
                        const rowTitle = row ? row.querySelector('.font-medium') : null;

                        if (isActive) {
                            btnElement.className = 'toggle-switch-btn px-2 py-0.5 rounded text-[10px] font-semibold border flex items-center gap-1 transition flex-none bg-white text-emerald-700 border-emerald-200 shadow-2xs hover:bg-emerald-50';
                            icon.className = 'bi bi-toggle-on text-emerald-600';
                            label.textContent = 'ON';
                            btnElement.title = 'Visible in API. Click to turn OFF';
                            if (rowTitle) rowTitle.className = 'font-medium text-slate-800';
                        } else {
                            btnElement.className = 'toggle-switch-btn px-2 py-0.5 rounded text-[10px] font-semibold border flex items-center gap-1 transition flex-none bg-slate-200 text-slate-500 border-slate-300 hover:bg-slate-300';
                            icon.className = 'bi bi-toggle-off text-slate-400';
                            label.textContent = 'OFF';
                            btnElement.title = 'Hidden in API. Click to turn ON';
                            if (rowTitle) rowTitle.className = 'font-medium line-through text-slate-400';
                        }
                    }

                    showToast(isActive ? 'success' : 'info', data.message + (isActive ? ' (Visible in API)' : ' (Hidden in API)'));
                } else {
                    showToast('error', data.message || 'Failed to update status.');
                }
            } catch (err) {
                console.error(err);
                showToast('error', 'Network error while updating status.');
            } finally {
                btnElement.disabled = false;
                btnElement.style.opacity = '1';
            }
        }

        function showToast(type, message) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'pointer-events-auto px-4 py-2.5 rounded-lg shadow-lg text-xs font-semibold flex items-center gap-2 transition-all transform duration-300 ' + 
                (type === 'success' ? 'bg-emerald-700 text-white' : (type === 'error' ? 'bg-rose-700 text-white' : 'bg-slate-800 text-white'));
            
            const icon = document.createElement('i');
            icon.className = type === 'success' ? 'bi bi-check-circle-fill' : (type === 'error' ? 'bi-exclamation-circle-fill' : 'bi-info-circle-fill');
            
            const text = document.createElement('span');
            text.textContent = message;

            toast.appendChild(icon);
            toast.appendChild(text);
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-8px)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function filterOptionGroups(flowCode, tabElement) {
            document.querySelectorAll('.flow-tab-btn').forEach(btn => {
                btn.className = 'flow-tab-btn px-3 py-1 rounded-full text-xs font-semibold border bg-white text-slate-600 border-slate-300 hover:bg-slate-50 transition';
            });
            if (tabElement) {
                tabElement.className = 'flow-tab-btn px-3 py-1 rounded-full text-xs font-semibold border bg-indigo-600 text-white border-indigo-600 transition shadow-xs';
            }

            const cards = document.querySelectorAll('.option-group-card');
            cards.forEach(card => {
                if (flowCode === 'all') {
                    card.style.display = '';
                } else {
                    const flows = (card.getAttribute('data-flows') || '').split(',');
                    if (flows.includes(flowCode)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        }
    </script>
    @endpush
@endsection
