<?php

declare(strict_types=1);

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WebOpportunity extends Model
{
    use HasFactory;

    protected $table = 'web_opportunities';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'requirements' => 'array',
        'tags' => 'array',
        'deadline' => 'date',
        'is_verified' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebOpportunity $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->attributes['code']) && Schema::hasColumn('web_opportunities', 'code')) {
                $model->code = 'OPP-'.random_int(100, 999);
            }
        });
    }

    public function getSectorAttribute(?string $value): string
    {
        return $value ?: ($this->attributes['category'] ?? ($this->attributes['deal_type'] ?? 'Growth'));
    }

    public function getValueAttribute(?string $value): string
    {
        return $value ?: ($this->attributes['deal_size'] ?? '₹ 2.5 Cr');
    }

    public function getProposerCompanyAttribute(?string $value): string
    {
        return $value ?: 'Peers Member Enterprise';
    }

    public function getCodeAttribute(?string $value): string
    {
        return $value ?: ('OPP-'.substr((string) ($this->id ?? '100'), 0, 4));
    }
}
