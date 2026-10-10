<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use HasUuids;

    protected $table = 'order_status_history';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'note',
        'reason',
        'changed_by',
        'actor_type',
        'actor_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function getStatusAttribute(): ?string
    {
        return $this->to_status;
    }

    public function getNotesAttribute(): ?string
    {
        return $this->reason ?? $this->note;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
