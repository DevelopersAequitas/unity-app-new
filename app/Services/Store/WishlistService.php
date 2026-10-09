<?php

namespace App\Services\Store;

use App\Constants\StoreErrorCodes;
use App\Models\Store\Product;
use App\Models\Store\ProductVariant;
use App\Models\Store\Wishlist;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WishlistService
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Get all wishlist items for a given user.
     */
    public function getUserWishlist(User $user): array
    {
        $items = Wishlist::forUser($user->id)
            ->with([
                'product' => function ($q) {
                    $q->with(['primaryImage', 'category']);
                },
                'variant',
            ])
            ->latest('created_at')
            ->get();

        $formattedItems = $items->map(function ($item) {
            $product = $item->product;
            $variant = $item->variant;

            if (! $product) {
                return null;
            }

            $coinPrice = (int) ($variant?->coin_price ?: ($variant?->price_coins ?: ($product->coin_price ?: ($product->price_coins ?: 0))));
            $stockQty = $variant ? (int) ($variant->stock_qty ?? $variant->stock_quantity ?? 0) : (int) ($product->stock_qty ?? 0);
            $inStock = $stockQty > 0 || (bool) ($product->allow_backorder ?? false);

            return [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'added_at' => $item->created_at?->toIso8601String(),
                'coin_price' => $coinPrice,
                'in_stock' => $inStock,
                'stock_qty' => $stockQty,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'sku' => $product->sku,
                    'type' => $product->type,
                    'status' => $product->status,
                    'coin_price' => (int) ($product->coin_price ?: ($product->price_coins ?: 0)),
                    'image_url' => $product->primaryImage?->url ?? $product->primaryImage?->image_url ?? null,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                        'slug' => $product->category->slug,
                    ] : null,
                ],
                'variant' => $variant ? [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'sku' => $variant->sku,
                    'coin_price' => (int) ($variant->coin_price ?: ($variant->price_coins ?: 0)),
                    'attributes' => $variant->attributes,
                ] : null,
            ];
        })->filter()->values();

        return [
            'total_items' => $formattedItems->count(),
            'items' => $formattedItems,
        ];
    }

    /**
     * Toggle product/variant in wishlist.
     */
    public function toggle(User $user, string $productId, ?string $variantId = null): array
    {
        $product = Product::active()->find($productId);
        if (! $product) {
            throw new Exception(StoreErrorCodes::PRODUCT_NOT_FOUND, 404);
        }

        if ($variantId) {
            $variant = ProductVariant::active()->where('product_id', $productId)->find($variantId);
            if (! $variant) {
                throw new Exception(StoreErrorCodes::VARIANT_NOT_FOUND, 404);
            }
        }

        $query = Wishlist::where('user_id', $user->id)->where('product_id', $productId);
        if ($variantId) {
            $query->where('variant_id', $variantId);
        } else {
            $query->whereNull('variant_id');
        }

        $existing = $query->first();

        if ($existing) {
            $existing->delete();

            return [
                'in_wishlist' => false,
                'wishlist_id' => null,
                'message' => 'Item removed from wishlist',
            ];
        }

        $item = Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $productId,
            'variant_id' => $variantId,
        ]);

        return [
            'in_wishlist' => true,
            'wishlist_id' => $item->id,
            'item' => $item,
            'message' => 'Item added to wishlist',
        ];
    }

    /**
     * Add item to wishlist.
     */
    public function addItem(User $user, string $productId, ?string $variantId = null): Wishlist
    {
        $product = Product::active()->find($productId);
        if (! $product) {
            throw new Exception(StoreErrorCodes::PRODUCT_NOT_FOUND, 404);
        }

        if ($variantId) {
            $variant = ProductVariant::active()->where('product_id', $productId)->find($variantId);
            if (! $variant) {
                throw new Exception(StoreErrorCodes::VARIANT_NOT_FOUND, 404);
            }
        }

        return Wishlist::firstOrCreate(
            [
                'user_id' => $user->id,
                'product_id' => $productId,
                'variant_id' => $variantId,
            ]
        );
    }

    /**
     * Remove item by wishlist ID.
     */
    public function removeItem(User $user, string $wishlistId): bool
    {
        return (bool) Wishlist::where('id', $wishlistId)
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * Remove item by product ID.
     */
    public function removeByProduct(User $user, string $productId, ?string $variantId = null): bool
    {
        $query = Wishlist::where('user_id', $user->id)->where('product_id', $productId);
        if ($variantId) {
            $query->where('variant_id', $variantId);
        }

        return (bool) $query->delete();
    }

    /**
     * Check if product/variant is in wishlist.
     */
    public function isWishlisted(User $user, string $productId, ?string $variantId = null): bool
    {
        $query = Wishlist::where('user_id', $user->id)->where('product_id', $productId);
        if ($variantId) {
            $query->where('variant_id', $variantId);
        }

        return $query->exists();
    }

    /**
     * Move item from wishlist to cart.
     */
    public function moveToCart(User $user, string $wishlistId): array
    {
        $item = Wishlist::where('id', $wishlistId)->where('user_id', $user->id)->first();
        if (! $item) {
            throw new Exception('Wishlist item not found', 404);
        }

        $cartResult = $this->cartService->addItem(
            $user,
            $item->product_id,
            $item->variant_id,
            1
        );

        $item->delete();

        return [
            'moved_to_cart' => true,
            'cart_item' => $cartResult,
            'message' => 'Item moved to cart successfully',
        ];
    }

    /**
     * Admin: Get filtered and paginated wishlist records.
     */
    public function getAdminWishlists(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Wishlist::query()
            ->with([
                'user:id,first_name,last_name,display_name,email,phone,company_name,coins_balance,profile_photo_url',
                'product' => function ($q) {
                    $q->with(['primaryImage', 'category']);
                },
                'variant',
            ]);

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                })->orWhereHas('product', function ($pq) use ($search) {
                    $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->latest('created_at')->paginate($perPage);
    }

    /**
     * Admin: Get wishlists grouped by peer/user.
     */
    public function getAdminWishlistsGroupedByUser(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $usersQuery = User::query()
            ->whereHas('wishlists')
            ->with([
                'wishlists.product.primaryImage',
                'wishlists.product.category',
                'wishlists.variant',
            ])
            ->withCount('wishlists');

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $usersQuery->where(function ($uq) use ($search) {
                $uq->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        return $usersQuery->orderByDesc('wishlists_count')->paginate($perPage);
    }

    /**
     * Admin: Get statistics on wishlists.
     */
    public function getAdminStats(): array
    {
        $totalItems = Wishlist::count();
        $uniqueUsers = Wishlist::distinct('user_id')->count('user_id');
        $uniqueProducts = Wishlist::distinct('product_id')->count('product_id');

        $topProducts = Wishlist::select('product_id', DB::raw('count(*) as wishlist_count'))
            ->groupBy('product_id')
            ->orderByDesc('wishlist_count')
            ->limit(5)
            ->with(['product.primaryImage', 'product.category'])
            ->get();

        return [
            'total_wishlist_items' => $totalItems,
            'unique_users_count' => $uniqueUsers,
            'unique_products_count' => $uniqueProducts,
            'top_products' => $topProducts,
        ];
    }
}
