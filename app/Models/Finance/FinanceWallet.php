<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FinanceWallet extends Model
{
    use HasFactory;

    protected $table = 'finance_wallets';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'account_number',
        'wallet_type',
        'scope_type',
        'scope_id',
        'wallet_name',
        'current_custodian_user_id',
        'currency',
        'current_balance',
        'total_credited',
        'total_debited',
        'locked_balance',
        'credit_count',
        'debit_count',
        'status',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'total_credited' => 'decimal:2',
        'total_debited' => 'decimal:2',
        'locked_balance' => 'decimal:2',
        'credit_count' => 'integer',
        'debit_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->account_number)) {
                $prefix = match ($model->wallet_type) {
                    'circle_budget' => 'WLT-CIR',
                    'district_budget' => 'WLT-DST',
                    'industry_budget' => 'WLT-IND',
                    'leader_wallet' => 'WLT-LDR',
                    default => 'WLT-GLB',
                };
                $model->account_number = $prefix.'-'.strtoupper(Str::random(6));
            }
        });
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_custodian_user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinanceLedgerTransaction::class, 'wallet_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(FinanceWithdrawalRequest::class, 'wallet_id');
    }
}

