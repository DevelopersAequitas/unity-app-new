@extends('admin.layouts.app')

@section('title', 'Peers Store — Order #' . ($order->order_number ?: $order->id))

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.orders.index') }}" class="text-decoration-none text-muted">Orders</a></li>
                    <li class="breadcrumb-item active" aria-current="page">#{{ $order->order_number ?: $order->id }}</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-bag-check-fill text-primary"></i> Order #{{ $order->order_number ?: $order->id }}
            </h1>
        </div>
        <div class="d-flex gap-2">
            @if($order->slip_url)
                <a href="{{ $order->slip_url }}" target="_blank" download class="btn btn-outline-primary d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-pdf"></i> Download Slip (PDF)
                </a>
            @endif
            <a href="{{ route('admin.store.orders.packing-slip', $order->id) }}" target="_blank" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-printer"></i> Print Packing Slip
            </a>
            <a href="{{ route('admin.store.orders.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> All Orders
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm" role="alert">
            <i class="bi bi-x-circle-fill fs-5"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- Left Column: Items & Timeline --}}
        <div class="col-lg-8">
            {{-- Order Items Card --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-cart3 text-primary"></i> Ordered Items ({{ $order->items->count() }})
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4" style="width: 50%;">Product / SKU</th>
                                    <th style="width: 15%;">Unit Coins</th>
                                    <th style="width: 15%;">Qty</th>
                                    <th class="pe-4 text-end" style="width: 20%;">Total Coins</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->items as $item)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="rounded bg-light border d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; overflow: hidden; flex-shrink: 0;">
                                                    @if($item->product->primary_image_url ?? false)
                                                        <img src="{{ $item->product->primary_image_url }}" alt="{{ $item->product_name }}" style="width: 100%; height: 100%; object-fit: cover;">
                                                    @else
                                                        <i class="bi bi-box-seam text-muted"></i>
                                                    @endif
                                                </div>
                                                <div>
                                                    <span class="fw-bold text-dark">{{ $item->product_name }}</span>
                                                    @if($item->variant_title)
                                                        <div class="text-muted small">Variant: {{ $item->variant_title }}</div>
                                                    @endif
                                                    <small class="text-muted">SKU: <code>{{ $item->sku }}</code></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="fw-semibold text-dark">{{ number_format($item->unit_coins) }}</span></td>
                                        <td><span class="badge bg-light text-dark border px-2 py-1 fs-6">{{ $item->quantity }}</span></td>
                                        <td class="pe-4 text-end">
                                            <span class="fw-bold text-primary fs-6">{{ number_format($item->total_coins) }}</span>
                                            <small class="text-muted">Coins</small>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="3" class="ps-4 fw-bold text-end">Subtotal:</td>
                                    <td class="pe-4 text-end fw-bold text-dark">{{ number_format($order->subtotal_coins ?? $order->total_coins) }} Coins</td>
                                </tr>
                                @if(($order->delivery_coins ?? 0) > 0)
                                    <tr>
                                        <td colspan="3" class="ps-4 text-end text-muted">Delivery Charges:</td>
                                        <td class="pe-4 text-end text-muted">{{ number_format($order->delivery_coins) }} Coins</td>
                                    </tr>
                                @endif
                                <tr class="border-top border-2">
                                    <td colspan="3" class="ps-4 fw-bold text-end fs-5">Grand Total Paid:</td>
                                    <td class="pe-4 text-end fw-bold text-primary fs-5">{{ number_format($order->total_coins) }} Coins</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Coin Deduction Split Summary --}}
            <div class="card shadow-sm border-0 mb-4 bg-light">
                <div class="card-body">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-pie-chart text-primary me-2"></i>Payment Deduction Breakdown</h6>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="p-3 bg-white rounded border">
                                <span class="text-muted small">Earned Coins Deducted:</span>
                                <div class="fs-5 fw-bold text-success">{{ number_format($order->earned_coins_used ?? $order->total_coins) }} Coins</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-white rounded border">
                                <span class="text-muted small">Bonus Coins Deducted:</span>
                                <div class="fs-5 fw-bold text-info">{{ number_format($order->bonus_coins_used ?? 0) }} Coins</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Order History / Status Timeline --}}
            @if($order->statusHistory && $order->statusHistory->isNotEmpty())
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-clock-history text-primary"></i> Lifecycle Status Timeline
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            @foreach($order->statusHistory as $hist)
                                <li class="list-group-item d-flex justify-content-between align-items-start px-0">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold text-dark">{{ ucfirst(str_replace('_', ' ', $hist->status)) }}</div>
                                        <span class="text-muted small">{{ $hist->notes ?: 'Status updated' }}</span>
                                    </div>
                                    <span class="text-muted small">{{ $hist->created_at->format('d M Y, h:i A') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>

        {{-- Right Column: Fulfilment, Actions & Customer --}}
        <div class="col-lg-4">
            {{-- Status & Workflow Action Card --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title fw-bold text-dark mb-0">Order Status & Actions</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 text-center p-3 bg-light rounded">
                        <span class="text-muted small d-block mb-1">Current State</span>
                        @php
                            $stLower = strtolower($order->status);
                            $badgeColor = match($stLower) {
                                'cancelled', 'refunded' => 'bg-danger',
                                'delivered', 'picked_up', 'completed' => 'bg-success',
                                'shipped', 'dispatched' => 'bg-info text-dark',
                                'ready_for_pickup' => 'bg-warning text-dark',
                                default => 'bg-primary'
                            };
                        @endphp
                        <span class="badge {{ $badgeColor }} fs-6 px-3 py-1.5">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                    </div>

                    {{-- Status Transition Form --}}
                    @php
                        $statuses = $allStatuses ?? [
                            'processing' => 'Processing',
                            'shipped' => 'Shipped',
                            'out_for_delivery' => 'Out for Delivery',
                            'delivered' => 'Delivered',
                        ];
                    @endphp
                    <form method="POST" action="{{ route('admin.store.orders.status', $order->id) }}" id="updateStatusForm" class="mb-3">
                        @csrf
                        <label class="form-label fw-semibold small text-muted">Update Order Status</label>
                        <div class="mb-3">
                            <select name="status" id="orderStatusSelect" class="form-select" required>
                                @foreach($statuses as $statusKey => $statusLabel)
                                    <option value="{{ $statusKey }}" {{ ($stLower === $statusKey || ($statusKey === 'out_for_delivery' && $stLower === 'ready_for_pickup')) ? 'selected' : '' }}>
                                        {{ $statusLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Dynamic Out for Delivery / Courier Information (Hidden for other statuses) --}}
                        <div id="outForDeliveryFields" class="p-3 mb-3 bg-light rounded border" style="display: none;">
                            <h6 class="fw-bold text-dark small mb-2">
                                <i class="bi bi-truck text-primary me-1"></i> Delivery Partner &amp; Contact Details <span class="text-danger">*</span>
                            </h6>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold text-muted mb-1">Courier / Delivery Person Name <span class="text-danger">*</span></label>
                                <input type="text" name="delivery_person_name" id="delivery_person_name" class="form-control form-control-sm" placeholder="e.g. BlueDart / Rahul Sharma" value="{{ $order->delivery_person_name ?: $order->courier_name }}">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold text-muted mb-1">AWB / Contact Mobile Number <span class="text-danger">*</span></label>
                                <input type="text" name="delivery_person_phone" id="delivery_person_phone" class="form-control form-control-sm" placeholder="e.g. 9876543210 / AWB12345678" value="{{ $order->delivery_person_phone ?: $order->tracking_number }}">
                            </div>
                            <small class="text-muted d-block" style="font-size: 11px;">
                                <i class="bi bi-info-circle me-1"></i> Both fields are mandatory before advancing to Out for Delivery. They will be saved to database, visible in member API, and sent via SMS/Email.
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted">Transition Notes (Optional)</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Optional notes for history timeline..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-check2-circle"></i> Update Status
                        </button>
                    </form>

                    {{-- Pickup PIN Verification (if Hub Pickup) --}}
                    @if($order->delivery_method === 'pickup' && $stLower === 'ready_for_pickup')
                        <div class="border-top pt-3 mt-3">
                            <h6 class="fw-bold text-dark small mb-2"><i class="bi bi-shield-lock text-primary me-1"></i> Verify Customer Pickup PIN</h6>
                            <form method="POST" action="{{ route('admin.store.orders.pickup.verify-pin', $order->id) }}">
                                @csrf
                                <div class="input-group">
                                    <input type="text" name="pickup_pin" class="form-control form-control-sm" placeholder="4 or 6-digit PIN" required>
                                    <button type="submit" class="btn btn-sm btn-success">Verify & Deliver</button>
                                </div>
                            </form>
                        </div>
                    @endif

                    {{-- Cancel & Refund Button --}}
                    @if(!in_array($stLower, ['delivered', 'cancelled', 'refunded', 'completed', 'picked_up']))
                        <div class="border-top pt-3 mt-3">
                            <button class="btn btn-sm btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                                <i class="bi bi-x-circle"></i> Cancel Order & Refund Coins
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Customer & Shipping Address Snapshot --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title fw-bold text-dark mb-0"><i class="bi bi-person text-primary me-1"></i> Customer & Address</h5>
                </div>
                <div class="card-body">
                    <div class="fw-bold text-dark mb-1">{{ $order->user->name ?? 'Guest/Peer' }}</div>
                    <div class="text-muted small mb-3">
                        <i class="bi bi-telephone me-1"></i> {{ $order->shipping_phone ?: ($order->user->phone_number ?? '—') }}<br>
                        <i class="bi bi-envelope me-1"></i> {{ $order->user->email ?? '—' }}
                    </div>

                    <div class="border-top pt-3">
                        <h6 class="fw-bold text-dark small mb-1">
                            @if($order->delivery_method === 'pickup')
                                <i class="bi bi-building text-primary me-1"></i> Pickup Hub
                            @else
                                <i class="bi bi-geo-alt text-danger me-1"></i> Shipping Address Snapshot
                            @endif
                        </h6>
                        <p class="text-muted small mb-0">
                            {{ $order->shipping_address_line1 }}<br>
                            @if($order->shipping_address_line2) {{ $order->shipping_address_line2 }}<br> @endif
                            {{ $order->shipping_city }}, {{ $order->shipping_state }} - <strong>{{ $order->shipping_pincode }}</strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Cancel Order Modal --}}
<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.store.orders.cancel', $order->id) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="bi bi-x-circle text-danger"></i> Cancel Order #{{ $order->order_number ?: $order->id }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Cancelling this order will immediately restock the reserved inventory items and fully refund <strong>{{ number_format($order->total_coins) }} Coins</strong> back into the customer's wallet according to the original Earned/Bonus split.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Cancellation Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Customer request, address undeliverable, stock discrepancy..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Order</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg"></i> Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusSelect = document.getElementById('orderStatusSelect');
    const deliveryFields = document.getElementById('outForDeliveryFields');
    const personNameInput = document.getElementById('delivery_person_name');
    const personPhoneInput = document.getElementById('delivery_person_phone');
    const statusForm = document.getElementById('updateStatusForm');

    function toggleDeliveryFields() {
        if (!statusSelect || !deliveryFields) return;
        const selected = statusSelect.value;
        if (selected === 'out_for_delivery') {
            deliveryFields.style.display = 'block';
            if (personNameInput) personNameInput.setAttribute('required', 'required');
            if (personPhoneInput) personPhoneInput.setAttribute('required', 'required');
        } else {
            deliveryFields.style.display = 'none';
            if (personNameInput) personNameInput.removeAttribute('required');
            if (personPhoneInput) personPhoneInput.removeAttribute('required');
        }
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', toggleDeliveryFields);
        toggleDeliveryFields();
    }

    if (statusForm) {
        statusForm.addEventListener('submit', function (e) {
            if (statusSelect && statusSelect.value === 'out_for_delivery') {
                const nameVal = personNameInput ? personNameInput.value.trim() : '';
                const phoneVal = personPhoneInput ? personPhoneInput.value.trim() : '';
                if (!nameVal || !phoneVal) {
                    e.preventDefault();
                    alert('Please enter both Delivery Person/Courier Name and Contact Number/AWB before advancing to Out for Delivery.');
                    if (!nameVal && personNameInput) personNameInput.focus();
                    else if (personPhoneInput) personPhoneInput.focus();
                    return false;
                }
            }
        });
    }
});
</script>
@endsection
