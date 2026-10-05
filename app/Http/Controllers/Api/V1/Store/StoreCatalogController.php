<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\Store\StoreCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreCatalogController extends BaseApiController
{
    protected StoreCatalogService $catalogService;

    public function __construct(StoreCatalogService $catalogService)
    {
        $this->catalogService = $catalogService;
    }

    public function categories(): JsonResponse
    {
        $categories = $this->catalogService->getCategories();

        return $this->success($categories, 'Active categories retrieved');
    }

    public function products(Request $request): JsonResponse
    {
        $filters = $request->only([
            'category_id',
            'search',
            'type',
            'min_coins',
            'max_coins',
            'delivery_mode',
            'featured',
            'sort',
        ]);
        $perPage = (int) $request->input('per_page', 20);

        $products = $this->catalogService->getProducts($filters, $perPage);

        return $this->success($products, 'Products retrieved successfully');
    }

    public function show(string $id): JsonResponse
    {
        $product = $this->catalogService->getProductById($id);
        if (! $product) {
            return $this->error('Product not found', 404);
        }

        return $this->success($product, 'Product details retrieved');
    }

    public function featured(): JsonResponse
    {
        $products = $this->catalogService->getFeaturedProducts(10);

        return $this->success($products, 'Featured products retrieved');
    }

    public function search(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('q', $request->input('search')),
            'category_id' => $request->input('category_id'),
            'type' => $request->input('type'),
        ];
        $perPage = (int) $request->input('per_page', 20);

        $products = $this->catalogService->getProducts($filters, $perPage);

        return $this->success($products, 'Search results retrieved');
    }
}
