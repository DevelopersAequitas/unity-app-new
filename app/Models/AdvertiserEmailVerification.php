<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvertiserEmailVerification extends Model
{
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'advertiser_email_verifications';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'application_id',
        'email',
        'otp_hash',
        'expires_at',
        'verified_at',
        'attempts',
        'max_attempts',
        'consumed_at',
        'created_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'consumed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdvertiserApplication::class, 'application_id');
    }
}
