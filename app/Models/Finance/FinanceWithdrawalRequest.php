<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FinanceWithdrawalRequest extends Model
{
    use HasFactory;

    protected $table = 'finance_withdrawal_requests';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'request_number',
        'wallet_id',
        'leader_user_id',
        'leader_tenure_id',
        'amount',
        'purpose',
        'notes',
        'status',
        'payout_method',
        'payout_reference_utr',
        'receipt_file_id',
        'ledger_transaction_id',
        'approved_by_admin_id',
        'approved_at',
        'paid_at',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->request_number)) {
                $model->request_number = 'WDR-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
            }
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(FinanceWallet::class, 'wallet_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    public function tenure(): BelongsTo
    {
        return $this->belongsTo(FinanceLeaderTenure::class, 'leader_tenure_id');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(FinanceLedgerTransaction::class, 'ledger_transaction_id');
    }
}

