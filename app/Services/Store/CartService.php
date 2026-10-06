<?php

namespace App\Services\Store;

use App\Constants\StoreErrorCodes;
use App\Models\Store\Cart;
use App\Models\Store\CartItem;
use App\Models\Store\Product;
use App\Models\Store\ProductVariant;
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
        $unitPrice = $product->coin_price;

        if ($variantId) {
            $variant = ProductVariant::active()->where('product_id', $productId)->find($variantId);
            if (! $variant) {
                throw new Exception(StoreErrorCodes::VARIANT_NOT_FOUND, 404);
            }
            if ($variant->coin_price !== null && $variant->coin_price > 0) {
                $unitPrice = $variant->coin_price;
            }
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

            if ($product->max_quantity_per_order && $newQty > $product->max_quantity_per_order) {
                throw new Exception(StoreErrorCodes::QUANTITY_LIMIT_EXCEEDED, 422);
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
        if ($product && $product->max_quantity_per_order && $quantity > $product->max_quantity_per_order) {
            throw new Exception(StoreErrorCodes::QUANTITY_LIMIT_EXCEEDED, 422);
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
                'price_seen' => (int) $item->unit_coin_price,
                'current_price' => $currentPrice,
                'price_changed' => $priceChanged,
            ];
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
            'items' => $issues,
        ];
    }
}
