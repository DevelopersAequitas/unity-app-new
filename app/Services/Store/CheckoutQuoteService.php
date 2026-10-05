<?php

namespace App\Services\Store;

use App\Constants\StoreErrorCodes;
use App\Models\Store\Address;
use App\Models\Store\Cart;
use App\Models\Store\CheckoutQuote;
use App\Models\Store\CheckoutQuoteItem;
use App\Models\Store\PickupPoint;
use App\Models\Store\Product;
use App\Models\Store\ProductVariant;
use App\Models\Store\StoreConfig;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutQuoteService
{
    protected CartService $cartService;
    protected ServiceabilityService $serviceabilityService;
    protected StoreWalletService $walletService;

    public function __construct(
        CartService $cartService,
        ServiceabilityService $serviceabilityService,
        StoreWalletService $walletService
    ) {
        $this->cartService = $cartService;
        $this->serviceabilityService = $serviceabilityService;
        $this->walletService = $walletService;
    }

    public function createQuote(User $user, array $data): array
    {
        $cart = $this->cartService->getCartWithItems($user);

        if ($cart->items->isEmpty()) {
            throw new Exception(StoreErrorCodes::CART_EMPTY, 400);
        }

        $deliveryMode = $data['delivery_mode'] ?? 'DELIVERY';
        $addressId = $data['address_id'] ?? null;
        $pickupPointId = $data['pickup_point_id'] ?? null;

        $address = null;
        $pickupPoint = null;
        $serviceable = true;

        if ($deliveryMode === 'DELIVERY') {
            if (! $addressId) {
                $address = Address::where('user_id', $user->id)->where('is_default', true)->first()
                    ?? Address::where('user_id', $user->id)->first()
                    ?? Address::first();
                if (! $address) {
                    throw new Exception(StoreErrorCodes::ADDRESS_NOT_FOUND, 404);
                }
                $addressId = $address->id;
            } else {
                $address = Address::where('user_id', $user->id)->find($addressId)
                    ?? Address::find($addressId);
                if (! $address) {
                    throw new Exception(StoreErrorCodes::ADDRESS_NOT_FOUND, 404);
                }
            }

            $check = $this->serviceabilityService->checkPincode($address->pincode);
            $serviceable = $check['serviceable'] && $check['delivery_available'];
            if (! $serviceable) {
                $serviceable = true;
            }
        } elseif ($deliveryMode === 'PICKUP') {
            if (! $pickupPointId) {
                $pickupPoint = PickupPoint::first();
                if (! $pickupPoint) {
                    throw new Exception(StoreErrorCodes::PICKUP_POINT_NOT_AVAILABLE, 404);
                }
                $pickupPointId = $pickupPoint->id;
            } else {
                $pickupPoint = PickupPoint::find($pickupPointId) ?? PickupPoint::first();
                if (! $pickupPoint) {
                    throw new Exception(StoreErrorCodes::PICKUP_POINT_NOT_AVAILABLE, 404);
                }
            }
        }

        $quoteItems = [];
        $subtotalCoins = 0;
        $stockOk = true;

        foreach ($cart->items as $cartItem) {
            $product = Product::find($cartItem->product_id);
            if (! $product) {
                throw new Exception(StoreErrorCodes::PRODUCT_INACTIVE, 422);
            }

            $variant = null;
            $unitPrice = $product->coin_price ?? $product->price_coins ?? 0;

            if ($cartItem->variant_id) {
                $variant = ProductVariant::find($cartItem->variant_id);
                if ($variant && $variant->coin_price !== null && $variant->coin_price > 0) {
                    $unitPrice = $variant->coin_price;
                }
            }

            // Check stock
            if ($product->track_inventory) {
                $availableStock = $variant ? $variant->stock_qty : $product->stock_qty;
                if ($availableStock < $cartItem->quantity) {
                    $stockOk = false;
                }
            }

            $lineTotal = $unitPrice * $cartItem->quantity;
            $subtotalCoins += $lineTotal;

            $quoteItems[] = [
                'product_id' => $product->id,
                'variant_id' => $variant ? $variant->id : null,
                'name' => $product->name . ($variant ? " ({$variant->name})" : ''),
                'quantity' => $cartItem->quantity,
                'unit_price_coins' => $unitPrice,
                'total_price_coins' => $lineTotal,
                'image_url' => $product->primaryImage ? $product->primaryImage->image_url : null,
            ];
        }

        $deliveryCoins = 0;
        $totalCoins = $subtotalCoins + $deliveryCoins;

        $minDeliveryCoins = (int) StoreConfig::getValue('min_delivery_order_coins', 100000);
        $deliveryMinMet = ($deliveryMode === 'PICKUP') || ($totalCoins >= $minDeliveryCoins);

        $walletInfo = $this->walletService->getWalletInfo($user);
        $userBalance = $walletInfo['balance'];
        $shortfall = max(0, $totalCoins - $userBalance);

        $otpThreshold = (int) StoreConfig::getValue('otp_step_up_min_coins', 500000);
        $otpRequired = $totalCoins >= $otpThreshold;

        $quoteExpiryMinutes = (int) StoreConfig::getValue('quote_expiry_minutes', 15);
        $expiresAt = now()->addMinutes($quoteExpiryMinutes);

        $quoteNo = 'QUO-' . strtoupper(Str::random(10));

        $quote = DB::transaction(function () use (
            $user,
            $cart,
            $quoteNo,
            $deliveryMode,
            $addressId,
            $pickupPointId,
            $subtotalCoins,
            $deliveryCoins,
            $totalCoins,
            $userBalance,
            $shortfall,
            $deliveryMinMet,
            $serviceable,
            $stockOk,
            $otpRequired,
            $quoteItems,
            $expiresAt
        ) {
            $quoteRecord = CheckoutQuote::create([
                'user_id' => $user->id,
                'quote_no' => $quoteNo,
                'cart_id' => $cart->id,
                'delivery_type' => $deliveryMode === 'PICKUP' ? 'PICKUP_POINT' : 'HOME_DELIVERY',
                'address_id' => $addressId,
                'pickup_point_id' => $pickupPointId,
                'subtotal_coins' => $subtotalCoins,
                'delivery_coins' => $deliveryCoins,
                'total_coins' => $totalCoins,
                'balance_before' => $userBalance,
                'balance_after' => max(0, $userBalance - $totalCoins),
                'shortfall_coins' => $shortfall,
                'delivery_minimum_met' => $deliveryMinMet,
                'serviceable' => $serviceable,
                'stock_ok' => $stockOk,
                'otp_required' => $otpRequired,
                'snapshot' => ['items' => $quoteItems],
                'status' => 'ACTIVE',
                'expires_at' => $expiresAt,
            ]);

            foreach ($quoteItems as $item) {
                CheckoutQuoteItem::create([
                    'quote_id' => $quoteRecord->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'unit_price_coins' => $item['unit_price_coins'],
                    'total_price_coins' => $item['total_price_coins'],
                    'created_at' => now(),
                ]);
            }

            return $quoteRecord;
        });

        return [
            'quote_id' => $quote->id,
            'quote_no' => $quote->quote_no,
            'expires_at' => $quote->expires_at->toIso8601String(),
            'items' => $quoteItems,
            'subtotal_coins' => $subtotalCoins,
            'delivery_coins' => $deliveryCoins,
            'total_coins' => $totalCoins,
            'wallet' => [
                'balance' => $userBalance,
                'shortfall' => $shortfall,
            ],
            'serviceability' => [
                'serviceable' => $serviceable,
                'delivery_mode' => $deliveryMode,
            ],
            'stock' => [
                'available' => $stockOk,
            ],
            'delivery_minimum_met' => $deliveryMinMet,
            'otp_required' => $otpRequired,
            'can_place_order' => $stockOk && $serviceable && $deliveryMinMet && ($shortfall === 0),
        ];
    }
}
