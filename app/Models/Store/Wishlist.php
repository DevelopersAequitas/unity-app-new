<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends Model
{
    use HasUuids;

    protected $table = 'store_wishlists';

    protected $fillable = [
        'user_id',
        'product_id',
        'variant_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithFullDetails(Builder $query): Builder
    {
        return $query->with([
            'product' => function ($q) {
                $q->with(['primaryImage', 'images', 'category']);
            },
            'variant',
            'user:id,first_name,last_name,display_name,email,phone,company_name,coins_balance,profile_photo_url',
        ]);
    }
}
