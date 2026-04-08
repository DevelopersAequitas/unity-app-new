<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CircleCategory extends Model
{
    use HasFactory;

    protected $table = 'circle_categories';

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'level',
        'circle_key',
        'sort_order',
        'is_active',
        'remarks',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'level' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
