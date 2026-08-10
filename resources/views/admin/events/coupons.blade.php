@extends('admin.layouts.app')

@section('title', 'Event Coupons Management')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4 max-w-full min-w-0">

    <!-- Top Action Row -->
    <div class="flex flex-wrap justify-between items-center gap-3 mb-2">
      <div>
        <h2 class="text-base font-bold tracking-wider uppercase text-indigo-600 font-display m-0 flex items-center gap-2">
          <i class="bi bi-ticket-perforated text-lg"></i>Event Coupons Management
        </h2>
        <p class="text-xs t3 m-0 mt-0.5">Create and manage full, percentage-based, or fixed-amount discount coupon codes for events</p>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" onclick="openCreateModal()" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition focus-ring flex items-center gap-1.5 shadow-2xs cursor-pointer">
          <i class="bi bi-plus-lg"></i> Create Coupon Code
        </button>
      </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
    <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-center justify-between gap-2 shadow-xs">
        <div class="flex items-center gap-2">
            <i class="bi bi-check-circle-fill text-emerald-600 text-sm"></i>
            <span class="font-medium">{{ session('success') }}</span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 text-xs">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    @endif

    @if(session('error'))
    <div class="p-3.5 bg-rose-50 border border-rose-200 text-rose-800 text-xs rounded-xl flex items-center justify-between gap-2 shadow-xs">
        <div class="flex items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill text-rose-600 text-sm"></i>
            <span class="font-medium">{{ session('error') }}</span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700 text-xs">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    @endif

    @if($errors->any())
    <div class="p-3.5 bg-rose-50 border border-rose-200 text-rose-800 text-xs rounded-xl space-y-1 shadow-xs">
        <div class="flex items-center gap-2 font-semibold text-rose-900">
            <i class="bi bi-x-circle-fill text-rose-600 text-sm"></i>
            <span>Please correct the errors below:</span>
        </div>
        <ul class="list-disc list-inside pl-5 space-y-0.5 text-rose-700 text-[11.5px]">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- KPI Summary Cards Row -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-title">Total Coupons</div>
          <div class="kpi-icon bg-indigo-50 text-indigo-600"><i class="bi bi-ticket-perforated"></i></div>
        </div>
        <div class="kpi-num">{{ number_format($stats['total'] ?? $coupons->total()) }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-title">Active Coupons</div>
          <div class="kpi-icon bg-emerald-50 text-emerald-600"><i class="bi bi-check-circle"></i></div>
        </div>
        <div class="kpi-num">{{ number_format($stats['active'] ?? 0) }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-title">Total Redemptions</div>
          <div class="kpi-icon bg-purple-50 text-purple-600"><i class="bi bi-people"></i></div>
        </div>
        <div class="kpi-num">{{ number_format($stats['redemptions'] ?? 0) }}</div>
      </div>
    </div>

    <!-- Search & Filters Toolbar -->
    <form method="GET" action="{{ route('admin.event-coupons.index') }}" class="surface-2 rounded-xl border bs p-3.5 space-y-3">
      <div class="flex flex-wrap items-center gap-2.5">
        <div class="flex-1 min-w-[200px]">
          <input type="text" name="search" value="{{ request('search') }}" placeholder="Search coupon code, name, or description..." class="w-full text-xs bg-white border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
        </div>
        <div class="w-40">
          <select name="discount_type" class="w-full text-xs bg-white border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            <option value="">All Discount Types</option>
            <option value="full" @selected(request('discount_type') === 'full')>Full (100% Free)</option>
            <option value="percentage" @selected(request('discount_type') === 'percentage')>Percentage (%)</option>
            <option value="fixed" @selected(request('discount_type') === 'fixed')>Fixed Money (₹)</option>
          </select>
        </div>
        <div class="w-44">
          <select name="event_id" class="w-full text-xs bg-white border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            <option value="">All Event Scopes</option>
            @foreach($events as $ev)
              <option value="{{ $ev->id }}" @selected(request('event_id') == $ev->id)>{{ $ev->title }}</option>
            @endforeach
          </select>
        </div>
        <div class="w-32">
          <select name="is_active" class="w-full text-xs bg-white border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            <option value="">All Statuses</option>
            <option value="true" @selected(request('is_active') === 'true')>Active</option>
            <option value="false" @selected(request('is_active') === 'false')>Inactive</option>
          </select>
        </div>
        <button type="submit" class="px-3.5 py-2 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition">
          <i class="bi bi-search mr-1"></i> Filter
        </button>
        @if(request()->anyFilled(['search', 'discount_type', 'is_active', 'event_id']))
          <a href="{{ route('admin.event-coupons.index') }}" class="px-3.5 py-2 bg-gray-100 text-gray-600 text-xs font-medium rounded-lg hover:bg-gray-200 transition no-underline">Clear</a>
        @endif
      </div>
    </form>

    <!-- Coupons Data Table -->
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
      <table class="w-full text-xs text-left text-gray-600">
        <thead class="bg-gray-50 text-gray-700 font-semibold border-b border-gray-200 uppercase tracking-wider text-[11px]">
          <tr>
            <th class="px-4 py-3">Code</th>
            <th class="px-4 py-3">Name / Description</th>
            <th class="px-4 py-3">Discount Type</th>
            <th class="px-4 py-3">Value</th>
            <th class="px-4 py-3">Usage</th>
            <th class="px-4 py-3">Event Scope</th>
            <th class="px-4 py-3">Validity</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          @forelse($coupons as $coupon)
          <tr class="hover:bg-gray-50/80 transition">
            <td class="px-4 py-3 font-mono font-bold text-indigo-700 text-xs">
              <div class="inline-flex items-center gap-1.5">
                <span class="px-2.5 py-1 bg-indigo-50 border border-indigo-200 text-indigo-700 rounded-md tracking-wider font-semibold select-all">
                  {{ $coupon->code }}
                </span>
                <button type="button" onclick="copyCouponCode('{{ $coupon->code }}', this)" class="text-gray-400 hover:text-indigo-600 transition p-1" title="Copy code">
                  <i class="bi bi-copy text-[11px]"></i>
                </button>
              </div>
            </td>
            <td class="px-4 py-3">
              <div class="font-semibold text-gray-900">{{ $coupon->name ?? '—' }}</div>
              @if(!empty($coupon->description))
                <div class="text-[11px] text-gray-500 line-clamp-1 mt-0.5" title="{{ $coupon->description }}">
                  {{ $coupon->description }}
                </div>
              @endif
            </td>
            <td class="px-4 py-3">
              @if($coupon->discount_type === 'full')
                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 font-semibold text-[10px] rounded-full uppercase tracking-wide">100% Free</span>
              @elseif($coupon->discount_type === 'percentage')
                <span class="px-2 py-0.5 bg-blue-100 text-blue-800 font-semibold text-[10px] rounded-full uppercase tracking-wide">Percentage</span>
              @else
                <span class="px-2 py-0.5 bg-purple-100 text-purple-800 font-semibold text-[10px] rounded-full uppercase tracking-wide">Fixed Amount</span>
              @endif
            </td>
            <td class="px-4 py-3 font-semibold text-gray-900">
              @if($coupon->discount_type === 'full')
                <span class="text-emerald-700 font-bold">100% OFF</span>
              @elseif($coupon->discount_type === 'percentage')
                <span class="text-blue-700 font-bold">{{ (float)$coupon->discount_value }}% OFF</span>
              @else
                <span class="text-purple-700 font-bold">₹{{ number_format((float)$coupon->discount_value, 2) }} OFF</span>
              @endif
            </td>
            <td class="px-4 py-3 text-gray-600">
              <div class="flex items-center gap-1.5">
                <span class="font-semibold text-gray-900">{{ $coupon->used_count }}</span>
                <span class="text-gray-400">/</span>
                <span class="text-gray-600">{{ $coupon->max_uses ? number_format($coupon->max_uses) : '∞' }}</span>
              </div>
              @if($coupon->max_uses)
                @php
                  $percentUsed = min(100, round(($coupon->used_count / max(1, $coupon->max_uses)) * 100));
                @endphp
                <div class="w-16 bg-gray-100 rounded-full h-1 mt-1 overflow-hidden">
                  <div class="bg-indigo-600 h-1 rounded-full" style="width: {{ $percentUsed }}%"></div>
                </div>
              @endif
            </td>
            <td class="px-4 py-3 text-gray-600">
              @if($coupon->event)
                <span class="font-medium text-gray-900">{{ $coupon->event->title }}</span>
              @else
                <span class="px-2 py-0.5 bg-gray-100 text-gray-700 text-[11px] rounded-md font-medium">All Events</span>
              @endif
            </td>
            <td class="px-4 py-3 text-gray-600 text-[11.5px]">
              @if($coupon->valid_from || $coupon->valid_until)
                <div>
                  @if($coupon->valid_from)
                    <span class="text-gray-500">From:</span> {{ $coupon->valid_from->format('d M Y') }}
                  @endif
                </div>
                <div>
                  @if($coupon->valid_until)
                    <span class="text-gray-500">Until:</span> {{ $coupon->valid_until->format('d M Y') }}
                  @endif
                </div>
              @else
                <span class="text-gray-400">Always valid</span>
              @endif
            </td>
            <td class="px-4 py-3">
              @if($coupon->is_active)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-medium rounded-md">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                </span>
              @else
                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 text-gray-600 border border-gray-200 text-[11px] font-medium rounded-md">
                  <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Inactive
                </span>
              @endif
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-1.5">
                <button type="button" onclick='openEditModal(@json($coupon))' class="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition" title="Edit coupon">
                  <i class="bi bi-pencil text-sm"></i>
                </button>
                <form method="POST" action="{{ route('admin.event-coupons.destroy', $coupon->id) }}" onsubmit="return confirm('Are you sure you want to delete coupon code {{ $coupon->code }}?');" class="inline">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete coupon">
                    <i class="bi bi-trash text-sm"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="9" class="px-4 py-12 text-center text-gray-400 text-xs">
              <i class="bi bi-ticket-perforated text-4xl mb-2 block text-gray-300"></i>
              <p class="font-medium text-gray-600 mb-1">No coupon codes found</p>
              <p class="text-gray-400 text-[11px] mb-3">Create discount coupon codes to allow participants and visitors to register with special rates or free entry.</p>
              <button type="button" onclick="openCreateModal()" class="px-3.5 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 transition shadow-2xs">
                + Create First Coupon
              </button>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    @if($coupons->hasPages())
    <div class="mt-4">
      {{ $coupons->links() }}
    </div>
    @endif

</div>

<!-- Create / Edit Modal -->
<div id="couponModal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl border border-gray-200 shadow-xl max-w-lg w-full p-6 space-y-4 relative my-8">
    <div class="flex justify-between items-center border-b border-gray-100 pb-3">
      <h3 id="modalTitle" class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
        <i class="bi bi-ticket-perforated text-indigo-600 text-base"></i> Create Coupon Code
      </h3>
      <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg transition">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <form id="couponForm" method="POST" action="{{ route('admin.event-coupons.store') }}" class="space-y-4">
      @csrf
      <div id="methodField"></div>

      <!-- Code Option (Manual vs Auto Random) -->
      <div id="codeOptionWrapper" class="space-y-1.5">
        <label class="text-xs font-semibold text-gray-700 block">Coupon Code Generation</label>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
            <input type="radio" name="generate_code" value="0" checked onclick="toggleCodeMode(false)" class="text-indigo-600 focus:ring-indigo-500">
            <span>Manual Code</span>
          </label>
          <label class="flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
            <input type="radio" name="generate_code" value="1" onclick="toggleCodeMode(true)" class="text-indigo-600 focus:ring-indigo-500">
            <span>Generate Random Code</span>
          </label>
        </div>
      </div>

      <!-- Manual Code Input -->
      <div id="manualCodeBox">
        <div class="flex justify-between items-center mb-1">
          <label class="text-xs font-semibold text-gray-700 block">Coupon Code <span class="text-red-500">*</span></label>
          <button type="button" id="quickGenBtn" onclick="generateQuickCode()" class="text-[11px] text-indigo-600 hover:text-indigo-800 font-medium cursor-pointer">
            <i class="bi bi-magic mr-0.5"></i> Quick Generate
          </button>
        </div>
        <input type="text" id="couponCodeInput" name="code" placeholder="e.g. VIPPASS60" class="w-full text-xs font-mono uppercase bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
      </div>

      <!-- Random Code Prefix -->
      <div id="randomCodeBox" class="hidden">
        <label class="text-xs font-semibold text-gray-700 block mb-1">Code Prefix (Optional)</label>
        <input type="text" id="codePrefixInput" name="code_prefix" placeholder="e.g. VIP" class="w-full text-xs font-mono uppercase bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
        <p class="text-[11px] text-gray-500 mt-1">Generates a random unique coupon code like <code>VIP-X89A2B</code> upon saving.</p>
      </div>

      <!-- Name & Description -->
      <div>
        <label class="text-xs font-semibold text-gray-700 block mb-1">Coupon Name</label>
        <input type="text" id="couponNameInput" name="name" placeholder="e.g. Summer Special 60% Off" class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
      </div>

      <div>
        <label class="text-xs font-semibold text-gray-700 block mb-1">Description (Optional)</label>
        <textarea id="couponDescriptionInput" name="description" rows="2" placeholder="Brief note about who this coupon is for…" class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none"></textarea>
      </div>

      <!-- Discount Type & Value -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-semibold text-gray-700 block mb-1">Discount Type <span class="text-red-500">*</span></label>
          <select id="discountTypeSelect" name="discount_type" onchange="toggleDiscountValueField()" class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
            <option value="percentage">Percentage (%)</option>
            <option value="fixed">Fixed Money (₹)</option>
            <option value="full">100% Full Discount (Free)</option>
          </select>
        </div>
        <div id="discountValueWrapper">
          <label id="discountValueLabel" class="text-xs font-semibold text-gray-700 block mb-1">Discount Value <span class="text-red-500">*</span></label>
          <input type="number" step="0.01" min="0" id="discountValueInput" name="discount_value" placeholder="e.g. 60" class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
      </div>

      <!-- Max Uses & Event Scope -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-semibold text-gray-700 block mb-1">Max Uses (Usage Limit)</label>
          <input type="number" min="1" id="maxUsesInput" name="max_uses" placeholder="Unlimited if empty" class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-700 block mb-1">Restrict to Event</label>
          <select id="eventIdSelect" name="event_id" class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
            <option value="">All Events</option>
            @foreach($events as $ev)
              <option value="{{ $ev->id }}">{{ $ev->title }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <!-- Validity Dates -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-semibold text-gray-700 block mb-1">Valid From</label>
          <input type="date" id="validFromInput" name="valid_from" class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-700 block mb-1">Valid Until</label>
          <input type="date" id="validUntilInput" name="valid_until" class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
      </div>

      <!-- Active Checkbox -->
      <div class="flex items-center gap-2 pt-1">
        <input type="checkbox" id="isActiveInput" name="is_active" value="1" checked class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500">
        <label for="isActiveInput" class="text-xs font-medium text-gray-700 cursor-pointer select-none">Active and available for checkout / registration</label>
      </div>

      <!-- Submit Buttons -->
      <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
        <button type="button" onclick="closeModal()" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition">Cancel</button>
        <button type="submit" id="saveCouponBtn" class="px-4 py-2 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition shadow-2xs">Save Coupon</button>
      </div>
    </form>
  </div>
</div>

<script>
function openCreateModal() {
  document.getElementById('modalTitle').innerHTML = '<i class="bi bi-ticket-perforated text-indigo-600 text-base"></i> Create Coupon Code';
  document.getElementById('couponForm').action = "{{ route('admin.event-coupons.store') }}";
  document.getElementById('methodField').innerHTML = '';
  document.getElementById('codeOptionWrapper').classList.remove('hidden');
  document.getElementById('quickGenBtn').classList.remove('hidden');
  document.getElementById('couponCodeInput').required = true;
  document.getElementById('couponCodeInput').readOnly = false;
  document.getElementById('couponCodeInput').value = '';
  document.getElementById('couponNameInput').value = '';
  document.getElementById('couponDescriptionInput').value = '';
  document.getElementById('discountTypeSelect').value = 'percentage';
  document.getElementById('discountValueInput').value = '';
  document.getElementById('maxUsesInput').value = '';
  document.getElementById('eventIdSelect').value = '';
  document.getElementById('validFromInput').value = '';
  document.getElementById('validUntilInput').value = '';
  document.getElementById('isActiveInput').checked = true;
  document.getElementById('saveCouponBtn').textContent = 'Save Coupon';
  toggleCodeMode(false);
  toggleDiscountValueField();
  document.getElementById('couponModal').classList.remove('hidden');
}

function openEditModal(coupon) {
  document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil text-indigo-600 text-base"></i> Edit Coupon Code (' + coupon.code + ')';
  document.getElementById('couponForm').action = "/admin/event-coupons/" + coupon.id;
  document.getElementById('methodField').innerHTML = '<input type="hidden" name="_method" value="PUT">';
  document.getElementById('codeOptionWrapper').classList.add('hidden');
  document.getElementById('quickGenBtn').classList.add('hidden');
  document.getElementById('manualCodeBox').classList.remove('hidden');
  document.getElementById('randomCodeBox').classList.add('hidden');
  document.getElementById('couponCodeInput').value = coupon.code;
  document.getElementById('couponCodeInput').readOnly = false;
  document.getElementById('couponCodeInput').required = true;
  document.getElementById('couponNameInput').value = coupon.name || '';
  document.getElementById('couponDescriptionInput').value = coupon.description || '';
  document.getElementById('discountTypeSelect').value = coupon.discount_type;
  document.getElementById('discountValueInput').value = (coupon.discount_type === 'full') ? '' : (coupon.discount_value || '');
  document.getElementById('maxUsesInput').value = coupon.max_uses || '';
  document.getElementById('eventIdSelect').value = coupon.event_id || '';
  
  if (coupon.valid_from) {
    document.getElementById('validFromInput').value = coupon.valid_from.substring(0, 10);
  } else {
    document.getElementById('validFromInput').value = '';
  }
  
  if (coupon.valid_until) {
    document.getElementById('validUntilInput').value = coupon.valid_until.substring(0, 10);
  } else {
    document.getElementById('validUntilInput').value = '';
  }

  document.getElementById('isActiveInput').checked = Boolean(coupon.is_active);
  document.getElementById('saveCouponBtn').textContent = 'Update Coupon';
  toggleDiscountValueField();
  document.getElementById('couponModal').classList.remove('hidden');
}

function closeModal() {
  document.getElementById('couponModal').classList.add('hidden');
}

function toggleCodeMode(isRandom) {
  if (isRandom) {
    document.getElementById('manualCodeBox').classList.add('hidden');
    document.getElementById('randomCodeBox').classList.remove('hidden');
    document.getElementById('couponCodeInput').required = false;
  } else {
    document.getElementById('manualCodeBox').classList.remove('hidden');
    document.getElementById('randomCodeBox').classList.add('hidden');
    document.getElementById('couponCodeInput').required = true;
  }
}

function toggleDiscountValueField() {
  const type = document.getElementById('discountTypeSelect').value;
  const wrapper = document.getElementById('discountValueWrapper');
  const input = document.getElementById('discountValueInput');
  const label = document.getElementById('discountValueLabel');
  
  if (type === 'full') {
    wrapper.classList.add('hidden');
    input.required = false;
    input.value = '';
  } else {
    wrapper.classList.remove('hidden');
    input.required = true;
    if (type === 'percentage') {
      label.innerHTML = 'Discount Percentage (%) <span class="text-red-500">*</span>';
      input.placeholder = 'e.g. 60';
      input.max = '100';
    } else {
      label.innerHTML = 'Discount Amount (₹) <span class="text-red-500">*</span>';
      input.placeholder = 'e.g. 500';
      input.removeAttribute('max');
    }
  }
}

function generateQuickCode() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  let random = '';
  for (let i = 0; i < 6; i++) {
    random += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  const prefix = document.getElementById('couponNameInput').value
    ? document.getElementById('couponNameInput').value.replace(/[^A-Za-z0-9]/g, '').substring(0, 4).toUpperCase()
    : 'SAVE';
  document.getElementById('couponCodeInput').value = prefix + '-' + random;
}

function copyCouponCode(code, btn) {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(code).then(() => {
      showCopiedFeedback(btn);
    });
  } else {
    const textArea = document.createElement('textarea');
    textArea.value = code;
    document.body.appendChild(textArea);
    textArea.select();
    try {
      document.execCommand('copy');
      showCopiedFeedback(btn);
    } catch (e) {}
    document.body.removeChild(textArea);
  }
}

function showCopiedFeedback(btn) {
  const originalHtml = btn.innerHTML;
  btn.innerHTML = '<i class="bi bi-check text-emerald-600 text-xs"></i>';
  setTimeout(() => {
    btn.innerHTML = originalHtml;
  }, 1500);
}
</script>
@endsection

