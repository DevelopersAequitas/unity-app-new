<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FinanceLeaderBonusAward extends Model
{
    protected $table = 'finance_leader_bonus_awards';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'award_ref',
        'bonus_rule_id',
        'role_config_id',
        'leader_user_id',
        'leader_tenure_id',
        'role_code',
        'scope_type',
        'scope_id',
        'counting_started_at',
        'deadline_at',
        'current_metric_value',
        'target_metric_value',
        'progress_percentage',
        'bonus_amount_inr',
        'reward_perks',
        'status',
        'achieved_at',
        'approved_by_admin_id',
        'approved_at',
        'wallet_id',
        'ledger_transaction_id',
        'proof_metadata',
        'admin_notes',
    ];

    protected $casts = [
        'counting_started_at' => 'datetime',
        'deadline_at' => 'datetime',
        'current_metric_value' => 'decimal:2',
        'target_metric_value' => 'decimal:2',
        'progress_percentage' => 'decimal:2',
        'bonus_amount_inr' => 'decimal:2',
        'achieved_at' => 'datetime',
        'approved_at' => 'datetime',
        'proof_metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->award_ref)) {
                $model->award_ref = 'BNS-'.now()->format('YmdHis').'-'.strtoupper(Str::random(5));
            }
        });
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(FinanceBonusRule::class, 'bonus_rule_id');
    }

    public function roleConfig(): BelongsTo
    {
        return $this->belongsTo(FinanceBonusRoleConfig::class, 'role_config_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    public function tenure(): BelongsTo
    {
        return $this->belongsTo(FinanceLeaderTenure::class, 'leader_tenure_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(FinanceWallet::class, 'wallet_id');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(FinanceLedgerTransaction::class, 'ledger_transaction_id');
    }
}

