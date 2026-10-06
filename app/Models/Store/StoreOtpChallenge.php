<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreOtpChallenge extends Model
{
    use HasUuids;

    protected $table = 'store_otp_challenges';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'purpose',
        'reference_type',
        'reference_id',
        'phone_hash',
        'otp_hash',
        'verification_token',
        'attempts',
        'max_attempts',
        'expires_at',
        'verified_at',
        'created_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
