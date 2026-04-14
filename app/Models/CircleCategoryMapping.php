<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CircleCategoryMapping extends Model
{
    use HasFactory;

    protected $table = 'circle_category_mappings';

    protected $fillable = [
        'circle_id',
        'category_id',
        'circle_category_id',
        'level2_id',
        'level3_id',
        'level4_id',
    ];

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CircleCategory::class, "category_id");
    }
}
