<?php

declare(strict_types=1);

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebPartnership extends Model
{
    use HasFactory;

    protected $table = 'web_partnerships';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'title',
        'company_a',
        'company_b',
        'sector',
        'route',
        'value',
        'numeric_value',
        'status',
        'stage',
        'progress_percent',
        'avatar_a',
        'avatar_b',
        'signed_date',
        'description',
        'synergies',
        'lead_manager',
    ];

    protected $casts = [
        'numeric_value' => 'decimal:2',
        'progress_percent' => 'integer',
        'signed_date' => 'date',
        'synergies' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebPartnership $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->code)) {
                $model->code = 'PTS-'.random_int(100, 999);
            }
        });
    }
}
