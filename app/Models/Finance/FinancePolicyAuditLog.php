<?php

declare(strict_types=1);

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FinancePolicyAuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'finance_policy_audit_logs';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'admin_user_id',
        'action',
        'target_table',
        'target_id',
        'before_snapshot',
        'after_snapshot',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'before_snapshot' => 'array',
        'after_snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }
}

