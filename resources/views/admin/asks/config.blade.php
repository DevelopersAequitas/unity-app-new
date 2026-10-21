@extends('admin.layouts.app')

@section('title', 'Ask Flows & Option Configuration')

@include('admin.partials.grid-head')

@push('styles')
<style>
  /* ── Reset Button Styles ── */
  .btn-reset {
      background: transparent !important;
      border: 0 !important;
      outline: none !important;
      box-shadow: none !important;
      padding: 0 !important;
      margin: 0 !important;
      line-height: 1 !important;
      color: inherit !important;
      cursor: pointer !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      font-size: inherit !important;
  }

  .toggle-switch-btn {
      transition: all 0.2s ease !important;
      cursor: pointer !important;
      user-select: none !important;
      outline: none !important;
  }
  .toggle-switch-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
  }

  /* ── Sleek Category Pill Styling ── */
  .type-pill {
      display: inline-flex !important;
      align-items: center !important;
      border-radius: 9999px !important;
      font-size: 11.5px !important;
      font-weight: 500 !important;
      padding: 3px 10px 3px 10px !important;
      transition: all 0.2s ease !important;
      position: relative !important;
      user-select: none !important;
      line-height: 1.35 !important;
      border: 1px solid transparent !important;
  }
  .type-pill.pill-active {
      background-color: #eef2ff !important;
      border-color: #c7d2fe !important;
      color: #3730a3 !important;
  }
  .type-pill.pill-inactive {
      background-color: #f1f5f9 !important;
      border-color: #cbd5e1 !important;
      color: #94a3b8 !important;
  }
  .type-pill.pill-inactive .type-name-label {
      text-decoration: line-through;
  }
  .type-pill:hover {
      box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  }
  .type-pill.pill-active:hover {
      background-color: #e0e7ff !important;
  }
  .type-pill.pill-inactive:hover {
      background-color: #e2e8f0 !important;
  }

  .type-pill-toggle {
      display: inline-flex !important;
      align-items: center !important;
      gap: 6px !important;
      cursor: pointer !important;
  }

  /* Smooth hover reveal for micro edit & delete actions */
  .pill-actions-wrapper {
      display: inline-flex !important;
      align-items: center !important;
      gap: 2px !important;
      margin-left: 0 !important;
      max-width: 0 !important;
      opacity: 0 !important;
      overflow: hidden !important;
      transition: max-width 0.2s ease, opacity 0.2s ease, margin-left 0.2s ease !important;
      white-space: nowrap !important;
  }
  .type-pill:hover .pill-actions-wrapper,
  .type-pill:focus-within .pill-actions-wrapper {
      margin-left: 6px !important;
      max-width: 44px !important;
      opacity: 1 !important;
  }

  .pill-action-icon {
      width: 17px !important;
      height: 17px !important;
      border-radius: 9999px !important;
      font-size: 9px !important;
      opacity: 0.6 !important;
      transition: all 0.15s ease !important;
  }
  .pill-action-icon:hover {
      opacity: 1 !important;
  }
  .pill-action-icon.edit-action:hover {
      background-color: rgba(99, 102, 241, 0.25) !important;
      color: #3730a3 !important;
  }
  .pill-action-icon.delete-action:hover {
      background-color: rgba(239, 68, 68, 0.25) !important;
      color: #b91c1c !important;
  }

  /* Section 2 Action Button Styles */
  .clean-action-btn {
      background: transparent !important;
      border: 0 !important;
      outline: none !important;
      box-shadow: none !important;
      padding: 4px !important;
      margin: 0 !important;
      cursor: pointer !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      border-radius: 6px !important;
      transition: all 0.15s ease !important;
      color: #94a3b8 !important;
  }
  .clean-action-btn.btn-edit:hover {
      color: #4f46e5 !important;
      background-color: #eef2ff !important;
  }
  .clean-action-btn.btn-delete:hover {
      color: #e11d48 !important;
      background-color: #ffe4e6 !important;
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
                    <i class="bi bi-info-circle text-indigo-600"></i> Click any pill to toggle ON/OFF. Hover pill to edit or remove.
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
            <p class="text-xs text-slate-500 m-0">Turn flows or individual category pills ON or OFF. Click <strong>+ Add Category</strong> to add new pills, or hover any pill to <strong>Edit</strong> or <strong>Remove</strong>.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                @forelse($flows as $flow)
                    <div class="rounded-xl border border-slate-200 bg-white p-4 space-y-3 shadow-xs transition flex flex-col justify-between" id="flow-card-{{ $flow->id }}">
                        <div class="space-y-3">
                            <!-- Flow Header: Code Badge & Flow Active Switch -->
                            <div class="flex items-center justify-between">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    {{ $flow->code }}
                                </span>

                                <!-- Flow Switch Button -->
                                <button type="button" 
                                        onclick="toggleEntity('flow', '{{ $flow->id }}', this)" 
                                        class="btn-reset toggle-switch-btn px-2.5 py-1 rounded-full text-[11px] font-semibold flex items-center gap-1.5 border transition {{ $flow->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-300 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500 border-slate-300 hover:bg-slate-200' }}"
                                        data-active="{{ $flow->is_active ? '1' : '0' }}"
                                        title="Click to toggle flow active state">
                                    <span class="w-2 h-2 rounded-full {{ $flow->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                    <span class="label-text">{{ $flow->is_active ? 'Flow Active' : 'Flow Inactive' }}</span>
                                </button>
                            </div>

                            <!-- Flow Title with subtle inline Edit icon -->
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <h3 class="font-bold text-base text-slate-800 m-0" id="flow-name-{{ $flow->id }}">{{ $flow->name }}</h3>
                                    <button type="button"
                                            onclick="openEditFlowModal('{{ $flow->id }}', '{{ addslashes($flow->name) }}', '{{ addslashes($flow->description ?? '') }}')"
                                            class="btn-reset text-slate-400 hover:text-indigo-600 p-0.5 transition"
                                            title="Edit flow name & description">
                                        <i class="bi bi-pencil text-xs"></i>
                                    </button>
                                </div>
                                <p class="text-xs text-slate-500 m-0 mt-1" id="flow-desc-{{ $flow->id }}">{{ $flow->description ?? '' }}</p>
                            </div>

                            <!-- Sub-Types (Clickable Pills with Hover Edit & Remove) -->
                            <div class="pt-2 border-t border-slate-100">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[11px] font-bold uppercase text-slate-400 tracking-wider">
                                        Sub-Types / Categories (<span id="flow-types-count-{{ $flow->id }}">{{ $flow->types->count() }}</span>)
                                    </span>
                                    <button type="button" 
                                            onclick="openAddTypeModal('{{ $flow->id }}', '{{ addslashes($flow->name) }}')"
                                            class="btn-reset px-2.5 py-0.5 rounded-full text-[11px] font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition flex items-center gap-1 cursor-pointer"
                                            style="border: 1px solid #c7d2fe !important;"
                                            title="Add category to {{ $flow->name }}">
                                        <i class="bi bi-plus text-xs leading-none"></i> Add Category
                                    </button>
                                </div>

                                <div class="flex flex-wrap gap-1.5" id="flow-types-list-{{ $flow->id }}">
                                    @forelse($flow->types as $type)
                                        <div class="type-pill group inline-flex items-center {{ $type->is_active ? 'pill-active' : 'pill-inactive' }}" 
                                             id="type-pill-{{ $type->id }}"
                                             data-flow-id="{{ $flow->id }}">
                                            <!-- Pill Main Toggle Button -->
                                            <button type="button"
                                                    onclick="toggleEntity('type', '{{ $type->id }}', this)"
                                                    class="btn-reset type-pill-toggle"
                                                    data-active="{{ $type->is_active ? '1' : '0' }}"
                                                    title="{{ $type->is_active ? 'Visible in API. Click to turn OFF' : 'Hidden in API. Click to turn ON' }}">
                                                <i class="bi {{ $type->is_active ? 'bi-check-circle-fill text-emerald-600' : 'bi-dash-circle text-slate-400' }} text-[10px]"></i>
                                                <span class="type-name-label">{{ $type->name }}</span>
                                            </button>

                                            <!-- Micro Action Icons (Revealed on Hover) -->
                                            <span class="pill-actions-wrapper">
                                                <button type="button"
                                                        onclick="openEditTypeModal('{{ $type->id }}', '{{ addslashes($type->name) }}', '{{ $type->code }}', '{{ addslashes($type->description ?? '') }}')"
                                                        class="btn-reset pill-action-icon edit-action"
                                                        title="Edit category">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button"
                                                        onclick="confirmDeleteEntity('type', '{{ $type->id }}', '{{ addslashes($type->name) }}', '{{ $flow->id }}')"
                                                        class="btn-reset pill-action-icon delete-action"
                                                        title="Remove category">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </span>
                                        </div>
                                    @empty
                                        <span class="text-xs text-slate-400 italic py-1">No sub-types assigned. Click &ldquo;+ Add Category&rdquo; to add one.</span>
                                    @endforelse
                                </div>
                            </div>
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
                'collaboration_bring' => ['collaboration'],
                'collaboration_need' => ['collaboration'],
                'industry' => ['collaboration', 'referral', 'help'],
                'geography' => ['collaboration', 'referral', 'help'],
                'business_stage' => ['collaboration'],
                'timeline' => ['collaboration'],
                'expected_outcome' => ['collaboration'],
                'referral_industry' => ['referral'],
                'referral_geography' => ['referral'],
                'who_to_meet' => ['referral'],
                'ideal_profile' => ['referral'],
                'referral_reason' => ['referral'],
                'what_i_offer' => ['referral'],
                'help_subtype' => ['help'],
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
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    <h2 class="text-sm font-bold text-slate-800 m-0">Dynamic Form Option Groups &amp; Options</h2>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" 
                            onclick="openAddGroupModal()" 
                            class="btn-reset px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                        <i class="bi bi-plus-circle-fill"></i> Add Section / Group
                    </button>
                    <div class="text-[11px] text-slate-400">
                        Governs <code class="bg-slate-100 px-1 py-0.5 rounded text-[11px]">GET /api/asks/form-config</code>
                    </div>
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
                    📢 2. Help me make the right connection (Referral)
                </button>
                <button type="button" onclick="filterOptionGroups('help', this)" id="tab-help" class="flow-tab-btn px-3 py-1 rounded-full text-xs font-semibold border bg-white text-slate-600 border-slate-300 hover:bg-slate-50 transition">
                    🆘 3. Guidance & Support (Help)
                </button>
            </div>

            <!-- Sub-Type / Category Filter for Collaboration, Referral, and Help -->
            <div id="subTypeFilterRow" class="flex flex-wrap items-center gap-1.5 pt-2 pb-1 text-xs bg-slate-50/80 -mx-4 px-4 border-b border-slate-100">
                <span class="text-[11px] font-bold text-slate-600 uppercase tracking-wider mr-1 flex items-center gap-1">
                    <i class="bi bi-diagram-3-fill text-indigo-600"></i> Category / Sub-Type Filter:
                </span>
                <button type="button" onclick="filterBySubType('all', this)" id="subtype-tab-all" class="subtype-tab-btn px-2.5 py-1 rounded-md text-[11px] font-semibold border bg-indigo-600 text-white border-indigo-600 transition shadow-2xs" data-flow="all">
                    All Categories
                </button>
                @foreach($flows as $f)
                    @foreach($f->types as $t)
                        <button type="button" 
                                onclick="filterBySubType('{{ $t->id }}', this, '{{ $t->code }}')" 
                                id="subtype-tab-{{ $t->id }}"
                                data-flow="{{ $f->code }}"
                                data-type-id="{{ $t->id }}"
                                data-type-code="{{ $t->code }}"
                                class="subtype-tab-btn px-2.5 py-1 rounded-md text-[11px] font-medium border bg-white text-slate-600 border-slate-300 hover:bg-slate-50 transition"
                                style="{{ $f->code === 'collaboration' ? '' : 'display: none;' }}">
                            {{ $t->name }}
                        </button>
                    @endforeach
                @endforeach
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pt-2" id="optionGroupsGrid">
                @forelse($optionGroups as $group)
                    @php
                        $flowsForGroup = $group->metadata['flows'] ?? $groupFlowMap[$group->code] ?? ['all'];
                        $flowsAttr = implode(',', $flowsForGroup);
                    @endphp
                    <div class="option-group-card rounded-xl border border-slate-200 bg-white p-4 space-y-3 shadow-xs flex flex-col justify-between transition" id="group-card-{{ $group->id }}" data-flows="{{ $flowsAttr }}">
                        <div>
                            <!-- Option Group Header & Group Actions -->
                            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                                <div>
                                    <h3 class="font-bold text-sm text-slate-800 m-0" id="group-name-{{ $group->id }}">{{ $group->name }}</h3>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">code: <span id="group-code-{{ $group->id }}">{{ $group->code }}</span></div>
                                </div>

                                <div class="flex items-center gap-1">
                                    <button type="button"
                                            onclick="openEditGroupModal('{{ $group->id }}', '{{ addslashes($group->name) }}', '{{ $group->code }}', '{{ $group->input_type }}', '{{ addslashes($group->description ?? '') }}', {{ json_encode($flowsForGroup) }})"
                                            class="clean-action-btn btn-edit"
                                            title="Edit section">
                                        <i class="bi bi-pencil text-xs"></i>
                                    </button>
                                    <button type="button"
                                            onclick="confirmDeleteEntity('group', '{{ $group->id }}', '{{ addslashes($group->name) }}')"
                                            class="clean-action-btn btn-delete"
                                            title="Remove section">
                                        <i class="bi bi-trash3 text-xs"></i>
                                    </button>

                                    <button type="button"
                                            onclick="openAddOptionModal('{{ $group->id }}', '{{ addslashes($group->name) }}')"
                                            class="btn-reset px-2 py-0.5 rounded text-[10px] font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 transition flex items-center gap-0.5 cursor-pointer ml-1"
                                            title="Add new option to this section">
                                        <i class="bi bi-plus"></i> Add Option
                                    </button>

                                    <!-- Group Toggle Button -->
                                    <button type="button"
                                            onclick="toggleEntity('group', '{{ $group->id }}', this)"
                                            class="btn-reset toggle-switch-btn px-2.5 py-0.5 rounded-full text-[10px] font-semibold border flex items-center gap-1 transition {{ $group->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-300' : 'bg-slate-100 text-slate-400 border-slate-200' }}"
                                            data-active="{{ $group->is_active ? '1' : '0' }}"
                                            title="Click to toggle group active state">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $group->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                        <span class="label-text">{{ $group->is_active ? 'Group ON' : 'Group OFF' }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Options List with Individual Toggles, Edit & Remove -->
                            <div class="pt-2 space-y-1.5 max-h-72 overflow-y-auto pr-1" id="options-list-{{ $group->id }}">
                                @forelse($group->options as $option)
                                    <div class="option-item flex items-center justify-between p-2 rounded-lg border text-xs transition {{ $option->is_active ? 'bg-slate-50/80 border-slate-200 text-slate-800' : 'bg-slate-100/50 border-slate-200 text-slate-400' }}" 
                                         id="option-row-{{ $option->id }}"
                                         data-type-id="{{ $option->ask_type_id ?? '' }}"
                                         data-type-code="{{ $option->askType?->code ?? '' }}"
                                         data-is-active="{{ $option->is_active ? '1' : '0' }}">
                                        <div class="overflow-hidden pr-2">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-medium {{ $option->is_active ? 'text-slate-800' : 'line-through text-slate-400' }} option-label-text">
                                                    {{ $option->label }}
                                                </span>
                                                @if($option->askType)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100 flex-none subtype-badge">
                                                        {{ $option->askType->name }}
                                                    </span>
                                                @endif
                                            </div>
                                            <span class="text-[10px] text-slate-400 font-mono option-code-text">{{ $option->code }}</span>
                                        </div>

                                        <div class="flex items-center gap-1 flex-none">
                                            <!-- Edit Option -->
                                            <button type="button"
                                                    onclick="openEditOptionModal('{{ $option->id }}', '{{ addslashes($option->label) }}', '{{ $option->code }}', '{{ $option->ask_type_id ?? '' }}', '{{ addslashes($option->description ?? '') }}')"
                                                    class="clean-action-btn btn-edit"
                                                    title="Edit option">
                                                <i class="bi bi-pencil text-xs"></i>
                                            </button>

                                            <!-- Remove Option -->
                                            <button type="button"
                                                    onclick="confirmDeleteEntity('option', '{{ $option->id }}', '{{ addslashes($option->label) }}', '{{ $group->id }}')"
                                                    class="clean-action-btn btn-delete"
                                                    title="Remove option">
                                                <i class="bi bi-trash3 text-xs"></i>
                                            </button>

                                            <!-- Individual Option Toggle Button -->
                                            <button type="button"
                                                    onclick="toggleEntity('option', '{{ $option->id }}', this)"
                                                    class="btn-reset toggle-switch-btn px-2 py-0.5 rounded text-[10px] font-semibold border flex items-center gap-1 transition flex-none {{ $option->is_active ? 'bg-white text-emerald-700 border-emerald-200 shadow-2xs hover:bg-emerald-50' : 'bg-slate-200 text-slate-500 border-slate-300 hover:bg-slate-300' }}"
                                                    data-active="{{ $option->is_active ? '1' : '0' }}"
                                                    title="{{ $option->is_active ? 'Visible in API. Click to turn OFF' : 'Hidden in API. Click to turn ON' }}">
                                                <i class="bi {{ $option->is_active ? 'bi-toggle-on text-emerald-600' : 'bi-toggle-off text-slate-400' }}"></i>
                                                <span class="label-text">{{ $option->is_active ? 'ON' : 'OFF' }}</span>
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-xs text-slate-400 italic py-2">No options in this group. Click &ldquo;+ Add Option&rdquo; to create one.</div>
                                @endforelse
                            </div>
                        </div>

                        <div class="pt-2 border-t border-slate-100 text-[10px] text-slate-400 flex justify-between items-center">
                            <span id="group-total-{{ $group->id }}">Total Options: {{ $group->options->count() }}</span>
                            <span class="text-emerald-600 font-medium" id="group-active-{{ $group->id }}">Active: {{ $group->options->where('is_active', true)->count() }}</span>
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 text-center py-6 text-slate-400">
                        No option groups configured.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Add Section / Group Modal -->
        <div id="addGroupModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden transition-all">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                        <h3 class="text-sm font-bold text-slate-800 m-0">Add Dynamic Option Group (Section)</h3>
                    </div>
                    <button type="button" onclick="closeAddGroupModal()" class="text-slate-400 hover:text-slate-600 transition cursor-pointer">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>

                <form id="addGroupForm" onsubmit="submitAddGroup(event)" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Section Name <span class="text-rose-500">*</span></label>
                        <input type="text" id="groupName" name="name" required placeholder="e.g. What I Bring, Assets Offered..." 
                               oninput="autoGenerateGroupCode(this.value)"
                               class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">System Code <span class="text-rose-500">*</span></label>
                        <input type="text" id="groupCode" name="code" required placeholder="e.g. collaboration_bring" 
                               class="w-full px-3 py-2 text-xs font-mono border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <span class="text-[10px] text-slate-400">Unique identifier used by API endpoints.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Description (Optional)</label>
                        <textarea id="groupDescription" name="description" rows="2" placeholder="Briefly describe what this option section collects..."
                                  class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Input Selection Type</label>
                            <select id="groupInputType" name="input_type" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500">
                                <option value="multi_select">Multi-Select (Pills / Checkboxes)</option>
                                <option value="single_select">Single-Select (Radio)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Target Flow(s)</label>
                            <div class="space-y-1 pt-1 text-xs">
                                <label class="inline-flex items-center gap-1.5 mr-2 cursor-pointer">
                                    <input type="checkbox" name="flows[]" value="collaboration" checked class="rounded border-slate-300 text-indigo-600 focus:ring-0">
                                    <span>Collaborator</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5 mr-2 cursor-pointer">
                                    <input type="checkbox" name="flows[]" value="referral" class="rounded border-slate-300 text-indigo-600 focus:ring-0">
                                    <span>Referral</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="checkbox" name="flows[]" value="help" class="rounded border-slate-300 text-indigo-600 focus:ring-0">
                                    <span>Help</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Initial Options (Optional)</label>
                        <textarea id="groupInitialOptions" name="initial_options" rows="2" placeholder="Customers, Capital, Technology, Manufacturing... (separated by commas or new lines)"
                                  class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500"></textarea>
                        <span class="text-[10px] text-slate-400">Options will automatically be created and activated for this group.</span>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeAddGroupModal()" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" id="saveGroupBtn" class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition flex items-center gap-1.5 cursor-pointer">
                            <i class="bi bi-check2"></i> Create Section
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Section / Group Modal -->
        <div id="editGroupModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden transition-all">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                        <h3 class="text-sm font-bold text-slate-800 m-0">Edit Option Group (Section)</h3>
                    </div>
                    <button type="button" onclick="closeEditGroupModal()" class="text-slate-400 hover:text-slate-600 transition cursor-pointer">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>

                <form id="editGroupForm" onsubmit="submitEditGroup(event)" class="p-6 space-y-4">
                    <input type="hidden" id="editGroupId" name="group_id">

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Section Name <span class="text-rose-500">*</span></label>
                        <input type="text" id="editGroupName" name="name" required 
                               class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">System Code <span class="text-rose-500">*</span></label>
                        <input type="text" id="editGroupCode" name="code" required 
                               class="w-full px-3 py-2 text-xs font-mono border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Description (Optional)</label>
                        <textarea id="editGroupDescription" name="description" rows="2" 
                                  class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Input Selection Type</label>
                            <select id="editGroupInputType" name="input_type" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500">
                                <option value="multi_select">Multi-Select (Pills / Checkboxes)</option>
                                <option value="single_select">Single-Select (Radio)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Target Flow(s)</label>
                            <div class="space-y-1 pt-1 text-xs">
                                <label class="inline-flex items-center gap-1.5 mr-2 cursor-pointer">
                                    <input type="checkbox" id="editFlowCollab" name="edit_flows[]" value="collaboration" class="rounded border-slate-300 text-indigo-600 focus:ring-0">
                                    <span>Collaborator</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5 mr-2 cursor-pointer">
                                    <input type="checkbox" id="editFlowReferral" name="edit_flows[]" value="referral" class="rounded border-slate-300 text-indigo-600 focus:ring-0">
                                    <span>Referral</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="checkbox" id="editFlowHelp" name="edit_flows[]" value="help" class="rounded border-slate-300 text-indigo-600 focus:ring-0">
                                    <span>Help</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeEditGroupModal()" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" id="updateGroupBtn" class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition flex items-center gap-1.5 cursor-pointer">
                            <i class="bi bi-check2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Option Modal -->
        <div id="addOptionModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden transition-all">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                        <h3 class="text-sm font-bold text-slate-800 m-0">Add Option to <span id="modalGroupNameBadge" class="text-indigo-600"></span></h3>
                    </div>
                    <button type="button" onclick="closeAddOptionModal()" class="text-slate-400 hover:text-slate-600 transition cursor-pointer">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>

                <form id="addOptionForm" onsubmit="submitAddOption(event)" class="p-6 space-y-4">
                    <input type="hidden" id="optionGroupId" name="option_group_id">

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Option Label <span class="text-rose-500">*</span></label>
                        <input type="text" id="optionLabel" name="label" required placeholder="e.g. Market access, IP, Customers..." 
                               oninput="autoGenerateOptionCode(this.value)"
                               class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Option Code <span class="text-rose-500">*</span></label>
                        <input type="text" id="optionCode" name="code" required placeholder="e.g. market_access" 
                               class="w-full px-3 py-2 text-xs font-mono border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <span class="text-[10px] text-slate-400">Machine code used in mobile payloads and responses.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Target Category / Sub-Type (Optional)</label>
                        <select id="optionAskTypeId" name="ask_type_id" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500">
                            <option value="">-- General (Common across all categories) --</option>
                            @foreach($flows as $f)
                                @if($f->types->isNotEmpty())
                                    <optgroup label="{{ $f->name }} Categories">
                                        @foreach($f->types as $t)
                                            <option value="{{ $t->id }}">{{ $t->name }} ({{ $f->name }})</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                        <span class="text-[10px] text-slate-400">Select which Sub-Type (e.g. Joint Venture) this option belongs to.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Description (Optional)</label>
                        <input type="text" id="optionDescription" name="description" placeholder="Brief tooltip or description..." 
                               class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeAddOptionModal()" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" id="saveOptionBtn" class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-1.5 cursor-pointer">
                            <i class="bi bi-plus-lg"></i> Add Option
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Option Modal -->
        <div id="editOptionModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden transition-all">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                        <h3 class="text-sm font-bold text-slate-800 m-0">Edit Option</h3>
                    </div>
                    <button type="button" onclick="closeEditOptionModal()" class="text-slate-400 hover:text-slate-600 transition cursor-pointer">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>

                <form id="editOptionForm" onsubmit="submitEditOption(event)" class="p-6 space-y-4">
                    <input type="hidden" id="editOptionId" name="option_id">

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Option Label <span class="text-rose-500">*</span></label>
                        <input type="text" id="editOptionLabel" name="label" required 
                               class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Option Code <span class="text-rose-500">*</span></label>
                        <input type="text" id="editOptionCode" name="code" required 
                               class="w-full px-3 py-2 text-xs font-mono border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Target Category / Sub-Type (Optional)</label>
                        <select id="editOptionAskTypeId" name="ask_type_id" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500">
                            <option value="">-- General (Common across all categories) --</option>
                            @foreach($flows as $f)
                                @if($f->types->isNotEmpty())
                                    <optgroup label="{{ $f->name }} Categories">
                                        @foreach($f->types as $t)
                                            <option value="{{ $t->id }}">{{ $t->name }} ({{ $f->name }})</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Description (Optional)</label>
                        <input type="text" id="editOptionDescription" name="description" placeholder="Brief tooltip or description..." 
                               class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeEditOptionModal()" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" id="updateOptionBtn" class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-1.5 cursor-pointer">
                            <i class="bi bi-check2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Category / Sub-Type Modal -->
        <div id="addTypeModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden transition-all">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                        <h3 class="text-sm font-bold text-slate-800 m-0">Add Category to <span id="addTypeFlowNameBadge" class="text-indigo-600"></span></h3>
                    </div>
                    <button type="button" onclick="closeAddTypeModal()" class="text-slate-400 hover:text-slate-600 transition cursor-pointer">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>

                <form id="addTypeForm" onsubmit="submitAddType(event)" class="p-6 space-y-4">
                    <input type="hidden" id="addTypeFlowId" name="flow_id">

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Category / Sub-Type Name <span class="text-rose-500">*</span></label>
                        <input type="text" id="addTypeName" name="name" required placeholder="e.g. Technology Partner, Seed Funding..." 
                               oninput="autoGenerateTypeCode(this.value)"
                               class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Category Code <span class="text-rose-500">*</span></label>
                        <input type="text" id="addTypeCode" name="code" required placeholder="e.g. technology_partner" 
                               class="w-full px-3 py-2 text-xs font-mono border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <span class="text-[10px] text-slate-400">Unique identifier returned in <code class="text-indigo-600">GET /api/asks/flows/{flow}/types</code></span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Description (Optional)</label>
                        <textarea id="addTypeDescription" name="description" rows="2" placeholder="Brief explanation of this sub-type category..."
                                  class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeAddTypeModal()" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" id="saveTypeBtn" class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-1.5 cursor-pointer">
                            <i class="bi bi-plus-lg"></i> Add Category
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Category / Sub-Type Modal -->
        <div id="editTypeModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden transition-all">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                        <h3 class="text-sm font-bold text-slate-800 m-0">Edit Category / Sub-Type</h3>
                    </div>
                    <button type="button" onclick="closeEditTypeModal()" class="text-slate-400 hover:text-slate-600 transition cursor-pointer">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>

                <form id="editTypeForm" onsubmit="submitEditType(event)" class="p-6 space-y-4">
                    <input type="hidden" id="editTypeId" name="type_id">

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Category Name <span class="text-rose-500">*</span></label>
                        <input type="text" id="editTypeName" name="name" required 
                               class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Category Code <span class="text-rose-500">*</span></label>
                        <input type="text" id="editTypeCode" name="code" required 
                               class="w-full px-3 py-2 text-xs font-mono border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Description (Optional)</label>
                        <textarea id="editTypeDescription" name="description" rows="2" 
                                  class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeEditTypeModal()" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" id="updateTypeBtn" class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-1.5 cursor-pointer">
                            <i class="bi bi-check2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Flow Modal -->
        <div id="editFlowModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden transition-all">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                        <h3 class="text-sm font-bold text-slate-800 m-0">Edit Discovery Flow</h3>
                    </div>
                    <button type="button" onclick="closeEditFlowModal()" class="text-slate-400 hover:text-slate-600 transition cursor-pointer">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>

                <form id="editFlowForm" onsubmit="submitEditFlow(event)" class="p-6 space-y-4">
                    <input type="hidden" id="editFlowId" name="flow_id">

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Flow Display Title <span class="text-rose-500">*</span></label>
                        <input type="text" id="editFlowName" name="name" required 
                               class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Flow Subtitle / Description</label>
                        <textarea id="editFlowDescription" name="description" rows="2" 
                                  class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-indigo-500 focus:ring-1 focus:ring-indigo-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" onclick="closeEditFlowModal()" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" id="updateFlowBtn" class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-1.5 cursor-pointer">
                            <i class="bi bi-check2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Confirm Deletion Modal -->
        <div id="confirmDeleteModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-sm w-full overflow-hidden transition-all">
                <div class="p-6 text-center space-y-4">
                    <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto text-xl">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 m-0">Confirm Removal</h3>
                        <p class="text-xs text-slate-500 m-0 mt-1">
                            Are you sure you want to remove <span id="deleteEntityTypeDesc" class="font-medium text-slate-700"></span>:
                            <br><strong id="deleteEntityName" class="text-slate-900"></strong>?
                        </p>
                    </div>
                    <div class="flex items-center justify-center gap-2 pt-2">
                        <button type="button" onclick="closeDeleteModal()" class="px-4 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="button" id="confirmDeleteBtn" onclick="executeDelete()" class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-700 text-white transition flex items-center gap-1.5 cursor-pointer">
                            <i class="bi bi-trash3"></i> Yes, Remove
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const CSRF_TOKEN = '{{ csrf_token() }}';
        const TOGGLE_URL = '{{ route("admin.asks.config.toggle") }}';
        const STORE_GROUP_URL = '{{ route("admin.asks.config.group.store") }}';
        const STORE_OPTION_URL = '{{ route("admin.asks.config.option.store") }}';
        const STORE_TYPE_URL = '{{ route("admin.asks.config.type.store") }}';
        const BASE_CONFIG_URL = '{{ url("admin/asks/config") }}';
        let currentSelectedTypeId = 'all';
        let currentSelectedTypeCode = '';
        let pendingDelete = { type: null, id: null, extraId: null };

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function escapeJs(str) {
            if (!str) return '';
            return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        }

        /* ─── Delete Modal Handlers ──────────────────────────────── */
        function confirmDeleteEntity(type, id, name, extraId = null) {
            pendingDelete = { type, id, extraId };
            document.getElementById('deleteEntityName').textContent = name;
            let typeDesc = 'item';
            if (type === 'type') typeDesc = 'category';
            else if (type === 'option') typeDesc = 'option';
            else if (type === 'group') typeDesc = 'section & options';
            document.getElementById('deleteEntityTypeDesc').textContent = typeDesc;
            document.getElementById('confirmDeleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            pendingDelete = { type: null, id: null, extraId: null };
            document.getElementById('confirmDeleteModal').classList.add('hidden');
        }

        async function executeDelete() {
            if (!pendingDelete.id || !pendingDelete.type) return;
            const { type, id, extraId } = pendingDelete;
            const btn = document.getElementById('confirmDeleteBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i> Removing...';

            let url = '';
            if (type === 'type') url = `${BASE_CONFIG_URL}/type/${id}`;
            else if (type === 'option') url = `${BASE_CONFIG_URL}/option/${id}`;
            else if (type === 'group') url = `${BASE_CONFIG_URL}/group/${id}`;

            try {
                const res = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    closeDeleteModal();
                    showToast('success', data.message || 'Removed successfully.');

                    if (type === 'type') {
                        const el = document.getElementById('type-pill-' + id);
                        if (el) el.remove();
                        if (extraId) {
                            const countEl = document.getElementById('flow-types-count-' + extraId);
                            if (countEl) {
                                const cnt = parseInt(countEl.textContent.replace(/\D/g, '')) || 1;
                                countEl.textContent = Math.max(0, cnt - 1);
                            }
                        }
                        const filterTab = document.getElementById('subtype-tab-' + id);
                        if (filterTab) filterTab.remove();
                    } else if (type === 'option') {
                        const el = document.getElementById('option-row-' + id);
                        if (el) el.remove();
                        if (extraId) {
                            const totalEl = document.getElementById('group-total-' + extraId);
                            if (totalEl) {
                                const cnt = parseInt(totalEl.textContent.replace(/\D/g, '')) || 1;
                                totalEl.textContent = 'Total Options: ' + Math.max(0, cnt - 1);
                            }
                        }
                    } else if (type === 'group') {
                        const card = document.getElementById('group-card-' + id);
                        if (card) card.remove();
                    }
                } else {
                    showToast('error', data.message || 'Failed to remove.');
                }
            } catch (err) {
                console.error(err);
                showToast('error', 'Network error occurred while removing.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-trash3"></i> Yes, Remove';
            }
        }

        /* ─── Category / Sub-Type Modal Handlers ─────────────────── */
        function openAddTypeModal(flowId, flowName) {
            document.getElementById('addTypeForm').reset();
            document.getElementById('addTypeFlowId').value = flowId;
            document.getElementById('addTypeFlowNameBadge').textContent = flowName;
            document.getElementById('addTypeModal').classList.remove('hidden');
        }

        function closeAddTypeModal() {
            document.getElementById('addTypeModal').classList.add('hidden');
        }

        function autoGenerateTypeCode(val) {
            const codeInput = document.getElementById('addTypeCode');
            codeInput.value = val.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        }

        async function submitAddType(e) {
            e.preventDefault();
            const btn = document.getElementById('saveTypeBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i> Creating...';

            const flowId = document.getElementById('addTypeFlowId').value;
            const name = document.getElementById('addTypeName').value.trim();
            const code = document.getElementById('addTypeCode').value.trim();
            const desc = document.getElementById('addTypeDescription').value.trim();

            try {
                const res = await fetch(STORE_TYPE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        flow_id: flowId,
                        name: name,
                        code: code,
                        description: desc || null
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    closeAddTypeModal();
                    showToast('success', data.message || 'Category created successfully.');
                    appendTypePill(flowId, data.data);
                } else {
                    showToast('error', data.message || 'Failed to create category.');
                }
            } catch (err) {
                console.error(err);
                showToast('error', 'Network error occurred while creating category.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-plus-lg"></i> Add Category';
            }
        }

        function appendTypePill(flowId, type) {
            const list = document.getElementById('flow-types-list-' + flowId);
            if (!list) {
                window.location.reload();
                return;
            }

            const emptyNotice = list.querySelector('.text-slate-400.italic');
            if (emptyNotice) emptyNotice.remove();

            const pill = document.createElement('div');
            pill.className = 'type-pill group inline-flex items-center pill-active';
            pill.id = 'type-pill-' + type.id;
            pill.setAttribute('data-flow-id', flowId);

            pill.innerHTML = `
                <button type="button"
                        onclick="toggleEntity('type', '${type.id}', this)"
                        class="btn-reset type-pill-toggle"
                        data-active="1"
                        title="Visible in API. Click to turn OFF">
                    <i class="bi bi-check-circle-fill text-emerald-600 text-[10px]"></i>
                    <span class="type-name-label">${escapeHtml(type.name)}</span>
                </button>
                <span class="pill-actions-wrapper">
                    <button type="button"
                            onclick="openEditTypeModal('${type.id}', '${escapeJs(type.name)}', '${type.code}', '${escapeJs(type.description || '')}')"
                            class="btn-reset pill-action-icon edit-action"
                            title="Edit category">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button"
                            onclick="confirmDeleteEntity('type', '${type.id}', '${escapeJs(type.name)}', '${flowId}')"
                            class="btn-reset pill-action-icon delete-action"
                            title="Remove category">
                        <i class="bi bi-trash3"></i>
                    </button>
                </span>
            `;
            list.appendChild(pill);

            const countEl = document.getElementById('flow-types-count-' + flowId);
            if (countEl) {
                const cnt = parseInt(countEl.textContent.replace(/\D/g, '')) || 0;
                countEl.textContent = cnt + 1;
            }

            // Append to subtype filter row
            const filterRow = document.getElementById('subTypeFilterRow');
            if (filterRow) {
                const tab = document.createElement('button');
                tab.type = 'button';
                tab.id = 'subtype-tab-' + type.id;
                tab.onclick = function() { filterBySubType(type.id, tab, type.code); };
                tab.className = 'subtype-tab-btn px-2.5 py-1 rounded-md text-[11px] font-medium border bg-white text-slate-600 border-slate-300 hover:bg-slate-50 transition';
                tab.textContent = type.name;
                tab.setAttribute('data-flow', flowId);
                tab.setAttribute('data-type-id', type.id);
                tab.setAttribute('data-type-code', type.code);
                filterRow.appendChild(tab);
            }
        }

        function openEditTypeModal(id, name, code, description) {
            document.getElementById('editTypeId').value = id;
            document.getElementById('editTypeName').value = name;
            document.getElementById('editTypeCode').value = code;
            document.getElementById('editTypeDescription').value = description || '';
            document.getElementById('editTypeModal').classList.remove('hidden');
        }

        function closeEditTypeModal() {
            document.getElementById('editTypeModal').classList.add('hidden');
        }

        async function submitEditType(e) {
            e.preventDefault();
            const btn = document.getElementById('updateTypeBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i> Saving...';

            const id = document.getElementById('editTypeId').value;
            const name = document.getElementById('editTypeName').value.trim();
            const code = document.getElementById('editTypeCode').value.trim();
            const desc = document.getElementById('editTypeDescription').value.trim();

            try {
                const res = await fetch(`${BASE_CONFIG_URL}/type/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        name: name,
                        code: code,
                        description: desc || null
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    closeEditTypeModal();
                    showToast('success', data.message || 'Category updated successfully.');
                    const pill = document.getElementById('type-pill-' + id);
                    if (pill) {
                        const label = pill.querySelector('.type-name-label');
                        if (label) label.textContent = name;
                        const editBtn = pill.querySelector('.pill-action-icon.edit-action');
                        if (editBtn) {
                            editBtn.setAttribute('onclick', `openEditTypeModal('${id}', '${escapeJs(name)}', '${code}', '${escapeJs(desc)}')`);
                        }
                    }
                    const filterTab = document.getElementById('subtype-tab-' + id);
                    if (filterTab) filterTab.textContent = name;
                } else {
                    showToast('error', data.message || 'Failed to update category.');
                }
            } catch (err) {
                console.error(err);
                showToast('error', 'Network error occurred while updating category.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2"></i> Save Changes';
            }
        }

        /* ─── Flow Modal Handlers ────────────────────────────────── */
        function openEditFlowModal(id, name, description) {
            document.getElementById('editFlowId').value = id;
            document.getElementById('editFlowName').value = name;
            document.getElementById('editFlowDescription').value = description || '';
            document.getElementById('editFlowModal').classList.remove('hidden');
        }

        function closeEditFlowModal() {
            document.getElementById('editFlowModal').classList.add('hidden');
        }

        async function submitEditFlow(e) {
            e.preventDefault();
            const btn = document.getElementById('updateFlowBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i> Saving...';

            const id = document.getElementById('editFlowId').value;
            const name = document.getElementById('editFlowName').value.trim();
            const desc = document.getElementById('editFlowDescription').value.trim();

            try {
                const res = await fetch(`${BASE_CONFIG_URL}/flow/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        name: name,
                        description: desc || null
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    closeEditFlowModal();
                    showToast('success', data.message || 'Flow updated successfully.');
                    const titleEl = document.getElementById('flow-name-' + id);
                    if (titleEl) titleEl.textContent = name;
                    const descEl = document.getElementById('flow-desc-' + id);
                    if (descEl) descEl.textContent = desc;
                } else {
                    showToast('error', data.message || 'Failed to update flow.');
                }
            } catch (err) {
                console.error(err);
                showToast('error', 'Network error occurred while updating flow.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2"></i> Save Changes';
            }
        }

        /* ─── Group Modal Handlers ───────────────────────────────── */
        function openAddGroupModal() {
            document.getElementById('addGroupForm').reset();
            document.getElementById('addGroupModal').classList.remove('hidden');
        }

        function closeAddGroupModal() {
            document.getElementById('addGroupModal').classList.add('hidden');
        }

        function autoGenerateGroupCode(val) {
            const codeInput = document.getElementById('groupCode');
            codeInput.value = val.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        }

        async function submitAddGroup(e) {
            e.preventDefault();
            const btn = document.getElementById('saveGroupBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i> Creating...';

            const form = document.getElementById('addGroupForm');
            const formData = new FormData(form);
            const flows = [];
            form.querySelectorAll('input[name="flows[]"]:checked').forEach(cb => flows.push(cb.value));

            const payload = {
                name: formData.get('name'),
                code: formData.get('code'),
                description: formData.get('description'),
                input_type: formData.get('input_type'),
                flows: flows.length ? flows : ['collaboration'],
                initial_options: formData.get('initial_options')
            };

            try {
                const res = await fetch(STORE_GROUP_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    closeAddGroupModal();
                    showToast('success', data.message || 'Section created successfully.');
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast('error', data.message || 'Failed to create section.');
                }
            } catch (err) {
                console.error(err);
                showToast('error', 'Network error occurred while creating section.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2"></i> Create Section';
            }
        }

        function openEditGroupModal(id, name, code, inputType, description, flows) {
            document.getElementById('editGroupId').value = id;
            document.getElementById('editGroupName').value = name;
            document.getElementById('editGroupCode').value = code;
            document.getElementById('editGroupInputType').value = inputType || 'multi_select';
            document.getElementById('editGroupDescription').value = description || '';

            const flowsArr = Array.isArray(flows) ? flows : [];
            document.getElementById('editFlowCollab').checked = flowsArr.includes('collaboration') || flowsArr.includes('all');
            document.getElementById('editFlowReferral').checked = flowsArr.includes('referral') || flowsArr.includes('all');
            document.getElementById('editFlowHelp').checked = flowsArr.includes('help') || flowsArr.includes('all');

            document.getElementById('editGroupModal').classList.remove('hidden');
        }

        function closeEditGroupModal() {
            document.getElementById('editGroupModal').classList.add('hidden');
        }

        async function submitEditGroup(e) {
            e.preventDefault();
            const btn = document.getElementById('updateGroupBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i> Saving...';

            const id = document.getElementById('editGroupId').value;
            const form = document.getElementById('editGroupForm');
            const flows = [];
            form.querySelectorAll('input[name="edit_flows[]"]:checked').forEach(cb => flows.push(cb.value));

            const payload = {
                name: document.getElementById('editGroupName').value.trim(),
                code: document.getElementById('editGroupCode').value.trim(),
                description: document.getElementById('editGroupDescription').value.trim() || null,
                input_type: document.getElementById('editGroupInputType').value,
                flows: flows.length ? flows : ['collaboration']
            };

            try {
                const res = await fetch(`${BASE_CONFIG_URL}/group/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    closeEditGroupModal();
                    showToast('success', data.message || 'Section updated successfully.');
                    const nameEl = document.getElementById('group-name-' + id);
                    if (nameEl) nameEl.textContent = payload.name;
                    const codeEl = document.getElementById('group-code-' + id);
                    if (codeEl) codeEl.textContent = payload.code;
                    const card = document.getElementById('group-card-' + id);
                    if (card) card.setAttribute('data-flows', payload.flows.join(','));
                } else {
                    showToast('error', data.message || 'Failed to update section.');
                }
            } catch (err) {
                console.error(err);
                showToast('error', 'Network error occurred while updating section.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2"></i> Save Changes';
            }
        }

        /* ─── Option Modal Handlers ──────────────────────────────── */
        function openAddOptionModal(groupId, groupName) {
            document.getElementById('addOptionForm').reset();
            document.getElementById('optionGroupId').value = groupId;
            document.getElementById('modalGroupNameBadge').textContent = groupName;
            const typeSelect = document.getElementById('optionAskTypeId');
            if (typeSelect) {
                typeSelect.value = (currentSelectedTypeId !== 'all') ? currentSelectedTypeId : '';
            }
            document.getElementById('addOptionModal').classList.remove('hidden');
        }

        function closeAddOptionModal() {
            document.getElementById('addOptionModal').classList.add('hidden');
        }

        function autoGenerateOptionCode(val) {
            const codeInput = document.getElementById('optionCode');
            codeInput.value = val.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        }

        async function submitAddOption(e) {
            e.preventDefault();
            const btn = document.getElementById('saveOptionBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i> Saving...';

            const groupId = document.getElementById('optionGroupId').value;
            const label = document.getElementById('optionLabel').value.trim();
            const code = document.getElementById('optionCode').value.trim();
            const desc = document.getElementById('optionDescription').value.trim();
            const typeSelect = document.getElementById('optionAskTypeId');
            const askTypeId = typeSelect ? typeSelect.value : null;

            try {
                const res = await fetch(STORE_OPTION_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        option_group_id: groupId,
                        ask_type_id: askTypeId || null,
                        label: label,
                        code: code,
                        description: desc || null
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    closeAddOptionModal();
                    showToast('success', data.message || 'Option created successfully.');
                    appendOptionRow(groupId, data.data);
                } else {
                    showToast('error', data.message || 'Failed to save option.');
                }
            } catch (err) {
                console.error(err);
                showToast('error', 'Network error occurred while saving option.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-plus-lg"></i> Add Option';
            }
        }

        function appendOptionRow(groupId, opt) {
            const list = document.getElementById('options-list-' + groupId);
            if (!list) {
                window.location.reload();
                return;
            }

            const emptyNotice = list.querySelector('.text-slate-400.italic');
            if (emptyNotice) emptyNotice.remove();

            const row = document.createElement('div');
            row.className = 'option-item flex items-center justify-between p-2 rounded-lg border text-xs transition bg-slate-50/80 border-slate-200 text-slate-800';
            row.id = 'option-row-' + opt.id;
            row.setAttribute('data-type-id', opt.ask_type_id || '');
            row.setAttribute('data-is-active', '1');

            const badgeHtml = opt.ask_type_name 
                ? `<span class="px-1.5 py-0.5 rounded text-[9px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100 flex-none subtype-badge">${escapeHtml(opt.ask_type_name)}</span>`
                : '';

            row.innerHTML = `
                <div class="overflow-hidden pr-2">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="font-medium text-slate-800 option-label-text">${escapeHtml(opt.label)}</span>
                        ${badgeHtml}
                    </div>
                    <span class="text-[10px] text-slate-400 font-mono option-code-text">${escapeHtml(opt.code)}</span>
                </div>
                <div class="flex items-center gap-1 flex-none">
                    <button type="button"
                            onclick="openEditOptionModal('${opt.id}', '${escapeJs(opt.label)}', '${opt.code}', '${opt.ask_type_id || ''}', '${escapeJs(opt.description || '')}')"
                            class="clean-action-btn btn-edit"
                            title="Edit option">
                        <i class="bi bi-pencil text-xs"></i>
                    </button>
                    <button type="button"
                            onclick="confirmDeleteEntity('option', '${opt.id}', '${escapeJs(opt.label)}', '${groupId}')"
                            class="clean-action-btn btn-delete"
                            title="Remove option">
                        <i class="bi bi-trash3 text-xs"></i>
                    </button>
                    <button type="button"
                            onclick="toggleEntity('option', '${opt.id}', this)"
                            class="btn-reset toggle-switch-btn px-2 py-0.5 rounded text-[10px] font-semibold border flex items-center gap-1 transition flex-none bg-white text-emerald-700 border-emerald-200 shadow-2xs hover:bg-emerald-50"
                            data-active="1"
                            title="Visible in API. Click to turn OFF">
                        <i class="bi bi-toggle-on text-emerald-600"></i>
                        <span class="label-text">ON</span>
                    </button>
                </div>
            `;
            list.appendChild(row);

            if (currentSelectedTypeId !== 'all' && opt.ask_type_id !== currentSelectedTypeId) {
                row.style.display = 'none';
            }

            const totalEl = document.getElementById('group-total-' + groupId);
            const activeEl = document.getElementById('group-active-' + groupId);
            if (totalEl) {
                const currentTotal = parseInt(totalEl.textContent.replace(/\D/g, '')) || 0;
                totalEl.textContent = 'Total Options: ' + (currentTotal + 1);
            }
            if (activeEl) {
                const currentActive = parseInt(activeEl.textContent.replace(/\D/g, '')) || 0;
                activeEl.textContent = 'Active: ' + (currentActive + 1);
            }
        }

        function openEditOptionModal(id, label, code, askTypeId, description) {
            document.getElementById('editOptionId').value = id;
            document.getElementById('editOptionLabel').value = label;
            document.getElementById('editOptionCode').value = code;
            document.getElementById('editOptionAskTypeId').value = askTypeId || '';
            document.getElementById('editOptionDescription').value = description || '';
            document.getElementById('editOptionModal').classList.remove('hidden');
        }

        function closeEditOptionModal() {
            document.getElementById('editOptionModal').classList.add('hidden');
        }

        async function submitEditOption(e) {
            e.preventDefault();
            const btn = document.getElementById('updateOptionBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i> Saving...';

            const id = document.getElementById('editOptionId').value;
            const label = document.getElementById('editOptionLabel').value.trim();
            const code = document.getElementById('editOptionCode').value.trim();
            const askTypeId = document.getElementById('editOptionAskTypeId').value;
            const desc = document.getElementById('editOptionDescription').value.trim();

            try {
                const res = await fetch(`${BASE_CONFIG_URL}/option/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        label: label,
                        code: code,
                        ask_type_id: askTypeId || null,
                        description: desc || null
                    })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    closeEditOptionModal();
                    showToast('success', data.message || 'Option updated successfully.');
                    const row = document.getElementById('option-row-' + id);
                    if (row) {
                        const labelEl = row.querySelector('.option-label-text');
                        if (labelEl) labelEl.textContent = label;
                        const codeEl = row.querySelector('.option-code-text');
                        if (codeEl) codeEl.textContent = code;
                        let badge = row.querySelector('.subtype-badge');
                        if (data.data?.ask_type_name) {
                            if (!badge) {
                                badge = document.createElement('span');
                                badge.className = 'px-1.5 py-0.5 rounded text-[9px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100 flex-none subtype-badge';
                                labelEl.after(badge);
                            }
                            badge.textContent = data.data.ask_type_name;
                        } else if (badge) {
                            badge.remove();
                        }
                        row.setAttribute('data-type-id', askTypeId || '');
                    }
                } else {
                    showToast('error', data.message || 'Failed to update option.');
                }
            } catch (err) {
                console.error(err);
                showToast('error', 'Network error occurred while updating option.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2"></i> Save Changes';
            }
        }

        /* ─── Toggle Status Handler ──────────────────────────────── */
        async function toggleEntity(type, id, btnElement) {
            const currentActive = btnElement.getAttribute('data-active') === '1';
            const targetActive = !currentActive;

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

                    if (type === 'type') {
                        const pill = btnElement.closest('.type-pill') || document.getElementById('type-pill-' + id);
                        if (pill) {
                            if (isActive) {
                                pill.classList.remove('pill-inactive');
                                pill.classList.add('pill-active');
                            } else {
                                pill.classList.remove('pill-active');
                                pill.classList.add('pill-inactive');
                            }
                        }
                        const icon = btnElement.querySelector('i');
                        if (icon) {
                            icon.className = isActive ? 'bi bi-check-circle-fill text-emerald-600 text-[10px]' : 'bi bi-dash-circle text-slate-400 text-[10px]';
                        }
                        btnElement.title = isActive ? 'Visible in API. Click to turn OFF' : 'Hidden in API. Click to turn ON';
                    } else if (type === 'flow') {
                        const dot = btnElement.querySelector('span:first-child');
                        const label = btnElement.querySelector('.label-text');
                        if (isActive) {
                            btnElement.className = 'btn-reset toggle-switch-btn px-2.5 py-1 rounded-full text-[11px] font-semibold flex items-center gap-1.5 border transition bg-emerald-50 text-emerald-700 border-emerald-300 hover:bg-emerald-100';
                            dot.className = 'w-2 h-2 rounded-full bg-emerald-500';
                            label.textContent = 'Flow Active';
                        } else {
                            btnElement.className = 'btn-reset toggle-switch-btn px-2.5 py-1 rounded-full text-[11px] font-semibold flex items-center gap-1.5 border transition bg-slate-100 text-slate-500 border-slate-300 hover:bg-slate-200';
                            dot.className = 'w-2 h-2 rounded-full bg-slate-400';
                            label.textContent = 'Flow Inactive';
                        }
                    } else if (type === 'group') {
                        const dot = btnElement.querySelector('span:first-child');
                        const label = btnElement.querySelector('.label-text');
                        if (isActive) {
                            btnElement.className = 'btn-reset toggle-switch-btn px-2.5 py-0.5 rounded-full text-[10px] font-semibold border flex items-center gap-1 transition bg-emerald-50 text-emerald-700 border-emerald-300';
                            dot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500';
                            label.textContent = 'Group ON';
                        } else {
                            btnElement.className = 'btn-reset toggle-switch-btn px-2.5 py-0.5 rounded-full text-[10px] font-semibold border flex items-center gap-1 transition bg-slate-100 text-slate-400 border-slate-200';
                            dot.className = 'w-1.5 h-1.5 rounded-full bg-slate-400';
                            label.textContent = 'Group OFF';
                        }
                    } else if (type === 'option') {
                        const icon = btnElement.querySelector('i');
                        const label = btnElement.querySelector('.label-text');
                        const row = document.getElementById('option-row-' + id);
                        const rowTitle = row ? row.querySelector('.option-label-text') : null;

                        if (isActive) {
                            btnElement.className = 'btn-reset toggle-switch-btn px-2 py-0.5 rounded text-[10px] font-semibold border flex items-center gap-1 transition flex-none bg-white text-emerald-700 border-emerald-200 shadow-2xs hover:bg-emerald-50';
                            icon.className = 'bi bi-toggle-on text-emerald-600';
                            label.textContent = 'ON';
                            btnElement.title = 'Visible in API. Click to turn OFF';
                            if (rowTitle) rowTitle.className = 'font-medium text-slate-800 option-label-text';
                        } else {
                            btnElement.className = 'btn-reset toggle-switch-btn px-2 py-0.5 rounded text-[10px] font-semibold border flex items-center gap-1 transition flex-none bg-slate-200 text-slate-500 border-slate-300 hover:bg-slate-300';
                            icon.className = 'bi bi-toggle-off text-slate-400';
                            label.textContent = 'OFF';
                            btnElement.title = 'Hidden in API. Click to turn ON';
                            if (rowTitle) rowTitle.className = 'font-medium line-through text-slate-400 option-label-text';
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

            const subTypeButtons = document.querySelectorAll('.subtype-tab-btn');
            let hasVisibleSubTypes = false;
            subTypeButtons.forEach(btn => {
                const btnFlow = btn.getAttribute('data-flow');
                if (btnFlow === 'all') {
                    btn.style.display = '';
                } else if (flowCode === 'all' || btnFlow === flowCode) {
                    btn.style.display = '';
                    hasVisibleSubTypes = true;
                } else {
                    btn.style.display = 'none';
                }
            });

            const subTypeRow = document.getElementById('subTypeFilterRow');
            if (subTypeRow) {
                subTypeRow.style.display = (flowCode === 'all' || hasVisibleSubTypes) ? '' : 'none';
            }

            const allBtn = document.getElementById('subtype-tab-all');
            if (allBtn) {
                filterBySubType('all', allBtn);
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

        function filterBySubType(typeId, tabElement, typeCode) {
            currentSelectedTypeId = typeId;
            currentSelectedTypeCode = typeCode || '';
            document.querySelectorAll('.subtype-tab-btn').forEach(btn => {
                btn.className = 'subtype-tab-btn px-2.5 py-1 rounded-md text-[11px] font-medium border bg-white text-slate-600 border-slate-300 hover:bg-slate-50 transition';
            });
            if (tabElement) {
                tabElement.className = 'subtype-tab-btn px-2.5 py-1 rounded-md text-[11px] font-semibold border bg-indigo-600 text-white border-indigo-600 transition shadow-2xs';
            }

            const cards = document.querySelectorAll('.option-group-card');
            cards.forEach(card => {
                const optionRows = card.querySelectorAll('.option-item');
                let visibleCount = 0;
                let activeVisibleCount = 0;

                const hasTypeSpecificOptions = (typeId !== 'all') && Array.from(optionRows).some(row => {
                    const rId = row.getAttribute('data-type-id') || '';
                    const rCode = row.getAttribute('data-type-code') || '';
                    return (rId && rId === typeId) || (typeCode && rCode === typeCode);
                });

                optionRows.forEach(row => {
                    const rId = row.getAttribute('data-type-id') || '';
                    const rCode = row.getAttribute('data-type-code') || '';
                    const isActive = row.getAttribute('data-is-active') === '1';

                    if (typeId === 'all') {
                        row.style.display = '';
                        visibleCount++;
                        if (isActive) activeVisibleCount++;
                    } else {
                        const matchesType = (rId && rId === typeId) || (typeCode && rCode === typeCode);
                        const isGeneral = (!rId && !rCode);

                        if (matchesType || (!hasTypeSpecificOptions && isGeneral)) {
                            row.style.display = '';
                            visibleCount++;
                            if (isActive) activeVisibleCount++;
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });

                const groupId = card.id.replace('group-card-', '');
                const totalEl = document.getElementById('group-total-' + groupId);
                const activeEl = document.getElementById('group-active-' + groupId);
                if (totalEl) totalEl.textContent = (typeId === 'all' ? 'Total Options: ' : 'Showing: ') + visibleCount;
                if (activeEl) activeEl.textContent = 'Active: ' + activeVisibleCount;
            });
        }
    </script>
    @endpush
@endsection
