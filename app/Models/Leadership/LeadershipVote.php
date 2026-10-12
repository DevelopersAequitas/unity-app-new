<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LeadershipVote extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'leadership_votes';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'campaign_id',
        'scope_id',
        'nomination_id',
        'voter_user_id',
        'voter_contact_hash',
        'verification_id',
        'vote_reference',
        'cast_at',
        'metadata',
    ];

    protected $casts = [
        'cast_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->vote_reference)) {
                $model->vote_reference = (string) Str::uuid();
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

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(LeadershipNomination::class, 'nomination_id');
    }

    public function voterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_user_id');
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(LeadershipVoterVerification::class, 'verification_id');
    }
}
