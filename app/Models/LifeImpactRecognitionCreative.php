<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LifeImpactRecognitionCreative extends Model
{
    use HasFactory;

    protected $table = 'life_impact_recognition_creatives';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'threshold',
        'recognition_name',
        'file_id',
        'is_active',
    ];

    protected $casts = [
        'threshold' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(LifeImpactCreative::class, 'recognition_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForCount(Builder $query, int $count): Builder
    {
        return $query->where('is_active', true)
            ->where('threshold', '<=', $count)
            ->orderBy('threshold', 'desc');
    }
}
