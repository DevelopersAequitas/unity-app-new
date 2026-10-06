<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\Product;
use App\Models\Store\ProductImage;
use App\Models\Store\ProductVariant;
use App\Models\Store\StoreCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AdminStoreCatalogWebController extends Controller
{
    // ==========================================
    // CATEGORIES MANAGEMENT
    // ==========================================

    public function categories(Request $request)
    {
        $query = StoreCategory::withCount('products')->orderBy('sort_order', 'asc');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('name', 'ILIKE', "%{$s}%")->orWhere('slug', 'ILIKE', "%{$s}%");
        }

        $categories = $query->paginate(15);

        return view('admin.store.catalog.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:120|unique:store_categories,slug',
            'description' => 'nullable|string|max:500',
            'image_url' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
            'status' => 'nullable|string|in:ACTIVE,INACTIVE',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }
        $validated['status'] = $validated['status'] ?? 'ACTIVE';
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        StoreCategory::create($validated);

        return redirect()->route('admin.store.categories.index')->with('success', 'Category created successfully.');
    }

    public function updateCategory(Request $request, string $id)
    {
        $category = StoreCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:120|unique:store_categories,slug,'.$id,
            'description' => 'nullable|string|max:500',
            'image_url' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
            'status' => 'required|string|in:ACTIVE,INACTIVE',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return redirect()->route('admin.store.categories.index')->with('success', 'Category updated successfully.');
    }

    public function deleteCategory(string $id)
    {
        $category = StoreCategory::withCount('products')->findOrFail($id);

        if ($category->products_count > 0) {
            return back()->with('error', 'Cannot delete category with active products. Reassign products first or deactivate category.');
        }

        $category->delete();

        return redirect()->route('admin.store.categories.index')->with('success', 'Category deleted successfully.');
    }

    // ==========================================
    // PRODUCTS MANAGEMENT
    // ==========================================

    public function products(Request $request)
    {
        $query = Product::with(['category', 'primaryImage', 'variants'])->orderBy('created_at', 'desc');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'ILIKE', "%{$s}%")
                    ->orWhere('sku', 'ILIKE', "%{$s}%")
                    ->orWhere('short_description', 'ILIKE', "%{$s}%");
            });
        }

        $products = $query->paginate(20);
        $categories = StoreCategory::where('status', 'ACTIVE')->orderBy('name')->get();

        return view('admin.store.catalog.products', compact('products', 'categories'));
    }

    public function createProduct()
    {
        $categories = StoreCategory::where('status', 'ACTIVE')->orderBy('name')->get();

        return view('admin.store.catalog.product-form', [
            'product' => new Product,
            'categories' => $categories,
            'isEdit' => false,
        ]);
    }

    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:50|unique:products,sku',
            'name' => 'required|string|max:200',
            'slug' => 'nullable|string|max:250|unique:products,slug',
            'category_id' => 'required|uuid|exists:store_categories,id',
            'type' => 'required|string|in:PHYSICAL,DIGITAL,COURSE,SUBSCRIPTION,MEMBERSHIP',
            'price_coins' => 'required|integer|min:0',
            'unit_cost_inr' => 'nullable|numeric|min:0',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'delivery_modes' => 'required|array',
            'max_quantity_per_order' => 'nullable|integer|min:1',
            'max_quantity_per_peer_month' => 'nullable|integer|min:1',
            'return_allowed' => 'nullable|boolean',
            'customised' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'status' => 'required|string|in:DRAFT,ACTIVE,HIDDEN,OUT_OF_STOCK,ARCHIVED',
            'stock_qty' => 'nullable|integer|min:0',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }
        $validated['return_allowed'] = $request->has('return_allowed');
        $validated['customised'] = $request->has('customised');
        $validated['is_featured'] = $request->has('is_featured');
        $validated['stock_qty'] = $validated['stock_qty'] ?? 0;
        $validated['created_by'] = Auth::guard('admin')->id();

        $product = Product::create($validated);

        // Handle variant default creation
        if ($request->filled('initial_variant_name')) {
            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $product->sku.'-STD',
                'name' => $request->initial_variant_name,
                'price_coins' => $product->price_coins,
                'stock_quantity' => $product->stock_qty,
                'status' => 'ACTIVE',
            ]);
        }

        // Handle uploaded images
        if ($request->filled('images')) {
            foreach ((array) $request->images as $idx => $imgUrl) {
                if (! empty($imgUrl)) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_url' => $imgUrl,
                        'is_primary' => $idx === 0,
                        'sort_order' => $idx + 1,
                    ]);
                }
            }
        }

        return redirect()->route('admin.store.products.edit', $product->id)->with('success', 'Product created successfully.');
    }

    public function editProduct(string $id)
    {
        $product = Product::with(['category', 'images', 'variants'])->findOrFail($id);
        $categories = StoreCategory::where('status', 'ACTIVE')->orderBy('name')->get();

        return view('admin.store.catalog.product-form', [
            'product' => $product,
            'categories' => $categories,
            'isEdit' => true,
        ]);
    }

    public function updateProduct(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'sku' => 'required|string|max:50|unique:products,sku,'.$id,
            'name' => 'required|string|max:200',
            'slug' => 'nullable|string|max:250|unique:products,slug,'.$id,
            'category_id' => 'required|uuid|exists:store_categories,id',
            'type' => 'required|string|in:PHYSICAL,DIGITAL,COURSE,SUBSCRIPTION,MEMBERSHIP',
            'price_coins' => 'required|integer|min:0',
            'unit_cost_inr' => 'nullable|numeric|min:0',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'delivery_modes' => 'required|array',
            'max_quantity_per_order' => 'nullable|integer|min:1',
            'max_quantity_per_peer_month' => 'nullable|integer|min:1',
            'return_allowed' => 'nullable|boolean',
            'customised' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'status' => 'required|string|in:DRAFT,ACTIVE,HIDDEN,OUT_OF_STOCK,ARCHIVED',
            'stock_qty' => 'nullable|integer|min:0',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }
        $validated['return_allowed'] = $request->has('return_allowed');
        $validated['customised'] = $request->has('customised');
        $validated['is_featured'] = $request->has('is_featured');

        $product->update($validated);

        return redirect()->route('admin.store.products.edit', $product->id)->with('success', 'Product updated successfully.');
    }

    public function archiveProduct(string $id)
    {
        $product = Product::findOrFail($id);
        $product->update(['status' => 'ARCHIVED']);

        return redirect()->route('admin.store.products.index')->with('success', 'Product archived successfully.');
    }

    // ==========================================
    // VARIANTS & IMAGES MANAGEMENT
    // ==========================================

    public function storeVariant(Request $request, string $productId)
    {
        $product = Product::findOrFail($productId);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'sku' => 'required|string|max:50|unique:product_variants,sku',
            'price_coins' => 'nullable|integer|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'status' => 'required|string|in:ACTIVE,INACTIVE',
        ]);

        $validated['product_id'] = $product->id;
        $validated['price_coins'] = $validated['price_coins'] ?? $product->price_coins;
        $validated['low_stock_threshold'] = $validated['low_stock_threshold'] ?? 5;

        ProductVariant::create($validated);

        return back()->with('success', 'Variant added successfully.');
    }

    public function updateVariant(Request $request, string $variantId)
    {
        $variant = ProductVariant::findOrFail($variantId);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'sku' => 'required|string|max:50|unique:product_variants,sku,'.$variantId,
            'price_coins' => 'nullable|integer|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'status' => 'required|string|in:ACTIVE,INACTIVE',
        ]);

        $variant->update($validated);

        return back()->with('success', 'Variant updated successfully.');
    }

    public function deleteVariant(string $variantId)
    {
        $variant = ProductVariant::findOrFail($variantId);
        $variant->delete();

        return back()->with('success', 'Variant deleted successfully.');
    }

    public function addImage(Request $request, string $productId)
    {
        $product = Product::findOrFail($productId);

        $request->validate([
            'image_url' => 'required|string|max:500',
            'is_primary' => 'nullable|boolean',
        ]);

        $isPrimary = $request->has('is_primary');
        if ($isPrimary) {
            ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);
        }

        ProductImage::create([
            'product_id' => $product->id,
            'image_url' => $request->image_url,
            'is_primary' => $isPrimary,
            'sort_order' => ProductImage::where('product_id', $product->id)->count() + 1,
        ]);

        return back()->with('success', 'Product image added.');
    }

    public function deleteImage(string $imageId)
    {
        $img = ProductImage::findOrFail($imageId);
        $img->delete();

        return back()->with('success', 'Product image deleted.');
    }
}
