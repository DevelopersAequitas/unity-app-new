<?php

namespace App\Services\Store;

use App\Constants\StoreErrorCodes;
use App\Models\Store\Cart;
use App\Models\Store\CartItem;
use App\Models\Store\Product;
use App\Models\Store\ProductVariant;
use App\Models\Store\StoreConfig;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class CartService
{
    protected StoreEligibilityService $eligibilityService;

    public function __construct(StoreEligibilityService $eligibilityService)
    {
        $this->eligibilityService = $eligibilityService;
    }

    public function getActiveCart(User $user): Cart
    {
        return Cart::firstOrCreate(
            ['user_id' => $user->id, 'status' => 'ACTIVE'],
            ['last_changed_at' => now(), 'expires_at' => now()->addDays(30)]
        );
    }

    public function getCartWithItems(User $user): Cart
    {
        $cart = $this->getActiveCart($user);
        $cart->load([
            'items.product.primaryImage',
            'items.variant',
        ]);

        foreach ($cart->items as $item) {
            $currentPrice = null;
            if ($item->variant) {
                $vPrice = (int) ($item->variant->coin_price ?: ($item->variant->price_coins ?: 0));
                if ($vPrice > 0) {
                    $currentPrice = $vPrice;
                }
            }
            if ($currentPrice === null && $item->product) {
                $pPrice = (int) ($item->product->coin_price ?: ($item->product->price_coins ?: 0));
                if ($pPrice > 0) {
                    $currentPrice = $pPrice;
                }
            }

            if ($currentPrice !== null && $currentPrice > 0 && (int) $item->price_seen_coins !== $currentPrice) {
                $item->price_seen_coins = $currentPrice;
                CartItem::where('id', $item->id)->update(['price_seen_coins' => $currentPrice]);
            }
        }

        return $cart;
    }

    public function addItem(User $user, string $productId, ?string $variantId, int $quantity): array
    {
        $product = Product::active()->find($productId);
        if (! $product) {
            throw new Exception(StoreErrorCodes::PRODUCT_NOT_FOUND, 404);
        }

        if (! $this->eligibilityService->isEligible($product, $user)) {
            throw new Exception(StoreErrorCodes::ELIGIBILITY_FAILED, 403);
        }

        $variant = null;
        $unitPrice = (int) ($product->coin_price ?: ($product->price_coins ?: 0));

        if ($variantId) {
            $variant = ProductVariant::active()->where('product_id', $productId)->find($variantId);
            if (! $variant) {
                throw new Exception(StoreErrorCodes::VARIANT_NOT_FOUND, 404);
            }
            $vPrice = (int) ($variant->coin_price ?: ($variant->price_coins ?: 0));
            if ($vPrice > 0) {
                $unitPrice = $vPrice;
            }
        }

        if ($unitPrice <= 0) {
            $unitPrice = (int) ($product->coin_price ?: ($product->price_coins ?: 1));
        }

        $cart = $this->getActiveCart($user);

        return DB::transaction(function () use ($cart, $product, $variantId, $quantity, $unitPrice) {
            $item = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->where('variant_id', $variantId)
                ->first();

            $newQty = $quantity;
            if ($item) {
                $newQty = $item->quantity + $quantity;
            }

            $minQty = (int) ($product->min_quantity_per_order ?: 1);
            if ($newQty < $minQty) {
                throw new Exception("Minimum purchase quantity for {$product->name} is {$minQty}.", 422);
            }

            if ($product->max_quantity_per_order && $newQty > $product->max_quantity_per_order) {
                throw new Exception("Maximum purchase quantity for {$product->name} is {$product->max_quantity_per_order}.", 422);
            }

            if ($item) {
                $item->update([
                    'quantity' => $newQty,
                    'price_seen_coins' => $unitPrice,
                ]);
            } else {
                $item = CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'variant_id' => $variantId,
                    'quantity' => $newQty,
                    'price_seen_coins' => $unitPrice,
                    'added_at' => now(),
                ]);
            }

            $cart->update(['last_changed_at' => now()]);

            return [
                'cart_item' => $item->load(['product.primaryImage', 'variant']),
                'cart' => $cart->load(['items.product.primaryImage', 'items.variant']),
            ];
        });
    }

    public function updateItem(User $user, string $cartItemId, int $quantity): array
    {
        $cart = $this->getActiveCart($user);

        // Find item by ID either directly or inside user's active cart
        $item = CartItem::where('cart_id', $cart->id)->where('id', $cartItemId)->first()
            ?? CartItem::where('id', $cartItemId)->first();

        if (! $item) {
            throw new Exception(StoreErrorCodes::CART_INVALID, 404);
        }

        $product = Product::find($item->product_id);
        $minQty = (int) ($product ? ($product->min_quantity_per_order ?: 1) : 1);

        if ($quantity < $minQty) {
            throw new Exception("Minimum purchase quantity for " . ($product ? $product->name : 'this product') . " is {$minQty}.", 422);
        }

        if ($product && $product->max_quantity_per_order && $quantity > $product->max_quantity_per_order) {
            throw new Exception("Maximum purchase quantity for {$product->name} is {$product->max_quantity_per_order}.", 422);
        }

        $item->update([
            'quantity' => $quantity,
        ]);

        $cart->update(['last_changed_at' => now()]);

        return [
            'cart_item' => $item->load(['product.primaryImage', 'variant']),
            'cart' => $cart->load(['items.product.primaryImage', 'items.variant']),
        ];
    }

    public function removeItem(User $user, string $cartItemId): bool
    {
        $cart = $this->getActiveCart($user);

        $deleted = CartItem::where('cart_id', $cart->id)->where('id', $cartItemId)->delete();
        if (! $deleted) {
            $deleted = CartItem::where('id', $cartItemId)->delete();
        }

        $cart->update(['last_changed_at' => now()]);

        return $deleted > 0;
    }

    public function validateCart(User $user): array
    {
        $cart = $this->getCartWithItems($user);
        $issues = [];
        $totalCoins = 0;
        $isValid = true;

        foreach ($cart->items as $item) {
            $product = $item->product;
            $variant = $item->variant;

            $itemStatus = 'OK';
            $currentPrice = $product->coin_price;

            $minQty = (int) ($product->min_quantity_per_order ?: 1);
            if ($item->quantity < $minQty) {
                $itemStatus = 'QUANTITY_BELOW_MIN';
                $isValid = false;
            } elseif ($product->max_quantity_per_order && $item->quantity > $product->max_quantity_per_order) {
                $itemStatus = 'QUANTITY_LIMIT_EXCEEDED';
                $isValid = false;
            }

            if ($variant) {
                if ($variant->coin_price !== null && $variant->coin_price > 0) {
                    $currentPrice = $variant->coin_price;
                }
                if ($variant->stock_quantity < $item->quantity) {
                    $itemStatus = 'OUT_OF_STOCK';
                    $isValid = false;
                }
            }

            if ($product->status !== 'ACTIVE') {
                $itemStatus = 'PRODUCT_INACTIVE';
                $isValid = false;
            }

            $priceChanged = ($currentPrice !== (int) $item->unit_coin_price);
            if ($priceChanged) {
                $itemStatus = 'PRICE_CHANGED';
                $isValid = false;
            }

            $totalCoins += ($currentPrice * $item->quantity);

            $issues[] = [
                'cart_item_id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'status' => $itemStatus,
                'quantity' => $item->quantity,
                'min_quantity_per_order' => $minQty,
                'max_quantity_per_order' => $product->max_quantity_per_order,
                'price_seen' => (int) $item->unit_coin_price,
                'current_price' => $currentPrice,
                'price_changed' => $priceChanged,
            ];
        }

        $minOrderCoins = (int) (StoreConfig::getValue('min_order_total_coins') ?? StoreConfig::getValue('min_order_coins', 0));
        $minOrderMet = ($minOrderCoins <= 0) || ($totalCoins >= $minOrderCoins);
        if (! $minOrderMet) {
            $isValid = false;
        }

        $sufficientBalance = ($user->coins_balance ?? 0) >= $totalCoins;
        if (! $sufficientBalance) {
            $isValid = false;
        }

        return [
            'is_valid' => $isValid,
            'total_coins' => $totalCoins,
            'user_coins_balance' => (int) ($user->coins_balance ?? 0),
            'sufficient_balance' => $sufficientBalance,
            'min_order_total_coins' => $minOrderCoins,
            'min_order_met' => $minOrderMet,
            'min_order_message' => (! $minOrderMet) ? "Your order must contain products worth at least " . number_format($minOrderCoins) . " coins. Please add more products to continue." : null,
            'items' => $issues,
        ];
    }
}
