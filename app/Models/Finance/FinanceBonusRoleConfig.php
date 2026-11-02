<?php

declare(strict_types=1);

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FinanceBonusRoleConfig extends Model
{
    protected $table = 'finance_bonus_role_configs';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'bonus_rule_id',
        'role_code',
        'scope_type',
        'is_active',
        'activated_at',
        'deactivated_at',
        'override_bonus_amount_inr',
        'override_target_members',
        'override_timeframe_days',
        'updated_by_admin_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'override_bonus_amount_inr' => 'decimal:2',
        'override_target_members' => 'integer',
        'override_timeframe_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(FinanceBonusRule::class, 'bonus_rule_id');
    }
}

