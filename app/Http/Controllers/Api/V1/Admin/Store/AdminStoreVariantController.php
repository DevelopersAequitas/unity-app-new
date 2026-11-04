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
    /**
     * Admin: List all inventory items (combines product variants and products without variants).
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->input('search');
        $lowStockOnly = filter_var($request->input('low_stock', false), FILTER_VALIDATE_BOOLEAN);

        $query = Product::with(['category', 'primaryImage', 'variants']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('sku', 'ILIKE', "%{$search}%");
            });
        }

        $products = $query->orderBy('name', 'asc')->get();
        $items = [];

        foreach ($products as $prod) {
            if ($prod->variants && $prod->variants->isNotEmpty()) {
                foreach ($prod->variants as $variant) {
                    $threshold = (int) ($variant->low_stock_threshold ?? 5);
                    $stock = (int) ($variant->stock_qty ?? $variant->stock_quantity ?? 0);
                    $isLow = $stock <= $threshold;

                    if ($lowStockOnly && ! $isLow) {
                        continue;
                    }

                    $items[] = [
                        'id' => (string) $variant->id,
                        'product_id' => (string) $prod->id,
                        'variant_id' => (string) $variant->id,
                        'product_title' => $prod->name . ($variant->name ? " ({$variant->name})" : ''),
                        'sku' => $variant->sku ?: $prod->sku,
                        'current_stock' => $stock,
                        'safety_stock' => $threshold,
                        'is_low_stock' => $isLow,
                        'price_inr' => (float) ($prod->unit_cost_inr ?? 0),
                        'coin_price' => (int) ($variant->coin_price ?: ($variant->price_coins ?: ($prod->coin_price ?: ($prod->price_coins ?: 0)))),
                        'last_restocked_at' => $variant->updated_at ? $variant->updated_at->toIso8601String() : null,
                    ];
                }
            } else {
                $threshold = 5;
                $stock = (int) ($prod->stock_qty ?? 0);
                $isLow = $stock <= $threshold;

                if ($lowStockOnly && ! $isLow) {
                    continue;
                }

                $items[] = [
                    'id' => (string) $prod->id,
                    'product_id' => (string) $prod->id,
                    'variant_id' => null,
                    'product_title' => $prod->name,
                    'sku' => $prod->sku,
                    'current_stock' => $stock,
                    'safety_stock' => $threshold,
                    'is_low_stock' => $isLow,
                    'price_inr' => (float) ($prod->unit_cost_inr ?? 0),
                    'coin_price' => (int) ($prod->coin_price ?: ($prod->price_coins ?: 0)),
                    'last_restocked_at' => $prod->updated_at ? $prod->updated_at->toIso8601String() : null,
                ];
            }
        }

        return $this->success($items, 'Store inventory items retrieved');
    }

    /**
     * Admin: List all stock movements across the store.
     */
    public function allMovements(Request $request): JsonResponse
    {
        $query = InventoryMovement::with(['variant.product']);

        if ($sku = $request->input('sku')) {
            $query->whereHas('variant', function ($q) use ($sku) {
                $q->where('sku', 'ILIKE', "%{$sku}%");
            });
        }

        $perPage = (int) $request->input('per_page', 25);
        $movements = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $mapped = $movements->getCollection()->map(function ($m) {
            $variant = $m->variant;
            $product = $variant?->product;

            $sku = $variant?->sku ?? 'SKU-STORE';
            $title = $product?->name ?? 'General Merchandise';
            if ($variant && $variant->name) {
                $title .= " ({$variant->name})";
            }

            $type = 'MANUAL_ADJUSTMENT';
            if ($m->quantity_change > 0 && str_contains(strtolower($m->reason ?? ''), 'inbound')) {
                $type = 'INBOUND';
            } elseif ($m->quantity_change < 0 && str_contains(strtolower($m->reason ?? ''), 'order')) {
                $type = 'ORDER_FULFILLMENT';
            } elseif ($m->quantity_change > 0 && str_contains(strtolower($m->reason ?? ''), 'return')) {
                $type = 'RETURN_RESTOCK';
            }

            return [
                'id' => (string) $m->id,
                'sku' => $sku,
                'product_title' => $title,
                'quantity_change' => (int) $m->quantity_change,
                'type' => $type,
                'previous_stock' => (int) ($m->quantity_after - $m->quantity_change),
                'new_stock' => (int) $m->quantity_after,
                'reason' => $m->reason ?: ($m->note ?? 'Stock adjustment'),
                'performed_by_admin' => 'Admin Operation',
                'created_at' => $m->created_at ? $m->created_at->toIso8601String() : now()->toIso8601String(),
            ];
        });

        $paginatedResponse = [
            'current_page' => $movements->currentPage(),
            'data' => $mapped,
            'total' => $movements->total(),
            'per_page' => $movements->perPage(),
            'last_page' => $movements->lastPage(),
        ];

        return $this->success($paginatedResponse, 'Inventory movements retrieved');
    }

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
        $variant = ProductVariant::find($variantId);
        if ($variant) {
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

        // Direct product update fallback
        $product = Product::findOrFail($variantId);
        $product->update($request->all());

        return $this->success($product, 'Product inventory updated successfully');
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
        $change = (int) ($data['quantity_change'] ?? ($data['quantity'] ?? 0));

        return DB::transaction(function () use ($variantId, $change, $data, $adminUser) {
            $variant = ProductVariant::where('id', $variantId)->lockForUpdate()->first();

            if ($variant) {
                $newStock = max(0, (int) $variant->stock_qty + $change);
                $variant->update(['stock_qty' => $newStock]);

                // Also update parent product total stock
                if ($variant->product_id) {
                    $totalStock = ProductVariant::where('product_id', $variant->product_id)->sum('stock_qty');
                    Product::where('id', $variant->product_id)->update(['stock_qty' => $totalStock]);
                }

                $movement = InventoryMovement::create([
                    'variant_id' => $variant->id,
                    'quantity_change' => $change,
                    'quantity_after' => $newStock,
                    'reason' => $data['reason'] ?? 'Manual Stock Adjustment',
                    'actor_type' => 'ADMIN',
                    'actor_id' => $adminUser ? $adminUser->id : null,
                    'note' => $data['note'] ?? null,
                    'created_at' => now(),
                ]);

                return $this->success([
                    'variant' => $variant->fresh(),
                    'movement' => $movement,
                ], 'Stock adjusted successfully');
            }

            // Otherwise, target is direct Product
            $product = Product::where('id', $variantId)->lockForUpdate()->firstOrFail();
            $newStock = max(0, (int) $product->stock_qty + $change);
            $product->update(['stock_qty' => $newStock]);

            return $this->success([
                'product' => $product->fresh(),
                'new_stock' => $newStock,
            ], 'Product stock adjusted successfully');
        });
    }

    public function inventoryHistory(string $variantId): JsonResponse
    {
        $movements = InventoryMovement::where('variant_id', $variantId)->orderBy('created_at', 'desc')->paginate(20);

        return $this->success($movements, 'Inventory movements history retrieved');
    }
}
