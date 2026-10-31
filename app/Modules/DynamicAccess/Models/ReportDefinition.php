<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReportDefinition extends Model
{
    use HasFactory;

    protected $table = 'report_definitions';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'report_key',
        'name',
        'category',
        'description',
        'data_source_key',
        'default_scope_type',
        'filter_schema',
        'column_schema',
        'chart_schema',
        'supports_export',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'filter_schema' => 'array',
        'column_schema' => 'array',
        'chart_schema' => 'array',
        'supports_export' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RoleReportPermission::class, 'report_id');
    }
}

