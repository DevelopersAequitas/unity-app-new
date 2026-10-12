<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packing Slip — #{{ $order->order_number ?: $order->id }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #1e293b; background: #f8fafc; }
        .slip-container { max-width: 840px; margin: 30px auto; padding: 40px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .badge-delivered { background-color: #10b981; color: #ffffff; }
        .balance-card { background-color: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; }
        @media print {
            body { background: #ffffff; }
            .no-print { display: none !important; }
            .slip-container { border: none; margin: 0; padding: 0; max-width: 100%; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container my-3 no-print text-end" style="max-width: 840px;">
        @if($order->slip_url)
            <a href="{{ $order->slip_url }}" download class="btn btn-outline-primary btn-sm me-2">
                <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
            </a>
        @endif
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="bi bi-printer me-1"></i> Print Packing Slip</button>
        <button onclick="window.close()" class="btn btn-outline-secondary btn-sm ms-2">Close Window</button>
    </div>

    <div class="slip-container">
        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">PEERS STORE</h3>
                <div class="text-muted small">Peers Global Community Merchandise &amp; Rewards</div>
                <div class="text-muted small">support@peersglobal.com</div>
            </div>
            <div class="text-end">
                <h4 class="fw-bold text-primary mb-1">PACKING SLIP</h4>
                <div class="fw-semibold text-dark">Order #{{ $order->order_number ?: $order->id }}</div>
                <div class="text-muted small">Date: {{ $order->created_at ? $order->created_at->format('d M Y, h:i A') : '—' }}</div>
                <div class="mt-1">
                    <span class="badge bg-secondary text-uppercase px-2.5 py-1" style="font-size: 11px;">
                        Status: {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Shipping / Delivery Info (Full Width, Balanced Two-Column Layout) --}}
        <div class="card bg-light border-0 mb-4">
            <div class="card-body p-4">
                <div class="row g-4">
                    {{-- Recipient / Customer --}}
                    <div class="col-md-6">
                        <div class="text-uppercase small fw-bold text-muted mb-2">
                            <i class="bi bi-person-circle text-primary me-1"></i> Deliver To (Customer):
                        </div>
                        <div class="fw-bold text-dark fs-6 mb-1">{{ $order->shipping_recipient_name ?: ($order->user->name ?? 'Peer Customer') }}</div>
                        <div class="small text-muted lh-base">
                            <div><i class="bi bi-telephone text-secondary me-1"></i> Phone: <strong>{{ $order->shipping_phone ?: ($order->user->phone_number ?? '—') }}</strong></div>
                            <div><i class="bi bi-envelope text-secondary me-1"></i> Email: {{ $order->user->email ?? '—' }}</div>
                        </div>
                    </div>

                    {{-- Shipping Address --}}
                    <div class="col-md-6 border-start-md">
                        <div class="text-uppercase small fw-bold text-muted mb-2">
                            <i class="bi bi-geo-alt-fill text-danger me-1"></i> Shipping Address:
                        </div>
                        <div class="small text-dark lh-base">
                            @if($order->shipping_address_line1)
                                <div class="fw-semibold">{{ $order->shipping_address_line1 }}</div>
                                @if($order->shipping_address_line2)
                                    <div>{{ $order->shipping_address_line2 }}</div>
                                @endif
                                @if($order->shipping_landmark)
                                    <div class="text-muted"><small>Landmark: {{ $order->shipping_landmark }}</small></div>
                                @endif
                                <div>{{ $order->shipping_city }}{{ $order->shipping_state ? ', ' . $order->shipping_state : '' }} - <strong>{{ $order->shipping_pincode }}</strong></div>
                            @else
                                <span class="text-muted fst-italic">No physical address specified (Store Pickup / Digital)</span>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- Items Table --}}
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 8%; text-align: center;">#</th>
                        <th style="width: 47%;">Item Description</th>
                        <th style="width: 20%;">SKU</th>
                        <th class="text-center" style="width: 10%;">Qty</th>
                        <th class="text-end" style="width: 15%;">Total Coins</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $idx => $item)
                        <tr>
                            <td class="text-center text-muted">{{ $idx + 1 }}</td>
                            <td>
                                <strong class="text-dark d-block">{{ $item->product_name }}</strong>
                                @if($item->variant_title)
                                    <small class="text-muted">Variant: {{ $item->variant_title }}</small>
                                @endif
                            </td>
                            <td><code class="text-dark bg-light px-1 py-0.5 rounded">{{ $item->sku }}</code></td>
                            <td class="text-center fw-bold">{{ $item->quantity }}</td>
                            <td class="text-end fw-semibold text-primary">
                                {{ number_format($item->total_coins) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Coins Balance & Deduction Breakdown Section --}}
        <div class="balance-card p-3 mb-4">
            <h6 class="fw-bold text-dark mb-3 text-uppercase small border-bottom pb-2">
                <i class="bi bi-coin text-warning me-1"></i> Coins Balance &amp; Payment Summary
            </h6>
            <div class="row g-3">
                <div class="col-sm-4">
                    <div class="bg-white p-2.5 rounded border">
                        <span class="text-muted small d-block">Earned Coins Used</span>
                        <strong class="text-success fs-6">{{ number_format($order->coins_from_earned ?: $order->total_coins) }} Coins</strong>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="bg-white p-2.5 rounded border">
                        <span class="text-muted small d-block">Bonus Coins Used</span>
                        <strong class="text-info fs-6">{{ number_format($order->coins_from_bonus ?: 0) }} Coins</strong>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="bg-white p-2.5 rounded border">
                        <span class="text-muted small d-block">Total Coins Deducted</span>
                        <strong class="text-primary fs-6">{{ number_format($order->total_coins) }} Coins</strong>
                    </div>
                </div>
            </div>

            @php
                $remainingCoins = $order->user ? (int) $order->user->coins_balance : 0;
            @endphp
            <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                <span class="text-muted small">
                    <i class="bi bi-wallet2 text-primary me-1"></i> Member Current Coins Balance:
                </span>
                <span class="fw-bold fs-6 text-dark">
                    {{ number_format($remainingCoins) }} Coins
                </span>
            </div>
        </div>

        {{-- Footer Note --}}
        <div class="border-top pt-3 text-muted small text-center">
            <p class="mb-1">Thank you for being a valued member of the Peers Global Community!</p>
            <p class="mb-0">This is an official packing slip generated for order fulfilment and dispatch verification.</p>
        </div>
    </div>
</body>
</html>
