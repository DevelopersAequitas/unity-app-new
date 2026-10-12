<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Packing Slip — #{{ $order->order_number ?: $order->id }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1e293b;
            font-size: 12px;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header-logo {
            font-size: 20px;
            font-weight: bold;
            color: #0f172a;
        }
        .header-title {
            text-align: right;
        }
        .header-title h2 {
            margin: 0;
            color: #2563eb;
            font-size: 18px;
        }
        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: bold;
            text-align: left;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            font-size: 11px;
            text-transform: uppercase;
        }
        .items-table td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            font-size: 11px;
        }
        .coins-box {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 25px;
        }
        .coins-table {
            width: 100%;
            border-collapse: collapse;
        }
        .coins-table td {
            padding: 4px 6px;
            font-size: 11px;
        }
        .footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
            text-align: center;
            font-size: 10px;
            color: #64748b;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <table class="header-table" width="100%">
        <tr>
            <td width="60%">
                <div class="header-logo">PEERS STORE</div>
                <div style="color: #64748b; font-size: 11px;">Peers Global Community Merchandise &amp; Rewards</div>
                <div style="color: #64748b; font-size: 11px;">support@peersglobal.com</div>
            </td>
            <td width="40%" class="header-title">
                <h2>PACKING SLIP</h2>
                <div style="font-weight: bold; font-size: 12px; margin-top: 4px;">Order #{{ $order->order_number ?: $order->id }}</div>
                <div style="color: #64748b; font-size: 11px;">Date: {{ $order->created_at ? $order->created_at->format('d M Y, h:i A') : '—' }}</div>
                <div style="color: #2563eb; font-weight: bold; font-size: 11px; margin-top: 4px; text-transform: uppercase;">
                    Status: {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Deliver To & Shipping Address Box --}}
    <div class="info-box">
        <table width="100%">
            <tr>
                <td width="50%" valign="top">
                    <strong style="color: #64748b; text-transform: uppercase; font-size: 9px; letter-spacing: 0.5px;">Deliver To (Customer):</strong><br>
                    <strong style="font-size: 13px; color: #0f172a;">{{ $order->shipping_recipient_name ?: ($order->user->name ?? 'Peer Customer') }}</strong><br>
                    <span style="color: #475569; font-size: 11px; line-height: 1.5;">
                        Phone: {{ $order->shipping_phone ?: ($order->user->phone_number ?? '—') }}<br>
                        Email: {{ $order->user->email ?? '—' }}
                    </span>
                </td>
                <td width="50%" valign="top">
                    <strong style="color: #64748b; text-transform: uppercase; font-size: 9px; letter-spacing: 0.5px;">Shipping Address:</strong><br>
                    <span style="color: #1e293b; font-size: 11px; line-height: 1.4;">
                        @if($order->shipping_address_line1)
                            <strong>{{ $order->shipping_address_line1 }}</strong><br>
                            @if($order->shipping_address_line2)
                                {{ $order->shipping_address_line2 }}<br>
                            @endif
                            @if($order->shipping_landmark)
                                <span style="color: #64748b; font-size: 10px;">Landmark: {{ $order->shipping_landmark }}</span><br>
                            @endif
                            {{ $order->shipping_city }}{{ $order->shipping_state ? ', ' . $order->shipping_state : '' }} - <strong>{{ $order->shipping_pincode }}</strong>
                        @else
                            <em style="color: #64748b;">No physical address specified</em>
                        @endif
                    </span>
                </td>
            </tr>
        </table>
    </div>

    {{-- Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th width="8%" style="text-align: center;">#</th>
                <th width="47%">Item Description</th>
                <th width="20%">SKU</th>
                <th width="10%" style="text-align: center;">Qty</th>
                <th width="15%" style="text-align: right;">Total Coins</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $idx => $item)
                <tr>
                    <td align="center" style="color: #64748b;">{{ $idx + 1 }}</td>
                    <td>
                        <strong style="color: #0f172a;">{{ $item->product_name }}</strong>
                        @if($item->variant_title)
                            <div style="color: #64748b; font-size: 10px;">Variant: {{ $item->variant_title }}</div>
                        @endif
                    </td>
                    <td><code style="color: #0f172a;">{{ $item->sku }}</code></td>
                    <td align="center"><strong>{{ $item->quantity }}</strong></td>
                    <td align="right" style="color: #2563eb; font-weight: bold;">
                        {{ number_format($item->total_coins) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Coins Balance & Deduction Breakdown --}}
    <div class="coins-box">
        <table class="coins-table">
            <tr>
                <td colspan="2" style="font-weight: bold; color: #1e293b; text-transform: uppercase; font-size: 11px; border-bottom: 1px solid #cbd5e1; padding-bottom: 6px;">
                    COINS PAYMENT &amp; BALANCE BREAKDOWN
                </td>
            </tr>
            <tr>
                <td width="70%" style="padding-top: 6px; color: #475569;">Earned Coins Deducted:</td>
                <td width="30%" align="right" style="padding-top: 6px; font-weight: bold; color: #059669;">
                    {{ number_format($order->coins_from_earned ?: $order->total_coins) }} Coins
                </td>
            </tr>
            <tr>
                <td style="color: #475569;">Bonus Coins Deducted:</td>
                <td align="right" style="font-weight: bold; color: #0284c7;">
                    {{ number_format($order->coins_from_bonus ?: 0) }} Coins
                </td>
            </tr>
            <tr style="border-top: 1px solid #cbd5e1;">
                <td style="font-weight: bold; color: #0f172a; padding-top: 4px;">Total Order Coins Paid:</td>
                <td align="right" style="font-weight: bold; color: #2563eb; font-size: 13px; padding-top: 4px;">
                    {{ number_format($order->total_coins) }} Coins
                </td>
            </tr>
            @php
                $remainingCoins = $order->user ? (int) $order->user->coins_balance : 0;
            @endphp
            <tr style="border-top: 1px dashed #cbd5e1;">
                <td style="padding-top: 6px; color: #475569;">Member Remaining Coins Balance:</td>
                <td align="right" style="padding-top: 6px; font-weight: bold; color: #0f172a; font-size: 12px;">
                    {{ number_format($remainingCoins) }} Coins
                </td>
            </tr>
        </table>
    </div>

    {{-- Footer Note --}}
    <div class="footer">
        <p style="margin: 0 0 4px 0;">Thank you for being a valued member of the Peers Global Community!</p>
        <p style="margin: 0;">This is an official packing slip generated for order fulfilment and dispatch verification.</p>
    </div>
</body>
</html>
