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
        <button onclick="openCreateModal()" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition focus-ring flex items-center gap-1.5 shadow-2xs cursor-pointer">
          <i class="bi bi-plus-lg"></i> Create Coupon Code
        </button>
      </div>
    </div>

    @if(session('success'))
    <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-lg flex items-center gap-2">
        <i class="bi bi-check-circle-fill text-emerald-600 text-sm"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    <!-- KPI Summary Cards Row -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-title">Total Coupons</div>
          <div class="kpi-icon bg-indigo-50 text-indigo-600"><i class="bi bi-ticket-perforated"></i></div>
        </div>
        <div class="kpi-num">{{ $coupons->total() }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-title">Active Coupons</div>
          <div class="kpi-icon bg-emerald-50 text-emerald-600"><i class="bi bi-check-circle"></i></div>
        </div>
        <div class="kpi-num">{{ $coupons->getCollection()->filter(fn($c) => $c->is_active)->count() }}</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-top">
          <div class="kpi-title">Total Redemptions</div>
          <div class="kpi-icon bg-purple-50 text-purple-600"><i class="bi bi-people"></i></div>
        </div>
        <div class="kpi-num">{{ $coupons->getCollection()->sum('used_count') }}</div>
      </div>
    </div>

    <!-- Search & Filters Toolbar -->
    <form method="GET" action="{{ route('admin.event-coupons.index') }}" class="surface-2 rounded-xl border bs p-3.5 space-y-3">
      <div class="flex flex-wrap items-center gap-2.5">
        <div class="flex-1 min-w-[200px]">
          <input type="text" name="search" value="{{ request('search') }}" placeholder="Search coupon code or name..." class="w-full text-xs bg-white border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
        </div>
        <div class="w-40">
          <select name="discount_type" class="w-full text-xs bg-white border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            <option value="">All Discount Types</option>
            <option value="full" @selected(request('discount_type') === 'full')>Full (100% Off)</option>
            <option value="percentage" @selected(request('discount_type') === 'percentage')>Percentage (%)</option>
            <option value="fixed" @selected(request('discount_type') === 'fixed')>Fixed Money (₹)</option>
          </select>
        </div>
        <div class="w-36">
          <select name="is_active" class="w-full text-xs bg-white border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            <option value="">All Statuses</option>
            <option value="true" @selected(request('is_active') === 'true')>Active</option>
            <option value="false" @selected(request('is_active') === 'false')>Inactive</option>
          </select>
        </div>
        <button type="submit" class="px-3.5 py-2 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition">
          <i class="bi bi-search mr-1"></i> Filter
        </button>
        @if(request()->anyFilled(['search', 'discount_type', 'is_active']))
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
            <th class="px-4 py-3">Name</th>
            <th class="px-4 py-3">Discount Type</th>
            <th class="px-4 py-3">Value</th>
            <th class="px-4 py-3">Usage</th>
            <th class="px-4 py-3">Event Scope</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          @forelse($coupons as $coupon)
          <tr class="hover:bg-gray-50/80 transition">
            <td class="px-4 py-3 font-mono font-bold text-indigo-700 text-xs">
              <span class="px-2.5 py-1 bg-indigo-50 border border-indigo-200 rounded-md tracking-wider">
                {{ $coupon->code }}
              </span>
            </td>
            <td class="px-4 py-3 font-medium text-gray-900">
              {{ $coupon->name ?? '—' }}
            </td>
            <td class="px-4 py-3">
              @if($coupon->discount_type === 'full')
                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 font-semibold text-[10px] rounded-full uppercase">100% Free</span>
              @elseif($coupon->discount_type === 'percentage')
                <span class="px-2 py-0.5 bg-blue-100 text-blue-800 font-semibold text-[10px] rounded-full uppercase">Percentage</span>
              @else
                <span class="px-2 py-0.5 bg-purple-100 text-purple-800 font-semibold text-[10px] rounded-full uppercase">Fixed Amount</span>
              @endif
            </td>
            <td class="px-4 py-3 font-semibold text-gray-900">
              @if($coupon->discount_type === 'full')
                100% OFF
              @elseif($coupon->discount_type === 'percentage')
                {{ (float)$coupon->discount_value }}% OFF
              @else
                ₹{{ number_format((float)$coupon->discount_value, 2) }} OFF
              @endif
            </td>
            <td class="px-4 py-3 text-gray-600">
              <span class="font-semibold text-gray-900">{{ $coupon->used_count }}</span>
              <span class="text-gray-400">/</span>
              <span>{{ $coupon->max_uses ?? '∞' }}</span>
            </td>
            <td class="px-4 py-3 text-gray-600">
              {{ $coupon->event?->title ?? 'All Events' }}
            </td>
            <td class="px-4 py-3">
              @if($coupon->is_active)
                <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-medium rounded-md">Active</span>
              @else
                <span class="px-2 py-0.5 bg-gray-100 text-gray-600 border border-gray-200 text-[11px] font-medium rounded-md">Inactive</span>
              @endif
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <button onclick='openEditModal(@json($coupon))' class="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition" title="Edit">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" action="{{ route('admin.event-coupons.destroy', $coupon->id) }}" onsubmit="return confirm('Are you sure you want to delete coupon {{ $coupon->code }}?');" class="inline">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="8" class="px-4 py-8 text-center text-gray-400 text-xs">
              <i class="bi bi-ticket-perforated text-3xl mb-2 block"></i>
              No coupon codes found.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="mt-4">
      {{ $coupons->links() }}
    </div>

</div>

<!-- Create / Edit Modal -->
<div id="couponModal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl border border-gray-200 shadow-xl max-w-lg w-full p-6 space-y-4 relative">
    <div class="flex justify-between items-center border-b border-gray-100 pb-3">
      <h3 id="modalTitle" class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
        <i class="bi bi-ticket-perforated text-indigo-600 text-base"></i> Create Coupon Code
      </h3>
      <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <form id="couponForm" method="POST" action="{{ route('admin.event-coupons.store') }}" class="space-y-4">
      @csrf
      <div id="methodField"></div>

      <!-- Code Option (Manual vs Auto Random) -->
      <div id="codeOptionWrapper" class="space-y-2">
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
        <label class="text-xs font-semibold text-gray-700 block mb-1">Coupon Code <span class="text-red-500">*</span></label>
        <input type="text" id="couponCodeInput" name="code" placeholder="e.g. VIPPASS60" class="w-full text-xs font-mono uppercase bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
      </div>

      <!-- Random Code Prefix -->
      <div id="randomCodeBox" class="hidden">
        <label class="text-xs font-semibold text-gray-700 block mb-1">Code Prefix (Optional)</label>
        <input type="text" name="code_prefix" placeholder="e.g. VIP" class="w-full text-xs font-mono uppercase bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
        <p class="text-[11px] text-gray-500 mt-1">Generates a random unique coupon code like <code>VIP-X89A2B</code>.</p>
      </div>

      <!-- Name & Description -->
      <div>
        <label class="text-xs font-semibold text-gray-700 block mb-1">Coupon Name</label>
        <input type="text" id="couponNameInput" name="name" placeholder="e.g. Summer Special 60% Off" class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
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
          <label class="text-xs font-semibold text-gray-700 block mb-1">Discount Value <span class="text-red-500">*</span></label>
          <input type="number" step="0.01" id="discountValueInput" name="discount_value" placeholder="e.g. 60" class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
        </div>
      </div>

      <!-- Max Uses & Event Scope -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-xs font-semibold text-gray-700 block mb-1">Max Uses (Usage Limit)</label>
          <input type="number" id="maxUsesInput" name="max_uses" placeholder="Unlimited if empty" class="w-full text-xs bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 focus:bg-white focus:ring-2 focus:ring-indigo-500 outline-none">
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

      <!-- Active Checkbox -->
      <div class="flex items-center gap-2 pt-1">
        <input type="checkbox" id="isActiveInput" name="is_active" value="1" checked class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500">
        <label for="isActiveInput" class="text-xs font-medium text-gray-700 cursor-pointer">Active and available for checkout</label>
      </div>

      <!-- Submit Buttons -->
      <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
        <button type="button" onclick="closeModal()" class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition">Cancel</button>
        <button type="submit" class="px-4 py-2 text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition shadow-2xs">Save Coupon</button>
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
  document.getElementById('couponCodeInput').required = true;
  document.getElementById('couponCodeInput').readOnly = false;
  document.getElementById('couponCodeInput').value = '';
  document.getElementById('couponNameInput').value = '';
  document.getElementById('discountTypeSelect').value = 'percentage';
  document.getElementById('discountValueInput').value = '';
  document.getElementById('maxUsesInput').value = '';
  document.getElementById('eventIdSelect').value = '';
  document.getElementById('isActiveInput').checked = true;
  toggleCodeMode(false);
  toggleDiscountValueField();
  document.getElementById('couponModal').classList.remove('hidden');
}

function openEditModal(coupon) {
  document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil text-indigo-600 text-base"></i> Edit Coupon Code (' + coupon.code + ')';
  document.getElementById('couponForm').action = "/admin/event-coupons/" + coupon.id;
  document.getElementById('methodField').innerHTML = '@method("PUT")';
  document.getElementById('codeOptionWrapper').classList.add('hidden');
  document.getElementById('manualCodeBox').classList.remove('hidden');
  document.getElementById('randomCodeBox').classList.add('hidden');
  document.getElementById('couponCodeInput').value = coupon.code;
  document.getElementById('couponCodeInput').readOnly = true;
  document.getElementById('couponCodeInput').required = false;
  document.getElementById('couponNameInput').value = coupon.name || '';
  document.getElementById('discountTypeSelect').value = coupon.discount_type;
  document.getElementById('discountValueInput').value = coupon.discount_value || '';
  document.getElementById('maxUsesInput').value = coupon.max_uses || '';
  document.getElementById('eventIdSelect').value = coupon.event_id || '';
  document.getElementById('isActiveInput').checked = !!coupon.is_active;
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
  if (type === 'full') {
    wrapper.classList.add('hidden');
    input.required = false;
  } else {
    wrapper.classList.remove('hidden');
    input.required = true;
  }
}
</script>
@endsection
