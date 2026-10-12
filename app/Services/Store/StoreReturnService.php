<?php

namespace App\Services\Store;

use App\Constants\StoreErrorCodes;
use App\Models\Store\NotificationEvent;
use App\Models\Store\Order;
use App\Models\Store\ReturnPhoto;
use App\Models\Store\StoreConfig;
use App\Models\Store\StoreReturn;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreReturnService
{
    protected StoreRefundService $refundService;

    public function __construct(StoreRefundService $refundService)
    {
        $this->refundService = $refundService;
    }

    public function createReturnRequest(User $user, string $orderId, array $data): StoreReturn
    {
        $order = Order::with('items.product')->where('id', $orderId)->where('user_id', $user->id)->first();
        if (! $order) {
            throw new Exception(StoreErrorCodes::ORDER_NOT_FOUND, 404);
        }

        if (! in_array($order->status, ['DELIVERED', 'CONFIRMED', 'PLACED', 'PROCESSING', 'READY_FOR_PICKUP'], true)) {
            throw new Exception(StoreErrorCodes::RETURN_NOT_ALLOWED, 422);
        }

        $returnWindowDays = (int) StoreConfig::getValue('return_window_days', 7);
        if ($order->updated_at->addDays($returnWindowDays) < now()) {
            throw new Exception(StoreErrorCodes::RETURN_WINDOW_EXPIRED, 422);
        }

        // Guard against duplicate active return submissions for the same order
        $existingActiveReturn = StoreReturn::where('order_id', $order->id)
            ->whereNotIn('status', ['CANCELLED', 'REJECTED'])
            ->first();

        if ($existingActiveReturn) {
            throw new Exception('A return request has already been submitted for this order.', 422);
        }

        // Validate products in return
        foreach ($order->items as $item) {
            if ($item->product && ! $item->product->return_allowed) {
                throw new Exception(StoreErrorCodes::RETURN_NOT_ALLOWED, 422);
            }
            if ($item->product && $item->product->customised) {
                throw new Exception(StoreErrorCodes::CUSTOMIZED_PRODUCT_NON_RETURNABLE, 422);
            }
        }

        $returnNo = 'RET-'.strtoupper(Str::random(10));

        return DB::transaction(function () use ($user, $order, $returnNo, $data) {
            $return = StoreReturn::create([
                'return_no' => $returnNo,
                'order_id' => $order->id,
                'user_id' => $user->id,
                'reason_code' => $data['reason'] ?? 'DAMAGED',
                'reason_detail' => $data['description'] ?? null,
                'status' => 'REQUESTED',
                'created_at' => now(),
            ]);

            if (! empty($data['photos']) && is_array($data['photos'])) {
                foreach ($data['photos'] as $photoUrl) {
                    ReturnPhoto::create([
                        'return_id' => $return->id,
                        'file_url' => $photoUrl,
                        'file_type' => 'IMAGE',
                        'created_at' => now(),
                    ]);
                }
            }

            NotificationEvent::create([
                'event_key' => 'return.requested',
                'user_id' => $user->id,
                'reference_type' => 'RETURN',
                'reference_id' => $return->id,
                'payload' => ['return_no' => $returnNo, 'order_no' => $order->order_no],
                'status' => 'PENDING',
                'available_at' => now(),
                'created_at' => now(),
            ]);

            return $return->load(['photos', 'order']);
        });
    }

    public function getUserReturns(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return StoreReturn::with(['order', 'photos', 'refund'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getReturnDetails(User $user, string $returnId): StoreReturn
    {
        $return = StoreReturn::with(['order.items', 'photos', 'refund'])
            ->where('id', $returnId)
            ->where('user_id', $user->id)
            ->first();

        if (! $return) {
            throw new Exception(StoreErrorCodes::ORDER_NOT_FOUND, 404);
        }

        return $return;
    }
}
