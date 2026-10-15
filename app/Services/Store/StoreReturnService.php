<?php

namespace App\Services\Store;

use App\Constants\StoreErrorCodes;
use App\Models\Store\NotificationEvent;
use App\Models\Store\Order;
use App\Models\Store\OrderItem;
use App\Models\Store\ReturnPhoto;
use App\Models\Store\StoreConfig;
use App\Models\Store\StoreReturn;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        // If app sends order_item_id, target only that specific item
        $orderItemId = ! empty($data['order_item_id']) ? (string) $data['order_item_id'] : null;
        $targetItem = null;

        if ($orderItemId !== null) {
            $targetItem = $order->items->first(function ($item) use ($orderItemId) {
                return (string) $item->id === $orderItemId;
            });

            if (! $targetItem) {
                throw new Exception('Selected order item was not found in this order.', 422);
            }
        }

        // Guard against duplicate active return submissions
        $returnQuery = StoreReturn::where('order_id', $order->id)
            ->whereNotIn('status', ['CANCELLED', 'REJECTED']);

        if ($targetItem !== null && Schema::hasColumn('returns', 'order_item_id')) {
            $returnQuery->where('order_item_id', $targetItem->id);
        }

        $existingActiveReturn = $returnQuery->first();

        if ($existingActiveReturn) {
            $duplicateMsg = $targetItem !== null
                ? 'A return request has already been submitted for this item.'
                : 'A return request has already been submitted for this order.';
            throw new Exception($duplicateMsg, 422);
        }

        // Validate products in return:
        // Check only target item if order_item_id provided, otherwise check all order items
        $itemsToValidate = $targetItem ? collect([$targetItem]) : $order->items;

        foreach ($itemsToValidate as $item) {
            if (! $this->isItemReturnAllowed($item)) {
                throw new Exception(StoreErrorCodes::RETURN_NOT_ALLOWED, 422);
            }
            if ($this->isItemCustomized($item)) {
                throw new Exception('Customized product cannot be returned.', 422);
            }
        }

        // Calculate refund coins for ONLY the selected item (or full order if whole order return)
        if ($targetItem) {
            $totalCoins = (int) ($targetItem->total_coins ?? ($targetItem->total_coin_price ?? ($targetItem->total_price_coins ?? 0)));
            if ($totalCoins <= 0) {
                $unitCoins = (int) ($targetItem->unit_coins ?? ($targetItem->unit_coin_price ?? ($targetItem->unit_price_coins ?? 0)));
                $qty = (int) ($targetItem->quantity ?? 1);
                $totalCoins = $unitCoins * $qty;
            }
            $refundCoins = $totalCoins;
        } else {
            $refundCoins = (int) ($order->total_coins ?? 0);
        }

        $returnNo = 'RET-'.strtoupper(Str::random(10));

        return DB::transaction(function () use ($user, $order, $returnNo, $data, $targetItem, $refundCoins) {
            $attributes = [
                'return_no' => $returnNo,
                'order_id' => $order->id,
                'user_id' => $user->id,
                'reason_code' => $data['reason'] ?? 'DAMAGED',
                'reason_detail' => $data['description'] ?? null,
                'status' => 'REQUESTED',
                'refund_coins' => $refundCoins,
                'created_at' => now(),
            ];

            if ($targetItem && Schema::hasColumn('returns', 'order_item_id')) {
                $attributes['order_item_id'] = $targetItem->id;
            }

            if (Schema::hasColumn('returns', 'refund_coins_amount')) {
                $attributes['refund_coins_amount'] = $refundCoins;
            }

            $return = StoreReturn::create($attributes);

            // Update item-level status to RETURN_REQUESTED
            if ($targetItem) {
                $targetItem->update(['status' => 'RETURN_REQUESTED']);

                // Only set entire order status to RETURN_REQUESTED if all items in order are now returned
                $allItemsReturned = $order->items->every(function ($it) use ($targetItem) {
                    return (string) $it->id === (string) $targetItem->id || strtoupper((string) $it->status) === 'RETURN_REQUESTED';
                });

                if ($allItemsReturned) {
                    $order->update(['status' => 'RETURN_REQUESTED']);
                }
            } else {
                foreach ($order->items as $it) {
                    $it->update(['status' => 'RETURN_REQUESTED']);
                }
                $order->update(['status' => 'RETURN_REQUESTED']);
            }

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

    public function isItemCustomized(OrderItem $item): bool
    {
        if (isset($item->is_customized) && (bool) $item->is_customized) {
            return true;
        }

        if (isset($item->customised) && (bool) $item->customised) {
            return true;
        }

        if ($item->product) {
            if (! empty($item->product->customised) || ! empty($item->product->is_customized)) {
                return true;
            }
        }

        $snapshot = $item->product_snapshot;
        if (is_array($snapshot)) {
            if (! empty($snapshot['customised']) || ! empty($snapshot['is_customized'])) {
                return true;
            }
        }

        return false;
    }

    public function isItemReturnAllowed(OrderItem $item): bool
    {
        if ($item->product && isset($item->product->return_allowed)) {
            return (bool) $item->product->return_allowed;
        }

        $snapshot = $item->product_snapshot;
        if (is_array($snapshot) && isset($snapshot['return_allowed'])) {
            return (bool) $snapshot['return_allowed'];
        }

        return true;
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
