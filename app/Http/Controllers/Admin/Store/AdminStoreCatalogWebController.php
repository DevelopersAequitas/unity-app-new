<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\Store\Product;
use App\Models\Store\ProductImage;
use App\Models\Store\ProductVariant;
use App\Models\Store\StoreCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
            'image_file' => 'nullable|image|max:10240',
            'image_data' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'status' => 'nullable|string|in:ACTIVE,INACTIVE',
        ]);

        if (! empty($request->image_data) && str_starts_with($request->image_data, 'data:image')) {
            $parts = explode(',', $request->image_data);
            $decoded = base64_decode(end($parts));
            $filename = 'store/categories/'.Str::uuid().'.jpg';
            Storage::disk('public')->put($filename, $decoded);
            $validated['image_url'] = '/storage/'.$filename;
        } elseif ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('store/categories', 'public');
            $validated['image_url'] = '/storage/'.$path;
        }

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }
        $validated['status'] = $validated['status'] ?? 'ACTIVE';
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        unset($validated['image_file'], $validated['image_data']);
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
            'image_file' => 'nullable|image|max:10240',
            'image_data' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'status' => 'required|string|in:ACTIVE,INACTIVE',
        ]);

        if (! empty($request->image_data) && str_starts_with($request->image_data, 'data:image')) {
            $parts = explode(',', $request->image_data);
            $decoded = base64_decode(end($parts));
            $filename = 'store/categories/'.Str::uuid().'.jpg';
            Storage::disk('public')->put($filename, $decoded);
            $validated['image_url'] = '/storage/'.$filename;
        } elseif ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('store/categories', 'public');
            $validated['image_url'] = '/storage/'.$path;
        }

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        unset($validated['image_file'], $validated['image_data']);
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
        $request->merge([
            'return_allowed' => $request->boolean('return_allowed'),
            'customised' => $request->boolean('customised'),
            'is_featured' => $request->boolean('is_featured'),
        ]);

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
            'min_quantity_per_order' => 'nullable|integer|min:1',
            'max_quantity_per_order' => 'nullable|integer|min:1|gte:min_quantity_per_order',
            'max_quantity_per_peer_month' => 'nullable|integer|min:1',
            'return_allowed' => 'nullable|boolean',
            'customised' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'status' => 'required|string|in:DRAFT,ACTIVE,HIDDEN,OUT_OF_STOCK,ARCHIVED',
            'stock_qty' => 'nullable|integer|min:0',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name']);
        } else {
            $validated['slug'] = $this->generateUniqueSlug($validated['slug']);
        }
        $validated['return_allowed'] = $request->boolean('return_allowed');
        $validated['customised'] = $request->boolean('customised');
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['stock_qty'] = $validated['stock_qty'] ?? 0;
        $validated['coin_price'] = $validated['price_coins'];
        $validated['created_by'] = Auth::guard('admin')->id();
        $validated['track_inventory'] = true;
        $validated['allow_backorder'] = false;
        $validated['sort_order'] = 0;
        $validated['eligibility'] = ['all'];
        $validated['min_quantity_per_order'] = $validated['min_quantity_per_order'] ?? 1;
        $validated['max_quantity_per_order'] = $validated['max_quantity_per_order'] ?? 10;
        $validated['delivery_modes'] = ! empty($request->delivery_modes) ? (array) $request->delivery_modes : ['COURIER'];

        $product = Product::create($validated);

        // Handle variant default creation
        if ($request->filled('initial_variant_name')) {
            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $product->sku.'-STD',
                'name' => $request->initial_variant_name,
                'attributes' => ['default' => true],
                'price_coins' => $product->price_coins,
                'coin_price' => $product->price_coins,
                'stock_quantity' => $product->stock_qty,
                'stock_qty' => $product->stock_qty,
                'reserved_quantity' => 0,
                'low_stock_threshold' => 5,
                'allow_backorder' => false,
                'is_active' => true,
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

        $request->merge([
            'return_allowed' => $request->boolean('return_allowed'),
            'customised' => $request->boolean('customised'),
            'is_featured' => $request->boolean('is_featured'),
        ]);

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
            'min_quantity_per_order' => 'nullable|integer|min:1',
            'max_quantity_per_order' => 'nullable|integer|min:1|gte:min_quantity_per_order',
            'max_quantity_per_peer_month' => 'nullable|integer|min:1',
            'return_allowed' => 'nullable|boolean',
            'customised' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'status' => 'required|string|in:DRAFT,ACTIVE,HIDDEN,OUT_OF_STOCK,ARCHIVED,INACTIVE',
            'stock_qty' => 'nullable|integer|min:0',
        ]);

        if (($validated['status'] ?? '') === 'INACTIVE') {
            $validated['status'] = 'ARCHIVED';
        }

        if (empty($validated['slug'])) {
            $validated['slug'] = $product->slug ?: $this->generateUniqueSlug($validated['name'], $product->id);
        } else {
            $validated['slug'] = $this->generateUniqueSlug($validated['slug'], $product->id);
        }
        $validated['return_allowed'] = $request->boolean('return_allowed');
        $validated['customised'] = $request->boolean('customised');
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['coin_price'] = $validated['price_coins'];

        $product->update($validated);

        $this->syncProductParentPrice($product);

        return redirect()->route('admin.store.products.edit', $product->id)->with('success', 'Product updated successfully.');
    }

    public function archiveProduct(string $id)
    {
        $product = Product::findOrFail($id);
        $product->update(['status' => 'ARCHIVED']);

        return redirect()->route('admin.store.products.index')->with('success', 'Product archived successfully.');
    }

    public function deleteProduct(string $id)
    {
        $product = Product::with(['images', 'variants'])->findOrFail($id);

        if ($product->orderItems()->exists()) {
            return back()->with('error', 'Cannot delete this product because customer orders are linked to it. Please Archive it instead.');
        }

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

        return redirect()->route('admin.store.products.index')->with('success', 'Product permanently deleted successfully.');
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

        $price = $validated['price_coins'] ?? ($product->price_coins ?: $product->coin_price);
        if (! $price || $price <= 0) {
            $price = (int) ($product->coin_price ?: ($product->price_coins ?: 1));
        }

        $validated['product_id'] = $product->id;
        $validated['price_coins'] = $price;
        $validated['coin_price'] = $price;
        $validated['stock_qty'] = $validated['stock_quantity'];
        $validated['low_stock_threshold'] = $validated['low_stock_threshold'] ?? 5;

        ProductVariant::create($validated);

        $this->syncProductParentPrice($product);

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

        if (isset($validated['price_coins'])) {
            $price = (int) $validated['price_coins'];
            if ($price <= 0 && $variant->product) {
                $price = (int) ($variant->product->coin_price ?: $variant->product->price_coins);
            }
            $validated['price_coins'] = $price;
            $validated['coin_price'] = $price;
        }
        if (isset($validated['stock_quantity'])) {
            $validated['stock_qty'] = $validated['stock_quantity'];
        }

        $variant->update($validated);

        if ($variant->product) {
            $this->syncProductParentPrice($variant->product);
        }

        return back()->with('success', 'Variant updated successfully.');
    }

    public function deleteVariant(string $variantId)
    {
        $variant = ProductVariant::findOrFail($variantId);
        $product = $variant->product;
        $variant->delete();

        if ($product) {
            $this->syncProductParentPrice($product);
        }

        return back()->with('success', 'Variant deleted successfully.');
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

    protected function generateUniqueSlug(string $name, ?string $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        if (empty($baseSlug)) {
            $baseSlug = 'product-'.Str::random(6);
        }

        $slug = $baseSlug;
        $counter = 1;

        while (
            Product::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    public function addImage(Request $request, string $productId)
    {
        $product = Product::findOrFail($productId);

        $request->validate([
            'image_url' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|max:10240',
            'image_data' => 'nullable|string',
            'is_primary' => 'nullable|boolean',
        ]);

        $imageUrl = $request->image_url;

        if (! empty($request->image_data) && str_starts_with($request->image_data, 'data:image')) {
            $parts = explode(',', $request->image_data);
            $decoded = base64_decode(end($parts));
            $filename = 'store/products/'.Str::uuid().'.jpg';
            Storage::disk('public')->put($filename, $decoded);
            $imageUrl = '/storage/'.$filename;
        } elseif ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('store/products', 'public');
            $imageUrl = '/storage/'.$path;
        }

        if (empty($imageUrl)) {
            return back()->with('error', 'Please provide an image file or URL.');
        }

        $isPrimary = $request->has('is_primary');
        if ($isPrimary) {
            ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);
        }

        ProductImage::create([
            'product_id' => $product->id,
            'image_url' => $imageUrl,
            'is_primary' => $isPrimary,
            'sort_order' => ProductImage::where('product_id', $product->id)->count() + 1,
        ]);

        return back()->with('success', 'Product image added successfully.');
    }

    public function deleteImage(string $imageId)
    {
        $img = ProductImage::findOrFail($imageId);
        $img->delete();

        return back()->with('success', 'Product image deleted.');
    }
}
