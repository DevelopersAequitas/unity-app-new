<?php

declare(strict_types=1);

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebCompany extends Model
{
    use HasFactory;

    protected $table = 'web_companies';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebCompany $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->attributes['slug']) && \Illuminate\Support\Facades\Schema::hasColumn('web_companies', 'slug')) {
                $model->slug = Str::slug($model->name ?? 'company').'-'.random_int(100, 999);
            }
        });
    }

    public function getSectorAttribute(?string $value): string
    {
        return $value ?: ($this->attributes['industry'] ?? 'Corporate');
    }

    public function getCityAttribute(?string $value): string
    {
        return $value ?: ($this->attributes['headquarters'] ?? ($this->attributes['location'] ?? 'Ahmedabad'));
    }

    public function getTurnoverAttribute(?string $value): string
    {
        return $value ?: ($this->attributes['revenue_range'] ?? '₹ 10 Cr+');
    }
}
