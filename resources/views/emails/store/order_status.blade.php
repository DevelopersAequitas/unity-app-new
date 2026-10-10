@extends('emails.layouts.email')

@section('title', 'Order Update #' . ($order->order_no ?: $order->id))

@section('content')
@php
    $statusKey = strtolower($status);
    $statusTitle = match ($statusKey) {
        'processing' => 'Order Processing',
        'shipped' => 'Order Shipped',
        'out_for_delivery', 'ready_for_pickup' => 'Out for Delivery',
        'delivered' => 'Order Delivered',
        'cancelled' => 'Order Cancelled & Refunded',
        default => ucfirst(str_replace('_', ' ', $status)),
    };

    $badgeColor = match ($statusKey) {
        'delivered' => '#10b981',
        'shipped', 'out_for_delivery', 'ready_for_pickup' => '#3b82f6',
        'cancelled' => '#ef4444',
        default => '#f59e0b',
    };

    $deliveryPerson = $order->delivery_person_name ?: $order->courier_name;
    $deliveryPhone = $order->delivery_person_phone ?: $order->tracking_number;
@endphp

<div style="text-align: center; margin-bottom: 24px;">
    <span style="display: inline-block; padding: 6px 16px; border-radius: 20px; background-color: {{ $badgeColor }}; color: #ffffff; font-weight: bold; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
        {{ $statusTitle }}
    </span>
    <h2 style="color: #ffffff; margin-top: 14px; margin-bottom: 6px; font-size: 22px;">
        Hello, {{ $order->user->name ?? 'Valued Peer' }}!
    </h2>
    <p style="color: #94a3b8; font-size: 14px; margin: 0;">
        Order #<strong style="color: #f8fafc;">{{ $order->order_no ?: $order->id }}</strong>
    </p>
</div>

{{-- Status Message --}}
<div style="background-color: #1e293b; border-radius: 8px; padding: 18px 20px; margin-bottom: 24px; border-left: 4px solid {{ $badgeColor }};">
    @if($statusKey === 'processing')
        <p style="margin: 0; color: #e2e8f0; font-size: 14px;">
            Your order has been confirmed and is currently being packed and processed by our store fulfillment team.
        </p>
    @elseif($statusKey === 'shipped')
        <p style="margin: 0; color: #e2e8f0; font-size: 14px;">
            Great news! Your package has been dispatched from our warehouse and is on its way to your destination.
        </p>
        @if($order->courier_name || $order->tracking_number)
            <div style="margin-top: 10px; font-size: 13px; color: #cbd5e1;">
                <strong>Courier:</strong> {{ $order->courier_name ?: 'Standard Courier' }} | 
                <strong>Tracking / AWB:</strong> {{ $order->tracking_number ?: 'N/A' }}
            </div>
        @endif
    @elseif($statusKey === 'out_for_delivery' || $statusKey === 'ready_for_pickup')
        <p style="margin: 0; color: #e2e8f0; font-size: 14px;">
            Your order is <strong>Out for Delivery</strong> today! Our delivery representative is heading your way.
        </p>
        @if($deliveryPerson || $deliveryPhone)
            <div style="margin-top: 12px; background: rgba(59, 130, 246, 0.15); border: 1px dashed #3b82f6; border-radius: 6px; padding: 12px; font-size: 13px; color: #93c5fd;">
                <div><strong>Delivery Executive:</strong> {{ $deliveryPerson ?: 'Assigned Delivery Partner' }}</div>
                @if($deliveryPhone)
                    <div><strong>Contact Mobile Number:</strong> {{ $deliveryPhone }}</div>
                    <div style="margin-top: 4px; font-size: 12px; color: #bfdbfe;">
                        You can contact <strong>{{ $deliveryPerson }}</strong> at <strong>{{ $deliveryPhone }}</strong> for any delivery coordination.
                    </div>
                @endif
            </div>
        @endif
    @elseif($statusKey === 'delivered')
        <p style="margin: 0; color: #e2e8f0; font-size: 14px;">
            Your order has been <strong>successfully delivered</strong>! Thank you for being a part of the Peers Global Community.
        </p>
        @if($slipUrl || $order->slip_url)
            <div style="margin-top: 14px; text-align: center;">
                <a href="{{ $slipUrl ?: $order->slip_url }}" target="_blank" style="display: inline-block; background-color: #3b82f6; color: #ffffff; padding: 10px 22px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 13px;">
                    Download Official Packing Slip (PDF)
                </a>
            </div>
        @endif
    @elseif($statusKey === 'cancelled')
        <p style="margin: 0; color: #e2e8f0; font-size: 14px;">
            Your order #{{ $order->order_no ?: $order->id }} has been cancelled. 
            All deducted coins (<strong>{{ number_format($order->total_coins) }} Coins</strong>) have been refunded back to your wallet.
        </p>
    @else
        <p style="margin: 0; color: #e2e8f0; font-size: 14px;">
            The status of your order has been updated to: <strong>{{ ucfirst(str_replace('_', ' ', $status)) }}</strong>.
        </p>
    @endif
</div>

{{-- Ordered Items Summary --}}
<div style="margin-bottom: 24px;">
    <h3 style="color: #ffffff; font-size: 15px; margin-bottom: 12px; border-bottom: 1px solid #334155; padding-bottom: 8px;">
        Ordered Items
    </h3>
    <table width="100%" cellpadding="6" cellspacing="0" style="font-size: 13px; color: #cbd5e1; border-collapse: collapse;">
        <thead>
            <tr style="background-color: #1e293b; color: #94a3b8; text-align: left;">
                <th style="padding: 8px;">Item Description</th>
                <th style="padding: 8px; text-align: center;">Qty</th>
                <th style="padding: 8px; text-align: right;">Coins</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr style="border-bottom: 1px solid #1e293b;">
                    <td style="padding: 8px;">
                        <strong style="color: #ffffff;">{{ $item->product_name }}</strong>
                        @if($item->sku)
                            <div style="font-size: 11px; color: #64748b;">SKU: {{ $item->sku }}</div>
                        @endif
                    </td>
                    <td style="padding: 8px; text-align: center;">{{ $item->quantity }}</td>
                    <td style="padding: 8px; text-align: right; color: #38bdf8; font-weight: bold;">
                        {{ number_format($item->total_coins) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" style="padding: 10px 8px; text-align: right; font-weight: bold; color: #94a3b8;">
                    Total Coins Deducted:
                </td>
                <td style="padding: 10px 8px; text-align: right; font-weight: bold; color: #10b981; font-size: 14px;">
                    {{ number_format($order->total_coins) }} Coins
                </td>
            </tr>
        </tfoot>
    </table>
</div>

{{-- Shipping Destination --}}
<div style="background-color: #1e293b; border-radius: 8px; padding: 14px 16px; font-size: 12px; color: #94a3b8;">
    <strong style="color: #e2e8f0; display: block; margin-bottom: 4px;">Delivery Destination:</strong>
    {{ $order->shipping_address_line1 ?: ($order->shipping_address['address_line1'] ?? '') }}
    @if($order->shipping_city || ($order->shipping_address['city'] ?? false))
        , {{ $order->shipping_city ?: ($order->shipping_address['city'] ?? '') }}
    @endif
    @if($order->shipping_pincode || ($order->shipping_address['pincode'] ?? false))
        - {{ $order->shipping_pincode ?: ($order->shipping_address['pincode'] ?? '') }}
    @endif
</div>
@endsection

@section('footer')
    <p style="margin: 0; font-size: 12px; color: #cbd5e1;">
        Need help with your order? Reach out to us at 
        <a href="mailto:support@peersglobal.com" style="color: #93c5fd; text-decoration: underline;">support@peersglobal.com</a>.
    </p>
    <p style="margin: 6px 0 0; font-size: 11px; color: #94a3b8;">
        © {{ date('Y') }} Peers Global Unity. All rights reserved.
    </p>
@endsection
