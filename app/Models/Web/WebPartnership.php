<?php

declare(strict_types=1);

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WebPartnership extends Model
{
    use HasFactory;

    protected $table = 'web_partnerships';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'numeric_value' => 'decimal:2',
        'progress_percent' => 'integer',
        'signed_date' => 'date',
        'synergies' => 'array',
        'is_featured' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebPartnership $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->attributes['code']) && Schema::hasColumn('web_partnerships', 'code')) {
                $model->code = 'PTS-'.random_int(100, 999);
            }
        });
    }

    public function getTitleAttribute(?string $value): string
    {
        return $value ?: ($this->attributes['company_name'] ?? 'Strategic Partnership');
    }

    public function getCompanyAAttribute(?string $value): string
    {
        return $value ?: ($this->attributes['company_name'] ?? 'Enterprise Partner');
    }

    public function getCompanyBAttribute(?string $value): string
    {
        return $value ?: 'Peers Global Network';
    }

    public function getSectorAttribute(?string $value): string
    {
        return $value ?: ($this->attributes['industry'] ?? ($this->attributes['partnership_tier'] ?? 'Strategic'));
    }

    public function getCodeAttribute(?string $value): string
    {
        return $value ?: ('PTS-'.substr((string) ($this->id ?? '100'), 0, 4));
    }
}
