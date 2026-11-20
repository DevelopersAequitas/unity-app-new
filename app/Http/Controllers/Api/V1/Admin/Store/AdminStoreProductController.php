<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\Admin\AdminCreateProductRequest;
use App\Http\Requests\Store\Admin\AdminUpdateProductRequest;
use App\Models\Store\Product;
use App\Models\Store\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AdminStoreProductController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'primaryImage', 'images', 'variants']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('sku', 'ILIKE', "%{$search}%")
                    ->orWhere('short_description', 'ILIKE', "%{$search}%");
            });
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $perPage = (int) $request->input('per_page', 20);
        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success($products, 'Admin products retrieved');
    }

    public function store(AdminCreateProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user() ? $request->user()->id : null;
        if (empty($data['subscription_duration_months']) && !empty($data['membership_duration'])) {
            $data['subscription_duration_months'] = (int) $data['membership_duration'];
        }
        $imageUrl = $data['image_url'] ?? $request->input('image_url');
        $product = Product::create($data);

        // Auto-create primary image if provided
        if (!empty($imageUrl)) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => $imageUrl,
                'is_primary' => true,
                'sort_order' => 1,
            ]);
        }
        // Save additional images if provided
        if ($request->has('images') && is_array($request->input('images'))) {
            foreach ($request->input('images') as $idx => $img) {
                $url = is_array($img) ? ($img['image_url'] ?? '') : (string) $img;
                if (!empty($url) && $url !== $imageUrl) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_url' => $url,
                        'is_primary' => false,
                        'sort_order' => $idx + 2,
                    ]);
                }
            }
        }
        // Save variants if provided
        if ($request->has('variants') && is_array($request->input('variants'))) {
            foreach ($request->input('variants') as $v) {
                if (is_array($v) && !empty($v['title'] ?? $v['name'])) {
                    \App\Models\Store\ProductVariant::create([
                        'product_id' => $product->id,
                        'name' => $v['title'] ?? $v['name'],
                        'sku' => $v['sku'] ?? ($product->sku . '-VAR-' . substr(uniqid(), -4)),
                        'coin_price' => (int) ($v['coin_price'] ?? $product->price_coins),
                        'stock_qty' => (int) ($v['stock_quantity'] ?? $v['stock_qty'] ?? $product->stock_qty ?? 0),
                        'is_active' => true,
                    ]);
                }
            }
        }

        $product->load(['category', 'primaryImage', 'images', 'variants']);

        return $this->success($product, 'Product created successfully', 201);
    }

    public function show(string $id): JsonResponse
    {
        $product = Product::with(['category', 'images', 'variants.inventoryMovements'])->findOrFail($id);

        return $this->success($product, 'Product retrieved');
    }

    public function update(AdminUpdateProductRequest $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $data = $request->validated();
        if (empty($data['subscription_duration_months']) && !empty($data['membership_duration'])) {
            $data['subscription_duration_months'] = (int) $data['membership_duration'];
        }
        $imageUrl = $data['image_url'] ?? $request->input('image_url');
        $product->update($data);

        if (!empty($imageUrl)) {
            $primary = ProductImage::where('product_id', $product->id)->where('is_primary', true)->first();
            if ($primary) {
                $primary->update(['image_url' => $imageUrl]);
            } else {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url' => $imageUrl,
                    'is_primary' => true,
                    'sort_order' => 1,
                ]);
            }
        }

        if ($request->has('variants') && is_array($request->input('variants'))) {
            foreach ($request->input('variants') as $v) {
                if (is_array($v) && !empty($v['title'] ?? $v['name'])) {
                    $vId = $v['id'] ?? null;
                    if ($vId && !str_starts_with($vId, 'var-')) {
                        \App\Models\Store\ProductVariant::where('id', $vId)->where('product_id', $product->id)->update([
                            'name' => $v['title'] ?? $v['name'],
                            'sku' => $v['sku'] ?? ($product->sku . '-VAR-' . substr(uniqid(), -4)),
                            'coin_price' => (int) ($v['coin_price'] ?? $product->price_coins),
                            'stock_qty' => (int) ($v['stock_quantity'] ?? $v['stock_qty'] ?? $product->stock_qty ?? 0),
                        ]);
                    } else {
                        \App\Models\Store\ProductVariant::create([
                            'product_id' => $product->id,
                            'name' => $v['title'] ?? $v['name'],
                            'sku' => $v['sku'] ?? ($product->sku . '-VAR-' . substr(uniqid(), -4)),
                            'coin_price' => (int) ($v['coin_price'] ?? $product->price_coins),
                            'stock_qty' => (int) ($v['stock_quantity'] ?? $v['stock_qty'] ?? $product->stock_qty ?? 0),
                            'is_active' => true,
                        ]);
                    }
                }
            }
        }

        $product->load(['category', 'primaryImage', 'images', 'variants']);

        return $this->success($product, 'Product updated successfully');
    }

    public function disable(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update(['status' => 'INACTIVE']);

        return $this->success($product, 'Product disabled successfully');
    }

    public function archive(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update(['status' => 'ARCHIVED']);

        return $this->success($product, 'Product archived successfully');
    }

    public function destroy(string $id): JsonResponse
    {
        $product = Product::with(['images', 'variants'])->findOrFail($id);

        DB::transaction(function () use ($product) {
            // Delete wishlists, cart items, quotes and entitlements
            if (Schema::hasTable('store_wishlists')) {
                \App\Models\Store\Wishlist::where('product_id', $product->id)->delete();
            }
            if (Schema::hasTable('store_cart_items')) {
                \App\Models\Store\CartItem::where('product_id', $product->id)->delete();
            }
            if (Schema::hasTable('store_checkout_quote_items')) {
                \App\Models\Store\CheckoutQuoteItem::where('product_id', $product->id)->delete();
            }
            if (Schema::hasTable('store_entitlements')) {
                \App\Models\Store\Entitlement::where('product_id', $product->id)->delete();
            }

            // Clean up any test/demo order items and returns linked to this product
            if (Schema::hasTable('order_items')) {
                $orderItemIds = \App\Models\Store\OrderItem::where('product_id', $product->id)->pluck('id');
                if ($orderItemIds->isNotEmpty()) {
                    if (Schema::hasTable('store_returns')) {
                        DB::table('store_returns')->whereIn('order_item_id', $orderItemIds)->delete();
                    }
                    if (Schema::hasTable('return_items')) {
                        DB::table('return_items')->whereIn('order_item_id', $orderItemIds)->delete();
                    }
                    \App\Models\Store\OrderItem::where('product_id', $product->id)->delete();
                }
            }

            // Delete reviews
            $product->reviews()->delete();

            // Delete variants and their movements
            foreach ($product->variants as $variant) {
                if (Schema::hasTable('store_inventory_movements')) {
                    \App\Models\Store\InventoryMovement::where('variant_id', $variant->id)->delete();
                }
                $variant->delete();
            }

            // Delete product images (including physical files)
            foreach ($product->images as $img) {
                if ($img->image_url && str_starts_with($img->image_url, '/storage/')) {
                    $storagePath = str_replace('/storage/', '', $img->image_url);
                    Storage::disk('public')->delete($storagePath);
                }
                $img->delete();
            }

            // Permanently delete the product
            $product->delete();
        });

        return $this->success(['deleted' => true], 'Product deleted successfully');
    }

    public function addImage(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'image_url' => 'required|string|url',
            'is_primary' => 'nullable|boolean',
            'alt_text' => 'nullable|string|max:255',
        ]);

        $product = Product::findOrFail($id);

        $existingImageCount = ProductImage::where('product_id', $product->id)->count();

        // Auto-set as primary if it's the first image, or if explicitly requested
        $isPrimary = $existingImageCount === 0 || (bool) $request->input('is_primary', false);

        if ($isPrimary) {
            ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);
        }

        $image = ProductImage::create([
            'product_id' => $product->id,
            'image_url' => $request->input('image_url'),
            'alt_text' => $request->input('alt_text'),
            'is_primary' => $isPrimary,
            'sort_order' => $existingImageCount,
            'created_at' => now(),
        ]);

        return $this->success($image, 'Image added successfully', 201);
    }

    public function removeImage(string $productId, string $imageId): JsonResponse
    {
        ProductImage::where('product_id', $productId)->where('id', $imageId)->delete();

        return $this->success(['deleted' => true], 'Image removed');
    }
}
