<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class LeadershipVoterVerification extends Model
{
    use HasFactory;

    protected $table = 'leadership_voter_verifications';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'campaign_id',
        'scope_id',
        'user_id',
        'contact_type',
        'contact_hash',
        'contact_masked',
        'otp_hash',
        'status',
        'attempt_count',
        'expires_at',
        'verified_at',
        'consumed_at',
        'provider_reference',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LeadershipCampaign::class, 'campaign_id');
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(LeadershipCampaignScope::class, 'scope_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vote(): HasOne
    {
        return $this->hasOne(LeadershipVote::class, 'verification_id');
    }
}
