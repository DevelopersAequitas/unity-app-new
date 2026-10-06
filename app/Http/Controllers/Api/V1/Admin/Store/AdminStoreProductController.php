<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\Admin\AdminCreateProductRequest;
use App\Http\Requests\Store\Admin\AdminUpdateProductRequest;
use App\Models\Store\Product;
use App\Models\Store\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreProductController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'primaryImage', 'variants']);

        if ($search = $request->input('search')) {
            $query->where('name', 'ILIKE', "%{$search}%")->orWhere('sku', 'ILIKE', "%{$search}%");
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $perPage = (int) $request->input('per_page', 20);
        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->success($products, 'Admin products retrieved');
    }

    public function store(AdminCreateProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user() ? $request->user()->id : null;
        $product = Product::create($data);

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
        $product->update($request->validated());

        return $this->success($product, 'Product updated successfully');
    }

    public function disable(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update(['status' => 'INACTIVE']);

        return $this->success($product, 'Product disabled successfully');
    }

    public function addImage(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'image_url' => 'required|string|url',
            'is_primary' => 'nullable|boolean',
            'alt_text' => 'nullable|string|max:255',
        ]);

        $product = Product::findOrFail($id);
        if ($request->input('is_primary')) {
            ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);
        }

        $image = ProductImage::create([
            'product_id' => $product->id,
            'image_url' => $request->input('image_url'),
            'alt_text' => $request->input('alt_text'),
            'is_primary' => (bool) $request->input('is_primary', false),
            'sort_order' => 0,
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
