<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Industry extends Model
{
    use HasFactory;

    protected $table = 'industries';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'parent_id',
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function circles(): HasMany
    {
        $relation = $this->hasMany(Circle::class, 'id', 'id');

        if (! Schema::hasColumn('circles', 'industry_tags')) {
            return $relation->whereRaw('1 = 0');
        }

        $industryId = (string) $this->getKey();
        $industryName = trim((string) $this->name);

        return $relation->where(function ($query) use ($industryId, $industryName): void {
            $query->whereJsonContains('industry_tags', $industryId);

            if ($industryName !== '') {
                $query->orWhereJsonContains('industry_tags', $industryName);
            }
        });
    }
}
