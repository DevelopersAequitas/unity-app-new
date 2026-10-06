<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\StoreAddCartItemRequest;
use App\Http\Requests\Store\StoreUpdateCartItemRequest;
use App\Services\Store\CartService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends BaseApiController
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $cart = $this->cartService->getCartWithItems($user);

        return $this->success($cart, 'Active cart retrieved');
    }

    public function addItem(StoreAddCartItemRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            $result = $this->cartService->addItem(
                $user,
                $data['product_id'],
                $data['variant_id'] ?? null,
                $data['quantity']
            );

            return $this->success($result, 'Item added to cart');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function updateItem(StoreUpdateCartItemRequest $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            $result = $this->cartService->updateItem($user, $id, $data['quantity']);

            return $this->success($result, 'Cart item updated');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function removeItem(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $deleted = $this->cartService->removeItem($user, $id);

        if (! $deleted) {
            return $this->error('Cart item not found', 404);
        }

        return $this->success(['removed' => true], 'Item removed from cart');
    }

    public function validateCart(Request $request): JsonResponse
    {
        $user = $request->user();
        $validation = $this->cartService->validateCart($user);

        return $this->success($validation, 'Cart validation completed');
    }
}
