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

    protected $fillable = [
        'id',
        'name',
        'logo',
        'industry',
        'sector',
        'location',
        'city',
        'website',
        'employee_count',
        'turnover',
        'description',
        'is_verified',
        'status',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebCompany $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
