<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FinanceCommissionRule extends Model
{
    use HasFactory;

    protected $table = 'finance_commission_rules';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'role_id',
        'role_code',
        'role_name',
        'channel_id',
        'category_code',
        'channel_key',
        'allocation_target',
        'base_amount_inr',
        'share_percentage',
        'payout_amount',
        'conditions_list',
        'responsibilities_list',
        'is_active',
        'effective_from',
        'effective_to',
        'updated_by_admin_id',
    ];

    protected $casts = [
        'base_amount_inr' => 'decimal:2',
        'share_percentage' => 'decimal:2',
        'payout_amount' => 'decimal:2',
        'conditions_list' => 'array',
        'responsibilities_list' => 'array',
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
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

    public function channel(): BelongsTo
    {
        return $this->belongsTo(FinanceAcquisitionChannel::class, 'channel_id');
    }
}

