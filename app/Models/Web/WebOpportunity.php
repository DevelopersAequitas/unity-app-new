<?php

declare(strict_types=1);

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebOpportunity extends Model
{
    use HasFactory;

    protected $table = 'web_opportunities';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'title',
        'sector',
        'value',
        'location',
        'status',
        'description',
        'requirements',
        'tags',
        'proposer_name',
        'proposer_company',
        'deadline',
    ];

    protected $casts = [
        'requirements' => 'array',
        'tags' => 'array',
        'deadline' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebOpportunity $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->code)) {
                $model->code = 'OPP-'.random_int(100, 999);
            }
        });
    }
}
