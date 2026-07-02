<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandPartnerCouponRedemption extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'brand_partner_coupon_redemptions';

    protected $keyType = 'string';

    public $incrementing = false;

    public const CREATED_AT = 'redeemed_at';

    public const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'brand_partner_id',
        'user_id',
        'coupon_code',
        'redeemed_at',
        'metadata',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function brandPartner(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class, 'brand_partner_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
