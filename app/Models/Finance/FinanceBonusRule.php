<?php

declare(strict_types=1);

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FinanceBonusRule extends Model
{
    protected $table = 'finance_bonus_rules';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'rule_code',
        'bonus_category',
        'metric_type',
        'title',
        'description',
        'target_members_count',
        'target_circles_count',
        'min_avg_members_per_circle',
        'target_percentage',
        'target_meetings_count',
        'meeting_window_mode',
        'target_collaborations_count',
        'max_disputes_allowed',
        'timeframe_days',
        'bonus_amount_inr',
        'reward_perks',
        'exclusivity_group',
        'requires_approval',
        'is_once_per_tenure',
        'is_system',
        'is_active',
        'sort_order',
        'updated_by_admin_id',
    ];

    protected $casts = [
        'target_members_count' => 'integer',
        'target_circles_count' => 'integer',
        'min_avg_members_per_circle' => 'integer',
        'target_percentage' => 'decimal:2',
        'target_meetings_count' => 'integer',
        'target_collaborations_count' => 'integer',
        'max_disputes_allowed' => 'integer',
        'timeframe_days' => 'integer',
        'bonus_amount_inr' => 'decimal:2',
        'requires_approval' => 'boolean',
        'is_once_per_tenure' => 'boolean',
        'is_system' => 'boolean',
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

    public function roleConfigs(): HasMany
    {
        return $this->hasMany(FinanceBonusRoleConfig::class, 'bonus_rule_id');
    }

    public function awards(): HasMany
    {
        return $this->hasMany(FinanceLeaderBonusAward::class, 'bonus_rule_id');
    }
}

