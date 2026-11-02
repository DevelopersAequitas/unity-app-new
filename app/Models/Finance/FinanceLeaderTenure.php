<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FinanceLeaderTenure extends Model
{
    use HasFactory;

    protected $table = 'finance_leader_tenures';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'admin_user_id',
        'role_id',
        'role_key',
        'role_code',
        'scope_type',
        'scope_id',
        'scope_name_snapshot',
        'tenure_start_at',
        'tenure_end_at',
        'expected_end_at',
        'is_active',
        'assigned_by_admin_id',
        'removed_by_admin_id',
        'assignment_source',
        'removal_reason',
    ];

    protected $casts = [
        'tenure_start_at' => 'datetime',
        'tenure_end_at' => 'datetime',
        'expected_end_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function ledgerTransactions(): HasMany
    {
        return $this->hasMany(FinanceLedgerTransaction::class, 'leader_tenure_id');
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(FinanceWithdrawalRequest::class, 'leader_tenure_id');
    }
}
