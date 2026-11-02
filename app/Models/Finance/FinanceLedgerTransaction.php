<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FinanceLedgerTransaction extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'finance_ledger_transactions';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'transaction_ref',
        'wallet_id',
        'transaction_type',
        'entry_category',
        'source_payment_id',
        'member_user_id',
        'introducer_user_id',
        'leader_user_id',
        'leader_tenure_id',
        'role_id',
        'role_code',
        'channel_id',
        'channel_code',
        'category_code',
        'rule_id',
        'scope_type',
        'scope_id',
        'base_amount',
        'applied_percentage',
        'amount',
        'opening_balance',
        'closing_balance',
        'status',
        'narration',
        'metadata',
        'created_by_admin_id',
        'created_at',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'applied_percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->transaction_ref)) {
                $model->transaction_ref = 'FIN-'.now()->format('YmdHis').'-'.strtoupper(Str::random(5));
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(FinanceWallet::class, 'wallet_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'source_payment_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_user_id');
    }

    public function introducer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'introducer_user_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    public function tenure(): BelongsTo
    {
        return $this->belongsTo(FinanceLeaderTenure::class, 'leader_tenure_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(FinanceAcquisitionChannel::class, 'channel_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(FinanceCommissionRule::class, 'rule_id');
    }
}
