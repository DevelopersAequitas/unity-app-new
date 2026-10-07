<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasUuids;

    protected $table = 'products';

    protected $fillable = [
        'sku',
        'type',
        'name',
        'slug',
        'category_id',
        'short_description',
        'description',
        'tags',
        'brand',
        'status',
        'is_featured',
        'price_coins',
        'coin_price',
        'compare_coin_price',
        'unit_cost_inr',
        'delivery_modes',
        'max_quantity_per_order',
        'max_quantity_per_peer_month',
        'eligibility',
        'return_allowed',
        'customised',
        'stock_qty',
        'track_inventory',
        'allow_backorder',
        'weight_grams',
        'meta_title',
        'meta_description',
        'digital_asset_url',
        'digital_access_method',
        'content_location',
        'course_duration_months',
        'subscription_duration_months',
        'course_id',
        'level',
        'trainer',
        'sort_order',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'tags' => 'array',
        'delivery_modes' => 'array',
        'eligibility' => 'array',
        'is_featured' => 'boolean',
        'return_allowed' => 'boolean',
        'customised' => 'boolean',
        'track_inventory' => 'boolean',
        'allow_backorder' => 'boolean',
        'price_coins' => 'integer',
        'compare_coin_price' => 'integer',
        'stock_qty' => 'integer',
        'max_quantity_per_order' => 'integer',
        'max_quantity_per_peer_month' => 'integer',
        'weight_grams' => 'integer',
        'sort_order' => 'integer',
        'published_at' => 'datetime',
    ];

    protected $appends = [
        'coin_price',
    ];

    protected function coinPrice(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                if ($this->relationLoaded('variants') && $this->variants->isNotEmpty()) {
                    $activeVariants = $this->variants->filter(fn ($v) => (bool) $v->is_active);
                    if ($activeVariants->isNotEmpty()) {
                        $minPrice = $activeVariants->map(function ($v) {
                            return (int) ($v->coin_price ?? ($v->price_coins ?? 0));
                        })->filter(fn ($p) => $p > 0)->min();

                        if ($minPrice !== null && $minPrice > 0) {
                            return (int) $minPrice;
                        }
                    }
                }

                return (int) ($attributes['price_coins'] ?? ($attributes['coin_price'] ?? 0));
            },
            set: fn ($value) => [
                'price_coins' => (int) $value,
                'coin_price' => (int) $value,
            ]
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StoreCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class, 'product_id')->where('is_primary', true);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'product_id');
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'product_id')->where('status', 'APPROVED');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
