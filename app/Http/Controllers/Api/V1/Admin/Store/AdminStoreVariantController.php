<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\Admin\AdminCreateVariantRequest;
use App\Http\Requests\Store\Admin\AdminStockAdjustmentRequest;
use App\Models\Store\InventoryMovement;
use App\Models\Store\Product;
use App\Models\Store\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminStoreVariantController extends BaseApiController
{
    public function store(AdminCreateVariantRequest $request, string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $data = $request->validated();
        $data['product_id'] = $product->id;

        $price = $data['price_coins'] ?? ($data['coin_price'] ?? ($product->price_coins ?: $product->coin_price));
        if (! $price || $price <= 0) {
            $price = (int) ($product->coin_price ?: ($product->price_coins ?: 1));
        }
        $data['price_coins'] = $price;
        $data['coin_price'] = $price;

        $variant = ProductVariant::create($data);

        $this->syncProductParentPrice($product);

        return $this->success($variant, 'Variant created successfully', 201);
    }

    public function update(Request $request, string $variantId): JsonResponse
    {
        $variant = ProductVariant::findOrFail($variantId);
        $data = $request->all();

        if (isset($data['price_coins']) || isset($data['coin_price'])) {
            $price = (int) ($data['price_coins'] ?? $data['coin_price']);
            if ($price <= 0 && $variant->product) {
                $price = (int) ($variant->product->coin_price ?: $variant->product->price_coins);
            }
            $data['price_coins'] = $price;
            $data['coin_price'] = $price;
        }

        $variant->update($data);

        if ($variant->product) {
            $this->syncProductParentPrice($variant->product);
        }

        return $this->success($variant, 'Variant updated successfully');
    }

    protected function syncProductParentPrice(Product $product): void
    {
        $activeVariants = ProductVariant::where('product_id', $product->id)
            ->where(function ($q) {
                $q->where('status', 'ACTIVE')
                    ->orWhere('is_active', true)
                    ->orWhereNull('status');
            })
            ->where(function ($q) {
                $q->where('price_coins', '>', 0)
                    ->orWhere('coin_price', '>', 0);
            })
            ->get();

        if ($activeVariants->isNotEmpty()) {
            $minPrice = $activeVariants->map(function ($v) {
                return (int) ($v->coin_price ?: ($v->price_coins ?: 0));
            })->filter(fn ($p) => $p > 0)->min();

            if ($minPrice !== null && $minPrice > 0) {
                $product->update([
                    'price_coins' => (int) $minPrice,
                    'coin_price' => (int) $minPrice,
                ]);
            }
        }
    }

    public function adjustStock(AdminStockAdjustmentRequest $request, string $variantId): JsonResponse
    {
        $data = $request->validated();
        $adminUser = $request->user();

        return DB::transaction(function () use ($variantId, $data, $adminUser) {
            $variant = ProductVariant::where('id', $variantId)->lockForUpdate()->firstOrFail();

            $change = (int) $data['quantity_change'];
            $newStock = max(0, $variant->stock_qty + $change);

            $variant->update(['stock_qty' => $newStock]);

            $movement = InventoryMovement::create([
                'variant_id' => $variant->id,
                'quantity_change' => $change,
                'quantity_after' => $newStock,
                'reason' => $data['reason'],
                'actor_type' => 'ADMIN',
                'actor_id' => $adminUser ? $adminUser->id : null,
                'note' => $data['note'] ?? null,
                'created_at' => now(),
            ]);

            return $this->success([
                'variant' => $variant->fresh(),
                'movement' => $movement,
            ], 'Stock adjusted successfully');
        });
    }

    public function inventoryHistory(string $variantId): JsonResponse
    {
        $movements = InventoryMovement::where('variant_id', $variantId)->orderBy('created_at', 'desc')->paginate(20);

        return $this->success($movements, 'Inventory movements history retrieved');
    }
}
