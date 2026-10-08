<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinClaimRequest extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'coin_claim_requests';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'activity_code',
        'payload',
        'status',
        'coins_awarded',
        'admin_notes',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'coins_awarded' => 'integer',
    ];

protected static function booted(): void
    {
        static::created(function (self $request): void {
            try {
                broadcast(new \App\Events\PendingRequestChangedEvent(
                    action: 'created',
                    category: 'coin_claims',
                    requestId: (string) $request->id,
                    itemData: [
                        'id' => (string) $request->id,
                        'category' => 'coin_claims',
                        'submitted_at' => optional($request->created_at)->toISOString() ?? now()->toISOString(),
                    ]
                ));
            } catch (\Throwable) {}
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
