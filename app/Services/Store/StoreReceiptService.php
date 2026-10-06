<?php

namespace App\Services\Store;

use App\Constants\StoreErrorCodes;
use App\Models\Store\Order;
use App\Models\Store\Receipt;
use App\Models\User;
use Exception;

class StoreReceiptService
{
    public function getReceiptByOrderId(User $user, string $orderId): Receipt
    {
        $order = Order::where('id', $orderId)->where('user_id', $user->id)->first();
        if (! $order) {
            throw new Exception(StoreErrorCodes::ORDER_NOT_FOUND, 404);
        }

        $receipt = Receipt::where('order_id', $orderId)->first();
        if (! $receipt) {
            throw new Exception(StoreErrorCodes::ORDER_NOT_FOUND, 404);
        }

        return $receipt;
    }
}
