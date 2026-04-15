<?php

namespace App\Models;

use App\Services\LifeImpact\LifeImpactService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BusinessDeal extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'business_deals';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'deal_date',
        'deal_amount',
        'business_type',
        'comment',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });

        static::deleted(function (self $model): void {
            app(LifeImpactService::class)->reverseBusinessDealLifeImpact(
                $model,
                Auth::id() ? (string) Auth::id() : null,
            );
        });
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
