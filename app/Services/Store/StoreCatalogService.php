<?php

namespace App\Services\Store;

use App\Models\Store\Product;
use App\Models\Store\StoreBanner;
use App\Models\Store\StoreCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class StoreCatalogService
{
    public function getCategories(): Collection
    {
        return StoreCategory::query()
            ->active()
            ->withCount(['products' => function ($q) {
                $q->where('status', 'ACTIVE');
            }])
            ->orderBy('sort_order', 'asc')
            ->get();
    }

    public function getBanners(int $perPage = 20): LengthAwarePaginator
    {
        return StoreBanner::query()
            ->active()
            ->orderBy('sort_order', 'asc')
            ->paginate($perPage);
    }

    public function getProducts(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Product::query()
            ->active()
            ->with(['primaryImage', 'category', 'variants' => function ($q) {
                $q->active();
            }]);

        if (! empty($filters['category_id']) && \Illuminate\Support\Str::isUuid($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['featured'])) {
            $query->where('is_featured', true);
        }

        if (! empty($filters['min_coins'])) {
            $query->where('price_coins', '>=', (int) $filters['min_coins']);
        }

        if (! empty($filters['max_coins'])) {
            $query->where('price_coins', '<=', (int) $filters['max_coins']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('short_description', 'ILIKE', "%{$search}%")
                    ->orWhere('sku', 'ILIKE', "%{$search}%");
            });
        }

        $sort = $filters['sort'] ?? 'latest';
        match ($sort) {
            'price_asc' => $query->orderBy('price_coins', 'asc'),
            'price_desc' => $query->orderBy('price_coins', 'desc'),
            'popular' => $query->orderBy('sort_order', 'asc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        return $query->paginate($perPage);
    }

    public function getProductById(string $id): ?Product
    {
        return Product::query()
            ->active()
            ->with([
                'category',
                'images',
                'variants' => function ($q) {
                    $q->active();
                },
                'approvedReviews.user',
            ])
            ->where('id', $id)
            ->first();
    }

    public function getFeaturedProducts(int $limit = 10): Collection
    {
        return Product::query()
            ->active()
            ->featured()
            ->with(['primaryImage', 'category'])
            ->orderBy('sort_order', 'asc')
            ->limit($limit)
            ->get();
    }
}
