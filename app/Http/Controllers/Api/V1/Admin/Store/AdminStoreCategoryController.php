<?php

namespace App\Http\Controllers\Api\V1\Admin\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\Admin\AdminCreateCategoryRequest;
use App\Models\Store\Product;
use App\Models\Store\StoreCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminStoreCategoryController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $categories = StoreCategory::withCount('products')
            ->with('parent')
            ->orderBy('sort_order', 'asc')
            ->get();

        return $this->success($categories, 'All categories retrieved');
    }

    public function store(AdminCreateCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $category = StoreCategory::create($data);

        return $this->success($category, 'Category created successfully', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $category = StoreCategory::findOrFail($id);
        $data = $request->all();
        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $category->update($data);

        return $this->success($category, 'Category updated successfully');
    }

    public function destroy(string $id): JsonResponse
    {
        $category = StoreCategory::findOrFail($id);

        DB::transaction(function () use ($category) {
            // Detach any products assigned to this category (set category_id to null)
            Product::where('category_id', $category->id)->update(['category_id' => null]);

            // Detach any child categories
            StoreCategory::where('parent_id', $category->id)->update(['parent_id' => null]);

            // Delete category image if local storage
            if ($category->image_url && str_starts_with($category->image_url, '/storage/')) {
                $storagePath = str_replace('/storage/', '', $category->image_url);
                Storage::disk('public')->delete($storagePath);
            }

            $category->delete();
        });

        return $this->success(['deleted' => true], 'Category deleted successfully');
    }
}

