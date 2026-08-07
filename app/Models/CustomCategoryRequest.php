<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomCategoryRequest extends Model
{
    use HasFactory;

    protected $table = 'custom_category_requests';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'level1_category_id',
        'category_name',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomCategoryRequest $model): void {
            if (blank($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function level1Category(): BelongsTo
    {
        return $this->belongsTo(CircleCategory::class, 'level1_category_id');
    }
}
