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

        $variant = ProductVariant::create($data);

        return $this->success($variant, 'Variant created successfully', 201);
    }

    public function update(Request $request, string $variantId): JsonResponse
    {
        $variant = ProductVariant::findOrFail($variantId);
        $variant->update($request->all());

        return $this->success($variant, 'Variant updated successfully');
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
