<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packing Slip — #{{ $order->order_number ?: $order->id }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #1e293b; background: #fff; }
        .slip-container { max-width: 800px; margin: 20px auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 8px; }
        @media print {
            .no-print { display: none !important; }
            .slip-container { border: none; margin: 0; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container my-3 no-print text-end">
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer me-1"></i> Print Packing Slip</button>
        <button onclick="window.close()" class="btn btn-outline-secondary btn-sm ms-2">Close Window</button>
    </div>

    <div class="slip-container">
        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">PEERS STORE</h3>
                <div class="text-muted small">Peers Global Community Merchandise & Rewards</div>
                <div class="text-muted small">support@peersglobal.com</div>
            </div>
            <div class="text-end">
                <h4 class="fw-bold text-primary mb-1">PACKING SLIP</h4>
                <div class="fw-semibold text-dark">Order #{{ $order->order_number ?: $order->id }}</div>
                <div class="text-muted small">Date: {{ $order->created_at ? $order->created_at->format('d M Y, h:i A') : '—' }}</div>
            </div>
        </div>

        {{-- Shipping / Delivery Info --}}
        <div class="row g-4 mb-4">
            <div class="col-6">
                <div class="p-3 bg-light rounded border h-100">
                    <h6 class="fw-bold text-dark mb-2 text-uppercase small">Deliver To:</h6>
                    <div class="fw-bold">{{ $order->user->name ?? 'Peer Customer' }}</div>
                    <div class="small text-muted">
                        Phone: {{ $order->shipping_phone ?: ($order->user->phone_number ?? '—') }}<br>
                        Email: {{ $order->user->email ?? '—' }}
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="p-3 bg-light rounded border h-100">
                    <h6 class="fw-bold text-dark mb-2 text-uppercase small">Delivery Method & Destination:</h6>
                    <div class="fw-bold">
                        @if($order->delivery_method === 'pickup')
                            <i class="bi bi-building me-1 text-primary"></i> Central Hub Pickup
                        @else
                            <i class="bi bi-truck me-1 text-primary"></i> Doorstep Courier Delivery
                        @endif
                    </div>
                    <div class="small text-muted">
                        {{ $order->shipping_address_line1 }}<br>
                        @if($order->shipping_address_line2) {{ $order->shipping_address_line2 }}<br> @endif
                        {{ $order->shipping_city }}, {{ $order->shipping_state }} - {{ $order->shipping_pincode }}
                    </div>
                    @if($order->tracking_number)
                        <div class="small fw-semibold mt-1">Courier: {{ $order->courier_name }} (AWB: {{ $order->tracking_number }})</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Items Table --}}
        <table class="table table-bordered mb-4">
            <thead class="table-light">
                <tr>
                    <th style="width: 10%;">#</th>
                    <th style="width: 55%;">Item Description</th>
                    <th style="width: 20%;">SKU</th>
                    <th class="text-center" style="width: 15%;">Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $idx => $item)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>
                            <strong>{{ $item->product_name }}</strong>
                            @if($item->variant_title)
                                <div class="small text-muted">Variant: {{ $item->variant_title }}</div>
                            @endif
                        </td>
                        <td><code>{{ $item->sku }}</code></td>
                        <td class="text-center fw-bold">{{ $item->quantity }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Footer Note --}}
        <div class="border-top pt-3 text-muted small text-center">
            <p class="mb-1">Thank you for being a valued member of the Peers Global Community!</p>
            <p class="mb-0">This is an official packing slip generated for order fulfilment and dispatch verification.</p>
        </div>
    </div>
</body>
</html>
