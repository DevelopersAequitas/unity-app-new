<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Models;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RoleReportPermission extends Model
{
    use HasFactory;

    protected $table = 'role_report_permissions';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'role_id',
        'report_id',
        'can_view',
        'can_export',
        'can_schedule',
        'allowed_scope_override',
        'visible_columns',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_export' => 'boolean',
        'can_schedule' => 'boolean',
        'visible_columns' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ReportDefinition::class, 'report_id');
    }
}

