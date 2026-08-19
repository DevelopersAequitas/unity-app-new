<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PeerReferral extends Model
{
    protected $table = 'peer_referrals';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'referrer_user_id',
        'referred_name',
        'referred_phone',
        'referred_email',
        'referred_company_name',
        'referred_designation',
        'main_circle_id',
        'circle_id',
        'open_category_id',
        'message',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = 'pending';
            }
        });
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function mainCircle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'main_circle_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CircleCategory::class, 'open_category_id');
    }
}
