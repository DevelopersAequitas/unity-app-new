<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasUuids;

    protected $table = 'inventory_movements';

    public $timestamps = false;

    protected $fillable = [
        'variant_id',
        'product_variant_id',
        'quantity_change',
        'quantity_after',
        'balance_after',
        'reason',
        'reference_type',
        'reference_id',
        'reference',
        'actor_type',
        'actor_id',
        'note',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'quantity_after' => 'integer',
        'created_at' => 'datetime',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
