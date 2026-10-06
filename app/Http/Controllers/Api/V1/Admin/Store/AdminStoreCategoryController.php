<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\Admin\AdminCreateCategoryRequest;
use App\Models\Store\StoreCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreCategoryController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $categories = StoreCategory::with('parent')->orderBy('sort_order', 'asc')->get();

        return $this->success($categories, 'All categories retrieved');
    }

    public function store(AdminCreateCategoryRequest $request): JsonResponse
    {
        $category = StoreCategory::create($request->validated());

        return $this->success($category, 'Category created successfully', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $category = StoreCategory::findOrFail($id);
        $category->update($request->all());

        return $this->success($category, 'Category updated successfully');
    }

    public function destroy(string $id): JsonResponse
    {
        $category = StoreCategory::findOrFail($id);
        // Safe disable if products exist
        if ($category->products()->exists()) {
            $category->update(['is_active' => false]);

            return $this->success(['disabled' => true], 'Category disabled as products reference it');
        }

        $category->delete();

        return $this->success(['deleted' => true], 'Category deleted');
    }
}
