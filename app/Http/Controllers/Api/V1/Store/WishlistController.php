<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\StoreAddWishlistRequest;
use App\Http\Requests\Store\StoreToggleWishlistRequest;
use App\Services\Store\WishlistService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends BaseApiController
{
    protected WishlistService $wishlistService;

    public function __construct(WishlistService $wishlistService)
    {
        $this->wishlistService = $wishlistService;
    }

    /**
     * Get active peer's wishlist.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $wishlist = $this->wishlistService->getUserWishlist($user);

        return $this->success($wishlist, 'Wishlist retrieved successfully');
    }

    /**
     * Toggle product/variant in peer's wishlist.
     */
    public function toggle(StoreToggleWishlistRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            $result = $this->wishlistService->toggle(
                $user,
                $data['product_id'],
                $data['variant_id'] ?? null
            );

            return $this->success($result, $result['message']);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * Add product/variant to peer's wishlist.
     */
    public function addItem(StoreAddWishlistRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            $item = $this->wishlistService->addItem(
                $user,
                $data['product_id'],
                $data['variant_id'] ?? null
            );

            return $this->success($item, 'Product added to wishlist', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    /**
     * Remove item by wishlist ID.
     */
    public function removeItem(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $deleted = $this->wishlistService->removeItem($user, $id);

        if (! $deleted) {
            return $this->error('Wishlist item not found', 404);
        }

        return $this->success(['removed' => true], 'Item removed from wishlist');
    }

    /**
     * Remove item by product ID.
     */
    public function removeByProduct(Request $request, string $productId): JsonResponse
    {
        $user = $request->user();
        $variantId = $request->query('variant_id');
        $deleted = $this->wishlistService->removeByProduct($user, $productId, $variantId);

        if (! $deleted) {
            return $this->error('Wishlist item not found', 404);
        }

        return $this->success(['removed' => true], 'Product removed from wishlist');
    }

    /**
     * Check if product is in wishlist.
     */
    public function check(Request $request, string $productId): JsonResponse
    {
        $user = $request->user();
        $variantId = $request->query('variant_id');
        $isWishlisted = $this->wishlistService->isWishlisted($user, $productId, $variantId);

        return $this->success([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'in_wishlist' => $isWishlisted,
        ], 'Wishlist status checked');
    }

    /**
     * Move wishlist item directly to user's cart.
     */
    public function moveToCart(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->wishlistService->moveToCart($user, $id);

            return $this->success($result, $result['message']);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}
