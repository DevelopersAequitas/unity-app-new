<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Store\Order;
use App\Models\Store\OrderStatusHistory;
use App\Models\Store\Shipment;
use App\Models\Store\ShipmentEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StoreWebhookController extends BaseApiController
{
    public function courierTracking(Request $request): JsonResponse
    {
        $providerEventId = $request->input('provider_event_id');
        $awb = $request->input('awb');
        $status = $request->input('status', 'IN_TRANSIT');
        $location = $request->input('location');
        $description = $request->input('description');

        if ($providerEventId) {
            $existing = ShipmentEvent::where('provider_event_id', $providerEventId)->first();
            if ($existing) {
                return $this->success(['duplicate' => true], 'Webhook already processed');
            }
        }

        $shipment = Shipment::where('tracking_no', $awb)->first();
        if ($shipment) {
            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'provider_event_id' => $providerEventId,
                'status' => $status,
                'location' => $location,
                'description' => $description,
                'event_at' => now(),
                'created_at' => now(),
            ]);

            $shipment->update(['status' => $status]);

            if ($status === 'DELIVERED') {
                $shipment->update(['delivered_at' => now()]);
                $order = Order::find($shipment->order_id);
                if ($order && $order->status !== 'DELIVERED') {
                    $order->update(['status' => 'DELIVERED']);
                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'from_status' => 'SHIPPED',
                        'to_status' => 'DELIVERED',
                        'reason' => 'Courier webhook delivered',
                        'created_at' => now(),
                    ]);
                }
            }
        }

        return $this->success(['processed' => true], 'Courier webhook processed');
    }

    public function whatsappStatus(Request $request): JsonResponse
    {
        Log::info('WhatsApp Store Webhook received', $request->all());

        return $this->success(['status' => 'acknowledged']);
    }

    public function emailStatus(Request $request): JsonResponse
    {
        Log::info('Email Store Webhook received', $request->all());

        return $this->success(['status' => 'acknowledged']);
    }

    public function smsStatus(Request $request): JsonResponse
    {
        Log::info('SMS Store Webhook received', $request->all());

        return $this->success(['status' => 'acknowledged']);
    }
}
