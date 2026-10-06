<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use HasUuids;

    protected $table = 'receipts';

    const UPDATED_AT = null;

    protected $fillable = [
        'receipt_no',
        'order_id',
        'user_id',
        'coins_paid',
        'receipt_data',
        'policy_version_id',
        'issued_at',
        'created_at',
    ];

    protected $casts = [
        'coins_paid' => 'integer',
        'receipt_data' => 'array',
        'issued_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function policyPage(): BelongsTo
    {
        return $this->belongsTo(PolicyPage::class, 'policy_version_id');
    }
}
