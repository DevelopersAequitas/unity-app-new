<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sector_id',
        'category_name',
        'sector',
        'remarks',
    ];

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['category_name'] ?? null,
            set: fn (?string $value) => ['category_name' => $value]
        );
    }

    protected function sectorId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['sector'] ?? null,
            set: fn (?string $value) => ['sector' => $value]
        );
    }

    public function circleMappings(): HasMany
    {
        return $this->hasMany(CircleCategoryMapping::class);
    }

    public function circles(): BelongsToMany
    {
        return $this->belongsToMany(Circle::class, 'circle_category_mappings', 'category_id', 'circle_id')
            ->withTimestamps();
    }

    public function eventGalleries(): HasMany
    {
        return $this->hasMany(EventGallery::class, 'circle_category_id');
    }
}
